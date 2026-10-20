<?php

namespace Tests\Feature;

use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tka\Dimension;
use App\Models\Tka\EarlyWarning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman admin TKA yang sebelumnya 500 karena eager-load relasi yang tidak ada
 * (`element`, `assessment`) dan validasi status yang tidak cocok dengan enum DB.
 */
class TkaAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Siswa $siswa;
    private EarlyWarning $warning;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['simantap.cbt_enabled' => true]);

        $guru = User::factory()->guru()->create();
        $this->admin = User::factory()->admin()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $guru->id, 'nama_guru' => $guru->nama_lengkap,
            'nis' => 'TKA001', 'nama_peserta_didik' => 'Siswa TKA',
            'kelas' => '6A', 'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
        $semester = Semester::create([
            'semester_id' => '2026-1', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]);
        $dimension = Dimension::create([
            'semester_id' => $semester->id, 'kode_dimensi' => 'DIM-1',
            'nama_dimensi' => 'Religius', 'kategori' => 'religius',
            'rubrik_penilaian' => [1 => 'belum', 2 => 'sedang', 3 => 'baik', 4 => 'sangat baik'],
        ]);
        $this->warning = EarlyWarning::create([
            'siswa_id' => $this->siswa->id, 'semester_id' => $semester->id,
            'dimension_id' => $dimension->id, 'tingkat_peringatan' => 'sedang',
            'alasan_peringatan' => 'Perlu perhatian', 'rekomendasi_tindakan' => 'Pendampingan',
            'status' => 'pending',
        ]);
    }

    public function test_halaman_daftar_dan_detail_peringatan_tidak_500(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tka.warnings.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.tka.warnings.show', $this->warning))->assertOk();
    }

    public function test_update_status_memakai_enum_yang_benar(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.tka.warnings.update-status', $this->warning), ['status' => 'selesai'])
            ->assertRedirect();

        $warning = $this->warning->fresh();
        $this->assertSame('selesai', $warning->status);
        $this->assertNotNull($warning->tanggal_selesai);
    }

    public function test_status_di_luar_enum_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.tka.warnings.update-status', $this->warning), ['status' => 'resolved'])
            ->assertSessionHasErrors('status');
    }
}
