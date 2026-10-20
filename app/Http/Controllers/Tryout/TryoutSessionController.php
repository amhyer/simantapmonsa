<?php

namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Models\Tryout\Session;
use App\Models\Tryout\Package;
use App\Models\Semester;
use App\Models\Rombel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TryoutSessionController extends Controller
{
    public function index()
    {
        $sessions = Session::with(['package', 'semester', 'guru', 'rombel'])
                          ->where('guru_id', auth()->id())
                          ->latest()
                          ->paginate(20);
        
        return view('tryout.sessions.index', compact('sessions'));
    }

    public function create()
    {
        $packages = Package::where('aktif', true)
                          ->with(['subjects.mataPelajaran'])
                          ->get();
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        $rombels = Rombel::with('guru')->orderBy('nama_rombel')->get();
        
        return view('tryout.sessions.create', compact('packages', 'semesters', 'rombels'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:tryout_packages,id',
            'semester_id' => 'required|exists:semesters,id',
            'nama_sesi' => 'required|string|max:255',
            'kelas' => 'nullable|string|max:50',
            'rombel_id' => 'nullable|exists:rombel,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        $session = Session::create([
            'uuid' => Str::uuid(),
            'kode_sesi' => 'TRY-' . strtoupper(Str::random(8)),
            'package_id' => $validated['package_id'],
            'semester_id' => $validated['semester_id'],
            'guru_id' => auth()->id(),
            'nama_sesi' => $validated['nama_sesi'],
            'kelas' => $validated['kelas'] ?? null,
            'rombel_id' => $validated['rombel_id'] ?? null,
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'status' => 'draft',
        ]);

        return redirect()->route('tryout.sessions.show', $session)
            ->with('success', 'Sesi tryout berhasil dibuat');
    }

    public function show(Session $session)
    {
        $this->authorize('view', $session);
        $session->load(['package.subjects.mataPelajaran', 'semester', 'guru', 'rombel']);
        $results = $session->results()->with('siswa')->paginate(20);
        
        return view('tryout.sessions.show', compact('session', 'results'));
    }

    public function publish(Session $session)
    {
        $this->authorize('publish', $session);
        $session->update(['status' => 'published']);
        return back()->with('success', 'Sesi tryout dipublikasikan');
    }

    public function start(Session $session)
    {
        $this->authorize('start', $session);
        $session->update(['status' => 'ongoing']);
        return back()->with('success', 'Sesi tryout dimulai');
    }

    public function complete(Session $session)
    {
        $this->authorize('complete', $session);
        $session->update(['status' => 'completed']);
        return back()->with('success', 'Sesi tryout selesai');
    }

    public function monitoring(Session $session)
    {
        $this->authorize('monitoring', $session);
        $session->load(['package.subjects', 'results.siswa']);
        
        $activeStudents = $session->results()
                                ->whereNull('waktu_selesai')
                                ->with('siswa')
                                ->get();

        $completedStudents = $session->results()
                                   ->whereNotNull('waktu_selesai')
                                   ->with('siswa')
                                   ->orderBy('nilai_total', 'desc')
                                   ->get();

        return view('tryout.sessions.monitoring', compact('session', 'activeStudents', 'completedStudents'));
    }

    public function apiMonitoring(Session $session)
    {
        $this->authorize('monitoring', $session);
        $active = $session->results()
                         ->whereNull('waktu_selesai')
                         ->with('siswa')
                         ->get()
                         ->map(function ($result) {
                             return [
                                 'id' => $result->id,
                                 'siswa' => $result->siswa->nama_peserta_didik,
                                 'waktu_mulai' => $result->waktu_mulai,
                                 'durasi_sudah' => now()->diffInSeconds($result->waktu_mulai),
                             ];
                         });

        return response()->json(['active_students' => $active]);
    }
}
