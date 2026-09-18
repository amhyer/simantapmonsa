<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AktivitasController extends Controller
{
    public function index()
    {
        $aktivitas = Aktivitas::with('guru')
            ->latest()
            ->limit(100)
            ->get();

        $guruAktif = User::where('peran', 'guru')
            ->whereNotNull('terakhir_masuk')
            ->orderBy('terakhir_masuk', 'desc')
            ->get();

        $statistikAktivitas = Aktivitas::selectRaw('jenis, count(*) as jumlah')
            ->groupBy('jenis')
            ->get();

        $penggunaAktif = User::whereNotNull('terakhir_masuk')
            ->where('terakhir_masuk', '>=', now()->subDays(7))
            ->count();

        $totalAktivitas = Aktivitas::count();

        $penggunaAktifList = User::whereNotNull('terakhir_masuk')
            ->orderBy('terakhir_masuk', 'desc')
            ->limit(10)
            ->get();

        // Batch query activity counts per guru
        $aktivitasCounts = Aktivitas::whereIn('guru_id', $penggunaAktifList->pluck('id'))
            ->selectRaw('guru_id, count(*) as jumlah')
            ->groupBy('guru_id')
            ->get()
            ->pluck('jumlah', 'guru_id');

        $penggunaAktifList = $penggunaAktifList->map(function ($u) use ($aktivitasCounts) {
            return (object) [
                'nama' => $u->nama_lengkap,
                'peran' => $u->peran,
                'total' => $aktivitasCounts->get($u->id, 0),
            ];
        })
            ->sortByDesc('total')
            ->values();

        $days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $days->push(now()->subDays($i)->format('D'));
        }

        $chartHariLabels = $days->toArray();
        $chartGuruData = $this->getChartPerRole('guru', 7);
        $chartSiswaData = $this->getChartPerRole('siswa', 7);
        $chartAdminData = $this->getChartPerRole('admin', 7);

        $chartDistribusiData = User::select('peran', DB::raw('count(*) as jumlah'))
            ->groupBy('peran')
            ->pluck('jumlah', 'peran')
            ->toArray();

        $siswaAktif = User::where('peran', 'siswa')
            ->whereNotNull('terakhir_masuk')
            ->where('terakhir_masuk', '>=', now()->subDays(7))
            ->count();

        $totalForPercent = max($totalAktivitas, 1);
        $statistikAktivitas = $statistikAktivitas->map(function ($item) use ($totalForPercent) {
            $item->persentase = round(($item->jumlah / $totalForPercent) * 100, 1);
            $item->ikon = match($item->jenis) {
                'login' => '🔑', 'logout' => '🚪', 'input_nilai' => '📝',
                'input_kehadiran' => '📋', 'lihat_laporan' => '📊',
                default => '📋',
            };
            return $item;
        });

        return view('kepsek.aktivitas.index', compact(
            'aktivitas', 'guruAktif', 'statistikAktivitas',
            'penggunaAktif', 'totalAktivitas', 'siswaAktif',
            'penggunaAktifList', 'chartHariLabels', 'chartGuruData',
            'chartSiswaData', 'chartAdminData', 'chartDistribusiData'
        ));
    }

    private function getChartPerRole(string $peran, int $days): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $dailyCounts = Aktivitas::whereHas('guru', function ($q) use ($peran) {
            $q->where('peran', $peran);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as tgl, count(*) as jumlah')
            ->groupBy('tgl')
            ->get()
            ->pluck('jumlah', 'tgl');

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $tgl = now()->subDays($i)->format('Y-m-d');
            $data[] = $dailyCounts->get($tgl, 0);
        }
        return $data;
    }
}
