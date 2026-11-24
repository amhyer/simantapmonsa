<?php

namespace Tests\Feature;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentifyTenantHostTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_domain_pusat_sch_id_co_id_www_dan_app_tidak_dianggap_subdomain_sekolah(): void
    {
        config([
            'simantap.multi_tenant_enabled' => true,
            'app.url' => 'https://simantap.sch.id',
        ]);

        Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40199001',
            'nama_sekolah' => 'SMP Negeri 1 Makassar',
            'subdomain' => 'smpn1',
            'aktif' => true,
        ]);

        // Domain pusat 3-label (.sch.id) beserta www. dan app. harus menuju halaman login pusat (200), BUKAN 404
        foreach ([
            'https://simantap.sch.id/login',
            'https://www.simantap.sch.id/login',
            'https://app.simantap.sch.id/login',
        ] as $centralUrl) {
            $this->get($centralUrl)
                ->assertOk()
                ->assertDontSee('SMP Negeri 1 Makassar');
        }

        // Subdomain sekolah yang valid di depan domain pusat 3-label (.sch.id) harus terdeteksi dengan benar dan menampilkan nama sekolah tersebut
        $this->get('https://smpn1.simantap.sch.id/login')
            ->assertOk()
            ->assertSee('SMP Negeri 1 Makassar');

        // Subdomain sekolah yang tidak terdaftar pada domain pusat 3-label harus menghasilkan 404
        $this->get('https://sekolah-tidak-ada.simantap.sch.id/login')->assertNotFound();

        // Uji juga untuk domain pusat .co.id
        config(['app.url' => 'https://simantap.co.id']);
        $this->get('https://simantap.co.id/login')
            ->assertOk()
            ->assertDontSee('SMP Negeri 1 Makassar');

        $this->get('https://www.simantap.co.id/login')
            ->assertOk()
            ->assertDontSee('SMP Negeri 1 Makassar');

        $this->get('https://smpn1.simantap.co.id/login')
            ->assertOk()
            ->assertSee('SMP Negeri 1 Makassar');
    }
}
