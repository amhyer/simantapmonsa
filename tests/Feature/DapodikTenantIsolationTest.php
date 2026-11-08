<?php

namespace Tests\Feature;

use App\Jobs\ImportDapodikJob;
use App\Models\ApiKey;
use App\Models\DapodikSyncLog;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DapodikTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function school(string $name, string $npsn): Tenant
    {
        return Tenant::create(['uuid' => Str::uuid(), 'nama_sekolah' => $name, 'npsn' => $npsn, 'subdomain' => 'school-'.$npsn, 'aktif' => true]);
    }

    private function logFor(Tenant $tenant, string $name): DapodikSyncLog
    {
        return TenantContext::run($tenant->id, fn () => DapodikSyncLog::create([
            'tipe' => 'siswa', 'nama_sekolah' => $name, 'sekolah_npsn' => null,
        ]));
    }

    public function test_dashboard_dan_cache_hanya_menampilkan_data_sekolah_sendiri(): void
    {
        Cache::flush();
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('Sekolah A', '10000001');
        $b = $this->school('Sekolah B', '10000002');
        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $b->id]);
        User::factory()->guru()->create(['tenant_id' => $a->id]);
        $this->logFor($a, 'Log Sekolah A');
        $this->logFor($b, 'Log Sekolah B');
        TenantContext::run($a->id, fn () => Siswa::create([
            'nis' => 'A-001', 'nama_peserta_didik' => 'Siswa A', 'kelas' => '1', 'jenis_kelamin' => 'L',
        ]));

        $this->actingAs($adminA)->get(route('admin.dashboard'))->assertOk()
            ->assertViewIs('admin.dashboard-tenant')->assertViewHas('totalUsers', 2)->assertViewHas('siswa', 1)
            ->assertSee('Sekolah A')->assertDontSee('Sekolah B');
        $this->actingAs($adminB)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('totalUsers', 1)->assertViewHas('siswa', 0)
            ->assertSee('Sekolah B')->assertDontSee('Sekolah A');
        $this->actingAs($adminA)->get(route('admin.dashboard'))->assertOk()->assertViewHas('totalUsers', 2);
    }

    public function test_log_sekolah_lain_dan_log_lama_tidak_bisa_dibaca_atau_dihapus(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('Sekolah A', '10000001');
        $b = $this->school('Sekolah B', '10000002');
        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $logA = $this->logFor($a, 'Log A');
        $logB = $this->logFor($b, 'Log B');
        $legacy = DapodikSyncLog::create(['tipe' => 'siswa', 'nama_sekolah' => 'Log lama', 'sekolah_npsn' => $a->npsn]);

        $this->actingAs($adminA)->get(route('admin.dapodik.log-detail', $logA->id))->assertOk()->assertSee('Log A');
        $this->actingAs($adminA)->get(route('admin.dapodik.log-detail', $logB->id))->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.dapodik.log-destroy', $logB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.dapodik.log-detail', $legacy->id))->assertNotFound();
        $this->assertDatabaseHas('dapodik_sync_logs', ['id' => $logB->id, 'tenant_id' => $b->id]);
        $this->assertSame([$logA->id], TenantContext::run($a->id, fn () => DapodikSyncLog::pluck('id')->all()));
    }

    public function test_api_key_memakai_tenant_pemilik_dan_bridge_lama_diblokir(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('Sekolah A', '10000001');
        $b = $this->school('Sekolah B', '10000002');
        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $b->id]);
        $keyA = ApiKey::generate($adminA->id)['plaintext_key'];
        $keyB = ApiKey::generate($adminB->id)['plaintext_key'];
        $this->logFor($a, 'Log A');
        $this->logFor($b, 'Log B');

        $this->withHeader('X-API-Key', $keyA)->getJson('/api/dapodik/sync/status')
            ->assertOk()->assertJsonCount(1, 'recent_logs')->assertJsonPath('recent_logs.0.nama_sekolah', 'Log A');
        $this->withHeader('X-API-Key', $keyB)->getJson('/api/dapodik/sync/status')
            ->assertOk()->assertJsonCount(1, 'recent_logs')->assertJsonPath('recent_logs.0.nama_sekolah', 'Log B');
        $this->withHeader('X-API-Key', $keyA)->postJson('/api/dapodik/sync/ptk', [])->assertStatus(503);
        $this->actingAs($adminA)->getJson(route('admin.dapodik.api.config'))->assertStatus(503);
        $this->actingAs($adminA)->get(route('admin.dapodik.push.index'))->assertStatus(503);
        $this->withHeaders(['X-API-Key' => $keyA, 'X-Tenant-Subdomain' => $b->subdomain])
            ->getJson('/api/dapodik/sync/status')->assertForbidden();
    }

    public function test_backup_global_log_aktivitas_dan_api_key_sekolah_lain_dilindungi(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('Sekolah A', '10000001');
        $b = $this->school('Sekolah B', '10000002');
        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $b->id]);
        $keyA = ApiKey::generate($adminA->id, 'Kunci Sekolah A')['model'];
        $keyB = ApiKey::generate($adminB->id, 'Rahasia Sekolah B')['model'];

        $this->actingAs($adminA)->get(route('admin.backup.index'))->assertStatus(503);
        $this->actingAs($adminA)->get(route('admin.backup.export'))->assertStatus(503);
        $this->actingAs($adminA)->post(route('admin.backup.import'))->assertStatus(503);
        $this->actingAs($adminA)->post(route('admin.backup.wipe'))->assertStatus(503);
        $this->actingAs($adminA)->get(route('admin.log.index'))->assertStatus(503);
        $this->actingAs($adminA)->get(route('admin.api-keys.index'))->assertOk()->assertSee('Kunci Sekolah A')->assertDontSee('Rahasia Sekolah B');
        $this->actingAs($adminA)->post(route('admin.api-keys.toggle', $keyA->id))->assertRedirect();
        $this->assertDatabaseHas('api_keys', ['id' => $keyA->id, 'active' => false]);
        $this->actingAs($adminA)->post(route('admin.api-keys.toggle', $keyB->id))->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.api-keys.destroy', $keyB->id))->assertNotFound();
        $this->assertDatabaseHas('api_keys', ['id' => $keyB->id, 'active' => true]);
    }

    public function test_admin_tidak_dapat_melihat_mengubah_atau_menghapus_akun_sekolah_lain(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('Sekolah A', '10000001');
        $b = $this->school('Sekolah B', '10000002');
        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $userB = User::factory()->siswa()->create(['tenant_id' => $b->id, 'nama_lengkap' => 'Nama Rahasia B']);
        $siswaB = TenantContext::run($b->id, fn () => Siswa::create([
            'nis' => 'B-RAHASIA', 'nama_peserta_didik' => 'Siswa B', 'kelas' => '1', 'jenis_kelamin' => 'L',
        ]));

        $this->actingAs($adminA)->get(route('admin.users.index'))->assertOk()->assertDontSee('Nama Rahasia B');
        $this->actingAs($adminA)->get(route('admin.users.edit', $userB->id))->assertNotFound();
        $this->actingAs($adminA)->put(route('admin.users.update', $userB->id), [])->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.users.destroy', $userB->id))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.users.store'), [
            'nama_lengkap' => 'Orang Tua A', 'nama_pengguna' => 'ortu-a', 'kata_sandi' => 'password-aman',
            'peran' => 'ortu', 'terhubung_dengan' => [$siswaB->id],
        ])->assertSessionHasErrors('terhubung_dengan');
        $this->actingAs($adminA)->postJson(route('admin.users.proses-massal'), [])->assertStatus(503);
        $this->actingAs($adminA)->deleteJson(route('admin.users.destroy-all', 'siswa'))->assertStatus(503);
        $this->assertDatabaseHas('users', ['id' => $userB->id, 'nama_lengkap' => 'Nama Rahasia B', 'deleted_at' => null]);
    }

    public function test_log_tenant_yang_dihapus_tidak_muncul_sebagai_log_sekolah_tunggal(): void
    {
        config(['simantap.multi_tenant_enabled' => false]);
        $tenant = $this->school('Sekolah A', '10000001');
        $log = $this->logFor($tenant, 'Log privat');
        $tenant->delete();

        $this->assertDatabaseHas('dapodik_sync_logs', ['id' => $log->id, 'tenant_id' => null, 'tenant_owned' => true]);
        $this->assertNull(DapodikSyncLog::find($log->id));
    }

    public function test_job_lama_tanpa_konteks_sekolah_ditolak(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $job = new ImportDapodikJob(['siswa' => []]);
        $this->expectException(\RuntimeException::class);
        $job->handle();
    }

    public function test_job_memulihkan_konteks_dan_tidak_mengubah_akun_sekolah_lain(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('Sekolah A', '10000001');
        $b = $this->school('Sekolah B', '10000002');
        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $other = User::factory()->siswa()->create([
            'tenant_id' => $b->id, 'nama_pengguna' => 'NIS-A-1', 'nama_lengkap' => 'Tidak Boleh Diubah',
        ]);
        $log = $this->logFor($a, 'Log A');
        $data = ['sekolah' => ['nama' => 'A'], 'siswa' => [
            ['nama' => 'Siswa Baru', 'nisn' => 'NIS-A-1', 'kelas' => '1', 'jenis_kelamin' => 'L'],
            ['nama' => 'Siswa Lain', 'nisn' => 'NIS-A-2', 'kelas' => '1', 'jenis_kelamin' => 'P'],
        ]];
        $job = new ImportDapodikJob($data, $log->id, 'semua', $adminA->id, 'Admin A', $a->id);
        $lastSql = null;
        $abortedAfter = null;
        if (\DB::connection()->getDriverName() === 'pgsql') {
            \DB::connection()->beforeExecuting(function ($sql) use (&$lastSql, &$abortedAfter) {
                if ($abortedAfter === null) {
                    try {
                        \DB::connection()->getPdo()->query('select 1');
                    } catch (\PDOException $exception) {
                        $abortedAfter = $lastSql;
                    }
                }
                $lastSql = $sql;
            });
        }
        TenantContext::set($b->id);
        $job->handle();

        // Pernyataan terakhir job dapat langsung menggagalkan transaksi tanpa
        // sempat diperiksa oleh hook beforeExecuting; periksa sekali lagi.
        if (\DB::connection()->getDriverName() === 'pgsql') {
            try {
                \DB::connection()->getPdo()->query('select 1');
            } catch (\PDOException $exception) {
                $abortedAfter ??= $lastSql;
            }
        }

        $this->assertNull($abortedAfter, 'PostgreSQL aborted after: '.$abortedAfter);
        $this->assertSame($b->id, TenantContext::id());
        $this->assertSame('Tidak Boleh Diubah', $other->fresh()->nama_lengkap);
        $this->assertDatabaseHas('siswa', ['tenant_id' => $a->id, 'nisn' => 'NIS-A-1']);
        $this->assertDatabaseHas('users', ['tenant_id' => $a->id, 'nama_pengguna' => 'NIS-A-2', 'force_password_change' => true]);
        $this->assertFalse(Hash::check('siswa123', User::where('nama_pengguna', 'NIS-A-2')->firstOrFail()->kata_sandi));
        $this->assertSame(2, TenantContext::run($a->id, fn () => DapodikSyncLog::findOrFail($log->id)->berhasil));
    }
}
