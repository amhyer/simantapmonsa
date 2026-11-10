<?php

namespace Tests\Feature;

use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterSekolahTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_daftar_sekolah_dapat_dibuka_oleh_tamu(): void
    {
        $response = $this->get(route('sekolah.register'));
        $response->assertStatus(200);
        $response->assertSee('Pendaftaran Akun Sekolah Baru');
    }

    public function test_sekolah_dan_admin_baru_berhasil_didaftarkan(): void
    {
        $payload = [
            'nama_sekolah' => 'SMP Negeri 55 Makassar',
            'npsn' => '40319999',
            'jenjang' => 'SMP',
            'kepala_sekolah' => 'Dr. H. Muhammad Ilyas, M.Pd.',
            'subdomain' => 'smpn55mks',
            'kota' => 'Kota Makassar',
            'alamat' => 'Jl. Pendidikan No. 55',
            'nama_lengkap' => 'Muhammad Akbar (Operator Sekolah)',
            'nama_pengguna' => 'operator_smpn55',
            'email' => 'operator@smpn55.sch.id',
            'telepon' => '081234567890',
            'kata_sandi' => 'PasswordSuper123!',
            'kata_sandi_confirmation' => 'PasswordSuper123!',
        ];

        $response = $this->post(route('sekolah.register.store'), $payload);

        $response->assertRedirect(route('admin.dashboard'));

        // Cek Tenant berhasil dibuat
        $this->assertDatabaseHas('tenants', [
            'npsn' => '40319999',
            'subdomain' => 'smpn55mks',
            'nama_sekolah' => 'SMP Negeri 55 Makassar',
            'aktif' => 1,
        ]);

        $tenant = Tenant::where('npsn', '40319999')->first();
        $this->assertNotNull($tenant);

        // Cek Admin User (Operator Sekolah) berhasil dibuat dan terikat ke tenant
        $this->assertDatabaseHas('users', [
            'nama_pengguna' => 'operator_smpn55',
            'email' => 'operator@smpn55.sch.id',
            'peran' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        // Cek SekolahSettings terbuat dengan Kepala Sekolah
        $this->assertDatabaseHas('sekolah_settings', [
            'tenant_id' => $tenant->id,
            'npsn' => '40319999',
            'nama_sekolah' => 'SMP Negeri 55 Makassar',
            'kepala_sekolah' => 'Dr. H. Muhammad Ilyas, M.Pd.',
        ]);

        // Pastikan login otomatis aktif
        $this->assertAuthenticated();
        $this->assertEquals('operator_smpn55', auth()->user()->nama_pengguna);
    }

    public function test_validasi_npsn_duplikat_ditolak(): void
    {
        Tenant::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'npsn' => '40319999',
            'nama_sekolah' => 'Sekolah Sudah Ada',
            'subdomain' => 'sekolah-lama',
            'aktif' => true,
        ]);

        $payload = [
            'nama_sekolah' => 'Sekolah Mencoba Duplikat',
            'npsn' => '40319999',
            'jenjang' => 'SD',
            'kepala_sekolah' => 'Kepsek Uji Coba',
            'subdomain' => 'sekolah-baru',
            'nama_lengkap' => 'Admin Baru',
            'nama_pengguna' => 'admin_baru',
            'email' => 'adminbaru@sekolah.sch.id',
            'kata_sandi' => 'Password123!',
            'kata_sandi_confirmation' => 'Password123!',
        ];

        $response = $this->post(route('sekolah.register.store'), $payload);
        $response->assertSessionHasErrors(['npsn']);
    }

    public function test_validasi_nama_kepala_sekolah_wajib_diisi(): void
    {
        $payload = [
            'nama_sekolah' => 'SD Negeri Contoh',
            'npsn' => '40318888',
            'jenjang' => 'SD',
            'kepala_sekolah' => '', // kosong
            'subdomain' => 'sdncontoh',
            'nama_lengkap' => 'Operator Sekolah',
            'nama_pengguna' => 'operator_contoh',
            'email' => 'operator@sdcontoh.sch.id',
            'kata_sandi' => 'Password123!',
            'kata_sandi_confirmation' => 'Password123!',
        ];

        $response = $this->post(route('sekolah.register.store'), $payload);
        $response->assertSessionHasErrors(['kepala_sekolah']);
    }
}
