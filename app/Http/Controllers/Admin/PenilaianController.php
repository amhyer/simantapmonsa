<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;

class PenilaianController extends Controller
{
    public function status()
    {
        $guru = User::where('peran', 'guru')->where('aktif', true)->orderBy('nama_lengkap')->get();

        $rows = $guru->map(function ($g) {
            $jmlSiswa = Siswa::where('guru_id', $g->id)->count();
            $jmlMateri = Materi::where('guru_id', $g->id)->count();
            $jmlKuis = Kuis::where('guru_id', $g->id)->count();
            $jmlNilai = Nilai::where('guru_id', $g->id)->count();
            $jmlErapot = NilaiErapot::where('guru_id', $g->id)->count();
            $terakhir = Aktivitas::where('guru_id', $g->id)->latest('created_at')->value('created_at');
            $terisi = collect([$jmlSiswa > 0, $jmlMateri > 0, $jmlKuis > 0, $jmlNilai > 0, $jmlErapot > 0])->filter()->count();

            return [
                'guru' => $g,
                'siswa' => $jmlSiswa,
                'materi' => $jmlMateri,
                'kuis' => $jmlKuis,
                'nilai' => $jmlNilai,
                'erapor' => $jmlErapot,
                'terakhir' => $terakhir,
                'lengkap' => $terisi === 5,
                'mulai' => $terisi > 0,
            ];
        });

        return view('admin.penilaian.status', compact('rows'));
    }

    public function statistik(Request $request)
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

        $perMapel = $nilai->groupBy('mata_pelajaran')->map(function ($items, $mapel) {
            return [
                'mapel' => $mapel ?: '-',
                'jumlah' => $items->count(),
                'rata' => round($items->avg('nilai_akhir'), 2),
                'tuntas' => $items->where('predikat', '!=', 'D')->count(),
            ];
        })->sortByDesc('rata')->values();

        $rataSekolah = $total > 0 ? round($nilai->avg('nilai_akhir'), 2) : 0;

        return view('admin.penilaian.statistik', compact('sebaran', 'perMapel', 'rataSekolah', 'total', 'kelasList', 'kelasFilter'));
    }
}
