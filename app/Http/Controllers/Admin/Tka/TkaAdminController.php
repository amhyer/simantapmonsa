<?php

namespace App\Http\Controllers\Admin\Tka;

use App\Http\Controllers\Controller;
use App\Models\Tka\Dimension;
use App\Models\Tka\StudentAssessment;
use App\Models\Tka\EarlyWarning;
use App\Models\Siswa;
use App\Models\Semester;
use App\Services\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TkaAdminController extends Controller
{
    /**
     * Display TKA dashboard with statistics.
     */
    public function index()
    {
        $stats = [
            'total_dimensions' => Dimension::count(),
            'active_dimensions' => Dimension::where('aktif', true)->count(),
            'total_assessments' => StudentAssessment::count(),
            'assessments_this_month' => StudentAssessment::whereMonth('tanggal_penilaian', now()->month)
                                                        ->whereYear('tanggal_penilaian', now()->year)
                                                        ->count(),
            'status_distribution' => StudentAssessment::selectRaw('status_kesiapan, count(*) as count')
                                                     ->groupBy('status_kesiapan')
                                                     ->pluck('count', 'status_kesiapan'),
            'early_warnings' => [
                'total' => EarlyWarning::count(),
                'pending' => EarlyWarning::where('status', 'pending')->count(),
                'sedang_diproses' => EarlyWarning::where('status', 'sedang_diproses')->count(),
                'selesai' => EarlyWarning::where('status', 'selesai')->count(),
            ],
            'recent_assessments' => StudentAssessment::with(['siswa', 'semester'])
                                                    ->latest('tanggal_penilaian')
                                                    ->take(5)
                                                    ->get(),
            'critical_warnings' => EarlyWarning::with(['siswa', 'dimension'])
                                              ->where('tingkat_peringatan', 'kritis')
                                              ->where('status', 'pending')
                                              ->latest()
                                              ->take(5)
                                              ->get(),
        ];

        $semesters = Semester::orderByDesc('tahun_ajaran')->orderBy('nama_semester')->get();

        return view('admin.tka.dashboard', compact('stats', 'semesters'));
    }

    /**
     * Display all dimensions management.
     */
    public function dimensions(Request $request)
    {
        $query = Dimension::with(['assessmentItems']);

        // Filter by semester
        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        // Filter by category
        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        // Filter by status
        if ($request->filled('aktif')) {
            $query->where('aktif', $request->aktif === 'true');
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_dimensi', 'like', "%{$search}%")
                  ->orWhere('kode_dimensi', 'like', "%{$search}%");
            });
        }

        $dimensions = $query->withCount(['assessmentItems'])
                           ->orderBy('kategori')
                           ->orderBy('nama_dimensi')
                           ->get();

        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();

        return view('admin.tka.dimensions.index', compact('dimensions', 'semesters'));
    }
    
    /**
     * Show the form for creating a new dimension.
     */
    public function createDimension()
    {
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        
        return view('admin.tka.dimensions.create', compact('semesters'));
    }
    
    /**
     * Store a newly created dimension.
     */
    public function storeDimension(Request $request)
    {
        $validated = $request->validate([
            'kode_dimensi' => 'required|string|max:20|unique:tka_dimensions,kode_dimensi',
            'nama_dimensi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'required|in:akhlak_beragama,kebinekaan_global,berkebhinekaan,mandiri,bernalar_kritis,kreatif',
        ]);
        
        $validated['aktif'] = true;
        
        Dimension::create($validated);
        
        return redirect()->route('admin.tka.dimensions.index')
            ->with('success', 'Dimensi berhasil dibuat');
    }
    
    /**
     * Display the specified dimension.
     */
    public function showDimension(Dimension $dimension)
    {
        $dimension->load(['assessmentItems']);
        
        return view('admin.tka.dimensions.show', compact('dimension'));
    }
    
    /**
     * Show the form for editing the specified dimension.
     */
    public function editDimension(Dimension $dimension)
    {
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        
        return view('admin.tka.dimensions.edit', compact('dimension', 'semesters'));
    }
    
    /**
     * Update the specified dimension.
     */
    public function updateDimension(Request $request, Dimension $dimension)
    {
        $validated = $request->validate([
            'kode_dimensi' => 'required|string|max:20|unique:tka_dimensions,kode_dimensi,' . $dimension->id,
            'nama_dimensi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'required|in:akhlak_beragama,kebinekaan_global,berkebhinekaan,mandiri,bernalar_kritis,kreatif',
        ]);
        
        $dimension->update($validated);
        
        return redirect()->route('admin.tka.dimensions.index')
            ->with('success', 'Dimensi berhasil diperbarui');
    }
    
    /**
     * Toggle dimension active status.
     */
    public function toggleDimensionStatus(Dimension $dimension)
    {
        $dimension->update(['aktif' => !$dimension->aktif]);
        
        return back()->with('success', 'Status dimensi berhasil diubah');
    }

    /**
     * Display all assessments.
     */
    public function assessments(Request $request)
    {
        $query = StudentAssessment::with(['siswa', 'semester', 'guru', 'responses.assessmentItem.dimension']);

        // Filter by status
        if ($request->filled('status_kesiapan')) {
            $query->where('status_kesiapan', $request->status_kesiapan);
        }

        // Filter by dimension (through responses)
        if ($request->filled('dimension_id')) {
            $query->whereHas('responses.assessmentItem', function ($q) use ($request) {
                $q->where('dimension_id', $request->dimension_id);
            });
        }

        // Filter by semester
        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        // Filter by date range
        if ($request->filled('tanggal_from')) {
            $query->whereDate('tanggal_penilaian', '>=', $request->tanggal_from);
        }
        if ($request->filled('tanggal_to')) {
            $query->whereDate('tanggal_penilaian', '<=', $request->tanggal_to);
        }

        // Search by student name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nama_peserta_didik', 'like', "%{$search}%");
            });
        }

        $assessments = $query->latest('tanggal_penilaian')
                            ->paginate(25);

        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        $dimensions = Dimension::where('aktif', true)->orderBy('nama_dimensi')->get();

        return view('admin.tka.assessments.index', compact('assessments', 'semesters', 'dimensions'));
    }

    /**
     * Display early warnings management.
     */
    public function earlyWarnings(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending,sedang_diproses,selesai',
            'tingkat_peringatan' => 'nullable|in:rendah,sedang,tinggi,kritis',
            'dimension_id' => 'nullable|integer|exists:tka_dimensions_enhanced,id',
            'tanggal_from' => 'nullable|date',
            'tanggal_to' => 'nullable|date',
            'search' => 'nullable|string|max:150',
        ]);

        $query = EarlyWarning::with(['siswa', 'semester', 'dimension', 'handler']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by severity
        if ($request->filled('tingkat_peringatan')) {
            $query->where('tingkat_peringatan', $request->tingkat_peringatan);
        }

        // Filter by dimension
        if ($request->filled('dimension_id')) {
            $query->where('dimension_id', $request->dimension_id);
        }

        // Filter by date
        if ($request->filled('tanggal_from')) {
            $query->whereDate('created_at', '>=', $request->tanggal_from);
        }
        if ($request->filled('tanggal_to')) {
            $query->whereDate('created_at', '<=', $request->tanggal_to);
        }
        
        // Search by student name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nama_peserta_didik', 'like', "%{$search}%");
            });
        }

        $warnings = $query->latest()
                         ->paginate(25)
                         ->withQueryString();

        $dimensions = Dimension::where('aktif', true)->orderBy('nama_dimensi')->get();
        $statusCounts = EarlyWarning::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $levelCounts = EarlyWarning::query()
            ->select('tingkat_peringatan')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('tingkat_peringatan')
            ->pluck('total', 'tingkat_peringatan');

        return view('admin.tka.warnings.index', compact('warnings', 'dimensions', 'statusCounts', 'levelCounts'));
    }
    
    /**
     * Display the specified warning.
     */
    public function showWarning(EarlyWarning $warning)
    {
        $warning->load(['siswa', 'dimension', 'handler']);
        
        return view('admin.tka.warnings.show', compact('warning'));
    }

    /**
     * Update early warning status.
     */
    public function updateWarningStatus(Request $request, EarlyWarning $warning)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,sedang_diproses,selesai',
            'ditangani_oleh' => 'nullable|exists:users,id',
        ]);

        $warning->update([
            'status' => $validated['status'],
            'ditangani_oleh' => $validated['ditangani_oleh'] ?? auth()->id(),
            'tanggal_selesai' => $validated['status'] === 'selesai' ? now() : null,
        ]);

        return back()->with('success', 'Status early warning berhasil diperbarui');
    }

    /**
     * Generate a semester report containing every student assessment recorded
     * for that semester.
     */
    public function generateReport(Request $request, ExcelExportService $excel)
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:semesters,id',
            'format' => 'required|in:pdf,excel',
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);
        $assessments = StudentAssessment::with(['siswa', 'semester', 'guru'])
            ->where('semester_id', $semester->id)
            ->orderBy('tanggal_penilaian')
            ->orderBy('id')
            ->get();

        if ($assessments->isEmpty()) {
            return back()->with('error', 'Belum ada penilaian TKA pada semester yang dipilih.');
        }

        $rows = $this->assessmentRows($assessments);
        $filename = 'laporan-tka-semester-' . $semester->id . '-' . now()->format('Y-m-d');
        $subtitle = 'Semester ' . $semester->label . ' • ' . $assessments->count() . ' penilaian';

        return $this->downloadTable(
            $excel,
            $validated['format'],
            $filename,
            'Laporan Massal TKA',
            'Laporan TKA',
            $this->assessmentHeaders(),
            $rows,
            $subtitle,
        );
    }

    /**
     * Show historical tracking for a student.
     */
    public function studentHistory($siswaId)
    {
        $siswa = Siswa::findOrFail($siswaId);
        
        $trackings = \App\Models\Tka\HistoricalTracking::with(['dimension', 'semester'])
                                                       ->where('siswa_id', $siswaId)
                                                       ->orderBy('created_at', 'desc')
                                                       ->get()
                                                       ->groupBy('dimension.nama_dimensi');

        $assessments = StudentAssessment::with(['semester', 'responses.assessmentItem.dimension'])
                                       ->where('siswa_id', $siswaId)
                                       ->orderBy('tanggal_penilaian', 'desc')
                                       ->get();

        return view('admin.tka.students.history', compact('siswa', 'trackings', 'assessments'));
    }

    /**
     * Export the selected TKA dataset as XLSX or PDF.
     */
    public function export(Request $request, ExcelExportService $excel)
    {
        $validated = $request->validate([
            'type' => 'required|in:assessments,warnings,dimensions',
            'format' => 'required|in:excel,pdf',
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;
        [$title, $sheetTitle, $headers, $rows] = $this->exportDataset($validated['type'], $semesterId);

        if ($rows === []) {
            return back()->with('error', 'Tidak ada data TKA yang cocok untuk diekspor.');
        }

        $semester = $semesterId === null ? null : Semester::find($semesterId);
        $subtitle = $semester ? 'Semester ' . $semester->label : 'Semua semester';
        $subtitle .= ' • ' . count($rows) . ' baris';
        $filename = 'tka-' . $validated['type'] . '-' . now()->format('Y-m-d');

        return $this->downloadTable(
            $excel,
            $validated['format'],
            $filename,
            $title,
            $sheetTitle,
            $headers,
            $rows,
            $subtitle,
        );
    }

    /** @return list<string> */
    private function assessmentHeaders(): array
    {
        return [
            'No', 'Nama siswa', 'NIS', 'NISN', 'Kelas', 'Semester',
            'Tanggal penilaian', 'Total skor', 'Skor maksimal', 'Persentase (%)',
            'Status kesiapan', 'Skor per dimensi',
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, StudentAssessment> $assessments
     * @return list<list<mixed>>
     */
    private function assessmentRows($assessments): array
    {
        return $assessments->values()->map(function (StudentAssessment $assessment, int $index): array {
            return [
                $index + 1,
                $assessment->siswa?->nama_peserta_didik ?? 'Siswa dihapus',
                $assessment->siswa?->nis ?? '-',
                $assessment->siswa?->nisn ?? '-',
                $assessment->siswa?->kelas ?? '-',
                $assessment->semester?->label ?? '-',
                $assessment->tanggal_penilaian?->format('Y-m-d') ?? '-',
                $assessment->total_score,
                $assessment->max_score,
                $assessment->persentase,
                $this->readinessLabel($assessment->status_kesiapan),
                $this->dimensionScoreSummary($assessment->skor_per_dimensi),
            ];
        })->all();
    }

    /** @param array<int, array<string, mixed>>|null $scores */
    private function dimensionScoreSummary(?array $scores): string
    {
        return collect($scores ?? [])->map(function ($score): ?string {
            if (! is_array($score)) {
                return null;
            }

            $name = (string) ($score['nama_dimensi'] ?? 'Dimensi');
            $earned = number_format((float) ($score['total_skor'] ?? 0), 1, '.', '');
            $maximum = number_format((float) ($score['maksimal'] ?? 0), 1, '.', '');

            return "{$name}: {$earned}/{$maximum}";
        })->filter()->implode('; ');
    }

    private function readinessLabel(?string $status): string
    {
        return match ($status) {
            'sangat_siap' => 'Sangat siap',
            'siap' => 'Siap',
            'perlu_bimbingan' => 'Perlu bimbingan',
            'belum_siap' => 'Belum siap',
            default => $status ?? '-',
        };
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>, 3: list<list<mixed>>}
     */
    private function exportDataset(string $type, ?int $semesterId): array
    {
        if ($type === 'assessments') {
            $assessments = StudentAssessment::with(['siswa', 'semester', 'guru'])
                ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
                ->orderByDesc('tanggal_penilaian')
                ->orderByDesc('id')
                ->get();

            return ['Penilaian TKA', 'Penilaian TKA', $this->assessmentHeaders(), $this->assessmentRows($assessments)];
        }

        if ($type === 'warnings') {
            $warnings = EarlyWarning::with(['siswa', 'semester', 'dimension', 'handler'])
                ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
                ->latest('created_at')
                ->get();

            $headers = [
                'No', 'Nama siswa', 'NIS', 'Kelas', 'Semester', 'Dimensi',
                'Tingkat peringatan', 'Status', 'Alasan', 'Rekomendasi tindakan',
                'Ditangani oleh', 'Dibuat pada', 'Selesai pada',
            ];
            $rows = $warnings->values()->map(fn (EarlyWarning $warning, int $index): array => [
                $index + 1,
                $warning->siswa?->nama_peserta_didik ?? 'Siswa dihapus',
                $warning->siswa?->nis ?? '-',
                $warning->siswa?->kelas ?? '-',
                $warning->semester?->label ?? '-',
                $warning->dimension?->nama_dimensi ?? '-',
                ucfirst($warning->tingkat_peringatan),
                str_replace('_', ' ', ucfirst($warning->status)),
                $warning->alasan_peringatan,
                $warning->rekomendasi_tindakan,
                $warning->handler?->nama_lengkap ?? '-',
                $warning->created_at?->format('Y-m-d H:i:s') ?? '-',
                $warning->tanggal_selesai?->format('Y-m-d H:i:s') ?? '-',
            ])->all();

            return ['Early Warning TKA', 'Early Warning', $headers, $rows];
        }

        $dimensions = Dimension::with('semester')
            ->withCount('assessmentItems')
            ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
            ->orderBy('nama_dimensi')
            ->get();

        $headers = [
            'No', 'Kode dimensi', 'Nama dimensi', 'Kategori', 'Semester',
            'Bobot', 'Ambang siap', 'Jumlah indikator', 'Status', 'Deskripsi',
        ];
        $rows = $dimensions->values()->map(fn (Dimension $dimension, int $index): array => [
            $index + 1,
            $dimension->kode_dimensi,
            $dimension->nama_dimensi,
            str_replace('_', ' ', ucfirst($dimension->kategori)),
            $dimension->semester?->label ?? '-',
            $dimension->bobot,
            $dimension->threshold_siap,
            $dimension->assessment_items_count,
            $dimension->aktif ? 'Aktif' : 'Nonaktif',
            $dimension->deskripsi ?? '-',
        ])->all();

        return ['Dimensi TKA', 'Dimensi TKA', $headers, $rows];
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows */
    private function downloadTable(
        ExcelExportService $excel,
        string $format,
        string $fileName,
        string $title,
        string $sheetTitle,
        array $headers,
        array $rows,
        string $subtitle,
    ) {
        if ($format === 'excel') {
            return $excel->download($fileName . '.xlsx', $sheetTitle, $headers, $rows);
        }

        return Pdf::loadView('admin.exports.table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
            'generatedAt' => now()->format('d-m-Y H:i'),
        ])->setPaper('a4', 'landscape')->download($fileName . '.pdf');
    }
}
