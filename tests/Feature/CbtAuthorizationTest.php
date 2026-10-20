<?php

namespace Tests\Feature;

use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2.1/2.2: policy CBT menutup seluruh mutasi lintas guru.
 * Fokus pada aksi tulis yang belum dicakup CbtExamTest.
 */
class CbtAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private array $flagSebelumnya;
    private User $guruA;
    private User $guruB;
    private CbtQuestionBank $bankA;
    private CbtQuestionBank $bankB;
    private CbtQuestion $soalB;
    private CbtTestSession $sesiB;
    private CbtResult $hasilB;

    protected function setUp(): void
    {
        $this->flagSebelumnya = [
            getenv('SIMANTAP_CBT_ENABLED'),
            $_ENV['SIMANTAP_CBT_ENABLED'] ?? null,
            $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null,
        ];
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        \Illuminate\Support\Env::enablePutenv();

        parent::setUp();
        $this->withoutVite();

        $this->guruA = User::factory()->guru()->create();
        $this->guruB = User::factory()->guru()->create();

        $this->bankA = CbtQuestionBank::create([
            'guru_id' => $this->guruA->id, 'title' => 'Bank A', 'subject' => 'Matematika',
        ]);
        $this->bankA->questions()->create([
            'prompt' => 'Soal A', 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'correct_answer' => 'A',
        ]);

        $this->bankB = CbtQuestionBank::create([
            'guru_id' => $this->guruB->id, 'title' => 'Bank B', 'subject' => 'IPA',
        ]);
        $this->soalB = $this->bankB->questions()->create([
            'prompt' => 'Soal B', 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'correct_answer' => 'B',
        ]);

        $siswaB = Siswa::create([
            'guru_id' => $this->guruB->id, 'nama_guru' => $this->guruB->nama_lengkap,
            'nis' => 'CBT-B1', 'nama_peserta_didik' => 'Siswa B', 'kelas' => '6B',
            'jenis_kelamin' => 'P', 'aktif' => true,
        ]);
        $akunB = User::factory()->siswa()->create(['nama_pengguna' => $siswaB->nis]);

        $this->sesiB = CbtTestSession::create([
            'guru_id' => $this->guruB->id, 'bank_id' => $this->bankB->id, 'title' => 'Sesi B',
            'kelas' => '6B', 'opens_at' => now()->subMinute(), 'closes_at' => now()->addHour(),
            'duration_minutes' => 60,
        ]);

        $this->hasilB = CbtResult::create([
            'session_id' => $this->sesiB->id, 'siswa_id' => $siswaB->id, 'user_id' => $akunB->id,
            'started_at' => now(), 'deadline_at' => now()->addHour(), 'answers' => [],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        [$env, $arrayEnv, $server] = $this->flagSebelumnya;
        $env === false ? putenv('SIMANTAP_CBT_ENABLED') : putenv('SIMANTAP_CBT_ENABLED='.$env);
        if ($arrayEnv === null) {
            unset($_ENV['SIMANTAP_CBT_ENABLED']);
        } else {
            $_ENV['SIMANTAP_CBT_ENABLED'] = $arrayEnv;
        }
        if ($server === null) {
            unset($_SERVER['SIMANTAP_CBT_ENABLED']);
        } else {
            $_SERVER['SIMANTAP_CBT_ENABLED'] = $server;
        }
        \Illuminate\Support\Env::enablePutenv();
    }

    public function test_guru_lain_tidak_bisa_mengubah_atau_menghapus_bank(): void
    {
        $this->actingAs($this->guruA)
            ->put(route('cbt.question-banks.update', $this->bankB), ['title' => 'Dibajak', 'subject' => 'X'])
            ->assertForbidden();
        $this->actingAs($this->guruA)
            ->delete(route('cbt.question-banks.destroy', $this->bankB))
            ->assertForbidden();

        $this->assertDatabaseHas('cbt_question_banks', ['id' => $this->bankB->id, 'title' => 'Bank B']);
    }

    public function test_guru_lain_tidak_bisa_menambah_mengubah_atau_menghapus_soal(): void
    {
        $payload = [
            'prompt' => 'Soal baru',
            'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'correct_answer' => 'A',
        ];

        $this->actingAs($this->guruA)
            ->post(route('cbt.questions.store', $this->bankB), $payload)
            ->assertForbidden();
        $this->actingAs($this->guruA)
            ->put(route('cbt.questions.update', $this->soalB), $payload)
            ->assertForbidden();
        $this->actingAs($this->guruA)
            ->delete(route('cbt.questions.destroy', $this->soalB))
            ->assertForbidden();

        $this->assertSame(1, $this->bankB->questions()->count());
        $this->assertSame('Soal B', $this->soalB->fresh()->prompt);
    }

    public function test_guru_lain_tidak_bisa_membuka_memulai_menutup_atau_memonitor_sesi(): void
    {
        $this->actingAs($this->guruA)->get(route('cbt.sessions.show', $this->sesiB))->assertForbidden();
        $this->actingAs($this->guruA)->get(route('cbt.sessions.monitoring', $this->sesiB))->assertForbidden();
        $this->actingAs($this->guruA)->post(route('cbt.sessions.publish', $this->sesiB))->assertForbidden();
        $this->actingAs($this->guruA)->post(route('cbt.sessions.start', $this->sesiB))->assertForbidden();
        $this->actingAs($this->guruA)->post(route('cbt.sessions.complete', $this->sesiB))->assertForbidden();

        $this->assertSame('draft', $this->sesiB->fresh()->status);
    }

    public function test_guru_lain_tidak_bisa_melihat_violations(): void
    {
        $this->actingAs($this->guruA)
            ->getJson(route('cbt.integrity.violations', $this->hasilB))
            ->assertForbidden();
    }

    public function test_api_soal_guru_pemilik_tidak_memuat_kunci_jawaban(): void
    {
        $json = $this->actingAs($this->guruB)
            ->getJson(route('cbt.question-banks.api.questions', $this->bankB))
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('correct_answer', $json['data'][0]);
    }

    public function test_guru_pemilik_tetap_bisa_mengakses_miliknya(): void
    {
        $this->actingAs($this->guruA)->get(route('cbt.question-banks.show', $this->bankA))->assertOk();
        $this->actingAs($this->guruB)->get(route('cbt.sessions.show', $this->sesiB))->assertOk();
    }
}
