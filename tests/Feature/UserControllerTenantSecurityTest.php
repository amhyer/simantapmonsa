<?php

namespace Tests\Feature;

use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Admin\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserControllerTenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['simantap.multi_tenant_enabled' => true]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function createTenant(string $code): Tenant
    {
        return Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '2000'.$code,
            'nama_sekolah' => 'Sekolah '.$code,
            'subdomain' => 'uc-sec-'.$code,
            'aktif' => true,
        ]);
    }

    public function test_admin_tenant_a_cannot_mutate_or_delete_tenant_b_users_via_http(): void
    {
        $tenantA = $this->createTenant('0001');
        $tenantB = $this->createTenant('0002');

        $adminA = User::factory()->admin()->create([
            'tenant_id' => $tenantA->id,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guruB = User::factory()->guru()->create([
            'tenant_id' => $tenantB->id,
            'nama_lengkap' => 'Guru Rahasia B',
            'nama_pengguna' => 'guru_b_sec',
            'email' => 'guru_b_sec@simantap.sch.id',
            'kata_sandi' => Hash::make('SandiAwalB123!'),
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $initialHash = $guruB->kata_sandi;

        // 1. Update user B oleh admin A -> ditolak (404), data B tetap utuh
        $this->actingAs($adminA)->put(route('admin.users.update', $guruB), [
            'nama_lengkap' => 'Dibajak Admin A',
            'nama_pengguna' => 'guru_b_sec',
            'email' => 'guru_b_sec@simantap.sch.id',
            'peran' => 'guru',
            'aktif' => true,
        ])->assertNotFound();

        $guruBFresh = User::withoutGlobalScopes()->find($guruB->id);
        $this->assertSame('Guru Rahasia B', $guruBFresh->nama_lengkap);

        // 2. Toggle aktif user B oleh admin A -> ditolak (404), aktif tetap true
        $this->actingAs($adminA)->postJson(route('admin.users.toggle-aktif', $guruB))
            ->assertNotFound();
        $this->assertTrue((bool) User::withoutGlobalScopes()->find($guruB->id)->aktif);

        // 3. Reset sandi instan user B oleh admin A -> ditolak (404), hash sandi tetap sama
        $this->actingAs($adminA)->postJson(route('admin.users.reset-password', $guruB))
            ->assertNotFound();
        $this->assertSame($initialHash, User::withoutGlobalScopes()->find($guruB->id)->kata_sandi);

        // 4. Hapus user B oleh admin A -> ditolak (404), user B tidak terhapus
        $this->actingAs($adminA)->delete(route('admin.users.destroy', $guruB))
            ->assertNotFound();
        $this->assertNotNull(User::withoutGlobalScopes()->find($guruB->id));

        // 5. Hapus massal pada mode multi-tenant -> ditolak (503), user B tetap ada
        $this->actingAs($adminA)->deleteJson(route('admin.users.destroy-all', 'guru'), [
            'konfirmasi' => 'HAPUS GURU',
        ])->assertStatus(503);
        $this->assertNotNull(User::withoutGlobalScopes()->find($guruB->id));
    }

    public function test_delete_all_by_role_only_touches_active_tenant_users(): void
    {
        $tenantA = $this->createTenant('0003');
        $tenantB = $this->createTenant('0004');

        $guruA = User::factory()->guru()->create(['tenant_id' => $tenantA->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $tenantB->id]);

        $deleted = TenantContext::run($tenantA->id, function (): int {
            return app(UserAccountService::class)->deleteAllByRole('guru');
        });

        $this->assertSame(1, $deleted);
        $this->assertSoftDeleted('users', ['id' => $guruA->id]);
        $this->assertDatabaseHas('users', ['id' => $guruB->id, 'deleted_at' => null]);
    }

    public function test_non_admin_roles_guru_and_siswa_are_rejected_on_all_user_management_actions(): void
    {
        $tenantA = $this->createTenant('0005');
        $guruA = User::factory()->guru()->create([
            'tenant_id' => $tenantA->id,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $siswaA = User::factory()->siswa()->create([
            'tenant_id' => $tenantA->id,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $target = User::factory()->guru()->create([
            'tenant_id' => $tenantA->id,
            'nama_lengkap' => 'Target Utuh',
            'aktif' => true,
        ]);

        foreach ([$guruA, $siswaA] as $actor) {
            $this->actingAs($actor)->get(route('admin.users.index'))->assertForbidden();
            $this->actingAs($actor)->put(route('admin.users.update', $target), [
                'nama_lengkap' => 'Diubah Non Admin',
                'nama_pengguna' => $target->nama_pengguna,
                'peran' => 'guru',
            ])->assertForbidden();
            $this->actingAs($actor)->postJson(route('admin.users.toggle-aktif', $target))->assertForbidden();
            $this->actingAs($actor)->postJson(route('admin.users.reset-password', $target))->assertForbidden();
            $this->actingAs($actor)->delete(route('admin.users.destroy', $target))->assertForbidden();
            $this->actingAs($actor)->deleteJson(route('admin.users.destroy-all', 'guru'), [
                'konfirmasi' => 'HAPUS GURU',
            ])->assertForbidden();
        }

        $fresh = User::withoutGlobalScopes()->find($target->id);
        $this->assertNotNull($fresh);
        $this->assertSame('Target Utuh', $fresh->nama_lengkap);
        $this->assertTrue((bool) $fresh->aktif);
    }

    public function test_cascade_delete_guru_and_siswa_in_tenant_a_does_not_touch_tenant_b_relations(): void
    {
        $tenantA = $this->createTenant('0006');
        $tenantB = $this->createTenant('0007');

        $adminA = User::factory()->admin()->create([
            'tenant_id' => $tenantA->id,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $tenantA->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $tenantB->id]);

        // Kedua siswa di tenant berbeda sengaja memakai NIS sama ('99001') untuk uji isolasi cascade delete siswa.
        $siswaA = TenantContext::run($tenantA->id, fn () => Siswa::create([
            'guru_id' => $guruA->id,
            'nis' => '99001',
            'nama_peserta_didik' => 'Siswa Tenant A',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));
        $siswaB = TenantContext::run($tenantB->id, fn () => Siswa::create([
            'guru_id' => $guruB->id,
            'nis' => '99001',
            'nama_peserta_didik' => 'Siswa Tenant B',
            'kelas' => '6A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]));

        $nilaiA = TenantContext::run($tenantA->id, fn () => Nilai::create([
            'siswa_id' => $siswaA->id,
            'nama_siswa' => $siswaA->nama_peserta_didik,
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'mata_pelajaran' => 'Matematika',
            'judul_penilaian' => 'UH 1',
            'kelas' => '6A',
            'jenis' => 'PAS',
            'nilai' => 88,
            'tanggal' => '2026-10-19',
        ]));
        $nilaiB = TenantContext::run($tenantB->id, fn () => Nilai::create([
            'siswa_id' => $siswaB->id,
            'nama_siswa' => $siswaB->nama_peserta_didik,
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'mata_pelajaran' => 'Matematika',
            'judul_penilaian' => 'UH 1',
            'kelas' => '6A',
            'jenis' => 'PAS',
            'nilai' => 92,
            'tanggal' => '2026-10-19',
        ]));

        $siswaUserA = User::factory()->siswa()->create([
            'tenant_id' => $tenantA->id,
            'nama_pengguna' => '99001',
            'terhubung_dengan' => [$siswaA->id],
        ]);

        // Hapus akun login siswa A -> akun login terhapus (soft-delete), sementara
        // data pokok Siswa A, Nilai A, Siswa B, dan Nilai B tetap utuh (tidak ada kehilangan data akademik)
        $this->actingAs($adminA)->delete(route('admin.users.destroy', $siswaUserA))->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $siswaUserA->id]);
        $this->assertDatabaseHas('siswa', ['id' => $siswaA->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('nilai', ['id' => $nilaiA->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('siswa', ['id' => $siswaB->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('nilai', ['id' => $nilaiB->id, 'deleted_at' => null]);

        // Hapus akun guru A -> Guru B, Siswa B, Nilai B harus tetap utuh
        $this->actingAs($adminA)->delete(route('admin.users.destroy', $guruA))->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $guruA->id]);
        $this->assertDatabaseHas('users', ['id' => $guruB->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('siswa', ['id' => $siswaB->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('nilai', ['id' => $nilaiB->id, 'deleted_at' => null]);
    }
}
