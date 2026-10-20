<?php

namespace App\Services;

use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Question;

/**
 * Fase 3: satu-satunya penilai asesmen untuk engine terpadu.
 *
 * Menyatukan aturan yang sebelumnya tersebar di `CbtScoring` (pilihan ganda)
 * dan `TryoutScoring` (benar_salah/isian_singkat + normalisasi teks).
 *
 * Kunci jawaban tidak pernah diterima dari klien. Sumber kunci, berurutan:
 *   1. kolom `exam_questions.kunci_jawaban` (eksplisit), lalu
 *   2. opsi dengan `adalah_benar = true` (pf. data Tryout lama).
 *
 * Bila kunci tidak ditemukan, jawaban bernilai 0 — bukan dianggap benar.
 */
class ExamGradingService
{
    /**
     * Normalisasi jawaban teks: huruf kecil, spasi jamak disatukan, di-trim.
     */
    public static function normalisasi(?string $teks): string
    {
        if ($teks === null) {
            return '';
        }

        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($teks)) ?? '');
    }

    /**
     * Apakah jawaban benar untuk soal tertentu. Dipakai baik untuk penilaian
     * massal (submit) maupun pemeriksaan satu jawaban.
     */
    public static function benar(Question $question, ?string $jawaban): bool
    {
        if ($jawaban === null || trim($jawaban) === '') {
            return false;
        }

        return match ($question->tipe) {
            'pilihan_ganda' => self::benarPilihanGanda($question, $jawaban),
            'benar_salah', 'isian_singkat' => self::benarTeks($question, $jawaban),
            default => false, // essay dan tipe tak dikenal menunggu penilaian manual
        };
    }

    /**
     * Nilai satu jawaban: [benar, poin].
     *
     * @return array{0: bool, 1: float}
     */
    public static function nilai(Question $question, ?string $jawaban): array
    {
        $benar = self::benar($question, $jawaban);

        return [$benar, $benar ? (float) $question->poin : 0.0];
    }

    /**
     * Nilai seluruh jawaban satu attempt lalu tulis hasilnya.
     *
     * @param  iterable<array{question: Question, jawaban: ?string}>  $jawabanList
     * @return array{nilai_total: float, nilai_maksimal: float, persentase: float, benar: int, jumlah: int}
     */
    public function nilaiAttempt(Attempt $attempt, iterable $jawabanList): array
    {
        $total = 0.0;
        $maksimal = 0.0;
        $benar = 0;
        $jumlah = 0;

        foreach ($jawabanList as $baris) {
            /** @var Question $question */
            $question = $baris['question'];
            [$isBenar, $poin] = self::nilai($question, $baris['jawaban']);

            $maksimal += (float) $question->poin;
            $total += $poin;
            $jumlah++;
            if ($isBenar) {
                $benar++;
            }
        }

        $persentase = $maksimal > 0 ? round(100 * $total / $maksimal, 2) : 0.0;

        $attempt->forceFill([
            'nilai_total' => round($total, 2),
            'nilai_maksimal' => round($maksimal, 2),
            'status' => Attempt::STATUS_DINILAI,
        ])->save();

        return [
            'nilai_total' => round($total, 2),
            'nilai_maksimal' => round($maksimal, 2),
            'persentase' => $persentase,
            'benar' => $benar,
            'jumlah' => $jumlah,
        ];
    }

    /**
     * Tulis/perbarui baris jawaban beserta hasil penilaiannya.
     */
    public function simpanJawaban(Attempt $attempt, Question $question, ?string $jawaban): Answer
    {
        [$isBenar, $poin] = self::nilai($question, $jawaban);
        $kosong = $jawaban === null || trim($jawaban) === '';

        return Answer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $question->id],
            [
                'jawaban' => $kosong ? null : $jawaban,
                'adalah_benar' => $isBenar,
                'poin_diperoleh' => $poin,
                'dijawab_at' => now(),
            ]
        );
    }

    private static function benarPilihanGanda(Question $question, string $jawaban): bool
    {
        $kunci = $question->options->firstWhere('adalah_benar', true);

        return $kunci !== null && strtoupper(trim($jawaban)) === strtoupper(trim($kunci->huruf));
    }

    private static function benarTeks(Question $question, string $jawaban): bool
    {
        $kunciEksplisit = $question->kunci_jawaban;
        if ($kunciEksplisit !== null && trim($kunciEksplisit) !== '') {
            return self::normalisasi($jawaban) === self::normalisasi($kunciEksplisit);
        }

        $kunci = $question->options->firstWhere('adalah_benar', true);

        return $kunci !== null && self::normalisasi($jawaban) === self::normalisasi($kunci->isi_opsi);
    }
}
