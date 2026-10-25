<?php

namespace Tests\Feature;

use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tka\AssessmentItem;
use App\Models\Tka\Dimension;
use App\Models\Tka\ParentReport;
use App\Models\Tka\StudentAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TkaAssessmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $guruA;
    private User $guruB;
    private Siswa $siswaA;
    private Siswa $siswaB;
    private Semester $semester;
    private Dimension $dimension;
    private AssessmentItem $itemRingan;
    private AssessmentItem $itemBerat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['simantap.cbt_enabled' => true]);

        $this->guruA = User::factory()->guru()->create();
        $this->guruB = User::factory()->guru()->create();
        $this->siswaA = $this->createStudent($this->guruA, 'TKA-A', 'Siswa Guru A');
        $this->siswaB = $this->createStudent($this->guruB, 'TKA-B', 'Siswa Guru B');

        $this->semester = Semester::create([
            'semester_id' => '2026-1',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $this->dimension = Dimension::create([
            'semester_id' => $this->semester->id,
            'kode_dimensi' => 'TKA-DIM-1',
            'nama_dimensi' => 'Bernalar Kritis',
            'kategori' => 'mandiri',
            'rubrik_penilaian' => [1 => 'belum', 2 => 'berkembang', 3 => 'baik', 4 => 'sangat baik'],
            'threshold_siap' => 3,
            'aktif' => true,
        ]);

        $this->itemRingan = $this->createItem('Indikator dasar', 1);
        $this->itemBerat = $this->createItem('Indikator berbobot', 3);
    }

    public function test_alur_tka_menilai_menghitung_membuat_dan_membaca_laporan_orang_tua(): void
    {
        $this->actingAs($this->guruA)
            ->get(route('tka.assessments.create'))
            ->assertOk()
            ->assertSee('Siswa Guru A')
            ->assertDontSee('Siswa Guru B');

        $this->post(route('tka.assessments.store'), [
            'siswa_id' => $this->siswaA->id,
            'semester_id' => $this->semester->id,
            'tanggal_penilaian' => '2026-10-25',
            'responses' => [
                [
                    'assessment_item_id' => $this->itemRingan->id,
                    'skor' => 1,
                    'catatan_guru' => 'Perlu dilatih',
                ],
                [
                    'assessment_item_id' => $this->itemBerat->id,
                    'skor' => 4,
                    'catatan_guru' => 'Kuat pada indikator ini',
                ],
            ],
        ])->assertRedirect();

        $assessment = StudentAssessment::where('siswa_id', $this->siswaA->id)->firstOrFail();
        $this->assertSame((int) $this->guruA->id, (int) $assessment->guru_id);
        $this->assertSame(13.0, $assessment->total_score);
        $this->assertSame(16.0, $assessment->max_score);
        $this->assertSame(81.25, $assessment->persentase);
        $this->assertSame('siap', $assessment->status_kesiapan);
        $this->assertCount(2, $assessment->responses);

        $this->actingAs($this->guruA)
            ->get(route('tka.assessments.show', $assessment))
            ->assertOk()
            ->assertSee('Siswa Guru A')
            ->assertSee('81.3%');

        $this->post(route('tka.assessments.generate-report', $assessment))
            ->assertRedirect(route('tka.assessments.show', $assessment));

        $report = ParentReport::where('assessment_id', $assessment->id)->firstOrFail();
        $ortu = User::factory()->ortu()->create([
            // String IDs are common in linked-account JSON; ownership must not depend on PHP type.
            'terhubung_dengan' => [(string) $this->siswaA->id],
        ]);

        $this->actingAs($ortu)
            ->get(route('tka.parent.show', $report))
            ->assertOk()
            ->assertSee('Siswa Guru A')
            ->assertSee('siap');

        $this->assertDatabaseHas('tka_parent_reports', [
            'id' => $report->id,
            'dibaca_oleh_ortu' => true,
            'ortu_user_id' => $ortu->id,
        ]);

        $otherParent = User::factory()->ortu()->create([
            'terhubung_dengan' => [(string) $this->siswaB->id],
        ]);
        $this->actingAs($otherParent)
            ->get(route('tka.parent.show', $report))
            ->assertForbidden();
    }

    public function test_laporan_tka_menyertakan_saran_untuk_dimensi_yang_lemah(): void
    {
        $assessment = StudentAssessment::create([
            'siswa_id' => $this->siswaA->id,
            'semester_id' => $this->semester->id,
            'guru_id' => $this->guruA->id,
            'tanggal_penilaian' => '2026-10-25',
            'total_score' => 1,
            'max_score' => 4,
            'persentase' => 25,
            'status_kesiapan' => 'belum_siap',
            'skor_per_dimensi' => [[
                'dimension_id' => $this->dimension->id,
                'nama_dimensi' => $this->dimension->nama_dimensi,
                'kategori' => $this->dimension->kategori,
                'total_skor' => 1,
                'maksimal' => 4,
            ]],
        ]);

        $this->actingAs($this->guruA)
            ->post(route('tka.assessments.generate-report', $assessment))
            ->assertRedirect(route('tka.assessments.show', $assessment));

        $report = ParentReport::where('assessment_id', $assessment->id)->firstOrFail();
        $this->assertCount(1, $report->aktivitas_suggested);
        $this->assertSame($this->dimension->nama_dimensi, $report->aktivitas_suggested[0]['dimension']);
        $this->assertStringContainsString($this->dimension->nama_dimensi, $report->rekomendasi_rumah);
    }

    public function test_item_dimensi_lain_tidak_dapat_dihapus_melalui_route_nested(): void
    {
        $otherDimension = Dimension::create([
            'semester_id' => $this->semester->id,
            'kode_dimensi' => 'TKA-DIM-2',
            'nama_dimensi' => 'Gotong Royong',
            'kategori' => 'gotong_royong',
            'rubrik_penilaian' => [1 => 'belum', 2 => 'berkembang', 3 => 'baik', 4 => 'sangat baik'],
            'threshold_siap' => 3,
            'aktif' => true,
        ]);
        $foreignItem = AssessmentItem::create([
            'dimension_id' => $otherDimension->id,
            'urutan' => 1,
            'indikator' => 'Kerja sama',
            'pertanyaan_penilaian' => 'Apakah siswa bekerja sama?',
            'tipe_jawaban' => 'skala_1_4',
            'bobot_item' => 1,
        ]);

        $this->actingAs($this->guruA)
            ->delete(route('tka.dimensions.destroy-item', [
                'dimension' => $this->dimension->id,
                'item' => $foreignItem->id,
            ]))
            ->assertNotFound();

        $this->assertDatabaseHas('tka_assessment_items', ['id' => $foreignItem->id]);
    }

    public function test_guru_tidak_dapat_melihat_membuat_atau_menerbitkan_laporan_untuk_siswa_guru_lain(): void
    {
        $assessment = StudentAssessment::create([
            'siswa_id' => $this->siswaB->id,
            'semester_id' => $this->semester->id,
            'guru_id' => $this->guruB->id,
            'tanggal_penilaian' => '2026-10-25',
            'total_score' => 4,
            'max_score' => 4,
            'persentase' => 100,
            'status_kesiapan' => 'sangat_siap',
            'skor_per_dimensi' => [[
                'dimension_id' => $this->dimension->id,
                'nama_dimensi' => $this->dimension->nama_dimensi,
                'kategori' => $this->dimension->kategori,
                'total_skor' => 4,
                'maksimal' => 4,
            ]],
        ]);

        $this->actingAs($this->guruA)
            ->get(route('tka.assessments.show', $assessment))
            ->assertForbidden();
        $this->actingAs($this->guruA)
            ->get(route('tka.assessments.by-siswa', $this->siswaB->id))
            ->assertForbidden();
        $this->actingAs($this->guruA)
            ->post(route('tka.assessments.store'), [
                'siswa_id' => $this->siswaB->id,
                'semester_id' => $this->semester->id,
                'tanggal_penilaian' => '2026-10-25',
                'responses' => [[
                    'assessment_item_id' => $this->itemRingan->id,
                    'skor' => 4,
                ]],
            ])
            ->assertForbidden();
        $this->actingAs($this->guruA)
            ->post(route('tka.assessments.generate-report', $assessment))
            ->assertForbidden();

        $this->assertDatabaseMissing('tka_parent_reports', ['assessment_id' => $assessment->id]);
    }

    private function createStudent(User $guru, string $nis, string $name): Siswa
    {
        return Siswa::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => $nis,
            'nama_peserta_didik' => $name,
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
    }

    private function createItem(string $indicator, float $weight): AssessmentItem
    {
        return AssessmentItem::create([
            'dimension_id' => $this->dimension->id,
            'urutan' => $this->dimension->assessmentItems()->count() + 1,
            'indikator' => $indicator,
            'pertanyaan_penilaian' => "Bagaimana siswa menunjukkan {$indicator}?",
            'tipe_jawaban' => 'skala_1_4',
            'bobot_item' => $weight,
        ]);
    }
}
