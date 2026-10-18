<?php

namespace App\Http\Controllers\Tka;

use App\Http\Controllers\Controller;
use App\Models\Tka\ParentReport;
use App\Models\Tka\StudentAssessment;
use App\Models\Siswa;
use Illuminate\Http\Request;

class TkaParentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Anak terhubung via User.terhubung_dengan (konvensi aplikasi).
        $terhubung = $user->terhubung_dengan;
        $ids = is_array($terhubung) ? $terhubung : [];
        $children = $ids === []
            ? collect()
            : Siswa::whereIn('id', $ids)->where('aktif', true)->orderBy('nama_peserta_didik')->get();

        $siswaIds = $children->pluck('id');
        
        $reports = ParentReport::whereIn('siswa_id', $siswaIds)
                              ->with(['assessment.siswa', 'assessment.semester'])
                              ->latest()
                              ->paginate(20);

        return view('tka.parent.index', compact('reports', 'children'));
    }

    public function show(ParentReport $report)
    {
        // Authorization check
        $user = auth()->user();
        $siswa = $report->siswa;
        
        $terhubung = $user->terhubung_dengan;
        $isParent = is_array($terhubung) && in_array($siswa->id, $terhubung, true);
        
        if (!$isParent) {
            abort(403, 'Anda tidak memiliki akses ke laporan ini');
        }

        // Mark as read
        if (!$report->dibaca_oleh_ortu) {
            $report->update([
                'dibaca_oleh_ortu' => true,
                'tanggal_dibaca' => now(),
                'ortu_user_id' => $user->id,
            ]);
        }

        $report->load(['assessment.siswa', 'assessment.responses.assessmentItem.dimension']);

        return view('tka.parent.show', compact('report'));
    }

    public function generateReport(StudentAssessment $assessment)
    {
        // Check if report already exists
        $existingReport = ParentReport::where('assessment_id', $assessment->id)->first();
        
        if ($existingReport) {
            return redirect()->route('tka.parent.show', $existingReport);
        }

        // Generate suggested activities based on weak dimensions
        $skorPerDimensi = $assessment->skor_per_dimensi;
        $aktivitasSuggested = [];

        foreach ($skorPerDimensi as $dimensi) {
            if ($dimensi['total_skor'] / $dimensi['maksimal'] < 0.75) {
                $aktivitasSuggested[] = [
                    'dimension' => $dimensi['nama_dimensi'],
                    'kategori' => $dimensi['kategori'],
                    'activities' => $this->getSuggestedActivities($dimensi['kategori']),
                ];
            }
        }

        // Create parent report
        $report = ParentReport::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'assessment_id' => $assessment->id,
            'siswa_id' => $assessment->siswa_id,
            'rekomendasi_rumah' => $this->generateHomeRecommendations($assessment),
            'aktivitas_suggested' => $aktivitasSuggested,
            'dibaca_oleh_ortu' => false,
        ]);

        return redirect()->route('tka.parent.show', $report)
            ->with('success', 'Laporan untuk orang tua berhasil dibuat');
    }

    private function getSuggestedActivities($kategori)
    {
        $activities = [
            'religius' => [
                'Membaca kitab suci bersama keluarga',
                'Melaksanakan ibadah secara rutin',
                'Mengikuti kegiatan keagamaan di komunitas',
            ],
            'nasionalis' => [
                'Mengenal tokoh pahlawan nasional',
                'Menyanyikan lagu nasional',
                'Mempelajari sejarah Indonesia',
            ],
            'mandiri' => [
                'Membantu pekerjaan rumah tangga',
                'Mengatur jadwal belajar sendiri',
                'Merapikan tempat tidur dan kamar',
            ],
            'gotong_royong' => [
                'Ikut kerja bakti lingkungan',
                'Membantu tetangga yang membutuhkan',
                'Bekerja sama dalam tugas kelompok',
            ],
            'integritas' => [
                'Berbicara jujur dalam segala situasi',
                'Mengakui kesalahan',
                'Menepati janji',
            ],
            'kreatif' => [
                'Membuat karya seni atau kerajinan',
                'Menulis cerita atau puisi',
                'Mencoba hal baru',
            ],
            'berdaya_saing' => [
                'Mengikuti kompetisi atau lomba',
                'Belajar dari kegagalan',
                'Meningkatkan kemampuan secara bertahap',
            ],
        ];

        return $activities[$kategori] ?? ['Aktivitas rutin sesuai minat siswa'];
    }

    private function generateHomeRecommendations($assessment)
    {
        $status = $assessment->status_kesiapan;
        $skorPerDimensi = $assessment->skor_per_dimensi;
        
        $weakDimensions = collect($skorPerDimensi)
            ->where('total_skor', '<', function ($item) {
                return $item['maksimal'] * 0.75;
            })
            ->pluck('nama_dimensi')
            ->toArray();

        $recommendation = "Ananda {$assessment->siswa->nama_peserta_didik} memiliki status kesiapan: **" . 
                         ucfirst(str_replace('_', ' ', $status)) . "**.\n\n";

        if (count($weakDimensions) > 0) {
            $recommendation .= "Dimensi yang perlu diperhatikan: " . implode(', ', $weakDimensions) . ".\n\n";
            $recommendation .= "Disarankan untuk memberikan bimbingan lebih intensif pada dimensi-dimensi tersebut.";
        } else {
            $recommendation .= "Semua dimensi sudah menunjukkan perkembangan yang baik. " .
                             "Pertahankan dan tingkatkan aktivitas positif yang sudah berjalan.";
        }

        return $recommendation;
    }
}
