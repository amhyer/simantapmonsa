<?php

namespace Tests\Feature;

use App\Models\Semester;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Presensi\IdentityCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function createTenant(string $subdomain, string $nama): Tenant
    {
        return Tenant::query()->create([
            'nama_sekolah' => $nama,
            'subdomain' => $subdomain,
            'status' => 'active',
        ]);
    }

    public function test_semester_aktif_label_cache_is_isolated_per_tenant_and_single_mode(): void
    {
        Cache::flush();

        $tenantA = $this->createTenant('cache-a', 'SD Cache A');
        $tenantB = $this->createTenant('cache-b', 'SD Cache B');

        $semester = Semester::query()->create([
            'semester_id' => '20241',
            'tahun_ajaran' => '2024/2025',
            'nama_semester' => 'ganjil',
        ]);

        TenantContext::set($tenantA->id);
        $this->assertSame('2024/2025 (ganjil)', semesterAktifLabel());

        // Simulasikan data berbeda untuk tenant B tanpa menghapus cache tenant A.
        $semester->update([
            'semester_id' => '20252',
            'tahun_ajaran' => '2025/2026',
            'nama_semester' => 'genap',
        ]);

        TenantContext::set($tenantB->id);
        $this->assertSame(
            '2025/2026 (genap)',
            semesterAktifLabel(),
            'Cache semesterAktifLabel tenant A tidak boleh bocor ke tenant B.'
        );

        // Tenant A tetap memakai cache terisolasi miliknya sendiri.
        TenantContext::set($tenantA->id);
        $this->assertSame('2024/2025 (ganjil)', semesterAktifLabel());

        // Mode single-school terisolasi pada scope "single".
        TenantContext::clear();
        $this->assertSame('2025/2026 (genap)', semesterAktifLabel());
    }

    public function test_setting_cache_key_is_scoped_per_tenant(): void
    {
        Cache::flush();

        $tenantA = $this->createTenant('set-a', 'SD Set A');
        $tenantB = $this->createTenant('set-b', 'SD Set B');

        Cache::put('app_settings:t'.$tenantA->id, ['nama_sekolah_cache' => 'SD Tenant A'], 300);
        Cache::put('app_settings:t'.$tenantB->id, ['nama_sekolah_cache' => 'SD Tenant B'], 300);

        TenantContext::set($tenantA->id);
        $this->assertSame('SD Tenant A', setting('nama_sekolah_cache'));

        TenantContext::set($tenantB->id);
        $this->assertSame(
            'SD Tenant B',
            setting('nama_sekolah_cache'),
            'Cache setting() tenant A tidak boleh bocor ke tenant B.'
        );
    }

    public function test_identity_card_svg_qr_uses_bounded_ttl_and_tenant_scope(): void
    {
        Cache::flush();

        $tenantA = $this->createTenant('qr-a', 'SD QR A');
        TenantContext::set($tenantA->id);

        $payload = 'SIMANTAP:SISWA:1:test-payload';
        $size = 156;
        $expectedKey = 'simantap_qr_svg:t'.$tenantA->id.':'.md5($payload.':'.$size);

        Cache::shouldReceive('remember')
            ->once()
            ->with($expectedKey, 86400, \Closure::class)
            ->andReturn('<svg>ok</svg>');

        $service = app(IdentityCardService::class);
        $this->assertSame('<svg>ok</svg>', $service->svgQr($payload, $size));
    }
}
