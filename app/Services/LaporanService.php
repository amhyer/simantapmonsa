<?php

namespace App\Services;

use App\Models\Kuis;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\Kehadiran;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanService
{
    public function laporanKelas($guruId, $kelas = null)
    {
        $query = Siswa::where('guru_id', $guruId);

        if ($kelas) {
            $query->where('kelas', $kelas);
        }

        $siswaList = $query->get();

        $hasil = [];
        foreach ($siswaList as $siswa) {
            $rataNilai = Nilai::where('siswa_id', $siswa->id)->avg('nilai');
            $kehadiran = Kehadiran::where('siswa_id', $siswa->id)
                ->whereBetween('tanggal', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->count();

            $hasil[] = [
                'siswa' => $siswa,
                'rata_nilai' => round($rataNilai ?? 0, 2),
                'kehadiran' => $kehadiran,
                'predikat' => $this->getPredikatNilai($rataNilai ?? 0),
            ];
        }

        return $hasil;
    }

    public function statistikKelas($guruId, $kelas = null)
    {
        $siswaList = Siswa::where('guru_id', $guruId)
            ->when($kelas, fn($q) => $q->where('kelas', $kelas))
            ->get();

        $totalSiswa = $siswaList->count();
        $rataNilai = Nilai::whereIn('siswa_id', $siswaList->pluck('id'))->avg('nilai');

        $kehadiranHadir = Kehadiran::whereIn('siswa_id', $siswaList->pluck('id'))
            ->where('status', 'Hadir')
            ->whereBetween('tanggal', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->count();

        $totalKehadiran = Kehadiran::whereIn('siswa_id', $siswaList->pluck('id'))
            ->whereBetween('tanggal', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->count();

        return [
            'total_siswa' => $totalSiswa,
            'rata_nilai' => round($rataNilai ?? 0, 2),
            'persen_kehadiran' => $totalKehadiran > 0
                ? round(($kehadiranHadir / $totalKehadiran) * 100, 2)
                : 0,
        ];
    }

    public function getPredikatNilai($nilai, $kkm = null)
    {
        $kkm = $kkm ?? getKKM();
        if ($nilai >= 90) return 'A';
        if ($nilai >= 80) return 'B';
        if ($nilai >= $kkm) return 'C';
        if ($nilai >= 60) return 'D';
        return 'E';
    }
}
