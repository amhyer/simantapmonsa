<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Catatan;
use App\Models\Kebiasaan;
use App\Models\Kehadiran;

class DashboardController extends Controller
{
    use HasAnakLookup;

    /**
     * Dashboard utama orang tua - ringkasan perkembangan anak.
     */
    public function index()
    {
        $anakList = $this->anakList();
        $anak = $this->getAnak($anakList);

        if (! $anak) {
            return view('ortu.dashboard', [
                'anak' => null, 'anakList' => $anakList, 'ringkasan' => null,
            ]);
        }

        // Ringkasan pantauan: nilai, kehadiran, kebiasaan, catatan
        $nilaiService = app(\App\Services\NilaiService::class);
        $na = $nilaiService->hitungNilaiAkhir($anak->id);
        $totalKehadiran = Kehadiran::where('siswa_id', $anak->id)->count();
        $hadir = Kehadiran::where('siswa_id', $anak->id)->where('status', 'H')->count();

        // Hasil CBT (hanya saat modul asesmen aktif). Sumber data dipilih
        // provider: engine `exam_*` atau tabel lama `cbt_*`.
        $cbtResults = config('simantap.cbt_enabled', false)
            ? app(\App\Services\Exam\CbtDashboardProvider::class)->hasilTerbaru($anak->id)
            : [];

        $ringkasan = [
            'nilai_akhir' => $na['nilai_akhir'],
            'predikat' => $na['nilai_akhir'] > 0
                ? $nilaiService->getPredikat($na['nilai_akhir'], getKKM($anak->guru_id))
                : null,
            'hadir_persen' => $totalKehadiran > 0 ? round($hadir / $totalKehadiran * 100, 1) : null,
            'kebiasaan_minggu' => Kebiasaan::where('siswa_id', $anak->id)
                ->where('tanggal', '>=', now()->startOfWeek()->toDateString())
                ->count(),
            'catatan' => Catatan::where('siswa_id', $anak->id)
                ->orderByDesc('tanggal')->limit(3)->get(),
            'cbt_results' => $cbtResults,
        ];

        return view('ortu.dashboard', compact('anak', 'anakList', 'ringkasan'));
    }
}
