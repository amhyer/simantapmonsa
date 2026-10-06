<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\TestSession;
use App\Models\Cbt\TestResult;
use App\Models\Cbt\Question;
use App\Models\Cbt\StudentAnswer;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CbtStudentController extends Controller
{
    public function index()
    {
        $siswa = Siswa::where('nis', auth()->user()->nama_pengguna)->first();

        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan');
        }

        $availableSessions = TestSession::where('status', 'ongoing')
                                        ->where('tanggal_mulai', '<=', now())
                                        ->where('tanggal_selesai', '>=', now())
                                        ->where(function ($q) use ($siswa) {
                                            $q->whereNull('kelas')
                                              ->orWhere('kelas', $siswa->kelas);
                                        })
                                        ->get();

        $myResults = TestResult::where('siswa_id', $siswa->id)
                              ->with('testSession')
                              ->latest()
                              ->get();

        return view('cbt.student.index', compact('availableSessions', 'myResults'));
    }

    public function start(TestSession $session)
    {
        $siswa = Siswa::where('nis', auth()->user()->nama_pengguna)->first();

        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan');
        }

        if (!$session->canStudentTake($siswa->id)) {
            return back()->with('error', 'Anda tidak dapat mengikuti ujian ini');
        }

        // Create test result record
        $result = TestResult::create([
            'uuid' => Str::uuid(),
            'test_session_id' => $session->id,
            'siswa_id' => $siswa->id,
            'attempt_number' => $session->testResults()->where('siswa_id', $siswa->id)->count() + 1,
            'nilai_akhir' => 0,
            'nilai_maksimal' => 0,
            'persentase' => 0,
            'lulus' => false,
            'jumlah_benar' => 0,
            'jumlah_salah' => 0,
            'jumlah_tidak_dijawab' => 0,
            'waktu_mulai' => now(),
            'waktu_selesai' => null,
            'durasi_detik' => 0,
            'submitted_by' => 'siswa',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'integrity_score' => 100.0,
        ]);

        // Get questions for this session
        $questions = $session->questionBank->questions()
                                          ->where('aktif', true)
                                          ->limit($session->jumlah_soal_tampil)
                                          ->get();

        if ($session->acak_soal) {
            $questions = $questions->shuffle();
        }

        // Randomize options if enabled
        if ($session->acak_opsi) {
            $questions->each(function ($q) {
                $q->options = $q->options->shuffle();
            });
        }

        return view('cbt.student.take', compact('session', 'result', 'questions'));
    }

    public function submitAnswer(Request $request, TestResult $result)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:cbt_questions,id',
            'jawaban' => 'required|string',
            'ragu_ragu' => 'boolean',
        ]);

        $question = Question::findOrFail($validated['question_id']);

        // Check if already answered
        $existing = StudentAnswer::where('test_result_id', $result->id)
                                ->where('question_id', $question->id)
                                ->first();

        if ($existing) {
            $existing->update([
                'jawaban' => $validated['jawaban'],
                'ragu_ragu' => $validated['ragu_ragu'] ?? false,
                'waktu_selesai_jawab' => now(),
                'durasi_detik' => $existing->waktu_mulai_jawab
                    ? now()->diffInSeconds($existing->waktu_mulai_jawab)
                    : 0,
            ]);
        } else {
            StudentAnswer::create([
                'uuid' => Str::uuid(),
                'test_result_id' => $result->id,
                'siswa_id' => $result->siswa_id,
                'question_id' => $question->id,
                'jawaban' => $validated['jawaban'],
                'adalah_benar' => null,
                'poin_diperoleh' => 0,
                'waktu_mulai_jawab' => now(),
                'waktu_selesai_jawab' => now(),
                'durasi_detik' => 0,
                'attempt_number' => $result->attempt_number,
                'ragu_ragu' => $validated['ragu_ragu'] ?? false,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function submit(TestResult $result)
    {
        if ($result->waktu_selesai) {
            return back()->with('error', 'Ujian sudah dikumpulkan');
        }

        // Calculate score
        $answers = $result->studentAnswers;
        $totalPoin = 0;
        $benar = 0;
        $salah = 0;
        $tidak_dijawab = 0;

        foreach ($answers as $answer) {
            $question = $answer->question;
            $isCorrect = false;

            if ($question->tipe_soal === 'pilihan_ganda') {
                $correctOption = $question->options->where('adalah_benar', true)->first();
                $isCorrect = $correctOption && $answer->jawaban === $correctOption->huruf;
            } elseif ($question->tipe_soal === 'benar_salah') {
                $isCorrect = strtolower($answer->jawaban) === 'benar';
            }

            $answer->update([
                'adalah_benar' => $isCorrect,
                'poin_diperoleh' => $isCorrect ? $question->poin : 0,
            ]);

            if ($isCorrect) {
                $benar++;
                $totalPoin += $question->poin;
            } elseif ($answer->jawaban) {
                $salah++;
            } else {
                $tidak_dijawab++;
            }
        }

        $maxPoin = $result->testSession->questionBank->questions->sum('poin');
        $persentase = $maxPoin > 0 ? ($totalPoin / $maxPoin) * 100 : 0;
        $lulus = $persentase >= $result->testSession->passing_grade;

        $result->update([
            'nilai_akhir' => $totalPoin,
            'nilai_maksimal' => $maxPoin,
            'persentase' => $persentase,
            'lulus' => $lulus,
            'jumlah_benar' => $benar,
            'jumlah_salah' => $salah,
            'jumlah_tidak_dijawab' => $tidak_dijawab,
            'waktu_selesai' => now(),
            'durasi_detik' => now()->diffInSeconds($result->waktu_mulai),
            'submitted_by' => 'siswa',
        ]);

        return redirect()->route('cbt.student.result', $result)
            ->with('success', 'Jawaban berhasil dikumpulkan');
    }

    public function result(TestResult $result)
    {
        if (!$result->testSession->tampilkan_nilai && $result->submitted_by !== 'guru') {
            return back()->with('error', 'Nilai belum ditampilkan oleh guru');
        }

        $result->load(['testSession', 'siswa', 'studentAnswers.question.options']);

        return view('cbt.student.result', compact('result'));
    }
}
