<?php

namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tryout\StoreTryoutSessionRequest;
use App\Models\Tryout\Session;
use App\Models\Tryout\Package;
use App\Models\Semester;
use App\Models\Rombel;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Support\Facades\Cache;
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

    public function store(StoreTryoutSessionRequest $request)
    {
        $validated = $request->validated();

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

        if (config('simantap.exam_engine_tryout', true)) {
            app(LegacyExamMapper::class)->pastikanSesiTryout($session);
        }

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
        Cache::forget("tryout:session:{$session->id}:monitoring");
        return back()->with('success', 'Sesi tryout dipublikasikan');
    }

    public function start(Session $session)
    {
        $this->authorize('start', $session);
        $session->update(['status' => 'ongoing']);
        Cache::forget("tryout:session:{$session->id}:monitoring");
        return back()->with('success', 'Sesi tryout dimulai');
    }

    public function complete(Session $session)
    {
        $this->authorize('complete', $session);
        $session->update(['status' => 'completed']);
        Cache::forget("tryout:session:{$session->id}:monitoring");
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

        // ATURAN PROYEK: Cache HANYA menyimpan array/skalar murni (bukan Model Eloquent).
        $active = Cache::remember("tryout:session:{$session->id}:monitoring", 3, function () use ($session) {
            return $session->results()
                ->whereNull('waktu_selesai')
                ->with('siswa')
                ->get()
                ->map(function ($result) {
                    return [
                        'id' => (int) $result->id,
                        'siswa' => (string) ($result->siswa?->nama_peserta_didik ?? 'Siswa dihapus'),
                        'waktu_mulai' => $result->waktu_mulai?->toIso8601String(),
                        'durasi_sudah' => $result->waktu_mulai ? (int) now()->diffInSeconds($result->waktu_mulai) : 0,
                    ];
                })
                ->all();
        });

        return response()->json(['active_students' => $active]);
    }
}
