<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Siswa;
use App\Models\NilaiErapot;
use App\Models\Kehadiran;
use App\Models\Kebiasaan;
use App\Models\RingkasanGuru;
use App\Models\Materi;
use App\Models\Kuis;
use App\Models\Aktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_siswa' => Siswa::where('aktif', true)->count(),
            'total_guru' => User::where('peran', 'guru')->where('aktif', true)->count(),
            'total_kelas' => Siswa::where('aktif', true)->distinct('kelas')->count('kelas'),
            'total_orang_tua' => User::where('peran', 'ortu')->where('aktif', true)->count(),
        ];

        $rataNilai = NilaiErapot::avg('nilai_akhir') ?? 0;
        $predikatSekolah = $this->getPredikat($rataNilai);

        $totalKehadiran = Kehadiran::count();
        $totalHadir = Kehadiran::where('status', 'H')->count();
        $persenHadir = $totalKehadiran > 0 ? round($totalHadir / $totalKehadiran * 100, 1) : 0;

        $siswaIds = Siswa::where('aktif', true)->pluck('id');
        $siswaLapor = Kebiasaan::whereIn('siswa_id', $siswaIds)->distinct('siswa_id')->count('siswa_id');
        $partisipasiOrtu = $stats['total_siswa'] > 0 ? round($siswaLapor / $stats['total_siswa'] * 100, 1) : 0;

        $startDate = today()->subDays(6)->startOfDay();
        $endDate = today()->endOfDay();

        $dailyNilai = NilaiErapot::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as tgl, count(*) as jumlah')
            ->groupBy('tgl')->get()->pluck('jumlah', 'tgl');
        $dailyMateri = Materi::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as tgl, count(*) as jumlah')
            ->groupBy('tgl')->get()->pluck('jumlah', 'tgl');
        $dailyKuis = Kuis::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as tgl, count(*) as jumlah')
            ->groupBy('tgl')->get()->pluck('jumlah', 'tgl');

        $aktivitasGuru = [];
        for ($i = 6; $i >= 0; $i--) {
            $tgl = today()->subDays($i)->format('Y-m-d');
            $aktivitasGuru[] = [
                'tanggal' => $tgl,
                'label' => \Carbon\Carbon::parse($tgl)->translatedFormat('D'),
                'nilai_input' => $dailyNilai->get($tgl, 0),
                'materi' => $dailyMateri->get($tgl, 0),
                'kuis' => $dailyKuis->get($tgl, 0),
            ];
        }

        $guruUsers = User::where('peran', 'guru')->where('aktif', true)->get();
        $guruIds = $guruUsers->pluck('id');

        $nilaiPerGuru = NilaiErapot::whereIn('guru_id', $guruIds)->get()->groupBy('guru_id');
        $materiPerGuru = Materi::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $kuisPerGuru = Kuis::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $siswaPerGuru = Siswa::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');

        $topGuru = $guruUsers->map(function ($guru) use ($nilaiPerGuru, $materiPerGuru, $kuisPerGuru, $siswaPerGuru) {
            $totalNilai = $nilaiPerGuru->get($guru->id, collect())->count();
            $totalMateri = $materiPerGuru->get($guru->id, 0);
            $totalKuis = $kuisPerGuru->get($guru->id, 0);
            $totalSiswa = $siswaPerGuru->get($guru->id, 0);
            return [
                'guru' => $guru,
                'total_aktivitas' => $totalNilai + $totalMateri + $totalKuis,
                'total_nilai' => $totalNilai,
                'total_materi' => $totalMateri,
                'total_kuis' => $totalKuis,
                'total_siswa' => $totalSiswa,
            ];
        })
            ->sortByDesc('total_aktivitas')
            ->take(5);

        $distribusiPredikat = [
            'A' => NilaiErapot::where('predikat', 'A')->count(),
            'B' => NilaiErapot::where('predikat', 'B')->count(),
            'C' => NilaiErapot::where('predikat', 'C')->count(),
            'D' => NilaiErapot::where('predikat', 'D')->count(),
        ];

        $perKelasData = Siswa::where('aktif', true)
            ->select('kelas', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('kelas')
            ->orderBy('kelas')
            ->get();

        $siswaSemua = Siswa::where('aktif', true)->get();
        $nilaiSemua = NilaiErapot::whereIn('siswa_id', $siswaIds)
            ->selectRaw('kelas, nilai_akhir')
            ->get()
            ->groupBy('kelas');
        $kehadiranSemua = Kehadiran::whereIn('siswa_id', $siswaIds)
            ->selectRaw('siswa_id, status, count(*) as jumlah')
            ->groupBy('siswa_id', 'status')
            ->get()
            ->groupBy('siswa_id');
        $kebiasaanSemua = Kebiasaan::whereIn('siswa_id', $siswaIds)
            ->selectRaw('DISTINCT siswa_id')
            ->pluck('siswa_id');

        $perKelas = $perKelasData->map(function ($item) use ($siswaSemua, $nilaiSemua, $kehadiranSemua, $kebiasaanSemua) {
            $kelasSiswaIds = $siswaSemua->where('kelas', $item->kelas)->pluck('id');
            $nilaiKelas = $nilaiSemua->get($item->kelas, collect());
            $rataNilai = $nilaiKelas->count() > 0 ? round($nilaiKelas->avg('nilai_akhir'), 1) : 0;

            $kehadiranKelas = $kehadiranSemua->filter(fn($h) => $kelasSiswaIds->contains($h->siswa_id));
            $totalKehadiran = $kehadiranKelas->sum('jumlah');
            $hadirCount = $kehadiranKelas->filter(fn($h) => $h->status === 'H')->sum('jumlah');
            $persenHadir = $totalKehadiran > 0 ? round($hadirCount / $totalKehadiran * 100, 1) : 0;

            $laporOrtu = $kebiasaanSemua->filter(fn($id) => $kelasSiswaIds->contains($id))->count();

            return [
                'kelas' => $item->kelas,
                'jumlah' => $item->jumlah,
                'rata_nilai' => $rataNilai,
                'persen_hadir' => $persenHadir,
                'partisipasi_ortu' => $item->jumlah > 0 ? round($laporOrtu / $item->jumlah * 100, 1) : 0,
            ];
        });

        return view('kepsek.dashboard', compact(
            'stats', 'rataNilai', 'predikatSekolah', 'persenHadir',
            'partisipasiOrtu', 'aktivitasGuru', 'topGuru',
            'distribusiPredikat', 'perKelas'
        ));
    }

    public function pantau(Request $request)
    {
        $guru = User::where('peran', 'guru')->where('aktif', true)->get();
        $filterGuru = $request->get('guru_id');
        $filterTanggal = $request->get('tanggal');

        $query = Aktivitas::query();
        if ($filterGuru) {
            $query->where('guru_id', $filterGuru);
        }
        if ($filterTanggal) {
            $query->whereDate('created_at', $filterTanggal);
        }
        $aktivitas = $query->orderBy('created_at', 'desc')->paginate(50);

        $guruIds = $guru->pluck('id');
        $aktivitasPerGuru = Aktivitas::whereIn('guru_id', $guruIds)->get()->groupBy('guru_id');
        $nilaiPerGuru = NilaiErapot::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $materiPerGuru = Materi::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $kuisPerGuru = Kuis::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $siswaPerGuru = Siswa::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');

        $statistikGuru = $guru->map(function ($g) use ($aktivitasPerGuru, $nilaiPerGuru, $materiPerGuru, $kuisPerGuru, $siswaPerGuru) {
            $aktivitasGuru = $aktivitasPerGuru->get($g->id, collect());
            return [
                'guru' => $g,
                'total_aktivitas' => $aktivitasGuru->count(),
                'terakhir_aktif' => $aktivitasGuru->sortByDesc('created_at')->first()?->created_at,
                'total_nilai' => $nilaiPerGuru->get($g->id, 0),
                'total_materi' => $materiPerGuru->get($g->id, 0),
                'total_kuis' => $kuisPerGuru->get($g->id, 0),
                'total_siswa' => $siswaPerGuru->get($g->id, 0),
            ];
        })->sortByDesc('total_aktivitas');

        return view('kepsek.pantau.index', compact('guru', 'aktivitas', 'statistikGuru', 'filterGuru', 'filterTanggal'));
    }

    protected function getPredikat($nilai, $kkm = null)
    {
        $kkm = $kkm ?? getKKM();
        if ($nilai >= 90) return ['label' => 'Sangat Baik', 'kelas' => 'ok'];
        if ($nilai >= 80) return ['label' => 'Baik', 'kelas' => 'gold'];
        if ($nilai >= $kkm) return ['label' => 'Cukup', 'kelas' => 'primary'];
        return ['label' => 'Perlu Bimbingan', 'kelas' => 'bad'];
    }
}
