<?php

namespace App\Services\Exam;

use App\Models\CbtTestSession;
use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Exam\Result as ExamResult;
use App\Models\Exam\Session as ExamSession;

/**
 * Fase 3, langkah 5b: pembaca berlaporan yang **hanya** membaca engine terpadu
 * `exam_*`.
 *
 * Ini jalur yang dibutuhkan langkah 6 (hapus tabel lama): membuktikan laporan
 * dapat disusun tanpa menyentuh `cbt_*` sama sekali. Jalur lama tetap utuh
 * sebagai pembanding; parity diuji di `ExamReportParityTest`.
 */
class ExamReportService
{
    /**
     * Baris laporan hasil satu sesi CBT, dibaca dari `exam_*`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hasilSesiCbt(CbtTestSession $cbtSession): array
    {
        $examSession = ExamSession::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI)
            ->where('sumber_id', $cbtSession->id)
            ->first();

        if (! $examSession) {
            return [];
        }

        return ExamAttempt::where('session_id', $examSession->id)
            ->with(['siswa', 'result'])
            ->withCount('violations')
            ->orderBy('id')
            ->get()
            ->map(function (ExamAttempt $attempt) {
                return [
                    'peserta' => $attempt->siswa?->nama_peserta_didik ?? 'Siswa dihapus',
                    'nis' => $attempt->siswa?->nis ?? '-',
                    'mulai' => $attempt->mulai_at,
                    'selesai' => $attempt->selesai_at,
                    'benar' => $this->jumlahBenar($attempt),
                    'skor' => $attempt->result?->persentase ?? $attempt->nilai_total,
                    'pelanggaran' => $attempt->violations_count,
                ];
            })
            ->all();
    }

    private function jumlahBenar(ExamAttempt $attempt): int
    {
        return $attempt->answers()->where('adalah_benar', true)->count();
    }
}
