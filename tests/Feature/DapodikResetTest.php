<?php

namespace Tests\Feature;

use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DapodikResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sekolah_dapat_mengosongkan_data_dapodik_sekolahnya_sendiri(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenantA = Tenant::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'nama_sekolah' => 'SD Negeri 1',
            'npsn' => '11111111',
            'subdomain' => 'sdn1',
            'aktif' => true,
        ]);

        $tenantB = Tenant::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'nama_sekolah' => 'SD Negeri 2',
            'npsn' => '22222222',
            'subdomain' => 'sdn2',
            'aktif' => true,
        ]);

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $guruDapodikA = User::factory()->guru()->create([
            'tenant_id' => $tenantA->id,
            'dapodik_id' => (string) \Illuminate\Support\Str::uuid(),
            'nama_lengkap' => 'Guru Dapodik A',
        ]);
        $userSiswaA = User::factory()->siswa()->create([
            'tenant_id' => $tenantA->id,
            'nama_pengguna' => '1001',
            'nama_lengkap' => 'Siswa A',
        ]);

        $guruDapodikB = User::factory()->guru()->create([
            'tenant_id' => $tenantB->id,
            'dapodik_id' => (string) \Illuminate\Support\Str::uuid(),
            'nama_lengkap' => 'Guru Dapodik B',
        ]);

        $sem = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        // Data Sekolah A
        $rombelA = Rombel::create([
            'tenant_id' => $tenantA->id,
            'semester_id' => $sem->id,
            'dapodik_id' => (string) \Illuminate\Support\Str::uuid(),
            'nama_rombel' => 'Kelas 1.A',
            'tingkat' => '1',
        ]);
        $siswaA = Siswa::create([
            'tenant_id' => $tenantA->id,
            'semester_id' => $sem->id,
            'dapodik_id' => (string) \Illuminate\Support\Str::uuid(),
            'rombel_id' => $rombelA->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'nis' => '1001',
            'nisn' => '1111111111',
            'nama_peserta_didik' => 'Siswa A',
            'kelas' => 'Kelas 1.A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        // Data Sekolah B
        $rombelB = Rombel::create([
            'tenant_id' => $tenantB->id,
            'semester_id' => $sem->id,
            'dapodik_id' => (string) \Illuminate\Support\Str::uuid(),
            'nama_rombel' => 'Kelas 1.B',
            'tingkat' => '1',
        ]);
        $siswaB = Siswa::create([
            'tenant_id' => $tenantB->id,
            'semester_id' => $sem->id,
            'dapodik_id' => (string) \Illuminate\Support\Str::uuid(),
            'rombel_id' => $rombelB->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'nis' => '2001',
            'nisn' => '2222222222',
            'nama_peserta_didik' => 'Siswa B',
            'kelas' => 'Kelas 1.B',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);

        // Request reset dengan teks konfirmasi salah -> harus gagal validasi
        TenantContext::set($tenantA->id);
        $resFail = $this->actingAs($adminA)->post(route('admin.dapodik.reset'), [
            'konfirmasi' => 'SALAH',
            'password' => 'password',
        ]);
        $resFail->assertSessionHasErrors('konfirmasi');

        // Request reset valid
        $resSuccess = $this->actingAs($adminA)->post(route('admin.dapodik.reset'), [
            'konfirmasi' => 'KOSONGKAN DATA DAPODIK',
            'password' => 'password',
        ]);
        $resSuccess->assertRedirect(route('admin.dapodik.index'));
        $resSuccess->assertSessionHas('success');

        // Pastikan Sekolah A kosong (Siswa, Rombel, User Dapodik terhapus)
        $this->assertDatabaseMissing('siswa', ['id' => $siswaA->id]);
        $this->assertDatabaseMissing('rombel', ['id' => $rombelA->id]);
        $this->assertDatabaseMissing('users', ['id' => $guruDapodikA->id]);
        $this->assertDatabaseMissing('users', ['id' => $userSiswaA->id]);

        // Pastikan Admin Sekolah A TETAP ADA
        $this->assertDatabaseHas('users', ['id' => $adminA->id]);

        // Pastikan Sekolah B TETAP UTUH (Data isolasi multi-tenant terjaga 100%)
        $this->assertDatabaseHas('siswa', ['id' => $siswaB->id]);
        $this->assertDatabaseHas('rombel', ['id' => $rombelB->id]);
        $this->assertDatabaseHas('users', ['id' => $guruDapodikB->id]);
    }

    public function test_admin_sekolah_dapat_melihat_halaman_dan_mengunduh_bridge(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenant = Tenant::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'nama_sekolah' => 'SD Negeri 1',
            'npsn' => '11111111',
            'subdomain' => 'sdn1',
            'aktif' => true,
        ]);

        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $resIndex = $this->actingAs($admin)->get(route('admin.dapodik.index'));
        $resIndex->assertOk();
        $resIndex->assertSee('Aplikasi Desktop SIMANTAP Bridge');
        $resIndex->assertSee(route('admin.dapodik.download-bridge'));

        $resDownload = $this->actingAs($admin)->get(route('admin.dapodik.download-bridge'));
        $resDownload->assertOk();
        $this->assertStringContainsString('SIMANTAP-Bridge.exe', $resDownload->headers->get('content-disposition', ''));
    }
}
