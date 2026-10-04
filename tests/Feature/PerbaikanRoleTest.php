<?php

namespace Tests\Feature;

use App\Models\Dimensi;
use App\Models\HasilKuis;
use App\Models\Kuis;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerbaikanRoleTest extends TestCase
{
    use RefreshDatabase;

    private function buatSiswa(User $guru, string $nis, string $nama): Siswa
    {
        return Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => $nis,
            'nama_peserta_didik' => $nama,
            'kelas' => '1.A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
    }

    private function buatKuis(User $guru, int $menit): Kuis
    {
        return Kuis::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'tanggal' => now()->toDateString(),
            'judul' => 'Kuis Pecahan',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '1.A',
            'kkm' => 70,
            'batas_waktu' => $menit,
            'aktif' => true,
            'jumlah_soal' => 1,
            'soal' => [['pertanyaan' => '1/2 + 1/2 = ?', 'pilihan' => ['A' => '1', 'B' => '2'], 'kunci' => 'A']],
        ]);
    }

    public function test_siap_tka_memakai_skala_skor_dimensi(): void
    {
        $guru = User::factory()->guru()->create();
        $siap = $this->buatSiswa($guru, 'T1', 'Siap');
        $bimbingan = $this->buatSiswa($guru, 'T2', 'Bimbingan');

        foreach ([[$siap, 3], [$bimbingan, 1]] as [$siswa, $skor]) {
            Dimensi::create([
                'uuid' => (string) Str::uuid(), 'guru_id' => $guru->id, 'siswa_id' => $siswa->id,
                'nama_guru' => $guru->nama_lengkap, 'nama_siswa' => $siswa->nama_peserta_didik,
                'no_dimensi' => 1, 'dimensi' => 'Keimanan', 'skor' => $skor,
            ]);
        }

        $this->actingAs($guru)->get(route('guru.tka.index'))
            ->assertOk()
            ->assertViewHas('jumlahSiap', 1)
            ->assertViewHas('jumlahPerluBimbingan', 1);
    }

    public function test_ortu_dapat_memilih_anak_yang_terhubung(): void
    {
        $guru = User::factory()->guru()->create();
        $anak1 = $this->buatSiswa($guru, 'A1', 'Ani');
        $anak2 = $this->buatSiswa($guru, 'A2', 'Budi');
        $ortu = User::factory()->ortu()->create(['terhubung_dengan' => [$anak1->id, $anak2->id]]);

        $this->actingAs($ortu)->get(route('ortu.dashboard'))
            ->assertOk()->assertViewHas('anak', fn ($a) => $a->id === $anak1->id);

        foreach (['ortu.dashboard' => 'anak', 'ortu.nilai.index' => 'siswa', 'ortu.catatan.index' => 'anak',
            'ortu.kehadiran.index' => 'anak', 'ortu.rekap.index' => 'anak', 'ortu.laporan.index' => 'anak'] as $route => $var) {
            $this->actingAs($ortu)->get(route($route, ['siswa_id' => $anak2->id]))
                ->assertOk()
                ->assertViewHas($var, fn ($a) => $a->id === $anak2->id)
                ->assertSee('Pilih Anak');
        }
    }

    public function test_ortu_tidak_dapat_membuka_anak_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $anak = $this->buatSiswa($guru, 'B1', 'Anak Sendiri');
        $lain = $this->buatSiswa($guru, 'B2', 'Anak Orang Lain');
        $ortu = User::factory()->ortu()->create(['terhubung_dengan' => [$anak->id]]);

        foreach (['ortu.dashboard', 'ortu.nilai.index', 'ortu.catatan.index', 'ortu.kehadiran.index', 'ortu.rekap.index', 'ortu.laporan.index'] as $route) {
            $this->actingAs($ortu)->get(route($route, ['siswa_id' => $lain->id]))->assertForbidden();
        }
    }

    public function test_akun_siswa_massal_menemukan_data_siswa(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = $this->buatSiswa($guru, '2026001', 'Budi');
        $akun = User::factory()->siswa()->create(['nama_pengguna' => 'siswa2026001']);

        $this->actingAs($akun)->get(route('siswa.kuis.index'))
            ->assertOk()->assertViewHas('siswa', fn ($s) => $s?->id === $siswa->id);
    }

    public function test_kuis_menampilkan_durasi_dan_menolak_kiriman_lewat_waktu(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = $this->buatSiswa($guru, 'K1', 'Citra');
        $akun = User::factory()->siswa()->create(['nama_pengguna' => 'K1']);
        $kuis = $this->buatKuis($guru, 10);

        $this->actingAs($akun)->get(route('siswa.kuis.index'))->assertOk()->assertSee('10 menit');
        $this->actingAs($akun)->get(route('siswa.kuis.show', $kuis->id))
            ->assertOk()->assertViewHas('sisaDetik', 600);

        $this->travel(12)->minutes();

        $this->actingAs($akun)->get(route('siswa.kuis.show', $kuis->id))->assertViewHas('sisaDetik', 0);
        $this->actingAs($akun)->post(route('siswa.kuis.submit', $kuis->id), ['jawaban' => ['A'], 'durasi' => 30])
            ->assertRedirect(route('siswa.kuis.index'))->assertSessionHas('error');
        $this->assertDatabaseMissing('hasil_kuis', ['kuis_id' => $kuis->id, 'siswa_id' => $siswa->id]);
    }

    public function test_kuis_tepat_waktu_tersimpan_dengan_durasi_server(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = $this->buatSiswa($guru, 'K2', 'Dodi');
        $akun = User::factory()->siswa()->create(['nama_pengguna' => 'K2']);
        $kuis = $this->buatKuis($guru, 10);

        $this->actingAs($akun)->get(route('siswa.kuis.show', $kuis->id))->assertOk();
        $this->travel(5)->minutes();

        $this->actingAs($akun)->post(route('siswa.kuis.submit', $kuis->id), ['jawaban' => ['A'], 'durasi' => 1])
            ->assertSessionHas('success');

        $hasil = HasilKuis::where('kuis_id', $kuis->id)->where('siswa_id', $siswa->id)->firstOrFail();
        $this->assertSame(100, $hasil->skor);
        $this->assertEqualsWithDelta(300, $hasil->durasi, 2);
    }
}
