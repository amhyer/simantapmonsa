<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantUserCollisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dua_tenant_berbeda_dapat_memiliki_username_yang_sama(): void
    {
        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'Sekolah A',
            'subdomain' => 'sekolaha',
            'aktif' => true,
        ]);

        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'Sekolah B',
            'subdomain' => 'sekolahb',
            'aktif' => true,
        ]);

        // User di Sekolah A
        $userA = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'nama_lengkap' => 'Siswa Sekolah A',
            'nama_pengguna' => 'siswa01',
            'kata_sandi' => Hash::make('passwordA'),
            'peran' => 'siswa',
            'aktif' => true,
        ]);

        // User di Sekolah B dengan username PERSIS SAMA
        $userB = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'nama_lengkap' => 'Siswa Sekolah B',
            'nama_pengguna' => 'siswa01',
            'kata_sandi' => Hash::make('passwordB'),
            'peran' => 'siswa',
            'aktif' => true,
        ]);

        $this->assertDatabaseHas('users', ['id' => $userA->id, 'nama_pengguna' => 'siswa01', 'tenant_id' => $tenantA->id]);
        $this->assertDatabaseHas('users', ['id' => $userB->id, 'nama_pengguna' => 'siswa01', 'tenant_id' => $tenantB->id]);
    }

    public function test_username_kembar_dalam_satu_tenant_tetap_ditolak(): void
    {
        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'Sekolah A',
            'subdomain' => 'sekolaha',
            'aktif' => true,
        ]);

        User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'nama_lengkap' => 'Siswa Pertama',
            'nama_pengguna' => 'siswa_duplikat',
            'kata_sandi' => Hash::make('secret123'),
            'peran' => 'siswa',
            'aktif' => true,
        ]);

        $this->expectException(QueryException::class);

        User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'nama_lengkap' => 'Siswa Kedua',
            'nama_pengguna' => 'siswa_duplikat',
            'kata_sandi' => Hash::make('secret456'),
            'peran' => 'siswa',
            'aktif' => true,
        ]);
    }

    public function test_login_pada_konteks_tenant_mengautentikasi_akun_sekolah_yang_tepat(): void
    {
        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'Sekolah A',
            'subdomain' => 'sekolaha',
            'aktif' => true,
        ]);

        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'Sekolah B',
            'subdomain' => 'sekolahb',
            'aktif' => true,
        ]);

        $userA = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'nama_lengkap' => 'Siswa Sekolah A',
            'nama_pengguna' => 'budi',
            'kata_sandi' => Hash::make('passSekolahA'),
            'peran' => 'siswa',
            'aktif' => true,
        ]);

        $userB = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'nama_lengkap' => 'Siswa Sekolah B',
            'nama_pengguna' => 'budi',
            'kata_sandi' => Hash::make('passSekolahB'),
            'peran' => 'siswa',
            'aktif' => true,
        ]);

        // Login di konteks Sekolah A via header X-Tenant-Subdomain
        $response = $this->withHeaders(['X-Tenant-Subdomain' => 'sekolaha'])
            ->post('/login', [
                'nama_pengguna' => 'budi',
                'kata_sandi' => 'passSekolahA',
            ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($userA);

        // Logout
        $this->post('/logout');
        $this->assertGuest();

        // Login di konteks Sekolah B via header X-Tenant-Subdomain
        $responseB = $this->withHeaders(['X-Tenant-Subdomain' => 'sekolahb'])
            ->post('/login', [
                'nama_pengguna' => 'budi',
                'kata_sandi' => 'passSekolahB',
            ]);

        $responseB->assertRedirect();
        $this->assertAuthenticatedAs($userB);
    }
}
