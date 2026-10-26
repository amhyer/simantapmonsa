<?php

namespace App\Services;

use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Exam\Bank as ExamBank;
use App\Models\Exam\Session as ExamSession;
use App\Models\Siswa;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Support\Collection;

/**
 * Pembaca dasbor peran (siswa, ortu, guru, kepsek) yang **hanya** membaca
 * engine terpadu `exam_*` â€” tanpa menyentuh `cbt_*`/`tryout_*`.
 *
 * Dipakai langkah 6 (hapus tabel lama); sebelum itu hasilnya dibandingkan
 * dengan pembaca lama lewat `ExamDashboardParityTest`.
 */
class ExamDashboardService
{
    /**
     * Sesi CBT yang tersedia untuk satu siswa (dasbor siswa).
     *
     * @return Collection<int, ExamSession>
     */
    public function ujianTersedia(Siswa $siswa): Collection
    {
        return ExamSession::with('bank')
            ->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI)
            ->whereIn('status', ['published', 'running'])
            ->where('mulai_at', '<=', now())
            ->where('selesai_at', '>', now())
            ->orderBy('mulai_at')
            ->limit(50)
            ->get()
            ->filter(fn (ExamSession $s) => in_array($siswa->id, $s->peserta_ids ?? []))
            ->take(3)
            ->values();
    }

    /**
     * Hasil CBT terbaru seorang siswa (dasbor siswa & dasbor ortu).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function hasilCbtTerbaru(int $siswaId, int $limit = 5): Collection
    {
        return ExamAttempt::with(['session.bank', 'result'])
            ->where('siswa_id', $siswaId)
            ->whereHas('session', fn ($q) => $q->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI))
            ->whereNotNull('selesai_at')
            ->orderByDesc('selesai_at')
            ->limit($limit)
            ->get()
            ->map(fn (ExamAttempt $a) => [
                'judul' => $a->session?->nama ?? '-',
                'mata_pelajaran' => $a->session?->bank?->mata_pelajaran ?? '-',
                'submitted_at' => $a->selesai_at,
                'score' => (float) ($a->result?->persentase ?? 0),
                'lulus' => (float) ($a->result?->persentase ?? 0) >= 70,
            ])
            ->values();
    }

    /**
     * Kartu statistik CBT di dasbor guru (difilter per guru).
     *
     * @return array<string, int|float>
     */
    public function ringkasanCbtGuru(int $guruId): array
    {
        $sesi = fn () => ExamSession::where('guru_id', $guruId)
            ->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI);

        return [
            'total_bank' => ExamBank::where('guru_id', $guruId)
                ->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_BANK)
                ->count(),
            'sesi_aktif' => (clone $sesi())->whereIn('status', ['published', 'running'])->count(),
            'sesi_selesai' => (clone $sesi())->where('status', 'completed')->count(),
            'total_peserta' => ExamAttempt::whereHas('session', function ($q) use ($guruId) {
                $q->where('guru_id', $guruId)->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI);
            })->count(),
        ];
    }

    /**
     * Analisis CBT sekolah (dasbor kepsek), seluruh guru.
     *
     * @return array<string, int|float>
     */
    public function ringkasanCbtSekolah(): array
    {
        $sesi = fn () => ExamSession::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI);
        $peserta = fn () => ExamAttempt::whereHas('session', fn ($q) =>
            $q->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI));

        $totalPeserta = (clone $peserta())->count();
        $lulus = (clone $peserta())->whereHas('result', fn ($q) => $q->where('persentase', '>=', 70))->count();
        $avg = (clone $peserta())->join('exam_results', 'exam_results.attempt_id', '=', 'exam_attempts.id')
            ->avg('exam_results.persentase') ?? 0;

        return [
            'total_sessions' => (clone $sesi())->count(),
            'ongoing' => (clone $sesi())->whereIn('status', ['published', 'running'])->count(),
            'completed' => (clone $sesi())->where('status', 'completed')->count(),
            'total_participants' => $totalPeserta,
            'avg_score' => round((float) $avg, 2),
            'pass_rate' => $totalPeserta > 0 ? round($lulus / max($totalPeserta, 1) * 100, 2) : 0,
        ];
    }

    /**
     * Nilai Tryout yang ditampilkan dasbor siswa (semua hasil).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function hasilTryoutSiswa(int $siswaId): Collection
    {
        return ExamAttempt::with(['session.bank', 'result'])
            ->where('siswa_id', $siswaId)
            ->whereHas('session', fn ($q) => $q->where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI))
            ->whereNotNull('selesai_at')
            ->orderByDesc('selesai_at')
            ->get()
            ->map(fn (ExamAttempt $a) => [
                'nama_ujian' => $a->session?->nama ?? '-',
                'persentase' => (float) ($a->result?->persentase ?? 0),
                'lulus' => (bool) ($a->result?->lulus ?? false),
            ])
            ->values();
    }
}
