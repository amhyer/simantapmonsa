<?php

namespace App\Services;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\CbtViolation;
use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Exam\Question as ExamQuestion;
use App\Models\Exam\Result as ExamResult;
use App\Models\Exam\Session as ExamSession;
use App\Models\Exam\Violation as ExamViolation;
use App\Services\Exam\LegacyExamMapper;

/**
 * Fase 3, langkah 5 (strangler) untuk CBT.
 *
 * Bukan pengganti `CbtExamService` — bridge ini hanya **lapisan penulisan ke
 * engine terpadu**. `CbtExamService` tetap pemilik aturan siklus (kunci,
 * kedaluwarsa, deadline, idempotensi), lalu memanggil bridge di tiga titik
 * simpan. Dengan begitu semantik yang diuji 28 test CBT tetap persis sama,
 * sementara `exam_*` menjadi tempat data disimpan.
 *
 * Penilaian tetap bersumber dari snapshot sesi (`question_snapshot`), disalin
 * ke `exam_questions.kunci_jawaban`, sehingga mengubah bank setelah publikasi
 * tidak menggeser nilai.
 */
class CbtEngineBridge
{
    public function __construct(
        private LegacyExamMapper $mapper,
    ) {}

    /**
     * Pastikan bank/sesi CBT punya padanan di engine terpadu, dan selaraskan
     * kunci dari snapshot.
     */
    public function sesi(CbtTestSession $cbtSession): ExamSession
    {
        $cbtBank = CbtQuestionBank::findOrFail($cbtSession->bank_id);
        $bank = $this->mapper->bankCbt($cbtBank);

        foreach ($cbtSession->question_snapshot ?? [] as $snapshot) {
            $question = ExamQuestion::where('bank_id', $bank->id)
                ->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SOAL)
                ->where('sumber_id', $snapshot['id'])
                ->first();

            if ($question && (string) $question->kunci_jawaban !== (string) $snapshot['correct_answer']) {
                $question->forceFill(['kunci_jawaban' => $snapshot['correct_answer']])->save();
            }
        }

        return $this->mapper->sesiCbt($bank, $cbtSession);
    }

    /**
     * Buat attempt engine untuk baris `cbt_results` yang baru dibuat.
     */
    public function catatAttempt(CbtResult $cbtResult): void
    {
        $examSession = $this->sesi($cbtResult->session);

        $attempt = ExamAttempt::updateOrCreate(
            ['sumber_tabel' => 'cbt_results', 'sumber_id' => $cbtResult->id],
            [
                'session_id' => $examSession->id,
                'siswa_id' => $cbtResult->siswa_id,
                'user_id' => $cbtResult->user_id,
                'attempt_number' => 1,
                'mulai_at' => $cbtResult->started_at,
                'deadline_at' => $cbtResult->deadline_at,
                'status' => ExamAttempt::STATUS_BERJALAN,
            ]
        );

        // Jawaban yang mungkin sudah tersimpan (mis. saat submit akhir) ikut
        // dipetakan agar engine tidak ketinggalan.
        $this->catatJawaban($cbtResult, is_array($cbtResult->answers) ? $cbtResult->answers : []);

        unset($attempt);
    }

    /**
     * Tulis jawaban dari blob JSON CBT ke `exam_answers`.
     *
     * @param  array<int|string, string>  $answers
     */
    public function catatJawaban(CbtResult $cbtResult, array $answers): void
    {
        if ($answers === []) {
            return;
        }

        $attempt = $this->attempt($cbtResult);
        if (! $attempt) {
            return;
        }

        $questions = ExamQuestion::with('options')
            ->where('bank_id', $attempt->session->bank_id)
            ->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SOAL)
            ->whereIn('sumber_id', array_map('intval', array_keys($answers)))
            ->get()
            ->keyBy(fn (ExamQuestion $q) => (int) $q->sumber_id);

        $grader = app(ExamGradingService::class);

        foreach ($answers as $cbtQuestionId => $jawaban) {
            $question = $questions[(int) $cbtQuestionId] ?? null;
            if (! $question) {
                continue;
            }
            $grader->simpanJawaban($attempt, $question, (string) $jawaban);
        }
    }

    /**
     * Tulis hasil akhir ke `exam_attempts` + `exam_results`.
     */
    public function catatPenilaian(CbtResult $cbtResult): void
    {
        $attempt = $this->attempt($cbtResult);
        if (! $attempt) {
            return;
        }

        $attempt->forceFill([
            'selesai_at' => $attempt->selesai_at ?? $cbtResult->submitted_at ?? now(),
            'status' => ExamAttempt::STATUS_DINILAI,
            'nilai_total' => (float) $cbtResult->score,
            'nilai_maksimal' => 100.0,
        ])->save();

        ExamResult::updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
                'nilai_total' => (float) $cbtResult->score,
                'nilai_maksimal' => 100.0,
                'persentase' => (float) $cbtResult->score,
                'sumber_tabel' => 'cbt_results',
                'sumber_id' => $cbtResult->id,
            ]
        );
    }

    public function attempt(CbtResult $cbtResult): ?ExamAttempt
    {
        return ExamAttempt::where('sumber_tabel', 'cbt_results')
            ->where('sumber_id', $cbtResult->id)
            ->first();
    }

    /**
     * Cerminkan catatan pelanggaran CBT ke `exam_violations`.
     *
     * Idempoten lewat jejak `sumber_tabel`/`sumber_id`, jadi memanggil ulang
     * tidak menggandakan baris.
     */
    public function catatPelanggaran(CbtViolation $violation): void
    {
        $attempt = $this->attempt($violation->result);

        if (! $attempt) {
            return;
        }

        ExamViolation::updateOrCreate(
            ['sumber_tabel' => 'cbt_violations', 'sumber_id' => $violation->id],
            [
                'attempt_id' => $attempt->id,
                'tipe' => $violation->type,
            ]
        );
    }
}
