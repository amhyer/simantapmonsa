<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Models\User;
use App\Services\CbtExamService;
use App\Services\Exam\CbtStudentReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 3 langkah 6d (tahap CBT): daftar ujian siswa harus bisa dibaca dari
 * `exam_sessions`. Parity diuji dua arah, lalu tabel `cbt_*` dikosongkan untuk
 * membuktikan pembaca tidak lagi bergantung padanya.
 */
class CbtStudentReaderTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $akun;
    private Siswa $siswa;
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
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'RDC01', 'nama_peserta_didik' => 'Siswa Reader CBT', 'kelas' => '6A',
            'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
        $this->akun = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);

        $this->bank = CbtQuestionBank::create(['guru_id' => $this->guru->id, 'title' => 'MTK', 'subject' => 'Matematika']);
        $this->bank->questions()->createMany([
            ['prompt' => 'Dua tambah dua?', 'options' => ['A' => 'Empat', 'B' => 'Lima', 'C' => 'Enam', 'D' => 'Tujuh'], 'correct_answer' => 'A'],
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
            'guru_id' => $this->guru->id, 'bank_id' => $this->bank->id, 'title' => 'Ujian Reader',
            'kelas' => '6A', 'opens_at' => now()->subMinute(), 'closes_at' => now()->addHours(2),
            'duration_minutes' => 60,
        ]);
        app(CbtExamService::class)->publish($session);
        app(CbtExamService::class)->open($session);

        return $session->fresh();
    }

    /** @return array<int, array<string, mixed>> */
    private function potret(bool $engine): array
    {
        config(['simantap.exam_engine_readers' => $engine]);

        return app(CbtStudentReader::class)->sesiSiswa($this->siswa)->items();
    }

    public function test_daftar_ujian_siswa_sama_di_kedua_sumber(): void
    {
        $session = $this->sesi();
        $this->actingAs($this->akun)->post(route('cbt.student.begin', $session))->assertRedirect();

        $legacy = $this->potret(false);
        $engine = $this->potret(true);

        $this->assertCount(1, $engine);
        $this->assertSame($legacy, $engine);
        $this->assertSame('Ujian Reader', $engine[0]['title']);
    }

    public function test_setelah_tabel_cbt_dikosongkan_daftar_engine_tetap_benar(): void
    {
        $session = $this->sesi();
        $this->actingAs($this->akun)->post(route('cbt.student.begin', $session))->assertRedirect();

        $sebelum = $this->potret(true);

        DB::table('cbt_results')->delete();
        DB::table('cbt_test_sessions')->delete();

        $sesudah = $this->potret(true);
        $this->assertSame($sebelum, $sesudah);

        $this->actingAs($this->akun)
            ->get(route('cbt.student.index'))
            ->assertOk()
            ->assertSee('Ujian Reader');
    }
}
