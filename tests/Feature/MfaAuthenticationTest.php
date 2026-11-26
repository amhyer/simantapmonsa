<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MfaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_login_akun_ber_mfa_membutuhkan_challenge_dan_tidak_bisa_bypass_langsung(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $recovery = $totp->generateRecoveryCodes(8);

        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'nama_pengguna' => 'super.mfa',
            'peran' => 'super_admin',
            'aktif' => true,
            'kata_sandi' => Hash::make('SandiSuper123'),
        ]);
        $superAdmin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $recovery['hashes'],
            'mfa_confirmed_at' => now(),
        ])->save();

        // 1. Login dengan sandi benar -> diarahkan ke /mfa/challenge dan BELUM terautentikasi
        $this->post('/login', [
            'nama_pengguna' => 'super.mfa',
            'kata_sandi' => 'SandiSuper123',
        ])->assertRedirect(route('mfa.challenge'));

        $this->assertGuest();

        // 2. Percobaan akses langsung ke /superadmin/dashboard ditolak (redirect login)
        $this->get(route('superadmin.dashboard'))->assertRedirect(route('login'));

        // 3. Kode OTP salah ditolak
        $this->post(route('mfa.challenge.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        // 4. Kode OTP benar menyelesaikan login
        $validCode = $totp->codeAt($secret);
        $this->post(route('mfa.challenge.verify'), ['code' => $validCode])
            ->assertRedirect(route('superadmin.dashboard'));

        $this->assertAuthenticatedAs($superAdmin);
        $this->assertNotNull($superAdmin->fresh()->mfa_last_used_window);

        // 5. Anti-replay: logout lalu login ulang dengan kode OTP yang sama di window yang sama -> ditolak
        $this->post(route('logout'));
        $this->post('/login', [
            'nama_pengguna' => 'super.mfa',
            'kata_sandi' => 'SandiSuper123',
        ])->assertRedirect(route('mfa.challenge'));

        $this->post(route('mfa.challenge.verify'), ['code' => $validCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        // 6. Recovery code sekali pakai berhasil login dan langsung terhapus
        $this->post(route('mfa.challenge.verify'), ['code' => $recovery['plain'][0]])
            ->assertRedirect(route('superadmin.dashboard'));
        $this->assertAuthenticatedAs($superAdmin);
        $this->assertCount(7, (array) $superAdmin->fresh()->mfa_recovery_codes);
    }

    public function test_super_admin_wajib_setup_mfa_bila_enforced_dan_impersonasi_tetap_berjalan(): void
    {
        config([
            'simantap.mfa_enabled' => true,
            'simantap.mfa_enforced_roles' => ['super_admin'],
            'simantap.mfa_allowed_roles' => ['super_admin', 'admin'],
        ]);

        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'peran' => 'super_admin',
            'aktif' => true,
            'kata_sandi' => Hash::make('SandiSuper123'),
        ]);

        // Belum punya MFA -> diarahkan ke halaman setup MFA wajib
        $this->actingAs($superAdmin)
            ->get(route('superadmin.dashboard'))
            ->assertRedirect(route('account.mfa.show'));

        // Halaman setup MFA dapat dibuka dan menghasilkan secret di session
        $this->actingAs($superAdmin)
            ->get(route('account.mfa.show'))
            ->assertOk()
            ->assertSee('Verifikasi &amp; Aktifkan MFA', false);

        $setupSecret = (string) session('mfa_setup_secret');
        $this->assertNotSame('', $setupSecret);

        // Aktifkan MFA dengan kata sandi + OTP pertama
        $totp = app(TotpService::class);
        $this->actingAs($superAdmin)
            ->post(route('account.mfa.enable'), [
                'kata_sandi_saat_ini' => 'SandiSuper123',
                'code' => $totp->codeAt($setupSecret),
            ])
            ->assertRedirect(route('account.mfa.show'))
            ->assertSessionHas('mfa_fresh_recovery_codes');

        $superAdmin->refresh();
        $this->assertTrue($superAdmin->hasMfaEnabled());
        $this->assertCount(8, (array) $superAdmin->mfa_recovery_codes);

        // Enforced role tidak bisa menonaktifkan MFA sendiri
        $this->actingAs($superAdmin)
            ->delete(route('account.mfa.disable'), [
                'kata_sandi_saat_ini' => 'SandiSuper123',
            ])
            ->assertSessionHasErrors('kata_sandi_saat_ini');

        $this->assertTrue($superAdmin->fresh()->hasMfaEnabled());
    }

    public function test_admin_sekolah_opt_in_mfa_dan_dapat_direset_oleh_super_admin_atau_cli(): void
    {
        config([
            'simantap.multi_tenant_enabled' => true,
            'simantap.mfa_enabled' => true,
            'simantap.mfa_enforced_roles' => ['super_admin'],
            'simantap.mfa_allowed_roles' => ['super_admin', 'admin'],
        ]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40340001',
            'nama_sekolah' => 'SDN MFA Sekolah',
            'subdomain' => 'sdnmfa',
            'aktif' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'nama_pengguna' => 'admin.mfa',
            'peran' => 'admin',
            'aktif' => true,
            'kata_sandi' => Hash::make('AdminSandi123'),
        ]);

        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $totp->generateRecoveryCodes(8)['hashes'],
            'mfa_confirmed_at' => now(),
        ])->save();

        // Admin sekolah (opt-in) boleh menonaktifkan sendiri dengan sandi valid
        $this->actingAs($admin)
            ->delete(route('account.mfa.disable'), [
                'kata_sandi_saat_ini' => 'AdminSandi123',
            ])
            ->assertRedirect(route('account.mfa.show'));

        $this->assertFalse($admin->fresh()->hasMfaEnabled());

        // Aktifkan lagi lalu uji reset oleh Super Admin
        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $totp->generateRecoveryCodes(8)['hashes'],
            'mfa_confirmed_at' => now(),
        ])->save();

        DB::table('sessions')->insert([
            'id' => 'sess-admin-mfa-reset',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('a:0:{}'),
            'last_activity' => now()->timestamp,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'peran' => 'super_admin',
            'aktif' => true,
        ]);
        $superAdmin->forceFill([
            'mfa_secret' => $totp->generateSecret(),
            'mfa_confirmed_at' => now(),
        ])->save();

        $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.reset-admin-mfa', $tenant))
            ->assertSessionHas('success');

        $this->assertFalse($admin->fresh()->hasMfaEnabled());
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-admin-mfa-reset']);

        // Uji CLI artisan mfa:reset untuk Super Admin
        $this->artisan('mfa:reset', ['username' => $superAdmin->nama_pengguna])
            ->assertExitCode(0);

        $this->assertFalse($superAdmin->fresh()->hasMfaEnabled());
    }
}
