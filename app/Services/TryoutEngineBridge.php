<?php

namespace App\Services;

use App\Models\Exam\Attempt;
use App\Models\Exam\Bank;
use App\Models\Exam\Question;
use App\Models\Exam\Result as ExamResult;
use App\Models\Exam\Session as ExamSession;
use App\Models\Siswa;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tryout\TryoutQuestion;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3, langkah 5 (strangler): alur ujian Tryout memakai engine terpadu
 * `exam_*`, sementara tabel lama tetap ditulis ulang (mirror) agar laporan,
 * ekspor, dan tampilan admin yang belum dipindahkan tetap benar.
 *
 * Tabel lama tetap punya kunci alami yang sama seperti sebelumnya
 * (`tryout_session_id` + `siswa_id` + `attempt_number`), sehingga semua
 * pembaca lama tidak perlu diubah.
 */
class TryoutEngineBridge
{
    public function __construct(
        private LegacyExamMapper $mapper,
        private ExamAttemptService $attempts,
    ) {}

    /**
     * Pastikan sesi Tryout punya padanan di engine terpadu.
     */
    public function sesi(TryoutSession $tryoutSession): ExamSession
    {
        return $this->mapper->pastikanSesiTryout($tryoutSession);
    }

    /**
     * Mulai/lanjutkan percobaan: engine terpadu jadi sumber kebenaran,
     * baris lama dibuat sebagai cermin.
     */
    public function mulai(TryoutSession $tryoutSession, Siswa $siswa): TryoutResult
    {
        return DB::transaction(function () use ($tryoutSession, $siswa) {
            $sesi = $this->sesi($tryoutSession);
            $attempt = $this->attempts->mulai(
                $sesi,
                $siswa,
                auth()->id(),
                (int) $tryoutSession->durasi_menit
            );

            return $this->cerminHasil($attempt);
        }, 3);
    }

    /**
     * Selesaikan percobaan: nilai di engine terpadu, lalu salin seluruh hasil
     * (termasuk detail per mapel) ke baris lama.
     */
    public function selesaikan(TryoutResult $tryoutResult, ?float $passingGrade): TryoutResult
    {
        return DB::transaction(function () use ($tryoutResult, $passingGrade) {
            $attempt = $this->attemptDariLegacy($tryoutResult);

            if (! $attempt) {
                return $tryoutResult;
            }

            // Segarkan pemetaan supaya soal yang ditambahkan setelah ujian mulai
            // tetap ikut dinilai (maksimal poin tidak meleset).
            $this->sesi($tryoutResult->session);

            $this->attempts->selesaikan($attempt, $passingGrade);
            $this->cerminSeluruhJawaban($attempt);

            return $this->cerminHasil($attempt, true);
        }, 3);
    }

    public function attemptDariLegacy(TryoutResult $tryoutResult): ?Attempt
    {
        return Attempt::where('sumber_tabel', 'tryout_results')
            ->where('sumber_id', $tryoutResult->id)
            ->first();
    }

    /**
     * Pastikan sebuah id soal Tryout punya padanan di `exam_questions`.
     * Mengembalikan id soal engine, atau 0 bila tidak ditemukan.
     */
    private function pastikanSoal(Attempt $attempt, int $tryoutQuestionId): int
    {
        $ada = Question::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SOAL)
            ->where('sumber_id', $tryoutQuestionId)
            ->value('id');

        if ($ada) {
            return (int) $ada;
        }

        $tryoutQuestion = TryoutQuestion::with(['options', 'subject'])
            ->find($tryoutQuestionId);

        if (! $tryoutQuestion || ! $tryoutQuestion->subject) {
            return 0;
        }

        $bank = Bank::find($attempt->session->bank_id);
        if (! $bank) {
            return 0;
        }

        $section = $this->mapper->sectionTryout($bank, $tryoutQuestion->subject);

        return (int) $this->mapper->soalTryout($bank, $section, $tryoutQuestion)->id;
    }

    /**
     * Simpan jawaban berdasarkan soal Tryout (bukan id engine). Padanan soal
     * dipastikan ada lebih dulu, sehingga soal yang dibuat di tengah ujian
     * tidak pernah terlewat.
     */
    public function simpanJawabanSoal(TryoutResult $tryoutResult, TryoutQuestion $tryoutQuestion, ?string $jawaban): bool
    {
        $attempt = $this->attemptDariLegacy($tryoutResult);

        if (! $attempt) {
            return false;
        }

        $questionId = $this->pastikanSoal($attempt, $tryoutQuestion->id);

        if ($questionId === 0) {
            return false;
        }

        if (! $this->attempts->simpanJawaban($attempt, $questionId, $jawaban)) {
            return false;
        }

        $this->cerminJawaban($attempt, $questionId);

        return true;
    }

    /**
     * Buat/perbarui baris `tryout_results` dari sebuah attempt.
     */
    private function cerminHasil(Attempt $attempt, bool $sudahSelesai = false): TryoutResult
    {
        $examResult = ExamResult::where('attempt_id', $attempt->id)->first();
        $sesi = $attempt->session;

        $detail = $examResult?->detail_per_section ?? [];
        $detailLama = array_map(fn (array $baris) => [
            'mata_pelajaran_id' => $baris['mata_pelajaran_id'] ?? null,
            'nama_mapel' => $baris['nama_mapel'] ?? 'Umum',
            'skor' => $baris['skor'] ?? 0,
            'maksimal' => $baris['maksimal'] ?? 0,
            'persentase' => $baris['persentase'] ?? 0,
            'lulus' => ($baris['persentase'] ?? 0) >= 60,
        ], $detail);

        $hasil = TryoutResult::updateOrCreate(
            [
                'tryout_session_id' => (int) $sesi->sumber_id,
                'siswa_id' => $attempt->siswa_id,
                'attempt_number' => $attempt->attempt_number,
            ],
            [
                'nilai_total' => $examResult?->nilai_total ?? (float) $attempt->nilai_total,
                'nilai_maksimal' => $examResult?->nilai_maksimal ?? (float) $attempt->nilai_maksimal,
                'persentase' => $examResult?->persentase ?? 0,
                'peringkat' => $examResult?->peringkat,
                'lulus' => (bool) ($examResult?->lulus ?? false),
                'detail_nilai_mapel' => $detailLama,
                'analisis_kelemahan' => $examResult?->analisis_kelemahan ?? [],
                'rekomendasi_pembelajaran' => $examResult?->rekomendasi,
                'waktu_mulai' => $attempt->mulai_at,
                'waktu_selesai' => $sudahSelesai ? ($attempt->selesai_at ?? now()) : $attempt->selesai_at,
                'durasi_total_detik' => $attempt->selesai_at && $attempt->mulai_at
                    ? $attempt->selesai_at->diffInSeconds($attempt->mulai_at)
                    : 0,
            ]
        );

        // Simpan penunjuk balik agar pencarian attempt dari baris lama O(1).
        if ((int) $attempt->sumber_id !== (int) $hasil->id || $attempt->sumber_tabel !== 'tryout_results') {
            $attempt->forceFill([
                'sumber_tabel' => 'tryout_results',
                'sumber_id' => $hasil->id,
            ])->save();
        }

        return $hasil;
    }

    private function cerminJawaban(Attempt $attempt, int $questionId): void
    {
        $examQuestion = Question::find($questionId);
        $answer = $attempt->answers()->where('question_id', $questionId)->first();

        if (! $examQuestion || ! $answer) {
            return;
        }

        // Section memberi tahu mapel mana yang menyimpan jawaban ini.
        $subjectId = \App\Models\Tryout\Subject::where('id', (int) $examQuestion->section?->sumber_id)->value('id');

        StudentAnswer::updateOrCreate(
            [
                'tryout_session_id' => (int) $attempt->session->sumber_id,
                'siswa_id' => $attempt->siswa_id,
                'question_id' => (int) $examQuestion->sumber_id,
                'attempt_number' => $attempt->attempt_number,
            ],
            [
                'tryout_subject_id' => $subjectId,
                'jawaban' => $answer->jawaban,
                'adalah_benar' => $answer->adalah_benar,
                'poin_diperoleh' => $answer->poin_diperoleh,
                'waktu_mulai_jawab' => $answer->dijawab_at,
                'waktu_selesai_jawab' => $answer->dijawab_at,
                'durasi_detik' => 0,
            ]
        );
    }

    private function cerminSeluruhJawaban(Attempt $attempt): void
    {
        foreach ($attempt->answers()->pluck('question_id') as $questionId) {
            $this->cerminJawaban($attempt, (int) $questionId);
        }
    }
}
