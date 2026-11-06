<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_perintah_artisan_superadmin_create_berhasil(): void
    {
        $this->artisan('superadmin:create', [
            '--user' => 'pusat_admin',
            '--pass' => 'Pusat12345!',
            '--name' => 'Admin Pusat FKKG',
            '--email' => 'pusat@simantap.id',
        ])->assertSuccessful();

        $user = User::where('nama_pengguna', 'pusat_admin')->first();
        $this->assertNotNull($user);
        $this->assertSame('super_admin', $user->peran);
        $this->assertNull($user->tenant_id);
    }

    public function test_superadmin_dapat_mengakses_dashboard_pusat(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $tenant1 = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '10000001',
            'nama_sekolah' => 'Sekolah Satu',
            'subdomain' => 'sekolah1',
            'aktif' => true,
        ]);

        $res = $this->actingAs($superAdmin)->get(route('superadmin.dashboard'));
        $res->assertStatus(200);
        $res->assertSee('Pusat Kontrol Multi-Sekolah');
        $res->assertSee('Sekolah Satu');
    }

    public function test_superadmin_dapat_melihat_katalog_sekolah_dan_toggle_aktif(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '10000002',
            'nama_sekolah' => 'Sekolah Dua',
            'subdomain' => 'sekolah2',
            'aktif' => true,
        ]);

        $res = $this->actingAs($superAdmin)->get(route('superadmin.tenants.index'));
        $res->assertStatus(200);
        $res->assertSee('Sekolah Dua');

        // Toggle nonaktif
        $toggleRes = $this->actingAs($superAdmin)->post(route('superadmin.tenants.toggle', $tenant->uuid));
        $toggleRes->assertRedirect();

        $this->assertFalse($tenant->fresh()->aktif);
    }

    public function test_superadmin_dapat_mendaftarkan_sekolah_baru(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $postData = [
            'npsn' => '40301111',
            'nama_sekolah' => 'SMP Negeri 99 Makassar',
            'subdomain' => 'smpn99',
            'admin_nama' => 'Admin Sembilan Sembilan',
            'admin_user' => 'admin_smpn99',
            'admin_email' => 'admin@smpn99.sch.id',
            'admin_password' => 'Password123!',
        ];

        $res = $this->actingAs($superAdmin)->post(route('superadmin.tenants.store'), $postData);
        $res->assertRedirect(route('superadmin.tenants.index'));

        $this->assertDatabaseHas('tenants', [
            'npsn' => '40301111',
            'subdomain' => 'smpn99',
        ]);

        $this->assertDatabaseHas('users', [
            'nama_pengguna' => 'admin_smpn99',
            'peran' => 'admin',
        ]);
    }

    public function test_superadmin_dapat_melihat_detail_tenant_dan_daftar_pengguna(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '10000003',
            'nama_sekolah' => 'Sekolah Tiga',
            'subdomain' => 'sekolah3',
            'aktif' => true,
        ]);

        $adminSekolah = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'nama_pengguna' => 'admin_sekolah3',
        ]);

        $res = $this->actingAs($superAdmin)->get(route('superadmin.tenants.show', $tenant->uuid));
        $res->assertStatus(200);
        $res->assertSee('Sekolah Tiga');
        $res->assertSee('admin_sekolah3');

        $usersRes = $this->actingAs($superAdmin)->get(route('superadmin.users.index'));
        $usersRes->assertStatus(200);
        $usersRes->assertSee('admin_sekolah3');
    }

    public function test_role_biasa_ditolak_mengakses_superadmin(): void
    {
        $guru = User::factory()->guru()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($guru)
            ->get(route('superadmin.dashboard'))
            ->assertStatus(403);

        $this->actingAs($admin)
            ->get(route('superadmin.dashboard'))
            ->assertStatus(403);
    }
}
