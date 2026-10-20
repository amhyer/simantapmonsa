<?php

namespace Tests\Unit;

use App\Models\Exam\Question as ExamQuestion;
use App\Models\Exam\QuestionOption as ExamOption;
use App\Models\Tryout\TryoutQuestion;
use App\Models\Tryout\TryoutQuestionOption;
use App\Services\CbtScoring;
use App\Services\ExamGradingService;
use App\Services\TryoutScoring;
use PHPUnit\Framework\TestCase;

/**
 * Fase 3: parity penilai baru terhadap dua penilai lama.
 * Bila aturan salah satu engine berubah, test ini yang menahan.
 */
class ExamGradingParityTest extends TestCase
{
    private function examSoal(string $tipe, array $opsi, ?string $kunci = null, float $poin = 1.0): ExamQuestion
    {
        $question = new ExamQuestion(['tipe' => $tipe, 'pertanyaan' => 'x', 'poin' => $poin]);
        if ($kunci !== null) {
            $question->kunci_jawaban = $kunci;
        }
        $question->setRelation('options', collect(array_map(
            fn (array $o) => new ExamOption(['huruf' => $o[0], 'isi_opsi' => $o[1], 'adalah_benar' => $o[2]]),
            $opsi
        )));

        return $question;
    }

    private function tryoutSoal(string $tipe, array $opsi, float $poin = 1.0): TryoutQuestion
    {
        $question = new TryoutQuestion(['tipe_soal' => $tipe, 'pertanyaan' => 'x', 'poin' => $poin]);
        $question->setRelation('options', collect(array_map(
            fn (array $o) => new TryoutQuestionOption(['huruf' => $o[0], 'isi_opsi' => $o[1], 'adalah_benar' => $o[2]]),
            $opsi
        )));

        return $question;
    }

    public function test_parity_pilihan_ganda_dengan_cbt_scoring(): void
    {
        $jawaban = ['A', 'B', 'C', 'D', ''];

        foreach ($jawaban as $isi) {
            $cbt = ['id' => 1, 'correct_answer' => 'C'];
            $exam = $this->examSoal('pilihan_ganda', [
                ['A', 'a', false], ['B', 'b', false], ['C', 'c', true], ['D', 'd', false],
            ]);

            $harapan = CbtScoring::grade([$cbt], [1 => $isi])['correct_count'] === 1;
            [$benar] = ExamGradingService::nilai($exam, $isi === '' ? null : $isi);

            $this->assertSame($harapan, $benar, "jawaban '$isi' tidak setara CbtScoring");
        }
    }

    public function test_parity_tryout_untuk_semua_tipe_soal(): void
    {
        $kasus = [
            ['pilihan_ganda', [['A', 'Empat', true], ['B', 'Lima', false]], null],
            ['benar_salah', [['A', 'Benar', true], ['B', 'Salah', false]], null],
            ['isian_singkat', [['A', 'Ibu Kota', true], ['B', 'Bandung', false]], null],
        ];
        $jawaban = ['A', 'B', 'a', 'benar', 'BENAR', 'salah', 'ibu kota', ' Ibu   Kota ', '', null, 'salah sekali'];

        foreach ($kasus as [$tipe, $opsi, $kunci]) {
            $tryout = $this->tryoutSoal($tipe, $opsi);
            $exam = $this->examSoal($tipe, $opsi, $kunci);

            foreach ($jawaban as $isi) {
                $lama = TryoutScoring::grade($tryout, $isi);
                $baru = ExamGradingService::nilai($exam, $isi);

                $this->assertSame($lama[0], $baru[0], "tipe $tipe jawaban ".var_export($isi, true));
            }
        }
    }

    public function test_kunci_eksplisit_diutamakan_atas_opsi(): void
    {
        // Data Tryout lama menyimpan kunci sebagai opsi; engine baru boleh
        // menyimpan kunci eksplisit dan harus menang.
        $exam = $this->examSoal('isian_singkat', [['A', 'Salah', true]], kunci: 'Jakarta');

        $this->assertSame([true, 1.0], ExamGradingService::nilai($exam, 'jakarta'));
        $this->assertSame([false, 0.0], ExamGradingService::nilai($exam, 'Salah'));
    }

    public function test_essay_menunggu_penilaian_manual(): void
    {
        $exam = $this->examSoal('essay', [['A', 'apa saja', true]], kunci: 'apa saja');

        $this->assertSame([false, 0.0], ExamGradingService::nilai($exam, 'apa saja'));
    }

    public function test_normalisasi_teks(): void
    {
        $this->assertSame('ibu kota', ExamGradingService::normalisasi('  IBU    Kota '));
        $this->assertSame('', ExamGradingService::normalisasi(null));
    }
}
