<?php

namespace Tests\Feature;

use App\Models\Elearning\KelasDigital;
use App\Models\Elearning\KelasDigitalModul;
use App\Models\Elearning\KelasDigitalPengumpulan;
use App\Models\Elearning\KelasDigitalPertemuan;
use App\Models\Elearning\KelasDigitalTugas;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
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

    private function makeSemester(): Semester
    {
        return Semester::firstOrCreate(
            ['semester_id' => '20261'],
            ['tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']
        );
    }

    public function test_command_palette_terfilter_hak_akses_wali_kelas_dan_seluruh_rute_valid(): void
    {
        $service = app(CommandPaletteService::class);

        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        // Saat data sekolah benar-benar kosong (hanya akun Admin), hanya menu inti yang muncul di Ctrl+K
        $adminKosongRoutes = collect($service->itemsFor($admin))->pluck('route')->all();
        $this->assertContains('admin.dashboard', $adminKosongRoutes);
        $this->assertContains('admin.dapodik.index', $adminKosongRoutes);
        $this->assertNotContains('admin.validasi-data.index', $adminKosongRoutes);
        $this->assertNotContains('admin.users.siswa', $adminKosongRoutes);
        $this->assertNotContains('admin.cetak.nilai', $adminKosongRoutes);
        $this->assertNotContains('superadmin.tenants.index', $adminKosongRoutes);

        $guruMapel = User::factory()->guru()->create(['is_wali_kelas' => false]);
        $guruWalas = User::factory()->guru()->create(['is_wali_kelas' => true]);
        $siswa = User::factory()->siswa()->create();
        $kepsek = User::factory()->kepsek()->create();
        $ortu = User::factory()->ortu()->create();
        $tendik = User::factory()->tendik()->create();

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

        // Begitu guru tersedia namun siswa belum ada, Validasi & Guru muncul tetapi Siswa/Cetak Rapor belum muncul
        $adminHanyaGuruRoutes = collect($service->itemsFor($admin))->pluck('route')->all();
        $this->assertContains('admin.validasi-data.index', $adminHanyaGuruRoutes);
        $this->assertContains('admin.referensi.guru', $adminHanyaGuruRoutes);
        $this->assertNotContains('admin.users.siswa', $adminHanyaGuruRoutes);
        $this->assertNotContains('admin.cetak.nilai', $adminHanyaGuruRoutes);

        // Begitu data siswa tersedia, menu Siswa dan e-Rapor ikut muncul di Ctrl+K
        Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guruMapel->id,
            'nama_guru' => $guruMapel->nama_lengkap,
            'nis' => 'CP001',
            'nama_peserta_didik' => 'Siswa Command Palette',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $adminRoutes = collect($service->itemsFor($admin))->pluck('route')->all();
        $this->assertContains('admin.validasi-data.index', $adminRoutes);
        $this->assertContains('admin.users.siswa', $adminRoutes);
        $this->assertContains('admin.cetak.nilai', $adminRoutes);
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

        // Saat modul asesmen dimatikan, rute CBT/Tryout tidak muncul di Ctrl+K admin
        config(['simantap.cbt_enabled' => false]);
        $adminTanpaCbt = collect($service->itemsFor($admin))->pluck('route')->all();
        $this->assertNotContains('admin.cbt.sessions.index', $adminTanpaCbt);
    }

    public function test_admin_validasi_kelengkapan_data_mendeteksi_temuan_dan_terisolasi_tenant(): void
    {
        $semester = $this->makeSemester();

        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Validasi A',
            'subdomain' => 'sdn-validasi-a',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Validasi B',
            'subdomain' => 'sdn-validasi-b',
            'aktif' => true,
        ]);

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $tenantB->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $tenantA->id]);

        TenantContext::set($tenantA->id);
        Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'nis' => 'V001',
            'nama_peserta_didik' => 'Siswa Tanpa Rombel Tenant A',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'rombel_id' => null,
            'aktif' => true,
        ]);
        Rombel::create([
            'tenant_id' => $tenantA->id,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $semester->id,
            'nama_rombel' => '6A Tanpa Wali',
            'tingkat' => 6,
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
        TenantContext::set($tenantB->id);
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
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'NS901',
            'nama_peserta_didik' => 'Budi Uji',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $kelas = KelasDigital::create([
            'guru_id' => $guru->id,
            'nama_kelas' => 'Matematika 6A',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'aktif' => true,
        ]);
        $modul = KelasDigitalModul::create([
            'kelas_digital_id' => $kelas->id,
            'judul' => 'Bab 1',
            'urutan' => 1,
            'dipublikasikan' => true,
        ]);
        $pertemuan = KelasDigitalPertemuan::create([
            'modul_id' => $modul->id,
            'judul' => 'Pertemuan 1',
            'urutan' => 1,
            'dipublikasikan' => true,
        ]);
        $tugas = KelasDigitalTugas::create([
            'kelas_digital_id' => $kelas->id,
            'pertemuan_id' => $pertemuan->id,
            'guru_id' => $guru->id,
            'judul' => 'Tugas Pecahan',
            'instruksi' => 'Kerjakan soal pecahan.',
            'tipe_pengumpulan' => 'teks',
            'nilai_maksimal' => 100,
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
