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

        // We don't implement password reset — expect 404
        $response->assertStatus(404);
    }

    public function test_reset_password_is_not_available(): void
    {
        $response = $this->get('/reset-password/some-token');

        // We don't implement password reset — expect 404
        $response->assertStatus(404);
    }
}
