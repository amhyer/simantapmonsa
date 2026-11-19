<?php

namespace Tests\Feature;

use App\Models\Aktivitas;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Database\Seeders\E2eFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MassPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(E2eFixtureSeeder::class);
    }

    public function test_non_admin_and_guest_cannot_access_mass_reset_or_preview(): void
    {
        $guru = User::findOrFail(E2eFixtureSeeder::USER_IDS['guru_a']);
        $siswa = User::findOrFail(E2eFixtureSeeder::USER_IDS['siswa_a1']);

        $this->post(route('admin.users.reset-massal'), [])->assertRedirect(route('login'));
        $this->actingAs($guru)->postJson(route('admin.users.reset-massal.pratinjau'), ['peran' => ['siswa']])->assertForbidden();
        $this->actingAs($siswa)->postJson(route('admin.users.reset-massal'), ['peran' => ['siswa'], 'password_admin' => 'x'])->assertForbidden();
    }

    public function test_wrong_admin_password_is_rejected_with_422(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);

        $this->actingAs($admin)
            ->postJson(route('admin.users.reset-massal'), [
                'peran' => ['siswa'],
                'password_admin' => 'password-salah-123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password_admin');
    }

    public function test_mass_reset_excludes_admin_super_admin_self_logged_in_and_other_tenants_and_prevents_double_reset(): void
    {
        $tenantA = Tenant::create(['npsn' => '77776666', 'nama_sekolah' => 'Sekolah A', 'subdomain' => 'mass-reset-a', 'aktif' => true]);
        $admin = User::factory()->create([
            'peran' => 'admin',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'kata_sandi' => Hash::make('AdminSecret!99'),
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $superAdmin = User::factory()->create(['peran' => 'super_admin', 'tenant_id' => null, 'terakhir_masuk' => null, 'sandi_awal_diterbitkan_at' => null]);
        $otherAdmin = User::factory()->create(['peran' => 'admin', 'tenant_id' => $tenantA->id, 'tenant_owned' => true, 'terakhir_masuk' => null, 'sandi_awal_diterbitkan_at' => null]);
        $kepsek = User::factory()->create(['peran' => 'kepsek', 'tenant_id' => $tenantA->id, 'tenant_owned' => true, 'terakhir_masuk' => null, 'sandi_awal_diterbitkan_at' => null]);

        $siswaBelumLogin = User::factory()->create([
            'peran' => 'siswa',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'kelas_mata_pelajaran' => '4A',
            'terakhir_masuk' => null,
            'sandi_awal_diterbitkan_at' => null,
        ]);
        $siswaSudahLogin = User::factory()->create([
            'peran' => 'siswa',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'kelas_mata_pelajaran' => '4A',
            'terakhir_masuk' => now()->subDay(),
            'sandi_awal_diterbitkan_at' => null,
        ]);
        $siswaSudahLoginHashBefore = $siswaSudahLogin->kata_sandi;

        // Tenant lain tidak boleh terpengaruh
        $tenantB = Tenant::create(['npsn' => '88887777', 'nama_sekolah' => 'Sekolah B', 'subdomain' => 'mass-reset-b', 'aktif' => true]);
        $siswaTenantB = User::factory()->create([
            'peran' => 'siswa',
            'tenant_id' => $tenantB->id,
            'tenant_owned' => true,
            'kelas_mata_pelajaran' => '4A',
            'terakhir_masuk' => null,
            'sandi_awal_diterbitkan_at' => null,
        ]);
        $siswaTenantBHashBefore = $siswaTenantB->kata_sandi;

        // Batch 1: eksekusi reset untuk siswa kelas 4A
        $res1 = $this->actingAs($admin)->postJson(route('admin.users.reset-massal'), [
            'peran' => ['siswa', 'kepsek'],
            'kelas' => '4A',
            'hanya_belum_login' => true,
            'sertakan_sudah_diterbitkan' => false,
            'password_admin' => 'AdminSecret!99',
        ]);
        $res1->assertOk()->assertJsonPath('success', true)->assertJsonPath('diproses', 1);

        $eksporUrl = $res1->json('ekspor_url');
        $this->assertNotEmpty($eksporUrl);

        // Unduh CSV & verifikasi Hash::check + unduhan kedua 404
        $download = $this->actingAs($admin)->get($eksporUrl)->assertOk();
        $csv = $download->streamedContent();
        $lines = array_values(array_filter(array_map('trim', explode("\n", $csv))));
        $cols = str_getcsv($lines[1]);
        $this->assertSame($siswaBelumLogin->nama_pengguna, $cols[2]);
        $exportedPlain = $cols[3];
        $this->assertTrue(Hash::check($exportedPlain, $siswaBelumLogin->fresh()->kata_sandi));
        $this->assertNotNull($siswaBelumLogin->fresh()->sandi_awal_diterbitkan_at);

        // Unduhan kedua 404
        $this->actingAs($admin)->get($eksporUrl)->assertNotFound();

        // Akun sudah login, admin, super_admin, dan tenant lain tidak tersentuh
        $this->assertSame($siswaSudahLoginHashBefore, $siswaSudahLogin->fresh()->kata_sandi);
        $this->assertSame($siswaTenantBHashBefore, $siswaTenantB->fresh()->kata_sandi);
        $this->assertNull($superAdmin->fresh()->sandi_awal_diterbitkan_at);
        $this->assertNull($otherAdmin->fresh()->sandi_awal_diterbitkan_at);
        $this->assertNull($kepsek->fresh()->sandi_awal_diterbitkan_at); // kelas != 4A

        // Log aktivitas tercatat TANPA password
        $log = Aktivitas::withoutGlobalScopes()
            ->where('tenant_id', $tenantA->id)
            ->where('judul', 'Reset Sandi Massal')
            ->latest('id')
            ->firstOrFail();
        $this->assertStringNotContainsString($exportedPlain, (string) $log->deskripsi);

        // Batch 2 (tanpa opsi sertakan): TIDAK boleh me-reset ulang akun batch 1!
        $hashAfterBatch1 = $siswaBelumLogin->fresh()->kata_sandi;
        $res2 = $this->actingAs($admin)->postJson(route('admin.users.reset-massal'), [
            'peran' => ['siswa'],
            'kelas' => '4A',
            'hanya_belum_login' => true,
            'sertakan_sudah_diterbitkan' => false,
            'password_admin' => 'AdminSecret!99',
        ]);
        $res2->assertOk()->assertJsonPath('diproses', 0);
        $this->assertSame($hashAfterBatch1, $siswaBelumLogin->fresh()->kata_sandi);

        // Dengan opsi sertakan_sudah_diterbitkan=true: memang me-reset ulang
        $res3 = $this->actingAs($admin)->postJson(route('admin.users.reset-massal'), [
            'peran' => ['siswa'],
            'kelas' => '4A',
            'hanya_belum_login' => true,
            'sertakan_sudah_diterbitkan' => true,
            'password_admin' => 'AdminSecret!99',
        ]);
        $res3->assertOk()->assertJsonPath('diproses', 1);
        $this->assertNotSame($hashAfterBatch1, $siswaBelumLogin->fresh()->kata_sandi);
    }

    public function test_user_changing_own_password_clears_sandi_awal_diterbitkan_at(): void
    {
        $siswa = User::factory()->create([
            'peran' => 'siswa',
            'kata_sandi' => Hash::make('OldPass123!'),
            'force_password_change' => true,
            'sandi_awal_diterbitkan_at' => now(),
        ]);

        $this->actingAs($siswa)->put(route('force-password-change.update'), [
            'password' => 'NewSecurePass!99',
            'password_confirmation' => 'NewSecurePass!99',
        ])->assertRedirect('/dashboard');

        $fresh = $siswa->fresh();
        $this->assertFalse((bool) $fresh->force_password_change);
        $this->assertNull($fresh->sandi_awal_diterbitkan_at);
    }

    public function test_mass_reset_respects_batch_limit_lock_and_throttle(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);
        $admin->update(['kata_sandi' => Hash::make('AdminSecret!99')]);

        config(['simantap.reset_massal_max' => 2]);
        User::factory()->count(3)->create([
            'peran' => 'siswa',
            'kelas_mata_pelajaran' => '5B',
            'terakhir_masuk' => null,
            'sandi_awal_diterbitkan_at' => null,
        ]);

        // Kunci aktif memblokir dengan ramah (423)
        $lock = Cache::lock('reset_massal:tenant:'.(TenantContext::id() ?? 'single'), 30);
        $this->assertTrue($lock->get());
        try {
            $this->actingAs($admin)->postJson(route('admin.users.reset-massal'), [
                'peran' => ['siswa'],
                'kelas' => '5B',
                'password_admin' => 'AdminSecret!99',
            ])->assertStatus(423);
        } finally {
            $lock->release();
        }

        // Melebihi batas (3 akun, batas 2) -> hanya memproses 2, sisa 1
        $res = $this->actingAs($admin)->postJson(route('admin.users.reset-massal'), [
            'peran' => ['siswa'],
            'kelas' => '5B',
            'hanya_belum_login' => true,
            'password_admin' => 'AdminSecret!99',
        ]);
        $res->assertOk()->assertJsonPath('diproses', 2)->assertJsonPath('sisa', 1);

        // Throttle 5/menit: 4 permintaan tambahan -> permintaan ke-6 (total >5) mendapat 429
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($admin)->postJson(route('admin.users.reset-massal'), [
                'peran' => ['siswa'],
                'kelas' => '5B',
                'password_admin' => 'AdminSecret!99',
            ]);
        }
        $this->actingAs($admin)->postJson(route('admin.users.reset-massal'), [
            'peran' => ['siswa'],
            'kelas' => '5B',
            'password_admin' => 'AdminSecret!99',
        ])->assertStatus(429);
    }
}
