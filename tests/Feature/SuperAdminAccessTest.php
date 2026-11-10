<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_dapat_menghapus_akun_pengguna_individual_dan_bulk(): void
    {
        $superAdmin = User::factory()->create([
            'peran' => 'super_admin',
            'tenant_id' => null,
        ]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Uji Superadmin',
            'npsn' => '99887766',
            'subdomain' => 'sduji',
            'aktif' => true,
        ]);

        $guru1 = User::factory()->guru()->create(['tenant_id' => $tenant->id]);
        $guru2 = User::factory()->guru()->create(['tenant_id' => $tenant->id]);
        $siswa1 = User::factory()->siswa()->create(['tenant_id' => $tenant->id]);

        // 1. Hapus akun individual
        $resDelete1 = $this->actingAs($superAdmin)->delete(route('superadmin.users.destroy', $guru1->id));
        $resDelete1->assertRedirect();
        $resDelete1->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $guru1->id]);

        // 2. Proteksi self-delete: SuperAdmin tidak boleh menghapus akunnya sendiri
        $resSelfDelete = $this->actingAs($superAdmin)->delete(route('superadmin.users.destroy', $superAdmin->id));
        $resSelfDelete->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);

        // 3. Hapus massal (bulk delete)
        $resBulk = $this->actingAs($superAdmin)->post(route('superadmin.users.bulk-destroy'), [
            'user_ids' => [$guru2->id, $siswa1->id],
        ]);
        $resBulk->assertRedirect();
        $resBulk->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $guru2->id]);
        $this->assertDatabaseMissing('users', ['id' => $siswa1->id]);
    }

    public function test_superadmin_dapat_menghapus_sekolah_dan_sekolah_expired_hosting(): void
    {
        $superAdmin = User::factory()->create([
            'peran' => 'super_admin',
            'tenant_id' => null,
        ]);

        // Sekolah A: masih aktif
        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Masih Aktif',
            'npsn' => '11223344',
            'subdomain' => 'sdaktif',
            'aktif' => true,
            'tgl_kedaluwarsa' => now()->addMonths(6)->toDateString(),
        ]);

        // Sekolah B: expired hosting (sudah lewat jatuh tempo)
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Expired Hosting',
            'npsn' => '55667788',
            'subdomain' => 'sdexpired',
            'aktif' => true,
            'tgl_kedaluwarsa' => now()->subDays(5)->toDateString(),
        ]);

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $tenantB->id]);

        // 1. Bersihkan semua sekolah yang expired hosting
        $resBulkExpired = $this->actingAs($superAdmin)->post(route('superadmin.tenants.bulk-destroy-expired'));
        $resBulkExpired->assertRedirect();
        $resBulkExpired->assertSessionHas('success');

        // Sekolah B harus terhapus beserta adminnya
        $this->assertDatabaseMissing('tenants', ['id' => $tenantB->id]);
        $this->assertDatabaseMissing('users', ['id' => $adminB->id]);

        // Sekolah A masih tetap ada
        $this->assertDatabaseHas('tenants', ['id' => $tenantA->id]);
        $this->assertDatabaseHas('users', ['id' => $adminA->id]);

        // 2. Coba Hapus Sekolah A tanpa force -> ditolak karena aktif
        $resDeleteTenantAFail = $this->actingAs($superAdmin)->delete(route('superadmin.tenants.destroy', $tenantA->uuid));
        $resDeleteTenantAFail->assertSessionHas('error');
        $this->assertDatabaseHas('tenants', ['id' => $tenantA->id]);

        // 3. Hapus Sekolah A secara individual dengan force (tombol konfirmasi Super Admin)
        $resDeleteTenantA = $this->actingAs($superAdmin)->delete(route('superadmin.tenants.destroy', $tenantA->uuid), ['force' => true]);
        $resDeleteTenantA->assertRedirect(route('superadmin.tenants.index'));
        $resDeleteTenantA->assertSessionHas('success');

        $this->assertDatabaseMissing('tenants', ['id' => $tenantA->id]);
        $this->assertDatabaseMissing('users', ['id' => $adminA->id]);
    }
}
