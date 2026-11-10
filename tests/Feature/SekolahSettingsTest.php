<?php

namespace Tests\Feature;

use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SekolahSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_sekolah_bersih_dan_dapat_dikosongkan(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Negeri 1 Makassar',
            'npsn' => '40313912',
            'subdomain' => 'sdn1',
            'aktif' => true,
        ]);

        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        // Buat data identitas sekolah
        SekolahSettings::create([
            'tenant_id' => $tenant->id,
            'guru_id' => $admin->id,
            'nama_sekolah' => 'SD Negeri 1 Makassar',
            'npsn' => '40313912',
            'alamat' => 'Jl. Pendidikan',
        ]);

        // Cari NPSN
        $resSearch = $this->actingAs($admin)->postJson(route('admin.sekolah.search'), [
            'npsn' => '40313912',
        ]);
        $resSearch->assertOk()->assertJson(['success' => true]);

        // Reset identitas sekolah
        $resReset = $this->actingAs($admin)->post(route('admin.sekolah.reset'));
        $resReset->assertRedirect(route('admin.sekolah.index'));
        $resReset->assertSessionHas('success');

        // Pastikan tabel sekolah_settings kosong untuk tenant ini
        $this->assertDatabaseMissing('sekolah_settings', ['tenant_id' => $tenant->id]);

        // Cari kembali setelah direset: harus mengembalikan data tidak ditemukan
        $resSearchAfter = $this->actingAs($admin)->postJson(route('admin.sekolah.search'), [
            'npsn' => '40313912',
        ]);
        $resSearchAfter->assertOk()->assertJson(['success' => false]);
    }
}
