<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\NilaiErapot;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Services\JadwalPelajaranResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

// Fase 6.2: feature permanen untuk alignment e-Rapor <-> pembelajaran.
class AlignmentTest extends TestCase
{
    use RefreshDatabase;

    private function seedDasar(User $guru): array
    {
        $semester = Semester::create([
            'semester_id' => '20251', 'tahun_ajaran' => '2025/2026', 'nama_semester' => 'ganjil',
        ]);
        $jadwal = JadwalPelajaran::create([
            'guru_id' => $guru->id, 'semester_id' => $semester->id,
            'kelas' => '6.A', 'mata_pelajaran' => 'Matematika',
            'hari' => 'Senin', 'jam_mulai' => '07:00', 'jam_selesai' => '08:00',
        ]);
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(), 'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap, 'nis' => 'AL' . $guru->id,
            'nama_peserta_didik' => 'Siswa Align', 'kelas' => '6.A', 'jenis_kelamin' => 'L',
        ]);

        return [$semester, $jadwal, $siswa];
    }

    private function buatNilai(Siswa $siswa, User $guru, string $mapel = 'Matematika', string $kelas = '6.A'): NilaiErapot
    {
        return NilaiErapot::create([
            'siswa_id' => $siswa->id, 'guru_id' => $guru->id,
            'mata_pelajaran' => $mapel, 'kelas' => $kelas,
            'semester' => 'Ganjil', 'tahun_ajaran' => '2025/2026',
            'nilai_akhir' => 80, 'predikat' => 'B',
        ]);
    }

    public function test_resolver_memetakan_dan_menolak(): void
    {
        $guru = User::factory()->guru()->create(['aktif' => true]);
        [, $jadwal] = $this->seedDasar($guru);
        $r = new JadwalPelajaranResolver();

        $this->assertSame($jadwal->id, $r->resolve($guru->id, 'Matematika', '6.A', 'Ganjil', '2025/2026'));
        $this->assertSame($jadwal->id, $r->resolve($guru->id, '  MATEMATIKA ', '6.a', 'ganjil', '2025/2026'));
        $this->assertNull($r->resolve($guru->id, 'Umum', '6.A', 'Ganjil', '2025/2026'));
        $this->assertNull($r->resolve($guru->id, 'Matematika', '6.A', 'Genap', '2025/2026'));
    }

    public function test_jadwal_ambigu_dimenangkan_id_terkecil(): void
    {
        $guru = User::factory()->guru()->create(['aktif' => true]);
        [$semester, $jadwal] = $this->seedDasar($guru);
        JadwalPelajaran::create([
            'guru_id' => $guru->id, 'semester_id' => $semester->id,
            'kelas' => '6.A', 'mata_pelajaran' => 'Matematika',
            'hari' => 'Selasa', 'jam_mulai' => '07:00', 'jam_selesai' => '08:00',
        ]);

        $this->assertSame(
            $jadwal->id,
            (new JadwalPelajaranResolver())->resolve($guru->id, 'Matematika', '6.A', 'Ganjil', '2025/2026')
        );
    }

    public function test_simpan_dan_autosave_mengisi_fk(): void
    {
        $guru = User::factory()->guru()->create(['aktif' => true]);
        [, $jadwal, $siswa] = $this->seedDasar($guru);

        $this->actingAs($guru)->postJson(route('guru.nilai-erapor.simpan'), [
            'kelas' => '6.A', 'mapel' => 'Matematika',
            'semester' => 'Ganjil', 'tahun_ajaran' => '2025/2026',
            'nilai' => [['siswa_id' => $siswa->id, 'formatif' => 80, 'sumatif' => 85, 'sumatif_akhir' => 90]],
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertSame($jadwal->id, (int) NilaiErapot::first()->jadwal_pelajaran_id);

        $this->actingAs($guru)->postJson(route('guru.nilai-erapor.auto-save'), [
            'siswa_id' => $siswa->id, 'field' => 'formatif', 'value' => 82,
            'kelas' => '6.A', 'mapel' => 'Matematika',
            'semester' => 'Ganjil', 'tahun_ajaran' => '2025/2026',
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertSame($jadwal->id, (int) NilaiErapot::first()->jadwal_pelajaran_id);
    }

    public function test_admin_dapat_mencocokkan_ulang_nilai_yang_belum_terpetakan(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['aktif' => true]);
        [, $jadwal, $siswa] = $this->seedDasar($guru);
        $bisaCocok = $this->buatNilai($siswa, $guru, 'Matematika');
        $tetapBelumCocok = $this->buatNilai($siswa, $guru, 'Fisika');

        $this->actingAs($admin)
            ->post(route('admin.referensi.alignment.refresh'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($jadwal->id, (int) $bisaCocok->fresh()->jadwal_pelajaran_id);
        $this->assertNull($tetapBelumCocok->fresh()->jadwal_pelajaran_id);
    }

    public function test_admin_dapat_mengunduh_semua_nilai_belum_terpetakan(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['aktif' => true]);
        [, , $siswa] = $this->seedDasar($guru);
        $this->buatNilai($siswa, $guru, 'Mapel Belum Terpetakan');

        $this->actingAs($admin)
            ->get(route('admin.referensi.alignment.export'))
            ->assertOk()
            ->assertDownload('nilai-erapor-unmatched-' . now()->format('Y-m-d') . '.csv');
    }

    public function test_halaman_alignment_dan_badge_pembelajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['aktif' => true]);
        [, $jadwal, $siswa] = $this->seedDasar($guru);
        $this->buatNilai($siswa, $guru, 'Matematika')->update(['jadwal_pelajaran_id' => $jadwal->id]);

        $siswa2 = Siswa::create([
            'uuid' => (string) Str::uuid(), 'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap, 'nis' => 'ALX',
            'nama_peserta_didik' => 'Siswa Umum', 'kelas' => '6.A', 'jenis_kelamin' => 'P',
        ]);
        $this->buatNilai($siswa2, $guru, 'Umum');

        $this->actingAs($admin)->get(route('admin.referensi.alignment'))
            ->assertOk()
            ->assertSee('Cek Alignment E-Rapor', false)
            ->assertSee('Belum terpetakan', false)
            ->assertDontSee('Nama mapel tak cocok');

        $this->actingAs($admin)->get(route('admin.referensi.pembelajaran'))
            ->assertOk()
            ->assertSee('1 terpetakan', false);
    }

    public function test_hapus_jadwal_menullkan_fk_bukan_hapus_nilai(): void
    {
        $guru = User::factory()->guru()->create(['aktif' => true]);
        [, $jadwal, $siswa] = $this->seedDasar($guru);
        $nilai = $this->buatNilai($siswa, $guru);
        $nilai->update(['jadwal_pelajaran_id' => $jadwal->id]);

        $jadwal->delete();

        $this->assertNull($nilai->fresh()->jadwal_pelajaran_id);
        $this->assertDatabaseHas('nilai_erapor', ['id' => $nilai->id]);
    }

    public function test_migrasi_fk_rollback_dan_maju_lagi(): void
    {
        $file = database_path('migrations/2026_10_04_202238_add_jadwal_pelajaran_id_to_nilai_erapor_table.php');
        $this->assertFileExists($file);

        $migrasi = require $file;
        $migrasi->down();
        $this->assertFalse(Schema::hasColumn('nilai_erapor', 'jadwal_pelajaran_id'));

        $migrasi->up();
        $this->assertTrue(Schema::hasColumn('nilai_erapor', 'jadwal_pelajaran_id'));
    }
}
