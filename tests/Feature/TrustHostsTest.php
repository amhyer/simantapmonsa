<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(TrustHosts::CUSTOM_DOMAINS_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        Cache::forget(TrustHosts::CUSTOM_DOMAINS_CACHE_KEY);
        parent::tearDown();
    }

    public function test_host_tidak_dikenal_ditolak_400_dan_mencegah_host_header_injection_reset_sandi(): void
    {
        Notification::fake();
        config([
            'app.url' => 'https://simantap.sch.id',
            'security.trust_hosts_enabled' => true,
            'security.trusted_hosts' => '',
        ]);

        User::factory()->create([
            'email' => 'korban@sekolah.sch.id',
            'aktif' => true,
        ]);

        $this->post('http://evil.attacker.com/lupa-sandi', ['login' => 'korban@sekolah.sch.id'])
            ->assertStatus(400);

        Notification::assertNothingSent();

        // API endpoint juga mengembalikan JSON 400
        $this->postJson('http://evil.attacker.com/api/dapodik/ping')
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_host_app_url_dan_subdomainnya_diizinkan(): void
    {
        config([
            'app.url' => 'https://simantap.sch.id',
            'simantap.multi_tenant_enabled' => true,
            'security.trust_hosts_enabled' => true,
        ]);

        Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40350001',
            'nama_sekolah' => 'SDN Sub Trust',
            'subdomain' => 'sdnsubtrust',
            'aktif' => true,
        ]);

        $this->get('http://simantap.sch.id/login')
            ->assertOk();

        $this->get('http://sdnsubtrust.simantap.sch.id/login')
            ->assertOk();
    }

    public function test_domain_kustom_tenant_aktif_diizinkan_dan_nonaktif_ditolak(): void
    {
        config([
            'app.url' => 'https://simantap.sch.id',
            'simantap.multi_tenant_enabled' => true,
            'security.trust_hosts_enabled' => true,
        ]);

        Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40350002',
            'nama_sekolah' => 'SDN Custom Aktif',
            'subdomain' => 'sdncustomaktif',
            'domain_kustom' => 'portal.sdncustom.sch.id',
            'aktif' => true,
        ]);

        Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40350003',
            'nama_sekolah' => 'SDN Custom Nonaktif',
            'subdomain' => 'sdncustomoff',
            'domain_kustom' => 'nonaktif.sdncustom.sch.id',
            'aktif' => false,
        ]);

        Cache::forget(TrustHosts::CUSTOM_DOMAINS_CACHE_KEY);

        $this->get('http://portal.sdncustom.sch.id/login')
            ->assertOk();

        $this->get('http://nonaktif.sdncustom.sch.id/login')
            ->assertStatus(400);
    }

    public function test_trusted_hosts_tambahan_exact_dan_wildcard_diizinkan(): void
    {
        config([
            'app.url' => 'https://simantap.sch.id',
            'security.trust_hosts_enabled' => true,
            'security.trusted_hosts' => 'portal.dinas.go.id,*.pendidikan.go.id',
        ]);

        $this->get('http://portal.dinas.go.id/login')
            ->assertOk();

        $this->get('http://mks.pendidikan.go.id/login')
            ->assertOk();

        $this->get('http://pendidikan.go.id/login')
            ->assertStatus(400);
    }

    public function test_cache_domain_kustom_otomatis_dihapus_saat_tenant_diubah_atau_dinonaktifkan(): void
    {
        config([
            'app.url' => 'https://simantap.sch.id',
            'simantap.multi_tenant_enabled' => true,
            'security.trust_hosts_enabled' => true,
        ]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40350009',
            'nama_sekolah' => 'SDN Cache Bust',
            'subdomain' => 'sdncachebust',
            'domain_kustom' => 'awal.sdncache.sch.id',
            'aktif' => true,
        ]);

        $this->get('http://awal.sdncache.sch.id/login')->assertOk();

        // Ubah domain_kustom tanpa manual Cache::forget
        $tenant->update(['domain_kustom' => 'baru.sdncache.sch.id']);
        $this->get('http://baru.sdncache.sch.id/login')->assertOk();
        $this->get('http://awal.sdncache.sch.id/login')->assertStatus(400);

        // Nonaktifkan tenant -> domain kustom langsung ditolak
        $tenant->update(['aktif' => false]);
        $this->get('http://baru.sdncache.sch.id/login')->assertStatus(400);
    }
}
