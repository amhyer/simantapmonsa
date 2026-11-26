<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Admin\MassPasswordResetService;
use App\Services\Admin\UserAccountService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrokenAuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function insertDbSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('a:0:{}'),
            'last_activity' => now()->timestamp,
        ]);
    }

    public function test_reset_sandi_menghapus_semua_sesi_aktif_pengguna_di_database(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'nama_pengguna' => 'guru.sesi.reset',
            'email' => 'guru.sesi@sekolah.test',
            'aktif' => true,
            'kata_sandi' => Hash::make('SandiLama123'),
        ]);
        $otherUser = User::factory()->create([
            'nama_pengguna' => 'guru.lain',
            'email' => 'guru.lain@sekolah.test',
            'aktif' => true,
        ]);

        $this->insertDbSession('sess-attacker-1', $user->id);
        $this->insertDbSession('sess-attacker-2', $user->id);
        $this->insertDbSession('sess-other-user', $otherUser->id);

        $this->post(route('password.email'), ['login' => 'guru.sesi@sekolah.test'])
            ->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'guru.sesi@sekolah.test',
            'kata_sandi' => 'SandiBaru456',
            'kata_sandi_confirmation' => 'SandiBaru456',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['id' => 'sess-attacker-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-attacker-2']);
        $this->assertDatabaseHas('sessions', ['id' => 'sess-other-user']);
    }

    public function test_force_password_change_merotasi_remember_token_dan_menghapus_sesi_lain(): void
    {
        $user = User::factory()->create([
            'peran' => 'guru',
            'aktif' => true,
            'force_password_change' => true,
            'kata_sandi' => Hash::make('SandiAwal123'),
            'remember_token' => 'token-lama-12345',
        ]);

        $this->insertDbSession('sess-hijacked-force', $user->id);

        $this->actingAs($user)
            ->put(route('force-password-change.update'), [
                'password' => 'SandiBaru789',
                'password_confirmation' => 'SandiBaru789',
            ])
            ->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertFalse((bool) $user->force_password_change);
        $this->assertNotSame('token-lama-12345', $user->remember_token);
        $this->assertTrue(Hash::check('SandiBaru789', $user->kata_sandi));
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-hijacked-force']);
    }

    public function test_account_profile_update_password_merotasi_remember_token_dan_menghapus_sesi_lain(): void
    {
        $user = User::factory()->create([
            'peran' => 'guru',
            'aktif' => true,
            'force_password_change' => true,
            'kata_sandi' => Hash::make('SandiLama123'),
            'remember_token' => 'token-lama-akun',
        ]);

        $this->insertDbSession('sess-hijacked-akun', $user->id);

        $this->actingAs($user)
            ->put(route('account.profile.password'), [
                'kata_sandi_saat_ini' => 'SandiLama123',
                'password' => 'SandiBaru321',
                'password_confirmation' => 'SandiBaru321',
            ])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse((bool) $user->force_password_change);
        $this->assertNotSame('token-lama-akun', $user->remember_token);
        $this->assertTrue(Hash::check('SandiBaru321', $user->kata_sandi));
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-hijacked-akun']);
    }

    public function test_admin_profile_update_password_merotasi_remember_token_dan_menghapus_sesi_lain(): void
    {
        config(['simantap.multi_tenant_enabled' => false]);

        $admin = User::factory()->create([
            'tenant_id' => null,
            'peran' => 'admin',
            'aktif' => true,
            'force_password_change' => false,
            'kata_sandi' => Hash::make('AdminLama123'),
            'remember_token' => 'token-lama-admin',
        ]);

        $this->insertDbSession('sess-hijacked-admin', $admin->id);

        $this->actingAs($admin)
            ->put(route('admin.profile.password'), [
                'kata_sandi_saat_ini' => 'AdminLama123',
                'password' => 'AdminBaru654',
                'password_confirmation' => 'AdminBaru654',
            ])
            ->assertSessionHas('success');

        $admin->refresh();
        $this->assertFalse((bool) $admin->force_password_change);
        $this->assertNotSame('token-lama-admin', $admin->remember_token);
        $this->assertTrue(Hash::check('AdminBaru654', $admin->kata_sandi));
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-hijacked-admin']);
    }

    public function test_leave_impersonate_menolak_super_admin_nonaktif_atau_terarsip(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40330001',
            'nama_sekolah' => 'SDN Impersonate Test',
            'subdomain' => 'sdnimpersonate',
            'aktif' => true,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'peran' => 'super_admin',
            'aktif' => true,
        ]);

        $schoolAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'peran' => 'admin',
            'aktif' => true,
        ]);

        // Super admin masuk ke mode impersonate
        $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.impersonate', $tenant))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($schoolAdmin);

        // Akun super admin dinonaktifkan saat sesi impersonate masih aktif
        $superAdmin->update(['aktif' => false]);

        $this->post(route('superadmin.impersonate.leave'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        // Kasus terarsip (archived_at terisi)
        $superAdmin->update(['aktif' => true, 'archived_at' => now()]);

        $this->actingAs($schoolAdmin)
            ->withSession([
                'impersonator_id' => $superAdmin->id,
                'impersonator_tenant_uuid' => $tenant->uuid,
            ])
            ->post(route('superadmin.impersonate.leave'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_reset_instan_toggle_nonaktif_dan_reset_massal_menginvalidasi_sesi_dan_remember_token(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Sesi Admin',
            'npsn' => '88776655',
            'subdomain' => 'sd-sesi-admin',
            'aktif' => true,
        ]);

        TenantContext::set($tenant->id);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'peran' => 'admin',
            'aktif' => true,
        ]);

        $guru = User::factory()->create([
            'tenant_id' => $tenant->id,
            'peran' => 'guru',
            'aktif' => true,
            'remember_token' => 'token-guru-awal',
        ]);

        $siswa = User::factory()->create([
            'tenant_id' => $tenant->id,
            'peran' => 'siswa',
            'aktif' => true,
            'remember_token' => 'token-siswa-awal',
        ]);

        $accountService = app(UserAccountService::class);

        // 1. Reset password instan oleh admin
        $this->insertDbSession('sess-guru-instan', $guru->id);
        $accountService->resetPasswordInstan($guru, $admin->id);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-guru-instan']);
        $this->assertNotSame('token-guru-awal', $guru->fresh()->remember_token);

        // 2. Nonaktifkan akun oleh admin (toggleAktif)
        $tokenSebelumNonaktif = $guru->fresh()->remember_token;
        $this->insertDbSession('sess-guru-toggle', $guru->id);
        $accountService->toggleAktif($guru->fresh());
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-guru-toggle']);
        $this->assertNotSame($tokenSebelumNonaktif, $guru->fresh()->remember_token);

        // 3. Reset massal password siswa oleh admin
        $this->insertDbSession('sess-siswa-massal', $siswa->id);
        app(MassPasswordResetService::class)->execute([
            'peran' => ['siswa'],
            'hanya_belum_login' => false,
            'sertakan_sudah_diterbitkan' => true,
        ], $admin);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-siswa-massal']);
        $this->assertNotSame('token-siswa-awal', $siswa->fresh()->remember_token);
    }

    public function test_superadmin_reset_password_admin_sekolah_menginvalidasi_sesi_admin(): void
    {
        $superAdmin = User::factory()->create([
            'peran' => 'super_admin',
            'tenant_id' => null,
            'aktif' => true,
        ]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Reset Superadmin',
            'npsn' => '77665544',
            'subdomain' => 'sd-reset-sa',
            'aktif' => true,
        ]);

        $schoolAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'peran' => 'admin',
            'aktif' => true,
            'remember_token' => 'token-admin-sekolah-lama',
        ]);

        $this->insertDbSession('sess-admin-sekolah', $schoolAdmin->id);

        $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.reset-admin-password', $tenant), [
                'password_baru' => 'SandiAdminBaru#2026',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'sess-admin-sekolah']);
        $this->assertNotSame('token-admin-sekolah-lama', $schoolAdmin->fresh()->remember_token);
    }

    public function test_artisan_tenant_dan_superadmin_menolak_email_duplikat_serta_subdomain_tercadang(): void
    {
        User::factory()->create([
            'nama_pengguna' => 'operator.lama',
            'email' => 'duplikat@simantap.id',
            'tenant_id' => null,
        ]);

        $this->artisan('superadmin:create', [
            '--user' => 'superadmin_baru',
            '--name' => 'SA Baru',
            '--email' => 'DUPLIKAT@simantap.id',
            '--pass' => 'SuperAdmin#2026',
        ])->assertExitCode(1);

        $this->artisan('tenant:create', [
            '--npsn' => '40319999',
            '--nama' => 'SD Subdomain Tercadang',
            '--subdomain' => 'admin',
            '--jenjang' => 'SD',
            '--admin-user' => 'admin_sd',
            '--admin-nama' => 'Admin SD',
            '--admin-email' => 'unik@sd.sch.id',
            '--admin-pass' => 'Admin#2026',
        ])->assertExitCode(1);

        $this->artisan('tenant:create', [
            '--npsn' => '40319998',
            '--nama' => 'SD Email Duplikat',
            '--subdomain' => 'sdujicli',
            '--jenjang' => 'SD',
            '--admin-user' => 'admin_sduji',
            '--admin-nama' => 'Admin SD Uji',
            '--admin-email' => 'duplikat@simantap.id',
            '--admin-pass' => 'Admin#2026',
        ])->assertExitCode(1);
    }
}
