<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\Kebiasaan;
use App\Models\User;
use App\Models\Siswa;

class KebiasaanController extends Controller
{
    public function index()
    {
        $siswaList = Siswa::where('aktif', true)
            ->with('guru')
            ->orderBy('nama_peserta_didik')
            ->get();

        $kebiasaan = Kebiasaan::whereIn('siswa_id', $siswaList->pluck('id'))
            ->with(['siswa', 'siswa.guru'])
            ->latest('tanggal')
            ->get()
            ->groupBy('siswa_id');

        $rekapKebiasaan = $siswaList->map(function ($siswa) use ($kebiasaan) {
            $dataKebiasaan = $kebiasaan->get($siswa->id, collect());
            $rataRata = [
                'bangun_pagi' => $dataKebiasaan->avg('bangun_pagi'),
                'beribadah' => $dataKebiasaan->avg('beribadah'),
                'berolahraga' => $dataKebiasaan->avg('berolahraga'),
                'makan_sehat' => $dataKebiasaan->avg('makan_sehat'),
                'gemar_belajar' => $dataKebiasaan->avg('gemar_belajar'),
                'bermasyarakat' => $dataKebiasaan->avg('bermasyarakat'),
                'tidur_cepat' => $dataKebiasaan->avg('tidur_cepat'),
            ];
            $rataRata['overall'] = round(collect($rataRata)->avg(), 2);

            return [
                'siswa' => $siswa,
                'jumlah_input' => $dataKebiasaan->count(),
                'rata_rata' => $rataRata,
                'catatan_ortu' => $dataKebiasaan->pluck('catatan_orang_tua')->filter()->values(),
            ];
        });

        $ortuAktif = User::where('peran', 'ortu')->where('aktif', true)->count();

        $totalSiswa = $siswaList->count();
        $partisipasiRataRata = $rekapKebiasaan->count()
            ? round($rekapKebiasaan->avg(fn($r) => $r['jumlah_input']), 1)
            : 0;

        $fieldTotals = collect([
            'bangun_pagi' => 0, 'beribadah' => 0, 'berolahraga' => 0,
            'makan_sehat' => 0, 'gemar_belajar' => 0, 'bermasyarakat' => 0, 'tidur_cepat' => 0,
        ]);
        foreach ($rekapKebiasaan as $rk) {
            foreach ($fieldTotals as $field => $_) {
                $fieldTotals[$field] += $rk['rata_rata'][$field] ?? 0;
            }
        }
        $fieldTotals = $fieldTotals->map(fn($v) => $totalSiswa > 0 ? round($v / $totalSiswa, 2) : 0);
        $kebiasaanTerbaik = $fieldTotals->keys()->first() ?? '—';
        $daftarKebiasaan = [
            'bangun_pagi' => 'Bangun Pagi', 'beribadah' => 'Beribadah', 'berolahraga' => 'Berolahraga',
            'makan_sehat' => 'Makan Sehat', 'gemar_belajar' => 'Gemar Belajar', 'bermasyarakat' => 'Bermasyarakat', 'tidur_cepat' => 'Tidur Cepat',
        ];

        $chartKebiasaanLabels = array_values($daftarKebiasaan);
        $chartKebiasaanData = $fieldTotals->values()->toArray();

        $kelasGroups = $siswaList->groupBy('kelas');
        $chartKelasLabels = $kelasGroups->keys()->take(3)->toArray();
        $chartRadarKelas = [];
        foreach ($chartKelasLabels as $kelasName) {
            $kelasSiswa = $kelasGroups->get($kelasName, collect());
            $kelasIds = $kelasSiswa->pluck('id');
            $kelasKebiasaan = $kebiasaan->filter(fn ($items, $key) => $kelasIds->contains($key))->flatten(1);
            $radar = [];
            foreach (array_keys($daftarKebiasaan) as $field) {
                $radar[] = round($kelasKebiasaan->avg($field) ?? 0, 1);
            }
            $chartRadarKelas[] = $radar;
        }
        $chartRadarKelas1 = $chartRadarKelas[0] ?? array_fill(0, 7, 0);
        $chartRadarKelas2 = $chartRadarKelas[1] ?? array_fill(0, 7, 0);
        $chartRadarKelas3 = $chartRadarKelas[2] ?? array_fill(0, 7, 0);

        $tabelKebiasaan = $kelasGroups->map(function ($siswa, $kelas) use ($kebiasaan, $daftarKebiasaan) {
            $siswaIds = $siswa->pluck('id');
            $kelasKebiasaan = $kebiasaan->filter(fn ($items, $key) => $siswaIds->contains($key))->flatten(1);
            $kebiasaanValues = [];
            foreach (array_keys($daftarKebiasaan) as $field) {
                $kebiasaanValues[$field] = round($kelasKebiasaan->avg($field) ?? 0, 1);
            }
            $overall = round(collect($kebiasaanValues)->avg(), 1);
            return (object) [
                'kelas' => $kelas,
                'nama_kelas' => $kelas,
                'jumlah_siswa' => $siswa->count(),
                'kebiasaan' => $kebiasaanValues,
                'rata_rata_kebiasaan' => $overall,
            ];
        })->values();

        $detailKebiasaan = collect($daftarKebiasaan)->map(function ($label, $key) use ($fieldTotals, $totalSiswa) {
            $partisipasi = $fieldTotals[$key] ?? 0;
            return (object) [
                'nama' => $label,
                'ikon' => match($key) {
                    'bangun_pagi' => '🌅', 'beribadah' => '🙏', 'berolahraga' => '🏃',
                    'makan_sehat' => '🥗', 'gemar_belajar' => '📚', 'bermasyarakat' => '🤝',
                    'tidur_cepat' => '🌙', default => '⭐',
                },
                'kategori' => 'Kebiasaan',
                'total_siswa' => $totalSiswa,
                'partisipasi' => round($partisipasi * 100, 1),
            ];
        })->values();

        return view('kepsek.kebiasaan.index', compact(
            'rekapKebiasaan', 'ortuAktif', 'totalSiswa', 'partisipasiRataRata',
            'kebiasaanTerbaik', 'daftarKebiasaan', 'fieldTotals',
            'tabelKebiasaan', 'detailKebiasaan',
            'chartKebiasaanLabels', 'chartKebiasaanData',
            'chartKelasLabels', 'chartRadarKelas1', 'chartRadarKelas2', 'chartRadarKelas3'
        ));
    }
}
