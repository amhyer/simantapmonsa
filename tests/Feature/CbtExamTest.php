<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Models\User;
use App\Services\CbtExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CbtExamTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $account;
    private Siswa $student;
    private CbtQuestionBank $bank;
    private array $previousFlag;

    protected function setUp(): void
    {
        // Flag dibaca ketika routes dimuat, BUKAN setelah request dimulai.
        $this->previousFlag = [getenv('SIMANTAP_CBT_ENABLED'), $_ENV['SIMANTAP_CBT_ENABLED'] ?? null, $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null];
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        \Illuminate\Support\Env::enablePutenv();
        parent::setUp();
        $this->withoutVite();
        $this->guru = User::factory()->guru()->create();
        $this->student = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'CBT001', 'nama_peserta_didik' => 'Siswa CBT', 'kelas' => '6A',
            'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
        $this->account = User::factory()->siswa()->create(['nama_pengguna' => $this->student->nis]);
        $this->bank = CbtQuestionBank::create(['guru_id' => $this->guru->id, 'title' => 'Matematika', 'subject' => 'Matematika']);
        $this->bank->questions()->create([
            'prompt' => '<script>alert("soal")</script> Dua tambah dua?',
            'options' => ['A' => 'Empat', 'B' => 'Lima', 'C' => 'Enam', 'D' => 'Tujuh'],
            'correct_answer' => 'A',
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

    private function createSession(bool $open = true): CbtTestSession
    {
        $session = CbtTestSession::create([
            'guru_id' => $this->guru->id, 'bank_id' => $this->bank->id, 'title' => 'Ujian',
            'kelas' => '6A', 'opens_at' => now()->subMinute(), 'closes_at' => now()->addHours(2),
            'duration_minutes' => 60,
        ]);
        app(CbtExamService::class)->publish($session);
        if ($open) { app(CbtExamService::class)->open($session); }
        return $session->fresh();
    }

    private function attempt(CbtTestSession $session): CbtResult
    {
        $this->actingAs($this->account)->post(route('cbt.student.begin', $session))->assertRedirect();
        return CbtResult::where('session_id', $session->id)->firstOrFail();
    }

    public function test_get_petunjuk_tidak_membuat_percobaan(): void
    {
        $session = $this->createSession();
        $this->actingAs($this->account)->get(route('cbt.student.start', $session))->assertOk();
        $this->assertDatabaseCount('cbt_results', 0);
    }

    public function test_siklus_bank_sesi_autosave_submit_dan_hasil(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $id = $session->question_snapshot[0]['id'];
        $this->postJson(route('cbt.student.answer', $result), ['question_id' => $id, 'answer' => 'A'])->assertOk()->assertJson(['saved' => true]);
        $this->post(route('cbt.student.submit', $result))->assertRedirect(route('cbt.student.result', $result));
        $this->assertSame(100.0, $result->fresh()->score);
        $this->get(route('cbt.student.result', $result))->assertOk()->assertSee('100.00');
    }

    public function test_post_mulai_ganda_tetap_satu_percobaan_dan_deadline_tetap(): void
    {
        $session = $this->createSession();
        $first = $this->attempt($session);
        $this->travel(2)->minutes();
        $second = $this->attempt($session);
        $this->assertSame($first->id, $second->id);
        $this->assertTrue($first->deadline_at->equalTo($second->deadline_at));
        $this->assertDatabaseCount('cbt_results', 1);
    }

    public function test_sesi_belum_dibuka_tidak_bisa_dimulai(): void
    {
        $session = $this->createSession(false);
        $this->actingAs($this->account)->post(route('cbt.student.begin', $session))->assertStatus(409);
        $this->assertDatabaseCount('cbt_results', 0);
    }

    public function test_di_luar_jadwal_tidak_bisa_mulai(): void
    {
        $session = $this->createSession();
        $this->travel(3)->hours();
        $this->actingAs($this->account)->post(route('cbt.student.begin', $session))->assertStatus(409);
    }

    public function test_deadline_tidak_melewati_tutup_sesi(): void
    {
        $session = $this->createSession();
        $session->update(['closes_at' => now()->addMinutes(5)]);
        $result = $this->attempt($session);
        $this->assertTrue($result->deadline_at->equalTo($session->fresh()->closes_at));
    }

    public function test_jawaban_setelah_deadline_ditolak_dan_hasil_difinalisasi(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $id = $session->question_snapshot[0]['id'];
        $this->postJson(route('cbt.student.answer', $result), ['question_id' => $id, 'answer' => 'B'])->assertOk();
        $this->travel(61)->minutes();
        $this->postJson(route('cbt.student.answer', $result), ['question_id' => $id, 'answer' => 'A'])->assertStatus(409);
        $this->assertSame('B', $result->fresh()->answers[$id]);
        $this->assertNotNull($result->fresh()->submitted_at);
        $this->assertSame(0.0, $result->fresh()->score);
    }

    public function test_submit_terlambat_tidak_menerima_payload_baru(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $this->travel(61)->minutes();
        $this->post(route('cbt.student.submit', $result), ['answers' => [$session->question_snapshot[0]['id'] => 'A'], 'score' => 100])->assertRedirect();
        $this->assertSame(0.0, $result->fresh()->score);
    }

    public function test_submit_ganda_idempoten_dan_nilai_client_diabaikan(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $id = $session->question_snapshot[0]['id'];
        $this->post(route('cbt.student.submit', $result), ['answers' => [$id => 'B'], 'score' => 100])->assertRedirect();
        $stamp = $result->fresh()->submitted_at;
        $this->post(route('cbt.student.submit', $result), ['answers' => [$id => 'A']])->assertRedirect();
        $this->assertSame(0.0, $result->fresh()->score);
        $this->assertTrue($stamp->equalTo($result->fresh()->submitted_at));
    }

    public function test_snapshot_tidak_berubah_saat_bank_diedit(): void
    {
        $session = $this->createSession();
        $question = $this->bank->questions()->first();
        $question->update(['correct_answer' => 'B']);
        $result = $this->attempt($session);
        $this->post(route('cbt.student.submit', $result), ['answers' => [$question->id => 'A']])->assertRedirect();
        $this->assertSame(100.0, $result->fresh()->score);
    }

    public function test_html_soal_di_escape_dan_kunci_tidak_dibocorkan(): void
    {
        $session = $this->createSession();
        $this->attempt($session);
        $this->get(route('cbt.student.start', $session))->assertOk()->assertDontSee('<script>alert("soal")</script>', false)->assertDontSee('correct_answer');
    }

    public function test_siswa_lain_tidak_bisa_mengakses_hasil_atau_mengirim_jawaban(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $other = User::factory()->siswa()->create(['terhubung_dengan' => [$this->student->id]]);
        $this->actingAs($other)->get(route('cbt.student.result', $result))->assertForbidden();
        $this->postJson(route('cbt.student.answer', $result), ['question_id' => $session->question_snapshot[0]['id'], 'answer' => 'A'])->assertForbidden();
    }

    public function test_guru_lain_tidak_bisa_membaca_bank_atau_monitoring(): void
    {
        $session = $this->createSession();
        $this->actingAs(User::factory()->guru()->create())->get(route('cbt.question-banks.show', $this->bank))->assertForbidden();
        $this->get(route('cbt.sessions.api.monitoring', $session))->assertForbidden();
        $this->post(route('cbt.sessions.publish', $session))->assertForbidden();
    }

    public function test_soal_asing_dan_opsi_tidak_valid_ditolak(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $this->postJson(route('cbt.student.answer', $result), ['question_id' => 999999, 'answer' => 'A'])->assertUnprocessable();
        $this->postJson(route('cbt.student.answer', $result), ['question_id' => $session->question_snapshot[0]['id'], 'answer' => 'Z'])->assertUnprocessable();
        $this->assertSame([], $result->fresh()->answers);
    }

    public function test_akun_nonaktif_ditolak_meski_masih_punya_sesi_login(): void
    {
        $session = $this->createSession();
        $this->account->update(['aktif' => false]);
        $this->actingAs($this->account)->get(route('cbt.student.start', $session))->assertForbidden();
    }

    public function test_guru_mengakhiri_sesi_menilai_semua_jawaban_tersimpan(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $this->postJson(route('cbt.student.answer', $result), ['question_id' => $session->question_snapshot[0]['id'], 'answer' => 'A'])->assertOk();
        $this->actingAs($this->guru)->post(route('cbt.sessions.complete', $session))->assertRedirect();
        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame(100.0, $result->fresh()->score);
    }

    public function test_command_finalisasi_bekerja_tanpa_browser_dan_idempoten(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $this->travel(61)->minutes();
        $this->artisan('cbt:finalize-expired')->assertSuccessful();
        $this->assertNotNull($result->fresh()->submitted_at);
        $stamp = $result->fresh()->submitted_at;
        $this->artisan('cbt:finalize-expired')->assertSuccessful();
        $this->assertTrue($stamp->equalTo($result->fresh()->submitted_at));
    }

    public function test_catatan_integritas_dibatasi_pemilik_dan_tidak_mengurangi_nilai(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $this->postJson(route('cbt.student.integrity', $result), ['type' => 'tab_hidden'])->assertOk();
        $this->post(route('cbt.student.submit', $result), ['answers' => [$session->question_snapshot[0]['id'] => 'A']])->assertRedirect();
        $this->assertSame(100.0, $result->fresh()->score);
        $this->assertSame(1, $result->violations()->count());
        $this->actingAs($this->guru)->getJson(route('cbt.integrity.violations', $result))->assertOk();
    }

    public function test_peserta_dibekukan_dan_siswa_dari_kelas_lain_ditolak(): void
    {
        $session = $this->createSession();
        $student = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap, 'nis' => 'BARU001',
            'nama_peserta_didik' => 'Peserta baru', 'kelas' => '6A', 'jenis_kelamin' => 'P', 'aktif' => true,
        ]);
        $newAccount = User::factory()->siswa()->create(['nama_pengguna' => $student->nis]);
        $this->actingAs($newAccount)->post(route('cbt.student.begin', $session))->assertForbidden();
    }

    public function test_siswa_tidak_bisa_mengakses_bank_soal_dan_kunci(): void
    {
        $this->actingAs($this->account)->get(route('cbt.question-banks.api.questions', $this->bank))->assertForbidden();
    }
    public function test_guru_dapat_membuat_bank_soal_dan_sesi_melalui_form(): void
    {
        $this->actingAs($this->guru)->get(route('cbt.question-banks.create'))->assertOk();
        $this->post(route('cbt.question-banks.store'), ['title' => 'Bahasa', 'subject' => 'Bahasa Indonesia'])->assertRedirect();
        $bank = CbtQuestionBank::where('title', 'Bahasa')->firstOrFail();
        $this->post(route('cbt.questions.store', $bank), [
            'prompt' => 'Pilih A', 'options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'], 'correct_answer' => 'A',
        ])->assertRedirect();
        $this->get(route('cbt.question-banks.show', $bank))
            ->assertOk()
            ->assertSee('Pilih A')
            ->assertSee('name="prompt"', false)
            ->assertDontSee('Fitur tambah soal inline');
        $this->get(route('cbt.sessions.create'))->assertOk();
        $this->post(route('cbt.sessions.store'), [
            'title' => 'Bahasa kelas 6', 'bank_id' => $bank->id, 'kelas' => '6A',
            'opens_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'closes_at' => now()->addHour()->format('Y-m-d H:i:s'), 'duration_minutes' => 30,
        ])->assertRedirect();
        $session = CbtTestSession::where('bank_id', $bank->id)->firstOrFail();
        $this->post(route('cbt.sessions.publish', $session))->assertRedirect();
        $this->post(route('cbt.sessions.start', $session))->assertRedirect();
        $this->assertSame('running', $session->fresh()->status);
        $this->get(route('cbt.sessions.monitoring', $session))->assertOk();
    }

    public function test_publikasi_bank_kosong_ditolak(): void
    {
        $this->bank->questions()->delete();
        $session = CbtTestSession::create([
            'guru_id' => $this->guru->id, 'bank_id' => $this->bank->id, 'title' => 'Kosong', 'kelas' => '6A',
            'opens_at' => now(), 'closes_at' => now()->addHour(), 'duration_minutes' => 30,
        ]);
        $this->actingAs($this->guru)->postJson(route('cbt.sessions.publish', $session))->assertUnprocessable();
        $this->assertSame('draft', $session->fresh()->status);
    }

    public function test_guru_tidak_bisa_membuat_sesi_dengan_bank_milik_guru_lain(): void
    {
        $this->actingAs(User::factory()->guru()->create())->postJson(route('cbt.sessions.store'), [
            'title' => 'Asing', 'bank_id' => $this->bank->id, 'kelas' => '6A',
            'opens_at' => now(), 'closes_at' => now()->addHour(), 'duration_minutes' => 30,
        ])->assertUnprocessable();
    }

    public function test_bank_yang_sudah_dipakai_sesi_tidak_dapat_dihapus(): void
    {
        $this->createSession();
        $this->actingAs($this->guru)->delete(route('cbt.question-banks.destroy', $this->bank))->assertStatus(409);
        $this->assertDatabaseHas('cbt_question_banks', ['id' => $this->bank->id]);
    }

    public function test_model_hidden_tidak_merusak_isi_backup_privat(): void
    {
        $session = $this->createSession();
        $this->assertArrayNotHasKey('question_snapshot', $session->toArray());
        $backup = $session->makeVisible($session->getHidden())->toArray();
        $this->assertSame('A', $backup['question_snapshot'][0]['correct_answer']);
        $this->assertContains($this->student->id, $backup['participant_ids']);
        $question = $this->bank->questions()->first();
        $this->assertArrayNotHasKey('correct_answer', $question->toArray());
        $this->assertSame('A', $question->makeVisible($question->getHidden())->toArray()['correct_answer']);
    }

    public function test_get_start_setelah_deadline_langsung_memfinalisasi_dan_redirect_ke_hasil(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);
        $qId = $session->question_snapshot[0]['id'];
        $this->actingAs($this->account)->postJson(route('cbt.student.answer', $result), ['question_id' => $qId, 'answer' => 'A'])->assertOk();

        $this->travel(61)->minutes();
        $this->actingAs($this->account)
            ->get(route('cbt.student.start', $session))
            ->assertRedirect(route('cbt.student.result', $result));
        $this->assertNotNull($result->fresh()->submitted_at);
        $this->assertSame(100.0, $result->fresh()->score);
    }

    public function test_integritas_ditolak_jika_siswa_pindah_guru_atau_sesi_sudah_ditutup(): void
    {
        $session = $this->createSession();
        $result = $this->attempt($session);

        $otherGuru = User::factory()->guru()->create();
        $this->student->update(['guru_id' => $otherGuru->id]);
        $this->actingAs($this->account)
            ->postJson(route('cbt.student.integrity', $result), ['type' => 'tab_hidden'])
            ->assertForbidden();

        $this->student->update(['guru_id' => $this->guru->id]);
        $session->update(['status' => 'completed']);
        $this->actingAs($this->account)
            ->postJson(route('cbt.student.integrity', $result), ['type' => 'tab_hidden'])
            ->assertStatus(409);
    }

    public function test_backup_restore_mempertahankan_timestamp_id_non_urut_dan_sequence(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-18 08:05:00'));
        $opens = \Illuminate\Support\Carbon::parse('2026-10-18 08:00:00');
        $closes = \Illuminate\Support\Carbon::parse('2026-10-18 10:00:00');
        $session = $this->createSession();
        $session->update(['opens_at' => $opens, 'closes_at' => $closes]);
        $result = $this->attempt($session->fresh());
        $qId = $session->question_snapshot[0]['id'];
        $this->actingAs($this->account)->postJson(route('cbt.student.answer', $result), ['question_id' => $qId, 'answer' => 'A'])->assertOk();
        $this->actingAs($this->account)->post(route('cbt.student.submit', $result));

        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('backups');
        (new \App\Jobs\ExportBackupJob())->handle();
        $files = \Illuminate\Support\Facades\Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $json = \Illuminate\Support\Facades\Storage::disk('local')->get($files[0]);

        \App\Models\CbtViolation::query()->delete();
        CbtResult::query()->delete();
        CbtTestSession::query()->delete();
        $this->bank->questions()->delete();
        CbtQuestionBank::query()->delete();

        $tmp = tempnam(sys_get_temp_dir(), 'cbt_bkp_') . '.json';
        file_put_contents($tmp, $json);
        $uploaded = new \Illuminate\Http\UploadedFile($tmp, 'backup.json', 'application/json', null, true);
        $req = \Illuminate\Http\Request::create('/admin/backup/import', 'POST', [], [], ['backup_file' => $uploaded]);
        app(\App\Http\Controllers\Admin\BackupController::class)->import($req);
        @unlink($tmp);

        $restoredSession = CbtTestSession::findOrFail($session->id);
        $this->assertSame('2026-10-18 08:00:00', $restoredSession->opens_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-18 10:00:00', $restoredSession->closes_at->format('Y-m-d H:i:s'));
        $restoredResult = CbtResult::findOrFail($result->id);
        $this->assertSame(100.0, $restoredResult->score);
        $newBank = CbtQuestionBank::create(['guru_id' => $this->guru->id, 'title' => 'Bank Pasca Restore', 'subject' => 'IPS']);
        $this->assertGreaterThan($this->bank->id, $newBank->id);
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('backups');
    }

}
