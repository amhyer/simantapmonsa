<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Reset sandi mandiri: email opsional, token sekali pakai, dan fallback admin
 * untuk akun yang belum punya email. Cakupan ini menjaga agar pemulihan akun
 * tidak lagi bergantung sepenuhnya pada admin.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_lupa_dan_reset_sandi_dapat_dibuka(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Lupa Kata Sandi');

        $this->get(route('password.reset', ['token' => 'contoh-token', 'email' => 'guru@sekolah.test']))
            ->assertOk()
            ->assertSee('Buat kata sandi baru');
    }

    public function test_akun_dengan_email_menerima_tautan_dan_dapat_mengganti_sandi(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'nama_pengguna' => 'guru.reset',
            'email' => 'guru.reset@sekolah.test',
            'aktif' => true,
            'kata_sandi' => Hash::make('sandiLama123'),
        ]);

        $this->post(route('password.email'), ['login' => 'guru.reset'])
            ->assertSessionHas('status');

        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->assertNotNull($token);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'guru.reset@sekolah.test',
            'kata_sandi' => 'sandiBaru456',
            'kata_sandi_confirmation' => 'sandiBaru456',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertTrue(Hash::check('sandiBaru456', $user->kata_sandi));
        $this->assertFalse((bool) $user->force_password_change);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_login_boleh_memakai_email_untuk_meminta_tautan(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'ortu.reset@sekolah.test',
            'aktif' => true,
        ]);

        $this->post(route('password.email'), ['login' => 'ortu.reset@sekolah.test'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_akun_tanpa_email_tidak_menerima_tautan_dan_diarahkan_ke_admin(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'nama_pengguna' => 'siswa.tanpa.email',
            'email' => null,
            'aktif' => true,
        ]);

        $this->post(route('password.email'), ['login' => 'siswa.tanpa.email'])
            ->assertSessionHas('status');

        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_token_salah_tidak_mengubah_sandi(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'kepsek.reset@sekolah.test',
            'aktif' => true,
            'kata_sandi' => Hash::make('sandiLama789'),
        ]);

        $this->post(route('password.email'), ['login' => 'kepsek.reset@sekolah.test']);

        $this->post(route('password.update'), [
            'token' => 'token-palsu',
            'email' => 'kepsek.reset@sekolah.test',
            'kata_sandi' => 'sandiPalsu456',
            'kata_sandi_confirmation' => 'sandiPalsu456',
        ])->assertSessionHasErrors('email');

        $user->refresh();

        $this->assertTrue(Hash::check('sandiLama789', $user->kata_sandi));
    }
}
