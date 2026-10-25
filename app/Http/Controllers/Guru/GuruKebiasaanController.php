<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kebiasaan;
use App\Models\Siswa;

class GuruKebiasaanController extends Controller
{
    public function index()
    {
        $guruId = auth()->id();
        $siswaList = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();
        $siswaIds = $siswaList->pluck('id');

        $tanggalFilter = request('tanggal', now()->format('Y-m-d'));
        $mingguIni = now()->startOfWeek();
        $akhirMinggu = now()->endOfWeek();

        $semuaKebiasaan = Kebiasaan::whereIn('siswa_id', $siswaIds)->with('siswa')->latest('tanggal')->get();

        $kebiasaanMingguIni = $semuaKebiasaan->filter(fn($k) => \Carbon\Carbon::parse($k->tanggal)->between($mingguIni, $akhirMinggu));

        $totalLaporan = $semuaKebiasaan->count();
        $totalDilaporkan = $kebiasaanMingguIni->pluck('siswa_id')->unique()->count();
        $belumDilaporkan = $siswaList->count() - $totalDilaporkan;

        // Kolom kebiasaan berisi skor 1-4 (bukan boolean). Rata-rata dihitung
        // dari nilai angkanya, bukan dari nama kolom yang ikut terkoleksi.
        $fieldSkor = ['bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat'];
        $semuaSkor = $semuaKebiasaan->flatMap(function ($k) use ($fieldSkor) {
            return collect($fieldSkor)
                ->map(fn($f) => $k->$f)
                ->filter(fn($nilai) => is_numeric($nilai) && (int) $nilai > 0)
                ->values();
        });
        $rataRataSkor = $semuaSkor->count() > 0 ? round($semuaSkor->avg(), 1) : 0;

        // View mengakses $item->bangun_pagi dan $item->siswa, jadi harus tetap
        // berupa model Eloquent (bukan array hasil toArray()).
        $kebiasaanByDate = $semuaKebiasaan
            ->groupBy(fn($k) => (string) $k->tanggal)
            ->sortKeysDesc();

        $chartLabels = $siswaList->pluck('nama_peserta_didik')->toArray();
        $chartData = $siswaList->map(function ($siswa) use ($semuaKebiasaan, $fieldSkor) {
            $data = $semuaKebiasaan->filter(fn($k) => $k->siswa_id == $siswa->id);
            if ($data->isEmpty()) return 0;
            $skor = $data->flatMap(function ($k) use ($fieldSkor) {
                return collect($fieldSkor)
                    ->map(fn($f) => $k->$f)
                    ->filter(fn($nilai) => is_numeric($nilai) && (int) $nilai > 0)
                    ->values();
            });
            return $skor->count() > 0 ? round($skor->avg(), 1) : 0;
        })->toArray();

        return view('guru.kebiasaan.index', compact(
            'siswaList', 'totalDilaporkan', 'totalLaporan', 'rataRataSkor',
            'belumDilaporkan', 'tanggalFilter', 'kebiasaanByDate', 'chartLabels', 'chartData'
        ));
    }

    public function show($id)
    {
        $siswa = Siswa::where('guru_id', auth()->id())->findOrFail($id);

        $kebiasaan = Kebiasaan::where('siswa_id', $siswa->id)
            ->latest('tanggal')
            ->get();

        return view('guru.kebiasaan.show', compact('siswa', 'kebiasaan'));
    }
}
