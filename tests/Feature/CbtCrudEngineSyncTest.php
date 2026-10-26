<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\Exam\Bank as ExamBank;
use App\Models\Exam\Question as ExamQuestion;
use App\Models\Exam\Session as ExamSession;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3 langkah 6c (tahap CBT): CRUD guru/admin harus langsung tercermin ke
 * engine `exam_*`, bukan hanya saat publish/backfill.
 *
 * Tanpa ini, bank/sesi yang dibuat lewat UI tidak terlihat oleh pembaca engine
 * (dasbor & laporan berflag), sehingga parity pembaca hanya berlaku untuk data
 * hasil backfill.
 */
class CbtCrudEngineSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $admin;
    private Siswa $siswa;
    private array $previousFlag;

    protected function setUp(): void
    {
        // Flag CBT dibaca saat routes dimuat, bukan saat request berjalan.
        $this->previousFlag = [getenv('SIMANTAP_CBT_ENABLED'), $_ENV['SIMANTAP_CBT_ENABLED'] ?? null, $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null];
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        \Illuminate\Support\Env::enablePutenv();

        parent::setUp();
        config(['simantap.cbt_enabled' => true, 'simantap.exam_engine_cbt' => true]);

        $this->guru = User::factory()->guru()->create();
        $this->admin = User::factory()->admin()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'CRUD01', 'nama_peserta_didik' => 'Siswa CRUD', 'kelas' => '6A',
            'jenis_kelamin' => 'L', 'aktif' => true,
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

    /** @return array<string, mixed> */
    private function payloadSoal(string $prompt): array
    {
        return [
            'prompt' => $prompt,
            'options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'correct_answer' => 'B',
        ];
    }

    private function buatBankLewatCrud(string $judul = 'IPA', string $mapel = 'Ilmu Pengetahuan Alam'): CbtQuestionBank
    {
        $this->actingAs($this->guru)
            ->post(route('cbt.question-banks.store'), ['title' => $judul, 'subject' => $mapel])
            ->assertRedirect();

        return CbtQuestionBank::latest('id')->firstOrFail();
    }

    public function test_bank_dan_soal_dari_crud_guru_langsung_masuk_engine(): void
    {
        $bank = $this->buatBankLewatCrud();

        $engineBank = ExamBank::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_BANK)
            ->where('sumber_id', $bank->id)
            ->firstOrFail();

        $this->assertSame('IPA', $engineBank->nama);
        $this->assertSame('Ilmu Pengetahuan Alam', $engineBank->mata_pelajaran);
        $this->assertSame($this->guru->id, $engineBank->guru_id);

        $this->actingAs($this->guru)
            ->post(route('cbt.questions.store', $bank), $this->payloadSoal('Soal baru'))
            ->assertRedirect();

        $soal = $bank->questions()->firstOrFail();
        $engineSoal = ExamQuestion::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SOAL)
            ->where('sumber_id', $soal->id)
            ->firstOrFail();

        $this->assertSame('Soal baru', $engineSoal->pertanyaan);
        $this->assertSame(4, $engineSoal->options()->count());
        $this->assertTrue($engineSoal->options()->where('huruf', 'B')->firstOrFail()->adalah_benar);
    }

    public function test_edit_dan_hapus_soal_tercermin_ke_engine(): void
    {
        $bank = $this->buatBankLewatCrud();
        $this->actingAs($this->guru)->post(route('cbt.questions.store', $bank), $this->payloadSoal('Soal awal'));
        $soal = $bank->questions()->firstOrFail();

        $this->actingAs($this->guru)
            ->put(route('cbt.question-banks.update', $bank), ['title' => 'IPA Revisi', 'subject' => 'IPA'])
            ->assertRedirect();
        $this->actingAs($this->guru)
            ->put(route('cbt.questions.update', $soal), $this->payloadSoal('Soal revisi'))
            ->assertRedirect();

        $engineBank = ExamBank::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_BANK)
            ->where('sumber_id', $bank->id)->firstOrFail();
        $engineSoal = ExamQuestion::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SOAL)
            ->where('sumber_id', $soal->id)->firstOrFail();

        $this->assertSame('IPA Revisi', $engineBank->nama);
        $this->assertSame('Soal revisi', $engineSoal->pertanyaan);
        $this->assertTrue($engineSoal->options()->where('huruf', 'B')->firstOrFail()->adalah_benar);

        $this->actingAs($this->guru)
            ->delete(route('cbt.questions.destroy', $soal))
            ->assertRedirect();

        $this->assertSoftDeleted('exam_questions', [
            'sumber_tabel' => LegacyExamMapper::SUMBER_CBT_SOAL,
            'sumber_id' => $soal->id,
        ]);
    }

    public function test_bank_dari_admin_tercermin_dan_penghapusan_ikut_ke_engine(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cbt.banks.store'), [
                'title' => 'Bank Admin', 'subject' => 'IPS', 'guru_id' => $this->guru->id,
            ])
            ->assertRedirect();

        $bank = CbtQuestionBank::latest('id')->firstOrFail();
        $this->assertDatabaseHas('exam_banks', [
            'sumber_tabel' => LegacyExamMapper::SUMBER_CBT_BANK,
            'sumber_id' => $bank->id,
            'nama' => 'Bank Admin',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.cbt.banks.destroy', $bank))
            ->assertRedirect();

        $this->assertSoftDeleted('exam_banks', [
            'sumber_tabel' => LegacyExamMapper::SUMBER_CBT_BANK,
            'sumber_id' => $bank->id,
        ]);
    }

    public function test_sesi_dari_crud_guru_masuk_engine_dan_penghapusan_admin_ikut(): void
    {
        $bank = $this->buatBankLewatCrud();

        $this->actingAs($this->guru)
            ->post(route('cbt.sessions.store'), [
                'bank_id' => $bank->id,
                'title' => 'Sesi Baru',
                'kelas' => '6A',
                'opens_at' => now()->addHour()->toDateTimeString(),
                'closes_at' => now()->addDays(2)->toDateTimeString(),
                'duration_minutes' => 60,
            ])
            ->assertRedirect();

        $sesi = CbtTestSession::latest('id')->firstOrFail();
        $engineSesi = ExamSession::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI)
            ->where('sumber_id', $sesi->id)
            ->firstOrFail();

        $this->assertSame('Sesi Baru', $engineSesi->nama);
        $this->assertSame('draft', $engineSesi->status);

        $this->actingAs($this->admin)
            ->delete(route('admin.cbt.sessions.destroy', $sesi))
            ->assertRedirect();

        $this->assertSoftDeleted('exam_sessions', [
            'sumber_tabel' => LegacyExamMapper::SUMBER_CBT_SESI,
            'sumber_id' => $sesi->id,
        ]);
    }
}
