<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\DapodikConfig;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Jobs\TarikSemuaPresensiJob;
use App\Support\BackupTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi perbaikan Fase 1 (review keamanan):
 * - IDOR NilaiController::update (siswa tujuan wajib milik guru)
 * - Validasi host Dapodik + masking cf_access
 * - Impersonate super-admin (akun nonaktif ditolak, sesi tercatat)
 * - Throttle login per-akun + per-IP
 * - Backup siswa menyertakan NIK, TLS TKA terverifikasi, API key hash-only
 */
class SecurityHardeningFase1Test extends TestCase
{
    use RefreshDatabase;

    private function buatSiswa(User $guru, string $nis, ?string $dapodikId = null): Siswa
    {
        return Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => $nis,
            'nisn' => '3333333333',
            'nama_peserta_didik' => 'Siswa '.$nis,
            'kelas' => '6.A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
            'dapodik_id' => $dapodikId,
        ]);
    }

    public function test_guru_tidak_dapat_memindahkan_nilai_ke_siswa_guru_lain(): void
    {
        $guruA = User::factory()->guru()->create();
        $guruB = User::factory()->guru()->create();
        $siswaA = $this->buatSiswa($guruA, 'N-A01');
        $siswaB = $this->buatSiswa($guruB, 'N-B01');

        $nilai = Nilai::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guruA->id,
            'siswa_id' => $siswaA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'nama_siswa' => $siswaA->nama_peserta_didik,
            'tanggal' => '2026-11-01',
            'jenis' => 'Tugas',
            'judul_penilaian' => 'Tugas 1',
            'mata_pelajaran' => 'Matematika',
            'nilai' => 90,
        ]);

        $this->actingAs($guruA)->put(route('guru.nilai.update', $nilai), [
            'siswa_id' => $siswaB->id,
            'tanggal' => '2026-11-01',
            'jenis' => 'Tugas',
            'judul_penilaian' => 'Tugas 1',
            'mata_pelajaran' => 'Matematika',
            'nilai' => 90,
        ])->assertSessionHasErrors('siswa_id');

        $this->assertDatabaseHas('nilai', ['id' => $nilai->id, 'siswa_id' => $siswaA->id]);
    }

    public function test_guru_dapat_memperbarui_nilai_ke_siswa_miliknya(): void
    {
        $guru = User::factory()->guru()->create();
        $siswaA = $this->buatSiswa($guru, 'N-A02');
        $siswaB = $this->buatSiswa($guru, 'N-A03');

        $nilai = Nilai::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'siswa_id' => $siswaA->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswaA->nama_peserta_didik,
            'tanggal' => '2026-11-01',
            'jenis' => 'Tugas',
            'judul_penilaian' => 'Tugas 1',
            'mata_pelajaran' => 'Matematika',
            'nilai' => 90,
        ]);

        $this->actingAs($guru)->put(route('guru.nilai.update', $nilai), [
            'siswa_id' => $siswaB->id,
            'tanggal' => '2026-11-02',
            'jenis' => 'Ulangan Harian',
            'judul_penilaian' => 'UH 1',
            'mata_pelajaran' => 'Matematika',
            'nilai' => 85,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nilai', ['id' => $nilai->id, 'siswa_id' => $siswaB->id, 'nilai' => 85]);
    }

    public function test_host_dapodik_berupa_url_ditolak_validasi(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('admin.dapodik.api.config.save'), [
            'npsn' => '12345678',
            'host' => 'https://evil.example.com/path',
        ])->assertStatus(422);
    }

    public function test_cf_access_dimasking_pada_respons_config(): void
    {
        $admin = User::factory()->admin()->create();
        $config = DapodikConfig::getInstance();
        $config->update(['cf_access' => ['CF-Access-Client-Secret' => 'supersecretvalue123']]);

        $response = $this->actingAs($admin)->getJson(route('admin.dapodik.api.config'));
        $response->assertOk();
        $masked = $response->json('data.cf_access.CF-Access-Client-Secret');
        $this->assertNotSame('supersecretvalue123', $masked);
        $this->assertStringContainsString('****', (string) $masked);
    }

    public function test_impersonate_admin_nonaktif_ditolak(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Uji Impersonate',
            'npsn' => '99887766',
            'subdomain' => 'sduji-imp',
            'aktif' => true,
        ]);
        User::factory()->admin()->create(['tenant_id' => $tenant->id, 'aktif' => false]);

        $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.impersonate', $tenant))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_impersonate_admin_aktif_berhasil_dan_mencatat_sesi(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Uji Impersonate 2',
            'npsn' => '99887767',
            'subdomain' => 'sduji-imp2',
            'aktif' => true,
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'aktif' => true]);

        $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.impersonate', $tenant))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('impersonator_id', $superAdmin->id);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_gagal_mencatat_throttle_per_ip_dan_per_akun(): void
    {
        $user = User::factory()->create([
            'nama_pengguna' => 'guru_throttle_uji',
            'kata_sandi' => bcrypt('password-benar'),
            'aktif' => true,
        ]);

        $this->post('/login', [
            'nama_pengguna' => $user->nama_pengguna,
            'kata_sandi' => 'salah',
        ])->assertSessionHasErrors('nama_pengguna');

        $this->assertSame(1, RateLimiter::attempts('login:127.0.0.1'));
        $this->assertSame(1, RateLimiter::attempts('login-account:guru_throttle_uji'));
    }

    public function test_tarik_semua_dikirim_ke_queue_bukan_sinkron(): void
    {
        Bus::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik.semua'))
            ->assertSessionHas('success');

        Bus::assertDispatched(TarikSemuaPresensiJob::class);

        // Job di-fake, jadi lock yang diambil controller tidak dilepas oleh job.
        // Bersihkan agar test lain yang memakai cache array tidak ikut terkunci.
        Cache::flush();
    }

    public function test_backup_siswa_menyertakan_nik(): void
    {
        $except = BackupTables::semua()['siswa']['except'] ?? [];
        $this->assertNotContains('nik', $except);
        $this->assertNotContains('no_kk', $except);
    }

    public function test_api_key_baru_tidak_menyimpan_kunci_polos(): void
    {
        $user = User::factory()->create();
        $result = ApiKey::generate($user->id);

        $this->assertNull($result['model']->key);
        $this->assertSame(hash('sha256', $result['plaintext_key']), $result['model']->key_hash);
    }

    public function test_tka_statistik_tidak_mematikan_verifikasi_tls(): void
    {
        $source = (string) file_get_contents(base_path('app/Services/TkaStatistikService.php'));

        // Nilai pemanggilan method, bukan kata di komentar: dokumentasi kode
        // boleh menyebut "jangan withoutVerifying()" tanpa menggagalkan test.
        $this->assertDoesNotMatchRegularExpression('/->withoutVerifying\s*\(/', $source);
        $this->assertDoesNotMatchRegularExpression('/Http::withoutVerifying\s*\(/', $source);
    }

    public function test_wipe_tidak_memakai_truncate_cascade_dan_berjalan_dalam_transaksi(): void
    {
        $source = (string) file_get_contents(base_path('app/Http/Controllers/Admin/BackupController.php'));

        // Komentar memang menyebut TRUNCATE (menjelaskan larangannya), jadi
        // komentar dibuang lebih dulu — yang dinilai hanya kode.
        $kode = $this->kodeTanpaKomentar($source);

        $this->assertDoesNotMatchRegularExpression('/TRUNCATE/i', $kode);
        $this->assertStringContainsString('DB::transaction', $kode);
        $this->assertStringContainsString('->delete()', $kode);
    }

    /** Buang komentar blok dan komentar penuh-baris agar assertion menilai kode saja. */
    private function kodeTanpaKomentar(string $source): string
    {
        $tanpaBlok = preg_replace('~/\*.*?\*/~s', '', $source) ?? $source;

        $baris = array_filter(
            preg_split('/\R/', $tanpaBlok) ?: [],
            fn (string $isi): bool => preg_match('~^\s*(//|\*)~', $isi) !== 1
        );

        return implode("\n", $baris);
    }

    public function test_foto_siswa_tidak_publik_secara_default(): void
    {
        $this->assertFalse((bool) config('simantap.foto_fallback_public'));
    }

    public function test_skrip_setup_api_key_tidak_menyimpan_kunci_polos(): void
    {
        $source = (string) file_get_contents(base_path('tools/setup_api_key.php'));
        $this->assertStringContainsString("'key' => null", $source);
        // String berkutip ganda akan menginterpolasi $apiKey — harus di-escape.
        $this->assertStringNotContainsString("'key' => \$apiKey", $source);
    }

    public function test_masked_token_mengembalikan_token_tersamar_dari_model(): void
    {
        $admin = User::factory()->admin()->create();
        $config = DapodikConfig::getInstance();
        $config->update(['token' => 'secret-token-987654']);

        $this->assertSame('secr****7654', $config->fresh()->masked_token);

        $this->actingAs($admin)->getJson(route('admin.dapodik.api.config'))
            ->assertOk()
            ->assertJsonPath('data.token', 'secr****7654');
    }

    public function test_host_dapodik_metadata_cloud_dan_loopback_multitenant_ditolak(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Uji SSRF',
            'npsn' => '88776655',
            'subdomain' => 'sduji-ssrf',
            'aktif' => true,
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        foreach (['169.254.169.254', '0.0.0.0', 'metadata.google.internal', '127.0.0.1', 'localhost'] as $blockedHost) {
            $this->actingAs($admin)->postJson(route('admin.dapodik.api.config.save'), [
                'npsn' => '88776655',
                'host' => $blockedHost,
            ])->assertStatus(422);
        }
    }

    public function test_tarik_semua_presensi_job_memiliki_timeout_eksplisit(): void
    {
        $job = new TarikSemuaPresensiJob(null);
        $this->assertSame(300, $job->timeout);
    }
}
