<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminTenantSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_dapat_memperbarui_paket_kuota_dan_domain_kustom(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40307001',
            'nama_sekolah' => 'SMP Negeri 1 Uji',
            'subdomain' => 'smpn1uji',
            'aktif' => true,
        ]);

        $res = $this->actingAs($superAdmin)->put(route('superadmin.tenants.update', $tenant->uuid), [
            'nama_sekolah' => 'SMP Negeri 1 Uji Kustom',
            'domain_kustom' => 'ujian.smpn1uji.sch.id',
            'paket' => 'pro',
            'kuota_siswa' => 750,
            'kuota_storage_mb' => 5120,
            'tgl_kedaluwarsa' => now()->addYear()->toDateString(),
            'catatan_langganan' => 'Pembayaran hosting 1 tahun terkonfirmasi',
        ]);

        $res->assertSessionHas('success');
        $tenant->refresh();

        $this->assertSame('SMP Negeri 1 Uji Kustom', $tenant->nama_sekolah);
        $this->assertSame('ujian.smpn1uji.sch.id', $tenant->domain_kustom);
        $this->assertSame('pro', $tenant->paket);
        $this->assertSame(750, $tenant->kuota_siswa);
        $this->assertSame(5120, $tenant->kuota_storage_mb);
        $this->assertFalse($tenant->isExpired());
        $this->assertNotNull($tenant->sisaHariLangganan());
        $this->assertGreaterThan(300, $tenant->sisaHariLangganan());
    }

    public function test_identify_tenant_mendukung_domain_kustom_sekolah(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40307002',
            'nama_sekolah' => 'SMP Mandiri Jaya',
            'subdomain' => 'smpmandiri',
            'domain_kustom' => 'cbt.smpmandiri.sch.id',
            'aktif' => true,
        ]);

        $guru = User::factory()->guru()->create(['tenant_id' => $tenant->id]);

        // Akses via domain kustom yang sesuai berhasil 200 OK
        $this->actingAs($guru)
            ->withHeaders(['X-Tenant-Domain' => 'cbt.smpmandiri.sch.id'])
            ->get(route('guru.dashboard'))
            ->assertOk();

        // Domain kustom fiktif yang tidak terdaftar ditolak 404
        $this->withHeaders(['X-Tenant-Domain' => 'cbt.sekolah-fiktif.sch.id'])
            ->get('/login')
            ->assertNotFound();
    }

    public function test_sekolah_dengan_langganan_kedaluwarsa_ditolak_402(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40307003',
            'nama_sekolah' => 'SMP Tempo Dulu',
            'subdomain' => 'smptempodulu',
            'tgl_kedaluwarsa' => now()->subDay()->toDateString(),
            'aktif' => true,
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Subdomain' => 'smptempodulu',
        ])->get('/login');

        $response->assertStatus(402);
    }

    public function test_superadmin_dapat_impersonasi_dan_kembali_dari_akun_admin_sekolah(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40307004',
            'nama_sekolah' => 'SMP Impersonate Test',
            'subdomain' => 'smpimpersonate',
            'aktif' => true,
        ]);

        $adminSekolah = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        // 1. SuperAdmin masuk sebagai Admin Sekolah
        $res = $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.impersonate', $tenant->uuid));

        $res->assertRedirect(route('admin.dashboard'));
        $this->assertSame($adminSekolah->id, auth()->id());
        $this->assertSame($superAdmin->id, session('impersonator_id'));

        // 2. Dari dalam akun admin sekolah, SuperAdmin dapat kembali ke sesi Super Admin
        $resLeave = $this->post(route('superadmin.impersonate.leave'));
        $resLeave->assertRedirect(route('superadmin.tenants.show', $tenant->uuid));
        $this->assertSame($superAdmin->id, auth()->id());
        $this->assertFalse(session()->has('impersonator_id'));
    }

    public function test_superadmin_dapat_mereset_kata_sandi_admin_sekolah(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40307005',
            'nama_sekolah' => 'SMP Reset Pass Test',
            'subdomain' => 'smpresetpass',
            'aktif' => true,
        ]);

        $adminSekolah = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $res = $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.reset-admin-password', $tenant->uuid), [
                'password_baru' => 'PasswordBaru2026!',
            ]);

        $res->assertSessionHas('success');
        $adminSekolah->refresh();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('PasswordBaru2026!', $adminSekolah->kata_sandi));
    }

    public function test_superadmin_dapat_memisahkan_daftar_akun_pengguna_per_sekolah_dan_per_wilayah(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $sekolahA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40307010',
            'nama_sekolah' => 'SD Negeri Biringkanaya 1',
            'subdomain' => 'biringkanaya1',
            'wilayah' => 'Kec. Biringkanaya',
            'aktif' => true,
        ]);

        $sekolahB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40307011',
            'nama_sekolah' => 'SD Negeri Panakkukang 2',
            'subdomain' => 'panakkukang2',
            'wilayah' => 'Kec. Panakkukang',
            'aktif' => true,
        ]);

        $siswaA = User::factory()->siswa()->create([
            'tenant_id' => $sekolahA->id,
            'nama_lengkap' => 'Ahmad Biringkanaya',
        ]);

        $guruB = User::factory()->guru()->create([
            'tenant_id' => $sekolahB->id,
            'nama_lengkap' => 'Siti Panakkukang',
        ]);

        // 1. Filter per wilayah Kec. Biringkanaya -> hanya siswaA yang muncul
        $resWilayah = $this->actingAs($superAdmin)
            ->get(route('superadmin.users.index', ['wilayah' => 'Kec. Biringkanaya']));
        $resWilayah->assertOk();
        $resWilayah->assertSee('Ahmad Biringkanaya');
        $resWilayah->assertDontSee('Siti Panakkukang');

        // 2. Filter per sekolah sekolahB -> hanya guruB yang muncul
        $resSekolah = $this->actingAs($superAdmin)
            ->get(route('superadmin.users.index', ['tenant_id' => $sekolahB->id]));
        $resSekolah->assertOk();
        $resSekolah->assertSee('Siti Panakkukang');
        $resSekolah->assertDontSee('Ahmad Biringkanaya');

        // 3. Tab per-sekolah menampilkan rekapitulasi sekolah
        $resTabSekolah = $this->actingAs($superAdmin)
            ->get(route('superadmin.users.index', ['tab' => 'per_sekolah']));
        $resTabSekolah->assertOk();
        $resTabSekolah->assertSee('SD Negeri Biringkanaya 1');
        $resTabSekolah->assertSee('SD Negeri Panakkukang 2');

        // 4. Tab per-wilayah menampilkan rekapitulasi per wilayah
        $resTabWilayah = $this->actingAs($superAdmin)
            ->get(route('superadmin.users.index', ['tab' => 'per_wilayah']));
        $resTabWilayah->assertOk();
        $resTabWilayah->assertSee('Kec. Biringkanaya');
        $resTabWilayah->assertSee('Kec. Panakkukang');
    }
}
