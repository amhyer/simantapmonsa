<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\Nilai;
use App\Models\OrangTuaSiswa;
use App\Models\PengaturanGuru;
use App\Models\Siswa;
use App\Models\User;

class PetaKelasController extends Controller
{
    public function index()
    {
        $guruList = User::forSchool()->where('peran', 'guru')
            ->where('aktif', true)
            ->with(['pengaturanGuru', 'siswa' => function ($q) {
                $q->where('aktif', true);
            }])
            ->orderBy('nama_lengkap')
            ->get();

        $allSiswaIds = $guruList->flatMap(fn ($g) => $g->siswa->where('aktif', true)->pluck('id'))->unique()->values();
        $siswaBerortu = $allSiswaIds->isNotEmpty()
            ? OrangTuaSiswa::whereIn('siswa_id', $allSiswaIds)->distinct()->pluck('siswa_id')->flip()
            : collect();

        $peta = $guruList->map(function ($guru) use ($siswaBerortu) {
            $siswaAktif = $guru->siswa->where('aktif', true);
            $kelasList = $siswaAktif->pluck('kelas')->unique()->values();
            $akunOrtu = $siswaAktif->filter(fn ($s) => $siswaBerortu->has($s->id))->count();

            return [
                'guru' => $guru,
                'kelas' => $kelasList->implode(', '),
                'jumlah_siswa' => $siswaAktif->count(),
                'akun_ortu' => $akunOrtu,
                'mapel' => $guru->pengaturanGuru->mata_pelajaran ?? '-',
                'kkm' => $guru->pengaturanGuru->kkm ?? getKKM($guru->id),
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
        $guru = User::forSchool()->where('peran', 'guru')->where('id', $guruId)->firstOrFail();
        $pengaturan = PengaturanGuru::forSchool()->where('guru_id', $guru->id)->first();
        $siswaList = Siswa::where('guru_id', $guru->id)
            ->where('aktif', true)
            ->with('kebiasaan')
            ->orderBy('nama_peserta_didik')
            ->get();

        $kkm = (int) ($pengaturan->kkm ?? getKKM($guru->id));

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
                'ada_ortu' => $siswa->filter(fn ($s) => $s->nama_orang_tua && $s->nama_orang_tua !== 'ORT'.substr($s->nis, -4))->count(),
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

        $nilaiRows = Nilai::query()
            ->where('guru_id', $guru->id)
            ->whereNotNull('nilai')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get(['judul_penilaian', 'jenis', 'tanggal', 'nilai']);

        $perkembangan = $nilaiRows
            ->groupBy(function (Nilai $row): string {
                $label = trim((string) ($row->judul_penilaian ?: $row->jenis ?: ''));

                return $label !== '' ? $label : ($row->tanggal ? $row->tanggal->format('d M') : 'Penilaian');
            })
            ->map(fn ($group) => round((float) $group->avg('nilai'), 1))
            ->slice(-6);

        $chartLabels = $perkembangan->keys()->values()->all();
        $chartData = $perkembangan->values()->all();
        $chartKKM = $chartLabels !== [] ? array_fill(0, count($chartLabels), $kkm) : [];
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
