<?php

namespace Tests\Feature;

use App\Models\Aktivitas;
use App\Models\NilaiErapot;
use App\Models\PresensiMesinSyncLog;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Models\Tka\Dimension;
use App\Models\Tka\EarlyWarning;
use App\Models\Tka\ParentReport;
use App\Models\Tka\StudentAssessment;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditRemediationNov2026Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function buatSekolah(string $npsn): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => $npsn,
            'nama_sekolah' => 'Sekolah '.$npsn,
            'subdomain' => 'sekolah-'.$npsn,
            'aktif' => true,
        ]);
    }

    public function test_p0_1_admin_crud_dimensi_tka_memakai_tabel_dan_enum_yang_benar(): void
    {
        $admin = User::factory()->admin()->create();
        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tka.dimensions.store'), [
                'kode_dimensi' => 'DIM-ADM-01',
                'nama_dimensi' => 'Dimensi Admin Uji',
                'kategori' => 'mandiri',
                'deskripsi' => 'Deskripsi uji',
            ])
            ->assertRedirect(route('admin.tka.dimensions.index'))
            ->assertSessionHasNoErrors();

        $dim = Dimension::where('kode_dimensi', 'DIM-ADM-01')->firstOrFail();
        $this->assertSame($semester->id, (int) $dim->semester_id);
        $this->assertSame('mandiri', $dim->kategori);
        $this->assertIsArray($dim->rubrik_penilaian);

        $this->actingAs($admin)
            ->put(route('admin.tka.dimensions.update', $dim), [
                'kode_dimensi' => 'DIM-ADM-01',
                'nama_dimensi' => 'Dimensi Admin Diperbarui',
                'kategori' => 'kreatif',
                'deskripsi' => 'Diperbarui',
            ])
            ->assertRedirect(route('admin.tka.dimensions.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('kreatif', $dim->fresh()->kategori);
    }

    public function test_p0_2_pencarian_transkrip_dengan_orwhere_tidak_membocorkan_siswa_sekolah_lain(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->buatSekolah('10000101');
        $b = $this->buatSekolah('10000102');

        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $a->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id]);

        TenantContext::run($a->id, fn () => Siswa::create([
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'nis' => 'NIS-A-01',
            'nisn' => '1111111111',
            'nama_peserta_didik' => 'Budi Sekolah A',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        TenantContext::run($b->id, fn () => Siswa::create([
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'nis' => 'NIS-B-01',
            'nisn' => '2222222222',
            'nama_peserta_didik' => 'Budi Rahasia Sekolah B',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        foreach (['admin.transkrip.nomor', 'admin.transkrip.input', 'admin.transkrip.cetak'] as $route) {
            $this->actingAs($adminA)
                ->get(route($route, ['q' => 'Budi']))
                ->assertOk()
                ->assertSee('Budi Sekolah A')
                ->assertDontSee('Budi Rahasia Sekolah B');
        }
    }

    public function test_p0_3_early_warning_dan_parent_report_terisolasi_antar_sekolah(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->buatSekolah('10000201');
        $b = $this->buatSekolah('10000202');

        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $ortuA = User::factory()->ortu()->create(['tenant_id' => $a->id, 'terhubung_dengan' => []]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id]);

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);
        $dim = Dimension::create([
            'semester_id' => $semester->id,
            'kode_dimensi' => 'DIM-ISO',
            'nama_dimensi' => 'Mandiri',
            'kategori' => 'mandiri',
            'rubrik_penilaian' => [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'],
        ]);

        $siswaB = TenantContext::run($b->id, fn () => Siswa::create([
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'nis' => 'NIS-B-99',
            'nama_peserta_didik' => 'Siswa Peringatan B',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        $warningB = EarlyWarning::create([
            'siswa_id' => $siswaB->id,
            'semester_id' => $semester->id,
            'dimension_id' => $dim->id,
            'tingkat_peringatan' => 'tinggi',
            'alasan_peringatan' => 'Rahasia B',
            'rekomendasi_tindakan' => 'Tindakan B',
            'status' => 'pending',
        ]);

        $assessmentB = StudentAssessment::create([
            'siswa_id' => $siswaB->id,
            'semester_id' => $semester->id,
            'guru_id' => $guruB->id,
            'tanggal_penilaian' => '2026-11-01',
            'total_score' => 10,
            'max_score' => 16,
            'persentase' => 62.5,
            'status_kesiapan' => 'perlu_bimbingan',
            'skor_per_dimensi' => [],
        ]);

        $reportB = ParentReport::create([
            'assessment_id' => $assessmentB->id,
            'siswa_id' => $siswaB->id,
            'rekomendasi_rumah' => 'Rahasia B',
            'aktivitas_suggested' => [],
        ]);

        $this->actingAs($adminA)->get(route('admin.tka.warnings.index'))
            ->assertOk()
            ->assertDontSee('Siswa Peringatan B');
        $this->actingAs($adminA)->get(route('admin.tka.warnings.show', $warningB))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.tka.warnings.update-status', $warningB), ['status' => 'selesai'])->assertNotFound();

        // Sebelum perbaikan: $siswa bernilai null karena TenantScope -> HTTP 500.
        $this->actingAs($ortuA)->get(route('tka.parent.show', $reportB))->assertNotFound();
    }

    public function test_p0_4_admin_tenant_kosong_tidak_dapat_menghapus_semester_yang_dipakai_sekolah_lain(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->buatSekolah('10000301');
        $b = $this->buatSekolah('10000302');

        $guruA = User::factory()->guru()->create(['tenant_id' => $a->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $b->id]);

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        TenantContext::run($a->id, fn () => Siswa::create([
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'semester_id' => $semester->id,
            'nis' => 'NIS-SEM-A',
            'nama_peserta_didik' => 'Siswa Semester A',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        $this->actingAs($adminB)
            ->delete(route('admin.semester.destroy', $semester))
            ->assertRedirect(route('admin.semester.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('semesters', ['id' => $semester->id]);
    }

    public function test_p0_6_presensi_bridge_ps1_menormalisasi_bagian_jam_dan_nama_log_benar(): void
    {
        $script = (string) file_get_contents(base_path('presensi-bridge/presensi-bridge.ps1'));

        $this->assertStringNotContainsString('presinsi-bridge.log', $script);
        $this->assertStringContainsString('presensi-bridge.log', $script);
        $this->assertStringContainsString('$bagian[1]', $script);
    }

    public function test_p1_7_tanggal_rapor_cocok_meski_beda_huruf_besar_kecil_semester(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'NIS-TGL-01',
            'nama_peserta_didik' => 'Siswa Tanggal Rapor',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        TanggalRapor::create([
            'tahun_ajaran' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal' => '2026-12-20',
            'tempat' => 'Makassar Uji Tanggal',
        ]);

        $res = $this->actingAs($admin)->get(route('admin.cetak.nilai.show', [
            'siswa' => $siswa->id,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
        ]));

        $res->assertOk();
        $this->assertNotNull($res->viewData('tanggalRapor'));
        $this->assertSame('Makassar Uji Tanggal', $res->viewData('tanggalRapor')->tempat);
    }

    public function test_p1_8_siswa_dengan_prefix_siswa_nis_dapat_mengakses_tryout(): void
    {
        config(['simantap.cbt_enabled' => true]);

        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => '88001',
            'nisn' => '0088001122',
            'nama_peserta_didik' => 'Siswa Prefix Tryout',
            'kelas' => '6A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        $akunSiswa = User::factory()->siswa()->create([
            'nama_pengguna' => 'siswa88001',
            'terhubung_dengan' => [],
        ]);

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);
        $cat = Category::create(['nama_kategori' => 'Uji', 'jenis' => 'akademik', 'aktif' => true]);
        $pkg = Package::create([
            'category_id' => $cat->id,
            'kode_paket' => 'PKT-88',
            'nama_paket' => 'Paket Uji Prefix',
            'jenjang' => 'SD',
            'tahun_paket' => 2026,
            'jumlah_mapel' => 1,
            'durasi_total_menit' => 60,
            'passing_grade' => 60,
            'aktif' => true,
        ]);
        TryoutSession::create([
            'kode_sesi' => 'SESI-88',
            'nama_sesi' => 'Sesi Tryout Prefix',
            'package_id' => $pkg->id,
            'semester_id' => $semester->id,
            'guru_id' => $guru->id,
            'kelas' => '6A',
            'tanggal_mulai' => now()->subMinutes(10),
            'tanggal_selesai' => now()->addHours(1),
            'durasi_menit' => 60,
            'status' => 'ongoing',
        ]);

        $this->actingAs($akunSiswa)
            ->get(route('tryout.student.index'))
            ->assertOk()
            ->assertSee('Sesi Tryout Prefix');
    }

    public function test_p1_9_nilai_erapor_menyimpan_angka_nol_dan_autosave_lintas_guru_tidak_500(): void
    {
        $guru1 = User::factory()->guru()->create();
        $guru2 = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'guru_id' => $guru1->id,
            'nama_guru' => $guru1->nama_lengkap,
            'nis' => 'ER-001',
            'nama_peserta_didik' => 'Siswa Erapor Nol',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        // Nilai 0 wajib tersimpan (sebelumnya terlewat karena empty(0) === true).
        $this->actingAs($guru1)->postJson(route('guru.nilai-erapor.simpan'), [
            'kelas' => '6A',
            'mapel' => 'Matematika',
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
            'nilai' => [
                [
                    'siswa_id' => $siswa->id,
                    'formatif' => 0,
                    'sumatif' => 0,
                    'sumatif_akhir' => 0,
                ],
            ],
        ])->assertOk()->assertJsonPath('count', 1);

        $this->assertDatabaseHas('nilai_erapor', [
            'siswa_id' => $siswa->id,
            'mata_pelajaran' => 'Matematika',
        ]);

        // Guru pengganti melakukan autoSave pada baris yang sama -> update, bukan 500 UniqueConstraintViolation.
        $this->actingAs($guru2)->postJson(route('guru.nilai-erapor.auto-save'), [
            'siswa_id' => $siswa->id,
            'field' => 'formatif',
            'value' => 85,
            'kelas' => '6A',
            'mapel' => 'Matematika',
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(1, NilaiErapot::where('siswa_id', $siswa->id)->count());
    }

    public function test_p1_10_wali_kelas_tidak_dapat_mengubah_transkrip_siswa_kelas_guru_lain(): void
    {
        $guruA = User::factory()->guru()->create();
        $guruB = User::factory()->guru()->create();

        $siswaA = Siswa::create([
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'nis' => 'WK-A01',
            'nama_peserta_didik' => 'Siswa Wali A',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        Siswa::create([
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'nis' => 'WK-B01',
            'nama_peserta_didik' => 'Siswa Wali B',
            'kelas' => '6B',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);

        $this->actingAs($guruB)
            ->post(route('guru.walas.transkrip.simpan', $siswaA), [
                'nomor_ijazah' => 'IJZ-ILEGAL',
            ])
            ->assertForbidden();

        $this->assertNull($siswaA->fresh()->nomor_ijazah);
    }

    public function test_p1_11_login_tanpa_subdomain_mencocokkan_akun_tenant_yang_tepat_saat_username_kembar(): void
    {
        $a = $this->buatSekolah('10000401');
        $b = $this->buatSekolah('10000402');

        User::factory()->guru()->create([
            'tenant_id' => $a->id,
            'nama_pengguna' => 'guru_kembar',
            'kata_sandi' => Hash::make('SandiSekolahA!123'),
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guruB = User::factory()->guru()->create([
            'tenant_id' => $b->id,
            'nama_pengguna' => 'guru_kembar',
            'kata_sandi' => Hash::make('SandiSekolahB!123'),
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $this->post('/login', [
            'nama_pengguna' => 'guru_kembar',
            'kata_sandi' => 'SandiSekolahB!123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($guruB);
    }

    public function test_p1_12_superadmin_membuat_tenant_sekaligus_baris_sekolah_settings(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post(route('superadmin.tenants.store'), [
            'nama_sekolah' => 'SDN Provisioning Baru',
            'npsn' => '40377889',
            'subdomain' => 'sdn-prov-baru',
            'jenjang' => 'SD',
            'admin_nama' => 'Admin Prov Baru',
            'admin_user' => 'admin_prov_baru',
            'admin_password' => 'SandiKuat#2026',
        ])->assertRedirect(route('superadmin.tenants.index'));

        $tenant = Tenant::where('subdomain', 'sdn-prov-baru')->firstOrFail();
        $this->assertTrue(
            SekolahSettings::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()
        );
    }

    public function test_p2_13_14_show_toast_memakai_text_content_dan_sync_log_menyimpan_user_agent(): void
    {
        $appLayout = (string) file_get_contents(base_path('resources/views/layouts/app.blade.php'));
        $adminLayout = (string) file_get_contents(base_path('resources/views/layouts/admin-with-tabs.blade.php'));

        $this->assertStringContainsString('toast.textContent =', $appLayout);
        $this->assertStringNotContainsString('toast.innerHTML =', $appLayout);
        $this->assertStringContainsString('toast.textContent =', $adminLayout);
        $this->assertStringNotContainsString('toast.innerHTML =', $adminLayout);

        $log = PresensiMesinSyncLog::create([
            'source' => 'bridge',
            'status' => 'sukses',
            'user_agent' => 'SIMANTAP-Bridge/1.0',
        ]);
        $this->assertSame('SIMANTAP-Bridge/1.0', $log->fresh()->user_agent);
    }

    public function test_browser_walkthrough_kepsek_dan_pengawas_perbaikan_5_temuan(): void
    {
        $tenant = $this->buatSekolah('40307563');
        $tenant->update(['nama_sekolah' => 'UPT SPF SD NEGERI UNGGULAN MONGISIDI 1']);

        $kepsek = User::factory()->kepsek()->create(['tenant_id' => $tenant->id]);
        $pengawas = User::factory()->pengawas()->create(['tenant_id' => $tenant->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $tenant->id, 'nama_lengkap' => 'Guru Pantau Alpha']);
        $guruB = User::factory()->guru()->create(['tenant_id' => $tenant->id, 'nama_lengkap' => 'Guru Pantau Beta']);

        TenantContext::run($tenant->id, fn () => Aktivitas::create([
            'tenant_id' => $tenant->id,
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'jenis' => 'nilai',
            'judul' => 'Simpan Nilai Matematika 6A',
            'deskripsi' => '28 siswa diperbarui',
        ]));

        // 1. /kepsek/pantau: filter guru_id memfilter tabel statistikGuru & merender judul/nama_guru
        $pantauRes = $this->actingAs($kepsek)->get(route('kepsek.pantau.index', ['guru_id' => $guruA->id]));
        $pantauRes->assertOk()
            ->assertSee('Guru Pantau Alpha')
            ->assertSee('Simpan Nilai Matematika 6A')
            ->assertSee('28 siswa diperbarui');
        $this->assertCount(1, $pantauRes->viewData('statistikGuru'));

        // 2. /kepsek/aktivitas: chartDistribusiData wajib array numerik terindeks (0..4) agar Chart.js v4 tidak error axis
        $aktRes = $this->actingAs($kepsek)->get(route('kepsek.aktivitas.index'));
        $aktRes->assertOk();
        $distData = $aktRes->viewData('chartDistribusiData');
        $this->assertIsArray($distData);
        $this->assertSame([0, 1, 2, 3, 4], array_keys($distData));

        // 3. /kepsek/kebiasaan: kebiasaanTerbaik tampil sebagai label manusiawi ("Bangun Pagi"), bukan "bangun_pagi"
        $kebRes = $this->actingAs($kepsek)->get(route('kepsek.kebiasaan.index'));
        $kebRes->assertOk()
            ->assertDontSee('>bangun_pagi<', false);
        $this->assertSame('Bangun Pagi', $kebRes->viewData('kebiasaanTerbaik'));

        // 4. /pengawas/tanda-tangan: menyimpan TTD tidak menimpa nama_sekolah menjadi 'SDN SIMANTAP'
        $this->actingAs($pengawas)->post(route('pengawas.ttd.store'), [
            'nama_pengawas' => 'Drs. H. Pengawas Uji, M.Pd.',
            'nip_pengawas' => '197001011995031001',
        ])->assertRedirect();

        $sekolah = SekolahSettings::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('UPT SPF SD NEGERI UNGGULAN MONGISIDI 1', $sekolah->nama_sekolah);
    }

    public function test_audit_sedang_rendah_login_refresh_password_siswa_dan_ping_hardening(): void
    {
        // 1. Login akun nonaktif memakai pesan generik yang sama (tidak membocorkan validitas sandi)
        $nonaktif = User::factory()->guru()->create([
            'nama_pengguna' => 'guru_nonaktif_uji',
            'kata_sandi' => Hash::make('SandiBenar123'),
            'aktif' => false,
        ]);

        $resNonaktif = $this->post('/login', [
            'nama_pengguna' => $nonaktif->nama_pengguna,
            'kata_sandi' => 'SandiBenar123',
        ]);
        $resNonaktif->assertSessionHasErrors([
            'nama_pengguna' => 'Nama pengguna atau kata sandi salah.',
        ]);
        $this->assertGuest();

        // 2. POST /tka-statistik/refresh ditolak 403 untuk siswa & ortu
        $siswaUser = User::factory()->siswa()->create();
        $ortuUser = User::factory()->ortu()->create();
        $this->actingAs($siswaUser)->post(route('tka.refresh'))->assertForbidden();
        $this->actingAs($ortuUser)->post(route('tka.refresh'))->assertForbidden();

        // 3. DataReferensiController::storeGuru menolak kata sandi < 8 karakter (konsisten Password::defaults())
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.referensi.guru.store'), [
            'nama' => 'Guru Sandi Pendek',
            'jenis_ptk' => 'Guru Kelas',
            'buat_akun' => true,
            'kata_sandi' => '123456',
        ])->assertSessionHasErrors('kata_sandi');

        // 4. Guru\SiswaController::store tidak menulis kode ORT acak ke kolom nama_orang_tua
        $guru = User::factory()->guru()->create();
        $this->actingAs($guru)->post(route('guru.siswa.store'), [
            'nama' => 'Siswa Tanpa Ortu',
            'nis' => 'NIS-NO-ORTU-01',
            'jenis_kelamin' => 'L',
            'kelas' => '6A',
            'nama_orang_tua' => '',
        ])->assertRedirect(route('guru.siswa.index'));

        $this->assertDatabaseHas('siswa', [
            'nis' => 'NIS-NO-ORTU-01',
            'nama_orang_tua' => null,
        ]);

        // 5. /api/dapodik/ping tidak mengekspos nomor versi
        $this->postJson('/api/dapodik/ping')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonMissingPath('version');
    }
}
