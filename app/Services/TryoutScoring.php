<?php

namespace App\Services;

use App\Models\Tryout\TryoutQuestion;

/**
 * Penilaian Tryout di sisi server (Fase 2.6).
 *
 * Kunci jawaban tidak pernah dikirim ke browser. Untuk tipe selain pilihan
 * ganda, kunci disimpan sebagai opsi dengan `adalah_benar = true` (skema lama
 * belum punya kolom kunci khusus). Bila kunci tidak ada, jawaban tidak diberi
 * poin — bukan dianggap benar.
 */
class TryoutScoring
{
    /**
     * @return array{0: bool, 1: float}
     */
    public static function grade(TryoutQuestion $question, ?string $jawaban): array
    {
        $jawaban = $jawaban === null ? null : trim($jawaban);

        if ($jawaban === null || $jawaban === '') {
            return [false, 0.0];
        }

        $benar = match ($question->tipe_soal) {
            'pilihan_ganda' => self::cocokPilihan($question, $jawaban),
            'benar_salah' => self::cocokTeks($question, $jawaban),
            'isian_singkat' => self::cocokTeks($question, $jawaban),
            default => false,
        };

        return [$benar, $benar ? (float) $question->poin : 0.0];
    }

    private static function cocokPilihan(TryoutQuestion $question, string $jawaban): bool
    {
        $kunci = $question->options->firstWhere('adalah_benar', true);

        return $kunci !== null && strtoupper($jawaban) === strtoupper(trim($kunci->huruf));
    }

    private static function cocokTeks(TryoutQuestion $question, string $jawaban): bool
    {
        $kunci = $question->options->firstWhere('adalah_benar', true);

        return $kunci !== null && self::normalisasi($jawaban) === self::normalisasi($kunci->isi_opsi);
    }

    private static function normalisasi(string $teks): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($teks)) ?? '');
    }
}
