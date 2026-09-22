<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    #[\PHPUnit\Framework\Attributes\DataProvider('halamanProvider')]
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
}
