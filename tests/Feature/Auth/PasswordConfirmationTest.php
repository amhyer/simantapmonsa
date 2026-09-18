<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_is_not_available(): void
    {
        $response = $this->get('/confirm-password');

        // We don't implement password confirmation — expect 404
        $response->assertStatus(404);
    }
}
