<?php

namespace Tests\Feature;

use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KepsekPetaKelasDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_peta_kelas_menampilkan_empty_state_tanpa_nilai_dummy_saat_kosong(): void
    {
        $kepsek = User::factory()->create([
            'peran' => 'kepsek',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guru = User::factory()->create([
            'peran' => 'guru',
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $response = $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', $guru->id));

        $response->assertOk()
            ->assertSee('Data belum tersedia')
            ->assertDontSee('PTS 1')
            ->assertDontSee('[72,75,78,76,80,82]', false)
            ->assertDontSee('[70,70,70,70,70,70]', false)
            ->assertDontSee('[3,5,2,1]', false);
    }

    public function test_detail_peta_kelas_memakai_data_nilai_nyata_bila_tersedia(): void
    {
        $kepsek = User::factory()->create([
            'peran' => 'kepsek',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guru = User::factory()->create([
            'peran' => 'guru',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $siswa = Siswa::create([
            'guru_id' => $guru->id,
            'nis' => '20260001',
            'nama_peserta_didik' => 'Siswa Uji Peta',
            'jenis_kelamin' => 'L',
            'kelas' => '5A',
            'aktif' => true,
        ]);

        Nilai::create([
            'guru_id' => $guru->id,
            'siswa_id' => $siswa->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa->nama_peserta_didik,
            'tanggal' => now()->subDays(5)->toDateString(),
            'jenis' => 'Ulangan',
            'judul_penilaian' => 'Ulangan Bab 1',
            'mata_pelajaran' => 'Matematika',
            'nilai' => 84,
        ]);
        Nilai::create([
            'guru_id' => $guru->id,
            'siswa_id' => $siswa->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa->nama_peserta_didik,
            'tanggal' => now()->subDay()->toDateString(),
            'jenis' => 'Sumatif',
            'judul_penilaian' => 'Sumatif Tengah',
            'mata_pelajaran' => 'Matematika',
            'nilai' => 92,
        ]);

        $response = $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', $guru->id));

        $response->assertOk()
            ->assertSee('Ulangan Bab 1')
            ->assertSee('Sumatif Tengah')
            ->assertDontSee('PTS 1');
    }

    public function test_detail_peta_kelas_memisahkan_nilai_antar_kelas_untuk_guru_yang_sama(): void
    {
        $kepsek = User::factory()->create([
            'peran' => 'kepsek',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guru = User::factory()->create([
            'peran' => 'guru',
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $siswa4A = Siswa::create([
            'guru_id' => $guru->id,
            'nis' => '20264001',
            'nama_peserta_didik' => 'Siswa Empat A',
            'jenis_kelamin' => 'L',
            'kelas' => '4A',
            'aktif' => true,
        ]);
        $siswa4B = Siswa::create([
            'guru_id' => $guru->id,
            'nis' => '20264002',
            'nama_peserta_didik' => 'Siswa Empat B',
            'jenis_kelamin' => 'P',
            'kelas' => '4B',
            'aktif' => true,
        ]);
        $siswaNonAktif = Siswa::create([
            'guru_id' => $guru->id,
            'nis' => '20264003',
            'nama_peserta_didik' => 'Siswa NonAktif',
            'jenis_kelamin' => 'L',
            'kelas' => '4A',
            'aktif' => false,
        ]);

        Nilai::create([
            'guru_id' => $guru->id,
            'siswa_id' => $siswa4A->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa4A->nama_peserta_didik,
            'tanggal' => now()->subDays(2)->toDateString(),
            'jenis' => 'Ulangan',
            'judul_penilaian' => 'UH Kelas 4A',
            'mata_pelajaran' => 'IPA',
            'nilai' => 81,
        ]);
        Nilai::create([
            'guru_id' => $guru->id,
            'siswa_id' => $siswa4B->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa4B->nama_peserta_didik,
            'tanggal' => now()->subDay()->toDateString(),
            'jenis' => 'Ulangan',
            'judul_penilaian' => 'UH Kelas 4B',
            'mata_pelajaran' => 'IPA',
            'nilai' => 96,
        ]);
        Nilai::create([
            'guru_id' => $guru->id,
            'siswa_id' => $siswaNonAktif->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswaNonAktif->nama_peserta_didik,
            'tanggal' => now()->toDateString(),
            'jenis' => 'Ulangan',
            'judul_penilaian' => 'UH Siswa NonAktif',
            'mata_pelajaran' => 'IPA',
            'nilai' => 45,
        ]);

        // Tanpa ?kelas=: menampilkan gabungan kelas aktif milik guru dengan label "Semua kelas"
        $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', $guru->id))
            ->assertOk()
            ->assertSee('Semua kelas')
            ->assertSee('UH Kelas 4A')
            ->assertSee('UH Kelas 4B')
            ->assertDontSee('UH Siswa NonAktif');

        // Filter 4A: hanya memuat nilai 4A, tidak memuat 4B atau siswa non-aktif
        $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', ['guruId' => $guru->id, 'kelas' => '4A']))
            ->assertOk()
            ->assertSee('Kelas 4A')
            ->assertSee('UH Kelas 4A')
            ->assertDontSee('UH Kelas 4B')
            ->assertDontSee('UH Siswa NonAktif');

        // Filter 4B: hanya memuat nilai 4B, tidak memuat 4A atau siswa non-aktif
        $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', ['guruId' => $guru->id, 'kelas' => '4B']))
            ->assertOk()
            ->assertSee('Kelas 4B')
            ->assertSee('UH Kelas 4B')
            ->assertDontSee('UH Kelas 4A')
            ->assertDontSee('UH Siswa NonAktif');

        // ?kelas=XYZ (bukan kelas guru ini) -> 404
        $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', ['guruId' => $guru->id, 'kelas' => 'XYZ']))
            ->assertNotFound();
    }

    public function test_detail_peta_kelas_mengisolasi_nilai_lintas_tenant(): void
    {
        $tenantA = Tenant::create(['npsn' => '70001111', 'nama_sekolah' => 'Sekolah A', 'subdomain' => 'peta-a', 'aktif' => true]);
        $tenantB = Tenant::create(['npsn' => '70002222', 'nama_sekolah' => 'Sekolah B', 'subdomain' => 'peta-b', 'aktif' => true]);

        $kepsekA = User::factory()->create([
            'peran' => 'kepsek',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guruA = User::factory()->create([
            'peran' => 'guru',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $siswaA = Siswa::create([
            'tenant_id' => $tenantA->id,
            'guru_id' => $guruA->id,
            'nis' => '90001001',
            'nama_peserta_didik' => 'Siswa Tenant A',
            'jenis_kelamin' => 'L',
            'kelas' => '5A',
            'aktif' => true,
        ]);
        $siswaB = Siswa::create([
            'tenant_id' => $tenantB->id,
            'guru_id' => $guruA->id,
            'nis' => '90002001',
            'nama_peserta_didik' => 'Siswa Tenant B',
            'jenis_kelamin' => 'P',
            'kelas' => '5A',
            'aktif' => true,
        ]);

        Nilai::create([
            'tenant_id' => $tenantA->id,
            'guru_id' => $guruA->id,
            'siswa_id' => $siswaA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'nama_siswa' => $siswaA->nama_peserta_didik,
            'tanggal' => now()->subDay()->toDateString(),
            'jenis' => 'Ulangan',
            'judul_penilaian' => 'UH Tenant A',
            'mata_pelajaran' => 'Matematika',
            'nilai' => 86,
        ]);
        Nilai::create([
            'tenant_id' => $tenantB->id,
            'guru_id' => $guruA->id,
            'siswa_id' => $siswaB->id,
            'nama_guru' => $guruA->nama_lengkap,
            'nama_siswa' => $siswaB->nama_peserta_didik,
            'tanggal' => now()->toDateString(),
            'jenis' => 'Ulangan',
            'judul_penilaian' => 'UH Tenant B',
            'mata_pelajaran' => 'Matematika',
            'nilai' => 99,
        ]);

        $this->actingAs($kepsekA)
            ->get(route('kepsek.peta-kelas.detail', $guruA->id))
            ->assertOk()
            ->assertSee('UH Tenant A')
            ->assertDontSee('UH Tenant B');
    }

    public function test_detail_peta_kelas_tidak_n_plus_1_dengan_30_siswa(): void
    {
        $kepsek = User::factory()->create([
            'peran' => 'kepsek',
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guru = User::factory()->create([
            'peran' => 'guru',
            'aktif' => true,
            'force_password_change' => false,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $siswa = Siswa::create([
                'guru_id' => $guru->id,
                'nis' => '20269'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'nama_peserta_didik' => "Siswa {$i}",
                'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                'kelas' => '6A',
                'aktif' => true,
            ]);

            Nilai::create([
                'guru_id' => $guru->id,
                'siswa_id' => $siswa->id,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswa->nama_peserta_didik,
                'tanggal' => now()->subDays($i % 3)->toDateString(),
                'jenis' => 'Ulangan',
                'judul_penilaian' => 'UH '.(($i % 3) + 1),
                'mata_pelajaran' => 'Matematika',
                'nilai' => 75 + ($i % 20),
            ]);
        }

        // Warm-up cache layout/view sebelum mengukur, lalu ukur 5 siswa
        $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', ['guruId' => $guru->id, 'kelas' => '6A']))
            ->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', ['guruId' => $guru->id, 'kelas' => '6A']))
            ->assertOk();
        $queryCount5 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Tambah 25 siswa lagi -> total 30 siswa
        for ($i = 6; $i <= 30; $i++) {
            $siswa = Siswa::create([
                'guru_id' => $guru->id,
                'nis' => '20269'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'nama_peserta_didik' => "Siswa {$i}",
                'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                'kelas' => '6A',
                'aktif' => true,
            ]);

            Nilai::create([
                'guru_id' => $guru->id,
                'siswa_id' => $siswa->id,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswa->nama_peserta_didik,
                'tanggal' => now()->subDays($i % 3)->toDateString(),
                'jenis' => 'Ulangan',
                'judul_penilaian' => 'UH '.(($i % 3) + 1),
                'mata_pelajaran' => 'Matematika',
                'nilai' => 75 + ($i % 20),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($kepsek)
            ->get(route('kepsek.peta-kelas.detail', ['guruId' => $guru->id, 'kelas' => '6A']))
            ->assertOk();
        $queryCount30 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Tidak ada N+1: jumlah query untuk 30 siswa harus sama dengan 5 siswa
        $this->assertSame($queryCount5, $queryCount30, "Query bertumbuh dari {$queryCount5} (5 siswa) menjadi {$queryCount30} (30 siswa).");
        $this->assertLessThan(30, $queryCount30);
        fwrite(STDOUT, "PETA_KELAS_DETAIL_QUERIES: 5_siswa={$queryCount5}, 30_siswa={$queryCount30}\n");
    }
}
