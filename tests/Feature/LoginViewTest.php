<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_menampilkan_tombol_kembali_dan_toggle_mata_password(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Kembali ke Beranda');
        $response->assertSee(route('home'));
        $response->assertSee('btnTogglePassword');
        $response->assertSee('eyeIcon');
        $response->assertSee('eyeSlashIcon');
    }
}
