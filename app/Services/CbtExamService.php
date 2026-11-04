<?php

namespace App\Services;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Services\Exam\DistributedConsistencyGuard;
use App\Services\Exam\ExamAnswerBuffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbtExamService
{
    public function __construct(
        private CbtEngineBridge $bridge,
        private ExamAnswerBuffer $buffer,
        private DistributedConsistencyGuard $guard,
    ) {}

    private function pakaiEngineBaru(): bool
    {
        return (bool) config('simantap.exam_engine_cbt', true);
    }

    private function pakaiBufferAsinkron(): bool
    {
        return (bool) config('simantap.cbt_buffer_async', false);
    }

    public function publish(CbtTestSession $session): void
    {
        DB::transaction(function () use ($session) {
            // Urutan lock: bank -> sesi -> hasil. Mutasi soal juga mengunci bank.
            $bank = CbtQuestionBank::whereKey($session->bank_id)->lockForUpdate()->firstOrFail();
            $session = CbtTestSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'draft', 409, 'Sesi sudah dipublikasikan.');
            $questions = $bank->questions()->orderBy('id')->get();
            $participants = Siswa::where('kelas', $session->kelas)
                ->where(function ($q) use ($session) {
                    $q->where('guru_id', $session->guru_id)
                      ->orWhereNotNull('dapodik_id')
                      ->orWhereNull('guru_id');
                })
                ->where('aktif', true)->whereNull('archived_at')->orderBy('id')->pluck('id')->all();
            if ($questions->isEmpty() || $questions->count() > 200 || !$participants || count($participants) > 500) {
                throw ValidationException::withMessages(['session' => 'Butuh 1–200 soal dan 1–500 siswa aktif pada kelas tersebut.']);
            }
            if (now()->greaterThanOrEqualTo($session->closes_at)) {
                throw ValidationException::withMessages(['session' => 'Waktu tutup sesi sudah lewat.']);
            }
            $session->update([
                'status' => 'published',
                // Jangan memakai toArray(): model menyembunyikan correct_answer.
                'question_snapshot' => $questions->map(fn ($q) => [
                    'id' => $q->id, 'prompt' => $q->prompt,
                    'options' => $q->options, 'correct_answer' => $q->correct_answer,
                ])->all(),
                'participant_ids' => array_map('intval', $participants),
            ]);
        }, 3);
    }

    public function open(CbtTestSession $session): void
    {
        DB::transaction(function () use ($session) {
            $session = CbtTestSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'published', 409, 'Publikasikan sesi terlebih dahulu.');
            abort_if(now()->greaterThanOrEqualTo($session->closes_at), 409, 'Sesi sudah kedaluwarsa.');
            $session->update(['status' => 'running']);
        }, 3);
    }

    public function eligible(CbtTestSession $session, Siswa $siswa): bool
    {
        if (!$siswa->aktif || $siswa->archived_at) {
            return false;
        }

        if (!in_array((int) $siswa->id, array_map('intval', $session->participant_ids ?? []), true)) {
            return false;
        }

        // Siswa non-dapodik terikat ketat ke guru_id pemilik sesi
        if (empty($siswa->dapodik_id) && $siswa->guru_id !== null && (int) $session->guru_id !== (int) $siswa->guru_id) {
            return false;
        }

        return true;
    }

    public function begin(CbtTestSession $session, Siswa $siswa, int $userId): CbtResult
    {
        return DB::transaction(function () use ($session, $siswa, $userId) {
            // Mengunci induk yang SUDAH ADA mencegah dua insert percobaan paralel.
            $session = CbtTestSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->eligible($session, $siswa), 403);
            $existing = $session->results()->where('siswa_id', $siswa->id)->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === $userId, 403);
                return $existing;
            }
            abort_unless($session->status === 'running', 409, 'Guru belum membuka sesi ini.');
            abort_if(now()->lessThan($session->opens_at) || now()->greaterThanOrEqualTo($session->closes_at), 409, 'Di luar jadwal ujian.');
            $deadline = now()->addMinutes($session->duration_minutes)->min($session->closes_at);

            $result = $session->results()->create([
                'siswa_id' => $siswa->id, 'user_id' => $userId,
                'started_at' => now(), 'deadline_at' => $deadline, 'answers' => [],
            ]);

            if ($this->pakaiEngineBaru()) {
                $this->bridge->catatAttempt($result);
            }

            return $result;
        }, 3);
    }

    /**
     * Mengunci HANYA baris `CbtResult` milik siswa (tanpa `lockForUpdate()` pada
     * `CbtTestSession`), sehingga 300+ siswa pada sesi yang sama tidak saling
     * memblokir saat autosave/submit/anti-cheat. Bebas deadlock karena `CbtResult`
     * adalah daun paling bawah pada hierarki `Bank -> Session -> Result`.
     */
    private function lockedResult(CbtResult $result, callable $callback): mixed
    {
        return DB::transaction(function () use ($result, $callback) {
            $locked = CbtResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            $session = CbtTestSession::findOrFail($locked->session_id);

            return $callback($session, $locked);
        }, 3);
    }

    private function expired(CbtTestSession $session, CbtResult $result): bool
    {
        return $session->status === 'completed' || now()->greaterThanOrEqualTo($result->deadline_at);
    }

    private function finish(CbtTestSession $session, CbtResult $result): CbtResult
    {
        $persisted = is_array($result->answers) ? $result->answers : [];
        $mergedAnswers = $result->submitted_at
            ? $persisted
            : $this->buffer->merge($result->id, $persisted);

        if (!$result->submitted_at) {
            // Sesi lama/rusak bisa punya snapshot atau jawaban NULL; jangan
            // sampai scheduler finalisasi berhenti dengan TypeError.
            $result->update(CbtScoring::grade(
                is_array($session->question_snapshot) ? $session->question_snapshot : [],
                $mergedAnswers
            ) + [
                'answers' => $mergedAnswers,
                'submitted_at' => now(),
            ]);
        }

        if ($this->pakaiEngineBaru()) {
            // Pastikan attempt ada (mis. hasil yang dibuat sebelum flag aktif),
            // lalu catat jawaban & nilainya ke engine terpadu.
            $this->bridge->catatAttempt($result);
            $this->bridge->catatJawaban($result, is_array($result->answers) ? $result->answers : []);
            $this->bridge->catatPenilaian($result);
        }

        $this->buffer->forget($result->id);

        return $result;
    }

    public function syncResult(CbtResult $result): CbtResult
    {
        if ($result->submitted_at) {
            return $result;
        }
        if ($result->session->status !== 'completed' && now()->lessThan($result->deadline_at)) {
            return $result;
        }
        return $this->lockedResult($result, fn ($session, $locked) => $this->expired($session, $locked) ? $this->finish($session, $locked) : $locked);
    }

    public function recordViolation(CbtResult $result, Siswa $siswa, string $type): void
    {
        $this->lockedResult($result, function ($session, $locked) use ($siswa, $type) {
            abort_unless($this->eligible($session, $siswa), 403);
            abort_if($locked->submitted_at || $this->expired($session, $locked), 409);
            if ($locked->violations()->count() < 100) {
                $violation = $locked->violations()->create(['type' => $type]);

                if ($this->pakaiEngineBaru()) {
                    $this->bridge->catatPelanggaran($violation);
                }
            }
        });
    }

    private function validateAnswers(CbtTestSession $session, array $answers): void
    {
        $questions = collect($session->question_snapshot)->keyBy('id');
        foreach ($answers as $id => $answer) {
            $question = $questions->get($id);
            if (!$question || !array_key_exists($answer, $question['options'])) {
                throw ValidationException::withMessages(['answers' => 'Soal atau pilihan jawaban tidak valid untuk sesi ini.']);
            }
        }
    }

    public function answer(CbtResult $result, int $questionId, string $answer): bool
    {
        // Jalur cepat Redis-first asinkron: simpan di buffer skalar tanpa lock DB.
        if ($this->pakaiBufferAsinkron()) {
            $session = $result->relationLoaded('session')
                ? $result->session
                : CbtTestSession::findOrFail($result->session_id);

            if ($result->submitted_at || $this->expired($session, $result)) {
                $this->lockedResult($result, fn ($s, $r) => $this->finish($s, $r));

                return false;
            }

            $this->validateAnswers($session, [$questionId => $answer]);
            $this->buffer->put($result->id, $questionId, $answer);

            return true;
        }

        return $this->lockedResult($result, function ($session, $result) use ($questionId, $answer) {
            if ($result->submitted_at || $this->expired($session, $result)) {
                $this->finish($session, $result);
                return false; // Jangan throw: finalisasi harus tetap COMMIT.
            }
            $this->validateAnswers($session, [$questionId => $answer]);
            $this->buffer->put($result->id, $questionId, $answer);

            $answers = $this->buffer->merge($result->id, is_array($result->answers) ? $result->answers : []);
            $result->update(['answers' => $answers]);

            if ($this->pakaiEngineBaru()) {
                $this->bridge->catatJawaban($result, [$questionId => $answer]);
            }

            return true;
        });
    }

    public function submit(CbtResult $result, array $answers = []): CbtResult
    {
        return $this->guard->run('cbt-submit', $result->id, function () use ($result, $answers) {
            return $this->lockedResult($result, function ($session, $result) use ($answers) {
                if (!$result->submitted_at && !$this->expired($session, $result)) {
                    $this->validateAnswers($session, $answers);
                    $saved = $this->buffer->merge($result->id, is_array($result->answers) ? $result->answers : []);
                    foreach ($answers as $id => $answer) {
                        $saved[(int) $id] = (string) $answer;
                    }
                    $result->update(['answers' => $saved]);
                }
                // Setelah deadline, nilai hanya dihitung dari jawaban yang sudah tersimpan/ter-buffer.
                return $this->finish($session, $result);
            });
        });
    }

    public function complete(CbtTestSession $session): void
    {
        DB::transaction(function () use ($session) {
            $session = CbtTestSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($session->status, ['running', 'completed'], true), 409);
            // Tandai sesi selesai terlebih dahulu agar autosave paralel langsung tertolak.
            $session->update(['status' => 'completed']);
            foreach ($session->results()->whereNull('submitted_at')->lockForUpdate()->get() as $result) {
                $this->finish($session, $result);
            }
        }, 3);
    }

    public function finalizeExpired(): int
    {
        $count = 0;
        CbtResult::whereNull('submitted_at')->where('deadline_at', '<=', now())
            ->chunkById(100, function ($results) use (&$count) {
                foreach ($results as $result) {
                    $this->lockedResult($result, function ($session, $result) use (&$count) {
                        if (!$result->submitted_at && $this->expired($session, $result)) {
                            $this->finish($session, $result);
                            $count++;
                        }
                    });
                }
            });
        return $count;
    }
}
