<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Kehadiran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SidebarCoverageAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_seluruh_menu_sidebar_semua_role_bebas_error_500(): void
    {
        config(['simantap.cbt_enabled' => true]);

        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $kepsek = User::factory()->kepsek()->create();
        $ortu = User::factory()->ortu()->create();
        $pegawai = User::factory()->create(['peran' => 'tendik']);

        $semester = Semester::create([
            'semester_id' => '20251',
            'tahun_ajaran' => '2025/2026',
            'nama_semester' => 'ganjil',
        ]);
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true]);
        
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'S001',
            'nama_peserta_didik' => 'Ani Contoh',
            'kelas' => '1.A',
            'jenis_kelamin' => 'P',
        ]);
        $siswaUser = User::factory()->siswa()->create(['nama_pengguna' => 'S001']);
        $ortu->update(['terhubung_dengan' => [$siswa->id]]);

        $files = [
            'admin' => [$admin, base_path('resources/views/admin/partials/sidebar.blade.php')],
            'guru' => [$guru, base_path('resources/views/guru/partials/sidebar.blade.php')],
            'siswa' => [$siswaUser, base_path('resources/views/siswa/partials/sidebar.blade.php')],
            'ortu' => [$ortu, base_path('resources/views/ortu/partials/sidebar.blade.php')],
            'kepsek' => [$kepsek, base_path('resources/views/kepsek/partials/sidebar.blade.php')],
            'pegawai' => [$pegawai, base_path('resources/views/pegawai/partials/sidebar.blade.php')],
        ];

        $errors = [];
        $testedCount = 0;

        foreach ($files as $role => [$user, $filePath]) {
            $content = file_get_contents($filePath);
            preg_match_all("/'route'\s*=>\s*'([^']+)'/", $content, $m1);
            preg_match_all("/route\('([^']+)'/", $content, $m2);
            $routes = array_unique(array_merge($m1[1] ?? [], $m2[1] ?? []));

            foreach ($routes as $route) {
                $testedCount++;
                try {
                    $res = $this->actingAs($user)->get(route($route));
                    $status = $res->getStatusCode();
                    if ($status >= 500) {
                        $errors[] = "[$role] route '$route' return HTTP $status";
                    }
                } catch (\Throwable $e) {
                    $errors[] = "[$role] route '$route' EXCEPTION: " . $e->getMessage();
                }
            }
        }

        $this->assertEmpty($errors, "Ditemukan error pada menu sidebar:\n" . implode("\n", $errors));
        $this->assertGreaterThan(70, $testedCount);
    }
}
