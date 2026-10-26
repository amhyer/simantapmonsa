<?php

namespace App\Console\Commands;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Bank;
use App\Models\Exam\Engine;
use App\Models\Exam\Question;
use App\Models\Exam\Result;
use App\Models\Exam\Section;
use App\Models\Exam\Session;
use App\Models\Exam\Violation;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\Tryout\StudentAnswer;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3: salin data `cbt_*` dan `tryout_*` ke engine terpadu `exam_*`.
 *
 * Idempoten: setiap baris baru menyimpan jejak asal (`sumber_tabel` +
 * `sumber_id`), sehingga perintah boleh dijalankan berulang tanpa menggandakan.
 * Tabel lama tidak pernah diubah maupun dihapus di sini.
 *
 * Pemetaan bank/section/soal didelegasikan ke `LegacyExamMapper` supaya sama
 * persis dengan yang dipakai alur ujian langsung.
 */
class ExamBackfill extends Command
{
    protected $signature = 'exam:backfill {--only= : Batasi ke cbt atau tryout} {--dry-run : Hanya hitung baris sumber; jangan tulis exam_*}';

    protected $description = 'Salin data CBT & Tryout ke tabel exam_* (idempoten, read-only terhadap tabel lama)';

    public function __construct(private LegacyExamMapper $mapper)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $only = $this->option('only');
        if ($only !== null && ! in_array($only, [Engine::CBT, Engine::TRYOUT], true)) {
            $this->error('Opsi --only harus cbt atau tryout.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN: tabel sumber hanya dibaca; tidak ada perubahan pada exam_*.');
            $this->table(['Tabel sumber', 'Baris'], $this->hitungBarisSumber($only));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($only) {
            if ($only !== Engine::TRYOUT) {
                $this->backfillCbt();
            }
            if ($only !== Engine::CBT) {
                $this->backfillTryout();
            }
        });

        $this->newLine();
        $this->table(
            ['Tabel', 'Jumlah'],
            [
                ['exam_engines', Engine::count()],
                ['exam_banks', Bank::count()],
                ['exam_sections', Section::count()],
                ['exam_questions', Question::count()],
                ['exam_sessions', Session::count()],
                ['exam_attempts', Attempt::count()],
                ['exam_answers', Answer::count()],
                ['exam_results', Result::count()],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Count legacy source rows only. This is a safe inventory, not proof of
     * field-level reconciliation or an estimate of rows that will be inserted.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private function hitungBarisSumber(?string $only): array
    {
        $tables = [];
        if ($only !== Engine::TRYOUT) {
            $tables = array_merge($tables, [
                'cbt_question_banks', 'cbt_questions', 'cbt_test_sessions', 'cbt_results', 'cbt_violations',
            ]);
        }
        if ($only !== Engine::CBT) {
            $tables = array_merge($tables, [
                'tryout_packages', 'tryout_subjects', 'tryout_questions', 'tryout_sessions',
                'tryout_results', 'tryout_student_answers',
            ]);
        }

        return array_map(
            fn (string $table) => [$table, (int) DB::table($table)->count()],
            $tables
        );
    }

    private function backfillCbt(): void
    {
        $this->info('CBT: bank soal → soal → sesi → attempt → hasil jawaban JSON');

        CbtQuestionBank::query()->chunkById(100, function ($banks) {
            foreach ($banks as $cbtBank) {
                $bank = $this->mapper->bankCbt($cbtBank);

                foreach ($cbtBank->sessions()->orderBy('id')->get() as $cbtSession) {
                    $session = $this->mapper->sesiCbt($bank, $cbtSession);

                    $peta = $this->petaSoalCbt($bank);

                    foreach ($cbtSession->results()->orderBy('id')->get() as $cbtResult) {
                        $this->backfillCbtResult($session, $peta, $cbtResult);
                    }
                }
            }
        });
    }

    /**
     * @return array<int, Question>
     */
    private function petaSoalCbt(Bank $bank): array
    {
        return Question::where('bank_id', $bank->id)
            ->with('options')
            ->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SOAL)
            ->get()
            ->keyBy(fn (Question $q) => (int) $q->sumber_id)
            ->all();
    }

    /**
     * @param  array<int, Question>  $peta
     */
    private function backfillCbtResult(Session $session, array $peta, CbtResult $cbtResult): void
    {
        $attempt = Attempt::updateOrCreate(
            ['sumber_tabel' => 'cbt_results', 'sumber_id' => $cbtResult->id],
            [
                'session_id' => $session->id,
                'siswa_id' => $cbtResult->siswa_id,
                'user_id' => $cbtResult->user_id,
                'attempt_number' => 1,
                'mulai_at' => $cbtResult->started_at,
                'deadline_at' => $cbtResult->deadline_at,
                'selesai_at' => $cbtResult->submitted_at,
                'status' => $cbtResult->submitted_at ? Attempt::STATUS_DINILAI : Attempt::STATUS_BERJALAN,
                'nilai_total' => $cbtResult->score,
            ]
        );

        $jawaban = (array) $cbtResult->answers;
        $totalPoin = 0.0;
        $maksimal = 0.0;

        foreach ($peta as $cbtQuestionId => $examQuestion) {
            $maksimal += (float) $examQuestion->poin;
            if (! array_key_exists($cbtQuestionId, $jawaban)) {
                continue;
            }

            $isi = $jawaban[$cbtQuestionId];
            $kunci = $examQuestion->options->firstWhere('adalah_benar', true);
            $isBenar = $kunci !== null && strtoupper((string) $isi) === strtoupper($kunci->huruf);
            $poin = $isBenar ? (float) $examQuestion->poin : 0.0;
            $totalPoin += $poin;

            Answer::updateOrCreate(
                ['attempt_id' => $attempt->id, 'question_id' => $examQuestion->id],
                ['jawaban' => (string) $isi, 'adalah_benar' => $isBenar, 'poin_diperoleh' => $poin]
            );
        }

        if ($cbtResult->submitted_at) {
            Result::updateOrCreate(
                ['attempt_id' => $attempt->id],
                [
                    'nilai_total' => round($totalPoin, 2),
                    'nilai_maksimal' => round($maksimal, 2),
                    'persentase' => $maksimal > 0 ? round(100 * $totalPoin / $maksimal, 2) : 0.0,
                    'sumber_tabel' => 'cbt_results',
                    'sumber_id' => $cbtResult->id,
                ]
            );
        }

        // Pelanggaran ikut dipetakan agar laporan engine lengkap dan tabel
        // `cbt_violations` dapat dihapus (langkah 6). Idempoten lewat jejak.
        foreach ($cbtResult->violations()->orderBy('id')->get() as $cbtViolation) {
            Violation::updateOrCreate(
                ['sumber_tabel' => 'cbt_violations', 'sumber_id' => $cbtViolation->id],
                ['attempt_id' => $attempt->id, 'tipe' => $cbtViolation->type]
            );
        }
    }

    private function backfillTryout(): void
    {
        $this->info('Tryout: paket → mapel (section) → soal → sesi → attempt → hasil');

        Package::withTrashed()->chunkById(100, function ($packages) {
            foreach ($packages as $package) {
                $bank = $this->mapper->bankTryout($package);

                foreach ($package->subjects()->with('mataPelajaran')->get() as $subject) {
                    $section = $this->mapper->sectionTryout($bank, $subject);
                    foreach ($subject->questions as $tryoutQuestion) {
                        $this->mapper->soalTryout($bank, $section, $tryoutQuestion);
                    }
                }

                foreach ($package->sessions()->orderBy('id')->get() as $tryoutSession) {
                    $session = $this->mapper->pastikanSesiTryout($tryoutSession);

                    foreach ($tryoutSession->results()->orderBy('id')->get() as $tryoutResult) {
                        $this->backfillTryoutResult($session, $tryoutResult);
                    }
                }
            }
        });
    }

    private function backfillTryoutResult(Session $session, TryoutResult $tryoutResult): void
    {
        $attempt = Attempt::updateOrCreate(
            ['sumber_tabel' => 'tryout_results', 'sumber_id' => $tryoutResult->id],
            [
                'session_id' => $session->id,
                'siswa_id' => $tryoutResult->siswa_id,
                'attempt_number' => $tryoutResult->attempt_number,
                'mulai_at' => $tryoutResult->waktu_mulai,
                'selesai_at' => $tryoutResult->waktu_selesai,
                'status' => $tryoutResult->waktu_selesai ? Attempt::STATUS_DINILAI : Attempt::STATUS_BERJALAN,
                'nilai_total' => $tryoutResult->nilai_total,
                'nilai_maksimal' => $tryoutResult->nilai_maksimal,
            ]
        );

        Result::updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
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
            ]
        );

        // Jawaban Tryout sudah tersimpan per soal; petakan lewat sumber_id.
        $petaSoal = $this->mapper->petaSoalTryout();

        $answers = StudentAnswer::where('tryout_session_id', $tryoutResult->tryout_session_id)
            ->where('siswa_id', $tryoutResult->siswa_id)
            ->where('attempt_number', $tryoutResult->attempt_number)
            ->get();

        foreach ($answers as $studentAnswer) {
            $examQuestion = $petaSoal[(int) $studentAnswer->question_id] ?? null;
            if (! $examQuestion) {
                continue;
            }

            Answer::updateOrCreate(
                ['attempt_id' => $attempt->id, 'question_id' => $examQuestion->id],
                [
                    'jawaban' => $studentAnswer->jawaban,
                    'adalah_benar' => $studentAnswer->adalah_benar,
                    'poin_diperoleh' => $studentAnswer->poin_diperoleh,
                    'dijawab_at' => $studentAnswer->waktu_selesai_jawab,
                ]
            );
        }
    }
}
