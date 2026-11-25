<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\HasilKuis;
use App\Models\Kehadiran;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Exam\CbtDashboardProvider;
use App\Services\NilaiService;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    use HasSiswaLookup;

    protected $nilaiService;

    public function __construct(NilaiService $nilaiService, private CbtDashboardProvider $cbt)
    {
        $this->nilaiService = $nilaiService;
    }

    public function index()
    {
        $siswa = $this->getSiswa();

        if (! $siswa) {
            return view('siswa.dashboard', [
                'siswa' => null,
                'kehadiran' => 0,
                'totalHadir' => 0,
                'rataRata' => 0,
                'jumlahNilai' => 0,
                'materiTerbaru' => collect(),
                'kuisAktif' => collect(),
                'hasilKuis' => collect(),
                'nilaiPerMapel' => collect(),
                'kehadiranBulanan' => collect(),
                'saran' => null,
                'cbtUjian' => [],
                'cbtHasil' => [],
            ]);
        }

        $kehadiran = $this->nilaiService->hitungKehadiran($siswa->id);

        $totalHadir = Kehadiran::where('siswa_id', $siswa->id)
            ->where('status', 'H')
            ->count();

        $nilaiData = Nilai::where('siswa_id', $siswa->id)->get();
        $jumlahNilai = $nilaiData->count();
        $rataRata = $jumlahNilai > 0 ? $nilaiData->avg('nilai') : 0;

        $materiTerbaru = Materi::where('guru_id', $siswa->guru_id)
            ->latest('tanggal')
            ->limit(5)
            ->get();

        $kuisAktif = Kuis::where('guru_id', $siswa->guru_id)
            ->where('aktif', true)
            ->latest('tanggal')
            ->limit(5)
            ->get();

        $hasilKuis = HasilKuis::where('siswa_id', $siswa->id)->get()->keyBy('kuis_id');

        $nilaiPerMapel = $nilaiData->groupBy('mata_pelajaran')->map(function ($items, $name) {
            return [
                'nama' => $name,
                'rata_rata' => $items->avg('nilai'),
                'jumlah' => $items->count(),
            ];
        })->values();

        // Portabel lintas database (dulu to_char() khusus PostgreSQL):
        // ambil baris tahun berjalan lalu kelompokkan per bulan di PHP.
        // Bentuk output identik: nama (Inggris, cth. "January"), H, S, I, A.
        $kehadiranBulanan = Kehadiran::where('siswa_id', $siswa->id)
            ->whereYear('tanggal', date('Y'))
            ->orderBy('tanggal')
            ->get()
            ->groupBy(fn ($k) => $k->tanggal->format('Y-m'))
            ->map(function ($items) {
                return [
                    'nama' => $items->first()->tanggal->translatedFormat('F'),
                    'H' => $items->where('status', 'H')->count(),
                    'S' => $items->where('status', 'S')->count(),
                    'I' => $items->where('status', 'I')->count(),
                    'A' => $items->where('status', 'A')->count(),
                ];
            })
            ->values();

        // KKM guru siswa — satu-satunya acuan predikat (via NilaiService).
        $kkm = getKKM($siswa->guru_id);

        $saran = $this->generateSaran($siswa, $rataRata, $kehadiran, $nilaiPerMapel, $kkm);

        // Modul CBT: data disiapkan provider (engine `exam_*` atau tabel lama,
        // dipilih lewat simantap.exam_engine_readers).
        $cbtAktif = config('simantap.cbt_enabled', false) && Route::has('cbt.student.index');
        $cbtUjian = $cbtAktif ? $this->cbt->ujianTersedia($siswa) : [];
        $cbtHasil = $cbtAktif ? $this->cbt->hasilTerbaru($siswa->id) : [];

        // Data Akademik Siswa & Rekap Rapor Semester Ini (ala e-Rapor SD 2025.1)
        $rombelRow = $siswa->rombel_id
            ? Rombel::with('guru')->find($siswa->rombel_id)
            : ($siswa->kelas ? Rombel::with('guru')->where('nama_rombel', $siswa->kelas)->first() : null);

        $waliUser = $rombelRow?->guru ?? ($siswa->guru_id ? User::find($siswa->guru_id) : null);
        $namaWali = $waliUser?->nama_lengkap ?? $siswa->nama_guru ?? '-';
        $ptkWali = $waliUser
            ? Ptk::where(fn ($q) => $q->where('nama', $waliUser->nama_lengkap)->orWhere('nip', $waliUser->nama_pengguna))->first()
            : null;
        $nipWali = $ptkWali?->nip ?? ($waliUser && preg_match('/^\d{8,}$/', (string) $waliUser->nama_pengguna) ? $waliUser->nama_pengguna : '-');

        $defaultTingkat = \App\Support\SchoolLevel::tingkatForJenjang(\App\Support\SchoolLevel::current())[0] ?? 1;
        $tingkatAngka = \App\Support\SchoolLevel::parseTingkat($rombelRow?->tingkat, (string) ($siswa->kelas ?? '')) ?? $defaultTingkat;
        $faseKode = \App\Support\SchoolLevel::faseUntukKelas($tingkatAngka);
        $faseLabel = "Tingkat {$tingkatAngka} (Fase {$faseKode})";

        $infoAkademik = [
            'status_rombel' => $rombelRow?->jenis_rombel ?? 'Reguler',
            'tingkat_fase' => $faseLabel,
            'wali_kelas' => $namaWali,
            'nip_wali' => $nipWali,
        ];

        $eraporRows = NilaiErapot::where('siswa_id', $siswa->id)
            ->whereNotNull('nilai_akhir')
            ->get();
        $sumberRapor = $eraporRows->isNotEmpty()
            ? $eraporRows->pluck('nilai_akhir')->map(fn ($v) => (float) $v)
            : $nilaiPerMapel->pluck('rata_rata')->map(fn ($v) => (float) $v);

        $rekapRaporSemester = [
            'total_mapel' => $eraporRows->isNotEmpty()
                ? $eraporRows->pluck('mata_pelajaran')->unique()->count()
                : $nilaiPerMapel->count(),
            'rata_rata' => $sumberRapor->isNotEmpty() ? round($sumberRapor->avg(), 2) : 0,
            'tertinggi' => $sumberRapor->isNotEmpty() ? round($sumberRapor->max(), 2) : 0,
            'terendah' => $sumberRapor->isNotEmpty() ? round($sumberRapor->min(), 2) : 0,
        ];

        return view('siswa.dashboard', compact(
            'siswa', 'kehadiran', 'totalHadir', 'rataRata',
            'jumlahNilai', 'materiTerbaru', 'kuisAktif', 'hasilKuis',
            'nilaiPerMapel', 'kehadiranBulanan', 'saran', 'kkm',
            'cbtUjian', 'cbtHasil', 'infoAkademik', 'rekapRaporSemester'
        ));
    }

    private function generateSaran(Siswa $siswa, float $rataRata, float $kehadiran, $nilaiPerMapel, int $kkm): array
    {
        $items = [];
        $warna = '#7c3aed';

        // Ambang predikat selalu dari NilaiService (relatif KKM), bukan angka mati.
        $hurufRata = $rataRata > 0
            ? ($this->nilaiService->getPredikat($rataRata, $kkm)['huruf'] ?? 'D')
            : null;

        if ($hurufRata === 'D') {
            $items[] = [
                'icon' => 'fa-book-reader',
                'title' => 'Tingkatkan Belajar',
                'text' => 'Rata-rata nilai Anda masih di bawah KKM ('.$kkm.'). Rutin belajar setiap hari dan bertanya kepada guru jika ada yang kurang dipahami.',
                'color' => '#B42318',
            ];
            $warna = '#B42318';
        } elseif ($hurufRata === 'C') {
            $items[] = [
                'icon' => 'fa-arrow-up',
                'title' => 'Pertahankan & Tingkatkan',
                'text' => 'Nilai Anda sudah tuntas KKM ('.$kkm.'). Untuk mencapai predikat B atau A, fokus pada mata pelajaran yang masih di bawah rata-rata.',
                'color' => '#B8860B',
            ];
            $warna = '#B8860B';
        } elseif ($hurufRata === 'A' || $hurufRata === 'B') {
            $items[] = [
                'icon' => 'fa-star',
                'title' => 'Prestasi Bagus!',
                'text' => 'Rata-rata nilai Anda sangat baik. Pertahankan semangat belajar dan bantu teman yang membutuhkan.',
                'color' => '#7c3aed',
            ];
        }

        if ($kehadiran < 80 && $kehadiran > 0) {
            $items[] = [
                'icon' => 'fa-calendar-xmark',
                'title' => 'Kehadiran Kurang',
                'text' => 'Tingkat kehadiran Anda '.number_format($kehadiran, 1).'%. Usahakan hadir setiap hari agar tidak ketinggalan pelajaran.',
                'color' => '#B42318',
            ];
            $warna = '#B42318';
        } elseif ($kehadiran >= 95) {
            $items[] = [
                'icon' => 'fa-calendar-check',
                'title' => 'Kehadiran Sangat Baik',
                'text' => 'Tingkat kehadiran Anda '.number_format($kehadiran, 1).'%. Luar biasa! Konsistensi hadir adalah kunci sukses.',
                'color' => '#7c3aed',
            ];
        }

        $terlemah = $nilaiPerMapel->sortBy('rata_rata')->first();
        $hurufLemah = $terlemah
            ? ($this->nilaiService->getPredikat($terlemah['rata_rata'], $kkm)['huruf'] ?? null)
            : null;
        if ($terlemah && ($hurufLemah === 'C' || $hurufLemah === 'D')) {
            $items[] = [
                'icon' => 'fa-exclamation-triangle',
                'title' => 'Perhatikan: '.$terlemah['nama'],
                'text' => 'Nilai rata-rata '.$terlemah['nama'].' baru '.number_format($terlemah['rata_rata'], 1).' (predikat '.$hurufLemah.'). Perlu perhatian lebih untuk mata pelajaran ini.',
                'color' => '#B8860B',
            ];
            if ($warna !== '#B42318') {
                $warna = '#B8860B';
            }
        }

        $terbaik = $nilaiPerMapel->sortByDesc('rata_rata')->first();
        $hurufBaik = $terbaik
            ? ($this->nilaiService->getPredikat($terbaik['rata_rata'], $kkm)['huruf'] ?? null)
            : null;
        if ($terbaik && $hurufBaik === 'A') {
            $items[] = [
                'icon' => 'fa-trophy',
                'title' => 'Terbaik: '.$terbaik['nama'],
                'text' => 'Anda sangat unggul di '.$terbaik['nama'].' dengan rata-rata '.number_format($terbaik['rata_rata'], 1).'. Teruskan!',
                'color' => '#7c3aed',
            ];
        }

        if (empty($items)) {
            $items[] = [
                'icon' => 'fa-info-circle',
                'title' => 'Mulai Belajar',
                'text' => 'Kerjakan kuis dan perhatikan pelajaran untuk melihat perkembangan Anda di sini.',
                'color' => '#667085',
            ];
            $warna = '#667085';
        }

        return ['items' => $items, 'warna' => $warna];
    }
}
