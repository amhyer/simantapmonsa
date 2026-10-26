<?php

namespace Tests\Feature;

use App\Jobs\ExportBackupJob;
use App\Services\Backup\BackupStreamWriter;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Drill restore harus membuktikan prosedur Import yang sama terhadap database
 * sementara: jumlah baris, kelengkapan ID, dan relasi induk-anak. Database
 * aplikasi tidak boleh berubah.
 */
class BackupRestoreDrillTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'backup.disk' => 'local',
            'backup.direktori' => 'backups',
        ]);

        $this->guru = User::factory()->guru()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id,
            'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'DRILL01',
            'nama_peserta_didik' => 'Siswa Drill',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $this->buatDataCbt();
        $this->artisan('exam:backfill')->assertSuccessful();
    }

    public function test_drill_restore_cocok_dan_tidak_menyentuh_database_aplikasi(): void
    {
        $file = $this->buatBackup();
        $sebelum = [
            'siswa' => Siswa::count(),
            'cbt_results' => CbtResult::count(),
            'exam_attempts' => \App\Models\Exam\Attempt::count(),
        ];

        $this->artisan('simantap:backup:drill', ['--file' => $file])
            ->expectsOutputToContain('Drill restore cocok')
            ->assertSuccessful();

        $this->assertSame($sebelum['siswa'], Siswa::count());
        $this->assertSame($sebelum['cbt_results'], CbtResult::count());
        $this->assertSame($sebelum['exam_attempts'], \App\Models\Exam\Attempt::count());
    }

    public function test_relasi_yatim_di_backup_terdeteksi(): void
    {
        $file = $this->buatBackup();
        $data = json_decode(Storage::disk('local')->get($file), true, 512, JSON_THROW_ON_ERROR);

        $this->assertNotEmpty($data['exam_answers']);
        $data['exam_answers'][0]['attempt_id'] = 999999;
        Storage::disk('local')->put($file, json_encode($data, JSON_THROW_ON_ERROR));

        $this->artisan('simantap:backup:drill', ['--file' => $file])
            ->expectsOutputToContain('Drill restore gagal')
            ->assertFailed();
    }

    public function test_tanpa_berkas_backup_perintah_gagal_dengan_pesan_jelas(): void
    {
        $this->artisan('simantap:backup:drill')
            ->expectsOutputToContain('Tidak ada berkas backup')
            ->assertFailed();
    }

    public function test_json_memuat_ringkasan_tabel_dan_relasi(): void
    {
        $file = $this->buatBackup();

        $this->artisan('simantap:backup:drill', ['--file' => $file, '--json' => true])
            ->expectsOutputToContain('"ok": true')
            ->assertSuccessful();
    }

    private function buatBackup(): string
    {
        (new ExportBackupJob())->handle(app(BackupStreamWriter::class));

        $berkas = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path) => str_ends_with($path, '.json'))
            ->values();

        $this->assertCount(1, $berkas);

        return $berkas->first();
    }

    private function buatDataCbt(): void
    {
        $bank = CbtQuestionBank::create([
            'guru_id' => $this->guru->id,
            'title' => 'Bank Drill',
            'subject' => 'IPA',
        ]);
        $question = $bank->questions()->create([
            'prompt' => 'Soal drill',
            'options' => ['A' => 'a', 'B' => 'b'],
            'correct_answer' => 'A',
        ]);
        $akun = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);
        $session = CbtTestSession::create([
            'guru_id' => $this->guru->id,
            'bank_id' => $bank->id,
            'title' => 'Sesi Drill',
            'kelas' => '6A',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'duration_minutes' => 30,
            'status' => 'completed',
        ]);
        $result = CbtResult::create([
            'session_id' => $session->id,
            'siswa_id' => $this->siswa->id,
            'user_id' => $akun->id,
            'started_at' => now()->subMinutes(20),
            'deadline_at' => now()->addMinutes(10),
            'submitted_at' => now(),
            'answers' => [$question->id => 'A'],
            'correct_count' => 1,
            'score' => 100.0,
        ]);
        $result->violations()->create(['type' => 'tab_hidden']);
    }
}
