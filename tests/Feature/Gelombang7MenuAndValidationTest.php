<?php

namespace Tests\Feature;

use App\Models\Elearning\KelasDigital;
use App\Models\Elearning\KelasDigitalModul;
use App\Models\Elearning\KelasDigitalPengumpulan;
use App\Models\Elearning\KelasDigitalPertemuan;
use App\Models\Elearning\KelasDigitalTugas;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Tenant\Support\TenantContext;
use App\Services\Navigation\CommandPaletteService;
use App\Services\Navigation\RoleTaskSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Gelombang7MenuAndValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_command_palette_terfilter_hak_akses_wali_kelas_dan_seluruh_rute_valid(): void
    {
        $service = app(CommandPaletteService::class);

        $guruMapel = User::factory()->guru()->create(['is_wali_kelas' => false]);
        $guruWalas = User::factory()->guru()->create(['is_wali_kelas' => true]);
        $siswa = User::factory()->siswa()->create();
        $kepsek = User::factory()->kepsek()->create();
        $ortu = User::factory()->ortu()->create();
        $tendik = User::factory()->tendik()->create();
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        // Guru mapel non-wali: ada Perangkat Ajar (CP/TP/ATP) & Jurnal, TIDAK ada menu wali kelas
        $mapelRoutes = collect($service->itemsFor($guruMapel))->pluck('route')->all();
        $this->assertContains('guru.perangkat.index', $mapelRoutes);
        $this->assertContains('guru.perangkat.jurnal.index', $mapelRoutes);
        $this->assertContains('guru.kelas-digital.index', $mapelRoutes);
        $this->assertNotContains('guru.walas.ekskul', $mapelRoutes);
        $this->assertNotContains('guru.walas.leger', $mapelRoutes);
        $this->assertNotContains('admin.dashboard', $mapelRoutes);

        // Guru wali kelas: MENDAPATKAN menu wali kelas
        $walasRoutes = collect($service->itemsFor($guruWalas))->pluck('route')->all();
        $this->assertContains('guru.walas.ekskul', $walasRoutes);
        $this->assertContains('guru.walas.leger', $walasRoutes);

        // Admin mendapatkan Validasi Kelengkapan Data
        $adminRoutes = collect($service->itemsFor($admin))->pluck('route')->all();
        $this->assertContains('admin.validasi-data.index', $adminRoutes);
        $this->assertNotContains('superadmin.tenants.index', $adminRoutes);

        // Siswa hanya rute siswa, tidak ada rute admin/guru
        $siswaRoutes = collect($service->itemsFor($siswa))->pluck('route')->all();
        $this->assertContains('siswa.kelas-digital.index', $siswaRoutes);
        $this->assertNotContains('guru.perangkat.index', $siswaRoutes);
        $this->assertNotContains('admin.dashboard', $siswaRoutes);

        // Kepsek mendapatkan Supervisi Perangkat Ajar
        $kepsekRoutes = collect($service->itemsFor($kepsek))->pluck('route')->all();
        $this->assertContains('kepsek.perangkat.index', $kepsekRoutes);
        $this->assertNotContains('admin.users.index', $kepsekRoutes);

        // Ortu & Tendik & SuperAdmin tidak kosong dan tidak punya duplikat rute
        foreach ([$ortu, $tendik, $superAdmin] as $actor) {
            $routes = collect($service->itemsFor($actor))->pluck('route')->all();
            $this->assertNotEmpty($routes);
            $this->assertSame(count($routes), count(array_unique($routes)));
        }

        // Saat modul asesmen dimatikan, rute CBT/Tryout tidak muncul di Ctrl+K admin maupun guru
        config(['simantap.cbt_enabled' => false]);
        $adminTanpaCbt = collect($service->itemsFor($admin))->pluck('route')->all();
        $this->assertNotContains('admin.cbt.sessions.index', $adminTanpaCbt);
    }

    public function test_admin_validasi_kelengkapan_data_mendeteksi_temuan_dan_terisolasi_tenant(): void
    {
        $tenantA = Tenant::create([
            'slug' => 'sdn-validasi-a',
            'npsn' => '40391001',
            'nama_sekolah' => 'SDN Validasi A',
            'kode_unik' => 'VALA01',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'slug' => 'sdn-validasi-b',
            'npsn' => '40391002',
            'nama_sekolah' => 'SDN Validasi B',
            'kode_unik' => 'VALB02',
            'aktif' => true,
        ]);

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $tenantB->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $tenantA->id]);

        TenantContext::set($tenantA);
        Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'nis' => 'V001',
            'nama_peserta_didik' => 'Siswa Tanpa Rombel Tenant A',
            'kelas' => '6A',
            'rombel_id' => null,
            'aktif' => true,
        ]);
        Rombel::create([
            'tenant_id' => $tenantA->id,
            'nama_rombel' => '6A Tanpa Wali',
            'tingkat' => '6',
            'guru_id' => null,
        ]);

        // Admin A melihat kedua temuan tenant A
        $this->actingAs($adminA)->get(route('admin.validasi-data.index'))
            ->assertOk()
            ->assertSee('Siswa Tanpa Rombel Tenant A')
            ->assertSee('6A Tanpa Wali');

        // Guru ditolak (403)
        $this->actingAs($guruA)->get(route('admin.validasi-data.index'))->assertForbidden();

        // Admin B (tenant B) tidak melihat temuan milik tenant A
        TenantContext::set($tenantB);
        $this->actingAs($adminB)->get(route('admin.validasi-data.index'))
            ->assertOk()
            ->assertDontSee('Siswa Tanpa Rombel Tenant A')
            ->assertDontSee('6A Tanpa Wali');
    }

    public function test_admin_dapat_mengatur_flag_is_wali_kelas_eksplisit_untuk_guru(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nama_pengguna' => 'guru_flag_ui',
            'email' => 'guru_flag_ui@simantap.sch.id',
            'is_wali_kelas' => null,
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $guru), [
            'nama_lengkap' => $guru->nama_lengkap,
            'nama_pengguna' => $guru->nama_pengguna,
            'email' => $guru->email,
            'peran' => 'guru',
            'kelas_mata_pelajaran' => 'PJOK',
            'is_wali_kelas' => '0',
            'aktif' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse($guru->fresh()->is_wali_kelas);
        $this->assertFalse($guru->fresh()->isWaliKelas());

        $this->actingAs($admin)->put(route('admin.users.update', $guru), [
            'nama_lengkap' => $guru->nama_lengkap,
            'nama_pengguna' => $guru->nama_pengguna,
            'email' => $guru->email,
            'peran' => 'guru',
            'kelas_mata_pelajaran' => 'Wali 6A',
            'is_wali_kelas' => '1',
            'aktif' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue($guru->fresh()->is_wali_kelas);
        $this->assertTrue($guru->fresh()->isWaliKelas());
    }

    public function test_role_task_summary_service_menampilkan_tugas_belum_dinilai_untuk_guru(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'nis' => 'NS901',
            'nama_peserta_didik' => 'Budi Uji',
            'kelas' => '6A',
            'aktif' => true,
        ]);

        $kelas = KelasDigital::create([
            'guru_id' => $guru->id,
            'nama_kelas' => 'Matematika 6A',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'aktif' => true,
        ]);
        $modul = KelasDigitalModul::create(['kelas_digital_id' => $kelas->id, 'judul' => 'Bab 1', 'urutan' => 1]);
        $pertemuan = KelasDigitalPertemuan::create(['modul_id' => $modul->id, 'judul' => 'Pertemuan 1', 'urutan' => 1]);
        $tugas = KelasDigitalTugas::create([
            'kelas_digital_id' => $kelas->id,
            'pertemuan_id' => $pertemuan->id,
            'judul' => 'Tugas Pecahan',
            'status' => 'diterbitkan',
            'skor_maksimal' => 100,
        ]);
        KelasDigitalPengumpulan::create([
            'tugas_id' => $tugas->id,
            'siswa_id' => $siswa->id,
            'jawaban_teks' => 'Jawaban saya',
            'status' => 'dikumpulkan',
            'dikumpulkan_pada' => now(),
        ]);

        $notifs = app(RoleTaskSummaryService::class)->notificationsFor($guru);
        $teksGabung = collect($notifs)->pluck('teks')->implode(' | ');
        $this->assertStringContainsString('1 pengumpulan tugas Kelas Digital menunggu dinilai', $teksGabung);
    }
}
