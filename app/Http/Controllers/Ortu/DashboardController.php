<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kehadiran;
use App\Models\Catatan;
use App\Models\Kebiasaan;
use App\Models\Cbt\TestResult;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard utama orang tua - ringkasan perkembangan anak.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.dashboard', ['anak' => null, 'anakList' => collect(), 'ringkasan' => null]);
        }

        $siswaId = $request->get('siswa_id') ?? $anakIds[0];

        if (!in_array($siswaId, $anakIds)) {
            abort(403, 'Anda tidak memiliki akses ke data siswa ini.');
        }

        $anak = Siswa::find($siswaId);
        $anakList = Siswa::whereIn('id', $anakIds)->get();

        // Ringkasan pantauan: nilai, kehadiran, kebiasaan, catatan
        $ringkasan = null;
        if ($anak) {
            $nilaiService = app(\App\Services\NilaiService::class);
            $na = $nilaiService->hitungNilaiAkhir($anak->id);
            $totalKehadiran = Kehadiran::where('siswa_id', $anak->id)->count();
            $hadir = Kehadiran::where('siswa_id', $anak->id)->where('status', 'H')->count();
            
            // CBT results
            $cbtResults = TestResult::where('siswa_id', $anak->id)
                ->with('testSession')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
            
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
        }

        return view('ortu.dashboard', compact('anak', 'anakList', 'ringkasan'));
    }
}
