<?php

namespace Tests\Feature;

use App\Models\Kuis;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi bug `kuis.batas_waktu`:
 *
 * Kolomnya `integer` (dilabeli "menit") tetapi di-cast `datetime` dan dipakai
 * sebagai waktu tutup — nilai 60 menit terbaca sebagai 1970-01-01 00:01:00,
 * sehingga SEMUA kuis bertimer ditolak server dan auto-submit seketika.
 *
 * Setelah perbaikan, batas_waktu adalah timestamp: kuis dengan batas waktu di
 * masa depan harus tetap bisa dikumpulkan.
 */
class KuisBatasWaktuTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Siswa, 1: User, 2: Kuis}
     */
    private function siapkanKuis(?\DateTimeInterface $batasWaktu): array
    {
        $guru = User::factory()->guru()->create();

        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'K001',
            'nisn' => '2222222222',
            'nama_peserta_didik' => 'Kiki Kuis',
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
        ]);

        $userSiswa = User::factory()->siswa()->create(['nama_pengguna' => 'K001']);

        $kuis = Kuis::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'tanggal' => now()->toDateString(),
            'judul' => 'Kuis Uji Batas Waktu',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6.A',
            'kkm' => 70,
            'batas_waktu' => $batasWaktu,
            'aktif' => true,
            'jenis' => 'Ulangan Harian',
            'cara_nilai' => 'siswa',
            'soal' => [['pertanyaan' => '2 + 2 = ?', 'kunci' => '4']],
            'jumlah_soal' => 1,
        ]);

        return [$siswa, $userSiswa, $kuis];
    }

    private function kumpulkan(User $userSiswa, Kuis $kuis)
    {
        return $this->actingAs($userSiswa)->post(route('siswa.kuis.submit', $kuis->id), [
            'jawaban' => [0 => '4'],
            'durasi' => 5,
        ]);
    }

    public function test_kuis_tanpa_batas_waktu_bisa_dikumpulkan(): void
    {
        [, $userSiswa, $kuis] = $this->siapkanKuis(null);

        $this->kumpulkan($userSiswa, $kuis)
            ->assertRedirect(route('siswa.kuis.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('hasil_kuis', 1);
        $this->assertDatabaseHas('hasil_kuis', ['kuis_id' => $kuis->id, 'skor' => 100]);
    }

    public function test_kuis_dengan_batas_waktu_masa_depan_bisa_dikumpulkan(): void
    {
        // Inilah yang sebelumnya gagal: batas_waktu 60 (menit) terbaca 1970.
        [, $userSiswa, $kuis] = $this->siapkanKuis(now()->addHour());

        $this->kumpulkan($userSiswa, $kuis)
            ->assertRedirect(route('siswa.kuis.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('hasil_kuis', 1);
    }

    public function test_kuis_yang_sudah_kedaluwarsa_ditolak_server(): void
    {
        [, $userSiswa, $kuis] = $this->siapkanKuis(now()->subDay());

        $this->kumpulkan($userSiswa, $kuis)
            ->assertRedirect(route('siswa.kuis.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('hasil_kuis', 0);
    }

    public function test_batas_waktu_disimpan_sebagai_tanggal_waktu(): void
    {
        [, , $kuis] = $this->siapkanKuis(now()->addDays(2));

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $kuis->fresh()->batas_waktu);
    }
}
