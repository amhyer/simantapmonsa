<?php

namespace App\Http\Controllers\Admin\Tryout;

use App\Http\Controllers\Controller;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Session;
use App\Models\Tryout\Result;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Tryout\StudentAnswer;
use App\Services\ExcelExportService;
use App\Services\TryoutResultDetailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TryoutPackageAdminController extends Controller
{
    /**
     * Display a listing of all tryout packages.
     */
    public function index(Request $request)
    {
        $query = Package::with(['category', 'subjects']);

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by jenjang
        if ($request->filled('jenjang')) {
            $query->where('jenjang', $request->jenjang);
        }
        
        // Filter by tipe ujian
        if ($request->filled('tipe_ujian')) {
            $query->where('tipe_ujian', $request->tipe_ujian);
        }
        
        // Filter by mata pelajaran
        if ($request->filled('mata_pelajaran_id')) {
            $query->where('mata_pelajaran_id', $request->mata_pelajaran_id);
        }
        
        // Filter by year
        if ($request->filled('tahun')) {
            $query->where('tahun_paket', $request->tahun);
        }

        // Filter by status
        if ($request->filled('aktif')) {
            $query->where('aktif', $request->aktif === 'true');
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_paket', 'like', "%{$search}%")
                  ->orWhere('kode_paket', 'like', "%{$search}%");
            });
        }

        $packages = $query->withCount(['subjects', 'sessions'])
                         ->latest()
                         ->paginate(20);

        $categories = Category::where('aktif', true)->get();
        $mataPelajarans = MataPelajaran::orderBy('nama')->get();

        return view('admin.tryout.packages.index', compact('packages', 'categories', 'mataPelajarans'));
    }
    
    /**
     * Show the form for creating a new package.
     */
    public function create()
    {
        $categories = Category::where('aktif', true)->get();
        $mataPelajarans = MataPelajaran::orderBy('nama')->get();
        
        return view('admin.tryout.packages.create', compact('categories', 'mataPelajarans'));
    }
    
    /**
     * Store a newly created package.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_paket' => 'required|string|max:50|unique:tryout_packages,kode_paket',
            'nama_paket' => 'required|string|max:255',
            'jenjang' => 'required|in:SD,SMP,SMA,SMK',
            'tipe_ujian' => 'required|in:USBN,UNBK,AKM',
            'tahun_paket' => 'required|integer|min:2020|max:2030',
            'mata_pelajaran_id' => 'nullable|exists:mata_pelajaran,id',
            'total_soal' => 'required|integer|min:1',
            'waktu_menit' => 'required|integer|min:1',
            'kkm' => 'required|numeric|min:0|max:100',
            'deskripsi' => 'nullable|string',
        ]);
        
        $validated['uuid'] = Str::uuid();
        $validated['aktif'] = true;
        
        Package::create($validated);
        
        return redirect()->route('admin.tryout.packages.index')
            ->with('success', 'Paket tryout berhasil dibuat');
    }
    
    /**
     * Display the specified package.
     */
    public function show(Package $package)
    {
        $package->load(['subjects.questions', 'sessions.results']);
        
        return view('admin.tryout.packages.show', compact('package'));
    }
    
    /**
     * Show the form for editing the specified package.
     */
    public function edit(Package $package)
    {
        $categories = Category::where('aktif', true)->get();
        $mataPelajarans = MataPelajaran::orderBy('nama')->get();
        
        return view('admin.tryout.packages.edit', compact('package', 'categories', 'mataPelajarans'));
    }
    
    /**
     * Update the specified package.
     */
    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'kode_paket' => 'required|string|max:50|unique:tryout_packages,kode_paket,' . $package->id,
            'nama_paket' => 'required|string|max:255',
            'jenjang' => 'required|in:SD,SMP,SMA,SMK',
            'tipe_ujian' => 'required|in:USBN,UNBK,AKM',
            'tahun_paket' => 'required|integer|min:2020|max:2030',
            'mata_pelajaran_id' => 'nullable|exists:mata_pelajaran,id',
            'total_soal' => 'required|integer|min:1',
            'waktu_menit' => 'required|integer|min:1',
            'kkm' => 'required|numeric|min:0|max:100',
            'deskripsi' => 'nullable|string',
        ]);
        
        $package->update($validated);
        
        return redirect()->route('admin.tryout.packages.index')
            ->with('success', 'Paket tryout berhasil diperbarui');
    }
    
    /**
     * Remove the specified package.
     */
    public function destroy(Package $package)
    {
        if ($package->sessions()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus paket yang memiliki sesi');
        }
        
        $package->delete();
        
        return redirect()->route('admin.tryout.packages.index')
            ->with('success', 'Paket tryout berhasil dihapus');
    }

    /**
     * Display statistics for all tryout modules.
     */
    public function statistics()
    {
        $stats = [
            'total_packages' => Package::count(),
            'total_sessions' => Session::count(),
            'active_sessions' => Session::where('status', 'ongoing')->count(),
            'total_results' => \App\Models\Tryout\Result::count(),
            'average_pass_rate' => \App\Models\Tryout\Result::whereNotNull('waktu_selesai')
                                                           ->selectRaw('AVG(CASE WHEN lulus THEN 100 ELSE 0 END) as pass_rate')
                                                           ->value('pass_rate') ?? 0,
            'packages_by_jenjang' => Package::selectRaw('jenjang, count(*) as count')
                                           ->groupBy('jenjang')
                                           ->pluck('count', 'jenjang'),
            'recent_sessions' => Session::with(['package', 'guru'])
                                       ->latest()
                                       ->take(5)
                                       ->get(),
        ];

        return view('admin.tryout.statistics', compact('stats'));
    }

    /**
     * Display all tryout sessions across all packages.
     */
    public function allSessions(Request $request)
    {
        $query = Session::with(['package', 'semester', 'guru', 'rombel']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by package
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // Filter by date
        if ($request->filled('tanggal_mulai_from')) {
            $query->whereDate('tanggal_mulai', '>=', $request->tanggal_mulai_from);
        }
        if ($request->filled('tanggal_mulai_to')) {
            $query->whereDate('tanggal_mulai', '<=', $request->tanggal_mulai_to);
        }

        $sessions = $query->withCount('results')
                         ->latest()
                         ->paginate(20);

        $packages = Package::where('aktif', true)->orderBy('nama_paket')->get();
        $tahunAjarans = Semester::orderBy('tahun_ajaran', 'desc')->get();

        return view('admin.tryout.sessions.index', compact('sessions', 'packages', 'tahunAjarans'));
    }
    
    /**
     * Show the form for creating a new session.
     */
    public function createSession()
    {
        $packages = Package::where('aktif', true)->orderBy('nama_paket')->get();
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        
        return view('admin.tryout.sessions.create', compact('packages', 'semesters'));
    }
    
    /**
     * Store a newly created session.
     */
    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'kode_sesi' => 'required|string|max:50|unique:tryout_sessions,kode_sesi',
            'nama_sesi' => 'required|string|max:255',
            'package_id' => 'required|exists:tryout_packages,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'durasi_menit' => 'required|integer|min:1',
            'instruksi' => 'nullable|string',
        ]);
        
        $validated['uuid'] = Str::uuid();
        $validated['status'] = 'draft';
        
        Session::create($validated);
        
        return redirect()->route('admin.tryout.sessions.index')
            ->with('success', 'Sesi tryout berhasil dibuat');
    }
    
    /**
     * Display the specified session.
     */
    public function showSession(Session $session)
    {
        $session->load(['package.subjects', 'participants', 'results.siswa']);
        
        return view('admin.tryout.sessions.show', compact('session'));
    }
    
    /**
     * Show the form for editing the specified session.
     */
    public function editSession(Session $session)
    {
        $packages = Package::where('aktif', true)->orderBy('nama_paket')->get();
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        
        return view('admin.tryout.sessions.edit', compact('session', 'packages', 'semesters'));
    }
    
    /**
     * Update the specified session.
     */
    public function updateSession(Request $request, Session $session)
    {
        $validated = $request->validate([
            'kode_sesi' => 'required|string|max:50|unique:tryout_sessions,kode_sesi,' . $session->id,
            'nama_sesi' => 'required|string|max:255',
            'package_id' => 'required|exists:tryout_packages,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'durasi_menit' => 'required|integer|min:1',
            'instruksi' => 'nullable|string',
        ]);
        
        $session->update($validated);
        
        return redirect()->route('admin.tryout.sessions.index')
            ->with('success', 'Sesi tryout berhasil diperbarui');
    }
    
    /**
     * Remove the specified session.
     */
    public function destroySession(Session $session)
    {
        if ($session->results()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus sesi yang memiliki hasil ujian');
        }
        
        $session->delete();
        
        return redirect()->route('admin.tryout.sessions.index')
            ->with('success', 'Sesi tryout berhasil dihapus');
    }
    
    /**
     * Force complete an ongoing session.
     */
    public function forceCompleteSession(Session $session)
    {
        if ($session->status !== 'ongoing') {
            return back()->with('error', 'Hanya sesi yang sedang berjalan yang dapat dipaksa selesai');
        }
        
        // End all active results
        $session->results()->whereNull('waktu_selesai')->update([
            'waktu_selesai' => now(),
        ]);
        
        $session->update(['status' => 'completed']);

        return back()->with('success', 'Sesi berhasil dipaksa selesai');
    }

    /**
     * Build the shared, validated query used by the results table and export.
     */
    private function resultsQuery(Request $request): Builder
    {
        $filters = $request->validate([
            'lulus' => 'nullable|in:true,false',
            'package_id' => 'nullable|integer|exists:tryout_packages,id',
            'session_id' => 'nullable|integer|exists:tryout_sessions,id',
            'tanggal_from' => 'nullable|date',
            'tanggal_to' => 'nullable|date',
            'search' => 'nullable|string|max:150',
        ]);

        $query = Result::query()->with(['session.package', 'siswa']);

        if (isset($filters['lulus'])) {
            $query->where('lulus', $filters['lulus'] === 'true');
        }

        if (isset($filters['session_id'])) {
            $query->where('tryout_session_id', $filters['session_id']);
        }

        // The package filter is exposed by the page and must also be applied to
        // the results query (and therefore to its export).
        if (isset($filters['package_id'])) {
            $query->whereHas('session', fn (Builder $sessionQuery) =>
                $sessionQuery->where('package_id', $filters['package_id'])
            );
        }

        if (isset($filters['tanggal_from'])) {
            $query->whereDate('waktu_mulai', '>=', $filters['tanggal_from']);
        }
        if (isset($filters['tanggal_to'])) {
            $query->whereDate('waktu_mulai', '<=', $filters['tanggal_to']);
        }

        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $search = trim($filters['search']);
            $query->whereHas('siswa', fn (Builder $siswaQuery) =>
                $siswaQuery->where('nama_peserta_didik', 'like', "%{$search}%")
            );
        }

        return $query;
    }

    /**
     * Display all tryout results across all sessions.
     */
    public function allResults(Request $request)
    {
        $results = $this->resultsQuery($request)
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $sessions = Session::with('package')->orderBy('tanggal_mulai', 'desc')->get();
        $packages = Package::where('aktif', true)->orderBy('nama_paket')->get();

        return view('admin.tryout.results.index', compact('results', 'sessions', 'packages'));
    }
    
    /**
     * Display the specified result.
     */
    public function showResult(Result $result, TryoutResultDetailService $details)
    {
        $result->load(['session.package.subjects.questions', 'siswa', 'answers.question']);
        $detailNilaiMapel = $details->forResult($result);
        $analisisKelemahan = $details->weaknesses($result, $detailNilaiMapel);

        return view('admin.tryout.results.show', compact('result', 'detailNilaiMapel', 'analisisKelemahan'));
    }
    
    /**
     * Download every result matching the filters shown on the results page.
     */
    public function exportResults(Request $request, ExcelExportService $excel)
    {
        $results = $this->resultsQuery($request)->latest('created_at')->get();

        if ($results->isEmpty()) {
            return back()->with('error', 'Tidak ada hasil tryout yang cocok untuk diekspor.');
        }

        $sessionIds = $results->pluck('tryout_session_id')->unique()->values();
        $answerCounts = StudentAnswer::query()
            ->select('tryout_session_id', 'siswa_id', 'attempt_number')
            ->selectRaw('SUM(CASE WHEN adalah_benar = ? THEN 1 ELSE 0 END) AS jumlah_benar', [true])
            ->selectRaw('SUM(CASE WHEN adalah_benar = ? THEN 1 ELSE 0 END) AS jumlah_salah', [false])
            ->selectRaw('SUM(CASE WHEN adalah_benar IS NULL THEN 1 ELSE 0 END) AS jumlah_kosong')
            ->whereIn('tryout_session_id', $sessionIds)
            ->groupBy('tryout_session_id', 'siswa_id', 'attempt_number')
            ->get()
            ->keyBy(fn ($counts) => implode(':', [
                $counts->tryout_session_id,
                $counts->siswa_id,
                $counts->attempt_number,
            ]));

        $rows = $results->values()->map(function (Result $result, int $index) use ($answerCounts): array {
            $key = implode(':', [$result->tryout_session_id, $result->siswa_id, $result->attempt_number]);
            $counts = $answerCounts->get($key);

            return [
                $index + 1,
                $result->siswa?->nama_peserta_didik ?? 'Siswa dihapus',
                $result->siswa?->nis ?? '-',
                $result->siswa?->kelas ?? '-',
                $result->session?->nama_sesi ?? '-',
                $result->session?->package?->nama_paket ?? '-',
                $result->attempt_number,
                $result->nilai_total,
                $result->nilai_maksimal,
                $result->persentase,
                (int) ($counts?->jumlah_benar ?? 0),
                (int) ($counts?->jumlah_salah ?? 0),
                (int) ($counts?->jumlah_kosong ?? 0),
                $result->durasi_total_detik,
                $result->lulus ? 'Lulus' : 'Tidak lulus',
                $result->waktu_mulai?->format('Y-m-d H:i:s') ?? '-',
                $result->waktu_selesai?->format('Y-m-d H:i:s') ?? '-',
            ];
        })->all();

        return $excel->download(
            'hasil-tryout-' . now()->format('Y-m-d') . '.xlsx',
            'Hasil Tryout',
            [
                'No', 'Nama siswa', 'NIS', 'Kelas', 'Sesi', 'Paket', 'Percobaan',
                'Nilai total', 'Nilai maksimal', 'Persentase (%)', 'Benar', 'Salah',
                'Kosong', 'Durasi (detik)', 'Status', 'Waktu mulai', 'Waktu selesai',
            ],
            $rows,
        );
    }

    /**
     * Toggle package active status.
     */
    public function toggleStatus(Package $package)
    {
        $package->update(['aktif' => !$package->aktif]);

        return back()->with('success', 'Status paket berhasil diubah');
    }

    /**
     * Duplicate a package for new year.
     */
    public function duplicate(Package $package, Request $request)
    {
        $validated = $request->validate([
            'tahun_paket' => 'required|integer|min:2020|max:2030',
            'nama_paket' => 'required|string|max:255',
        ]);

        $newPackage = $package->replicate();
        $newPackage->uuid = Str::uuid();
        $newPackage->kode_paket = $package->kode_paket . '-' . $validated['tahun_paket'];
        $newPackage->nama_paket = $validated['nama_paket'];
        $newPackage->tahun_paket = $validated['tahun_paket'];
        $newPackage->aktif = false;
        $newPackage->save();

        // Duplicate subjects and questions
        foreach ($package->subjects as $subject) {
            $newSubject = $subject->replicate();
            $newSubject->uuid = Str::uuid();
            $newSubject->package_id = $newPackage->id;
            $newSubject->push();

            foreach ($subject->questions as $question) {
                $newQuestion = $question->replicate();
                $newQuestion->uuid = Str::uuid();
                $newQuestion->tryout_subject_id = $newSubject->id;
                $newQuestion->push();

                foreach ($question->options as $option) {
                    $newOption = $option->replicate();
                    $newOption->question_id = $newQuestion->id;
                    $newOption->save();
                }
            }
        }

        return redirect()->route('admin.tryout.packages.show', $newPackage)
            ->with('success', 'Paket berhasil diduplikasi');
    }
}
