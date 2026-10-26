<?php

namespace App\Services\Exam;

use App\Models\CbtTestSession;
use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Exam\Session as ExamSession;
use App\Models\Tryout\Session as TryoutSession;

/**
 * Fase 3, langkah 5b: pembaca berlaporan yang **hanya** membaca engine terpadu
 * `exam_*`.
 *
 * Ini jalur yang dibutuhkan langkah 6 (hapus tabel lama): membuktikan laporan
 * dapat disusun tanpa menyentuh `cbt_*` maupun `tryout_*` sama sekali.
 * Jalur lama tetap utuh sebagai pembanding sampai parity terbukti.
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

    /**
     * Baris laporan hasil satu sesi Tryout, dibaca dari `exam_*`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hasilSesiTryout(TryoutSession $tryoutSession): array
    {
        $examSession = ExamSession::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI)
            ->where('sumber_id', $tryoutSession->id)
            ->first();

        if (! $examSession) {
            return [];
        }

        return ExamAttempt::where('session_id', $examSession->id)
            ->with(['siswa', 'result'])
            ->orderBy('attempt_number')
            ->orderBy('id')
            ->get()
            ->map(function (ExamAttempt $attempt) {
                $result = $attempt->result;

                return [
                    'peserta' => $attempt->siswa?->nama_peserta_didik ?? 'Siswa dihapus',
                    'nis' => $attempt->siswa?->nis ?? '-',
                    'attempt_number' => $attempt->attempt_number,
                    'mulai' => $attempt->mulai_at,
                    'selesai' => $attempt->selesai_at,
                    'nilai_total' => $result?->nilai_total ?? $attempt->nilai_total,
                    'nilai_maksimal' => $result?->nilai_maksimal ?? $attempt->nilai_maksimal,
                    'persentase' => $result?->persentase ?? 0,
                    'lulus' => (bool) ($result?->lulus ?? false),
                    'peringkat' => $result?->peringkat,
                    'detail_per_mapel' => $result?->detail_per_section ?? [],
                ];
            })
            ->all();
    }

    /**
     * Ringkasan agregat Tryout lintas sesi, dibaca dari `exam_*`.
     *
     * @return array<string, mixed>
     */
    public function ringkasanTryout(): array
    {
        $sesi = ExamSession::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI);

        $totalSessions = (clone $sesi)->count();
        $activeSessions = (clone $sesi)->where('status', 'ongoing')->count();
        $totalPackages = \App\Models\Exam\Bank::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_PAKET)->count();

        $attempts = ExamAttempt::whereHas('session', function ($q) {
            $q->where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI);
        })->with('result')->get();

        $selesai = $attempts->filter(fn (ExamAttempt $a) => $a->selesai_at !== null);
        $lulus = $selesai->filter(fn (ExamAttempt $a) => (bool) ($a->result?->lulus ?? false));

        return [
            'total_packages' => $totalPackages,
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'total_results' => $attempts->count(),
            'average_pass_rate' => $selesai->count() > 0
                ? round(($lulus->count() / $selesai->count()) * 100, 2)
                : 0,
        ];
    }

    private function jumlahBenar(ExamAttempt $attempt): int
    {
        return $attempt->answers()->where('adalah_benar', true)->count();
    }
}
