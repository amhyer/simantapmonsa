<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi dua celah autentikasi:
 *
 * 1. LoginController tidak pernah memeriksa kolom `aktif`/`archived_at`,
 *    sehingga akun yang dinonaktifkan admin tetap dapat masuk.
 * 2. Pendaftaran publik langsung membuat akun aktif (termasuk peran guru)
 *    tanpa verifikasi admin.
 */
class LoginAktifTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_nonaktif_tidak_dapat_login(): void
    {
        $user = User::factory()->create([
            'kata_sandi' => bcrypt('password'),
            'aktif' => false,
        ]);

        $this->post('/login', [
            'nama_pengguna' => $user->nama_pengguna,
            'kata_sandi' => 'password',
        ])->assertSessionHasErrors('nama_pengguna');

        $this->assertGuest();
    }

    public function test_akun_aktif_tetap_dapat_login(): void
    {
        $user = User::factory()->create([
            'kata_sandi' => bcrypt('password'),
            'aktif' => true,
        ]);

        $this->post('/login', [
            'nama_pengguna' => $user->nama_pengguna,
            'kata_sandi' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_akun_terarsip_tidak_dapat_login(): void
    {
        $user = User::factory()->create([
            'kata_sandi' => bcrypt('password'),
            'aktif' => true,
            'archived_at' => now(),
        ]);

        $this->post('/login', [
            'nama_pengguna' => $user->nama_pengguna,
            'kata_sandi' => 'password',
        ])->assertSessionHasErrors('nama_pengguna');

        $this->assertGuest();
    }

    public function test_pendaftaran_publik_menghasilkan_akun_nonaktif(): void
    {
        $namaPengguna = 'calon_' . rand(1000, 9999);

        $this->post('/register', [
            'nama_lengkap' => 'Calon Pengguna',
            'nama_pengguna' => $namaPengguna,
            'peran' => 'guru',
            'kata_sandi' => 'password123',
            'kata_sandi_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'nama_pengguna' => $namaPengguna,
            'aktif' => false,
        ]);

        // Akun hasil pendaftaran belum bisa dipakai sebelum diaktifkan admin.
        $this->post('/login', [
            'nama_pengguna' => $namaPengguna,
            'kata_sandi' => 'password123',
        ])->assertSessionHasErrors('nama_pengguna');

        $this->assertGuest();
    }
}
