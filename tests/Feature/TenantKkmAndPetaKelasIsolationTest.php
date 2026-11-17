<?php

namespace Tests\Feature;

use App\Models\PengajuanIzinPresensi;
use App\Models\PengaturanGuru;
use App\Models\RingkasanGuru;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Admin\ValidasiDataSekolahService;
use App\Services\Navigation\RoleTaskSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantKkmAndPetaKelasIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        Cache::flush();
        parent::tearDown();
    }

    private function school(string $npsn): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'Sekolah '.$npsn,
            'npsn' => $npsn,
            'subdomain' => 'kkm-'.$npsn,
            'aktif' => true,
        ]);
    }

    public function test_get_kkm_terisolasi_per_tenant_dan_cache_tidak_bocor_antar_sekolah(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('20000001');
        $b = $this->school('20000002');

        $guruA = User::factory()->guru()->create(['tenant_id' => $a->id, 'nama_lengkap' => 'Guru Alpha']);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id, 'nama_lengkap' => 'Guru Beta']);

        $pengaturanA = PengaturanGuru::create([
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '5A',
            'kkm' => 82,
        ]);
        PengaturanGuru::create([
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'mata_pelajaran' => 'IPA',
            'kelas' => '6B',
            'kkm' => 68,
        ]);

        $this->assertSame(82, TenantContext::run($a->id, fn () => getKKM()));
        $this->assertSame(68, TenantContext::run($b->id, fn () => getKKM()));

        // Dari konteks Sekolah A, meminta guruId milik Sekolah B tidak boleh membocorkan KKM 68.
        $this->assertSame(82, TenantContext::run($a->id, fn () => getKKM($guruB->id)));

        // Tanpa konteks sekolah di mode multi-tenant, kembalikan default aman 70.
        $this->assertSame(70, getKKM());

        // Perubahan KKM pada Sekolah A langsung me-bust cache Sekolah A tanpa memengaruhi Sekolah B.
        TenantContext::run($a->id, fn () => $pengaturanA->update(['kkm' => 85]));
        $this->assertSame(85, TenantContext::run($a->id, fn () => getKKM()));
        $this->assertSame(68, TenantContext::run($b->id, fn () => getKKM()));
    }

    public function test_admin_tidak_bisa_mengakses_atau_mengubah_bobot_kode_akses_dan_peta_kelas_sekolah_lain(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('20000003');
        $b = $this->school('20000004');

        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $a->id, 'nama_lengkap' => 'Guru Unik A']);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id, 'nama_lengkap' => 'Guru Rahasia B']);

        $pengaturanA = PengaturanGuru::create([
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'kelas' => '4A',
            'kkm' => 78,
        ]);
        $pengaturanB = PengaturanGuru::create([
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'kelas' => '6B',
            'kkm' => 72,
            'pengaturan' => ['kode_akses' => 'RAHASIA-B'],
        ]);

        $this->actingAs($adminA)->get(route('admin.bobot.index'))
            ->assertOk()
            ->assertSee('Guru Unik A')
            ->assertDontSee('Guru Rahasia B');

        $this->actingAs($adminA)->post(route('admin.bobot.update', $pengaturanB->id), [
            'kkm' => 99,
            'bobot_tugas' => 30,
            'bobot_uh' => 20,
            'bobot_uts' => 20,
            'bobot_uas' => 30,
            'bobot_formatif' => 30,
            'bobot_sumatif' => 40,
            'bobot_sumatif_akhir' => 30,
        ])->assertNotFound();
        $this->assertSame(72, (int) $pengaturanB->fresh()->kkm);

        $this->actingAs($adminA)->get(route('admin.kode-akses.index'))
            ->assertOk()
            ->assertSee('Guru Unik A')
            ->assertDontSee('Guru Rahasia B');

        $this->actingAs($adminA)->post(route('admin.kode-akses.update', $pengaturanB->id), [
            'kode_akses' => 'BAJAK',
        ])->assertNotFound();
        $this->assertSame('RAHASIA-B', $pengaturanB->fresh()->pengaturan['kode_akses'] ?? null);

        $this->actingAs($adminA)->delete(route('admin.peta-kelas.destroy', $pengaturanB->id))
            ->assertNotFound();
        $this->assertDatabaseHas('pengaturan_guru', ['id' => $pengaturanB->id]);

        $this->actingAs($adminA)
            ->getJson(route('admin.peta-kelas.kelas-options', ['guru_id' => $guruB->id]))
            ->assertOk()
            ->assertExactJson(['kelas' => []]);

        // Pastikan admin tetap dapat mengubah pengaturan gurunya sendiri.
        $this->actingAs($adminA)->post(route('admin.bobot.update', $pengaturanA->id), [
            'kkm' => 84,
            'bobot_tugas' => 30,
            'bobot_uh' => 20,
            'bobot_uts' => 20,
            'bobot_uas' => 30,
            'bobot_formatif' => 30,
            'bobot_sumatif' => 40,
            'bobot_sumatif_akhir' => 30,
        ])->assertRedirect(route('admin.bobot.index'));
        $this->assertSame(84, (int) $pengaturanA->fresh()->kkm);
    }

    public function test_kepsek_peta_kelas_dan_hasil_belajar_terisolasi_per_tenant_serta_memakai_kkm_sekolah(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('20000005');
        $b = $this->school('20000006');

        $kepsekA = User::factory()->kepsek()->create(['tenant_id' => $a->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $a->id, 'nama_lengkap' => 'Guru Alpha Kepsek']);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id, 'nama_lengkap' => 'Guru Beta Rahasia']);

        PengaturanGuru::create([
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '5A',
            'kkm' => 83,
        ]);
        PengaturanGuru::create([
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'mata_pelajaran' => 'Bahasa',
            'kelas' => '6B',
            'kkm' => 65,
        ]);

        TenantContext::run($a->id, function () use ($guruA): void {
            RingkasanGuru::create([
                'guru_id' => $guruA->id,
                'nama_guru' => $guruA->nama_lengkap,
                'mata_pelajaran' => 'Matematika',
                'kelas' => 'Kelas 5A Alpha',
                'kkm' => 83,
                'jumlah_siswa' => 20,
                'rata_rata' => 86,
                'ketuntasan_persen' => 90,
                'kehadiran_persen' => 95,
            ]);
        });
        TenantContext::run($b->id, function () use ($guruB): void {
            RingkasanGuru::create([
                'guru_id' => $guruB->id,
                'nama_guru' => $guruB->nama_lengkap,
                'mata_pelajaran' => 'Bahasa',
                'kelas' => 'Kelas 6B Rahasia',
                'kkm' => 65,
                'jumlah_siswa' => 18,
                'rata_rata' => 70,
                'ketuntasan_persen' => 80,
                'kehadiran_persen' => 88,
            ]);
        });

        $this->actingAs($kepsekA)->get(route('kepsek.peta-kelas.index'))
            ->assertOk()
            ->assertSee('Guru Alpha Kepsek')
            ->assertSee('83')
            ->assertDontSee('Guru Beta Rahasia');

        $this->actingAs($kepsekA)->get(route('kepsek.peta-kelas.detail', $guruB->id))->assertNotFound();
        $this->actingAs($kepsekA)->get(route('kepsek.peta-kelas.detail', $guruA->id))->assertOk();

        $this->actingAs($kepsekA)->get(route('kepsek.hasil-belajar.index'))
            ->assertOk()
            ->assertSee('Kelas 5A Alpha')
            ->assertSee('const kkmSekolah = 83;', false)
            ->assertDontSee('Kelas 6B Rahasia')
            ->assertDontSee('Guru Beta Rahasia');
    }

    public function test_mode_sekolah_tunggal_tetap_berfungsi_untuk_kkm_bobot_dan_kepsek_peta_kelas(): void
    {
        config(['simantap.multi_tenant_enabled' => false]);

        $admin = User::factory()->admin()->create();
        $kepsek = User::factory()->kepsek()->create();
        $guru = User::factory()->guru()->create(['nama_lengkap' => 'Guru Tunggal']);

        PengaturanGuru::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'kelas' => '3A',
            'kkm' => 78,
        ]);

        $this->assertSame(78, getKKM());
        $this->actingAs($admin)->get(route('admin.bobot.index'))->assertOk()->assertSee('Guru Tunggal');
        $this->actingAs($kepsek)->get(route('kepsek.peta-kelas.index'))->assertOk()->assertSee('Guru Tunggal');
        $this->actingAs($kepsek)->get(route('kepsek.hasil-belajar.index'))->assertOk()->assertSee('const kkmSekolah = 78;', false);
    }

    public function test_validasi_data_mendeteksi_nik_pegawai_ambigu_dan_notifikasi_guru_menghitung_izin_tanpa_bocor_alasan(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('20000007');
        $b = $this->school('20000008');

        $guruA1 = User::factory()->guru()->create(['tenant_id' => $a->id, 'nik' => '320100001']);
        User::factory()->guru()->create(['tenant_id' => $a->id, 'nik' => '320100001']);
        // Pegawai di Sekolah B dengan NIK sama tidak boleh tercampur.
        User::factory()->guru()->create(['tenant_id' => $b->id, 'nik' => '320100002']);
        User::factory()->guru()->create(['tenant_id' => $b->id, 'nik' => '320100002']);

        $ringkasanA = TenantContext::run($a->id, fn () => app(ValidasiDataSekolahService::class)->ringkasan($a->id));
        $itemNik = collect($ringkasanA['items'])->firstWhere('kode', 'nik_pegawai_ambigu');
        $this->assertNotNull($itemNik);
        $this->assertSame(1, $itemNik['jumlah']);
        $this->assertSame('warn', $itemNik['status']);

        $semester = Semester::create(['semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']);
        $ortuA = User::factory()->ortu()->create(['tenant_id' => $a->id]);

        TenantContext::run($a->id, function () use ($guruA1, $semester, $ortuA): void {
            $rombel = Rombel::create([
                'dapodik_id' => 'RB-IZIN-A',
                'semester_id' => $semester->id,
                'nama_rombel' => '4A',
                'tingkat' => '4',
                'guru_id' => $guruA1->id,
            ]);
            $siswa = Siswa::create([
                'uuid' => (string) Str::uuid(),
                'guru_id' => $guruA1->id,
                'rombel_id' => $rombel->id,
                'nama_guru' => $guruA1->nama_lengkap,
                'nis' => 'IZIN-01',
                'nama_peserta_didik' => 'Siswa Izin A',
                'kelas' => '4A',
                'jenis_kelamin' => 'L',
                'aktif' => true,
            ]);
            PengajuanIzinPresensi::create([
                'siswa_id' => $siswa->id,
                'orang_tua_id' => $ortuA->id,
                'tanggal' => today(),
                'jenis' => 'S',
                'alasan' => 'Kontrol dokter spesialis rahasia',
            ]);
        });

        $notifs = TenantContext::run($a->id, fn () => app(RoleTaskSummaryService::class)->notificationsFor($guruA1));
        $serialized = json_encode($notifs);
        $this->assertStringContainsString('1 pengajuan izin siswa menunggu verifikasi', (string) $serialized);
        $this->assertStringNotContainsString('Kontrol dokter spesialis rahasia', (string) $serialized);
    }
}
