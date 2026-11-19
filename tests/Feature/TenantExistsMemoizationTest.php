<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantExistsMemoizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_guru_dashboards_run_at_most_one_tenants_exists_query_per_request(): void
    {
        $admin = User::factory()->create([
            'peran' => 'admin',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guru = User::factory()->create([
            'peran' => 'guru',
            'aktif' => true,
            'force_password_change' => false,
        ]);

        // Warm-up cache dashboard admin terlebih dahulu
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // Reset scoped cache untuk simulasi request baru yang bersih
        Tenant::forgetExistsCache();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $adminLog = DB::getQueryLog();
        DB::disableQueryLog();

        $adminExistsCount = collect($adminLog)->filter(
            fn (array $q): bool => str_contains(strtolower($q['query']), 'from "tenants"') && str_contains(strtolower($q['query']), 'exists')
        )->count();

        $this->assertLessThanOrEqual(1, $adminExistsCount, "Query exists(tenants) di /admin/dashboard seharusnya <= 1, aktual: {$adminExistsCount}");

        Tenant::forgetExistsCache();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($guru)->get(route('guru.dashboard'))->assertOk();
        $guruLog = DB::getQueryLog();
        DB::disableQueryLog();

        $guruExistsCount = collect($guruLog)->filter(
            fn (array $q): bool => str_contains(strtolower($q['query']), 'from "tenants"') && str_contains(strtolower($q['query']), 'exists')
        )->count();

        $this->assertLessThanOrEqual(1, $guruExistsCount, "Query exists(tenants) di /guru/dashboard seharusnya <= 1, aktual: {$guruExistsCount}");

        fwrite(STDOUT, sprintf(
            "S3_TENANT_EXISTS: admin_warm_total=%d (exists=%d), guru_total=%d (exists=%d)\n",
            count($adminLog),
            $adminExistsCount,
            count($guruLog),
            $guruExistsCount
        ));
    }

    public function test_memoized_tenant_existence_resets_when_tenant_created_or_deleted_in_same_request(): void
    {
        $this->assertFalse(Tenant::hasAnyTenant());

        $tenant = Tenant::create([
            'npsn' => '44001111',
            'nama_sekolah' => 'Sekolah Reset Cache',
            'subdomain' => 'reset-cache-test',
            'aktif' => true,
        ]);

        $this->assertTrue(Tenant::hasAnyTenant());

        $tenant->delete();

        $this->assertFalse(Tenant::hasAnyTenant());
    }
}
