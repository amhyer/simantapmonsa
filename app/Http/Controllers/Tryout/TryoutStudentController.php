<?php

namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Models\Tryout\Result;
use App\Models\Tryout\Session;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tryout\TryoutQuestion;
use App\Models\Siswa;
use App\Services\TryoutEngineBridge;
use App\Services\TryoutResultDetailService;
use App\Services\TryoutScoring;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TryoutStudentController extends Controller
{
    public function __construct(private TryoutEngineBridge $bridge) {}

    private function pakaiEngineBaru(): bool
    {
        return (bool) config('simantap.exam_engine_tryout', true);
    }

    public function index()
    {
        $siswa = $this->siswaSaya();

        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan');
        }

        $availableSessions = Session::where('status', 'ongoing')
                                    ->where('tanggal_mulai', '<=', now())
                                    ->where('tanggal_selesai', '>=', now())
                                    ->where(function ($q) use ($siswa) {
                                        $q->whereNull('kelas')
                                          ->orWhere('kelas', $siswa->kelas)
                                          ->orWhereHas('rombel', function ($q) use ($siswa) {
                                              $q->where('id', $siswa->rombel_id);
                                          });
                                    })
                                    ->with(['package.subjects.mataPelajaran'])
                                    ->get();

        $myResults = Result::where('siswa_id', $siswa->id)
                          ->with(['session.package', 'siswa'])
                          ->latest()
                          ->get();

        return view('tryout.student.index', compact('availableSessions', 'myResults'));
    }

    public function start(Session $session)
    {
        $siswa = $this->siswaSaya();

        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan');
        }

        if (!$session->canStudentTake($siswa->id)) {
            return back()->with('error', 'Anda tidak dapat mengikuti tryout ini');
        }

        // Lanjutkan percobaan yang belum selesai; kalau tidak ada, buat baru.
        if ($this->pakaiEngineBaru()) {
            $result = $this->bridge->mulai($session, $siswa);
        } else {
            $result = Result::where('tryout_session_id', $session->id)
                            ->where('siswa_id', $siswa->id)
                            ->whereNull('waktu_selesai')
                            ->latest('id')
                            ->first();

            if (!$result) {
                $attemptNumber = (int) Result::where('tryout_session_id', $session->id)
                            ->where('siswa_id', $siswa->id)
                            ->max('attempt_number') + 1;

                $result = Result::create([
                    'uuid' => Str::uuid(),
                    'tryout_session_id' => $session->id,
                    'siswa_id' => $siswa->id,
                    'attempt_number' => $attemptNumber,
                    'nilai_total' => 0,
                    'nilai_maksimal' => 0,
                    'persentase' => 0,
                    'lulus' => false,
                    'detail_nilai_mapel' => [],
                    'waktu_mulai' => now(),
                    'waktu_selesai' => null,
                    'durasi_total_detik' => 0,
                ]);
            }
        }

        $subjects = $session->package->subjects()->with(['questions.options'])->get();
        $existingAnswers = $result->answers()
            ->pluck('jawaban', 'question_id')
            ->all();

        return view('tryout.student.take', compact('session', 'result', 'subjects', 'existingAnswers'));
    }

    public function submitAnswer(Request $request, Result $result)
    {
        $this->authorize('answer', $result);

        if ($result->waktu_selesai !== null) {
            return response()->json(['success' => false, 'message' => 'Tryout sudah diselesaikan'], 409);
        }

        if (!$result->session->isOngoing()) {
            return response()->json(['success' => false, 'message' => 'Sesi tryout sudah berakhir'], 409);
        }

        $validated = $request->validate([
            'subject_id' => 'required|exists:tryout_subjects,id',
            'question_id' => 'required|exists:tryout_questions,id',
            'jawaban' => 'required|string',
        ]);

        // Soal & mapel harus milik paket sesi ini (cegah menyisipkan ID asing).
        $question = TryoutQuestion::with('options')
            ->whereKey($validated['question_id'])
            ->where('tryout_subject_id', $validated['subject_id'])
            ->whereHas('subject', fn ($q) => $q->where('package_id', $result->session->package_id))
            ->first();

        if (!$question) {
            return response()->json(['success' => false, 'message' => 'Soal bukan bagian dari tryout ini'], 422);
        }

        $this->simpanJawaban($result, $question, $validated);

        // Jangan pernah bocorkan kunci/poin selama ujian berjalan.
        return response()->json(['success' => true]);
    }

    /**
     * Simpan jawaban ke engine yang aktif. Penilaian selalu di server.
     */
    private function simpanJawaban(Result $result, TryoutQuestion $question, array $validated): void
    {
        if ($this->pakaiEngineBaru()) {
            $this->bridge->simpanJawabanSoal($result, $question, $validated['jawaban']);

            return;
        }

        [$isCorrect, $poin] = TryoutScoring::grade($question, $validated['jawaban']);

        StudentAnswer::updateOrCreate(
            [
                'tryout_session_id' => $result->tryout_session_id,
                'siswa_id' => $result->siswa_id,
                'question_id' => $question->id,
                'attempt_number' => $result->attempt_number,
            ],
            [
                'uuid' => Str::uuid(),
                'tryout_subject_id' => $validated['subject_id'],
                'jawaban' => $validated['jawaban'],
                'adalah_benar' => $isCorrect,
                'poin_diperoleh' => $poin,
                'waktu_mulai_jawab' => now(),
                'waktu_selesai_jawab' => now(),
                'durasi_detik' => 0,
            ]
        );
    }

    public function complete(Request $request, Result $result)
    {
        $this->authorize('complete', $result);

        if ($result->waktu_selesai) {
            return back()->with('error', 'Tryout sudah diselesaikan');
        }

        if ($this->pakaiEngineBaru()) {
            $this->bridge->selesaikan($result, (float) $result->session->package->passing_grade);

            return redirect()->route('tryout.student.result', $result->fresh())
                ->with('success', 'Tryout berhasil diselesaikan');
        }

        $subjects = $result->session->package->subjects()->with(['questions'])->get();
        $detailNilaiMapel = [];
        $totalScore = 0;
        $maxScore = 0;

        foreach ($subjects as $subject) {
            $answers = StudentAnswer::where('tryout_session_id', $result->tryout_session_id)
                                   ->where('siswa_id', $result->siswa_id)
                                   ->where('attempt_number', $result->attempt_number)
                                   ->where('tryout_subject_id', $subject->id)
                                   ->get();

            $subjectScore = $answers->sum('poin_diperoleh');
            $subjectMax = $subject->questions->sum('poin');
            $subjectPercentage = $subjectMax > 0 ? ($subjectScore / $subjectMax * 100) : 0;

            $detailNilaiMapel[] = [
                'mata_pelajaran_id' => $subject->mata_pelajaran_id,
                'nama_mapel' => $subject->mataPelajaran?->nama ?? 'Unknown',
                'skor' => $subjectScore,
                'maksimal' => $subjectMax,
                'persentase' => round($subjectPercentage, 2),
                'lulus' => $subjectPercentage >= $subject->passing_grade_mapel,
            ];

            $totalScore += $subjectScore * $subject->bobot;
            $maxScore += $subjectMax * $subject->bobot;
        }

        $persentase = $maxScore > 0 ? ($totalScore / $maxScore * 100) : 0;
        $lulus = $persentase >= $result->session->package->passing_grade;

        $ranking = Result::where('tryout_session_id', $result->tryout_session_id)
                        ->whereNotNull('waktu_selesai')
                        ->where('nilai_total', '>', $totalScore)
                        ->count() + 1;

        $weakSubjects = collect($detailNilaiMapel)
            ->where('persentase', '<', 60)
            ->sortBy('persentase')
            ->take(3)
            ->values()
            ->toArray();

        $result->update([
            'nilai_total' => $totalScore,
            'nilai_maksimal' => $maxScore,
            'persentase' => $persentase,
            'lulus' => $lulus,
            'peringkat' => $ranking,
            'detail_nilai_mapel' => $detailNilaiMapel,
            'analisis_kelemahan' => $weakSubjects,
            'waktu_selesai' => now(),
            'durasi_total_detik' => now()->diffInSeconds($result->waktu_mulai),
        ]);

        return redirect()->route('tryout.student.result', $result)
            ->with('success', 'Tryout berhasil diselesaikan');
    }

    public function result(Result $result, TryoutResultDetailService $details)
    {
        $this->authorize('view', $result);

        $result->load(['session.package.subjects.mataPelajaran', 'siswa']);
        $detailNilaiMapel = $details->forResult($result);
        $analisisKelemahan = $details->weaknesses($result, $detailNilaiMapel);

        return view('tryout.student.result', compact('result', 'detailNilaiMapel', 'analisisKelemahan'));
    }

    private function siswaSaya(): ?Siswa
    {
        return Siswa::where('nis', auth()->user()->nama_pengguna)->first();
    }
}
