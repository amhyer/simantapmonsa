<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Catatan;
use App\Models\Dimensi;
use App\Models\Ekstrakurikuler;
use App\Models\HasilKuis;
use App\Models\JadwalPelajaran;
use App\Models\Kehadiran;
use App\Models\KenaikanKelas;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\PengaturanGuru;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Services\Exam\CbtDashboardProvider;
use App\Services\NilaiService;
use Illuminate\Support\Facades\Route;

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

        // Hoist: 1 lookup KKM (cached) untuk semua baris.
        $kkm = getKKM($guruId);

        foreach ($siswa as $s) {
            $na = $this->nilaiService->hitungNilaiAkhirBatch($allNilai->get($s->id, collect()), $allHasilKuis->get($s->id, collect()));
            if ($na['nilai_akhir'] > 0) {
                $totalNilai += $na['nilai_akhir'];
                $countNilai++;
                if ($na['nilai_akhir'] > $tertinggi) {
                    $tertinggi = $na['nilai_akhir'];
                }
                if ($na['nilai_akhir'] >= $kkm) {
                    $tuntas++;
                }
                if ($na['nilai_akhir'] < $kkm) {
                    $perluPendampingan[] = ['siswa' => $s, 'nilai' => $na['nilai_akhir']];
                }
                if ($na['nilai_akhir'] >= 85) {
                    $menonjol[] = [
                        'siswa' => $s,
                        'nilai' => $na['nilai_akhir'],
                        'predikat' => $this->nilaiService->getPredikat($na['nilai_akhir'], $kkm),
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
            'belum' => $countNilai - $tuntas,
            'belum_dinilai' => $jumlah - $countNilai,
            'kehadiran' => $rataKehadiran,
        ];

        $materiTerbaru = Materi::where('guru_id', $guruId)->latest('tanggal')->limit(5)->get();
        $kuisAktif = Kuis::where('guru_id', $guruId)->where('aktif', true)->withCount('hasilKuis')->latest('tanggal')->limit(5)->get();

        // Chart data: real predikat distribution
        $predikat = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        foreach ($allNilai as $siswaId => $nilaiList) {
            $na = $this->nilaiService->hitungNilaiAkhirBatch($nilaiList, $allHasilKuis->get($siswaId, collect()));
            if ($na['nilai_akhir'] > 0) {
                $huruf = $this->nilaiService->getPredikat($na['nilai_akhir'], $kkm)['huruf'] ?? 'D';
                if (isset($predikat[$huruf])) {
                    $predikat[$huruf]++;
                }
            }
        }

        // Chart data: trend by jenis (1 query agregat, bukan 1 per jenis).
        $jenisList = ['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS'];
        $trendLabels = $jenisList;
        $rataPerJenis = Nilai::whereIn('siswa_id', $siswaIds)
            ->selectRaw('jenis, avg(nilai) as rata')
            ->groupBy('jenis')
            ->pluck('rata', 'jenis');
        $trendData = [];
        foreach ($jenisList as $jenis) {
            $trendData[] = isset($rataPerJenis[$jenis]) ? round((float) $rataPerJenis[$jenis], 1) : 0;
        }

        // Banner sumber data siswa (dipindah dari Blade agar view bebas query).
        $totalGuruSiswa = Siswa::where('guru_id', $guruId)->count();
        $syncedSiswa = Siswa::where('guru_id', $guruId)->whereNotNull('dapodik_id')->count();

        // P3: agenda guru — KKM, jadwal hari ini, deadline rapor, progres e-Rapor.
        $pengaturan = PengaturanGuru::where('guru_id', $guruId)->first();
        $kkmDiatur = (bool) ($pengaturan && $pengaturan->kkm);
        $namaHari = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ][now()->format('l')];
        $jadwalHariIni = JadwalPelajaran::where('guru_id', $guruId)
            ->where('hari', $namaHari)
            ->orderBy('jam_mulai')
            ->get();
        $deadlineRapor = TanggalRapor::where('tanggal', '>=', now()->toDateString())
            ->orderBy('tanggal')
            ->first();
        $siswaErapot = NilaiErapot::where('guru_id', $guruId)->distinct()->count('siswa_id');

        // P4: esei menunggu koreksi — submisi 30 hari terakhir pada kuis
        // yang punya ≥1 soal tanpa pilihan (skor otomatisnya tak andal).
        $kuisEseiIds = [];
        foreach (Kuis::where('guru_id', $guruId)->get(['id', 'soal']) as $k) {
            foreach ((array) ($k->soal ?? []) as $item) {
                if (empty($item['pilihan'])) {
                    $kuisEseiIds[] = $k->id;
                    break;
                }
            }
        }
        $eseiMenunggu = $kuisEseiIds === []
            ? 0
            : HasilKuis::whereIn('kuis_id', $kuisEseiIds)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

        // P4: status Google Sheets (dari pengaturan guru, tanpa request ke Google).
        $sistem = $pengaturan->sistem ?? [];
        $riwayatSinkron = $sistem['riwayat_sinkron'] ?? [];
        $sheetStatus = [
            'terhubung' => ! empty($sistem['google_sheet_url']),
            'nama' => $sistem['spreadsheet_name'] ?? null,
            'url' => $sistem['google_sheet_url'] ?? null,
            'terakhir' => $riwayatSinkron[0] ?? null,
        ];

        // Modul CBT: ringkasan disiapkan provider (engine `exam_*` atau `cbt_*`).
        $cbtRingkasan = config('simantap.cbt_enabled', false) && Route::has('cbt.sessions.index')
            ? app(CbtDashboardProvider::class)->ringkasanGuru((int) auth()->id())
            : null;

        // Modul TKA Kesiapan Belajar Kelas:
        $dimensiScores = Dimensi::where('guru_id', $guruId)->pluck('skor');
        $tkaCount = $dimensiScores->count();
        $tkaRata = $tkaCount > 0 ? round($dimensiScores->avg(), 2) : 0;
        $tkaSiap = $dimensiScores->filter(fn ($s) => $s >= 2.5)->count();
        $tkaPerlu = $dimensiScores->filter(fn ($s) => $s < 2.5)->count();
        $tkaRingkasan = [
            'total' => $tkaCount,
            'rata_rata' => $tkaRata,
            'siap' => $tkaSiap,
            'perlu_bimbingan' => $tkaPerlu,
        ];

        // Beban Mengajar & Status Kerja Guru/Wali Kelas (ala e-Rapor SD 2025.1)
        $rombelDiampu = Rombel::where('guru_id', $guruId)->count();
        if ($rombelDiampu === 0) {
            $rombelDiampu = $siswa->pluck('kelas')->filter()->unique()->count();
        }
        $jadwalSemua = JadwalPelajaran::where('guru_id', $guruId)->get(['mata_pelajaran', 'kelas']);
        $mapelDiampu = $jadwalSemua->pluck('mata_pelajaran')->filter()->unique()->count();
        if ($mapelDiampu === 0 && ! empty($pengaturan?->mata_pelajaran)) {
            $mapelDiampu = 1;
        }
        $ekskulDiampu = Ekstrakurikuler::where('pembina_id', $guruId)->count();
        $bebanMengajar = [
            'rombel' => $rombelDiampu,
            'siswa' => $jumlah,
            'mapel' => $mapelDiampu,
            'pembelajaran' => $jadwalSemua->count(),
            'ekskul' => $ekskulDiampu,
        ];

        $tpItems = is_array(($pengaturan?->pengaturan ?? [])['tujuan_pembelajaran'] ?? null)
            ? ($pengaturan->pengaturan['tujuan_pembelajaran'])
            : [];
        $persenTp = (count($tpItems) > 0 || $materiTerbaru->isNotEmpty()) ? 100.0 : 0.0;
        $persenNilaiMapel = $jumlah > 0 ? round(min(100, max($siswaErapot, $countNilai) / $jumlah * 100), 2) : 0.0;
        $siswaLengkap = $siswa->filter(fn ($s) => ! empty($s->nisn) && ! empty($s->jenis_kelamin))->count();
        $persenKelengkapan = $jumlah > 0 ? round($siswaLengkap / $jumlah * 100, 2) : 0.0;
        $siswaEkskul = $siswa->filter(fn ($s) => ! empty(($s->rekaman ?? [])['ekskul']))->count();
        $persenEkskul = $jumlah > 0 ? round($siswaEkskul / $jumlah * 100, 2) : 0.0;
        $persenKehadiranInput = $jumlah > 0 ? round($countHadir / $jumlah * 100, 2) : 0.0;
        $siswaCatatan = $siswaIds->isEmpty() ? 0 : Catatan::whereIn('siswa_id', $siswaIds)->distinct()->count('siswa_id');
        $persenCatatan = $jumlah > 0 ? round($siswaCatatan / $jumlah * 100, 2) : 0.0;
        $siswaNaikKelas = $siswaIds->isEmpty() ? 0 : KenaikanKelas::whereIn('siswa_id', $siswaIds)->distinct()->count('siswa_id');
        $persenNaikKelas = $jumlah > 0 ? round($siswaNaikKelas / $jumlah * 100, 2) : 0.0;

        $statusKerjaGuru = [
            ['label' => 'Input Tujuan Pembelajaran', 'persen' => $persenTp, 'route' => 'guru.tp.index'],
            ['label' => 'Input Nilai Mata Pelajaran', 'persen' => $persenNilaiMapel, 'route' => 'guru.nilai-erapor.index'],
            ['label' => 'Update Data Kelengkapan Siswa [Admin / Wali Kelas]', 'persen' => $persenKelengkapan, 'route' => 'guru.siswa.index'],
            ['label' => 'Input Data Nilai Ekstrakurikuler [Wali Kelas]', 'persen' => $persenEkskul, 'route' => 'guru.walas.ekskul'],
            ['label' => 'Input Data Rekap Kehadiran Siswa', 'persen' => $persenKehadiranInput, 'route' => 'guru.kehadiran.index'],
            ['label' => 'Input Data Catatan Wali Kelas', 'persen' => $persenCatatan, 'route' => 'guru.catatan.index'],
            ['label' => 'Input Data Kenaikan Kelas', 'persen' => $persenNaikKelas, 'route' => 'guru.walas.naik-kelas'],
        ];
        $progresKerjaGuru = round(collect($statusKerjaGuru)->avg('persen'), 2);

        return view('guru.dashboard', compact(
            'ringkasan', 'perluPendampingan', 'menonjol', 'materiTerbaru', 'kuisAktif',
            'predikat', 'trendLabels', 'trendData', 'totalGuruSiswa', 'syncedSiswa',
            'kkmDiatur', 'namaHari', 'jadwalHariIni', 'deadlineRapor', 'siswaErapot',
            'eseiMenunggu', 'sheetStatus', 'cbtRingkasan', 'tkaRingkasan',
            'bebanMengajar', 'statusKerjaGuru', 'progresKerjaGuru'
        ));
    }
}
