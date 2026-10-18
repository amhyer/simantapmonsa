<?php

namespace App\Http\Controllers\Admin\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\TestSession;
use App\Models\Cbt\QuestionBank;
use App\Models\Semester;
use App\Models\Rombel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CbtSessionController extends Controller
{
    /**
     * Display a listing of all test sessions (admin view).
     */
    public function index(Request $request)
    {
        $query = TestSession::with(['questionBank', 'mataPelajaran', 'semester', 'guru', 'rombel']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by teacher
        if ($request->filled('guru_id')) {
            $query->where('guru_id', $request->guru_id);
        }

        // Filter by date range
        if ($request->filled('tanggal_mulai_from')) {
            $query->whereDate('tanggal_mulai', '>=', $request->tanggal_mulai_from);
        }
        if ($request->filled('tanggal_mulai_to')) {
            $query->whereDate('tanggal_mulai', '<=', $request->tanggal_mulai_to);
        }

        // Filter by class
        if ($request->filled('kelas')) {
            $query->where('kelas', $request->kelas);
        }

        // Search by session name or code
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_sesi', 'like', "%{$search}%")
                  ->orWhere('kode_sesi', 'like', "%{$search}%");
            });
        }

        $sessions = $query->withCount('testResults')
                         ->latest()
                         ->paginate(20);

        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get();
        $kelasList = TestSession::distinct()->orderBy('kelas')->pluck('kelas')->filter();

        return view('admin.cbt.sessions.index', compact('sessions', 'gurus', 'kelasList'));
    }

    /**
     * Show the form for creating a new test session.
     */
    public function create()
    {
        $banks = QuestionBank::where('aktif', true)
                            ->with(['mataPelajaran', 'semester'])
                            ->has('questions')
                            ->get();
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        $rombels = Rombel::with('guru')->orderBy('nama_rombel')->get();
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get();

        return view('admin.cbt.sessions.create', compact('banks', 'semesters', 'rombels', 'gurus'));
    }

    /**
     * Store a newly created test session.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_bank_id' => 'required|exists:cbt_question_banks,id',
            'nama_sesi' => 'required|string|max:255',
            'kelas' => 'nullable|string|max:50',
            'rombel_id' => 'nullable|exists:rombel,id',
            'guru_id' => 'required|exists:users,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'durasi_menit' => 'required|integer|min:1',
            'jumlah_soal_tampil' => 'required|integer|min:1',
            'acak_soal' => 'boolean',
            'acak_opsi' => 'boolean',
            'tampilkan_nilai' => 'boolean',
            'boleh_ulang' => 'boolean',
            'maksimal_percobaan' => 'nullable|integer|min:1',
            'passing_grade' => 'required|numeric|min:0|max:100',
        ]);

        $bank = QuestionBank::findOrFail($validated['question_bank_id']);

        $session = TestSession::create([
            'uuid' => Str::uuid(),
            'kode_sesi' => 'CBT-' . strtoupper(Str::random(8)),
            'question_bank_id' => $validated['question_bank_id'],
            'mata_pelajaran_id' => $bank->mata_pelajaran_id,
            'semester_id' => $bank->semester_id,
            'guru_id' => $validated['guru_id'],
            'nama_sesi' => $validated['nama_sesi'],
            'kelas' => $validated['kelas'] ?? null,
            'rombel_id' => $validated['rombel_id'] ?? null,
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'durasi_menit' => $validated['durasi_menit'],
            'jumlah_soal_tampil' => $validated['jumlah_soal_tampil'],
            'acak_soal' => $validated['acak_soal'] ?? false,
            'acak_opsi' => $validated['acak_opsi'] ?? false,
            'tampilkan_nilai' => $validated['tampilkan_nilai'] ?? false,
            'boleh_ulang' => $validated['boleh_ulang'] ?? false,
            'maksimal_percobaan' => $validated['maksimal_percobaan'] ?? 1,
            'passing_grade' => $validated['passing_grade'],
            'status' => 'draft',
        ]);

        return redirect()->route('admin.cbt.sessions.show', $session)
            ->with('success', 'Sesi ujian berhasil dibuat');
    }

    /**
     * Display the specified test session.
     */
    public function show(TestSession $session)
    {
        $session->load(['questionBank.questions', 'mataPelajaran', 'semester', 'guru', 'rombel']);
        
        $results = $session->testResults()
                         ->with('siswa')
                         ->latest()
                         ->paginate(30);

        // Statistics
        $stats = [
            'total_participants' => $session->testResults()->count(),
            'completed' => $session->testResults()->whereNotNull('waktu_selesai')->count(),
            'passed' => $session->testResults()->where('lulus', true)->count(),
            'average_score' => $session->testResults()->whereNotNull('waktu_selesai')->avg('persentase') ?? 0,
        ];

        return view('admin.cbt.sessions.show', compact('session', 'results', 'stats'));
    }

    /**
     * Update session status.
     */
    public function updateStatus(Request $request, TestSession $session)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,published,ongoing,completed,archived',
        ]);

        $session->update(['status' => $validated['status']]);

        return back()->with('success', 'Status sesi berhasil diubah');
    }

    /**
     * Remove the test session.
     */
    public function destroy(TestSession $session)
    {
        // Check if there are any results
        if ($session->testResults()->count() > 0) {
            return back()->with('error', 'Sesi ujian tidak dapat dihapus karena sudah ada siswa yang mengerjakan');
        }

        $session->delete();

        return redirect()->route('admin.cbt.sessions.index')
            ->with('success', 'Sesi ujian berhasil dihapus');
    }

    /**
     * Export results to Excel/PDF.
     */
    public function exportResults(TestSession $session, $format = 'excel')
    {
        $session->load(['testResults.siswa', 'questionBank']);
        
        // Implementation depends on your export library
        // This is a placeholder
        return back()->with('info', 'Fitur export akan segera tersedia');
    }

    /**
     * Force complete all ongoing sessions.
     */
    public function forceComplete(TestSession $session)
    {
        $ongoingResults = $session->testResults()
                                 ->whereNull('waktu_selesai')
                                 ->get();

        foreach ($ongoingResults as $result) {
            // Calculate and save score
            $answers = $result->studentAnswers;
            // ... scoring logic
            $result->update([
                'waktu_selesai' => now(),
                'submitted_by' => 'admin',
            ]);
        }

        $session->update(['status' => 'completed']);

        return back()->with('success', 'Semua sesi yang sedang berjalan telah diselesaikan');
    }
}
