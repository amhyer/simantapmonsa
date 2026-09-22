<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MataPelajaran;
use App\Models\JadwalPelajaran;
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

    public function test_mapel_meta_menyimpan_flag_transkrip(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'aktif' => true]);

        $this->actingAs($admin)->put(route('admin.referensi.mapel-meta.update'), [
            'mapel' => [$mapel->id => ['kelompok' => 'A', 'urutan' => 1, 'masuk_transkrip' => 1]],
        ])->assertRedirect();

        $this->assertTrue($mapel->fresh()->masuk_transkrip);

        $this->actingAs($admin)->put(route('admin.referensi.mapel-meta.update'), [
            'mapel' => [$mapel->id => ['kelompok' => 'A', 'urutan' => 1, 'masuk_transkrip' => 0]],
        ])->assertRedirect();

        $this->assertFalse($mapel->fresh()->masuk_transkrip);
    }

    public function test_admin_dapat_menambah_pembelajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();

        $this->actingAs($admin)->post(route('admin.referensi.pembelajaran.store'), [
            'hari' => 'Senin',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 1.A',
            'guru_id' => $guru->id,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'hari' => 'Senin',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 1.A',
        ]);
    }

    public function test_tambah_pembelajaran_memvalidasi_input(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.referensi.pembelajaran.store'), [
            'hari' => 'Ahad',
        ])->assertSessionHasErrors(['hari', 'mata_pelajaran', 'kelas']);
    }

    public function test_admin_dapat_menghapus_pembelajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $jadwal = JadwalPelajaran::create([
            'hari' => 'Selasa',
            'mata_pelajaran' => 'IPA',
            'kelas' => 'Kelas 2.B',
            'guru_id' => $guru->id,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:00',
        ]);

        $this->actingAs($admin)->delete(route('admin.referensi.pembelajaran.destroy', $jadwal))
            ->assertRedirect();

        $this->assertDatabaseMissing('jadwal_pelajaran', ['id' => $jadwal->id]);
    }

    public function test_non_admin_ditolak_mengubah_pembelajaran(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->post(route('admin.referensi.pembelajaran.store'), [
            'hari' => 'Senin',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 1.A',
        ])->assertForbidden();
    }
}
