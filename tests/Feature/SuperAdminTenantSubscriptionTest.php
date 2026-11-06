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
}
