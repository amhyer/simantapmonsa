<?php

namespace App\Services;

use App\Models\Nilai;
use App\Models\HasilKuis;
use App\Models\Kehadiran;
use App\Models\Kuis;

class NilaiService
{
    private $kuisCache = [];

    public function hitungNilaiAkhir($siswaId)
    {
        $nilai = Nilai::where('siswa_id', $siswaId)->get();
        $hasilKuis = HasilKuis::where('siswa_id', $siswaId)->get();

        return $this->hitungNilaiAkhirBatch($nilai, $hasilKuis);
    }

    public function hitungNilaiAkhirBatch($nilaiCollection, $hasilKuisCollection)
    {
        $totalBobot = 0;
        $totalNilai = 0;

        $bobot = [
            'Tugas' => 0.30,
            'Ulangan Harian' => 0.30,
            'PTS' => 0.20,
            'PAS' => 0.20,
        ];

        $rataPerJenis = [];
        foreach ($bobot as $jenis => $b) {
            $items = $nilaiCollection->where('jenis', $jenis);
            if ($items->count()) {
                $rata = $items->avg('nilai');
                $rataPerJenis[$jenis] = $rata;
                $totalNilai += $rata * $b;
                $totalBobot += $b;
            }
        }

        $praktik = $nilaiCollection->where('jenis', 'Praktik');
        if ($praktik->count()) {
            $rataPerJenis['Praktik'] = $praktik->avg('nilai');
        }

        $kuisLokal = $hasilKuisCollection->filter(function ($hk) {
            if (!isset($this->kuisCache[$hk->kuis_id])) {
                $this->kuisCache[$hk->kuis_id] = Kuis::find($hk->kuis_id);
            }
            $kuis = $this->kuisCache[$hk->kuis_id];
            return $kuis && $kuis->sumber === 'lokal' && $kuis->cara_nilai === 'guru';
        });

        if ($kuisLokal->count()) {
            $rataKuis = $kuisLokal->avg('skor');
            $totalNilai += $rataKuis * 0.20;
            $totalBobot += 0.20;
        }

        $nilaiAkhir = $totalBobot > 0 ? round($totalNilai / $totalBobot * 100, 2) : 0;

        return [
            'nilai_akhir' => $nilaiAkhir,
            'rata_per_jenis' => $rataPerJenis,
            'total_nilai' => $totalNilai,
            'total_bobot' => $totalBobot,
        ];
    }

    public function getPredikat($nilai, $kkm = 70)
    {
        $selisih = $kkm * 0.1;

        if ($nilai >= $kkm + $selisih * 2) {
            return ['huruf' => 'A', 'label' => 'Sangat Baik', 'kelas' => 'ok'];
        } elseif ($nilai >= $kkm + $selisih) {
            return ['huruf' => 'B', 'label' => 'Baik', 'kelas' => 'ok'];
        } elseif ($nilai >= $kkm) {
            return ['huruf' => 'C', 'label' => 'Cukup', 'kelas' => 'warn'];
        } else {
            return ['huruf' => 'D', 'label' => 'Perlu Bimbingan', 'kelas' => 'bad'];
        }
    }

    public function hitungKehadiran($siswaId)
    {
        $total = Kehadiran::where('siswa_id', $siswaId)->count();
        $hadir = Kehadiran::where('siswa_id', $siswaId)->where('status', 'H')->count();

        return $total > 0 ? round($hadir / $total * 100, 1) : 0;
    }
}
