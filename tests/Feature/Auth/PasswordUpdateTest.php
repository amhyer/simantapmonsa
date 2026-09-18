<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_password_change_screen_can_be_rendered(): void
    {
        $user = User::factory()->forcePasswordChange()->create([
            'kata_sandi' => bcrypt('oldpassword'),
        ]);

        $response = $this->actingAs($user)->get('/force-password-change');

        $response->assertStatus(200);
    }

    public function test_password_can_be_changed_via_force_password_change(): void
    {
        $user = User::factory()->forcePasswordChange()->create([
            'kata_sandi' => bcrypt('oldpassword'),
        ]);

        $response = $this
            ->actingAs($user)
            ->put('/force-password-change', [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpassword123', $user->refresh()->kata_sandi));
        $this->assertFalse($user->refresh()->force_password_change);
    }

    public function test_new_password_must_be_different_from_old(): void
    {
        $user = User::factory()->forcePasswordChange()->create([
            'kata_sandi' => bcrypt('oldpassword'),
        ]);

        $response = $this
            ->actingAs($user)
            ->put('/force-password-change', [
                'password' => 'oldpassword',
                'password_confirmation' => 'oldpassword',
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->forcePasswordChange()->create([
            'kata_sandi' => bcrypt('oldpassword'),
        ]);

        $response = $this
            ->actingAs($user)
            ->put('/force-password-change', [
                'password' => 'newpassword123',
                'password_confirmation' => 'differentpassword',
            ]);

        $response->assertSessionHasErrors();
    }
}
