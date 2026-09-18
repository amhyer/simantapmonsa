<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\RingkasanGuru;
use App\Models\Nilai;

class HasilBelajarController extends Controller
{
    public function index()
    {
        $ringkasan = RingkasanGuru::with('guru')->orderBy('nama_guru')->get();

        $analisisPerMapel = $ringkasan->groupBy('mata_pelajaran')->map(function ($items, $mapel) {
            return [
                'mata_pelajaran' => $mapel,
                'jumlah_kelas' => $items->count(),
                'rata_rata' => round($items->avg('rata_rata'), 2),
                'ketuntasan' => round($items->avg('ketuntasan_persen'), 2),
                'kehadiran' => round($items->avg('kehadiran_persen'), 2),
                'tertinggi' => $items->max('rata_rata'),
                'terendah' => $items->min('rata_rata'),
                'detail' => $items,
            ];
        })->values();

        $rataRataSekolah = $ringkasan->count() > 0 ? round($ringkasan->avg('rata_rata'), 1) : 0;
        $ketuntasanRataRata = $ringkasan->count() > 0 ? round($ringkasan->avg('ketuntasan_persen'), 1) : 0;

        $kelasTertinggi = $ringkasan->sortByDesc('rata_rata')->first()->mata_pelajaran ?? '—';
        $nilaiTertinggi = $ringkasan->sortByDesc('rata_rata')->first()->rata_rata ?? 0;

        $kkm = getKKM();
        $jumlahKelasRendah = $ringkasan->filter(fn($r) => $r->rata_rata < $kkm)->count();

        $nilaiTertinggiList = Nilai::with(['siswa', 'guru'])
            ->orderByDesc('nilai')
            ->limit(10)
            ->get();

        $nilaiTerendahList = Nilai::where('nilai', '>', 0)
            ->with(['siswa', 'guru'])
            ->orderBy('nilai')
            ->limit(10)
            ->get();

        return view('kepsek.hasil-belajar.index', compact(
            'ringkasan', 'analisisPerMapel', 'rataRataSekolah', 'kelasTertinggi',
            'nilaiTertinggi', 'ketuntasanRataRata', 'jumlahKelasRendah',
            'nilaiTertinggiList', 'nilaiTerendahList'
        ));
    }
}
