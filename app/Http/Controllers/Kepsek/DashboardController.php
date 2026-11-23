<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Models\Dimensi;
use App\Models\Kebiasaan;
use App\Models\Kehadiran;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\NilaiErapot;
use App\Models\PresensiPegawai;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Exam\CbtDashboardProvider;
use App\Services\NilaiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_siswa' => Siswa::where('aktif', true)->count(),
            'total_guru' => User::forSchool()->where('peran', 'guru')->where('aktif', true)->count(),
            'total_kelas' => Siswa::where('aktif', true)->distinct('kelas')->count('kelas'),
            'total_orang_tua' => User::forSchool()->where('peran', 'ortu')->where('aktif', true)->count(),
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
                'label' => Carbon::parse($tgl)->translatedFormat('D'),
                'nilai_input' => $dailyNilai->get($tgl, 0),
                'materi' => $dailyMateri->get($tgl, 0),
                'kuis' => $dailyKuis->get($tgl, 0),
            ];
        }

        $guruUsers = User::forSchool()->where('peran', 'guru')->where('aktif', true)->get();
        $guruIds = $guruUsers->pluck('id');

        // Agregasi di SQL (dulu get() seluruh baris lalu count di PHP).
        $nilaiPerGuru = NilaiErapot::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $materiPerGuru = Materi::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $kuisPerGuru = Kuis::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $siswaPerGuru = Siswa::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');

        $topGuru = $guruUsers->map(function ($guru) use ($nilaiPerGuru, $materiPerGuru, $kuisPerGuru, $siswaPerGuru) {
            $totalNilai = $nilaiPerGuru->get($guru->id, 0);
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

        // Satu query groupBy alih-alih 4 query count terpisah.
        $predikatCounts = NilaiErapot::whereIn('predikat', ['A', 'B', 'C', 'D'])
            ->selectRaw('predikat, count(*) as jumlah')
            ->groupBy('predikat')
            ->get()
            ->pluck('jumlah', 'predikat');
        $distribusiPredikat = [
            'A' => $predikatCounts->get('A', 0),
            'B' => $predikatCounts->get('B', 0),
            'C' => $predikatCounts->get('C', 0),
            'D' => $predikatCounts->get('D', 0),
        ];

        $perKelasData = Siswa::where('aktif', true)
            ->select('kelas', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('kelas')
            ->orderBy('kelas')
            ->get();

        // Agregasi per kelas di SQL — dulu seluruh baris siswa/nilai/kehadiran
        // dimuat ke memori lalu dihitung di PHP (OOM seiring data membesar).
        $rataNilaiPerKelas = NilaiErapot::whereIn('siswa_id', $siswaIds)
            ->selectRaw('kelas, avg(nilai_akhir) as rata')
            ->groupBy('kelas')
            ->get()
            ->pluck('rata', 'kelas');

        $kehadiranPerKelas = Kehadiran::query()
            ->join('siswa', 'siswa.id', '=', 'kehadiran.siswa_id')
            ->whereIn('kehadiran.siswa_id', $siswaIds)
            ->selectRaw('siswa.kelas as kelas, count(*) as total, sum(case when kehadiran.status = \'H\' then 1 else 0 end) as hadir')
            ->groupBy('siswa.kelas')
            ->get()
            ->keyBy('kelas');

        $laporOrtuPerKelas = Kebiasaan::query()
            ->join('siswa', 'siswa.id', '=', 'kebiasaan.siswa_id')
            ->whereIn('kebiasaan.siswa_id', $siswaIds)
            ->selectRaw('siswa.kelas as kelas, count(distinct kebiasaan.siswa_id) as jumlah')
            ->groupBy('siswa.kelas')
            ->get()
            ->pluck('jumlah', 'kelas');

        $perKelas = $perKelasData->map(function ($item) use ($rataNilaiPerKelas, $kehadiranPerKelas, $laporOrtuPerKelas) {
            $rataNilai = round((float) ($rataNilaiPerKelas->get($item->kelas) ?? 0), 1);
            $hadirRow = $kehadiranPerKelas->get($item->kelas);
            $totalKehadiran = (int) ($hadirRow->total ?? 0);
            $hadirCount = (int) ($hadirRow->hadir ?? 0);
            $persenHadir = $totalKehadiran > 0 ? round(($hadirCount / $totalKehadiran) * 100, 1) : 0;
            $laporOrtu = (int) ($laporOrtuPerKelas->get($item->kelas) ?? 0);

            return [
                'kelas' => $item->kelas,
                'jumlah' => $item->jumlah,
                'rata_nilai' => $rataNilai,
                'persen_hadir' => $persenHadir,
                'partisipasi_ortu' => $item->jumlah > 0 ? round(($laporOrtu / $item->jumlah) * 100, 1) : 0,
            ];
        });

        // Modul CBT: analisis sekolah dari provider (engine `exam_*` atau `cbt_*`).
        $cbtRingkasan = config('simantap.cbt_enabled', false)
            ? app(CbtDashboardProvider::class)->ringkasanSekolah()
            : null;

        // Agregat di SQL — dulu pluck() seluruh skor lalu dihitung di PHP.
        $tkaTotal = Dimensi::count();
        $tkaRata = $tkaTotal > 0 ? round((float) (Dimensi::avg('skor') ?? 0), 2) : 0;
        // Kolom `dimensi.skor` bertipe INTEGER (1..4). Ambang 2.5 yang dikirim
        // sebagai parameter string membuat PostgreSQL menolak query
        // (SQLSTATE 22P02: invalid input syntax for type integer: "2.5").
        // Untuk nilai integer, `skor >= 2.5` identik dengan `skor >= 3`.
        $tkaSiap = Dimensi::where('skor', '>=', 3)->count();
        $tkaSekolah = [
            'total' => $tkaTotal,
            'rata_rata' => $tkaRata,
            'siap' => $tkaSiap,
            'persen_siap' => $tkaTotal > 0 ? round(($tkaSiap / $tkaTotal) * 100) : 0,
        ];

        $todayStr = today()->toDateString();
        $kehadiranSiswaHariIni = Kehadiran::whereDate('tanggal', $todayStr)->where('status', 'H')->count();
        $presensiPegawaiHariIni = PresensiPegawai::whereDate('tanggal', $todayStr)->whereNotNull('waktu_masuk')->count();
        $presensiHariIni = [
            'siswa_hadir' => $kehadiranSiswaHariIni,
            'pegawai_hadir' => $presensiPegawaiHariIni,
            'total_siswa' => $stats['total_siswa'],
            'total_guru' => $stats['total_guru'],
            'persen_siswa' => $stats['total_siswa'] > 0 ? round(($kehadiranSiswaHariIni / $stats['total_siswa']) * 100, 1) : 0,
        ];

        return view('kepsek.dashboard', compact(
            'stats', 'rataNilai', 'predikatSekolah', 'persenHadir',
            'partisipasiOrtu', 'aktivitasGuru', 'topGuru',
            'distribusiPredikat', 'perKelas', 'cbtRingkasan', 'tkaSekolah', 'presensiHariIni'
        ));
    }

    public function pantau(Request $request)
    {
        $guru = User::forSchool()->where('peran', 'guru')->where('aktif', true)->orderBy('nama_lengkap')->get();
        $filterGuru = $request->get('guru_id');
        $filterTanggal = $request->get('tanggal');

        $query = Aktivitas::with('guru');
        if ($filterGuru) {
            $query->where('guru_id', $filterGuru);
        }
        if ($filterTanggal) {
            $query->whereDate('created_at', $filterTanggal);
        }
        $aktivitas = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        $guruUntukStatistik = $filterGuru
            ? $guru->where('id', (int) $filterGuru)->values()
            : $guru;

        $guruIds = $guruUntukStatistik->pluck('id');
        $aktivitasQuery = Aktivitas::whereIn('guru_id', $guruIds);
        if ($filterTanggal) {
            $aktivitasQuery->whereDate('created_at', $filterTanggal);
        }
        $aktivitasPerGuru = $aktivitasQuery->get()->groupBy('guru_id');
        $nilaiPerGuru = NilaiErapot::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $materiPerGuru = Materi::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $kuisPerGuru = Kuis::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');
        $siswaPerGuru = Siswa::whereIn('guru_id', $guruIds)->selectRaw('guru_id, count(*) as jumlah')->groupBy('guru_id')->get()->pluck('jumlah', 'guru_id');

        $statistikGuru = $guruUntukStatistik->map(function ($g) use ($aktivitasPerGuru, $nilaiPerGuru, $materiPerGuru, $kuisPerGuru, $siswaPerGuru) {
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

    /**
     * Label + kelas CSS stat-card untuk sebuah nilai.
     * Ambang diambil dari App\Services\NilaiService (satu sumber kebenaran
     * predikat); hanya pemetaan kelas CSS yang khas dashboard kepsek.
     */
    protected function getPredikat($nilai, $kkm = null)
    {
        $predikat = app(NilaiService::class)
            ->getPredikat((float) $nilai, $kkm ?? getKKM());

        $kelas = match ($predikat['huruf']) {
            'A' => 'ok',
            'B' => 'gold',
            'C' => 'primary',
            default => 'bad',
        };

        return ['label' => $predikat['label'], 'kelas' => $kelas];
    }
}
