<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $namaPengguna = 'testuser_' . rand(1000, 9999);

        $response = $this->post('/register', [
            'nama_lengkap' => 'Test User',
            'nama_pengguna' => $namaPengguna,
            'peran' => 'siswa',
            'kata_sandi' => 'password123',
            'kata_sandi_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['nama_pengguna' => $namaPengguna]);
    }

    public function test_registration_rejects_kepsek_role(): void
    {
        $namaPengguna = 'kepsekjahat_' . rand(1000, 9999);

        $response = $this->post('/register', [
            'nama_lengkap' => 'Calon Kepsek Jahat',
            'nama_pengguna' => $namaPengguna,
            'peran' => 'kepsek',
            'kata_sandi' => 'password123',
            'kata_sandi_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('peran');
        $this->assertDatabaseMissing('users', ['nama_pengguna' => $namaPengguna]);
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
