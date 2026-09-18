<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Nilai;
use App\Models\Kuis;
use App\Models\HasilKuis;
use App\Models\Siswa;

class AnalisisController extends Controller
{
    public function index()
    {
        $guruId = auth()->id();
        $siswaList = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();
        $kuisList = Kuis::where('guru_id', $guruId)->latest('tanggal')->get();

        $totalKuis = $kuisList->count();

        $allNilai = Nilai::where('guru_id', $guruId)->get();
        $semuaNilai = $allNilai->pluck('nilai');
        $rataRataKelas = $semuaNilai->count() > 0 ? round($semuaNilai->avg(), 1) : 0;
        $skorTertinggi = $semuaNilai->count() > 0 ? $semuaNilai->max() : 0;

        $kkm = getKKM($guruId);
        $tuntas = $semuaNilai->filter(fn($n) => $n >= $kkm)->count();
        $ketuntasan = $semuaNilai->count() > 0 ? round($tuntas / $semuaNilai->count() * 100, 1) : 0;

        $kuisLabels = $kuisList->pluck('judul')->toArray();
        $nilaiPerKuis = $allNilai->groupBy('kuis_id');
        $kuisRataRata = [];
        foreach ($kuisList as $kuis) {
            $nilaiKuis = $nilaiPerKuis->get($kuis->id, collect())->pluck('nilai');
            $kuisRataRata[] = $nilaiKuis->count() > 0 ? round($nilaiKuis->avg(), 1) : 0;
        }

        $predikatCount = [0, 0, 0, 0];
        foreach ($semuaNilai as $n) {
            if ($n >= 90) $predikatCount[0]++;
            elseif ($n >= 75) $predikatCount[1]++;
            elseif ($n >= $kkm) $predikatCount[2]++;
            else $predikatCount[3]++;
        }

        $soalSulit = $this->getButirSoalSulit($guruId);

        $nilaiPerSiswa = $allNilai->groupBy('siswa_id');
        $matriksSiswa = [];
        foreach ($siswaList as $siswa) {
            $skor = [];
            $siswaNilai = $nilaiPerSiswa->get($siswa->id, collect());
            foreach ($kuisList as $kuis) {
                $skor[$kuis->id] = $siswaNilai->where('kuis_id', $kuis->id)->first()?->nilai;
            }
            $nilaiSiswa = array_filter($skor);
            $rataRata = count($nilaiSiswa) > 0 ? round(array_sum($nilaiSiswa) / count($nilaiSiswa), 1) : 0;
            $matriksSiswa[] = [
                'nama' => $siswa->nama_peserta_didik,
                'skor' => $skor,
                'rata_rata' => $rataRata,
            ];
        }

        return view('guru.analisis.index', compact(
            'totalKuis', 'rataRataKelas', 'ketuntasan', 'kkm', 'skorTertinggi',
            'soalSulit', 'matriksSiswa', 'kuisList', 'kuisLabels', 'kuisRataRata', 'predikatCount'
        ));
    }

    public function detail($siswaId)
    {
        $siswa = Siswa::where('guru_id', auth()->id())->findOrFail($siswaId);
        $nilai = Nilai::where('siswa_id', $siswaId)->latest('tanggal')->get();
        $hasilKuis = HasilKuis::where('siswa_id', $siswaId)->with('kuis')->latest('waktu')->get();

        $rataRata = $nilai->count() > 0 ? round($nilai->avg('nilai'), 2) : 0;
        $rataKuis = $hasilKuis->count() > 0 ? round($hasilKuis->avg('skor'), 2) : 0;

        return view('guru.analisis.detail', compact(
            'siswa', 'nilai', 'hasilKuis', 'rataRata', 'rataKuis'
        ));
    }

    private function getButirSoalSulit($guruId)
    {
        $hasilKuis = HasilKuis::where('guru_id', $guruId)->with('kuis')->get();

        $soalStats = [];
        foreach ($hasilKuis as $hasil) {
            if (empty($hasil->jawaban)) continue;
            foreach ($hasil->jawaban as $idx => $jawaban) {
                $key = "kuis_{$hasil->kuis_id}_soal_{$idx}";
                if (!isset($soalStats[$key])) {
                    $kuis = $hasil->kuis;
                    $soalStats[$key] = [
                        'kuis_id' => $hasil->kuis_id,
                        'judul_kuis' => $kuis->judul ?? 'Kuis #' . $hasil->kuis_id,
                        'pertanyaan' => $jawaban['pertanyaan'] ?? 'Soal #' . ($idx + 1),
                        'soal_ke' => $idx + 1,
                        'total_jawaban' => 0,
                        'benar' => 0,
                    ];
                }
                $soalStats[$key]['total_jawaban']++;
                if (!empty($jawaban['benar'])) {
                    $soalStats[$key]['benar']++;
                }
            }
        }

        return collect($soalStats)
            ->map(function ($stat) {
                $stat['rata_rata_benar'] = $stat['total_jawaban'] > 0 ? round($stat['benar'] / $stat['total_jawaban'] * 100, 2) : 0;
                return $stat;
            })
            ->sortBy('rata_rata_benar')
            ->take(10)
            ->values();
    }
}
