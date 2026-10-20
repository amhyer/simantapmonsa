<?php

namespace App\Console\Commands;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Bank;
use App\Models\Exam\Engine;
use App\Models\Exam\Question;
use App\Models\Exam\QuestionOption;
use App\Models\Exam\Result;
use App\Models\Exam\Section;
use App\Models\Exam\Session;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tryout\TryoutQuestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3: salin data `cbt_*` dan `tryout_*` ke engine terpadu `exam_*`.
 *
 * Idempoten: setiap baris baru menyimpan jejak asal (`sumber_tabel` +
 * `sumber_id`), sehingga perintah boleh dijalankan berulang tanpa menggandakan.
 * Tabel lama tidak pernah diubah maupun dihapus di sini.
 */
class ExamBackfill extends Command
{
    protected $signature = 'exam:backfill {--only= : Batasi ke cbt atau tryout}';

    protected $description = 'Salin data CBT & Tryout ke tabel exam_* (idempoten, read-only terhadap tabel lama)';

    public function handle(): int
    {
        $only = $this->option('only');

        DB::transaction(function () use ($only) {
            $cbt = $this->engine(Engine::CBT, 'CBT (pilihan ganda)');
            $tryout = $this->engine(Engine::TRYOUT, 'Tryout');

            if ($only !== Engine::TRYOUT) {
                $this->backfillCbt($cbt);
            }
            if ($only !== Engine::CBT) {
                $this->backfillTryout($tryout);
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

    private function engine(string $kode, string $nama): Engine
    {
        return Engine::updateOrCreate(['kode' => $kode], ['nama' => $nama, 'jenis' => $kode, 'aktif' => true]);
    }

    private function backfillCbt(Engine $engine): void
    {
        $this->info('CBT: bank soal → soal → sesi → attempt → hasil jawaban JSON');

        CbtQuestionBank::query()->chunkById(100, function ($banks) use ($engine) {
            foreach ($banks as $cbtBank) {
                $bank = Bank::updateOrCreate(
                    ['sumber_tabel' => 'cbt_question_banks', 'sumber_id' => $cbtBank->id],
                    [
                        'engine_id' => $engine->id,
                        'guru_id' => $cbtBank->guru_id,
                        'nama' => $cbtBank->title,
                        'mata_pelajaran' => $cbtBank->subject,
                    ]
                );

                foreach ($cbtBank->questions()->orderBy('id')->get() as $cbtQuestion) {
                    $question = Question::updateOrCreate(
                        ['sumber_tabel' => 'cbt_questions', 'sumber_id' => $cbtQuestion->id],
                        [
                            'bank_id' => $bank->id,
                            'tipe' => 'pilihan_ganda',
                            'pertanyaan' => $cbtQuestion->prompt,
                            'poin' => 1,
                            'aktif' => true,
                        ]
                    );

                    foreach ((array) $cbtQuestion->options as $huruf => $isi) {
                        QuestionOption::updateOrCreate(
                            ['question_id' => $question->id, 'huruf' => (string) $huruf],
                            [
                                'isi_opsi' => (string) $isi,
                                'adalah_benar' => (string) $huruf === (string) $cbtQuestion->correct_answer,
                                'urutan' => is_numeric($huruf) ? (int) $huruf : ord((string) $huruf) - 64,
                            ]
                        );
                    }
                }

                foreach ($cbtBank->sessions ?? [] as $cbtSession) {
                    $this->backfillCbtSession($bank, $cbtSession);
                }
            }
        });
    }

    private function backfillCbtSession(Bank $bank, CbtTestSession $cbtSession): void
    {
        $session = Session::updateOrCreate(
            ['sumber_tabel' => 'cbt_test_sessions', 'sumber_id' => $cbtSession->id],
            [
                'bank_id' => $bank->id,
                'guru_id' => $cbtSession->guru_id,
                'nama' => $cbtSession->title,
                'kelas' => $cbtSession->kelas,
                'mulai_at' => $cbtSession->opens_at,
                'selesai_at' => $cbtSession->closes_at,
                'durasi_menit' => $cbtSession->duration_minutes,
                'status' => $cbtSession->status,
                'soal_snapshot' => $cbtSession->question_snapshot,
                'peserta_ids' => $cbtSession->participant_ids,
            ]
        );

        // Peta id soal CBT -> id soal exam, dari snapshot sesi ini.
        $peta = [];
        foreach (Question::where('bank_id', $bank->id)->get() as $examQuestion) {
            if ($examQuestion->sumber_tabel === 'cbt_questions') {
                $peta[(int) $examQuestion->sumber_id] = $examQuestion;
            }
        }

        foreach ($cbtSession->results as $cbtResult) {
            $this->backfillCbtResult($session, $peta, $cbtResult);
        }
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
    }

    private function backfillTryout(Engine $engine): void
    {
        $this->info('Tryout: paket → mapel (section) → soal → sesi → attempt → hasil');

        Package::withTrashed()->chunkById(100, function ($packages) use ($engine) {
            foreach ($packages as $package) {
                $bank = Bank::updateOrCreate(
                    ['sumber_tabel' => 'tryout_packages', 'sumber_id' => $package->id],
                    [
                        'engine_id' => $engine->id,
                        'kode' => $package->kode_paket,
                        'nama' => $package->nama_paket,
                        'meta' => [
                            'category_id' => $package->category_id,
                            'jenjang' => $package->jenjang,
                            'tahun_paket' => $package->tahun_paket,
                            'durasi_total_menit' => $package->durasi_total_menit,
                            'passing_grade' => $package->passing_grade,
                        ],
                    ]
                );

                $petaSection = [];
                foreach ($package->subjects as $subject) {
                    $section = Section::updateOrCreate(
                        ['sumber_tabel' => 'tryout_subjects', 'sumber_id' => $subject->id],
                        [
                            'bank_id' => $bank->id,
                            'nama' => $subject->mataPelajaran?->nama,
                            'mata_pelajaran_id' => $subject->mata_pelajaran_id,
                            'urutan' => $subject->urutan,
                            'bobot' => $subject->bobot,
                            'durasi_menit' => $subject->durasi_menit,
                            'passing_grade' => $subject->passing_grade_mapel,
                        ]
                    );
                    $petaSection[$subject->id] = $section;

                    foreach ($subject->questions as $tryoutQuestion) {
                        $this->backfillTryoutQuestion($bank, $section, $tryoutQuestion);
                    }
                }

                foreach ($package->sessions as $tryoutSession) {
                    $this->backfillTryoutSession($bank, $tryoutSession);
                }
            }
        });
    }

    private function backfillTryoutQuestion(Bank $bank, Section $section, TryoutQuestion $tryoutQuestion): void
    {
        $question = Question::updateOrCreate(
            ['sumber_tabel' => 'tryout_questions', 'sumber_id' => $tryoutQuestion->id],
            [
                'bank_id' => $bank->id,
                'section_id' => $section->id,
                'urutan' => $tryoutQuestion->urutan,
                'tipe' => $tryoutQuestion->tipe_soal,
                'pertanyaan' => $tryoutQuestion->pertanyaan,
                'media_type' => $tryoutQuestion->media_type ?? 'text',
                'media_url' => $tryoutQuestion->media_url,
                'poin' => $tryoutQuestion->poin,
                'tingkat_kesulitan' => $tryoutQuestion->tingkat_kesulitan,
                'pembahasan' => $tryoutQuestion->pembahasan,
                'aktif' => (bool) $tryoutQuestion->aktif,
            ]
        );

        foreach ($tryoutQuestion->options as $option) {
            QuestionOption::updateOrCreate(
                ['question_id' => $question->id, 'huruf' => $option->huruf],
                [
                    'isi_opsi' => $option->isi_opsi,
                    'adalah_benar' => (bool) $option->adalah_benar,
                    'urutan' => $option->urutan,
                ]
            );
        }
    }

    private function backfillTryoutSession(Bank $bank, TryoutSession $tryoutSession): void
    {
        $session = Session::updateOrCreate(
            ['sumber_tabel' => 'tryout_sessions', 'sumber_id' => $tryoutSession->id],
            [
                'bank_id' => $bank->id,
                'guru_id' => $tryoutSession->guru_id,
                'semester_id' => $tryoutSession->semester_id,
                'rombel_id' => $tryoutSession->rombel_id,
                'kode' => $tryoutSession->kode_sesi,
                'nama' => $tryoutSession->nama_sesi,
                'kelas' => $tryoutSession->kelas,
                'mulai_at' => $tryoutSession->tanggal_mulai,
                'selesai_at' => $tryoutSession->tanggal_selesai,
                'status' => $tryoutSession->status,
                'instruksi' => $tryoutSession->instruksi ?? null,
            ]
        );

        foreach ($tryoutSession->results as $tryoutResult) {
            $this->backfillTryoutResult($session, $tryoutResult);
        }
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
        $petaSoal = Question::where('sumber_tabel', 'tryout_questions')
            ->pluck('id', 'sumber_id');

        $answers = StudentAnswer::where('tryout_session_id', $tryoutResult->tryout_session_id)
            ->where('siswa_id', $tryoutResult->siswa_id)
            ->where('attempt_number', $tryoutResult->attempt_number)
            ->get();

        foreach ($answers as $studentAnswer) {
            $examQuestionId = $petaSoal[$studentAnswer->question_id] ?? null;
            if (! $examQuestionId) {
                continue;
            }

            Answer::updateOrCreate(
                ['attempt_id' => $attempt->id, 'question_id' => $examQuestionId],
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
