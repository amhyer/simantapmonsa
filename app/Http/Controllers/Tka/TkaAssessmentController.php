<?php

namespace App\Http\Controllers\Tka;

use App\Http\Controllers\Controller;
use App\Models\Tka\Dimension;
use App\Models\Tka\AssessmentItem;
use App\Models\Tka\StudentAssessment;
use App\Models\Tka\AssessmentResponse;
use App\Models\Tka\HistoricalTracking;
use App\Models\Tka\EarlyWarning;
use App\Models\Siswa;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TkaAssessmentController extends Controller
{
    public function index()
    {
        $assessments = StudentAssessment::with(['siswa', 'semester', 'guru'])
                                       ->where('guru_id', auth()->id())
                                       ->latest('tanggal_penilaian')
                                       ->paginate(20);
        
        return view('tka.assessments.index', compact('assessments'));
    }

    public function create()
    {
        $siswas = Siswa::where('aktif', true)
                      ->orderBy('kelas')
                      ->orderBy('nama_peserta_didik')
                      ->get();
        
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        
        $dimensions = Dimension::with(['assessmentItems'])
                              ->where('aktif', true)
                              ->orderBy('kategori')
                              ->get();
        
        return view('tka.assessments.create', compact('siswas', 'semesters', 'dimensions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'semester_id' => 'required|exists:semesters,id',
            'tanggal_penilaian' => 'required|date',
            'responses' => 'required|array',
            'responses.*.assessment_item_id' => 'required|exists:tka_assessment_items,id',
            'responses.*.skor' => 'required|numeric|min:1|max:4',
            'responses.*.catatan_guru' => 'nullable|string',
        ]);

        // Check if assessment already exists
        $existing = StudentAssessment::where('siswa_id', $validated['siswa_id'])
                                    ->where('semester_id', $validated['semester_id'])
                                    ->first();
        
        if ($existing) {
            return back()->with('error', 'Penilaian TKA untuk siswa ini pada semester tersebut sudah ada');
        }

        DB::beginTransaction();
        try {
            // Calculate total score
            $totalScore = 0;
            $maxScore = 0;
            $skorPerDimensi = [];

            foreach ($validated['responses'] as $response) {
                $item = AssessmentItem::with('dimension')->find($response['assessment_item_id']);
                $weightedScore = $response['skor'] * $item->bobot_item;
                $totalScore += $weightedScore;
                $maxScore += 4 * $item->bobot_item;

                // Group by dimension
                $dimId = $item->dimension_id;
                if (!isset($skorPerDimensi[$dimId])) {
                    $skorPerDimensi[$dimId] = [
                        'dimension_id' => $dimId,
                        'nama_dimensi' => $item->dimension->nama_dimensi,
                        'kategori' => $item->dimension->kategori,
                        'total_skor' => 0,
                        'maksimal' => 0,
                    ];
                }
                $skorPerDimensi[$dimId]['total_skor'] += $weightedScore;
                $skorPerDimensi[$dimId]['maksimal'] += 4 * $item->bobot_item;
            }

            $persentase = $maxScore > 0 ? ($totalScore / $maxScore * 100) : 0;
            $avgScore = $maxScore > 0 ? ($totalScore / count($validated['responses'])) : 0;

            // Determine status
            $statusKesiapan = 'belum_siap';
            if ($avgScore >= 3.5) {
                $statusKesiapan = 'sangat_siap';
            } elseif ($avgScore >= 3.0) {
                $statusKesiapan = 'siap';
            } elseif ($avgScore >= 2.0) {
                $statusKesiapan = 'perlu_bimbingan';
            }

            // Create assessment
            $assessment = StudentAssessment::create([
                'uuid' => Str::uuid(),
                'siswa_id' => $validated['siswa_id'],
                'semester_id' => $validated['semester_id'],
                'guru_id' => auth()->id(),
                'tanggal_penilaian' => $validated['tanggal_penilaian'],
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'persentase' => $persentase,
                'status_kesiapan' => $statusKesiapan,
                'skor_per_dimensi' => array_values($skorPerDimensi),
            ]);

            // Create responses
            foreach ($validated['responses'] as $response) {
                AssessmentResponse::create([
                    'uuid' => Str::uuid(),
                    'assessment_id' => $assessment->id,
                    'assessment_item_id' => $response['assessment_item_id'],
                    'skor' => $response['skor'],
                    'catatan_guru' => $response['catatan_guru'] ?? null,
                ]);
            }

            // Track historical data
            foreach ($skorPerDimensi as $dimId => $data) {
                $avgDimScore = $data['maksimal'] > 0 ? ($data['total_skor'] / $data['maksimal'] * 4) : 0;
                
                // Get previous score for comparison
                $previousTracking = HistoricalTracking::where('siswa_id', $validated['siswa_id'])
                                                     ->where('dimension_id', $dimId)
                                                     ->latest()
                                                     ->first();
                
                $perubahan = null;
                $trend = null;
                
                if ($previousTracking) {
                    $perubahan = $avgDimScore - $previousTracking->skor;
                    $trend = $perubahan > 0.1 ? 'meningkat' : ($perubahan < -0.1 ? 'menurun' : 'stabil');
                }

                HistoricalTracking::create([
                    'uuid' => Str::uuid(),
                    'siswa_id' => $validated['siswa_id'],
                    'dimension_id' => $dimId,
                    'semester_id' => $validated['semester_id'],
                    'skor' => $avgDimScore,
                    'perubahan_skor' => $perubahan,
                    'trend' => $trend,
                ]);

                // Create early warning if score below threshold
                $dimension = Dimension::find($dimId);
                if ($avgDimScore < $dimension->threshold_siap && $statusKesiapan !== 'sangat_siap') {
                    $tingkat = 'rendah';
                    if ($avgDimScore < 2.0) $tingkat = 'kritis';
                    elseif ($avgDimScore < 2.5) $tingkat = 'tinggi';
                    elseif ($avgDimScore < 3.0) $tingkat = 'sedang';

                    EarlyWarning::create([
                        'uuid' => Str::uuid(),
                        'siswa_id' => $validated['siswa_id'],
                        'semester_id' => $validated['semester_id'],
                        'dimension_id' => $dimId,
                        'tingkat_peringatan' => $tingkat,
                        'alasan_peringatan' => "Skor dimensi {$dimension->nama_dimensi} di bawah threshold ({$avgDimScore} < {$dimension->threshold_siap})",
                        'rekomendasi_tindakan' => "Perlu bimbingan intensif untuk dimensi {$dimension->nama_dimensi}",
                        'status' => 'pending',
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('tka.assessments.show', $assessment)
                ->with('success', 'Penilaian TKA berhasil disimpan');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show(StudentAssessment $assessment)
    {
        $assessment->load(['siswa', 'semester', 'guru', 'responses.assessmentItem.dimension']);
        
        return view('tka.assessments.show', compact('assessment'));
    }

    public function bySiswa($siswaId)
    {
        $siswa = Siswa::findOrFail($siswaId);
        $assessments = StudentAssessment::where('siswa_id', $siswaId)
                                       ->with(['semester', 'guru'])
                                       ->latest('tanggal_penilaian')
                                       ->get();
        
        return view('tka.assessments.by-siswa', compact('siswa', 'assessments'));
    }
}
