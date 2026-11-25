<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\AlokasiWaktu;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\PengaturanGuru;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\PembelajaranMendalam\GuruScopeService;
use App\Services\PembelajaranMendalam\PengawasAnalyticsService;
use App\Support\SchoolLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrossLevelMultiSchoolSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function buatTenantSekolah(string $npsn, string $nama, string $jenjang): array
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => $npsn,
            'nama_sekolah' => $nama,
            'subdomain' => 'sch-'.strtolower($npsn),
            'aktif' => true,
            'meta' => ['jenjang' => $jenjang],
        ]);

        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'tenant_owned' => true,
        ]);

        $settings = TenantContext::run($tenant->id, fn () => SekolahSettings::create([
            'tenant_id' => $tenant->id,
            'guru_id' => $admin->id,
            'nama_sekolah' => $nama,
            'npsn' => $npsn,
            'jenjang' => $jenjang,
            'status' => 'Negeri',
        ]));

        $semester = TenantContext::run($tenant->id, fn () => Semester::create([
            'tenant_id' => $tenant->id,
            'semester_id' => '20261',
            'nama_semester' => 'ganjil',
            'tahun_ajaran' => '2026/2027',
        ]));

        return [$tenant, $admin, $settings, $semester];
    }

    public function test_school_level_helper_memetakan_tingkat_fase_dan_romawi_lintas_jenjang(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], SchoolLevel::tingkatForJenjang('SD'));
        $this->assertSame([7, 8, 9], SchoolLevel::tingkatForJenjang('SMP'));
        $this->assertSame([7, 8, 9], SchoolLevel::tingkatForJenjang('MTs'));
        $this->assertSame([10, 11, 12], SchoolLevel::tingkatForJenjang('SMA'));
        $this->assertSame([10, 11, 12], SchoolLevel::tingkatForJenjang('SMK'));
        $this->assertSame(range(1, 12), SchoolLevel::tingkatForJenjang('SLB'));

        $this->assertSame(['A', 'B', 'C'], SchoolLevel::faseForJenjang('SD'));
        $this->assertSame(['D'], SchoolLevel::faseForJenjang('SMP'));
        $this->assertSame(['E', 'F'], SchoolLevel::faseForJenjang('SMA'));
        $this->assertSame(['E', 'F'], SchoolLevel::faseForJenjang('SMK'));

        $this->assertSame('A', TujuanPembelajaranPm::faseUntukKelas(2));
        $this->assertSame('B', TujuanPembelajaranPm::faseUntukKelas(4));
        $this->assertSame('C', TujuanPembelajaranPm::faseUntukKelas(6));
        $this->assertSame('D', TujuanPembelajaranPm::faseUntukKelas(8));
        $this->assertSame('E', TujuanPembelajaranPm::faseUntukKelas(10));
        $this->assertSame('F', TujuanPembelajaranPm::faseUntukKelas(12));

        $this->assertSame([7, 8, 9], TujuanPembelajaranPm::kelasUntukFase('D'));
        $this->assertSame([10], TujuanPembelajaranPm::kelasUntukFase('E'));
        $this->assertSame([11, 12], TujuanPembelajaranPm::kelasUntukFase('F'));

        $this->assertSame(32, AlokasiWaktu::mingguPerTahun(6));
        $this->assertSame(32, AlokasiWaktu::mingguPerTahun(9));
        $this->assertSame(32, AlokasiWaktu::mingguPerTahun(12));
        $this->assertSame(36, AlokasiWaktu::mingguPerTahun(8));
        $this->assertSame(36, AlokasiWaktu::mingguPerTahun(10));

        $slbStandar = AlokasiWaktu::standarUntukJenjang('SLB');
        $this->assertArrayHasKey(1, $slbStandar['Matematika']);
        $this->assertArrayHasKey(8, $slbStandar['Matematika']);
        $this->assertArrayHasKey(12, $slbStandar['Matematika']);

        $this->assertSame(7, SchoolLevel::parseTingkat(null, '7A'));
        $this->assertSame(8, SchoolLevel::parseTingkat(null, 'VIII B'));
        $this->assertSame(9, SchoolLevel::parseTingkat('IX', null));
        $this->assertSame(10, SchoolLevel::parseTingkat(null, '10 IPA 1'));
        $this->assertSame(11, SchoolLevel::parseTingkat(null, 'XI MIPA 2'));
        $this->assertSame(12, SchoolLevel::parseTingkat(null, 'XII TKJ 1'));
    }

    public function test_sekolah_smp_mendukung_tingkat_7_sampai_9_dan_fase_d_secara_penuh(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        [$smp, $adminSmp, , $semSmp] = $this->buatTenantSekolah('81000001', 'SMP Negeri 1 Nusantara', 'SMP');

        // 1. Admin SMP mengisi standar Alokasi Waktu -> Kelas 7, 8, 9 terisi
        $this->actingAs($adminSmp)
            ->post(route('admin.pm.alokasi.standar'), ['tahun_ajaran' => '2026/2027'])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_alokasi_waktu', [
            'tenant_id' => $smp->id,
            'nama_mapel' => 'Matematika',
            'tingkat' => 8,
            'tahun_ajaran' => '2026/2027',
        ]);

        $this->actingAs($adminSmp)
            ->get(route('admin.pm.alokasi.index', ['tahun_ajaran' => '2026/2027']))
            ->assertOk()
            ->assertSee('Kelas 7')
            ->assertSee('Kelas 8')
            ->assertSee('Kelas 9')
            ->assertDontSee('Kelas 1</th>', false);

        // 2. Guru SMP mengampu Rombel VIII B (tingkat 8)
        $guruSmp = User::factory()->guru()->create([
            'tenant_id' => $smp->id,
            'tenant_owned' => true,
            'is_wali_kelas' => true,
        ]);

        $rombel8 = TenantContext::run($smp->id, fn () => Rombel::create([
            'tenant_id' => $smp->id,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $semSmp->id,
            'guru_id' => $guruSmp->id,
            'nama_rombel' => 'VIII B',
            'tingkat' => 8,
        ]));

        TenantContext::run($smp->id, fn () => MataPelajaran::create([
            'tenant_id' => $smp->id,
            'nama' => 'Matematika',
            'kode' => 'MTK',
            'jenjang' => 'SMP',
            'aktif' => true,
        ]));

        $scope = TenantContext::run($smp->id, fn () => app(GuruScopeService::class)->resolveForGuru($guruSmp, $smp->id));
        $this->assertSame([8], $scope['tingkat_list']);
        $this->assertSame(['D'], $scope['fase_list']);

        // Simpan konteks PM tingkat 8 (sebelumnya ditolak max:6)
        $this->actingAs($guruSmp)
            ->post(route('guru.pm.konteks.simpan'), [
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
                'tingkat' => 8,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Simpan CP & TP Fase D + ATP kelas_target = 8 (sebelumnya di-clamp ke 6)
        $this->actingAs($guruSmp)
            ->post(route('guru.pm.cp.store'), [
                'nama_mapel' => 'Matematika',
                'fase' => 'D',
                'elemen' => 'Aljabar',
                'deskripsi' => 'Murid memahami persamaan linear dua variabel.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cpD = TenantContext::run($smp->id, fn () => CapaianPembelajaran::where('fase', 'D')->firstOrFail());

        $this->actingAs($guruSmp)
            ->post(route('guru.pm.tp.store'), [
                'nama_mapel' => 'Matematika',
                'fase' => 'D',
                'cp_id' => $cpD->id,
                'elemen' => 'Aljabar',
                'kode_tp' => 'TP.D.1',
                'deskripsi' => 'Menyelesaikan sistem persamaan linear dua variabel.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tpD = TenantContext::run($smp->id, fn () => TujuanPembelajaranPm::where('kode_tp', 'TP.D.1')->firstOrFail());

        $this->actingAs($guruSmp)
            ->post(route('guru.pm.atp.simpan'), [
                'items' => [
                    $tpD->id => [
                        'urutan' => 1,
                        'kelas_target' => 8,
                        'semester_target' => 1,
                        'materi_pokok' => 'SPLDV',
                        'alokasi_jp' => 10,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(8, (int) $tpD->fresh()->kelas_target);

        // Simpan KKTP, cetak PDF KKTP kelas 8, & Prota untuk tingkat 8
        $this->actingAs($guruSmp)
            ->post(route('guru.pm.kktp.interval'), [
                'nama_mapel' => 'Matematika',
                'tingkat' => 8,
                'batas_bawah' => 60,
                'nilai_kktp' => 75,
                'batas_atas' => 88,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pm_kktp_interval', [
            'tenant_id' => $smp->id,
            'nama_mapel' => 'Matematika',
            'tingkat' => 8,
            'nilai_kktp' => 75,
        ]);

        $this->actingAs($guruSmp)
            ->get(route('guru.pm.kktp.pdf', ['tingkat' => 8, 'mapel' => 'Matematika']))
            ->assertOk();

        $this->actingAs($guruSmp)
            ->post(route('guru.pm.prota.ambil-atp'), [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 8,
                'nama_mapel' => 'Matematika',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pm_prota', [
            'tenant_id' => $smp->id,
            'tingkat' => 8,
            'nama_mapel' => 'Matematika',
        ]);

        // Simpan TP Wali Kelas untuk Fase D dan verifikasi tersimpan di PengaturanGuru
        $this->actingAs($guruSmp)
            ->post(route('guru.tp.store'), [
                'kode' => 'TP-8.1',
                'mata_pelajaran' => 'Matematika',
                'fase' => 'Fase D',
                'semester' => 'Ganjil',
                'deskripsi' => 'Menyelesaikan masalah kontekstual SPLDV.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pengaturanGuru = TenantContext::run($smp->id, fn () => PengaturanGuru::where('guru_id', $guruSmp->id)->firstOrFail());
        $tpWali = collect($pengaturanGuru->pengaturan['tujuan_pembelajaran'] ?? []);
        $this->assertTrue($tpWali->contains(fn (array $row) => ($row['kode'] ?? null) === 'TP-8.1' && ($row['fase'] ?? null) === 'Fase D'));

        // 3. Siswa SMP Kelas VIII B melihat Tingkat 8 (Fase D) di dashboard
        $akunSiswaSmp = User::factory()->siswa()->create([
            'tenant_id' => $smp->id,
            'tenant_owned' => true,
            'nama_pengguna' => '0081122334',
            'nama_lengkap' => 'Budi Siswa SMP',
            'kelas' => 'VIII B',
        ]);

        TenantContext::run($smp->id, fn () => Siswa::create([
            'tenant_id' => $smp->id,
            'guru_id' => $guruSmp->id,
            'nama_guru' => $guruSmp->nama_lengkap,
            'rombel_id' => $rombel8->id,
            'nis' => '260801',
            'nisn' => '0081122334',
            'nama_peserta_didik' => 'Budi Siswa SMP',
            'kelas' => 'VIII B',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        $this->actingAs($akunSiswaSmp)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee('Tingkat 8 (Fase D)');
    }

    public function test_sekolah_smk_dan_sma_mendukung_tingkat_10_sampai_12_serta_fase_e_dan_f(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        [$smk, $adminSmk, , $semSmk] = $this->buatTenantSekolah('82000002', 'SMK Negeri 2 Teknologi', 'SMK');

        // Seed default mapel untuk SMK
        $this->actingAs($adminSmk)
            ->post(route('admin.mapel.seed'))
            ->assertRedirect();

        $this->assertDatabaseHas('mata_pelajaran', [
            'tenant_id' => $smk->id,
            'jenjang' => 'SMK',
            'nama' => 'Informatika',
        ]);

        $guruSmk = User::factory()->guru()->create([
            'tenant_id' => $smk->id,
            'tenant_owned' => true,
            'is_wali_kelas' => true,
        ]);

        $rombel12 = TenantContext::run($smk->id, fn () => Rombel::create([
            'tenant_id' => $smk->id,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $semSmk->id,
            'guru_id' => $guruSmk->id,
            'nama_rombel' => 'XII TKJ 1',
            'tingkat' => 12,
        ]));

        // Kokurikuler & TP Fase F (Kelas 12)
        $this->actingAs($guruSmk)
            ->post(route('guru.pm.kokurikuler.store'), [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 12,
                'tema_judul' => 'Rekayasa Jaringan Sekolah Hijau',
                'bentuk_kegiatan' => 'kolaboratif_lintas_disiplin',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pm_kokurikuler', [
            'tenant_id' => $smk->id,
            'tingkat' => 12,
            'tema_judul' => 'Rekayasa Jaringan Sekolah Hijau',
        ]);

        $akunSiswaSmk = User::factory()->siswa()->create([
            'tenant_id' => $smk->id,
            'tenant_owned' => true,
            'nama_pengguna' => '0129988776',
            'nama_lengkap' => 'Siti Siswa SMK',
            'kelas' => 'XII TKJ 1',
        ]);

        TenantContext::run($smk->id, fn () => Siswa::create([
            'tenant_id' => $smk->id,
            'guru_id' => $guruSmk->id,
            'nama_guru' => $guruSmk->nama_lengkap,
            'rombel_id' => $rombel12->id,
            'nis' => '261201',
            'nisn' => '0129988776',
            'nama_peserta_didik' => 'Siti Siswa SMK',
            'kelas' => 'XII TKJ 1',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]));

        $this->actingAs($akunSiswaSmk)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee('Tingkat 12 (Fase F)');

        // Analytics Pengawas pada SMK merender tingkat [10, 11, 12]
        $analytics = TenantContext::run(
            $smk->id,
            fn () => app(PengawasAnalyticsService::class)->auditKeselarasan($smk->id, '2026/2027', 1)
        );
        $this->assertSame([10, 11, 12], array_column($analytics['peta_kelas'], 'tingkat'));
        $this->assertSame(['E', 'F', 'F'], array_column($analytics['peta_kelas'], 'fase'));
    }
}
