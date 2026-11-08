<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BackupController;
use App\Models\Kehadiran;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Telescope\Telescope;
use Tests\TestCase;

class QaSecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function createTenant(string $nama, string $npsn): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => $nama,
            'npsn' => $npsn,
            'subdomain' => 'sekolah-'.$npsn,
            'aktif' => true,
        ]);
    }

    public function test_super_admin_login_langsung_dialihkan_ke_dasbor_superadmin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create([
            'nama_pengguna' => 'superadmin_monsa',
            'kata_sandi' => Hash::make('SuperAman123!'),
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $response = $this->post(route('login'), [
            'nama_pengguna' => $superAdmin->nama_pengguna,
            'kata_sandi' => 'SuperAman123!',
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));
    }

    public function test_input_nilai_menolak_judul_penilaian_lebih_dari_255_karakter(): void
    {
        $guru = User::factory()->guru()->create(['force_password_change' => false]);
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => '1001',
            'nama_peserta_didik' => 'Budi Santoso',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $this->actingAs($guru)
            ->postJson(route('guru.input-nilai.simpan'), [
                'jenis' => 'Tugas',
                'judul_penilaian' => str_repeat('X', 300),
                'nilai' => [
                    ['siswa_id' => $siswa->id, 'nilai' => 90],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['judul_penilaian']);
    }

    public function test_nis_siswa_unik_per_sekolah_namun_boleh_sama_antar_sekolah(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $sekolahA = $this->createTenant('SDN Mongisidi 1', '40312001');
        $sekolahB = $this->createTenant('SDN Makassar 2', '40312002');

        $guruA = User::factory()->guru()->create(['tenant_id' => $sekolahA->id, 'force_password_change' => false]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $sekolahB->id, 'force_password_change' => false]);

        $this->actingAs($guruA)->post(route('guru.siswa.store'), [
            'nama' => 'Siswa Sekolah A',
            'nis' => '2026001',
            'jenis_kelamin' => 'L',
            'kelas' => '1A',
            'aktif' => true,
        ])->assertRedirect(route('guru.siswa.index'));

        // Sekolah B boleh memakai NIS lokal yang sama ('2026001')
        $this->actingAs($guruB)->post(route('guru.siswa.store'), [
            'nama' => 'Siswa Sekolah B',
            'nis' => '2026001',
            'jenis_kelamin' => 'P',
            'kelas' => '1A',
            'aktif' => true,
        ])->assertRedirect(route('guru.siswa.index'));

        // Duplikat NIS di sekolah yang sama (Sekolah A) tetap ditolak
        $this->actingAs($guruA)->post(route('guru.siswa.store'), [
            'nama' => 'Siswa Duplikat A',
            'nis' => '2026001',
            'jenis_kelamin' => 'L',
            'kelas' => '1B',
            'aktif' => true,
        ])->assertSessionHasErrors(['nis']);

        $this->assertDatabaseHas('siswa', ['tenant_id' => $sekolahA->id, 'nis' => '2026001']);
        $this->assertDatabaseHas('siswa', ['tenant_id' => $sekolahB->id, 'nis' => '2026001']);
    }

    public function test_peta_kelas_dan_penilaian_admin_terisolasi_antar_sekolah(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $sekolahA = $this->createTenant('Sekolah Alpha', '40313001');
        $sekolahB = $this->createTenant('Sekolah Beta', '40313002');

        $adminA = User::factory()->admin()->create(['tenant_id' => $sekolahA->id, 'force_password_change' => false]);
        $guruA = User::factory()->guru()->create([
            'tenant_id' => $sekolahA->id,
            'nama_lengkap' => 'Guru Milik Alpha',
            'aktif' => true,
        ]);
        $guruB = User::factory()->guru()->create([
            'tenant_id' => $sekolahB->id,
            'nama_lengkap' => 'Guru Rahasia Beta',
            'aktif' => true,
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.penilaian.status'))
            ->assertOk()
            ->assertSee('Guru Milik Alpha')
            ->assertDontSee('Guru Rahasia Beta');

        $this->actingAs($adminA)
            ->get(route('admin.peta-kelas.index'))
            ->assertOk()
            ->assertSee('Guru Milik Alpha')
            ->assertDontSee('Guru Rahasia Beta');

        $this->actingAs($adminA)
            ->post(route('admin.peta-kelas.store'), [
                'guru_id' => $guruB->id,
                'kelas' => '6A',
                'mata_pelajaran' => 'Matematika',
            ])
            ->assertNotFound();
    }

    public function test_backup_wipe_all_tidak_pernah_truncate_data_sekolah_lain(): void
    {
        $sekolahA = $this->createTenant('Sekolah Wipe A', '40314001');
        $sekolahB = $this->createTenant('Sekolah Aman B', '40314002');

        $adminA = User::factory()->admin()->create([
            'tenant_id' => $sekolahA->id,
            'kata_sandi' => Hash::make('SandiAdminA123!'),
            'force_password_change' => false,
        ]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $sekolahA->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $sekolahB->id]);

        $siswaA = TenantContext::run($sekolahA->id, fn () => Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'nis' => 'A-101',
            'nama_peserta_didik' => 'Siswa Hapus A',
            'kelas' => '5A',
            'jenis_kelamin' => 'L',
        ]));
        $siswaB = TenantContext::run($sekolahB->id, fn () => Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'nis' => 'B-101',
            'nama_peserta_didik' => 'Siswa Utuh B',
            'kelas' => '5A',
            'jenis_kelamin' => 'P',
        ]));

        TenantContext::run($sekolahA->id, fn () => Kehadiran::create([
            'guru_id' => $guruA->id,
            'siswa_id' => $siswaA->id,
            'tanggal' => '2026-11-08',
            'status' => 'H',
        ]));
        TenantContext::run($sekolahB->id, fn () => Kehadiran::create([
            'guru_id' => $guruB->id,
            'siswa_id' => $siswaB->id,
            'tanggal' => '2026-11-08',
            'status' => 'H',
        ]));

        // Uji defense-in-depth langsung pada controller method seandainya middleware dilewati
        $this->actingAs($adminA);
        TenantContext::set($sekolahA->id);

        $request = Request::create('/admin/backup/wipe', 'POST', [
            'konfirmasi' => 'HAPUS SEMUA DATA',
            'password' => 'SandiAdminA123!',
        ]);
        $request->setUserResolver(fn () => $adminA);

        app(BackupController::class)->wipeAll($request);

        $this->assertDatabaseMissing('siswa', ['id' => $siswaA->id]);
        $this->assertDatabaseMissing('kehadiran', ['siswa_id' => $siswaA->id]);
        $this->assertDatabaseHas('siswa', ['id' => $siswaB->id, 'nama_peserta_didik' => 'Siswa Utuh B']);
        $this->assertDatabaseHas('kehadiran', ['siswa_id' => $siswaB->id]);
    }

    public function test_telescope_menyensor_kata_sandi_dan_menolak_admin_sekolah_tenant(): void
    {
        $hidden = Telescope::$hiddenRequestParameters;
        $this->assertContains('kata_sandi', $hidden);
        $this->assertContains('kata_sandi_confirmation', $hidden);
        $this->assertContains('password', $hidden);
        $this->assertContains('current_password', $hidden);

        $superAdmin = User::factory()->superAdmin()->create(['aktif' => true]);
        $sekolah = $this->createTenant('Sekolah Tenant', '40315001');
        $tenantAdmin = User::factory()->admin()->create([
            'nama_pengguna' => 'admin',
            'tenant_id' => $sekolah->id,
            'aktif' => true,
        ]);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewTelescope'));
        $this->assertFalse(Gate::forUser($tenantAdmin)->allows('viewTelescope'));
    }

    public function test_aset_favicon_dan_logo_terkompresi_optimal(): void
    {
        $faviconPng = public_path('favicon.png');
        $faviconIco = public_path('favicon.ico');
        $logoPng = public_path('images/logo.png');

        $this->assertFileExists($faviconPng);
        $this->assertFileExists($faviconIco);
        $this->assertFileExists($logoPng);

        $this->assertLessThan(15 * 1024, filesize($faviconPng), 'favicon.png harus < 15 KB');
        $this->assertLessThan(25 * 1024, filesize($faviconIco), 'favicon.ico harus < 25 KB');
        $this->assertLessThan(100 * 1024, filesize($logoPng), 'images/logo.png harus < 100 KB');
    }
}
