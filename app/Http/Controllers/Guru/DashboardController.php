<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Materi;
use App\Models\Kuis;
use App\Models\Nilai;
use App\Models\Kehadiran;
use App\Models\HasilKuis;
use App\Services\NilaiService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $nilaiService;

    public function __construct(NilaiService $nilaiService)
    {
        $this->nilaiService = $nilaiService;
    }

    public function index()
    {
        $guruId = auth()->id();

        $siswa = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();
        $siswaIds = $siswa->pluck('id');

        $allNilai = Nilai::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
        $allKehadiran = Kehadiran::whereIn('siswa_id', $siswaIds)
            ->selectRaw('siswa_id, status, count(*) as jumlah')
            ->groupBy('siswa_id', 'status')
            ->get()
            ->groupBy('siswa_id');
        $allHasilKuis = HasilKuis::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');

        $jumlah = $siswa->count();
        $totalNilai = 0;
        $countNilai = 0;
        $tertinggi = 0;
        $tuntas = 0;
        $totalPersenHadir = 0;
        $countHadir = 0;
        $perluPendampingan = [];
        $menonjol = [];

        foreach ($siswa as $s) {
            $na = $this->nilaiService->hitungNilaiAkhirBatch($allNilai->get($s->id, collect()), $allHasilKuis->get($s->id, collect()));
            if ($na['nilai_akhir'] > 0) {
                $totalNilai += $na['nilai_akhir'];
                $countNilai++;
                if ($na['nilai_akhir'] > $tertinggi) $tertinggi = $na['nilai_akhir'];
                if ($na['nilai_akhir'] >= getKKM($guruId)) $tuntas++;
                if ($na['nilai_akhir'] < getKKM($guruId)) {
                    $perluPendampingan[] = ['siswa' => $s, 'nilai' => $na['nilai_akhir']];
                }
                if ($na['nilai_akhir'] >= 85) {
                    $menonjol[] = [
                        'siswa' => $s,
                        'nilai' => $na['nilai_akhir'],
                        'predikat' => $this->nilaiService->getPredikat($na['nilai_akhir'], getKKM($s->id)),
                    ];
                }
            }

            $kehadiranSiswa = $allKehadiran->get($s->id, collect());
            if ($kehadiranSiswa->count() > 0) {
                $totalKehadiran = $kehadiranSiswa->sum('jumlah');
                $hadirCount = $kehadiranSiswa->where('status', 'H')->sum('jumlah');
                $totalPersenHadir += $totalKehadiran > 0 ? ($hadirCount / $totalKehadiran * 100) : 0;
                $countHadir++;
            }
        }

        $rataRata = $countNilai > 0 ? round($totalNilai / $countNilai, 1) : 0;
        $rataKehadiran = $countHadir > 0 ? round($totalPersenHadir / $countHadir, 1) : 0;

        $ringkasan = [
            'jumlah' => $jumlah,
            'rata_rata' => $rataRata,
            'tertinggi' => $tertinggi,
            'tuntas' => $tuntas,
            'belum' => $jumlah - $tuntas,
            'kehadiran' => $rataKehadiran,
        ];

        $materiTerbaru = Materi::where('guru_id', $guruId)->latest('tanggal')->limit(5)->get();
        $kuisAktif = Kuis::where('guru_id', $guruId)->where('aktif', true)->latest('tanggal')->limit(5)->get();

        // Chart data: real predikat distribution
        $predikat = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        foreach ($allNilai as $siswaId => $nilaiList) {
            $na = $this->nilaiService->hitungNilaiAkhirBatch($nilaiList, $allHasilKuis->get($siswaId, collect()));
            if ($na['nilai_akhir'] > 0) {
                $p = $this->nilaiService->getPredikat($na['nilai_akhir'], getKKM($guruId));
                $predikat[$p] = ($predikat[$p] ?? 0) + 1;
            }
        }

        // Chart data: trend by jenis
        $jenisList = ['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS'];
        $trendLabels = $jenisList;
        $trendData = [];
        foreach ($jenisList as $jenis) {
            $filtered = Nilai::whereIn('siswa_id', $siswaIds)->where('jenis', $jenis)->pluck('nilai');
            $trendData[] = $filtered->count() > 0 ? round($filtered->avg(), 1) : 0;
        }

        return view('guru.dashboard', compact(
            'ringkasan', 'perluPendampingan', 'menonjol', 'materiTerbaru', 'kuisAktif',
            'predikat', 'trendLabels', 'trendData'
        ));
    }
}
