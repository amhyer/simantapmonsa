<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_is_not_available(): void
    {
        $response = $this->get('/forgot-password');

        // Jalur lama tetap 404; alur reset mandiri memakai /lupa-sandi.
        $response->assertStatus(404);
    }

    public function test_reset_password_is_not_available(): void
    {
        $response = $this->get('/reset-password/some-token');

        // Jalur lama tetap 404; alur reset mandiri memakai /reset-sandi/{token}.
        $response->assertStatus(404);
    }
}
