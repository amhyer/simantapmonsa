<?php

namespace App\Services;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbtExamService
{
    public function __construct(private CbtEngineBridge $bridge) {}

    private function pakaiEngineBaru(): bool
    {
        return (bool) config('simantap.exam_engine_cbt', true);
    }

    public function publish(CbtTestSession $session): void
    {
        DB::transaction(function () use ($session) {
            // Urutan lock: bank -> sesi -> hasil. Mutasi soal juga mengunci bank.
            $bank = CbtQuestionBank::whereKey($session->bank_id)->lockForUpdate()->firstOrFail();
            $session = CbtTestSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'draft', 409, 'Sesi sudah dipublikasikan.');
            $questions = $bank->questions()->orderBy('id')->get();
            $participants = Siswa::where('guru_id', $session->guru_id)->where('kelas', $session->kelas)
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
        return $siswa->aktif && !$siswa->archived_at
            && (int) $session->guru_id === (int) $siswa->guru_id
            && in_array((int) $siswa->id, array_map('intval', $session->participant_ids ?? []), true);
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

    /** Semua pembaruan hasil mengunci sesi dulu, lalu hasil. */
    private function locked(CbtResult $result, callable $callback): mixed
    {
        return DB::transaction(function () use ($result, $callback) {
            $session = CbtTestSession::whereKey($result->session_id)->lockForUpdate()->firstOrFail();
            $result = CbtResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            return $callback($session, $result);
        }, 3);
    }

    private function expired(CbtTestSession $session, CbtResult $result): bool
    {
        return $session->status === 'completed' || now()->greaterThanOrEqualTo($result->deadline_at);
    }

    private function finish(CbtTestSession $session, CbtResult $result): CbtResult
    {
        if (!$result->submitted_at) {
            // Sesi lama/rusak bisa punya snapshot atau jawaban NULL; jangan
            // sampai scheduler finalisasi berhenti dengan TypeError.
            $result->update(CbtScoring::grade(
                is_array($session->question_snapshot) ? $session->question_snapshot : [],
                is_array($result->answers) ? $result->answers : []
            ) + ['submitted_at' => now()]);
        }

        if ($this->pakaiEngineBaru()) {
            // Pastikan attempt ada (mis. hasil yang dibuat sebelum flag aktif),
            // lalu catat jawaban & nilainya ke engine terpadu.
            $this->bridge->catatAttempt($result);
            $this->bridge->catatJawaban($result, is_array($result->answers) ? $result->answers : []);
            $this->bridge->catatPenilaian($result);
        }

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
        return $this->locked($result, fn ($session, $locked) => $this->expired($session, $locked) ? $this->finish($session, $locked) : $locked);
    }

    public function recordViolation(CbtResult $result, Siswa $siswa, string $type): void
    {
        $this->locked($result, function ($session, $locked) use ($siswa, $type) {
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
        return $this->locked($result, function ($session, $result) use ($questionId, $answer) {
            if ($result->submitted_at || $this->expired($session, $result)) {
                $this->finish($session, $result);
                return false; // Jangan throw: finalisasi harus tetap COMMIT.
            }
            $this->validateAnswers($session, [$questionId => $answer]);
            $answers = $result->answers;
            $answers[$questionId] = $answer;
            $result->update(['answers' => $answers]);

            if ($this->pakaiEngineBaru()) {
                $this->bridge->catatJawaban($result, [$questionId => $answer]);
            }

            return true;
        });
    }

    public function submit(CbtResult $result, array $answers = []): CbtResult
    {
        return $this->locked($result, function ($session, $result) use ($answers) {
            if (!$result->submitted_at && !$this->expired($session, $result)) {
                $this->validateAnswers($session, $answers);
                $saved = $result->answers;
                foreach ($answers as $id => $answer) {
                    $saved[$id] = $answer;
                }
                $result->update(['answers' => $saved]);
            }
            // Setelah deadline, nilai hanya dihitung dari jawaban yang sudah tersimpan.
            return $this->finish($session, $result);
        });
    }

    public function complete(CbtTestSession $session): void
    {
        DB::transaction(function () use ($session) {
            $session = CbtTestSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($session->status, ['running', 'completed'], true), 409);
            foreach ($session->results()->whereNull('submitted_at')->lockForUpdate()->get() as $result) {
                $this->finish($session, $result);
            }
            $session->update(['status' => 'completed']);
        }, 3);
    }

    public function finalizeExpired(): int
    {
        $count = 0;
        CbtResult::whereNull('submitted_at')->where('deadline_at', '<=', now())
            ->chunkById(100, function ($results) use (&$count) {
                foreach ($results as $result) {
                    $this->locked($result, function ($session, $result) use (&$count) {
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
