<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_pendaftaran_dialihkan_ke_login(): void
    {
        $this->get('/register')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');
    }

    public function test_pendaftaran_publik_ditolak_untuk_guru_siswa_dan_orang_tua(): void
    {
        foreach (['guru', 'siswa', 'ortu'] as $peran) {
            $namaPengguna = 'calon_' . $peran . '_' . rand(1000, 9999);

            $this->post('/register', [
                'nama_lengkap' => 'Calon ' . ucfirst($peran),
                'nama_pengguna' => $namaPengguna,
                'peran' => $peran,
                'kata_sandi' => 'password123',
                'kata_sandi_confirmation' => 'password123',
            ])->assertStatus(405);

            $this->assertDatabaseMissing('users', ['nama_pengguna' => $namaPengguna]);
        }
    }

    public function test_login_page_hides_demo_credentials_outside_local(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        if (!app()->environment('local')) {
            $response->assertDontSee('admin / admin123');
        } else {
            $this->assertTrue(true);
        }
    }
}
