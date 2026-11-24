<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\JadwalPelajaran;
use App\Models\Kebiasaan;
use App\Models\KegiatanKokurikuler;
use App\Models\Materi;
use App\Models\NilaiErapot;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TemaKokurikuler;
use App\Models\Tka\AssessmentItem;
use App\Models\Tka\Dimension;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditBatch2Nov2026Test extends TestCase
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

    public function test_p0_1_hapus_akun_guru_tidak_menghapus_siswa_dan_nilai_erapor_kelas(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nama_lengkap' => 'Guru Mutasi']);

        $siswa = Siswa::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'NIS-KEEP-01',
            'nama_peserta_didik' => 'Siswa Tetap Utuh',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $nilai = NilaiErapot::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
            'nilai_akhir' => 88,
            'predikat' => 'B',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $guru))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $guru->id]);
        $this->assertDatabaseHas('siswa', [
            'id' => $siswa->id,
            'deleted_at' => null,
            'guru_id' => null,
            'nama_guru' => null,
        ]);
        $this->assertDatabaseHas('nilai_erapor', [
            'id' => $nilai->id,
            'siswa_id' => $siswa->id,
            'nilai_akhir' => 88,
        ]);
    }

    public function test_p0_2_dan_p2_8_sync_jadwal_dapodik_tidak_undefined_rombel_dan_tahan_item_korup(): void
    {
        $admin = User::factory()->admin()->create();
        $key = ApiKey::generate($admin->id, 'Bridge Jadwal Test')['plaintext_key'];

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $rombel = Rombel::create([
            'dapodik_id' => 'romb-dapodik-6a',
            'semester_id' => $semester->id,
            'nama_rombel' => 'Kelas 6A',
            'tingkat' => 6,
            'aktif' => true,
        ]);

        $res = $this->withHeaders(['X-API-Key' => $key])
            ->postJson('/api/dapodik/sync/jadwal', [
                'semester_id' => '20261',
                'tahun_ajaran' => '2026/2027',
                'nama_semester' => 'ganjil',
                'data' => [
                    'corrupted_non_array_payload',
                    [
                        'pembelajaran_id' => 'pemb-001',
                        'rombongan_belajar_id' => 'romb-dapodik-6a',
                        'nama_rombel' => 'Kelas 6A',
                        'nama_mata_pelajaran' => 'Matematika',
                        'hari' => 'Senin',
                        'jam_mulai' => '07:30:00',
                        'jam_selesai' => '08:30:00',
                    ],
                ],
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('berhasil', 1)
            ->assertJsonPath('gagal', 1);

        $jadwal = JadwalPelajaran::where('dapodik_id', 'pemb-001')->firstOrFail();
        $this->assertSame($rombel->id, (int) $jadwal->rombel_id);
        $this->assertSame('Kelas 6A', $jadwal->kelas);
    }

    public function test_p0_3_dan_p2_10_superadmin_email_default_unik_dan_hapus_user_tenant_aman_dari_fk(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $otherSuperAdmin = User::factory()->superAdmin()->create();

        // P2 #10: Dua sekolah memakai admin_user sama tanpa admin_email -> tidak bentrok email unik
        $this->actingAs($superAdmin)->post(route('superadmin.tenants.store'), [
            'nama_sekolah' => 'SDN Satu',
            'npsn' => '50100001',
            'subdomain' => 'sdn-satu-uji',
            'jenjang' => 'SD',
            'admin_nama' => 'Operator Satu',
            'admin_user' => 'operator',
            'admin_email' => '',
            'admin_password' => 'SandiKuat#2026',
        ])->assertRedirect(route('superadmin.tenants.index'));

        $this->actingAs($superAdmin)->post(route('superadmin.tenants.store'), [
            'nama_sekolah' => 'SDN Dua',
            'npsn' => '50100002',
            'subdomain' => 'sdn-dua-uji',
            'jenjang' => 'SD',
            'admin_nama' => 'Operator Dua',
            'admin_user' => 'operator',
            'admin_email' => '',
            'admin_password' => 'SandiKuat#2026',
        ])->assertRedirect(route('superadmin.tenants.index'));

        $tenant1 = Tenant::where('subdomain', 'sdn-satu-uji')->firstOrFail();
        $tenant2 = Tenant::where('subdomain', 'sdn-dua-uji')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant1->id,
            'email' => 'operator@sdn-satu-uji.simantap.sch.id',
        ]);
        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant2->id,
            'email' => 'operator@sdn-dua-uji.simantap.sch.id',
        ]);

        // P0 #3: Hapus guru yang memiliki CbtQuestionBank & CbtTestSession (FK RESTRICT)
        $guru = User::factory()->guru()->create(['tenant_id' => $tenant1->id]);
        $bank = TenantContext::run($tenant1->id, fn () => CbtQuestionBank::create([
            'guru_id' => $guru->id,
            'title' => 'Bank Uji FK',
            'subject' => 'IPA',
            'grade_level' => '6',
        ]));
        TenantContext::run($tenant1->id, fn () => CbtTestSession::create([
            'guru_id' => $guru->id,
            'bank_id' => $bank->id,
            'title' => 'Sesi Uji FK',
            'kelas' => '6A',
            'duration_minutes' => 30,
            'status' => 'draft',
            'opens_at' => now(),
            'closes_at' => now()->addHour(),
        ]));

        // bulkDestroyUsers tidak menyentuh super_admin lain dan berhasil menghapus guru beserta CBT-nya
        $this->actingAs($superAdmin)->post(route('superadmin.users.bulk-destroy'), [
            'user_ids' => [$otherSuperAdmin->id, $guru->id],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $otherSuperAdmin->id]);
        $this->assertDatabaseMissing('users', ['id' => $guru->id]);

        // Hapus tenant penuh (berisi semester, siswa, rombel) tanpa gagal urutan FK
        $sem1 = TenantContext::run($tenant1->id, fn () => Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]));
        TenantContext::run($tenant1->id, fn () => Siswa::create([
            'semester_id' => $sem1->id,
            'nis' => 'TEN-DEL-01',
            'nama_peserta_didik' => 'Siswa Tenant Hapus',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        $this->actingAs($superAdmin)
            ->delete(route('superadmin.tenants.destroy', $tenant1), ['force' => 1])
            ->assertRedirect(route('superadmin.tenants.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tenants', ['id' => $tenant1->id]);
    }

    public function test_p1_4_tryout_kode_paket_dan_sesi_unik_per_tenant_serta_tolak_fk_lintas_sekolah(): void
    {
        config(['simantap.multi_tenant_enabled' => true, 'simantap.cbt_enabled' => true]);
        $a = $this->buatSekolah('50200001');
        $b = $this->buatSekolah('50200002');

        $adminA = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $b->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id]);

        $cat = Category::create(['nama_kategori' => 'Umum', 'jenis' => 'akademik', 'aktif' => true]);
        $semA = TenantContext::run($a->id, fn () => Semester::create([
            'semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]));
        $semB = TenantContext::run($b->id, fn () => Semester::create([
            'semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]));

        $pkgA = TenantContext::run($a->id, fn () => Package::create([
            'category_id' => $cat->id,
            'kode_paket' => 'TO-2026-01',
            'nama_paket' => 'Paket Sekolah A',
            'jenjang' => 'SD',
            'tahun_paket' => 2026,
            'durasi_total_menit' => 60,
            'passing_grade' => 70,
            'aktif' => true,
        ]));

        TenantContext::run($a->id, fn () => TryoutSession::create([
            'kode_sesi' => 'SESI-TO-01',
            'nama_sesi' => 'Sesi A',
            'package_id' => $pkgA->id,
            'semester_id' => $semA->id,
            'guru_id' => $adminA->id,
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addHours(2),
            'durasi_menit' => 60,
            'status' => 'draft',
        ]));

        // Sekolah B boleh memakai kode_paket yang sama dengan Sekolah A
        $this->actingAs($adminB)->post(route('admin.tryout.packages.store'), [
            'kode_paket' => 'TO-2026-01',
            'nama_paket' => 'Paket Sekolah B',
            'jenjang' => 'SD',
            'tipe_ujian' => 'AKM',
            'tahun_paket' => 2026,
            'total_soal' => 20,
            'waktu_menit' => 60,
            'kkm' => 70,
        ])->assertRedirect(route('admin.tryout.packages.index'))
            ->assertSessionHasNoErrors();

        $pkgB = Package::withoutGlobalScopes()->where('tenant_id', $b->id)->where('kode_paket', 'TO-2026-01')->firstOrFail();

        // Sekolah B ditolak jika menyuntikkan package_id / semester_id milik Sekolah A
        $this->actingAs($adminB)->post(route('admin.tryout.sessions.store'), [
            'kode_sesi' => 'SESI-TO-01',
            'nama_sesi' => 'Sesi Injeksi',
            'package_id' => $pkgA->id,
            'semester_id' => $semA->id,
            'tanggal_mulai' => now()->format('Y-m-d H:i:s'),
            'tanggal_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'durasi_menit' => 60,
        ])->assertSessionHasErrors(['package_id', 'semester_id']);

        // Guru B juga ditolak di TryoutSessionController::store
        $this->actingAs($guruB)->post(route('tryout.sessions.store'), [
            'package_id' => $pkgA->id,
            'semester_id' => $semA->id,
            'nama_sesi' => 'Sesi Guru Injeksi',
            'tanggal_mulai' => now()->format('Y-m-d H:i:s'),
            'tanggal_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors(['package_id', 'semester_id']);

        // Sekolah B sukses memakai kode_sesi yang sama dengan package & semester miliknya sendiri
        $this->actingAs($adminB)->post(route('admin.tryout.sessions.store'), [
            'kode_sesi' => 'SESI-TO-01',
            'nama_sesi' => 'Sesi Sekolah B',
            'package_id' => $pkgB->id,
            'semester_id' => $semB->id,
            'tanggal_mulai' => now()->format('Y-m-d H:i:s'),
            'tanggal_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'durasi_menit' => 60,
        ])->assertRedirect(route('admin.tryout.sessions.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_p1_5_6_7_blokir_injeksi_fk_lintas_sekolah_dan_guru_pada_tka_cbt_kuis_kokurikuler(): void
    {
        config(['simantap.multi_tenant_enabled' => true, 'simantap.cbt_enabled' => true]);
        $a = $this->buatSekolah('50300001');
        $b = $this->buatSekolah('50300002');

        $adminB = User::factory()->admin()->create(['tenant_id' => $b->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $a->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id]);
        $guruB2 = User::factory()->guru()->create(['tenant_id' => $b->id]);

        // P1 #5: TKA Assessment menolak semester_id & assessment_item_id milik Sekolah A
        $semA = TenantContext::run($a->id, fn () => Semester::create([
            'semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]));
        $dimA = TenantContext::run($a->id, fn () => Dimension::create([
            'semester_id' => $semA->id,
            'kode_dimensi' => 'DIM-A',
            'nama_dimensi' => 'Dimensi A',
            'kategori' => 'mandiri',
            'rubrik_penilaian' => [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'],
        ]));
        $itemA = TenantContext::run($a->id, fn () => AssessmentItem::create([
            'dimension_id' => $dimA->id,
            'urutan' => 1,
            'indikator' => 'Indikator Sekolah A',
            'pertanyaan_penilaian' => 'Item Sekolah A',
            'bobot_item' => 1.0,
        ]));
        $siswaB = TenantContext::run($b->id, fn () => Siswa::create([
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'nis' => 'TKA-B-01',
            'nama_peserta_didik' => 'Siswa TKA B',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        $this->actingAs($guruB)->post(route('tka.assessments.store'), [
            'siswa_id' => $siswaB->id,
            'semester_id' => $semA->id,
            'tanggal_penilaian' => now()->format('Y-m-d'),
            'responses' => [
                ['assessment_item_id' => $itemA->id, 'skor' => 3],
            ],
        ])->assertSessionHasErrors(['semester_id', 'responses.0.assessment_item_id']);

        // P1 #6: Admin CBT Session menolak guru_id & bank_id milik Sekolah A
        $bankA = TenantContext::run($a->id, fn () => CbtQuestionBank::create([
            'guru_id' => $guruA->id,
            'title' => 'Bank A',
            'subject' => 'MTK',
            'grade_level' => '6',
        ]));
        $this->actingAs($adminB)->post(route('admin.cbt.sessions.store'), [
            'guru_id' => $guruA->id,
            'bank_id' => $bankA->id,
            'title' => 'Sesi CBT Injeksi',
            'kelas' => '6A',
            'opens_at' => now()->format('Y-m-d H:i:s'),
            'closes_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
        ])->assertSessionHasErrors(['guru_id', 'bank_id']);

        // P1 #7a: Guru Kuis menolak materi_id milik guru lain
        $materiB2 = TenantContext::run($b->id, fn () => Materi::create([
            'guru_id' => $guruB2->id,
            'nama_guru' => $guruB2->nama_lengkap,
            'judul' => 'Materi Guru B2',
            'mata_pelajaran' => 'IPA',
            'kelas' => '6A',
            'tanggal' => now()->format('Y-m-d'),
            'tujuan_pembelajaran' => 'Tujuan uji',
            'materi_pokok' => 'Pokok uji',
            'kegiatan' => 'Kegiatan uji',
        ]));
        $this->actingAs($guruB)->post(route('guru.kuis.store'), [
            'judul' => 'Kuis Injeksi Materi',
            'mata_pelajaran' => 'IPA',
            'kelas' => '6A',
            'tanggal' => now()->format('Y-m-d'),
            'mode' => 'harian',
            'sumber' => 'lokal',
            'materi_id' => $materiB2->id,
            'kkm' => 70,
            'jenis' => 'Tugas',
            'cara_nilai' => 'guru',
        ])->assertSessionHasErrors(['materi_id']);

        // P1 #7b: Admin Kokurikuler menolak tema_id & kegiatan_id milik Sekolah A
        $temaA = TenantContext::run($a->id, fn () => TemaKokurikuler::create(['nama' => 'Tema A', 'aktif' => true]));
        $kegA = TenantContext::run($a->id, fn () => KegiatanKokurikuler::create([
            'tema_id' => $temaA->id, 'nama' => 'Kegiatan A', 'aktif' => true,
        ]));

        $this->actingAs($adminB)->post(route('admin.kokurikuler.kegiatan.store'), [
            'tema_id' => $temaA->id,
            'nama' => 'Kegiatan Injeksi',
        ])->assertSessionHasErrors(['tema_id']);

        $this->actingAs($adminB)->post(route('admin.kokurikuler.kelompok.store'), [
            'kegiatan_id' => $kegA->id,
            'nama' => 'Kelompok Injeksi',
        ])->assertSessionHasErrors(['kegiatan_id']);
    }

    public function test_p2_9_ortu_kebiasaan_menyimpan_skor_nol_tanpa_menghapus_catatan(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'KEB-001',
            'nama_peserta_didik' => 'Siswa Kebiasaan Nol',
            'kelas' => '6A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        $ortu = User::factory()->ortu()->create([
            'nama_lengkap' => 'Orang Tua Uji',
            'terhubung_dengan' => [$siswa->id],
        ]);

        $tanggal = now()->format('Y-m-d');

        // Simpan awal seluruh nilai = 0 (harus tersimpan, bukan dihapus atau crash undefined $user)
        $this->actingAs($ortu)->post(route('ortu.kebiasaan.store'), [
            'siswa_id' => $siswa->id,
            'tanggal' => $tanggal,
            'bangun_pagi' => 0,
            'beribadah' => 0,
            'berolahraga' => 0,
            'makan_sehat' => 0,
            'gemar_belajar' => 0,
            'bermasyarakat' => 0,
            'tidur_cepat' => 0,
            'catatan' => '',
        ])->assertRedirect()->assertSessionHas('success', 'Laporan 7 Kebiasaan berhasil disimpan.');

        $record = Kebiasaan::where('siswa_id', $siswa->id)->whereDate('tanggal', $tanggal)->first();
        $this->assertNotNull($record);
        $this->assertSame(0, (int) $record->bangun_pagi);
        $this->assertSame('Orang Tua Uji', $record->diisi_oleh);

        // Update kembali dengan skor 0 -> catatan TIDAK boleh terhapus
        $this->actingAs($ortu)->post(route('ortu.kebiasaan.store'), [
            'siswa_id' => $siswa->id,
            'tanggal' => $tanggal,
            'bangun_pagi' => 0,
            'beribadah' => 0,
            'catatan' => '',
        ])->assertRedirect()->assertSessionHas('success', 'Laporan 7 Kebiasaan berhasil disimpan.');

        $this->assertDatabaseHas('kebiasaan', [
            'id' => $record->id,
            'bangun_pagi' => 0,
        ]);
    }
}
