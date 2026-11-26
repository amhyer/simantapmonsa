<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\PengajuanIzinPresensi;
use App\Models\PresensiPegawai;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_dapat_menghapus_akun_pengguna_individual_dan_bulk(): void
    {
        $superAdmin = User::factory()->create([
            'peran' => 'super_admin',
            'tenant_id' => null,
        ]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Uji Superadmin',
            'npsn' => '99887766',
            'subdomain' => 'sduji',
            'aktif' => true,
        ]);

        $guru1 = User::factory()->guru()->create(['tenant_id' => $tenant->id]);
        $guru2 = User::factory()->guru()->create(['tenant_id' => $tenant->id]);
        $siswa1 = User::factory()->siswa()->create(['tenant_id' => $tenant->id]);
        $ortu1 = User::factory()->create(['peran' => 'ortu', 'tenant_id' => $tenant->id]);

        // Pasang relasi RESTRICT terhadap users untuk memverifikasi penghapusan
        // child -> parent bekerja mulus di PostgreSQL tanpa disableForeignKeyConstraints().
        $dataSiswa = Siswa::factory()->create([
            'tenant_id' => $tenant->id,
            'guru_id' => $guru1->id,
        ]);

        CbtQuestionBank::create([
            'tenant_id' => $tenant->id,
            'guru_id' => $guru1->id,
            'title' => 'Bank Matematika Uji',
            'subject' => 'Matematika',
        ]);

        PresensiPegawai::create([
            'tenant_id' => $tenant->id,
            'user_id' => $guru2->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
        ]);

        PengajuanIzinPresensi::create([
            'tenant_id' => $tenant->id,
            'siswa_id' => $dataSiswa->id,
            'orang_tua_id' => $ortu1->id,
            'tanggal' => now()->toDateString(),
            'jenis' => 'I',
            'alasan' => 'Acara keluarga',
        ]);

        // 1. Hapus akun individual (memiliki relasi RESTRICT cbt_question_banks & siswa.guru_id)
        $resDelete1 = $this->actingAs($superAdmin)->delete(route('superadmin.users.destroy', $guru1->id));
        $resDelete1->assertRedirect();
        $resDelete1->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $guru1->id]);
        $this->assertNull($dataSiswa->fresh()->guru_id);

        // 2. Proteksi self-delete: SuperAdmin tidak boleh menghapus akunnya sendiri
        $resSelfDelete = $this->actingAs($superAdmin)->delete(route('superadmin.users.destroy', $superAdmin->id));
        $resSelfDelete->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);

        // 3. Hapus massal (bulk delete dengan relasi RESTRICT presensi_pegawai & pengajuan_izin_presensi)
        $resBulk = $this->actingAs($superAdmin)->post(route('superadmin.users.bulk-destroy'), [
            'user_ids' => [$guru2->id, $siswa1->id, $ortu1->id],
        ]);
        $resBulk->assertRedirect();
        $resBulk->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $guru2->id]);
        $this->assertDatabaseMissing('users', ['id' => $siswa1->id]);
        $this->assertDatabaseMissing('users', ['id' => $ortu1->id]);
    }

    public function test_superadmin_dapat_menghapus_sekolah_dan_sekolah_expired_hosting(): void
    {
        $superAdmin = User::factory()->create([
            'peran' => 'super_admin',
            'tenant_id' => null,
        ]);

        // Sekolah A: masih aktif
        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Masih Aktif',
            'npsn' => '11223344',
            'subdomain' => 'sdaktif',
            'aktif' => true,
            'tgl_kedaluwarsa' => now()->addMonths(6)->toDateString(),
        ]);

        // Sekolah B: expired hosting (sudah lewat jatuh tempo)
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Expired Hosting',
            'npsn' => '55667788',
            'subdomain' => 'sdexpired',
            'aktif' => true,
            'tgl_kedaluwarsa' => now()->subDays(5)->toDateString(),
        ]);

        // Sekolah C: pendaftaran baru menunggu persetujuan (aktif = false, approval_status = pending)
        $tenantPending = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Menunggu Persetujuan',
            'npsn' => '33445566',
            'subdomain' => 'sdpending',
            'aktif' => false,
            'meta' => ['approval_status' => 'pending'],
        ]);

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $tenantB->id]);
        $adminPending = User::factory()->admin()->create(['tenant_id' => $tenantPending->id, 'aktif' => false]);

        // 1. Bersihkan semua sekolah yang expired hosting / nonaktif permanen
        $resBulkExpired = $this->actingAs($superAdmin)->post(route('superadmin.tenants.bulk-destroy-expired'));
        $resBulkExpired->assertRedirect();
        $resBulkExpired->assertSessionHas('success');

        // Sekolah B harus terhapus beserta adminnya
        $this->assertDatabaseMissing('tenants', ['id' => $tenantB->id]);
        $this->assertDatabaseMissing('users', ['id' => $adminB->id]);

        // Sekolah A (aktif) dan Sekolah C (pending persetujuan) TIDAK boleh ikut terhapus
        $this->assertDatabaseHas('tenants', ['id' => $tenantA->id]);
        $this->assertDatabaseHas('users', ['id' => $adminA->id]);
        $this->assertDatabaseHas('tenants', ['id' => $tenantPending->id]);
        $this->assertDatabaseHas('users', ['id' => $adminPending->id]);

        // 2. Coba Hapus Sekolah A tanpa force -> ditolak karena aktif
        $resDeleteTenantAFail = $this->actingAs($superAdmin)->delete(route('superadmin.tenants.destroy', $tenantA->uuid));
        $resDeleteTenantAFail->assertSessionHas('error');
        $this->assertDatabaseHas('tenants', ['id' => $tenantA->id]);

        // 3. Hapus Sekolah A secara individual dengan force (tombol konfirmasi Super Admin)
        $resDeleteTenantA = $this->actingAs($superAdmin)->delete(route('superadmin.tenants.destroy', $tenantA->uuid), ['force' => true]);
        $resDeleteTenantA->assertRedirect(route('superadmin.tenants.index'));
        $resDeleteTenantA->assertSessionHas('success');

        $this->assertDatabaseMissing('tenants', ['id' => $tenantA->id]);
        $this->assertDatabaseMissing('users', ['id' => $adminA->id]);
    }
}
