<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi bug kosakata jenis nilai:
 *
 * Halaman "Input Nilai" mengirim jenis "UH", sedangkan enum kolom
 * `nilai.jenis` (dan bobot di App\Services\NilaiService) memakai
 * "Ulangan Harian". Akibatnya penyimpanan gagal (check constraint PostgreSQL)
 * atau nilai tidak pernah ikut dihitung.
 */
class InputNilaiJenisTest extends TestCase
{
    use RefreshDatabase;

    public function test_jenis_uh_dinormalkan_menjadi_ulangan_harian(): void
    {
        $guru = User::factory()->guru()->create();

        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'N001',
            'nisn' => '3333333333',
            'nama_peserta_didik' => 'Nana Nilai',
            'kelas' => '6.A',
            'jenis_kelamin' => 'P',
        ]);

        $this->actingAs($guru)->postJson(route('guru.input-nilai.simpan'), [
            'jenis' => 'UH',
            'judul_penilaian' => 'UH 1',
            'nilai' => [
                ['siswa_id' => $siswa->id, 'nilai' => 88],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('nilai', [
            'siswa_id' => $siswa->id,
            'jenis' => 'Ulangan Harian',
            'judul_penilaian' => 'UH 1',
        ]);
        $this->assertDatabaseMissing('nilai', ['jenis' => 'UH']);
    }

    public function test_jenis_di_luar_enum_ditolak_validasi(): void
    {
        $guru = User::factory()->guru()->create();

        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'N002',
            'nisn' => '4444444444',
            'nama_peserta_didik' => 'Nunu Nilai',
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
        ]);

        $this->actingAs($guru)->postJson(route('guru.input-nilai.simpan'), [
            'jenis' => 'Ulangan',
            'judul_penilaian' => 'Salah',
            'nilai' => [
                ['siswa_id' => $siswa->id, 'nilai' => 70],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('nilai', 0);
    }

    public function test_guru_tidak_dapat_menyimpan_nilai_siswa_guru_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $guruLain = User::factory()->guru()->create();

        $siswaOrangLain = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guruLain->id,
            'nama_guru' => $guruLain->nama_lengkap,
            'nis' => 'N003',
            'nisn' => '5555555555',
            'nama_peserta_didik' => 'Nia Nilai',
            'kelas' => '6.B',
            'jenis_kelamin' => 'P',
        ]);

        $this->actingAs($guru)->postJson(route('guru.input-nilai.simpan'), [
            'jenis' => 'Tugas',
            'judul_penilaian' => 'Tugas 1',
            'nilai' => [
                ['siswa_id' => $siswaOrangLain->id, 'nilai' => 90],
            ],
        ])->assertOk();

        // Guard kepemilikan: nilai tidak boleh masuk ke siswa guru lain.
        $this->assertDatabaseCount('nilai', 0);
    }
}
