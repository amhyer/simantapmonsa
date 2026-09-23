<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NilaiErapot;
use Illuminate\Http\Request;

class PerkembanganController extends Controller
{
    public function index()
    {
        $tren = NilaiErapot::whereNotNull('nilai_akhir')
            ->get()
            ->groupBy(function ($n) {
                return ($n->tahun_ajaran ?? '-') . ' ' . ucfirst($n->semester ?? '-');
            })
            ->map(function ($items, $label) {
                return [
                    'label' => $label,
                    'jumlah' => $items->count(),
                    'rata' => round($items->avg('nilai_akhir'), 2),
                    'tuntas' => $items->where('predikat', '!=', 'D')->count(),
                ];
            })
            ->sortKeys()
            ->values();

        return view('admin.perkembangan.index', compact('tren'));
    }

    public function grafik(Request $request)
    {
        $kelasFilter = $request->get('kelas');
        $kelasList = NilaiErapot::whereNotNull('kelas')->distinct()->orderBy('kelas')->pluck('kelas');

        $query = NilaiErapot::whereNotNull('nilai_akhir');
        if ($kelasFilter) {
            $query->where('kelas', $kelasFilter);
        }
        $nilai = $query->get();
        $total = $nilai->count();

        $sebaran = collect(['A', 'B', 'C', 'D'])->mapWithKeys(function ($p) use ($nilai, $total) {
            $jml = $nilai->where('predikat', $p)->count();
            return [$p => ['jumlah' => $jml, 'persen' => $total > 0 ? round($jml / $total * 100, 1) : 0]];
        });

        $perKelas = NilaiErapot::whereNotNull('nilai_akhir')
            ->get()
            ->groupBy('kelas')
            ->map(function ($items, $kelas) {
                return [
                    'kelas' => $kelas ?: '-',
                    'jumlah' => $items->count(),
                    'rata' => round($items->avg('nilai_akhir'), 2),
                ];
            })
            ->sortKeys()
            ->values();

        return view('admin.perkembangan.grafik', compact('sebaran', 'perKelas', 'total', 'kelasList', 'kelasFilter'));
    }
}
