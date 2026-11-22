<?php

namespace Tests\Feature;

use App\Models\Dimensi;
use App\Models\Kehadiran;
use App\Models\Kebiasaan;
use App\Models\NilaiErapot;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi optimasi dashboard kepala sekolah (review #12).
 *
 * Dulu seluruh baris nilai/kehadiran/kebiasaan dimuat ke memori lalu dihitung
 * di PHP. Sekarang agregasinya di SQL — test ini mengunci angka hasilnya agar
 * refactor tidak diam-diam mengubah arti "rata nilai", "% hadir", atau
 * "partisipasi orang tua".
 */
class KepsekDashboardAgregasiTest extends TestCase
{
    use RefreshDatabase;

    private function siswaUntuk(User $guru, string $kelas, string $nis): Siswa
    {
        return Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => $nis,
            'nama_peserta_didik' => 'Siswa '.$nis,
            'kelas' => $kelas,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
    }

    public function test_per_kelas_menghitung_rata_nilai_kehadiran_dan_partisipasi(): void
    {
        $kepsek = User::factory()->kepsek()->create();
        $guru = User::factory()->guru()->create();

        $a = $this->siswaUntuk($guru, '4.A', 'K-001');
        $b = $this->siswaUntuk($guru, '4.A', 'K-002');

        foreach ([[$a, 80.0, 'B'], [$b, 90.0, 'A']] as [$siswa, $nilai, $predikat]) {
            NilaiErapot::create([
                'uuid' => (string) Str::uuid(),
                'siswa_id' => $siswa->id,
                'guru_id' => $guru->id,
                'mata_pelajaran' => 'Matematika',
                'kelas' => '4.A',
                'semester' => 'Ganjil',
                'tahun_ajaran' => '2026/2027',
                'nilai_akhir' => $nilai,
                'predikat' => $predikat,
            ]);
        }

        // Empat baris kehadiran: 3 hadir, 1 alfa.
        foreach ([[$a, '2026-11-02', 'H'], [$a, '2026-11-03', 'A'], [$b, '2026-11-02', 'H'], [$b, '2026-11-03', 'H']] as [$siswa, $tanggal, $status]) {
            Kehadiran::create([
                'uuid' => (string) Str::uuid(),
                'guru_id' => $guru->id,
                'siswa_id' => $siswa->id,
                'tanggal' => $tanggal,
                'status' => $status,
            ]);
        }

        // Hanya satu siswa yang punya catatan kebiasaan (laporan orang tua).
        Kebiasaan::create([
            'uuid' => (string) Str::uuid(),
            'siswa_id' => $a->id,
            'tanggal' => '2026-11-02',
            'diisi_oleh' => 'Orang tua',
        ]);

        $response = $this->actingAs($kepsek)->get(route('kepsek.dashboard'));
        $response->assertOk();

        $perKelas = collect($response->viewData('perKelas'))->firstWhere('kelas', '4.A');

        $this->assertNotNull($perKelas);
        $this->assertSame(2, (int) $perKelas['jumlah']);
        $this->assertSame(85.0, (float) $perKelas['rata_nilai']);
        $this->assertSame(75.0, (float) $perKelas['persen_hadir']);
        $this->assertSame(50.0, (float) $perKelas['partisipasi_ortu']);
    }

    public function test_distribusi_predikat_dan_ringkasan_tka_dihitung_benar(): void
    {
        $kepsek = User::factory()->kepsek()->create();
        $guru = User::factory()->guru()->create();
        $siswa = $this->siswaUntuk($guru, '5.A', 'K-003');

        foreach ([['A', 92.0], ['A', 88.0], ['B', 80.0], ['C', 70.0]] as $i => [$predikat, $nilai]) {
            NilaiErapot::create([
                'uuid' => (string) Str::uuid(),
                'siswa_id' => $siswa->id,
                'guru_id' => $guru->id,
                'mata_pelajaran' => 'Mapel '.$i,
                'kelas' => '5.A',
                'semester' => 'Ganjil',
                'tahun_ajaran' => '2026/2027',
                'nilai_akhir' => $nilai,
                'predikat' => $predikat,
            ]);
        }

        // Kolom dimensi.skor bertipe integer; ambang "siap" masih 2.5 seperti
        // perilaku dashboard sebelumnya.
        foreach ([4, 2, 3] as $i => $skor) {
            Dimensi::create([
                'uuid' => (string) Str::uuid(),
                'siswa_id' => $siswa->id,
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswa->nama_peserta_didik,
                'no_dimensi' => $i + 1,
                'dimensi' => 'Dimensi '.($i + 1),
                'skor' => $skor,
            ]);
        }

        $response = $this->actingAs($kepsek)->get(route('kepsek.dashboard'));
        $response->assertOk();

        $this->assertSame(
            ['A' => 2, 'B' => 1, 'C' => 1, 'D' => 0],
            array_map('intval', $response->viewData('distribusiPredikat'))
        );

        $tka = $response->viewData('tkaSekolah');
        $this->assertSame(3, (int) $tka['total']);
        $this->assertEqualsWithDelta(3.0, (float) $tka['rata_rata'], 0.001);
        $this->assertSame(2, (int) $tka['siap']);
        $this->assertSame(67, (int) $tka['persen_siap']);
    }
}
