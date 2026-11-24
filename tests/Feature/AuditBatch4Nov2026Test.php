<?php

namespace Tests\Feature;

use App\Http\Controllers\Siswa\HasSiswaLookup;
use App\Models\HasilKuis;
use App\Models\Kuis;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\PengaturanGuru;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditBatch4Nov2026Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function createTenant(string $npsn, string $nama, string $subdomain): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => $npsn,
            'nama_sekolah' => $nama,
            'subdomain' => $subdomain,
            'aktif' => true,
        ]);
    }

    private function createSemester(Tenant $tenant, string $code = '20261'): Semester
    {
        return TenantContext::run($tenant->id, fn () => Semester::create([
            'tenant_id' => $tenant->id,
            'semester_id' => $code,
            'nama_semester' => 'ganjil',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'aktif' => true,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-31',
        ]));
    }

    public function test_p0_1_login_portal_pusat_tidak_terkunci_oleh_akun_nonaktif_dengan_username_sama_di_sekolah_lain(): void
    {
        $t1 = $this->createTenant('40100001', 'SDN 1 Nonaktif', 'sdn1-nonaktif');
        $t2 = $this->createTenant('40100002', 'SDN 2 Aktif', 'sdn2-aktif');

        // Akun lama di Sekolah 1 sudah nonaktif (ID lebih kecil)
        User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Lama Nonaktif',
            'nama_pengguna' => 'guru_sama',
            'email' => 'gurulama@sdn1.sch.id',
            'kata_sandi' => Hash::make('SandiLamaSekolah1!'),
            'peran' => 'guru',
            'tenant_id' => $t1->id,
            'aktif' => false,
        ]);

        // Akun aktif di Sekolah 2 dengan nama_pengguna yang sama
        $guruAktif = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Baru Aktif',
            'nama_pengguna' => 'guru_sama',
            'email' => 'gurubaru@sdn2.sch.id',
            'kata_sandi' => Hash::make('SandiBaruSekolah2!'),
            'peran' => 'guru',
            'tenant_id' => $t2->id,
            'aktif' => true,
        ]);

        $response = $this->post('/login', [
            'nama_pengguna' => 'guru_sama',
            'kata_sandi' => 'SandiBaruSekolah2!',
        ]);

        $response->assertRedirect(route('guru.dashboard'));
        $this->assertAuthenticatedAs($guruAktif);
    }

    public function test_p0_2_registrasi_sekolah_menolak_subdomain_sistem_dan_menormalisasi_email(): void
    {
        config(['simantap.school_registration_enabled' => true]);

        foreach (['www', 'app', 'api', '-awalan'] as $invalidSubdomain) {
            \Illuminate\Support\Facades\RateLimiter::clear('register-sekolah:127.0.0.1');
            $res = $this->from('/daftar-sekolah')->post('/daftar-sekolah', [
                'nama_sekolah' => 'SDN Uji Subdomain',
                'npsn' => '88776655',
                'jenjang' => 'SD',
                'kepala_sekolah' => 'Kepsek Uji',
                'subdomain' => $invalidSubdomain,
                'nama_lengkap' => 'Operator Uji',
                'nama_pengguna' => 'op_uji_sub',
                'email' => 'op@uji.sch.id',
                'kata_sandi' => 'SandiKuat123!@#',
                'kata_sandi_confirmation' => 'SandiKuat123!@#',
            ]);

            $res->assertRedirect('/daftar-sekolah');
            $res->assertSessionHasErrors('subdomain');
        }

        $validRes = $this->post('/daftar-sekolah', [
            'nama_sekolah' => 'SDN Merdeka 01',
            'npsn' => '88776655',
            'jenjang' => 'SD',
            'kepala_sekolah' => 'Kepsek Merdeka',
            'subdomain' => '  SDN-Merdeka-01 ',
            'nama_lengkap' => 'Operator Merdeka',
            'nama_pengguna' => 'op_merdeka01',
            'email' => '  Operator.Merdeka@Sekolah.SCH.ID ',
            'kata_sandi' => 'SandiKuat123!@#',
            'kata_sandi_confirmation' => 'SandiKuat123!@#',
        ]);

        $validRes->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('tenants', [
            'npsn' => '88776655',
            'subdomain' => 'sdn-merdeka-01',
        ]);
        $this->assertDatabaseHas('users', [
            'nama_pengguna' => 'op_merdeka01',
            'email' => 'operator.merdeka@sekolah.sch.id',
        ]);
    }

    public function test_p0_3_dan_p2_11_guru_simpan_pungut_tidak_menduplikasi_siswa_dan_impor_csv_tanpa_ort_acak(): void
    {
        $t1 = $this->createTenant('40100003', 'SDN 3 Pungut', 'sdn3-pungut');
        $guruAsal = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Kelas IV',
            'nama_pengguna' => 'guru_kls4',
            'email' => 'guru4@sdn3.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'guru',
            'tenant_id' => $t1->id,
            'kelas_mata_pelajaran' => 'IV A',
            'aktif' => true,
        ]);
        $guruTujuan = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Kelas V',
            'nama_pengguna' => 'guru_kls5',
            'email' => 'guru5@sdn3.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'guru',
            'tenant_id' => $t1->id,
            'kelas_mata_pelajaran' => 'V A',
            'aktif' => true,
        ]);

        $siswaDapodik = TenantContext::run($t1->id, fn () => Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t1->id,
            'guru_id' => $guruAsal->id,
            'nama_guru' => $guruAsal->nama_lengkap,
            'nis' => '3001',
            'nisn' => '003001001',
            'dapodik_id' => 'dapo-siswa-3001',
            'nama_peserta_didik' => 'Siswa Asli Dapodik',
            'kelas' => 'IV A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        $this->actingAs($guruTujuan)
            ->post(route('guru.siswa.pungut.simpan'), [
                'siswa_ids' => [$siswaDapodik->id],
            ])
            ->assertRedirect(route('guru.siswa.index'));

        // Pastikan TIDAK ada kloning baris Siswa ke-2 dengan dapodik_id/nisn yang sama
        $this->assertSame(1, Siswa::withoutGlobalScopes()->where('dapodik_id', 'dapo-siswa-3001')->count());
        $siswaDapodik->refresh();
        $this->assertSame($guruTujuan->id, (int) $siswaDapodik->guru_id);
        $this->assertSame('3001', $siswaDapodik->nis);
        $this->assertSame('V A', $siswaDapodik->kelas);

        // Uji impor CSV guru tidak lagi membuat kode 'ORTxxxx' acak
        $this->actingAs($guruTujuan)
            ->post(route('guru.siswa.impor'), [
                'csv' => "Nama,NIS,JK,Kelas\nSiswa Impor Bersih,3002,P,V A",
            ])
            ->assertRedirect(route('guru.siswa.index'));

        $siswaImpor = Siswa::withoutGlobalScopes()->where('nis', '3002')->firstOrFail();
        $this->assertNull($siswaImpor->nama_orang_tua);
    }

    public function test_p0_4_siswa_dapodik_tanpa_guru_id_dapat_melihat_dan_mengumpulkan_kuis_kelasnya(): void
    {
        $t1 = $this->createTenant('40100004', 'SDN 4 Kuis', 'sdn4-kuis');
        $guru = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Kuis Kelas VI',
            'nama_pengguna' => 'guru_kuis6',
            'email' => 'gurukuis@sdn4.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'guru',
            'tenant_id' => $t1->id,
            'kelas_mata_pelajaran' => 'VI A',
            'aktif' => true,
        ]);

        // Siswa sinkronisasi Dapodik memiliki guru_id = null
        $siswa = TenantContext::run($t1->id, fn () => Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t1->id,
            'guru_id' => null,
            'dapodik_id' => 'dapo-siswa-4001',
            'nis' => '4001',
            'nisn' => '004001001',
            'nama_peserta_didik' => 'Siswa Dapodik Kuis',
            'kelas' => 'VI A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        $akunSiswa = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Siswa Dapodik Kuis',
            'nama_pengguna' => '4001',
            'email' => 'siswa4001@sdn4.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'siswa',
            'tenant_id' => $t1->id,
            'terhubung_dengan' => [$siswa->id],
            'aktif' => true,
        ]);

        $kuis = TenantContext::run($t1->id, fn () => Kuis::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t1->id,
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'judul' => 'Kuis Matematika Pecahan',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'VI A',
            'tanggal' => now()->toDateString(),
            'kkm' => 75,
            'aktif' => true,
            'sumber' => 'manual',
            'soal' => [
                ['pertanyaan' => '1/2 + 1/2 = ?', 'opsi' => ['A' => '1', 'B' => '2'], 'kunci' => 'A'],
            ],
        ]));

        $this->actingAs($akunSiswa)
            ->get(route('siswa.kuis.index'))
            ->assertOk()
            ->assertSee('Kuis Matematika Pecahan');

        $this->actingAs($akunSiswa)
            ->get(route('siswa.kuis.show', $kuis->id))
            ->assertOk()
            ->assertSee('Kuis Matematika Pecahan');

        $this->actingAs($akunSiswa)
            ->post(route('siswa.kuis.submit', $kuis->id), [
                'jawaban' => [0 => 'A'],
                'durasi' => 45,
            ])
            ->assertRedirect(route('siswa.kuis.index'));

        $hasil = HasilKuis::withoutGlobalScopes()
            ->where('kuis_id', $kuis->id)
            ->where('siswa_id', $siswa->id)
            ->firstOrFail();

        $this->assertSame($guru->id, (int) $hasil->guru_id);
        $this->assertEquals(100, (float) $hasil->skor);
    }

    public function test_p1_5_dan_6_otorisasi_nilai_erapor_lintas_kelas_dan_kunci_input_rapor_pada_nilai_harian(): void
    {
        $t1 = $this->createTenant('40100005', 'SDN 5 Nilai', 'sdn5-nilai');
        $sem = $this->createSemester($t1);

        $guruVA = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru V A',
            'nama_pengguna' => 'guru_va',
            'email' => 'guruva@sdn5.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'guru',
            'tenant_id' => $t1->id,
            'aktif' => true,
        ]);
        $guruVIB = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru VI B',
            'nama_pengguna' => 'guru_vib',
            'email' => 'guruvib@sdn5.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'guru',
            'tenant_id' => $t1->id,
            'aktif' => true,
        ]);

        TenantContext::run($t1->id, function () use ($t1, $guruVA, $guruVIB) {
            PengaturanGuru::create([
                'tenant_id' => $t1->id,
                'guru_id' => $guruVA->id,
                'nama_guru' => $guruVA->nama_lengkap,
                'kelas' => 'V A',
                'mata_pelajaran' => 'Matematika',
                'kkm' => 75,
                'semester' => 'Ganjil',
                'tahun_pelajaran' => '2026/2027',
            ]);
            PengaturanGuru::create([
                'tenant_id' => $t1->id,
                'guru_id' => $guruVIB->id,
                'nama_guru' => $guruVIB->nama_lengkap,
                'kelas' => 'VI B',
                'mata_pelajaran' => 'Matematika',
                'kkm' => 75,
                'semester' => 'Ganjil',
                'tahun_pelajaran' => '2026/2027',
            ]);
        });

        $siswaVA = TenantContext::run($t1->id, fn () => Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t1->id,
            'guru_id' => $guruVA->id,
            'nama_guru' => $guruVA->nama_lengkap,
            'nis' => '5001',
            'nama_peserta_didik' => 'Siswa Kelas V A',
            'kelas' => 'V A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        // Guru VI B tidak boleh mengubah nilai e-Rapor siswa Kelas V A
        $this->actingAs($guruVIB)
            ->postJson(route('guru.nilai-erapor.auto-save'), [
                'siswa_id' => $siswaVA->id,
                'field' => 'formatif',
                'value' => 95,
                'kelas' => 'V A',
                'mapel' => 'Matematika',
                'semester' => 'Ganjil',
                'tahun_ajaran' => '2026/2027',
            ])
            ->assertStatus(403);

        $this->assertSame(0, NilaiErapot::withoutGlobalScopes()->where('siswa_id', $siswaVA->id)->count());

        // Kunci rombel V A, pastikan InputNilaiController::simpanNilai dan NilaiController::store menolak input
        TenantContext::run($t1->id, fn () => Rombel::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t1->id,
            'dapodik_id' => 'rombel-va-5',
            'semester_id' => $sem->id,
            'guru_id' => $guruVA->id,
            'nama_rombel' => 'V A',
            'tingkat_pendidikan' => 5,
            'kunci_input_rapor' => true,
        ]));

        $this->actingAs($guruVA)
            ->postJson(route('guru.input-nilai.simpan'), [
                'jenis' => 'Tugas',
                'judul_penilaian' => 'Tugas 1',
                'nilai' => [
                    ['siswa_id' => $siswaVA->id, 'nilai' => 88],
                ],
            ])
            ->assertStatus(423);

        $this->actingAs($guruVA)
            ->from(route('guru.nilai.create'))
            ->post(route('guru.nilai.store'), [
                'siswa_id' => $siswaVA->id,
                'tanggal' => now()->toDateString(),
                'jenis' => 'Tugas',
                'judul_penilaian' => 'Tugas 1',
                'mata_pelajaran' => 'Matematika',
                'nilai' => 88,
            ])
            ->assertRedirect(route('guru.nilai.create'))
            ->assertSessionHas('error');

        $this->assertSame(0, Nilai::withoutGlobalScopes()->where('siswa_id', $siswaVA->id)->count());
    }

    public function test_p1_7_validasi_exists_terisolasi_per_tenant_pada_peta_kelas_presensi_dan_kelas_digital(): void
    {
        $t1 = $this->createTenant('40100007', 'SDN 7 Tenant A', 'sdn7-a');
        $t2 = $this->createTenant('40100008', 'SDN 8 Tenant B', 'sdn8-b');
        $sem2 = $this->createSemester($t2, '20261');

        $admin1 = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Admin Sekolah 1',
            'nama_pengguna' => 'admin_t1_7',
            'email' => 'admin1@sdn7.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'admin',
            'tenant_id' => $t1->id,
            'aktif' => true,
        ]);
        $guru1 = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Sekolah 1',
            'nama_pengguna' => 'guru_t1_7',
            'email' => 'guru1@sdn7.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'guru',
            'tenant_id' => $t1->id,
            'aktif' => true,
        ]);
        $guru2 = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Sekolah 2',
            'nama_pengguna' => 'guru_t2_8',
            'email' => 'guru2@sdn8.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'guru',
            'tenant_id' => $t2->id,
            'aktif' => true,
        ]);
        $rombel2 = TenantContext::run($t2->id, fn () => Rombel::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t2->id,
            'dapodik_id' => 'rombel-t2-8',
            'semester_id' => $sem2->id,
            'nama_rombel' => 'VI A',
            'tingkat_pendidikan' => 6,
        ]));

        // Admin Sekolah 1 mencoba memakai guru_id milik Sekolah 2 di Peta Kelas & Presensi Koreksi -> ditolak 404 oleh scope forSchool()
        $this->actingAs($admin1)
            ->post(route('admin.peta-kelas.store'), [
                'guru_id' => $guru2->id,
                'kelas' => 'VI A',
            ])
            ->assertNotFound();

        $this->actingAs($admin1)
            ->post(route('admin.presensi.pegawai.koreksi'), [
                'user_id' => $guru2->id,
                'tanggal' => now()->toDateString(),
                'status' => 'hadir',
                'jam_masuk' => '07:00',
                'alasan_koreksi' => 'Uji lintas tenant',
            ])
            ->assertNotFound();

        // Guru Sekolah 1 mencoba memakai rombel_id milik Sekolah 2 di Kelas Digital
        $this->actingAs($guru1)
            ->from(route('guru.kelas-digital.create'))
            ->post(route('guru.kelas-digital.store'), [
                'nama_kelas' => 'Kelas Digital Lintas',
                'mata_pelajaran' => 'Matematika',
                'rombel_id' => $rombel2->id,
            ])
            ->assertSessionHasErrors('rombel_id');
    }

    public function test_p1_8_9_dan_p2_10_12_sinkronisasi_nama_sekolah_otorisasi_kepsek_dan_lookup_siswa_aktif(): void
    {
        $t1 = $this->createTenant('40100009', 'SDN 9 Nama Lama', 'sdn9-lama');
        $t2 = $this->createTenant('40100010', 'SDN 10 Lain', 'sdn10-lain');

        $admin1 = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Admin SDN 9',
            'nama_pengguna' => 'admin_sdn9',
            'email' => 'admin@sdn9.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'admin',
            'tenant_id' => $t1->id,
            'aktif' => true,
        ]);

        // Admin mengubah nama sekolah di /admin/sekolah -> Tenant & header layout ikut tersinkronisasi
        $this->actingAs($admin1)
            ->post(route('admin.sekolah.update'), [
                'nama_sekolah' => 'SDN 9 Unggulan Baru',
                'npsn' => '40100009',
            ])
            ->assertRedirect(route('admin.sekolah.index'));

        $this->assertSame('SDN 9 Unggulan Baru', $t1->fresh()->nama_sekolah);
        $this->actingAs($admin1)
            ->get(route('admin.sekolah.index'))
            ->assertOk()
            ->assertSee('SDN 9 Unggulan Baru');

        // Kepsek dengan tenant_id = null tidak boleh mengakses supervisi guru milik tenant 2
        $kepsekNull = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Kepsek Single',
            'nama_pengguna' => 'kepsek_null',
            'email' => 'kepsek@single.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'kepsek',
            'tenant_id' => null,
            'aktif' => true,
        ]);
        $guruT2 = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Tenant 2',
            'nama_pengguna' => 'guru_t2_sup',
            'email' => 'gurut2@sdn10.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'guru',
            'tenant_id' => $t2->id,
            'aktif' => true,
        ]);

        $this->actingAs($kepsekNull)
            ->get(route('kepsek.supervisi-pm.show', $guruT2))
            ->assertStatus(403);

        // HasSiswaLookup harus mengabaikan baris siswa yang sudah diarsipkan dan mengambil siswa aktif
        $siswaArsip = TenantContext::run($t1->id, fn () => Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t1->id,
            'nis' => '9001-ARSIP',
            'nama_peserta_didik' => 'Siswa Arsip Lama',
            'kelas' => 'V A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
            'archived_at' => now(),
        ]));
        $siswaAktif = TenantContext::run($t1->id, fn () => Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t1->id,
            'nis' => '9001',
            'nama_peserta_didik' => 'Siswa Aktif Baru',
            'kelas' => 'VI A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
            'archived_at' => null,
        ]));
        $userSiswa = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Siswa Aktif Baru',
            'nama_pengguna' => 'siswa9001',
            'email' => 'siswa9001@sdn9.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'siswa',
            'tenant_id' => $t1->id,
            'terhubung_dengan' => [$siswaArsip->id],
            'aktif' => true,
        ]);

        $this->actingAs($userSiswa);
        $lookup = new class
        {
            use HasSiswaLookup;

            public function find(): ?Siswa
            {
                return TenantContext::run(auth()->user()->tenant_id, fn () => $this->getSiswa());
            }
        };

        $found = $lookup->find();
        $this->assertNotNull($found);
        $this->assertSame($siswaAktif->id, $found->id);

        // Uji pembuatan API Key dengan opsi "Semua (Dapodik + Presensi Mesin)" ('dapodik:import,presensi:import')
        $this->actingAs($admin1)
            ->post(route('admin.api-keys.store'), [
                'name' => 'Dapodik Bridge & Fingerprint',
                'akses' => 'dapodik:import,presensi:import',
            ])
            ->assertRedirect(route('admin.api-keys.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('new_key');

        $apiKey = \App\Models\ApiKey::where('user_id', $admin1->id)->latest('id')->firstOrFail();
        $this->assertSame(['dapodik:import', 'presensi:import'], $apiKey->abilities);
    }

    public function test_verifikasi_laporan_audit_lengkap_kokurikuler_mapel_push_post_tka_token_dan_profil_tendik(): void
    {
        $t1 = $this->createTenant('40100011', 'SDN 11 Sekolah A', 'sdn11-a');
        $t2 = $this->createTenant('40100012', 'SDN 12 Sekolah B', 'sdn12-b');

        $admin1 = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Admin Sekolah A',
            'nama_pengguna' => 'admin_11a',
            'email' => 'admin@sdn11.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'admin',
            'tenant_id' => $t1->id,
            'aktif' => true,
        ]);
        $admin2 = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Admin Sekolah B',
            'nama_pengguna' => 'admin_12b',
            'email' => 'admin@sdn12.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'admin',
            'tenant_id' => $t2->id,
            'aktif' => true,
        ]);

        // 1. P0-2: Isolasi TemaKokurikuler lintas tenant (Admin B tidak bisa menghapus tema milik Sekolah A)
        $temaA = TenantContext::run($t1->id, fn () => \App\Models\TemaKokurikuler::create([
            'tenant_id' => $t1->id,
            'nama' => 'Gaya Hidup Berkelanjutan A',
            'deskripsi' => 'Tema khusus Sekolah A',
            'aktif' => true,
        ]));

        $this->actingAs($admin2)
            ->delete(route('admin.kokurikuler.tema.destroy', $temaA->id))
            ->assertNotFound();
        $this->assertDatabaseHas('tema_kokurikuler', ['id' => $temaA->id]);

        // 2. P0-3: Isolasi MataPelajaran::destroySelected lintas tenant (Admin B tidak bisa menghapus mapel Sekolah A)
        $mapelA = TenantContext::run($t1->id, fn () => \App\Models\MataPelajaran::create([
            'tenant_id' => $t1->id,
            'kode' => 'MTK-A',
            'nama' => 'Matematika Sekolah A',
            'kelompok' => 'Wajib',
            'jenjang' => 'SD',
            'urutan' => 1,
            'aktif' => true,
        ]));

        $this->actingAs($admin2)
            ->delete(route('admin.mapel.destroy-selected'), [
                'ids' => [$mapelA->id],
            ]);
        $this->assertDatabaseHas('mata_pelajaran', ['id' => $mapelA->id]);

        // 3. P1-3: Route admin.dapodik.push.sekolah wajib POST (GET ditolak 405)
        $this->actingAs($admin1)
            ->get('/admin/dapodik/push/sekolah')
            ->assertStatus(405);

        // 4. P1-7: TkaSimulasiController::start menolak bypass token 'SIMANTAP' dan hanya menerima token acak sesi
        $siswa1 = TenantContext::run($t1->id, fn () => Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $t1->id,
            'nis' => '11001',
            'nama_peserta_didik' => 'Siswa Simulasi TKA',
            'kelas' => 'VI A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));
        $userSiswa1 = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Siswa Simulasi TKA',
            'nama_pengguna' => '11001',
            'email' => 'siswa11001@sdn11.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'siswa',
            'tenant_id' => $t1->id,
            'terhubung_dengan' => [$siswa1->id],
            'aktif' => true,
        ]);

        $this->actingAs($userSiswa1)
            ->get(route('siswa.tka-simulasi.confirm', 'literasi'))
            ->assertOk();

        $tokenAsli = session('tka_simulasi_token_literasi');
        $this->assertNotEmpty($tokenAsli);

        // Bypass 'SIMANTAP' wajib ditolak!
        $this->actingAs($userSiswa1)
            ->from(route('siswa.tka-simulasi.confirm', 'literasi'))
            ->post(route('siswa.tka-simulasi.start', 'literasi'), ['token' => 'SIMANTAP'])
            ->assertRedirect(route('siswa.tka-simulasi.confirm', 'literasi'))
            ->assertSessionHas('error');

        // Token sesi yang sah diterima
        $this->actingAs($userSiswa1)
            ->post(route('siswa.tka-simulasi.start', 'literasi'), ['token' => $tokenAsli])
            ->assertRedirect(route('siswa.tka-simulasi.exam', 'literasi'));

        // 5. P2-6 & P3: Akun Tendik dapat mengganti kata sandi mandiri via AccountProfileController & labelPeran('tendik') ramah pengguna
        $tendik = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Staf TU Sekolah A',
            'nama_pengguna' => 'tendik_11a',
            'email' => 'tendik@sdn11.sch.id',
            'kata_sandi' => Hash::make('SandiAwal123!'),
            'peran' => 'tendik',
            'tenant_id' => $t1->id,
            'aktif' => true,
        ]);

        $this->assertSame('Tenaga Kependidikan', labelPeran($tendik->peran));

        $this->actingAs($tendik)
            ->put(route('account.profile.password'), [
                'kata_sandi_saat_ini' => 'SandiAwal123!',
                'password' => 'SandiBaruTendik99!@#',
                'password_confirmation' => 'SandiBaruTendik99!@#',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('SandiBaruTendik99!@#', $tendik->fresh()->kata_sandi));

        // 6. P1-5 & P0-2: Alur Persetujuan Pendaftaran Sekolah (school_registration_require_approval=true), Mailable Verifikasi Email (tanpa bocor ke session guest), Halaman Status Tunggu, & Notifikasi Super Admin
        auth()->logout();
        \Illuminate\Support\Facades\Mail::fake();
        config([
            'simantap.school_registration_enabled' => true,
            'simantap.school_registration_require_approval' => true,
        ]);

        $superAdmin = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Super Admin Pusat',
            'nama_pengguna' => 'superadmin_approval',
            'email' => 'sa@simantap.sch.id',
            'kata_sandi' => Hash::make('Password123!'),
            'peran' => 'super_admin',
            'tenant_id' => null,
            'aktif' => true,
        ]);

        // NPSN palsu (8 digit kembar / diawali 0) harus ditolak
        \Illuminate\Support\Facades\RateLimiter::clear('register-sekolah:127.0.0.1');
        $this->from('/daftar-sekolah')->post('/daftar-sekolah', [
            'nama_sekolah' => 'SDN NPSN Palsu',
            'npsn' => '11111111',
            'jenjang' => 'SD',
            'kepala_sekolah' => 'Kepsek Palsu',
            'subdomain' => 'sdn-npsn-palsu',
            'nama_lengkap' => 'Op Palsu',
            'nama_pengguna' => 'op_palsu',
            'email' => 'oppalsu@sekolah.sch.id',
            'kata_sandi' => 'SandiKuat123!@#',
            'kata_sandi_confirmation' => 'SandiKuat123!@#',
        ])->assertSessionHasErrors('npsn');

        // Pendaftaran dengan NPSN valid saat require_approval=true -> masuk status pending (aktif = false), diarahkan ke halaman status tunggu, dan mengirim Mailable sungguhan
        \Illuminate\Support\Facades\RateLimiter::clear('register-sekolah:127.0.0.1');
        $regRes = $this->post('/daftar-sekolah', [
            'nama_sekolah' => 'SDN Bintang Timur 07',
            'npsn' => '40398765',
            'jenjang' => 'SD',
            'kepala_sekolah' => 'Drs. Kepsek Bintang',
            'subdomain' => 'sdn-bintang-07',
            'nama_lengkap' => 'Operator Bintang',
            'nama_pengguna' => 'op_bintang07',
            'email' => 'op@bintang07.sch.id',
            'kata_sandi' => 'SandiKuat123!@#',
            'kata_sandi_confirmation' => 'SandiKuat123!@#',
        ]);

        $pendingTenant = Tenant::where('npsn', '40398765')->firstOrFail();
        $regRes->assertRedirect(route('sekolah.status', $pendingTenant->uuid));
        $this->assertNull(session('email_verification_url'), 'Token verifikasi tidak boleh dibocorkan ke session guest');

        $verifyUrl = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SchoolRegistrationVerificationMail::class, function ($mail) use (&$verifyUrl) {
            $verifyUrl = $mail->verifyUrl;

            return $mail->hasTo('op@bintang07.sch.id');
        });
        $this->assertNotEmpty($verifyUrl);

        // Super Admin juga menerima email notifikasi pendaftaran sekolah baru
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SchoolRegistrationStatusMail::class, function ($mail) {
            return $mail->hasTo('sa@simantap.sch.id') && $mail->status === 'new_registration_admin_alert';
        });

        // Halaman status tunggu dapat diakses dan menampilkan informasi sekolah
        $this->get(route('sekolah.status', $pendingTenant->uuid))
            ->assertOk()
            ->assertSee('SDN Bintang Timur 07')
            ->assertSee('Menunggu Aktivasi');

        $this->assertFalse($pendingTenant->aktif);
        $this->assertTrue($pendingTenant->isPendingApproval());
        $this->assertFalse($pendingTenant->isEmailVerified());

        // Sebelum email diverifikasi, approve tanpa force_approve ditolak
        $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.approve', $pendingTenant->uuid))
            ->assertSessionHas('error');
        $this->assertFalse($pendingTenant->fresh()->aktif);

        // Uji kirim ulang verifikasi email: ditolak bila email tidak cocok, berhasil bila email sesuai
        auth()->logout();
        $this->from(route('sekolah.status', $pendingTenant->uuid))
            ->post(route('sekolah.verify-email.resend', $pendingTenant->uuid), ['email' => 'penyerang@asing.com'])
            ->assertSessionHasErrors('email');

        $newVerifyUrl = null;
        $resendRes = $this->post(route('sekolah.verify-email.resend', $pendingTenant->uuid), ['email' => 'op@bintang07.sch.id']);
        $resendRes->assertRedirect(route('sekolah.status', $pendingTenant->uuid));
        $this->assertNull(session('email_verification_url'));

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SchoolRegistrationVerificationMail::class, function ($mail) use (&$newVerifyUrl) {
            $newVerifyUrl = $mail->verifyUrl;

            return $mail->hasTo('op@bintang07.sch.id');
        });
        $this->assertNotEmpty($newVerifyUrl);

        // Klik tautan verifikasi email -> status email_verified_at tercatat di Tenant meta DAN kolom users.email_verified_at
        $this->get($newVerifyUrl)->assertRedirect(route('sekolah.status', $pendingTenant->uuid));
        $pendingTenant->refresh();
        $this->assertTrue($pendingTenant->isEmailVerified());
        $this->assertNotNull(
            User::withoutGlobalScopes()->where('nama_pengguna', 'op_bintang07')->value('email_verified_at')
        );

        $this->actingAs($superAdmin)
            ->get(route('superadmin.tenants.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('SDN Bintang Timur 07')
            ->assertSee('Menunggu Persetujuan');

        $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.approve', $pendingTenant->uuid))
            ->assertRedirect();

        $pendingTenant->refresh();
        $this->assertTrue($pendingTenant->aktif);
        $this->assertSame('approved', $pendingTenant->approvalStatus());
        $this->assertTrue(
            (bool) User::withoutGlobalScopes()->where('nama_pengguna', 'op_bintang07')->value('aktif')
        );

        // Email pemberitahuan persetujuan dikirim ke operator sekolah
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SchoolRegistrationStatusMail::class, function ($mail) {
            return $mail->hasTo('op@bintang07.sch.id') && $mail->status === 'approved';
        });

        // 7. Isolasi TranskripNilai dengan kolom tenant_id langsung & BelongsToTenant
        $transkripA = TenantContext::run($t1->id, fn () => \App\Models\TranskripNilai::create([
            'tenant_id' => $t1->id,
            'siswa_id' => $siswa1->id,
            'mata_pelajaran_id' => $mapelA->id,
            'nama_mapel' => 'Matematika Sekolah A',
            'nilai_akhir' => 92.5,
            'predikat' => 'A',
        ]));
        $this->assertSame($t1->id, (int) $transkripA->tenant_id);
        $this->assertSame(0, TenantContext::run($t2->id, fn () => \App\Models\TranskripNilai::count()));
        $this->assertSame(1, TenantContext::run($t1->id, fn () => \App\Models\TranskripNilai::count()));
    }
}
