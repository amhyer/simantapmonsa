<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_is_not_available(): void
    {
        $response = $this->get('/verify-email');

        // We don't implement email verification — expect 404 or redirect
        $response->assertStatus(404);
    }
}
