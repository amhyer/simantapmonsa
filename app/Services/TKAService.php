<?php

namespace App\Services;

use App\Models\Dimensi;
use App\Models\Kebiasaan;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

class TKAService
{
    public function hitungTKA($siswaId)
    {
        $siswa = Siswa::find($siswaId);
        if (!$siswa) return null;

        $dimensi = Dimensi::where('siswa_id', $siswaId)->get();

        if ($dimensi->isEmpty()) {
            return [
                'siswa' => $siswa,
                'dimensi' => [],
                'total_skor' => 0,
                'predikat_umum' => 'Belum Dinilai',
            ];
        }

        $hasil = [];
        foreach ($dimensi as $d) {
            $skor = $d->skor ?? 0;
            $hasil[] = [
                'dimensi_id' => $d->id,
                'dimensi_nama' => $d->dimensi,
                'rata_rata' => round($skor, 2),
                'predikat' => $this->getPredikat($skor),
            ];
        }

        $totalSkor = collect($hasil)->avg('rata_rata');

        return [
            'siswa' => $siswa,
            'dimensi' => $hasil,
            'total_skor' => round($totalSkor, 2),
            'predikat_umum' => $this->getPredikat($totalSkor),
        ];
    }

    public function hitungTKAKelas($kelas)
    {
        $siswaList = Siswa::where('kelas', $kelas)->get();
        $hasil = [];

        foreach ($siswaList as $siswa) {
            $tkA = $this->hitungTKA($siswa->id);
            if ($tkA) {
                $hasil[] = $tkA;
            }
        }

        return $hasil;
    }

    public function getPredikat($nilai)
    {
        if ($nilai >= 90) return 'Sangat Baik';
        if ($nilai >= 75) return 'Baik';
        if ($nilai >= 60) return 'Cukup';
        if ($nilai >= 40) return 'Perlu Bimbingan';
        return 'Kurang';
    }

    public function getDimensiRingkasan()
    {
        return Dimensi::select('dimensi', DB::raw('COUNT(*) as jumlah'), DB::raw('AVG(skor) as avg_skor'))
            ->groupBy('dimensi')
            ->get();
    }
}
