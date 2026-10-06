<?php

namespace App\Http\Controllers\Admin\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\QuestionBank;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CbtBankController extends Controller
{
    /**
     * Display a listing of all question banks (admin view - all banks).
     */
    public function index(Request $request)
    {
        $query = QuestionBank::with(['mataPelajaran', 'semester', 'guru', 'questions']);

        // Filter by subject
        if ($request->filled('mata_pelajaran_id')) {
            $query->where('mata_pelajaran_id', $request->mata_pelajaran_id);
        }

        // Filter by semester
        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        // Filter by teacher
        if ($request->filled('guru_id')) {
            $query->where('guru_id', $request->guru_id);
        }

        // Filter by status
        if ($request->filled('aktif')) {
            $query->where('aktif', $request->aktif === 'true');
        }

        // Filter by difficulty
        if ($request->filled('tingkat_kesulitan')) {
            $query->where('tingkat_kesulitan', $request->tingkat_kesulitan);
        }

        // Search by name or code
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_bank', 'like', "%{$search}%")
                  ->orWhere('kode_bank', 'like', "%{$search}%");
            });
        }

        $banks = $query->withCount('questions')
                       ->latest()
                       ->paginate(20);

        $mataPelajarans = MataPelajaran::where('aktif', true)->orderBy('kelompok')->orderBy('urutan')->get();
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get();

        return view('admin.cbt.banks.index', compact('banks', 'mataPelajarans', 'semesters', 'gurus'));
    }

    /**
     * Show the form for creating a new question bank.
     */
    public function create()
    {
        $mataPelajarans = MataPelajaran::where('aktif', true)->orderBy('kelompok')->orderBy('urutan')->get();
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get();

        return view('admin.cbt.banks.create', compact('mataPelajarans', 'semesters', 'gurus'));
    }

    /**
     * Store a newly created question bank.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'semester_id' => 'required|exists:semesters,id',
            'guru_id' => 'required|exists:users,id',
            'kode_bank' => 'required|string|max:50|unique:cbt_question_banks,kode_bank',
            'nama_bank' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tingkat_kesulitan' => 'required|in:mudah,sedang,sulit',
            'kategori_soal' => 'nullable|string|max:100',
            'aktif' => 'boolean',
        ]);

        $bank = QuestionBank::create([
            'uuid' => Str::uuid(),
            'mata_pelajaran_id' => $validated['mata_pelajaran_id'],
            'semester_id' => $validated['semester_id'],
            'guru_id' => $validated['guru_id'],
            'kode_bank' => $validated['kode_bank'],
            'nama_bank' => $validated['nama_bank'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'tingkat_kesulitan' => $validated['tingkat_kesulitan'],
            'kategori_soal' => $validated['kategori_soal'] ?? null,
            'jumlah_soal' => 0,
            'aktif' => $validated['aktif'] ?? true,
        ]);

        return redirect()->route('admin.cbt.banks.show', $bank)
            ->with('success', 'Bank soal berhasil dibuat');
    }

    /**
     * Display the specified question bank.
     */
    public function show(QuestionBank $bank)
    {
        $bank->load(['mataPelajaran', 'semester', 'guru', 'questions.options']);
        
        $testSessions = $bank->testSessions()
                           ->with(['guru', 'rombel'])
                           ->latest()
                           ->paginate(10);

        return view('admin.cbt.banks.show', compact('bank', 'testSessions'));
    }

    /**
     * Show the form for editing the question bank.
     */
    public function edit(QuestionBank $bank)
    {
        $mataPelajarans = MataPelajaran::where('aktif', true)->orderBy('kelompok')->orderBy('urutan')->get();
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get();

        return view('admin.cbt.banks.edit', compact('bank', 'mataPelajarans', 'semesters', 'gurus'));
    }

    /**
     * Update the question bank.
     */
    public function update(Request $request, QuestionBank $bank)
    {
        $validated = $request->validate([
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'semester_id' => 'required|exists:semesters,id',
            'guru_id' => 'required|exists:users,id',
            'kode_bank' => 'required|string|max:50|unique:cbt_question_banks,kode_bank,' . $bank->id,
            'nama_bank' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tingkat_kesulitan' => 'required|in:mudah,sedang,sulit',
            'kategori_soal' => 'nullable|string|max:100',
            'aktif' => 'boolean',
        ]);

        $bank->update($validated);

        return redirect()->route('admin.cbt.banks.show', $bank)
            ->with('success', 'Bank soal berhasil diperbarui');
    }

    /**
     * Remove the question bank.
     */
    public function destroy(QuestionBank $bank)
    {
        // Check if bank is used in any test sessions
        if ($bank->testSessions()->count() > 0) {
            return back()->with('error', 'Bank soal tidak dapat dihapus karena sudah digunakan dalam sesi ujian');
        }

        $bank->delete();

        return redirect()->route('admin.cbt.banks.index')
            ->with('success', 'Bank soal berhasil dihapus');
    }

    /**
     * Toggle bank active status.
     */
    public function toggleStatus(QuestionBank $bank)
    {
        $bank->update(['aktif' => !$bank->aktif]);

        return back()->with('success', 'Status bank soal berhasil diubah');
    }

    /**
     * Get statistics for dashboard.
     */
    public function statistics()
    {
        $stats = [
            'total_banks' => QuestionBank::count(),
            'active_banks' => QuestionBank::where('aktif', true)->count(),
            'total_questions' => \App\Models\Cbt\Question::count(),
            'total_sessions' => \App\Models\Cbt\TestSession::count(),
            'sessions_by_status' => \App\Models\Cbt\TestSession::selectRaw('status, count(*) as count')
                                                                ->groupBy('status')
                                                                ->pluck('count', 'status'),
            'recent_sessions' => \App\Models\Cbt\TestSession::with(['questionBank', 'guru'])
                                                           ->latest()
                                                           ->take(5)
                                                           ->get(),
        ];

        return response()->json($stats);
    }
}
