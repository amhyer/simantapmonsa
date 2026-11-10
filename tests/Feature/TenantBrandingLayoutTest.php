<?php

namespace Tests\Feature;

use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantBrandingLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_displays_tenant_school_name_for_tenant_user(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '10203040',
            'nama_sekolah' => 'SD Negeri 10 Makassar',
            'subdomain' => 'sdn10mks',
            'aktif' => true,
        ]);

        $admin = User::factory()->create([
            'nama_pengguna' => 'admin_sdn10',
            'peran' => 'admin',
            'tenant_id' => $tenant->id,
            'aktif' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('SD Negeri 10 Makassar');
        $response->assertDontSee('UPTD SDN Unggulan Mongisidi 1');

        // Test juga di layout admin-with-tabs (misal menu Dapodik)
        $tabResponse = $this->actingAs($admin)->get(route('admin.dapodik.index'));
        $tabResponse->assertOk();
        $tabResponse->assertSee('SD Negeri 10 Makassar');
    }

    public function test_sidebar_displays_superadmin_label_for_superadmin(): void
    {
        $superAdmin = User::factory()->create([
            'nama_pengguna' => 'superadmin_pusat',
            'peran' => 'super_admin',
            'tenant_id' => null,
            'aktif' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('superadmin.dashboard'));

        $response->assertOk();
        $response->assertSee('Administrator Sistem');
    }

    public function test_sidebar_falls_back_to_default_when_no_tenant(): void
    {
        $admin = User::factory()->create([
            'nama_pengguna' => 'admin_default',
            'peran' => 'admin',
            'tenant_id' => null,
            'aktif' => true,
        ]);

        SekolahSettings::create([
            'guru_id' => $admin->id,
            'nama_sekolah' => 'UPTD SPF SD Negeri Unggulan Mongisidi 1',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('UPTD SPF SD Negeri Unggulan Mongisidi 1');
    }
}
