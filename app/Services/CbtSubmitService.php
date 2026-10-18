<?php

namespace App\Services;

use App\Models\Cbt\TestResult;
use App\Models\Cbt\StudentAnswer;
use App\Models\Cbt\Question;

class CbtSubmitService
{
    public function submit(TestResult $result, string $submittedBy = 'siswa'): TestResult
    {
        if ($result->waktu_selesai) {
            throw new \Exception('Ujian sudah dikumpulkan');
        }

        $answers = $result->studentAnswers;
        $totalPoin = 0;
        $benar = 0;
        $salah = 0;
        $tidakDijawab = 0;

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
                $tidakDijawab++;
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
            'jumlah_tidak_dijawab' => $tidakDijawab,
            'waktu_selesai' => now(),
            'durasi_detik' => now()->diffInSeconds($result->waktu_mulai),
            'submitted_by' => $submittedBy,
        ]);

        return $result;
    }

    public function autoSubmit(TestResult $result): TestResult
    {
        return $this->submit($result, 'auto');
    }
}