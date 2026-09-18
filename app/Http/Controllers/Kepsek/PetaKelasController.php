<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Siswa;
use App\Models\PengaturanGuru;
use App\Models\OrangTuaSiswa;

class PetaKelasController extends Controller
{
    public function index()
    {
        $guruList = User::where('peran', 'guru')
            ->where('aktif', true)
            ->with(['pengaturanGuru', 'siswa' => function ($q) {
                $q->where('aktif', true);
            }])
            ->orderBy('nama_lengkap')
            ->get();

        $peta = $guruList->map(function ($guru) {
            $siswaAktif = $guru->siswa->where('aktif', true);
            $kelasList = $siswaAktif->pluck('kelas')->unique()->values();

            $akunOrtu = 0;
            $akunSiswa = 0;
            foreach ($siswaAktif as $s) {
                if (OrangTuaSiswa::where('siswa_id', $s->id)->exists()) $akunOrtu++;
            }

            return [
                'guru' => $guru,
                'kelas' => $kelasList->implode(', '),
                'jumlah_siswa' => $siswaAktif->count(),
                'akun_ortu' => $akunOrtu,
                'mapel' => $guru->pengaturanGuru->mata_pelajaran ?? '-',
                'kkm' => $guru->pengaturanGuru->kkm ?? getKKM(),
                'terakhir_masuk' => $guru->terakhir_masuk,
            ];
        });

        $stats = [
            'total_guru' => $guruList->count(),
            'total_kelas' => $guruList->flatMap(function ($g) {
                return $g->siswa->pluck('kelas');
            })->unique()->count(),
            'total_siswa' => $guruList->sum(fn ($g) => $g->siswa->where('aktif', true)->count()),
        ];

        return view('kepsek.peta-kelas.index', compact('peta', 'stats'));
    }

    public function detail($guruId)
    {
        $guru = User::where('peran', 'guru')->where('id', $guruId)->firstOrFail();
        $pengaturan = PengaturanGuru::where('guru_id', $guruId)->first();
        $siswaList = Siswa::where('guru_id', $guruId)
            ->where('aktif', true)
            ->with('kebiasaan')
            ->orderBy('nama_peserta_didik')
            ->get();

        $kkm = $pengaturan->kkm ?? 70;

        $kelasStats = $siswaList->groupBy('kelas')->map(function ($siswa, $kelas) use ($kkm) {
            $rataRata = $siswa->avg(fn ($s) => $s->rata_rata ?? 0);
            $tuntas = $siswa->filter(fn ($s) => ($s->rata_rata ?? 0) >= $kkm)->count();
            $belum = $siswa->count() - $tuntas;
            $ketuntasan = $siswa->count() > 0 ? round(($tuntas / $siswa->count()) * 100, 1) : 0;
            return [
                'kelas' => $kelas,
                'jumlah' => $siswa->count(),
                'laki' => $siswa->where('jenis_kelamin', 'L')->count(),
                'perempuan' => $siswa->where('jenis_kelamin', 'P')->count(),
                'ada_ortu' => $siswa->filter(fn ($s) => $s->nama_orang_tua && $s->nama_orang_tua !== 'ORT' . substr($s->nis, -4))->count(),
                'rata_rata' => $rataRata,
                'ketuntasan_persen' => $ketuntasan,
                'tuntas' => $tuntas,
                'belum' => $belum,
            ];
        });

        $kelas = (object) [
            'rata_rata' => $siswaList->count() > 0 ? round($siswaList->avg(fn ($s) => $s->rata_rata ?? 0), 1) : 0,
            'ketuntasan_persen' => $siswaList->count() > 0 ? round(($siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) >= $kkm)->count() / $siswaList->count()) * 100, 1) : 0,
            'tuntas' => $siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) >= $kkm)->count(),
            'belum' => $siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) < $kkm)->count(),
            'kehadiran_persen' => 0,
        ];

        $siswaPerluPendampingan = $siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) > 0 && ($s->rata_rata ?? 0) < $kkm)
            ->map(fn ($s) => (object) [
                'nama' => $s->nama_peserta_didik,
                'nis' => $s->nis,
                'nilai' => $s->rata_rata ?? 0,
                'keterangan' => 'Di bawah KKM',
            ])->values();

        $siswaBerprestasi = $siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) >= 90)
            ->map(fn ($s) => (object) [
                'nama' => $s->nama_peserta_didik,
                'nis' => $s->nis,
                'nilai' => $s->rata_rata ?? 0,
                'predikat' => ($s->rata_rata ?? 0) >= 95 ? 'A+' : 'A',
            ])->values();

        $kkm = $pengaturan->kkm ?? 70;
        $chartLabels = ['PTS 1', 'UH 1', 'Tugas 1', 'PTS 2', 'UH 2', 'PAS'];
        $chartData = array_fill(0, 6, $kelas->rata_rata);
        $chartKKM = array_fill(0, 6, $kkm);
        $predikatA = $siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) >= 90)->count();
        $predikatB = $siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) >= 80 && ($s->rata_rata ?? 0) < 90)->count();
        $predikatC = $siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) >= $kkm && ($s->rata_rata ?? 0) < 80)->count();
        $predikatD = $siswaList->filter(fn ($s) => ($s->rata_rata ?? 0) > 0 && ($s->rata_rata ?? 0) < $kkm)->count();
        $chartPredikatData = [$predikatA, $predikatB, $predikatC, $predikatD];

        return view('kepsek.peta-kelas.detail', compact(
            'guru', 'pengaturan', 'siswaList', 'kelasStats', 'kelas',
            'siswaPerluPendampingan', 'siswaBerprestasi',
            'chartLabels', 'chartData', 'chartKKM', 'chartPredikatData'
        ));
    }
}
