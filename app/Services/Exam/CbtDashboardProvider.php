<?php

namespace App\Services\Exam;

use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Services\ExamDashboardService;

/**
 * Satu pintu data CBT untuk dasbor peran.
 *
 * Fase 3 langkah 6b: ketika `simantap.exam_engine_readers` aktif, data dibaca
 * dari engine terpadu `exam_*`; jika tidak, dari tabel lama `cbt_*` (perilaku
 * produksi saat ini). Kedua cabang mengembalikan bentuk yang sama sehingga view
 * tidak perlu tahu asalnya, dan parity-nya diuji di `ExamReaderTest`.
 */
class CbtDashboardProvider
{
    public function __construct(private ExamDashboardService $engine) {}

    private function pakaiEngine(): bool
    {
        return (bool) config('simantap.exam_engine_readers', false);
    }

    /**
     * Ujian yang bisa diikuti siswa (maks 3, seperti dasbor lama).
     *
     * @return array<int, array{id:int, judul:string, mapel:string, closes_at:mixed}>
     */
    public function ujianTersedia(Siswa $siswa): array
    {
        if ($this->pakaiEngine()) {
            return $this->engine->ujianTersedia($siswa)
                ->map(fn ($sesi) => [
                    'id' => (int) ($sesi->sumber_id ?? $sesi->id),
                    'judul' => $sesi->nama,
                    'mapel' => $sesi->bank?->mata_pelajaran ?? '-',
                    'closes_at' => $sesi->selesai_at,
                ])
                ->all();
        }

        return CbtTestSession::with('bank')
            ->whereIn('status', ['published', 'running'])
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>', now())
            ->orderBy('opens_at')
            ->limit(50)
            ->get()
            ->filter(fn ($s) => in_array($siswa->id, $s->participant_ids ?? []))
            ->take(3)
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'judul' => $s->title,
                'mapel' => $s->bank?->subject ?? '-',
                'closes_at' => $s->closes_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Hasil CBT terbaru seorang siswa (dasbor siswa & ortu).
     *
     * @return array<int, array{judul:string, mapel:string, submitted_at:mixed, score:float}>
     */
    public function hasilTerbaru(int $siswaId, int $limit = 5): array
    {
        if ($this->pakaiEngine()) {
            return $this->engine->hasilCbtTerbaru($siswaId, $limit)
                ->map(fn ($baris) => [
                    'judul' => $baris['judul'],
                    'mapel' => $baris['mata_pelajaran'],
                    'submitted_at' => $baris['submitted_at'],
                    'score' => $baris['score'],
                ])
                ->all();
        }

        return CbtResult::where('siswa_id', $siswaId)
            ->with('session.bank')
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'judul' => $r->session?->title ?? '-',
                'mapel' => $r->session?->bank?->subject ?? '-',
                'submitted_at' => $r->submitted_at,
                'score' => (float) ($r->score ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Kartu statistik CBT guru.
     *
     * @return array<string, int|float>
     */
    public function ringkasanGuru(int $guruId): array
    {
        if ($this->pakaiEngine()) {
            return $this->engine->ringkasanCbtGuru($guruId);
        }

        return [
            'total_bank' => \App\Models\CbtQuestionBank::where('guru_id', $guruId)->count(),
            'sesi_aktif' => CbtTestSession::where('guru_id', $guruId)
                ->whereIn('status', ['published', 'running'])->count(),
            'sesi_selesai' => CbtTestSession::where('guru_id', $guruId)->where('status', 'completed')->count(),
            'total_peserta' => CbtResult::whereHas('session', fn ($q) => $q->where('guru_id', $guruId))->count(),
        ];
    }

    /**
     * Analisis CBT sekolah (dasbor kepsek).
     *
     * @return array<string, int|float>
     */
    public function ringkasanSekolah(): array
    {
        if ($this->pakaiEngine()) {
            return $this->engine->ringkasanCbtSekolah();
        }

        $totalPeserta = CbtResult::count();
        $passRate = CbtResult::where('score', '>=', 70)->count() / max($totalPeserta, 1) * 100;

        return [
            'total_sessions' => CbtTestSession::count(),
            'ongoing' => CbtTestSession::whereIn('status', ['published', 'running'])->count(),
            'completed' => CbtTestSession::where('status', 'completed')->count(),
            'total_participants' => $totalPeserta,
            'avg_score' => (float) (CbtResult::avg('score') ?? 0),
            'pass_rate' => (float) $passRate,
        ];
    }
}
