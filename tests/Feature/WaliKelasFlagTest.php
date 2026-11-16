<?php

namespace Tests\Feature;

use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaliKelasFlagTest extends TestCase
{
    use RefreshDatabase;

    private function makeSemester(): Semester
    {
        return Semester::firstOrCreate(
            ['semester_id' => '20261'],
            ['tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']
        );
    }

    private function makeRombel(string $nama, int $tingkat, int $guruId): Rombel
    {
        return Rombel::create([
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $this->makeSemester()->id,
            'nama_rombel' => $nama,
            'tingkat' => $tingkat,
            'guru_id' => $guruId,
        ]);
    }

    public function test_helper_guru_adalah_wali_kelas_aman_saat_guest(): void
    {
        $this->assertFalse(guruAdalahWaliKelas());
    }

    public function test_non_guru_selalu_bukan_wali_kelas_meski_flag_true(): void
    {
        $admin = User::factory()->admin()->create(['is_wali_kelas' => true]);
        $siswa = User::factory()->siswa()->create(['is_wali_kelas' => true]);

        $this->assertFalse($admin->isWaliKelas());
        $this->assertFalse($siswa->isWaliKelas());
    }

    public function test_flag_eksplisit_true_dan_false_mengoverride_relasi_rombel(): void
    {
        $guruEksplisitTrue = User::factory()->guru()->create(['is_wali_kelas' => true]);
        $guruEksplisitFalse = User::factory()->guru()->create(['is_wali_kelas' => false]);

        // Meski guruEksplisitFalse tercantum sebagai guru_id rombel, override false tetap berlaku.
        $this->makeRombel('Kelas 5A', 5, $guruEksplisitFalse->id);

        $this->assertTrue($guruEksplisitTrue->isWaliKelas());
        $this->assertFalse($guruEksplisitFalse->isWaliKelas());
    }

    public function test_null_flag_mengevaluasi_rombel_dan_fallback_siswa_tanpa_rombel(): void
    {
        $guruRombel = User::factory()->guru()->create(['is_wali_kelas' => null]);
        $guruFallback = User::factory()->guru()->create(['is_wali_kelas' => null]);
        $guruMapelBiasa = User::factory()->guru()->create(['is_wali_kelas' => null]);

        $this->makeRombel('Kelas 6A', 6, $guruRombel->id);

        Siswa::create([
            'guru_id' => $guruFallback->id,
            'rombel_id' => null,
            'nama_peserta_didik' => 'Siswa Fallback',
            'nisn' => '0011223344',
            'kelas' => 'Kelas 4A',
            'aktif' => true,
        ]);

        $this->assertTrue($guruRombel->isWaliKelas());
        $this->assertTrue($guruFallback->isWaliKelas());
        $this->assertFalse($guruMapelBiasa->isWaliKelas());
    }

    public function test_sidebar_guru_menampilkan_menu_wali_kelas_hanya_untuk_wali_kelas(): void
    {
        $guruMapel = User::factory()->guru()->create(['is_wali_kelas' => null]);
        $guruWalas = User::factory()->guru()->create(['is_wali_kelas' => null]);

        $this->makeRombel('Kelas 6B', 6, $guruWalas->id);

        $htmlMapel = $this->actingAs($guruMapel)->get(route('guru.dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('MENU WALI KELAS', $htmlMapel);
        $this->assertStringContainsString('Kelas Digital', $htmlMapel);

        $htmlWalas = $this->actingAs($guruWalas)->get(route('guru.dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('MENU WALI KELAS', $htmlWalas);
        $this->assertStringContainsString('Kelas Digital', $htmlWalas);
    }
}
