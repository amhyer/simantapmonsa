<?php

namespace App\Services\Exam;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Bank;
use App\Models\Exam\Engine;
use App\Models\Exam\Question;
use App\Models\Exam\Result as ExamResult;
use App\Models\Exam\Section;
use App\Models\Exam\Session as ExamSession;
use App\Models\Exam\Violation;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\Tryout\StudentAnswer;
use Illuminate\Support\Carbon;

/**
 * Rekonsiliasi berbasis ISI, bukan sekadar jumlah baris.
 *
 * `exam:backfill` boleh saja berhasil menyalin 1.000 baris, tetapi hasilnya
 * belum membuktikan nilainya sama. Layanan ini membandingkan setiap baris lama
 * dengan padanan `exam_*` memakai aturan atribut yang sama dengan
 * `LegacyExamMapper` (sumber tunggal aturan), termasuk opsi soal, jawaban,
 * nilai, dan relasi induk-anak.
 *
 * Layanan ini READ-ONLY: tidak membuat, mengubah, atau menghapus baris.
 */
class ExamReconciliationService
{
    /** @var array<int, array<string, mixed>> */
    private array $masalah = [];

    private int $totalMasalah = 0;

    private int $diperiksa = 0;

    private int $batas = 50;

    /** @var array<string, array<string, int>> */
    private array $jumlah = ['legacy' => [], 'engine' => []];

    public function __construct(private LegacyExamMapper $mapper)
    {
    }

    /**
     * @return array{
     *     ok: bool,
     *     diperiksa: int,
     *     total_masalah: int,
     *     masalah: array<int, array<string, mixed>>,
     *     jumlah: array<string, array<string, int>>
     * }
     */
    public function laporan(?string $only = null, int $batas = 50): array
    {
        $this->masalah = [];
        $this->totalMasalah = 0;
        $this->diperiksa = 0;
        $this->batas = max(1, $batas);
        $this->jumlah = ['legacy' => [], 'engine' => []];

        if ($only !== Engine::TRYOUT) {
            $this->periksaCbt();
        }

        if ($only !== Engine::CBT) {
            $this->periksaTryout();
        }

        return [
            'ok' => $this->totalMasalah === 0,
            'diperiksa' => $this->diperiksa,
            'total_masalah' => $this->totalMasalah,
            'masalah' => $this->masalah,
            'jumlah' => $this->jumlah,
        ];
    }

    // ---------------------------------------------------------------- CBT

    private function periksaCbt(): void
    {
        $this->hitung('cbt_question_banks', CbtQuestionBank::count());
        $this->hitung('exam_banks.cbt', $this->hitungEngine(Bank::class, LegacyExamMapper::SUMBER_CBT_BANK));
        $this->hitung('cbt_questions', \App\Models\CbtQuestion::count());
        $this->hitung('exam_questions.cbt', $this->hitungEngine(Question::class, LegacyExamMapper::SUMBER_CBT_SOAL));
        $this->hitung('cbt_test_sessions', CbtTestSession::count());
        $this->hitung('exam_sessions.cbt', $this->hitungEngine(ExamSession::class, LegacyExamMapper::SUMBER_CBT_SESI));
        $this->hitung('cbt_results', CbtResult::count());
        $this->hitung('exam_attempts.cbt', $this->hitungEngine(Attempt::class, 'cbt_results'));
        $this->hitung('exam_results.cbt', $this->hitungEngine(ExamResult::class, 'cbt_results'));

        CbtQuestionBank::query()->chunkById(100, function ($banks) {
            foreach ($banks as $cbtBank) {
                $this->periksaBankCbt($cbtBank);
            }
        });
    }

    private function periksaBankCbt(CbtQuestionBank $cbtBank): void
    {
        $bank = $this->cari(Bank::class, LegacyExamMapper::SUMBER_CBT_BANK, $cbtBank->id);

        $this->bandingkan(
            'cbt',
            'cbt_question_banks',
            $cbtBank->id,
            $this->mapper->atributBankCbt($cbtBank),
            $bank,
            $bank ? ['engine' => $this->kodeEngine($bank->engine_id) === Engine::CBT] : []
        );

        if (! $bank) {
            return;
        }

        $pertanyaan = $cbtBank->questions()->orderBy('id')->get();

        foreach ($pertanyaan as $cbtQuestion) {
            $this->periksaSoalCbt($bank, $cbtQuestion);
        }

        foreach ($cbtBank->sessions()->orderBy('id')->get() as $cbtSession) {
            $this->periksaSesiCbt($bank, $cbtSession);
        }
    }

    private function periksaSoalCbt(Bank $bank, \App\Models\CbtQuestion $cbtQuestion): void
    {
        $question = $this->cari(Question::class, LegacyExamMapper::SUMBER_CBT_SOAL, $cbtQuestion->id);

        $this->bandingkan(
            'cbt',
            'cbt_questions',
            $cbtQuestion->id,
            $this->mapper->atributSoalCbt($cbtQuestion),
            $question,
            $question ? ['bank_id' => (int) $question->bank_id === (int) $bank->id] : []
        );

        if (! $question) {
            return;
        }

        $this->bandingkanOpsi('cbt', 'cbt_questions', $cbtQuestion->id, $this->mapper->atributOpsiCbt($cbtQuestion), $question);
    }

    private function periksaSesiCbt(Bank $bank, CbtTestSession $cbtSession): void
    {
        $session = $this->cari(ExamSession::class, LegacyExamMapper::SUMBER_CBT_SESI, $cbtSession->id);

        $this->bandingkan(
            'cbt',
            'cbt_test_sessions',
            $cbtSession->id,
            $this->mapper->atributSesiCbt($cbtSession),
            $session,
            $session ? ['bank_id' => (int) $session->bank_id === (int) $bank->id] : []
        );

        if (! $session) {
            return;
        }

        foreach ($cbtSession->results()->orderBy('id')->get() as $cbtResult) {
            $this->periksaHasilCbt($bank, $session, $cbtResult);
        }
    }

    private function periksaHasilCbt(Bank $bank, ExamSession $session, CbtResult $cbtResult): void
    {
        $attempt = $this->cari(Attempt::class, 'cbt_results', $cbtResult->id);

        $harapanAttempt = [
            'siswa_id' => $cbtResult->siswa_id,
            'user_id' => $cbtResult->user_id,
            'attempt_number' => 1,
            'mulai_at' => $cbtResult->started_at,
            'deadline_at' => $cbtResult->deadline_at,
            'selesai_at' => $cbtResult->submitted_at,
            'status' => $cbtResult->submitted_at ? Attempt::STATUS_DINILAI : Attempt::STATUS_BERJALAN,
            'nilai_total' => $cbtResult->score,
        ];

        $this->bandingkan(
            'cbt',
            'cbt_results',
            $cbtResult->id,
            $harapanAttempt,
            $attempt,
            $attempt ? ['session_id' => (int) $attempt->session_id === (int) $session->id] : []
        );

        if (! $attempt) {
            return;
        }

        $peta = $this->petaSoalCbt($bank);
        $jawaban = (array) $cbtResult->answers;
        $harapanJawaban = [];
        $totalPoin = 0.0;

        foreach ($jawaban as $cbtQuestionId => $isi) {
            $examQuestion = $peta[(int) $cbtQuestionId] ?? null;

            if (! $examQuestion) {
                $this->catat('cbt', 'cbt_results', $cbtResult->id, 'jawaban menunjuk soal yang tidak ada di engine', [
                    'cbt_question_id' => $cbtQuestionId,
                ]);

                continue;
            }

            $kunci = $examQuestion->options->firstWhere('adalah_benar', true);
            $benar = $kunci !== null && strtoupper((string) $isi) === strtoupper((string) $kunci->huruf);
            $poin = $benar ? (float) $examQuestion->poin : 0.0;
            $totalPoin += $poin;

            $harapanJawaban[$examQuestion->id] = [
                'jawaban' => (string) $isi,
                'adalah_benar' => $benar,
                'poin_diperoleh' => $poin,
            ];
        }

        $this->bandingkanJawaban('cbt', $cbtResult->id, $harapanJawaban, $attempt);

        $this->bandingkanPelanggaranCbt($attempt, $cbtResult);

        if (! $cbtResult->submitted_at) {
            $this->pastikanTidakAda('cbt', 'cbt_results', $cbtResult->id, ExamResult::class, 'hasil engine ada padahal hasil lama belum dikumpulkan');

            return;
        }

        $maksimal = (float) $peta->sum(fn (Question $q) => (float) $q->poin);
        $harapanHasil = [
            'nilai_total' => round($totalPoin, 2),
            'nilai_maksimal' => round($maksimal, 2),
            'persentase' => $maksimal > 0 ? round(100 * $totalPoin / $maksimal, 2) : 0.0,
            'sumber_tabel' => 'cbt_results',
            'sumber_id' => $cbtResult->id,
        ];

        $hasil = ExamResult::where('attempt_id', $attempt->id)->first();
        $this->bandingkan('cbt', 'cbt_results', $cbtResult->id, $harapanHasil, $hasil, $hasil ? [
            'attempt_id' => (int) $hasil->attempt_id === (int) $attempt->id,
        ] : []);
    }

    /** @return \Illuminate\Support\Collection<int, Question> */
    private function petaSoalCbt(Bank $bank)
    {
        return Question::with('options')
            ->where('bank_id', $bank->id)
            ->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SOAL)
            ->get()
            ->keyBy(fn (Question $q) => (int) $q->sumber_id);
    }

    private function bandingkanPelanggaranCbt(Attempt $attempt, CbtResult $cbtResult): void
    {
        foreach ($cbtResult->violations()->orderBy('id')->get() as $cbtViolation) {
            $violation = Violation::where('sumber_tabel', 'cbt_violations')
                ->where('sumber_id', $cbtViolation->id)
                ->first();

            $this->bandingkan(
                'cbt',
                'cbt_violations',
                $cbtViolation->id,
                ['tipe' => $cbtViolation->type],
                $violation,
                $violation ? ['attempt_id' => (int) $violation->attempt_id === (int) $attempt->id] : []
            );
        }
    }

    // ------------------------------------------------------------- TRYOUT

    private function periksaTryout(): void
    {
        $this->hitung('tryout_packages', Package::withTrashed()->count());
        $this->hitung('exam_banks.tryout', $this->hitungEngine(Bank::class, LegacyExamMapper::SUMBER_TRYOUT_PAKET));
        $this->hitung('tryout_subjects', \App\Models\Tryout\Subject::count());
        $this->hitung('exam_sections.tryout', $this->hitungEngine(Section::class, LegacyExamMapper::SUMBER_TRYOUT_MAPEL));
        $this->hitung('tryout_questions', \App\Models\Tryout\TryoutQuestion::withTrashed()->count());
        $this->hitung('exam_questions.tryout', $this->hitungEngine(Question::class, LegacyExamMapper::SUMBER_TRYOUT_SOAL));
        $this->hitung('tryout_sessions', TryoutSession::withTrashed()->count());
        $this->hitung('exam_sessions.tryout', $this->hitungEngine(ExamSession::class, LegacyExamMapper::SUMBER_TRYOUT_SESI));
        $this->hitung('tryout_results', TryoutResult::count());
        $this->hitung('exam_attempts.tryout', $this->hitungEngine(Attempt::class, 'tryout_results'));
        $this->hitung('tryout_student_answers', StudentAnswer::count());

        Package::withTrashed()->chunkById(100, function ($packages) {
            foreach ($packages as $package) {
                $this->periksaPaketTryout($package);
            }
        });
    }

    private function periksaPaketTryout(Package $package): void
    {
        $bank = $this->cari(Bank::class, LegacyExamMapper::SUMBER_TRYOUT_PAKET, $package->id);

        $this->bandingkan(
            'tryout',
            'tryout_packages',
            $package->id,
            $this->mapper->atributBankTryout($package),
            $bank,
            $bank ? ['engine' => $this->kodeEngine($bank->engine_id) === Engine::TRYOUT] : []
        );

        if (! $bank) {
            return;
        }

        foreach ($package->subjects()->with('mataPelajaran')->get() as $subject) {
            $this->periksaMapelTryout($bank, $subject);
        }

        foreach ($package->sessions()->orderBy('id')->get() as $tryoutSession) {
            $this->periksaSesiTryout($bank, $tryoutSession);
        }
    }

    private function periksaMapelTryout(Bank $bank, \App\Models\Tryout\Subject $subject): void
    {
        $section = $this->cari(Section::class, LegacyExamMapper::SUMBER_TRYOUT_MAPEL, $subject->id);

        $this->bandingkan(
            'tryout',
            'tryout_subjects',
            $subject->id,
            $this->mapper->atributSectionTryout($subject),
            $section,
            $section ? ['bank_id' => (int) $section->bank_id === (int) $bank->id] : []
        );

        if (! $section) {
            return;
        }

        foreach ($subject->questions as $tryoutQuestion) {
            $this->periksaSoalTryout($bank, $section, $tryoutQuestion);
        }
    }

    private function periksaSoalTryout(Bank $bank, Section $section, \App\Models\Tryout\TryoutQuestion $tryoutQuestion): void
    {
        $question = $this->cari(Question::class, LegacyExamMapper::SUMBER_TRYOUT_SOAL, $tryoutQuestion->id);

        $this->bandingkan(
            'tryout',
            'tryout_questions',
            $tryoutQuestion->id,
            $this->mapper->atributSoalTryout($tryoutQuestion),
            $question,
            $question ? [
                'bank_id' => (int) $question->bank_id === (int) $bank->id,
                'section_id' => (int) $question->section_id === (int) $section->id,
            ] : []
        );

        if (! $question) {
            return;
        }

        $this->bandingkanOpsi('tryout', 'tryout_questions', $tryoutQuestion->id, $this->mapper->atributOpsiTryout($tryoutQuestion), $question);
    }

    private function periksaSesiTryout(Bank $bank, TryoutSession $tryoutSession): void
    {
        $session = $this->cari(ExamSession::class, LegacyExamMapper::SUMBER_TRYOUT_SESI, $tryoutSession->id);

        $this->bandingkan(
            'tryout',
            'tryout_sessions',
            $tryoutSession->id,
            $this->mapper->atributSesiTryout($tryoutSession),
            $session,
            $session ? ['bank_id' => (int) $session->bank_id === (int) $bank->id] : []
        );

        if (! $session) {
            return;
        }

        foreach ($tryoutSession->results()->orderBy('id')->get() as $tryoutResult) {
            $this->periksaHasilTryout($session, $tryoutResult);
        }
    }

    private function periksaHasilTryout(ExamSession $session, TryoutResult $tryoutResult): void
    {
        $attempt = $this->cari(Attempt::class, 'tryout_results', $tryoutResult->id);

        $harapanAttempt = [
            'siswa_id' => $tryoutResult->siswa_id,
            'attempt_number' => $tryoutResult->attempt_number,
            'mulai_at' => $tryoutResult->waktu_mulai,
            'selesai_at' => $tryoutResult->waktu_selesai,
            'status' => $tryoutResult->waktu_selesai ? Attempt::STATUS_DINILAI : Attempt::STATUS_BERJALAN,
            'nilai_total' => $tryoutResult->nilai_total,
            'nilai_maksimal' => $tryoutResult->nilai_maksimal,
        ];

        $this->bandingkan(
            'tryout',
            'tryout_results',
            $tryoutResult->id,
            $harapanAttempt,
            $attempt,
            $attempt ? ['session_id' => (int) $attempt->session_id === (int) $session->id] : []
        );

        if (! $attempt) {
            return;
        }

        $harapanHasil = [
            'nilai_total' => $tryoutResult->nilai_total,
            'nilai_maksimal' => $tryoutResult->nilai_maksimal,
            'persentase' => $tryoutResult->persentase,
            'peringkat' => $tryoutResult->peringkat,
            'lulus' => $tryoutResult->lulus,
            'detail_per_section' => $tryoutResult->detail_nilai_mapel,
            'analisis_kelemahan' => $tryoutResult->analisis_kelemahan,
            'rekomendasi' => $tryoutResult->rekomendasi_pembelajaran,
            'sumber_tabel' => 'tryout_results',
            'sumber_id' => $tryoutResult->id,
        ];

        $hasil = ExamResult::where('attempt_id', $attempt->id)->first();
        $this->bandingkan('tryout', 'tryout_results', $tryoutResult->id, $harapanHasil, $hasil, $hasil ? [
            'attempt_id' => (int) $hasil->attempt_id === (int) $attempt->id,
        ] : []);

        $this->periksaJawabanTryout($attempt, $tryoutResult);
    }

    private function periksaJawabanTryout(Attempt $attempt, TryoutResult $tryoutResult): void
    {
        $peta = $this->mapper->petaSoalTryout();
        $harapan = [];

        $answers = StudentAnswer::where('tryout_session_id', $tryoutResult->tryout_session_id)
            ->where('siswa_id', $tryoutResult->siswa_id)
            ->where('attempt_number', $tryoutResult->attempt_number)
            ->get();

        foreach ($answers as $studentAnswer) {
            $examQuestion = $peta[(int) $studentAnswer->question_id] ?? null;

            if (! $examQuestion) {
                $this->catat('tryout', 'tryout_results', $tryoutResult->id, 'jawaban menunjuk soal yang tidak ada di engine', [
                    'tryout_question_id' => $studentAnswer->question_id,
                ]);

                continue;
            }

            $harapan[$examQuestion->id] = [
                'jawaban' => $studentAnswer->jawaban,
                'adalah_benar' => $studentAnswer->adalah_benar,
                'poin_diperoleh' => $studentAnswer->poin_diperoleh,
                'dijawab_at' => $studentAnswer->waktu_selesai_jawab,
            ];
        }

        $this->bandingkanJawaban('tryout', $tryoutResult->id, $harapan, $attempt);
    }

    // ---------------------------------------------------------- UTILITAS

    /**
     * @param  array<string, mixed>  $harapan
     * @param  array<string, bool>  $tambahan
     */
    private function bandingkan(string $area, string $tabel, int $id, array $harapan, mixed $model, array $tambahan = []): void
    {
        $this->diperiksa++;

        if ($model === null) {
            $this->catat($area, $tabel, $id, 'baris engine tidak ditemukan');

            return;
        }

        if (method_exists($model, 'trashed') && $model->trashed()) {
            $this->catat($area, $tabel, $id, 'baris engine soft-deleted');
        }

        foreach ($harapan as $kolom => $nilai) {
            $aktual = $model->getAttribute($kolom);

            if (! $this->sama($nilai, $aktual)) {
                $this->catat($area, $tabel, $id, "kolom '{$kolom}' berbeda", [
                    'harapan' => $this->ringkas($nilai),
                    'aktual' => $this->ringkas($aktual),
                ]);
            }
        }

        foreach ($tambahan as $label => $cocok) {
            if (! $cocok) {
                $this->catat($area, $tabel, $id, "relasi '{$label}' tidak cocok");
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $harapan  huruf => atribut
     */
    private function bandingkanOpsi(string $area, string $tabel, int $id, array $harapan, Question $question): void
    {
        $aktual = $question->options()->get()->keyBy(fn ($option) => (string) $option->huruf);

        if ($aktual->count() !== count($harapan)) {
            $this->catat($area, $tabel, $id, 'jumlah opsi berbeda', [
                'harapan' => count($harapan),
                'aktual' => $aktual->count(),
            ]);
        }

        foreach ($harapan as $huruf => $atribut) {
            $option = $aktual->get((string) $huruf);

            if (! $option) {
                $this->catat($area, $tabel, $id, "opsi '{$huruf}' tidak ada di engine");

                continue;
            }

            foreach ($atribut as $kolom => $nilai) {
                if (! $this->sama($nilai, $option->getAttribute($kolom))) {
                    $this->catat($area, $tabel, $id, "opsi '{$huruf}' kolom '{$kolom}' berbeda", [
                        'harapan' => $this->ringkas($nilai),
                        'aktual' => $this->ringkas($option->getAttribute($kolom)),
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $harapan  question_id => atribut
     */
    private function bandingkanJawaban(string $area, int $id, array $harapan, Attempt $attempt): void
    {
        $aktual = Answer::where('attempt_id', $attempt->id)->get()->keyBy('question_id');

        foreach ($harapan as $questionId => $atribut) {
            $answer = $aktual->get($questionId);

            if (! $answer) {
                $this->catat($area, 'exam_answers', $id, 'jawaban tidak ada di engine', [
                    'question_id' => $questionId,
                ]);

                continue;
            }

            foreach ($atribut as $kolom => $nilai) {
                if (! $this->sama($nilai, $answer->getAttribute($kolom))) {
                    $this->catat($area, 'exam_answers', $id, "jawaban question_id={$questionId} kolom '{$kolom}' berbeda", [
                        'harapan' => $this->ringkas($nilai),
                        'aktual' => $this->ringkas($answer->getAttribute($kolom)),
                    ]);
                }
            }
        }

        foreach ($aktual->keys() as $questionId) {
            if (! array_key_exists((int) $questionId, $harapan)) {
                $this->catat($area, 'exam_answers', $id, 'ada jawaban engine tanpa padanan', [
                    'question_id' => $questionId,
                ]);
            }
        }
    }

    private function pastikanTidakAda(string $area, string $tabel, int $id, string $class, string $pesan): void
    {
        if ($class::where('sumber_tabel', $tabel)->where('sumber_id', $id)->exists()) {
            $this->catat($area, $tabel, $id, $pesan);
        }
    }

    private function cari(string $class, string $sumber, int $id): mixed
    {
        $query = method_exists($class, 'withTrashed') ? $class::withTrashed() : $class::query();

        return $query->where('sumber_tabel', $sumber)->where('sumber_id', $id)->first();
    }

    private function hitungEngine(string $class, string $sumber): int
    {
        $query = method_exists($class, 'withTrashed') ? $class::withTrashed() : $class::query();

        return $query->where('sumber_tabel', $sumber)->count();
    }

    private function kodeEngine(?int $engineId): ?string
    {
        return Engine::whereKey($engineId)->value('kode');
    }

    private function hitung(string $kunci, int $nilai): void
    {
        $this->jumlah[str_contains($kunci, 'exam_') ? 'engine' : 'legacy'][$kunci] = $nilai;
    }

    private function catat(string $area, string $tabel, int $id, string $pesan, array $detail = []): void
    {
        $this->totalMasalah++;

        if (count($this->masalah) >= $this->batas) {
            return;
        }

        $this->masalah[] = [
            'area' => $area,
            'tabel' => $tabel,
            'id' => $id,
            'masalah' => $pesan,
            'detail' => $detail,
        ];
    }

    private function sama(mixed $a, mixed $b): bool
    {
        $a = $this->normalisasi($a);
        $b = $this->normalisasi($b);

        if (is_array($a) || is_array($b)) {
            return is_array($a) && is_array($b) && $a == $b;
        }

        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.011;
        }

        return $a === $b;
    }

    private function normalisasi(mixed $nilai): mixed
    {
        if ($nilai instanceof \DateTimeInterface) {
            return Carbon::instance($nilai)->format('Y-m-d H:i:s');
        }

        if (is_object($nilai)) {
            $nilai = (array) $nilai;
        }

        if (is_string($nilai)) {
            $teks = trim($nilai);

            if ($teks !== '' && ($teks[0] === '{' || $teks[0] === '[')) {
                $decoded = json_decode($teks, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $nilai = $decoded;
                } else {
                    return $teks;
                }
            } else {
                return $teks;
            }
        }

        if (is_array($nilai)) {
            $hasil = [];

            foreach ($nilai as $kunci => $isi) {
                $hasil[$kunci] = $this->normalisasi($isi);
            }

            if (array_is_list($hasil)) {
                usort($hasil, fn ($a, $b) => strcmp(
                    (string) json_encode($a, JSON_UNESCAPED_UNICODE),
                    (string) json_encode($b, JSON_UNESCAPED_UNICODE)
                ));
            } else {
                ksort($hasil);
            }

            return $hasil;
        }

        if (is_float($nilai)) {
            return round($nilai, 4);
        }

        return $nilai;
    }

    private function ringkas(mixed $nilai): string
    {
        $nilai = $this->normalisasi($nilai);

        $teks = is_array($nilai)
            ? (string) json_encode($nilai, JSON_UNESCAPED_UNICODE)
            : var_export($nilai, true);

        if (mb_strlen($teks) > 120) {
            $teks = mb_substr($teks, 0, 117).'...';
        }

        return $teks;
    }
}
