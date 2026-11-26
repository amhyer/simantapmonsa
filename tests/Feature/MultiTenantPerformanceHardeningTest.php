<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Admin\MassPasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiTenantPerformanceHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        Cache::flush();
        parent::tearDown();
    }

    public function test_setting_helper_terisolasi_per_tenant_dan_menolak_global_pada_multi_tenant_tanpa_konteks(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40360001',
            'nama_sekolah' => 'SDN Setting A',
            'subdomain' => 'sdnsettinga',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40360002',
            'nama_sekolah' => 'SDN Setting B',
            'subdomain' => 'sdnsettingb',
            'aktif' => true,
        ]);

        $adminA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'peran' => 'admin',
            'aktif' => true,
        ]);

        $this->actingAs($adminA);

        // Tanpa TenantContext pada mode multi-tenant -> tulis ditolak & baca mengembalikan default
        TenantContext::clear();
        $this->assertFalse(setting(['tema_ujian' => 'bocor']));
        $this->assertSame('fallback', setting('tema_ujian', 'fallback'));

        // Dengan TenantContext A -> tersimpan khusus tenant A dan tidak bocor ke tenant B
        TenantContext::run($tenantA->id, function (): void {
            $this->assertTrue(setting(['tema_ujian' => 'khusus-a']));
            $this->assertSame('khusus-a', setting('tema_ujian'));
        });

        TenantContext::run($tenantB->id, function (): void {
            $this->assertSame('default-b', setting('tema_ujian', 'default-b'));
        });

        // Bersihkan file sementara hasil test
        @unlink(storage_path('app/settings-t'.$tenantA->id.'.json'));
        @unlink(storage_path('app/settings-t'.$tenantB->id.'.json'));
    }

    public function test_mass_password_reset_memperbarui_sandi_rotasi_token_dan_hapus_sesi(): void
    {
        config(['simantap.multi_tenant_enabled' => false]);

        $admin = User::factory()->create([
            'tenant_id' => null,
            'peran' => 'admin',
            'aktif' => true,
        ]);

        $siswa = User::factory()->create([
            'tenant_id' => null,
            'peran' => 'siswa',
            'aktif' => true,
            'terakhir_masuk' => null,
            'sandi_awal_diterbitkan_at' => null,
            'kata_sandi' => Hash::make('SandiLamaSiswa123'),
            'remember_token' => 'token-lama-siswa',
        ]);

        DB::table('sessions')->insert([
            'id' => 'sess-siswa-mass-reset',
            'user_id' => $siswa->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('a:0:{}'),
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin);

        $result = app(MassPasswordResetService::class)->execute([
            'peran' => ['siswa'],
            'hanya_belum_login' => true,
        ], $admin);

        $this->assertFalse($result['locked']);
        $this->assertSame(1, $result['diproses']);
        $this->assertNotNull($result['ekspor_url']);

        $siswa->refresh();
        $this->assertFalse(Hash::check('SandiLamaSiswa123', $siswa->kata_sandi));
        $this->assertNotSame('token-lama-siswa', $siswa->remember_token);
        $this->assertNotNull($siswa->sandi_awal_diterbitkan_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-siswa-mass-reset']);
    }

    public function test_config_dapodik_bridge_download_url_hanya_menerima_https(): void
    {
        $configPath = base_path('config/dapodik.php');

        putenv('DAPODIK_BRIDGE_DOWNLOAD_URL=http://tidak-aman.example.com/bridge.exe');
        $cfgHttp = require $configPath;
        $this->assertNull($cfgHttp['bridge_download_url']);

        putenv('DAPODIK_BRIDGE_DOWNLOAD_URL=javascript:alert(1)');
        $cfgJs = require $configPath;
        $this->assertNull($cfgJs['bridge_download_url']);

        putenv('DAPODIK_BRIDGE_DOWNLOAD_URL=https://releases.simantap.sch.id/SIMANTAP-Bridge.exe');
        $cfgHttps = require $configPath;
        $this->assertSame('https://releases.simantap.sch.id/SIMANTAP-Bridge.exe', $cfgHttps['bridge_download_url']);

        putenv('DAPODIK_BRIDGE_DOWNLOAD_URL');
    }
}
