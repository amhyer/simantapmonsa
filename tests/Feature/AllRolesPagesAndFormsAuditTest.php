<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AllRolesPagesAndFormsAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private User $siswaUser;
    private User $ortu;
    private User $kepsek;
    private User $pegawai;
    private Siswa $siswa;
    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        config(['simantap.cbt_enabled' => true]);

        $this->admin = User::factory()->admin()->create();
        $this->guru = User::factory()->guru()->create();
        $this->kepsek = User::factory()->kepsek()->create();
        $this->ortu = User::factory()->ortu()->create();
        $this->pegawai = User::factory()->create(['peran' => 'tendik']);

        $this->semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $rombel = Rombel::create([
            'dapodik_id' => 'RB-AUDIT-1',
            'semester_id' => $this->semester->id,
            'nama_rombel' => 'Kelas 3A',
            'tingkat' => '3',
            'guru_id' => $this->guru->id,
            'wali_kelas_id' => $this->guru->id,
        ]);

        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true]);

        $this->siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $this->guru->id,
            'rombel_id' => $rombel->id,
            'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'NIS001',
            'nama_peserta_didik' => 'Siswa Audit',
            'kelas' => 'Kelas 3A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $this->siswaUser = User::factory()->siswa()->create(['nama_pengguna' => 'NIS001']);
        $this->ortu->update(['terhubung_dengan' => [$this->siswa->id]]);
    }

    public function test_audit_admin_pages(): void
    {
        $routes = [
            'admin.dashboard',
            'admin.profile',
            'admin.users.index',
            'admin.users.siswa',
            'admin.referensi.guru',
            'admin.sekolah.index',
            'admin.semester.index',
            'admin.mapel.index',
            'admin.referensi.pembelajaran',
            'admin.peta-kelas.daftar',
            'admin.dapodik.index',
            'admin.dapodik.push.index',
            'admin.kode-akses.index',
            'admin.presensi.index',
            'admin.presensi.kartu.index',
            'admin.kokurikuler.tema',
            'admin.kokurikuler.kegiatan',
            'admin.kokurikuler.kelompok',
            'admin.modul.index',
            'admin.bobot.index',
            'admin.sheet.index',
            'admin.backup.index',
            'admin.log.index',
            'admin.penilaian.status',
            'admin.penilaian.statistik',
            'admin.transkrip.setting',
            'admin.transkrip.nomor',
            'admin.transkrip.input',
            'admin.transkrip.import',
            'admin.transkrip.cetak',
            'admin.cetak.leger',
            'admin.cetak.pelengkap',
            'admin.cetak.nilai',
            'admin.cbt.sessions.index',
            'admin.cbt.banks.index',
            'admin.tryout.statistics',
            'admin.tryout.packages.index',
            'admin.tryout.sessions.index',
            'admin.tryout.results.index',
            'admin.tka.dashboard',
            'admin.tka.dimensions.index',
            'admin.tka.assessments.index',
            'admin.tka.warnings.index',
        ];

        foreach ($routes as $route) {
            $res = $this->actingAs($this->admin)->get(route($route));
            $this->assertLessThan(500, $res->getStatusCode(), "Admin route '$route' returned HTTP " . $res->getStatusCode());
        }
    }

    public function test_audit_guru_pages(): void
    {
        $routes = [
            'guru.dashboard',
            'guru.materi.index',
            'guru.dimensi.index',
            'guru.tka.index',
            'guru.tka.analysis',
            'guru.input-nilai.index',
            'guru.nilai-erapor.index',
            'guru.kuis.index',
            'guru.nilai.index',
            'guru.analisis.index',
            'guru.kehadiran.index',
            'guru.catatan.index',
            'guru.kebiasaan.index',
            'guru.laporan.index',
            'guru.erapor.index',
            'guru.siswa.index',
            'guru.integrasi.index',
            'guru.pengaturan.index',
            'cbt.sessions.index',
            'cbt.question-banks.index',
            'tryout.packages.index',
            'tryout.sessions.index',
            'tka.dimensions.index',
            'tka.assessments.index',
        ];

        foreach ($routes as $route) {
            $res = $this->actingAs($this->guru)->get(route($route));
            $this->assertLessThan(500, $res->getStatusCode(), "Guru route '$route' returned HTTP " . $res->getStatusCode());
        }
    }

    public function test_audit_siswa_pages(): void
    {
        $routes = [
            'siswa.dashboard',
            'siswa.materi.index',
            'siswa.kuis.index',
            'siswa.nilai.index',
            'siswa.profil.show',
            'cbt.student.index',
            'tryout.student.index',
            'siswa.tka-simulasi.index',
        ];

        foreach ($routes as $route) {
            $res = $this->actingAs($this->siswaUser)->get(route($route));
            $this->assertLessThan(500, $res->getStatusCode(), "Siswa route '$route' returned HTTP " . $res->getStatusCode());
        }
    }

    public function test_audit_ortu_pages(): void
    {
        $routes = [
            'ortu.dashboard',
            'ortu.kebiasaan.index',
            'ortu.rekap.index',
            'ortu.nilai.index',
            'ortu.kehadiran.index',
            'ortu.catatan.index',
            'ortu.laporan.index',
        ];

        foreach ($routes as $route) {
            $res = $this->actingAs($this->ortu)->get(route($route));
            $this->assertLessThan(500, $res->getStatusCode(), "Ortu route '$route' returned HTTP " . $res->getStatusCode());
        }
    }

    public function test_audit_kepsek_pages(): void
    {
        $routes = [
            'kepsek.dashboard',
            'kepsek.rekap.index',
            'kepsek.peta-kelas.index',
            'kepsek.hasil-belajar.index',
            'kepsek.pantau.index',
            'kepsek.kebiasaan.index',
            'kepsek.aktivitas.index',
        ];

        foreach ($routes as $route) {
            $res = $this->actingAs($this->kepsek)->get(route($route));
            $this->assertLessThan(500, $res->getStatusCode(), "Kepsek route '$route' returned HTTP " . $res->getStatusCode());
        }
    }

    public function test_audit_pegawai_pages(): void
    {
        $routes = [
            'presensi.pegawai.index',
        ];

        foreach ($routes as $route) {
            $res = $this->actingAs($this->pegawai)->get(route($route));
            $this->assertLessThan(500, $res->getStatusCode(), "Pegawai route '$route' returned HTTP " . $res->getStatusCode());
        }
    }
}
