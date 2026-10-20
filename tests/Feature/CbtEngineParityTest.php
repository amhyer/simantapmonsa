<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Exam\Answer as ExamAnswer;
use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Exam\Result as ExamResult;
use App\Models\Exam\Session as ExamSession;
use App\Models\Siswa;
use App\Models\User;
use App\Services\CbtExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3, langkah 5: bukti alur CBT benar-benar menulis ke engine terpadu
 * `exam_*` (bukan hanya ke `cbt_*`), dan nilai kedua sisi identik.
 *
 * Seperti parity test Tryout: kalau alur dikembalikan ke engine lama tanpa
 * sengaja, test ini gagal.
 */
class CbtEngineParityTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $account;
    private Siswa $student;
    private CbtQuestionBank $bank;
    private array $previousFlag;

    protected function setUp(): void
    {
        // Flag CBT dibaca saat routes dimuat, bukan saat request berjalan.
        $this->previousFlag = [getenv('SIMANTAP_CBT_ENABLED'), $_ENV['SIMANTAP_CBT_ENABLED'] ?? null, $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null];
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        \Illuminate\Support\Env::enablePutenv();

        parent::setUp();
        $this->withoutVite();
        config(['simantap.cbt_enabled' => true, 'simantap.exam_engine_cbt' => true]);

        $this->guru = User::factory()->guru()->create();
        $this->student = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'CBTP01', 'nama_peserta_didik' => 'Siswa Parity CBT', 'kelas' => '6A',
            'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
        $this->account = User::factory()->siswa()->create(['nama_pengguna' => $this->student->nis]);

        $this->bank = CbtQuestionBank::create(['guru_id' => $this->guru->id, 'title' => 'MTK', 'subject' => 'Matematika']);
        $this->bank->questions()->createMany([
            ['prompt' => 'Dua tambah dua?', 'options' => ['A' => 'Empat', 'B' => 'Lima', 'C' => 'Enam', 'D' => 'Tujuh'], 'correct_answer' => 'A'],
            ['prompt' => 'Tiga tambah tiga?', 'options' => ['A' => 'Lima', 'B' => 'Enam', 'C' => 'Tujuh', 'D' => 'Delapan'], 'correct_answer' => 'B'],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        [$env, $arrayEnv, $server] = $this->previousFlag;
        $env === false ? putenv('SIMANTAP_CBT_ENABLED') : putenv('SIMANTAP_CBT_ENABLED='.$env);
        if ($arrayEnv === null) { unset($_ENV['SIMANTAP_CBT_ENABLED']); } else { $_ENV['SIMANTAP_CBT_ENABLED'] = $arrayEnv; }
        if ($server === null) { unset($_SERVER['SIMANTAP_CBT_ENABLED']); } else { $_SERVER['SIMANTAP_CBT_ENABLED'] = $server; }
        \Illuminate\Support\Env::enablePutenv();
    }

    private function sesi(): CbtTestSession
    {
        $session = CbtTestSession::create([
            'guru_id' => $this->guru->id, 'bank_id' => $this->bank->id, 'title' => 'Ujian',
            'kelas' => '6A', 'opens_at' => now()->subMinute(), 'closes_at' => now()->addHours(2),
            'duration_minutes' => 60,
        ]);
        app(CbtExamService::class)->publish($session);
        app(CbtExamService::class)->open($session);

        return $session->fresh();
    }

    public function test_memulai_ujian_membuat_attempt_dan_sesi_engine(): void
    {
        $session = $this->sesi();
        $this->actingAs($this->account)->post(route('cbt.student.begin', $session))->assertRedirect();

        $this->assertSame(1, ExamSession::where('sumber_tabel', 'cbt_test_sessions')->count());
        $this->assertSame(1, ExamAttempt::where('sumber_tabel', 'cbt_results')->count());

        $result = CbtResult::firstOrFail();
        $attempt = ExamAttempt::firstOrFail();
        $this->assertSame($result->id, (int) $attempt->sumber_id);
        $this->assertSame($result->siswa_id, $attempt->siswa_id);
    }

    public function test_menjawab_menulis_ke_exam_answers_dan_blob_lama_sama(): void
    {
        $session = $this->sesi();
        $this->actingAs($this->account)->post(route('cbt.student.begin', $session));
        $result = CbtResult::firstOrFail();
        $id = $session->question_snapshot[0]['id'];

        $this->actingAs($this->account)
            ->postJson(route('cbt.student.answer', $result), ['question_id' => $id, 'answer' => 'A'])
            ->assertOk();

        $this->assertSame(1, ExamAnswer::count());
        $this->assertSame('A', ExamAnswer::firstOrFail()->jawaban);
        $this->assertTrue((bool) ExamAnswer::firstOrFail()->adalah_benar);

        // Blob lama tetap identik (dibaca monitoring/ekspor).
        $this->assertSame('A', $result->fresh()->answers[$id]);
    }

    public function test_nilai_engine_sama_dengan_nilai_tabel_lama(): void
    {
        $session = $this->sesi();
        $this->actingAs($this->account)->post(route('cbt.student.begin', $session));
        $result = CbtResult::firstOrFail();

        [$q1, $q2] = $session->question_snapshot;
        $this->actingAs($this->account)->postJson(route('cbt.student.answer', $result), ['question_id' => $q1['id'], 'answer' => 'A'])->assertOk();
        $this->actingAs($this->account)->postJson(route('cbt.student.answer', $result), ['question_id' => $q2['id'], 'answer' => 'A'])->assertOk();

        $this->actingAs($this->account)->post(route('cbt.student.submit', $result))->assertRedirect();

        $legacy = $result->fresh();
        $examResult = ExamResult::firstOrFail();

        // 1 benar dari 2 → 50.
        $this->assertSame(50.0, (float) $legacy->score);
        $this->assertSame(1, $legacy->correct_count);
        $this->assertSame((float) $legacy->score, (float) $examResult->nilai_total);
        $this->assertSame((float) $legacy->score, (float) $examResult->persentase);

        $attempt = ExamAttempt::firstOrFail();
        $this->assertSame(ExamAttempt::STATUS_DINILAI, $attempt->status);
        $this->assertSame((float) $legacy->score, (float) $attempt->nilai_total);
        $this->assertNotNull($attempt->selesai_at);
    }

    public function test_snapshot_menang_atas_bank_yang_diedit(): void
    {
        $session = $this->sesi();
        $this->actingAs($this->account)->post(route('cbt.student.begin', $session));
        $result = CbtResult::firstOrFail();

        // Ubah kunci di bank SETELAH publikasi.
        $soal = $this->bank->questions()->orderBy('id')->first();
        $soal->update(['correct_answer' => 'D']);

        $this->actingAs($this->account)->post(route('cbt.student.answer', $result), ['answers' => [$soal->id => 'A']]);
        $this->actingAs($this->account)->post(route('cbt.student.submit', $result), ['answers' => [$soal->id => 'A']]);

        // Snapshot masih 'A' → jawaban 'A' harus dihitung benar (1 dari 2 soal).
        $this->assertSame(50.0, (float) $result->fresh()->score);
        $this->assertSame(50.0, (float) ExamResult::firstOrFail()->nilai_total);

        // Dan engine memang menilai 'A' sebagai benar (kunci snapshot, bukan 'D' di bank).
        $this->assertTrue((bool) ExamAnswer::firstOrFail()->adalah_benar);
    }

    public function test_flag_mati_tidak_menyentuh_engine(): void
    {
        config(['simantap.exam_engine_cbt' => false]);
        $session = $this->sesi();
        $this->actingAs($this->account)->post(route('cbt.student.begin', $session));

        $this->assertSame(0, ExamAttempt::count());
        $this->assertSame(0, ExamAnswer::count());
        $this->assertSame(1, CbtResult::count());
    }
}
