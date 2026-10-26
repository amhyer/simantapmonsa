<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Services\CbtExamService;
use App\Services\CbtScoring;
use Illuminate\Http\Request;

class CbtStudentController extends Controller
{
    public function __construct(private CbtExamService $exam) {}

    private function student(): Siswa
    {
        $user = auth()->user();
        $siswa = Siswa::where('nis', $user->nama_pengguna)->first();
        if (!$siswa) {
            $linked = Siswa::whereIn('id', $user->terhubung_dengan ?? [])->limit(2)->get();
            abort_unless($linked->count() === 1, 403, 'Akun harus terhubung ke tepat satu siswa.');
            $siswa = $linked->first();
        }
        abort_unless($siswa->aktif && !$siswa->archived_at, 403);
        return $siswa;
    }

    private function own(CbtResult $result): void
    {
        $this->authorize('answer', $result);
    }

    public function index()
    {
        $student = $this->student();
        $sessions = app(\App\Services\Exam\CbtStudentReader::class)->sesiSiswa($student);
        return view('cbt.student.index', compact('sessions'));
    }

    /** GET hanya membuka petunjuk atau melanjutkan percobaan; tidak membuat data. */
    public function start(CbtTestSession $session)
    {
        $student = $this->student();
        abort_unless($this->exam->eligible($session, $student) && $session->status !== 'draft', 403);
        $result = $session->results()->where('siswa_id', $student->id)->first();
        if (!$result) {
            return view('cbt.student.start', compact('session'));
        }
        $this->own($result);
        $result = $this->exam->syncResult($result);
        if ($result->submitted_at) {
            return redirect()->route('cbt.student.result', $result);
        }
        $questions = CbtScoring::publicQuestions($session->question_snapshot ?? []);
        return view('cbt.student.exam', compact('session', 'result', 'questions'));
    }

    public function begin(CbtTestSession $session)
    {
        $result = $this->exam->begin($session, $this->student(), (int) auth()->id());
        return redirect()->route($result->submitted_at ? 'cbt.student.result' : 'cbt.student.start', $result->submitted_at ? $result : $session);
    }

    public function submitAnswer(Request $request, CbtResult $result)
    {
        $this->own($result);
        $data = $request->validate(['question_id' => 'required|integer|min:1', 'answer' => 'required|in:A,B,C,D']);
        $accepted = $this->exam->answer($result, $data['question_id'], $data['answer']);
        return response()->json([
            'saved' => $accepted, 'server_time' => now()->toIso8601String(),
            'message' => $accepted ? 'Jawaban tersimpan.' : 'Waktu habis atau ujian telah dikumpulkan.',
            'result_url' => route('cbt.student.result', $result),
        ], $accepted ? 200 : 409);
    }

    public function submit(Request $request, CbtResult $result)
    {
        $this->own($result);
        $data = $request->validate(['answers' => 'sometimes|array|max:200', 'answers.*' => 'required|in:A,B,C,D']);
        $this->exam->submit($result, $data['answers'] ?? []);
        return redirect()->route('cbt.student.result', $result)->with('success', 'Ujian telah dikumpulkan.');
    }

    public function result(CbtResult $result)
    {
        $this->authorize('view', $result);
        $result = $this->exam->syncResult($result);
        $session = $result->session;
        return view('cbt.student.result', compact('result', 'session'));
    }
}
