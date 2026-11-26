<?php

namespace Tests\Feature;

use App\Jobs\TarikSemuaPresensiJob;
use App\Models\ApiKey;
use App\Models\DapodikConfig;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Dapodik\DapodikClient;
use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\MesinPresensi\PresensiMesinPullService;
use App\Services\PresensiMesinService;
use App\Support\BackupTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        $this->assertGreaterThan($job->timeout, (int) config('queue.connections.database.retry_after'));
    }

    public function test_ssrf_guard_dapodik_memblokir_ipv6_loopback_link_local_ula_dan_ipv4_mapped(): void
    {
        try {
            DapodikClient::useDnsResolver(fn (string $host): array => match ($host) {
                'ipv6-loopback.internal' => ['::1'],
                'ipv6-linklocal.internal' => ['fe80::1'],
                'ipv6-metadata.internal' => ['fd00:ec2::254'],
                'ipv6-ula.internal' => ['fd12:3456:789a::1'],
                'ipv4-mapped-meta.internal' => ['::ffff:169.254.169.254'],
                'ipv4-mapped-loop.internal' => ['::ffff:127.0.0.1'],
                'ipv6-publik.sekolah.id' => ['2001:db8:85a3::8a2e:370:7334'],
                default => [],
            });

            // Link-local, unspecified, dan metadata diblokir baik di mode single maupun multi-school
            foreach (['::', 'fe80::1', 'fd00:ec2::254', '::ffff:169.254.169.254', 'ipv6-linklocal.internal', 'ipv6-metadata.internal', 'ipv4-mapped-meta.internal'] as $blockedAlways) {
                $this->assertNotNull(DapodikClient::validateHostSafety($blockedAlways, false), "Harus diblokir di single-school: {$blockedAlways}");
                $this->assertNotNull(DapodikClient::validateHostSafety($blockedAlways, true), "Harus diblokir di multi-school: {$blockedAlways}");
            }

            // Loopback & ULA IPv6 diblokir pada mode multi-school secara bawaan
            foreach (['::1', '[::1]', 'ipv6-loopback.internal', 'ipv6-ula.internal', 'ipv4-mapped-loop.internal'] as $blockedMulti) {
                $this->assertNotNull(DapodikClient::validateHostSafety($blockedMulti, true), "Harus diblokir di multi-school: {$blockedMulti}");
            }

            // Host publik IPv6 valid diizinkan
            $this->assertNull(DapodikClient::validateHostSafety('ipv6-publik.sekolah.id', true));

            // Opsi config dapodik.allow_loopback_in_multi_school bekerja nyata
            config(['dapodik.allow_loopback_in_multi_school' => true]);
            $this->assertNull(DapodikClient::validateHostSafety('localhost', true));
            $this->assertNull(DapodikClient::validateHostSafety('::1', true));
        } finally {
            config(['dapodik.allow_loopback_in_multi_school' => false]);
            DapodikClient::useDnsResolver(null);
        }
    }

    public function test_dapodik_client_memoize_resolusi_dns_dan_validasi_base_url(): void
    {
        $lookupCalls = 0;

        try {
            DapodikClient::useDnsResolver(function (string $host) use (&$lookupCalls): array {
                $lookupCalls++;

                return ['203.0.113.10'];
            });

            Http::fake([
                '*' => Http::response(['rows' => [['nama' => 'SD Uji Memo', 'npsn' => '12345678']], 'count' => 1]),
            ]);

            $client = new DapodikClient('12345678', 'tok-uji', 'dapodik.sekolah.sch.id', 5774, 'http');
            $client->getSekolah();
            $client->getSekolah();
            $client->getSekolah();

            // Meski memanggil 3 request berturut-turut, DNS resolver hanya dipanggil 1 kali
            $this->assertSame(1, $lookupCalls);
        } finally {
            DapodikClient::useDnsResolver(null);
        }
    }

    public function test_default_host_dapodik_kosong_pada_multitenant_dan_mengembalikan_422_ramah(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Default Host',
            'npsn' => '77889900',
            'subdomain' => 'sd-default-host',
            'aktif' => true,
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        try {
            TenantContext::set($tenant->id);
            $cfg = DapodikConfig::getInstance();
            $this->assertSame('', $cfg->host);
            $cfg->update(['npsn' => '77889900', 'token' => 'token-valid-123456']);
        } finally {
            TenantContext::clear();
        }

        $this->actingAs($admin)
            ->postJson(route('admin.dapodik.api.test-connection'))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_tarik_semua_presensi_job_mencegah_eksekusi_worker_ganda_dan_email_user_dinormalisasi_lowercase(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => '  Admin.HurufBesar@Sekolah.SCH.ID  ',
        ]);
        $this->assertSame('admin.hurufbesar@sekolah.sch.id', $admin->fresh()->email);

        $execLock = Cache::lock(TarikSemuaPresensiJob::executionLockKey($admin->tenant_id), TarikSemuaPresensiJob::TTL_LOCK);
        $this->assertTrue($execLock->get());

        try {
            $readerMock = $this->mock(AttendanceDatabaseReader::class);
            $readerMock->shouldNotReceive('rentang');

            (new TarikSemuaPresensiJob($admin->id, $admin->tenant_id))->handle(
                $readerMock,
                app(PresensiMesinService::class),
                app(PresensiMesinPullService::class),
            );
        } finally {
            $execLock->release();
        }
    }
}
