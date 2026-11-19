<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tes karakterisasi perilaku IdentifyTenant + EnsureSchoolContext
 * saat SIMANTAP_MULTI_TENANT=false (mendokumentasikan perilaku saat ini).
 */
class SingleSchoolContextCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_school_without_tenants_allows_null_tenant_admin(): void
    {
        config(['simantap.multi_tenant_enabled' => false]);

        $admin = User::factory()->create([
            'peran' => 'admin',
            'tenant_id' => null,
            'tenant_owned' => false,
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_single_school_with_existing_tenant_row_blocks_null_tenant_admin_with_403(): void
    {
        config(['simantap.multi_tenant_enabled' => false]);

        Tenant::create([
            'npsn' => '88887777',
            'nama_sekolah' => 'Sekolah Lama',
            'subdomain' => 'sekolah-lama',
            'aktif' => true,
        ]);

        $admin = User::factory()->create([
            'peran' => 'admin',
            'tenant_id' => null,
            'tenant_owned' => false,
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_single_school_with_existing_tenant_row_allows_admin_bound_to_active_tenant_without_subdomain(): void
    {
        config(['simantap.multi_tenant_enabled' => false]);

        $tenant = Tenant::create([
            'npsn' => '88887778',
            'nama_sekolah' => 'Sekolah Aktif',
            'subdomain' => 'sekolah-aktif',
            'aktif' => true,
        ]);

        $admin = User::factory()->create([
            'peran' => 'admin',
            'tenant_id' => $tenant->id,
            'tenant_owned' => true,
            'aktif' => true,
            'force_password_change' => false,
        ]);

        // IdentifyTenant melakukan fallback ke $user->tenant_id dan menyetel TenantContext,
        // sehingga EnsureSchoolContext meloloskan request meski tanpa subdomain.
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();
    }
}
