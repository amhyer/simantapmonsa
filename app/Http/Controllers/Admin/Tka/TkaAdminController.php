<?php

namespace App\Http\Controllers\Admin\Tka;

use App\Http\Controllers\Controller;
use App\Models\Tka\Dimension;
use App\Models\Tka\StudentAssessment;
use App\Models\Tka\EarlyWarning;
use App\Models\Siswa;
use App\Models\Semester;
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

        return view('admin.tka.dashboard', compact('stats'));
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
                         ->paginate(25);

        $dimensions = Dimension::where('aktif', true)->orderBy('nama_dimensi')->get();

        return view('admin.tka.warnings.index', compact('warnings', 'dimensions'));
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
     * Generate bulk report for all students.
     */
    public function generateReport(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'format' => 'required|in:pdf,excel',
        ]);

        // Implementation depends on your reporting library
        // This is a placeholder
        return back()->with('info', 'Fitur laporan massal akan segera tersedia');
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
     * Export TKA data.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:assessments,warnings,dimensions',
            'format' => 'required|in:excel,pdf',
            'semester_id' => 'nullable|exists:semesters,id',
        ]);

        // Implementation depends on your export library
        return back()->with('info', 'Fitur export akan segera tersedia');
    }
}
