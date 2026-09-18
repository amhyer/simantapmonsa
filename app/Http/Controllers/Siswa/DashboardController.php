<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\HasilKuis;
use App\Models\Kehadiran;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Services\NilaiService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use HasSiswaLookup;

    protected $nilaiService;

    public function __construct(NilaiService $nilaiService)
    {
        $this->nilaiService = $nilaiService;
    }

    public function index()
    {
        $siswa = $this->getSiswa();

        if (!$siswa) {
            return view('siswa.dashboard', [
                'siswa' => null,
                'kehadiran' => 0,
                'totalHadir' => 0,
                'rataRata' => 0,
                'jumlahNilai' => 0,
                'materiTerbaru' => collect(),
                'kuisAktif' => collect(),
                'nilaiPerMapel' => collect(),
                'kehadiranBulanan' => collect(),
                'saran' => null,
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

        $nilaiPerMapel = $nilaiData->groupBy('mata_pelajaran')->map(function ($items, $name) {
            return [
                'nama' => $name,
                'rata_rata' => $items->avg('nilai'),
                'jumlah' => $items->count(),
            ];
        })->values();

        $kehadiranBulanan = Kehadiran::where('siswa_id', $siswa->id)
            ->whereYear('tanggal', date('Y'))
            ->selectRaw("to_char(tanggal, 'FMMonth') as nama, to_char(tanggal, 'YYYY-MM') as bulan")
            ->selectRaw("SUM(CASE WHEN status='H' THEN 1 ELSE 0 END) as h")
            ->selectRaw("SUM(CASE WHEN status='S' THEN 1 ELSE 0 END) as s")
            ->selectRaw("SUM(CASE WHEN status='I' THEN 1 ELSE 0 END) as i")
            ->selectRaw("SUM(CASE WHEN status='A' THEN 1 ELSE 0 END) as a")
            ->groupByRaw("to_char(tanggal, 'FMMonth'), to_char(tanggal, 'YYYY-MM')")
            ->orderBy('bulan')
            ->get()
            ->map(fn($r) => ['nama' => $r->nama, 'H' => (int) $r->h, 'S' => (int) $r->s, 'I' => (int) $r->i, 'A' => (int) $r->a]);

        $saran = $this->generateSaran($siswa, $rataRata, $kehadiran, $nilaiPerMapel);

        return view('siswa.dashboard', compact(
            'siswa', 'kehadiran', 'totalHadir', 'rataRata',
            'jumlahNilai', 'materiTerbaru', 'kuisAktif',
            'nilaiPerMapel', 'kehadiranBulanan', 'saran'
        ));
    }

    private function generateSaran(Siswa $siswa, float $rataRata, float $kehadiran, $nilaiPerMapel): array
    {
        $items = [];
        $warna = '#0f766e';

        if ($rataRata > 0 && $rataRata < 70) {
            $items[] = [
                'icon' => 'fa-book-reader',
                'title' => 'Tingkatkan Belajar',
                'text' => 'Rata-rata nilai Anda masih di bawah KKM (70). Rutin belajar setiap hari dan bertanya kepada guru jika ada yang kurang dipahami.',
                'color' => '#B42318',
            ];
            $warna = '#B42318';
        } elseif ($rataRata >= 70 && $rataRata < 80) {
            $items[] = [
                'icon' => 'fa-arrow-up',
                'title' => 'Pertahankan & Tingkatkan',
                'text' => 'Nilai Anda sudah cukup baik. Untuk mencapai predikat B atau A, fokus pada mata pelajaran yang masih di bawah rata-rata.',
                'color' => '#B8860B',
            ];
            $warna = '#B8860B';
        } elseif ($rataRata >= 80) {
            $items[] = [
                'icon' => 'fa-star',
                'title' => 'Prestasi Bagus!',
                'text' => 'Rata-rata nilai Anda sangat baik. Pertahankan semangat belajar dan bantu teman yang membutuhkan.',
                'color' => '#0f766e',
            ];
        }

        if ($kehadiran < 80 && $kehadiran > 0) {
            $items[] = [
                'icon' => 'fa-calendar-xmark',
                'title' => 'Kehadiran Kurang',
                'text' => 'Tingkat kehadiran Anda ' . number_format($kehadiran, 1) . '%. Usahakan hadir setiap hari agar tidak ketinggalan pelajaran.',
                'color' => '#B42318',
            ];
            $warna = '#B42318';
        } elseif ($kehadiran >= 95) {
            $items[] = [
                'icon' => 'fa-calendar-check',
                'title' => 'Kehadiran Sangat Baik',
                'text' => 'Tingkat kehadiran Anda ' . number_format($kehadiran, 1) . '%. Luar biasa! Konsistensi hadir adalah kunci sukses.',
                'color' => '#0f766e',
            ];
        }

        $terlemah = $nilaiPerMapel->sortBy('rata_rata')->first();
        if ($terlemah && $terlemah['rata_rata'] < 75) {
            $items[] = [
                'icon' => 'fa-exclamation-triangle',
                'title' => 'Perhatikan: ' . $terlemah['nama'],
                'text' => 'Nilai rata-rata ' . $terlemah['nama'] . ' baru ' . number_format($terlemah['rata_rata'], 1) . '. Perlu perhatian lebih untuk mata pelajaran ini.',
                'color' => '#B8860B',
            ];
            if ($warna !== '#B42318') $warna = '#B8860B';
        }

        $terbaik = $nilaiPerMapel->sortByDesc('rata_rata')->first();
        if ($terbaik && $terbaik['rata_rata'] >= 85) {
            $items[] = [
                'icon' => 'fa-trophy',
                'title' => 'Terbaik: ' . $terbaik['nama'],
                'text' => 'Anda sangat unggul di ' . $terbaik['nama'] . ' dengan rata-rata ' . number_format($terbaik['rata_rata'], 1) . '. Teruskan!',
                'color' => '#0f766e',
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
