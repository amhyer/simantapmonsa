<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Nilai;
use App\Models\HasilKuis;
use App\Models\Kehadiran;
use App\Models\Kebiasaan;
use App\Models\Kuis;
use App\Models\PengaturanGuru;
use App\Models\Semester;
use App\Services\NilaiService;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanController extends Controller
{
    protected $nilaiService;

    public function __construct(NilaiService $nilaiService)
    {
        $this->nilaiService = $nilaiService;
    }

    public function index()
    {
        $guruId = auth()->id();
        $request = request();

        $query = Siswa::where('guru_id', $guruId)->where('aktif', true);

        $kelasFilter = $request->input('kelas');
        $semesterId = $request->input('semester_id');
        $mapelFilter = $request->input('mata_pelajaran');

        if ($kelasFilter) {
            $query->where('kelas', $kelasFilter);
        }
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $siswaList = $query->get();
        $siswaIds = $siswaList->pluck('id');

        $allKelas = Siswa::where('guru_id', $guruId)->where('aktif', true)
            ->distinct()->pluck('kelas')->sort()->values();
        $allSemesters = Semester::orderByDesc('id')->get();
        $allMapel = Nilai::where('guru_id', $guruId)
            ->distinct()->pluck('mata_pelajaran')->filter()->sort()->values();

        // Batch load all data upfront
        $allNilai = Nilai::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
        $allKuis = HasilKuis::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
        $allKehadiran = Kehadiran::whereIn('siswa_id', $siswaIds)
            ->selectRaw('siswa_id, status, count(*) as jumlah')
            ->groupBy('siswa_id', 'status')
            ->get()
            ->groupBy('siswa_id');
        $allKebiasaan = Kebiasaan::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');

        $dataLaporan = [];
        foreach ($siswaList as $s) {
            $nilai = $allNilai->get($s->id, collect());
            $hasilKuis = $allKuis->get($s->id, collect());

            if ($mapelFilter) {
                $nilai = $nilai->where('mata_pelajaran', $mapelFilter);
            }
            if ($semesterId) {
                // Filter by siswa's semester_id
                $nilai = $nilai->filter(function ($n) use ($semesterId) {
                    return $n->siswa_id && Siswa::where('id', $n->siswa_id)->where('semester_id', $semesterId)->exists();
                });
            }

            $na = $this->nilaiService->hitungNilaiAkhirBatch($nilai, $hasilKuis);
            $predikat = $na['nilai_akhir'] > 0 ? $this->nilaiService->getPredikat($na['nilai_akhir'], getKKM($guruId)) : null;
            $kehadiranPersen = $this->nilaiService->hitungKehadiran($s->id);

            $kehadiranData = $allKehadiran->get($s->id, collect());
            $totalHadir = $kehadiranData->where('status', 'H')->sum('jumlah');
            $totalSakit = $kehadiranData->where('status', 'S')->sum('jumlah');
            $totalIzin = $kehadiranData->where('status', 'I')->sum('jumlah');
            $totalAlpa = $kehadiranData->where('status', 'A')->sum('jumlah');
            $totalKehadiran = $kehadiranData->sum('jumlah');

            $nilaiPerMapel = $nilai->groupBy('mata_pelajaran')->map(function ($items, $mapel) use ($guruId) {
                $rata = round($items->avg('nilai'), 1);
                return [
                    'nama' => $mapel,
                    'rata_rata' => $rata,
                    'predikat' => $rata >= getKKM($guruId) ? ($rata >= 90 ? 'A' : ($rata >= 80 ? 'B' : 'C')) : 'D',
                    'jumlah' => $items->count(),
                ];
            })->values();

            $rataKehadiran = [];
            $fields = ['bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat'];
            $semuaKebiasaan = $allKebiasaan->get($s->id, collect());
            foreach ($fields as $field) {
                $values = $semuaKebiasaan->pluck($field)->filter()->values();
                $rataKehadiran[$field] = $values->count() ? round($values->avg(), 2) : 0;
            }

            $dataLaporan[] = [
                'siswa' => $s,
                'nilai_akhir' => $na['nilai_akhir'],
                'rata_per_jenis' => $na['rata_per_jenis'],
                'predikat' => $predikat,
                'kehadiran_persen' => $kehadiranPersen,
                'total_hadir' => $totalHadir,
                'total_sakit' => $totalSakit,
                'total_izin' => $totalIzin,
                'total_alpa' => $totalAlpa,
                'total_kehadiran' => $totalKehadiran,
                'nilai_per_mapel' => $nilaiPerMapel,
                'kebiasaan_rata' => $rataKehadiran,
            ];
        }

        $stats = [
            'total_siswa' => count($dataLaporan),
            'rata_nilai' => count($dataLaporan) > 0 ? round(collect($dataLaporan)->avg('nilai_akhir'), 1) : 0,
            'tuntas' => collect($dataLaporan)->filter(fn($d) => $d['nilai_akhir'] >= getKKM($guruId))->count(),
            'belum_tuntas' => collect($dataLaporan)->filter(fn($d) => $d['nilai_akhir'] > 0 && $d['nilai_akhir'] < getKKM($guruId))->count(),
            'belum_dinilai' => collect($dataLaporan)->filter(fn($d) => $d['nilai_akhir'] == 0)->count(),
            'rata_kehadiran' => count($dataLaporan) > 0 ? round(collect($dataLaporan)->avg('kehadiran_persen'), 1) : 0,
        ];

        $predikatDistribusi = [
            'A' => collect($dataLaporan)->filter(fn($d) => is_array($d['predikat']) && ($d['predikat']['huruf'] ?? '') === 'A')->count(),
            'B' => collect($dataLaporan)->filter(fn($d) => is_array($d['predikat']) && ($d['predikat']['huruf'] ?? '') === 'B')->count(),
            'C' => collect($dataLaporan)->filter(fn($d) => is_array($d['predikat']) && ($d['predikat']['huruf'] ?? '') === 'C')->count(),
            'D' => collect($dataLaporan)->filter(fn($d) => is_array($d['predikat']) && ($d['predikat']['huruf'] ?? '') === 'D')->count(),
        ];

        return view('guru.laporan.index', compact(
            'dataLaporan', 'stats', 'predikatDistribusi',
            'allKelas', 'allSemesters', 'allMapel',
            'kelasFilter', 'semesterId', 'mapelFilter'
        ));
    }

    public function pdf()
    {
        $guruId = auth()->id();
        $request = request();

        $query = Siswa::where('guru_id', $guruId)->where('aktif', true);

        $kelasFilter = $request->input('kelas');
        $semesterId = $request->input('semester_id');
        $mapelFilter = $request->input('mata_pelajaran');

        if ($kelasFilter) {
            $query->where('kelas', $kelasFilter);
        }
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $siswaList = $query->get();
        $siswaIds = $siswaList->pluck('id');
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guruId)->first();

        $allNilai = Nilai::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
        $allKuis = HasilKuis::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
        $allKehadiran = Kehadiran::whereIn('siswa_id', $siswaIds)
            ->selectRaw('siswa_id, status, count(*) as jumlah')
            ->groupBy('siswa_id', 'status')
            ->get()
            ->groupBy('siswa_id');

        $dataLaporan = [];
        foreach ($siswaList as $s) {
            $nilai = $allNilai->get($s->id, collect());
            $hasilKuis = $allKuis->get($s->id, collect());

            if ($mapelFilter) {
                $nilai = $nilai->where('mata_pelajaran', $mapelFilter);
            }

            $na = $this->nilaiService->hitungNilaiAkhirBatch($nilai, $hasilKuis);
            $predikat = $na['nilai_akhir'] > 0 ? $this->nilaiService->getPredikat($na['nilai_akhir'], getKKM($guruId)) : null;
            $kehadiranPersen = $this->nilaiService->hitungKehadiran($s->id);

            $kehadiranData = $allKehadiran->get($s->id, collect());
            $totalHadir = $kehadiranData->where('status', 'H')->sum('jumlah');
            $totalSakit = $kehadiranData->where('status', 'S')->sum('jumlah');
            $totalIzin = $kehadiranData->where('status', 'I')->sum('jumlah');
            $totalAlpa = $kehadiranData->where('status', 'A')->sum('jumlah');
            $totalKehadiran = $kehadiranData->sum('jumlah');

            $nilaiPerMapel = $nilai->groupBy('mata_pelajaran')->map(function ($items, $mapel) use ($guruId) {
                $rata = round($items->avg('nilai'), 1);
                return [
                    'nama' => $mapel,
                    'rata_rata' => $rata,
                    'predikat' => $rata >= getKKM($guruId) ? ($rata >= 90 ? 'A' : ($rata >= 80 ? 'B' : 'C')) : 'D',
                    'jumlah' => $items->count(),
                ];
            })->values();

            $dataLaporan[] = [
                'siswa' => $s,
                'nilai_akhir' => $na['nilai_akhir'],
                'predikat' => $predikat,
                'kehadiran_persen' => $kehadiranPersen,
                'total_hadir' => $totalHadir,
                'total_sakit' => $totalSakit,
                'total_izin' => $totalIzin,
                'total_alpa' => $totalAlpa,
                'total_kehadiran' => $totalKehadiran,
                'nilai_per_mapel' => $nilaiPerMapel,
            ];
        }

        $pdf = Pdf::loadView('guru.laporan.pdf', compact('dataLaporan', 'guru', 'pengaturan', 'kelasFilter', 'semesterId', 'mapelFilter'))
            ->setPaper('a4', 'landscape');

        $filename = 'Laporan_' . ($guru->nama_lengkap ?? 'Guru');
        if ($kelasFilter) $filename .= '_Kelas-' . $kelasFilter;
        $filename .= '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
