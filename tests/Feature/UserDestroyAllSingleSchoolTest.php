<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDestroyAllSingleSchoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['simantap.multi_tenant_enabled' => false]);
    }

    public function test_admin_can_bulk_delete_guru_in_single_school_mode_while_preserving_other_roles(): void
    {
        $admin = User::factory()->admin()->create([
            'nama_pengguna' => 'admin_utama',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $superAdmin = User::factory()->create([
            'peran' => 'super_admin',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $kepsek = User::factory()->create([
            'peran' => 'kepsek',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $siswa = User::factory()->siswa()->create(['aktif' => true]);
        $guru1 = User::factory()->guru()->create(['aktif' => true]);
        $guru2 = User::factory()->guru()->create(['aktif' => true]);

        // Konfirmasi salah ditolak 422, tidak ada yang terhapus.
        $this->actingAs($admin)->deleteJson(route('admin.users.destroy-all', 'guru'), [
            'konfirmasi' => 'SALAH',
        ])->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $guru1->id, 'deleted_at' => null]);

        // Konfirmasi cocok menghapus hanya peran guru.
        $this->actingAs($admin)->deleteJson(route('admin.users.destroy-all', 'guru'), [
            'konfirmasi' => 'HAPUS GURU',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', ['id' => $guru1->id]);
        $this->assertSoftDeleted('users', ['id' => $guru2->id]);

        // Admin sendiri, super_admin, kepsek, dan siswa tetap utuh.
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $kepsek->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $siswa->id, 'deleted_at' => null]);
    }

    public function test_forbidden_roles_cannot_be_bulk_deleted_and_non_admin_gets_403(): void
    {
        $admin = User::factory()->admin()->create([
            'nama_pengguna' => 'admin_pelaku',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $adminLain = User::factory()->admin()->create([
            'nama_pengguna' => 'admin_kedua',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $superAdmin = User::factory()->create(['peran' => 'super_admin', 'aktif' => true]);
        $kepsek = User::factory()->create(['peran' => 'kepsek', 'aktif' => true]);
        $guru = User::factory()->guru()->create(['aktif' => true, 'force_password_change' => false]);
        $siswa = User::factory()->siswa()->create(['aktif' => true, 'force_password_change' => false]);

        foreach (['admin', 'super_admin', 'kepsek'] as $forbiddenRole) {
            $this->actingAs($admin)->deleteJson(route('admin.users.destroy-all', $forbiddenRole), [
                'konfirmasi' => 'HAPUS '.strtoupper($forbiddenRole),
            ])->assertStatus(400);
        }

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $adminLain->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $kepsek->id, 'deleted_at' => null]);

        foreach ([$guru, $siswa] as $nonAdmin) {
            $this->actingAs($nonAdmin)->deleteJson(route('admin.users.destroy-all', 'guru'), [
                'konfirmasi' => 'HAPUS GURU',
            ])->assertForbidden();
        }
        $this->assertDatabaseHas('users', ['id' => $guru->id, 'deleted_at' => null]);
    }
}
