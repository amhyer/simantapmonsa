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

class MenuAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_semua_menu(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $kepsek = User::factory()->kepsek()->create();
        $ortu = User::factory()->ortu()->create();

        $semester = Semester::create([
            'semester_id' => '20251', 'tahun_ajaran' => '2025/2026', 'nama_semester' => 'ganjil',
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

        $map = [
            'admin' => [$admin, [
                'admin.dashboard', 'admin.profile', 'admin.dapodik.index',
                'admin.users.index', 'admin.sekolah.index', 'admin.referensi.guru',
                'admin.users.siswa', 'admin.peta-kelas.index', 'admin.mapel.index',
                'admin.referensi.pembelajaran', 'admin.referensi.ekstrakurikuler',
                'admin.referensi.kelompok-mapel', 'admin.referensi.mapping-rapor',
                'admin.referensi.logo-ttd', 'admin.referensi.tanggal-rapor',
                'admin.referensi.foto-siswa', 'admin.kokurikuler.tema',
                'admin.kokurikuler.kegiatan', 'admin.kokurikuler.kelompok',
                'admin.penilaian.status', 'admin.penilaian.statistik',
                'admin.dapodik.push.index', 'admin.semester.index',
                'admin.kode-akses.index', 'admin.modul.index', 'admin.bobot.index',
                'admin.sheet.index', 'admin.backup.index', 'admin.api-keys.index',
                'admin.log.index',
            ]],
            'guru' => [$guru, [
                'guru.dashboard', 'guru.materi.index', 'guru.dimensi.index',
                'guru.tka.index', 'guru.input-nilai.index', 'guru.nilai-erapor.index',
                'guru.kuis.index', 'guru.nilai.index', 'guru.analisis.index',
                'guru.kehadiran.index', 'guru.catatan.index', 'guru.kebiasaan.index',
                'guru.laporan.index', 'guru.erapor.index', 'guru.siswa.index',
                'guru.integrasi.index', 'guru.pengaturan.index',
            ]],
            'siswa' => [$siswaUser, [
                'siswa.dashboard', 'siswa.materi.index', 'siswa.kuis.index',
                'siswa.nilai.index', 'siswa.profil.show',
            ]],
            'ortu' => [$ortu, [
                'ortu.dashboard', 'ortu.kebiasaan.index', 'ortu.rekap.index',
                'ortu.nilai.index', 'ortu.kehadiran.index', 'ortu.catatan.index',
                'ortu.laporan.index',
            ]],
            'kepsek' => [$kepsek, [
                'kepsek.dashboard', 'kepsek.rekap.index', 'kepsek.peta-kelas.index',
                'kepsek.hasil-belajar.index', 'kepsek.pantau.index',
                'kepsek.kebiasaan.index', 'kepsek.aktivitas.index',
            ]],
        ];

        $rows = [];
        $errors = [];
        foreach ($map as $role => [$user, $routes]) {
            foreach ($routes as $name) {
                try {
                    $res = $this->actingAs($user)->get(route($name));
                    $status = $res->getStatusCode();
                    $html = $res->getContent() ?: '';
                    $bodyRows = 0;
                    if (preg_match_all('/<tbody[^>]*>(.*?)<\/tbody>/s', $html, $m)) {
                        foreach ($m[1] as $tb) {
                            $bodyRows += substr_count($tb, '<tr');
                        }
                    }
                    $empty = preg_match('/empty-state|Belum ada data|belum ada data|Tidak ada data|belum tersedia/i', $html) ? 'YA' : '-';
                    $rows[] = [$role, $name, $status, strlen($html), $bodyRows, $empty];
                    if ($status >= 500) {
                        $errors[] = "{$role} {$name} => HTTP {$status}";
                    }
                } catch (\Throwable $e) {
                    $rows[] = [$role, $name, 'EXC', 0, 0, '-'];
                    $errors[] = "{$role} {$name} => " . get_class($e) . ': ' . substr($e->getMessage(), 0, 200);
                }
            }
        }

        $out = "role | route | http | bytes | baris_tbody | tanda_kosong\n";
        foreach ($rows as $r) {
            $out .= implode(' | ', $r) . "\n";
        }
        if ($errors) {
            $out .= "\nERRORS:\n" . implode("\n", $errors) . "\n";
        }
        file_put_contents('C:/Users/USER/AppData/Local/Temp/opencode/menu_audit.txt', $out);

        $this->assertSame([], $errors, 'Ada halaman error: ' . implode('; ', $errors));
    }

    /**
     * Regresi: rekap bulanan dashboard siswa harus dikelompokkan per bulan
     * (dulu memakai to_char() khusus PostgreSQL sehingga error di sqlite).
     */
    public function test_dashboard_siswa_menampilkan_rekap_bulanan(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(), 'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap, 'nis' => 'S001',
            'nama_peserta_didik' => 'Ani Contoh', 'kelas' => '1.A', 'jenis_kelamin' => 'P',
        ]);
        $su = User::factory()->siswa()->create(['nama_pengguna' => 'S001']);
        $tahun = date('Y');
        Kehadiran::create(['guru_id' => $guru->id, 'siswa_id' => $siswa->id, 'tanggal' => "{$tahun}-01-05", 'status' => 'H']);
        Kehadiran::create(['guru_id' => $guru->id, 'siswa_id' => $siswa->id, 'tanggal' => "{$tahun}-01-06", 'status' => 'S']);
        Kehadiran::create(['guru_id' => $guru->id, 'siswa_id' => $siswa->id, 'tanggal' => "{$tahun}-02-05", 'status' => 'H']);

        $res = $this->actingAs($su)->get(route('siswa.dashboard'))->assertOk();
        $data = $res->viewData('kehadiranBulanan');

        $this->assertCount(2, $data);
        $this->assertSame('January', $data[0]['nama']);
        $this->assertSame(1, $data[0]['H']);
        $this->assertSame(1, $data[0]['S']);
        $this->assertSame('February', $data[1]['nama']);
        $this->assertSame(1, $data[1]['H']);
    }
}
