<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\StoreCbtSessionRequest;
use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Services\CbtExamService;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Support\Facades\DB;

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

    public function store(StoreCbtSessionRequest $request)
    {
        $data = $request->validated();
        $session = DB::transaction(function () use ($data) {
            CbtQuestionBank::whereKey($data['bank_id'])->lockForUpdate()->firstOrFail();
            return CbtTestSession::create($data + ['guru_id' => auth()->id(), 'status' => 'draft']);
        });
        if (config('simantap.exam_engine_cbt', true) && $session->bank) {
            $mapper = app(LegacyExamMapper::class);
            $mapper->sesiCbt($mapper->bankCbt($session->bank), $session);
        }
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
