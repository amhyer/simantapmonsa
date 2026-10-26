<?php

namespace Tests\Feature;

use App\Models\DapodikConfig;
use App\Models\SekolahSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pemeriksaan integrasi harus read-only dan memberi exit code yang bisa dipakai
 * pipeline staging. Tes ini tidak pernah memanggil jaringan nyata.
 */
class IntegrationCheckTest extends TestCase
{
    use RefreshDatabase;

    private ?string $credentialsFile = null;

    protected function tearDown(): void
    {
        if ($this->credentialsFile && is_file($this->credentialsFile)) {
            @unlink($this->credentialsFile);
        }

        parent::tearDown();
    }

    public function test_dapodik_tanpa_token_gagal_dengan_pesan_jelas(): void
    {
        $config = DapodikConfig::getInstance();
        $config->update(['npsn' => '12345678', 'token' => null, 'host' => 'localhost', 'port' => 5774, 'protocol' => 'http']);

        $this->artisan('integrasi:dapodik:cek')
            ->expectsOutputToContain('Token WebService Dapodik belum diisi')
            ->assertFailed();
    }

    public function test_dapodik_live_berhasil_dengan_http_fake(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [['nama' => 'SD Negeri Contoh']]], 200),
        ]);

        $config = DapodikConfig::getInstance();
        $config->update([
            'npsn' => '12345678',
            'token' => 'token-rahasia-panjang',
            'host' => 'dapodik.test',
            'port' => 5774,
            'protocol' => 'http',
        ]);

        $this->assertSame('12345678', $config->fresh()->npsn);
        $this->assertSame('dapodik.test', $config->fresh()->host);

        $exit = Artisan::call('integrasi:dapodik:cek', ['--live' => true, '--json' => true]);
        $laporan = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exit, json_encode($laporan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->assertTrue($laporan['ok']);
        $this->assertSame('SD Negeri Contoh', $laporan['nama_sekolah']);
        $this->assertStringNotContainsString('token-rahasia-panjang', Artisan::output());
        Http::assertSentCount(1);
    }

    public function test_sheets_tanpa_pengaturan_gagal(): void
    {
        $this->artisan('integrasi:sheets:cek')
            ->expectsOutputToContain('Pengaturan sekolah belum ada')
            ->assertFailed();
    }

    public function test_sheets_dengan_kredensial_dan_sambungan_lengkap_lolos_tanpa_live(): void
    {
        $this->buatSekolahDenganSheet();
        $this->buatKredensial();

        $this->artisan('integrasi:sheets:cek')
            ->expectsOutputToContain('Konfigurasi Google Sheets siap')
            ->assertSuccessful();
    }

    public function test_sheets_dengan_kredensial_rusak_gagal(): void
    {
        $this->buatSekolahDenganSheet();

        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'google-creds-');
        File::put($this->credentialsFile, json_encode(['client_email' => 'x@example.test', 'private_key' => 'bukan-kunci']));
        config(['google.service_account_path' => $this->credentialsFile]);

        $this->artisan('integrasi:sheets:cek')
            ->expectsOutputToContain('private_key service account tidak valid')
            ->assertFailed();
    }

    private function buatSekolahDenganSheet(): void
    {
        $guru = User::factory()->guru()->create();

        SekolahSettings::create([
            'guru_id' => $guru->id,
            'nama_sekolah' => 'SD Negeri Contoh',
            'pengaturan' => [
                'google_sheet' => [
                    'terhubung' => true,
                    'spreadsheet_id' => 'spreadsheet-contoh',
                    'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/spreadsheet-contoh/edit',
                ],
            ],
        ]);
    }

    private function buatKredensial(): void
    {
        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'google-creds-');
        File::put($this->credentialsFile, json_encode([
            'client_email' => 'simantap@project.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\ncontoh\n-----END PRIVATE KEY-----\n",
        ]));

        config(['google.service_account_path' => $this->credentialsFile]);
    }
}
