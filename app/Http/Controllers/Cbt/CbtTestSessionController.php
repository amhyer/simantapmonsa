<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Services\CbtExamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CbtTestSessionController extends Controller
{
    public function __construct(private CbtExamService $exam) {}

    public function index()
    {
        $sessions = CbtTestSession::where('guru_id', auth()->id())->withCount('results')->latest()->paginate(20);
        return view('cbt.sessions.index', compact('sessions'));
    }

    public function create()
    {
        $banks = CbtQuestionBank::where('guru_id', auth()->id())->orderBy('title')->get();
        $classes = Siswa::where('guru_id', auth()->id())->where('aktif', true)->whereNull('archived_at')->distinct()->orderBy('kelas')->pluck('kelas');
        return view('cbt.sessions.create', compact('banks', 'classes'));
    }

    public function store(Request $request)
    {
        $classes = Siswa::where('guru_id', auth()->id())->where('aktif', true)->whereNull('archived_at')->distinct()->pluck('kelas')->all();
        $data = $request->validate([
            'bank_id' => ['required', 'integer', Rule::exists('cbt_question_banks', 'id')->where('guru_id', auth()->id())],
            'title' => 'required|string|max:200', 'kelas' => ['required', 'string', Rule::in($classes)],
            'opens_at' => 'required|date', 'closes_at' => 'required|date|after:opens_at|after:now',
            'duration_minutes' => 'required|integer|min:1|max:300',
        ]);
        $session = DB::transaction(function () use ($data) {
            CbtQuestionBank::whereKey($data['bank_id'])->lockForUpdate()->firstOrFail();
            return CbtTestSession::create($data + ['guru_id' => auth()->id(), 'status' => 'draft']);
        });
        return redirect()->route('cbt.sessions.show', $session)->with('success', 'Draf sesi dibuat.');
    }

    public function show(CbtTestSession $session)
    {
        $this->authorize('view', $session);
        return view('cbt.sessions.show', compact('session'));
    }

    public function publish(CbtTestSession $session)
    {
        $this->authorize('publish', $session);
        $this->exam->publish($session);
        return redirect()->route('cbt.sessions.show', $session)->with('success', 'Sesi dipublikasikan; soal dan peserta telah dibekukan.');
    }

    public function start(CbtTestSession $session)
    {
        $this->authorize('start', $session);
        $this->exam->open($session);
        return redirect()->route('cbt.sessions.show', $session)->with('success', 'Sesi dibuka. Siswa dapat mulai sesuai jadwal.');
    }

    public function complete(CbtTestSession $session)
    {
        $this->authorize('complete', $session);
        $this->exam->complete($session);
        return redirect()->route('cbt.sessions.show', $session)->with('success', 'Sesi ditutup dan jawaban tersimpan dinilai.');
    }

    public function monitoring(CbtTestSession $session)
    {
        $this->authorize('monitoring', $session);
        $results = $session->results()->with('siswa')->withCount('violations')->orderBy('id')->paginate(50);
        return view('cbt.sessions.monitoring', compact('session', 'results'));
    }

    public function apiMonitoring(CbtTestSession $session)
    {
        $this->authorize('monitoring', $session);
        $results = $session->results()->with('siswa')->withCount('violations')->orderBy('id')->paginate(50);
        return response()->json([
            'status' => $session->status,
            'participants' => count($session->participant_ids ?? []),
            'results' => $results->through(fn ($r) => [
                'id' => $r->id, 'name' => $r->siswa?->nama_peserta_didik ?? 'Siswa dihapus',
                'answered' => count($r->answers ?? []), 'deadline' => $r->deadline_at?->toIso8601String(),
                'submitted' => (bool) $r->submitted_at, 'score' => $r->score, 'violations' => $r->violations_count,
            ]),
        ]);
    }
}
