<?php

namespace Tests\Feature;

use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
