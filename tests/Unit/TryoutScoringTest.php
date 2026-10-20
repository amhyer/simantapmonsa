<?php

namespace Tests\Unit;

use App\Models\Tryout\TryoutQuestion;
use App\Models\Tryout\TryoutQuestionOption;
use App\Services\TryoutScoring;
use PHPUnit\Framework\TestCase;

class TryoutScoringTest extends TestCase
{
    private function soal(string $tipe, ?string $jawabanBenar, float $poin = 2.0): TryoutQuestion
    {
        $question = new TryoutQuestion([
            'tipe_soal' => $tipe,
            'pertanyaan' => 'Soal',
            'poin' => $poin,
        ]);

        $options = [];
        if ($jawabanBenar !== null) {
            $options[] = new TryoutQuestionOption([
                'huruf' => 'A', 'isi_opsi' => $jawabanBenar, 'adalah_benar' => true,
            ]);
            $options[] = new TryoutQuestionOption([
                'huruf' => 'B', 'isi_opsi' => 'Pengecoh', 'adalah_benar' => false,
            ]);
        }
        $question->setRelation('options', collect($options));

        return $question;
    }

    public function test_pilihan_ganda_benar_dan_salah(): void
    {
        $question = $this->soal('pilihan_ganda', 'Empat');

        $this->assertSame([true, 2.0], TryoutScoring::grade($question, 'A'));
        $this->assertSame([false, 0.0], TryoutScoring::grade($question, 'B'));
        // Huruf kecil tetap diterima.
        $this->assertSame([true, 2.0], TryoutScoring::grade($question, 'a'));
    }

    public function test_benar_salah_memakai_opsi_kunci(): void
    {
        $question = $this->soal('benar_salah', 'Benar');

        $this->assertSame([true, 2.0], TryoutScoring::grade($question, 'benar'));
        $this->assertSame([true, 2.0], TryoutScoring::grade($question, 'BENAR'));
        $this->assertSame([false, 0.0], TryoutScoring::grade($question, 'salah'));
    }

    public function test_isian_singkat_dinormalisasi(): void
    {
        $question = $this->soal('isian_singkat', 'Ibu Kota');

        $this->assertSame([true, 2.0], TryoutScoring::grade($question, ' ibu   kota '));
        $this->assertSame([false, 0.0], TryoutScoring::grade($question, 'ibu'));
    }

    public function test_tanpa_kunci_atau_jawaban_kosong_tidak_diberi_poin(): void
    {
        $tanpaKunci = $this->soal('isian_singkat', null);

        $this->assertSame([false, 0.0], TryoutScoring::grade($tanpaKunci, 'apa saja'));
        $this->assertSame([false, 0.0], TryoutScoring::grade($this->soal('isian_singkat', 'x'), ''));
        $this->assertSame([false, 0.0], TryoutScoring::grade($this->soal('isian_singkat', 'x'), null));
    }
}
