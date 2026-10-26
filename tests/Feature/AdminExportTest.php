<?php

namespace Tests\Feature;

use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tka\Dimension;
use App\Models\Tka\EarlyWarning;
use App\Models\Tka\StudentAssessment;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private Siswa $siswa;
    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['simantap.cbt_enabled' => true]);

        $this->admin = User::factory()->admin()->create();
        $this->guru = User::factory()->guru()->create();
        $this->semester = Semester::create([
            'semester_id' => '2026-1',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id,
            'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'EXP001',
            'nisn' => '0099001100',
            'nama_peserta_didik' => 'Siswa Ekspor',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
    }

    public function test_tryout_results_download_xlsx_and_respects_filters(): void
    {
        $result = $this->createTryoutResult('Paket A', true);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.tryout.results.export', [
                'package_id' => $result->session->package_id,
                'session_id' => $result->tryout_session_id,
                'lulus' => 'true',
            ]))
            ->assertOk()
            ->assertDownload('hasil-tryout-' . now()->format('Y-m-d') . '.xlsx');

        $xlsx = $response->streamedContent();
        $this->assertStringStartsWith('PK', $xlsx, 'Ekspor hasil Tryout harus berupa berkas XLSX yang valid.');
        $this->assertGreaterThan(100, strlen($xlsx));
    }

    public function test_tryout_export_reports_no_matching_rows_without_server_error(): void
    {
        $result = $this->createTryoutResult('Paket Kosong', true);

        $this->actingAs($this->admin)
            ->get(route('admin.tryout.results.export', [
                'session_id' => $result->tryout_session_id,
                'lulus' => 'false',
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Tidak ada hasil tryout yang cocok untuk diekspor.');
    }

    public function test_tka_bulk_report_downloads_excel_and_pdf(): void
    {
        $this->createAssessment();

        $excelResponse = $this->actingAs($this->admin)
            ->post(route('admin.tka.generate-report'), [
                'semester_id' => $this->semester->id,
                'format' => 'excel',
            ])
            ->assertOk()
            ->assertDownload('laporan-tka-semester-' . $this->semester->id . '-' . now()->format('Y-m-d') . '.xlsx');
        $this->assertStringStartsWith('PK', $excelResponse->streamedContent());

        $pdfResponse = $this->actingAs($this->admin)
            ->post(route('admin.tka.generate-report'), [
                'semester_id' => $this->semester->id,
                'format' => 'pdf',
            ])
            ->assertOk()
            ->assertDownload('laporan-tka-semester-' . $this->semester->id . '-' . now()->format('Y-m-d') . '.pdf');
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
    }

    public function test_tka_data_export_supports_assessments_warnings_and_dimensions(): void
    {
        $this->createAssessment();
        $dimension = Dimension::create([
            'semester_id' => $this->semester->id,
            'kode_dimensi' => 'EXP-DIM',
            'nama_dimensi' => 'Mandiri',
            'kategori' => 'mandiri',
            'rubrik_penilaian' => [1 => 'Belum', 2 => 'Mulai', 3 => 'Baik', 4 => 'Sangat baik'],
        ]);
        EarlyWarning::create([
            'siswa_id' => $this->siswa->id,
            'semester_id' => $this->semester->id,
            'dimension_id' => $dimension->id,
            'tingkat_peringatan' => 'sedang',
            'alasan_peringatan' => 'Perlu pendampingan',
            'rekomendasi_tindakan' => 'Jadwalkan diskusi',
            'status' => 'pending',
        ]);

        foreach ([
            ['type' => 'assessments', 'format' => 'excel', 'name' => 'tka-assessments-'],
            ['type' => 'warnings', 'format' => 'pdf', 'name' => 'tka-warnings-'],
            ['type' => 'dimensions', 'format' => 'excel', 'name' => 'tka-dimensions-'],
        ] as $case) {
            $this->actingAs($this->admin)
                ->post(route('admin.tka.export'), [
                    'type' => $case['type'],
                    'format' => $case['format'],
                    'semester_id' => $this->semester->id,
                ])
                ->assertOk()
                ->assertDownload($case['name'] . now()->format('Y-m-d') . ($case['format'] === 'pdf' ? '.pdf' : '.xlsx'));
        }
    }

    public function test_tka_bulk_report_requires_assessments_for_selected_semester(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.tka.generate-report'), [
                'semester_id' => $this->semester->id,
                'format' => 'pdf',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Belum ada penilaian TKA pada semester yang dipilih.');
    }

    public function test_tka_dashboard_has_working_export_forms(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.tka.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.tka.export'))
            ->assertSee(route('admin.tka.generate-report'))
            ->assertSee('Laporan Massal per Semester');
    }

    public function test_tka_assessment_table_rows_match_the_column_headers(): void
    {
        $this->createAssessment();

        $html = $this->actingAs($this->admin)
            ->get(route('admin.tka.assessments.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<!-- Assessments Table -->/', $html);
        preg_match('/<!-- Assessments Table -->(.*?<\/table>)/s', $html, $tableMatch);
        $this->assertNotEmpty($tableMatch[1] ?? null);
        preg_match('/<thead>(.*?)<\/thead>/s', $tableMatch[1], $headMatch);
        preg_match('/<tbody>(.*?)<\/tbody>/s', $tableMatch[1], $bodyMatch);
        preg_match('/<tr>(.*?)<\/tr>/s', $bodyMatch[1] ?? '', $rowMatch);

        $this->assertSame(8, substr_count($headMatch[1] ?? '', '<th'));
        $this->assertSame(8, substr_count($rowMatch[1] ?? '', '<td'));
    }

    private function createTryoutResult(string $packageName, bool $passed): Result
    {
        $category = Category::create([
            'nama_kategori' => 'Ekspor ' . Str::random(6),
            'jenis' => 'akademik',
            'aktif' => true,
        ]);
        $package = Package::create([
            'category_id' => $category->id,
            'kode_paket' => 'PKT-' . Str::upper(Str::random(8)),
            'nama_paket' => $packageName,
            'jenjang' => 'SD',
            'tahun_paket' => 2026,
            'durasi_total_menit' => 60,
            'aktif' => true,
        ]);
        $session = TryoutSession::create([
            'kode_sesi' => 'SES-' . Str::upper(Str::random(8)),
            'nama_sesi' => 'Sesi ' . $packageName,
            'package_id' => $package->id,
            'semester_id' => $this->semester->id,
            'guru_id' => $this->guru->id,
            'kelas' => '6A',
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 60,
            'status' => 'completed',
        ]);

        return Result::create([
            'tryout_session_id' => $session->id,
            'siswa_id' => $this->siswa->id,
            'attempt_number' => 1,
            'nilai_total' => $passed ? 80 : 40,
            'nilai_maksimal' => 100,
            'persentase' => $passed ? 80 : 40,
            'peringkat' => 1,
            'lulus' => $passed,
            'detail_nilai_mapel' => [],
            'waktu_mulai' => now()->subMinutes(30),
            'waktu_selesai' => now(),
            'durasi_total_detik' => 1800,
        ]);
    }

    private function createAssessment(): StudentAssessment
    {
        return StudentAssessment::create([
            'siswa_id' => $this->siswa->id,
            'semester_id' => $this->semester->id,
            'guru_id' => $this->guru->id,
            'tanggal_penilaian' => now()->toDateString(),
            'total_score' => 14,
            'max_score' => 16,
            'persentase' => 87.5,
            'status_kesiapan' => 'sangat_siap',
            'skor_per_dimensi' => [[
                'dimension_id' => 1,
                'nama_dimensi' => 'Mandiri',
                'kategori' => 'mandiri',
                'total_skor' => 14,
                'maksimal' => 16,
            ]],
        ]);
    }
}
