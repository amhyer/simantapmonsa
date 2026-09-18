<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\HasilKuis;
use App\Models\Nilai;
use App\Models\Siswa;

class NilaiController extends Controller
{
    use HasSiswaLookup;
    public function index()
    {
        $siswa = $this->getSiswa();

        if (!$siswa) {
            return view('siswa.nilai.index', [
                'nilai' => collect(),
                'hasilKuis' => collect(),
                'siswa' => null,
            ]);
        }

        $nilai = Nilai::where('siswa_id', $siswa->id)
            ->orderBy('tanggal', 'desc')
            ->get();

        $hasilKuis = HasilKuis::where('siswa_id', $siswa->id)
            ->orderBy('waktu', 'desc')
            ->get();

        return view('siswa.nilai.index', compact('nilai', 'hasilKuis', 'siswa'));
    }

    public function detail($jenis)
    {
        $siswa = $this->getSiswa();

        if (!$siswa) {
            abort(404);
        }

        $allowedJenis = ['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS', 'Kuis'];

        if (!in_array($jenis, $allowedJenis)) {
            abort(404);
        }

        if ($jenis === 'Kuis') {
            $hasilKuis = HasilKuis::where('siswa_id', $siswa->id)
                ->orderBy('waktu', 'desc')
                ->get();

            return view('siswa.nilai.detail', [
                'jenis' => $jenis,
                'nilai' => collect(),
                'hasilKuis' => $hasilKuis,
                'siswa' => $siswa,
            ]);
        }

        $nilai = Nilai::where('siswa_id', $siswa->id)
            ->where('jenis', $jenis)
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('siswa.nilai.detail', [
            'jenis' => $jenis,
            'nilai' => $nilai,
            'hasilKuis' => collect(),
            'siswa' => $siswa,
        ]);
    }
}
