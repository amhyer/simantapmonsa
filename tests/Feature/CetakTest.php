<?php

namespace Tests\Feature;

use App\Models\Catatan;
use App\Models\Kehadiran;
use App\Models\NilaiErapot;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CetakTest extends TestCase
{
    use RefreshDatabase;

    private function buatSiswa(): Siswa
    {
        $guru = User::factory()->guru()->create();

        return Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'C001',
            'nisn' => '1111111111',
            'nama_peserta_didik' => 'Citra Cetak',
            'kelas' => '6.A',
            'jenis_kelamin' => 'P',
            'tinggi_badan' => '130',
            'berat_badan' => '28',
        ]);
    }

    private function seedNilai(Siswa $siswa): void
    {
        foreach ([['Matematika', 85, 'B'], ['Bahasa Indonesia', 92, 'A']] as [$mapel, $nilai, $predikat]) {
            NilaiErapot::create([
                'siswa_id' => $siswa->id,
                'guru_id' => $siswa->guru_id,
                'mata_pelajaran' => $mapel,
                'kelas' => '6.A',
                'semester' => 'Ganjil',
                'tahun_ajaran' => '2025/2026',
                'nilai_akhir' => $nilai,
                'predikat' => $predikat,
                'deskripsi_capaian' => "Baik dalam {$mapel}.",
            ]);
        }
        Kehadiran::create(['guru_id' => $siswa->guru_id, 'siswa_id' => $siswa->id, 'tanggal' => '2025-08-01', 'status' => 'S']);
        Kehadiran::create(['guru_id' => $siswa->guru_id, 'siswa_id' => $siswa->id, 'tanggal' => '2025-08-02', 'status' => 'I']);
        Kehadiran::create(['guru_id' => $siswa->guru_id, 'siswa_id' => $siswa->id, 'tanggal' => '2025-08-03', 'status' => 'A']);
        Catatan::create([
            'guru_id' => $siswa->guru_id, 'siswa_id' => $siswa->id,
            'nama_guru' => 'Guru', 'nama_siswa' => 'Citra',
            'tanggal' => '2025-12-01', 'jenis' => 'apresiasi', 'catatan' => 'Rajin belajar.',
        ]);
    }

    public function test_guest_dialihkan_ke_login(): void
    {
        $this->get(route('admin.cetak.leger'))->assertRedirect(route('login'));
    }

    public function test_guru_ditolak_akses_cetak(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get(route('admin.cetak.leger'))->assertForbidden();
        $this->actingAs($guru)->get(route('admin.cetak.pelengkap'))->assertForbidden();
        $this->actingAs($guru)->get(route('admin.cetak.nilai'))->assertForbidden();
    }

    public function test_admin_dapat_membuka_halaman_kosong(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['admin.cetak.leger', 'admin.cetak.pelengkap', 'admin.cetak.nilai'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_leger_menampilkan_matriks_nilai(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $this->seedNilai($siswa);

        $res = $this->actingAs($admin)->get(route('admin.cetak.leger', [
            'kelas' => '6.A', 'semester' => 'Ganjil', 'tahun_ajaran' => '2025/2026',
        ]))->assertOk();
        $res->assertSee('Citra Cetak');
        $res->assertSee('Matematika');
        $res->assertSee('88,50'); // rata-rata (85+92)/2
    }

    public function test_pelengkap_menampilkan_rekap_hadir_dan_fisik(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $this->seedNilai($siswa);

        $res = $this->actingAs($admin)->get(route('admin.cetak.pelengkap', [
            'kelas' => '6.A',
        ]))->assertOk();
        $res->assertSee('Citra Cetak');
        $res->assertSee('130 cm');
        $res->assertSee('Rajin belajar.');
    }

    public function test_nilai_show_menampilkan_deskripsi_dan_ketidakhadiran(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $this->seedNilai($siswa);

        $res = $this->actingAs($admin)->get(route('admin.cetak.nilai.show', [
            'siswa' => $siswa->id, 'semester' => 'Ganjil', 'tahun_ajaran' => '2025/2026',
        ]))->assertOk();
        $res->assertSee('Citra Cetak');
        $res->assertSee('Bahasa Indonesia');
        $res->assertSee('Rajin belajar.');
    }
}
