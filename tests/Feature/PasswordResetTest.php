<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

    public function test_multi_tenant_domain_utama_dapat_reset_sandi_dengan_email(): void
    {
        Notification::fake();
        config(['simantap.multi_tenant_enabled' => true]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40310001',
            'nama_sekolah' => 'SDN Multi Satu',
            'subdomain' => 'sdnmultisatu',
            'aktif' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'nama_pengguna' => 'guru.multi',
            'email' => 'guru.multi@sdn1.sch.id',
            'aktif' => true,
            'kata_sandi' => Hash::make('sandiLama123'),
        ]);

        TenantContext::clear();

        $this->post(route('password.email'), ['login' => 'guru.multi@sdn1.sch.id'])
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
            'email' => 'guru.multi@sdn1.sch.id',
            'kata_sandi' => 'sandiBaru999',
            'kata_sandi_confirmation' => 'sandiBaru999',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('sandiBaru999', $user->kata_sandi));
    }

    public function test_multi_tenant_domain_utama_dapat_reset_sandi_dengan_username_unik(): void
    {
        Notification::fake();
        config(['simantap.multi_tenant_enabled' => true]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40310002',
            'nama_sekolah' => 'SDN Multi Dua',
            'subdomain' => 'sdnmultidua',
            'aktif' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'nama_pengguna' => 'unik.guru.sdn2',
            'email' => 'unik@sdn2.sch.id',
            'aktif' => true,
        ]);

        TenantContext::clear();

        $this->post(route('password.email'), ['login' => 'unik.guru.sdn2'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_multi_tenant_domain_utama_menolak_username_bentrok_lintas_tenant(): void
    {
        Notification::fake();
        config(['simantap.multi_tenant_enabled' => true]);

        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40310003',
            'nama_sekolah' => 'SDN Bentrok A',
            'subdomain' => 'sdnbentroka',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40310004',
            'nama_sekolah' => 'SDN Bentrok B',
            'subdomain' => 'sdnbentrokb',
            'aktif' => true,
        ]);

        User::factory()->create([
            'tenant_id' => $tenantA->id,
            'nama_pengguna' => 'admin.sama',
            'email' => 'admin@sdna.sch.id',
            'aktif' => true,
        ]);
        User::factory()->create([
            'tenant_id' => $tenantB->id,
            'nama_pengguna' => 'admin.sama',
            'email' => 'admin@sdnb.sch.id',
            'aktif' => true,
        ]);

        TenantContext::clear();

        $this->post(route('password.email'), ['login' => 'admin.sama'])
            ->assertSessionHasErrors('login');

        Notification::assertNothingSent();
    }

    public function test_multi_tenant_subdomain_hanya_mencari_di_tenant_aktif(): void
    {
        Notification::fake();
        config(['simantap.multi_tenant_enabled' => true]);

        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40310005',
            'nama_sekolah' => 'SDN Sub A',
            'subdomain' => 'sdnsuba',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40310006',
            'nama_sekolah' => 'SDN Sub B',
            'subdomain' => 'sdnsubb',
            'aktif' => true,
        ]);

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'nama_pengguna' => 'admin.sekolah',
            'email' => 'admin@sdnsuba.sch.id',
            'aktif' => true,
        ]);
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'nama_pengguna' => 'admin.sekolah',
            'email' => 'admin@sdnsubb.sch.id',
            'aktif' => true,
        ]);

        $this->withHeaders(['X-Tenant-Subdomain' => 'sdnsuba'])
            ->post(route('password.email'), ['login' => 'admin.sekolah'])
            ->assertSessionHas('status');

        Notification::assertSentTo($userA, ResetPassword::class);
        Notification::assertNotSentTo($userB, ResetPassword::class);
    }

    public function test_akun_terarsip_tidak_menerima_tautan_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'nama_pengguna' => 'guru.arsip',
            'email' => 'guru.arsip@sekolah.test',
            'aktif' => true,
            'archived_at' => now(),
        ]);

        $this->post(route('password.email'), ['login' => 'guru.arsip@sekolah.test'])
            ->assertSessionHas('status');

        Notification::assertNotSentTo($user, ResetPassword::class);
    }
}
