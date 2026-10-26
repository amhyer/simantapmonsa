<?php

namespace Tests\Feature;

use App\Models\Kehadiran;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CriticalRoutesRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_ortu_tanpa_anak_tetap_merender_pesan_yang_aman(): void
    {
        $ortu = User::factory()->ortu()->create(['terhubung_dengan' => []]);

        $this->actingAs($ortu)
            ->get(route('ortu.dashboard'))
            ->assertOk()
            ->assertSee('Akun Anda belum terhubung dengan data siswa. Hubungi admin.');
    }

    public function test_export_erapor_excel_berhasil_tanpa_pengaturan_dan_dengan_siswa(): void
    {
        $guru = User::factory()->guru()->create();
        Siswa::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'ERAPOR-NO-SETTINGS',
            'nama_peserta_didik' => 'Siswa Tanpa Pengaturan',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $response = $this->actingAs($guru)->get(route('guru.erapor.excel'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_edit_assessment_yang_tidak_didukung_tidak_melempar_500(): void
    {
        $this->get('/tka/assessments/1/edit')->assertNotFound();
    }

    public function test_view_yang_pernah_dilaporkan_hilang_semuanya_tersedia(): void
    {
        $views = [
            'admin.cbt.banks.show',
            'admin.cbt.banks.edit',
            'admin.cbt.sessions.show',
            'cbt.sessions.show',
            'admin.tryout.packages.show',
            'admin.tryout.packages.edit',
            'admin.tryout.sessions.edit',
            'tryout.packages.show',
            'tryout.packages.edit',
            'tryout.sessions.show',
            'tryout.sessions.monitoring',
            'tka.assessments.by-siswa',
            'admin.tka.students.history',
        ];

        foreach ($views as $view) {
            $this->assertTrue(view()->exists($view), "View {$view} harus tersedia.");
        }
    }

    public function test_laporan_guru_memakai_agregat_kehadiran_tanpa_query_per_siswa(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = $this->buatBanyakSiswa($guru, 12);
        Kehadiran::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'guru_id' => $guru->id,
            'siswa_id' => $siswa[0]->id,
            'tanggal' => now()->toDateString(),
            'status' => 'H',
        ]);
        Kehadiran::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'guru_id' => $guru->id,
            'siswa_id' => $siswa[0]->id,
            'tanggal' => now()->subDay()->toDateString(),
            'status' => 'S',
        ]);

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $this->actingAs($guru)
            ->get(route('guru.laporan.index'))
            ->assertOk()
            ->assertSee('50%');

        $this->assertSame([], $this->individualAttendanceCountQueries($connection->getQueryLog()));
    }

    public function test_pdf_laporan_guru_berhasil_tanpa_pengaturan_dan_tanpa_query_kehadiran_n_plus_satu(): void
    {
        $guru = User::factory()->guru()->create();
        $this->buatBanyakSiswa($guru, 12);

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $response = $this->actingAs($guru)->get(route('guru.laporan.pdf'));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame([], $this->individualAttendanceCountQueries($connection->getQueryLog()));
    }

    /** @return array<int, Siswa> */
    private function buatBanyakSiswa(User $guru, int $jumlah): array
    {
        $siswa = [];

        for ($i = 1; $i <= $jumlah; $i++) {
            $siswa[] = Siswa::create([
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nis' => 'PERF'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nama_peserta_didik' => 'Siswa Perf '.$i,
                'kelas' => '6A',
                'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                'aktif' => true,
            ]);
        }

        return $siswa;
    }

    /** @param array<int, array{query: string, bindings: array, time: float}> $queries
     *  @return array<int, array{query: string, bindings: array, time: float}>
     */
    private function individualAttendanceCountQueries(array $queries): array
    {
        return array_values(array_filter($queries, static function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'count(*) as aggregate')
                && str_contains($sql, 'kehadiran')
                && preg_match('/where.*siswa_id.*=\s*\?/', $sql) === 1;
        }));
    }
}
