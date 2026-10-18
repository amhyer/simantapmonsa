<?php

namespace Tests\Unit;

use App\Services\CbtScoring;
use PHPUnit\Framework\TestCase;

class CbtScoringTest extends TestCase
{
    private function questions(): array
    {
        return [
            ['id' => 17, 'prompt' => 'Satu', 'options' => ['A' => 'Ya', 'B' => 'Tidak'], 'correct_answer' => 'A'],
            ['id' => 23, 'prompt' => 'Dua', 'options' => ['A' => 'Ya', 'B' => 'Tidak'], 'correct_answer' => 'B'],
            ['id' => 35, 'prompt' => 'Tiga', 'options' => ['A' => 'Ya', 'B' => 'Tidak'], 'correct_answer' => 'A'],
        ];
    }

    public function test_semua_benar_bernilai_seratus(): void
    {
        $this->assertEquals(['correct_count' => 3, 'score' => 100.0], CbtScoring::grade($this->questions(), [17 => 'A', 23 => 'B', 35 => 'A']));
    }

    public function test_jawaban_kosong_dinilai_salah(): void
    {
        $this->assertSame(0, CbtScoring::grade($this->questions(), [])['correct_count']);
    }

    public function test_nilai_dibulatkan_dua_desimal(): void
    {
        $this->assertSame(33.33, CbtScoring::grade($this->questions(), [17 => 'A'])['score']);
    }

    public function test_jawaban_dari_soal_lain_diabaikan(): void
    {
        $this->assertSame(0, CbtScoring::grade($this->questions(), [999 => 'A'])['correct_count']);
    }

    public function test_payload_siswa_tidak_memuat_kunci_dan_tidak_memutasi_snapshot(): void
    {
        $questions = $this->questions();
        $public = CbtScoring::publicQuestions($questions);
        $this->assertArrayNotHasKey('correct_answer', $public[0]);
        $this->assertSame('A', $questions[0]['correct_answer']);
        $this->assertSame($questions[0]['options'], $public[0]['options']);
    }

    public function test_snapshot_kosong_tidak_membagi_nol(): void
    {
        $this->assertSame(0.0, CbtScoring::grade([], [])['score']);
    }
}
