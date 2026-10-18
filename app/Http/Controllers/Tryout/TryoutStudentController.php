<?php

namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Models\Tryout\Session;
use App\Models\Tryout\Result;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tryout\TryoutQuestion;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TryoutStudentController extends Controller
{
    public function index()
    {
        $siswa = Siswa::where('nis', auth()->user()->nama_pengguna)->first();
        
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
        $siswa = Siswa::where('nis', auth()->user()->nama_pengguna)->first();

        if (!$session->canStudentTake($siswa->id)) {
            return back()->with('error', 'Anda tidak dapat mengikuti tryout ini');
        }

        // Create result record
        $result = Result::create([
            'uuid' => Str::uuid(),
            'tryout_session_id' => $session->id,
            'siswa_id' => $siswa->id,
            'attempt_number' => 1,
            'nilai_total' => 0,
            'nilai_maksimal' => 0,
            'persentase' => 0,
            'lulus' => false,
            'detail_nilai_mapel' => [],
            'waktu_mulai' => now(),
            'waktu_selesai' => null,
            'durasi_total_detik' => 0,
        ]);

        // Get all subjects with questions
        $subjects = $session->package->subjects()->with(['questions.options'])->get();

        return view('tryout.student.take', compact('session', 'result', 'subjects'));
    }

    public function submitAnswer(Request $request, Result $result)
    {
        $validated = $request->validate([
            'subject_id' => 'required|exists:tryout_subjects,id',
            'question_id' => 'required|exists:tryout_questions,id',
            'jawaban' => 'required|string',
        ]);

        $question = TryoutQuestion::with('options')->findOrFail($validated['question_id']);
        
        // Check if correct
        $isCorrect = false;
        $poin = 0;
        
        if ($question->tipe_soal === 'pilihan_ganda') {
            $correctOption = $question->options->where('adalah_benar', true)->first();
            $isCorrect = $correctOption && $validated['jawaban'] === $correctOption->huruf;
        } elseif ($question->tipe_soal === 'benar_salah') {
            $isCorrect = strtolower($validated['jawaban']) === 'benar';
        }
        
        if ($isCorrect) {
            $poin = $question->poin;
        }

        // Save or update answer
        StudentAnswer::updateOrCreate(
            [
                'tryout_session_id' => $result->tryout_session_id,
                'siswa_id' => $result->siswa_id,
                'question_id' => $question->id,
                'attempt_number' => 1,
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

        return response()->json(['success' => true, 'is_correct' => $isCorrect, 'poin' => $poin]);
    }

    public function complete(Request $request, Result $result)
    {
        if ($result->waktu_selesai) {
            return back()->with('error', 'Tryout sudah diselesaikan');
        }

        // Calculate scores per subject
        $subjects = $result->session->package->subjects()->with(['questions'])->get();
        $detailNilaiMapel = [];
        $totalScore = 0;
        $maxScore = 0;

        foreach ($subjects as $subject) {
            $answers = StudentAnswer::where('tryout_session_id', $result->tryout_session_id)
                                   ->where('siswa_id', $result->siswa_id)
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

        // Calculate ranking
        $ranking = Result::where('tryout_session_id', $result->tryout_session_id)
                        ->whereNotNull('waktu_selesai')
                        ->where('nilai_total', '>', $totalScore)
                        ->count() + 1;

        // Analyze weaknesses
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

    public function result(Result $result)
    {
        $result->load(['session.package.subjects.mataPelajaran', 'siswa']);
        
        return view('tryout.student.result', compact('result'));
    }
}
