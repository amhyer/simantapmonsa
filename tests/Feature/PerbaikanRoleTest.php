<?php

namespace Tests\Feature;

use App\Http\Controllers\Guru\TKAController;
use App\Models\Dimensi;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi perbaikan peran (menggantikan PR #1):
 *
 * 1. "Siap TKA" memakai skala skor dimensi 1–4, bukan KKM (~70).
 * 2. Akun ortu dengan banyak anak dapat memilih anak lewat ?siswa_id= dan
 *    ditolak 403 bila memilih siswa yang bukan anaknya.
 * 3. Akun siswa massal (nama_pengguna = "siswa{NIS}") menemukan data siswanya.
 *
 * Perbaikan kuis/batas_waktu dari PR #1 sengaja TIDAK diambil: `main` sudah
 * memakai semantik timestamp dan menguncinya di tests/Feature/KuisBatasWaktuTest.php.
 */
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

    private function buatDimensi(User $guru, Siswa $siswa, int $skor): void
    {
        Dimensi::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'siswa_id' => $siswa->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa->nama_peserta_didik,
            'no_dimensi' => 1,
            'dimensi' => 'Keimanan',
            'skor' => $skor,
        ]);
    }

    public function test_siap_tka_memakai_skala_skor_dimensi(): void
    {
        config(['simantap.cbt_enabled' => true]);

        $guru = User::factory()->guru()->create();
        $siap = $this->buatSiswa($guru, 'T1', 'Siap');
        $bimbingan = $this->buatSiswa($guru, 'T2', 'Bimbingan');

        // Skor 3 → Siap (>= 2.5), skor 1 → Perlu bimbingan. Dengan getKKM() (~70)
        // keduanya akan dihitung "tidak siap" sehingga jumlahSiap selalu 0.
        $this->buatDimensi($guru, $siap, 3);
        $this->buatDimensi($guru, $bimbingan, 1);

        $this->actingAs($guru)->get(route('guru.tka.index'))
            ->assertOk()
            ->assertViewHas('jumlahSiap', 1)
            ->assertViewHas('jumlahPerluBimbingan', 1)
            ->assertViewHas('skorSiap', TKAController::SKOR_SIAP)
            ->assertSee('skala 1–4', false);
    }

    public function test_ortu_dapat_memilih_anak_yang_terhubung(): void
    {
        $guru = User::factory()->guru()->create();
        $anak1 = $this->buatSiswa($guru, 'A1', 'Ani');
        $anak2 = $this->buatSiswa($guru, 'A2', 'Budi');
        $ortu = User::factory()->ortu()->create(['terhubung_dengan' => [$anak1->id, $anak2->id]]);

        $this->actingAs($ortu)->get(route('ortu.dashboard'))
            ->assertOk()
            ->assertViewHas('anak', fn ($a) => $a?->id === $anak1->id);

        $rute = [
            'ortu.dashboard' => 'anak',
            'ortu.nilai.index' => 'siswa',
            'ortu.catatan.index' => 'anak',
            'ortu.kehadiran.index' => 'anak',
            'ortu.rekap.index' => 'anak',
            'ortu.laporan.index' => 'anak',
            'ortu.kebiasaan.index' => 'siswa',
        ];

        foreach ($rute as $route => $variabel) {
            $this->actingAs($ortu)->get(route($route, ['siswa_id' => $anak2->id]))
                ->assertOk()
                ->assertViewHas($variabel, fn ($a) => $a?->id === $anak2->id)
                ->assertSee('Pilih Anak');
        }
    }

    public function test_ortu_tidak_dapat_membuka_anak_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $anak = $this->buatSiswa($guru, 'B1', 'Anak Sendiri');
        $lain = $this->buatSiswa($guru, 'B2', 'Anak Orang Lain');
        $ortu = User::factory()->ortu()->create(['terhubung_dengan' => [$anak->id]]);

        $rute = [
            'ortu.dashboard', 'ortu.nilai.index', 'ortu.catatan.index',
            'ortu.kehadiran.index', 'ortu.rekap.index', 'ortu.laporan.index',
            'ortu.kebiasaan.index',
        ];

        foreach ($rute as $route) {
            $this->actingAs($ortu)->get(route($route, ['siswa_id' => $lain->id]))
                ->assertForbidden();
        }
    }

    public function test_ortu_tanpa_anak_terhubung_tetap_mengirim_anak_list(): void
    {
        $ortu = User::factory()->ortu()->create(['terhubung_dengan' => []]);

        $rute = [
            'ortu.dashboard', 'ortu.nilai.index', 'ortu.catatan.index',
            'ortu.kehadiran.index', 'ortu.rekap.index', 'ortu.laporan.index',
        ];

        foreach ($rute as $route) {
            $this->actingAs($ortu)->get(route($route))
                ->assertOk()
                ->assertViewHas('anakList', fn ($l) => $l instanceof \Illuminate\Support\Collection && $l->isEmpty());
        }
    }

    public function test_akun_siswa_massal_menemukan_data_siswa(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = $this->buatSiswa($guru, '2026001', 'Budi');
        $akun = User::factory()->siswa()->create(['nama_pengguna' => 'siswa2026001']);

        $this->actingAs($akun)->get(route('siswa.kuis.index'))
            ->assertOk()
            ->assertViewHas('siswa', fn ($s) => $s?->id === $siswa->id);
    }

    public function test_akun_siswa_dengan_tautan_eksplisit_diutamakan(): void
    {
        $guru = User::factory()->guru()->create();
        // NIS sengaja tidak sama dengan nama_pengguna: tautan eksplisit yang menang.
        $siswa = $this->buatSiswa($guru, 'X9', 'Terhubung');
        $akun = User::factory()->siswa()->create([
            'nama_pengguna' => 'akun-tanpa-nis',
            'terhubung_dengan' => [$siswa->id],
        ]);

        $this->actingAs($akun)->get(route('siswa.kuis.index'))
            ->assertOk()
            ->assertViewHas('siswa', fn ($s) => $s?->id === $siswa->id);
    }
}
