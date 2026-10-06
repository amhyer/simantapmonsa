<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\TestSession;
use App\Models\Cbt\QuestionBank;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Rombel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CbtTestSessionController extends Controller
{
    public function index()
    {
        $sessions = TestSession::with(['questionBank', 'mataPelajaran', 'semester'])
                               ->where('guru_id', auth()->id())
                               ->latest()
                               ->paginate(20);
        return view('cbt.sessions.index', compact('sessions'));
    }

    public function create()
    {
        $banks = QuestionBank::where('guru_id', auth()->id())
                            ->where('aktif', true)
                            ->with('mataPelajaran', 'semester')
                            ->get();
        return view('cbt.sessions.create', compact('banks'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_bank_id' => 'required|exists:cbt_question_banks,id',
            'nama_sesi' => 'required|string|max:255',
            'kelas' => 'nullable|string|max:50',
            'rombel_id' => 'nullable|exists:rombel,id',
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
            'guru_id' => auth()->id(),
            'mata_pelajaran_id' => $bank->mata_pelajaran_id,
            'semester_id' => $bank->semester_id,
            ...$validated,
            'status' => 'draft',
        ]);

        return redirect()->route('cbt.sessions.show', $session)
            ->with('success', 'Sesi ujian berhasil dibuat');
    }

    public function show(TestSession $session)
    {
        $session->load(['questionBank', 'mataPelajaran', 'semester', 'guru', 'rombel']);
        $results = $session->testResults()->with('siswa')->paginate(20);
        return view('cbt.sessions.show', compact('session', 'results'));
    }

    public function publish(TestSession $session)
    {
        $session->update(['status' => 'published']);
        return back()->with('success', 'Sesi ujian dipublikasikan');
    }

    public function start(TestSession $session)
    {
        $session->update(['status' => 'ongoing']);
        return back()->with('success', 'Sesi ujian dimulai');
    }

    public function complete(TestSession $session)
    {
        $session->update(['status' => 'completed']);
        return back()->with('success', 'Sesi ujian selesai');
    }

    // Live monitoring
    public function monitoring(TestSession $session)
    {
        $activeStudents = $session->testResults()
                                ->whereNull('waktu_selesai')
                                ->with('siswa')
                                ->get();

        $completedStudents = $session->testResults()
                                   ->whereNotNull('waktu_selesai')
                                   ->with('siswa')
                                   ->get();

        return view('cbt.sessions.monitoring', compact('session', 'activeStudents', 'completedStudents'));
    }

    // API: Get active students for live monitoring
    public function apiMonitoring(TestSession $session)
    {
        $active = $session->testResults()
                         ->whereNull('waktu_selesai')
                         ->with('siswa')
                         ->get()
                         ->map(function ($result) {
                             return [
                                 'id' => $result->id,
                                 'siswa' => $result->siswa->nama_peserta_didik,
                                 'waktu_mulai' => $result->waktu_mulai,
                                 'durasi_sudah' => now()->diffInSeconds($result->waktu_mulai),
                                 'integrity_score' => $result->integrity_score,
                             ];
                         });

        return response()->json(['active_students' => $active]);
    }
}
