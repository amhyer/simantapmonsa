<?php

namespace Tests\Feature;

use App\Models\Ekstrakurikuler;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\Ptk;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminReferensiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Semua halaman Data Referensi (12 sub) + Status Penilaian (2)
     * harus merespons 200 sebagai admin.
     *
     * @return array<string, array{string}>
     */
    public static function halamanProvider(): array
    {
        return [
            'sekolah' => ['admin.sekolah.index'],
            'guru' => ['admin.referensi.guru'],
            'siswa' => ['admin.users.siswa'],
            'kelas' => ['admin.peta-kelas.index'],
            'mapel' => ['admin.mapel.index'],
            'pembelajaran' => ['admin.referensi.pembelajaran'],
            'ekstrakurikuler' => ['admin.referensi.ekstrakurikuler'],
            'kelompok mapel' => ['admin.referensi.kelompok-mapel'],
            'mapping rapor' => ['admin.referensi.mapping-rapor'],
            'logo ttd' => ['admin.referensi.logo-ttd'],
            'tanggal rapor' => ['admin.referensi.tanggal-rapor'],
            'foto siswa' => ['admin.referensi.foto-siswa'],
            'status penilaian' => ['admin.penilaian.status'],
            'statistik nilai' => ['admin.penilaian.statistik'],
        ];
    }

    #[DataProvider('halamanProvider')]
    public function test_halaman_merespons_200_untuk_admin(string $route): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route($route))->assertOk();
    }

    /**
     * Halaman bertabel wajib memakai struktur CSS global .table-wrapper
     * agar konsisten dengan seluruh aplikasi.
     */
    public function test_halaman_tabel_memakai_table_wrapper(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();

        // Seed 1 baris per sumber data agar tiap halaman benar-benar
        // merender <div class="table-wrapper"> (bukan empty-state).
        $semester = Semester::create([
            'semester_id' => '20251',
            'tahun_ajaran' => '2025/2026',
            'nama_semester' => 'ganjil',
        ]);
        Ptk::create([
            'dapodik_id' => 'PTK-SEED-1',
            'semester_id' => $semester->id,
            'nama' => 'Guru Seed',
            'jenis_ptk' => 'Guru',
        ]);
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'T001',
            'nisn' => '9999999999',
            'nama_peserta_didik' => 'Siswa Tabel',
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
        ]);
        JadwalPelajaran::create([
            'hari' => 'Senin',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6.A',
            'guru_id' => $guru->id,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:00',
        ]);
        Ekstrakurikuler::create(['nama' => 'Pramuka']);
        MataPelajaran::create(['nama' => 'Matematika', 'aktif' => true]);
        TanggalRapor::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'tanggal' => '2025-12-20',
        ]);
        NilaiErapot::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6.A',
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2025/2026',
            'nilai_akhir' => 85,
            'predikat' => 'B',
        ]);

        // Skema standar tanpa is_aktif tetap memilih semester terbaru dan
        // tidak meninggalkan transaksi PostgreSQL dalam keadaan aborted.
        forgetSemesterAktifLabelCache();
        $this->assertSame('2025/2026 (ganjil)', semesterAktifLabel());
        $this->assertTrue(Semester::query()->exists());

        $routes = [
            'admin.referensi.guru',
            'admin.users.siswa',
            'admin.referensi.pembelajaran',
            'admin.referensi.ekstrakurikuler',
            'admin.referensi.kelompok-mapel',
            'admin.referensi.mapping-rapor',
            'admin.referensi.tanggal-rapor',
            'admin.penilaian.status',
            'admin.penilaian.statistik',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get(route($route))
                ->assertOk()
                ->assertSee('table-wrapper', false);
        }
    }

    public function test_mapel_meta_menyimpan_flag_transkrip(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'aktif' => true]);

        $this->actingAs($admin)->put(route('admin.referensi.mapel-meta.update'), [
            'mapel' => [$mapel->id => ['kelompok' => 'A', 'urutan' => 1, 'masuk_transkrip' => 1]],
        ])->assertRedirect();

        $this->assertTrue($mapel->fresh()->masuk_transkrip);

        $this->actingAs($admin)->put(route('admin.referensi.mapel-meta.update'), [
            'mapel' => [$mapel->id => ['kelompok' => 'A', 'urutan' => 1, 'masuk_transkrip' => 0]],
        ])->assertRedirect();

        $this->assertFalse($mapel->fresh()->masuk_transkrip);
    }

    public function test_admin_dapat_menambah_pembelajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();

        $this->actingAs($admin)->post(route('admin.referensi.pembelajaran.store'), [
            'hari' => 'Senin',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 1.A',
            'guru_id' => $guru->id,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'hari' => 'Senin',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 1.A',
        ]);
    }

    public function test_tambah_pembelajaran_memvalidasi_input(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.referensi.pembelajaran.store'), [
            'hari' => 'Ahad',
        ])->assertSessionHasErrors(['hari', 'mata_pelajaran', 'kelas']);
    }

    public function test_admin_dapat_menghapus_pembelajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $jadwal = JadwalPelajaran::create([
            'hari' => 'Selasa',
            'mata_pelajaran' => 'IPA',
            'kelas' => 'Kelas 2.B',
            'guru_id' => $guru->id,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:00',
        ]);

        $this->actingAs($admin)->delete(route('admin.referensi.pembelajaran.destroy', $jadwal))
            ->assertRedirect();

        $this->assertDatabaseMissing('jadwal_pelajaran', ['id' => $jadwal->id]);
    }

    public function test_non_admin_ditolak_mengubah_pembelajaran(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->post(route('admin.referensi.pembelajaran.store'), [
            'hari' => 'Senin',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 1.A',
        ])->assertForbidden();
    }

    public function test_admin_dapat_menambah_guru_non_dapodik_dan_akun_user_terbuat(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.referensi.guru.store'), [
            'nama' => 'Budi Santoso',
            'nip' => '198501012010011001',
            'nuptk' => '1234567890123456',
            'jk' => 'L',
            'jenis_ptk' => 'Guru Kelas',
            'jabatan' => 'Guru Kelas 4',
            'gelar_depan' => 'Drs.',
            'gelar_belakang' => 'M.Pd',
            'buat_akun' => '1',
        ]);

        $response->assertRedirect(route('admin.referensi.guru'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ptk', [
            'nama' => 'Budi Santoso',
            'nip' => '198501012010011001',
            'nuptk' => '1234567890123456',
            'jk' => 'L',
            'jenis_ptk' => 'Guru Kelas',
            'gelar_depan' => 'Drs.',
            'gelar_belakang' => 'M.Pd',
            'dapodik_id' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'nama_lengkap' => 'Budi Santoso',
            'nama_pengguna' => '198501012010011001',
            'peran' => 'guru',
            'aktif' => true,
        ]);
    }

    public function test_admin_dapat_menghapus_guru_non_dapodik(): void
    {
        $admin = User::factory()->admin()->create();
        $ptk = Ptk::create([
            'nama' => 'Guru Manual',
            'jenis_ptk' => 'Guru',
            'dapodik_id' => null,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.referensi.guru.destroy', $ptk));
        $response->assertRedirect(route('admin.referensi.guru'));

        $this->assertDatabaseMissing('ptk', ['id' => $ptk->id]);
    }

    public function test_admin_tidak_dapat_menghapus_guru_dari_dapodik(): void
    {
        $admin = User::factory()->admin()->create();
        $semester = Semester::create(['semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']);
        $ptk = Ptk::create([
            'nama' => 'Guru Dapodik Asli',
            'jenis_ptk' => 'Guru',
            'dapodik_id' => 'PTK-DAPODIK-999',
            'semester_id' => $semester->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.referensi.guru.destroy', $ptk));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('ptk', ['id' => $ptk->id]);
    }

    public function test_halaman_referensi_guru_menampilkan_tombol_dan_badge_non_dapodik(): void
    {
        $admin = User::factory()->admin()->create();
        Ptk::create([
            'nama' => 'Guru Non Dapodik Display',
            'jenis_ptk' => 'Guru',
            'dapodik_id' => null,
        ]);

        $this->actingAs($admin)->get(route('admin.referensi.guru'))
            ->assertOk()
            ->assertSee('Tambah PTK / Guru (Non-Dapodik)')
            ->assertSee('NON-DAPODIK')
            ->assertSee('Guru Non Dapodik Display');
    }

    public function test_admin_dapat_menambah_ptk_non_dapodik_gpk_dan_kebersihan_dan_security(): void
    {
        $admin = User::factory()->admin()->create();

        // 1. Tambah Guru GPK
        $responseGpk = $this->actingAs($admin)->post(route('admin.referensi.guru.store'), [
            'nama' => 'Sri Wahyuni GPK',
            'jenis_ptk' => 'Guru GPK',
            'jabatan' => 'Guru Pembimbing Khusus Inklusi',
            'jk' => 'P',
            'buat_akun' => 1,
            'nama_pengguna' => 'sri.gpk',
        ]);
        $responseGpk->assertRedirect(route('admin.referensi.guru'));
        $this->assertDatabaseHas('ptk', ['nama' => 'Sri Wahyuni GPK', 'jenis_ptk' => 'Guru GPK']);
        $this->assertDatabaseHas('users', ['nama_pengguna' => 'sri.gpk', 'peran' => 'guru']);

        // 2. Tambah Kebersihan
        $responseKebersihan = $this->actingAs($admin)->post(route('admin.referensi.guru.store'), [
            'nama' => 'Dg Bau Bersih',
            'jenis_ptk' => 'Tenaga Kebersihan',
            'jabatan' => 'Petugas Kebersihan Gedung',
            'jk' => 'L',
            'buat_akun' => 1,
            'nama_pengguna' => 'dgbau.kebersihan',
        ]);
        $responseKebersihan->assertRedirect(route('admin.referensi.guru'));
        $this->assertDatabaseHas('ptk', ['nama' => 'Dg Bau Bersih', 'jenis_ptk' => 'Tenaga Kebersihan']);
        $this->assertDatabaseHas('users', ['nama_pengguna' => 'dgbau.kebersihan', 'peran' => 'tendik']);

        // 3. Tambah Security
        $responseSecurity = $this->actingAs($admin)->post(route('admin.referensi.guru.store'), [
            'nama' => 'Pak Rustam Satpam',
            'jenis_ptk' => 'Security',
            'jabatan' => 'Koordinator Satpam',
            'jk' => 'L',
            'buat_akun' => 1,
            'nama_pengguna' => 'rustam.security',
        ]);
        $responseSecurity->assertRedirect(route('admin.referensi.guru'));
        $this->assertDatabaseHas('ptk', ['nama' => 'Pak Rustam Satpam', 'jenis_ptk' => 'Security']);
        $this->assertDatabaseHas('users', ['nama_pengguna' => 'rustam.security', 'peran' => 'tendik']);
    }

    public function test_admin_dapat_update_jenis_ptk_dan_jabatan(): void
    {
        $admin = User::factory()->admin()->create();
        $ptk = Ptk::create([
            'nama' => 'Budi Staff',
            'jenis_ptk' => 'Guru Kelas',
            'jabatan' => null,
            'dapodik_id' => null,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.referensi.gelar-ptk.update'), [
            'ptk' => [
                $ptk->id => [
                    'gelar_depan' => '',
                    'gelar_belakang' => 'S.Pd',
                    'jenis_ptk' => 'Guru GPK',
                    'jabatan' => 'Guru Pembimbing Khusus 1B',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ptk', [
            'id' => $ptk->id,
            'gelar_belakang' => 'S.Pd',
            'jenis_ptk' => 'Guru GPK',
            'jabatan' => 'Guru Pembimbing Khusus 1B',
        ]);
    }

    public function test_ptk_model_helpers_detect_gpk_kebersihan_security(): void
    {
        $gpk = new Ptk(['jenis_ptk' => 'Guru GPK', 'jabatan' => 'Guru Pembimbing Khusus']);
        $kebersihan = new Ptk(['jenis_ptk' => 'Tenaga Kebersihan', 'jabatan' => 'Petugas Kebersihan']);
        $security = new Ptk(['jenis_ptk' => 'Security', 'jabatan' => 'Satpam Sekolah']);

        $this->assertTrue($gpk->isGpk());
        $this->assertTrue($gpk->isGuru());
        $this->assertFalse($gpk->isKepsek());

        $this->assertTrue($kebersihan->isKebersihan());
        $this->assertFalse($kebersihan->isGuru());

        $this->assertTrue($security->isSecurity());
        $this->assertFalse($security->isGuru());
    }
}
