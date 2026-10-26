<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Session;
use App\Models\Tryout\Subject;
use App\Models\Tryout\TryoutQuestion;
use App\Models\User;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3 langkah 6c (tahap Tryout): CRUD paket/mapel/sesi harus langsung
 * tercermin ke engine `exam_*`, bukan menunggu publish atau backfill.
 */
class TryoutCrudEngineSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $admin;
    private MataPelajaran $mapel;
    private Category $kategori;
    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'simantap.cbt_enabled' => true,
            'simantap.exam_engine_tryout' => true,
        ]);

        $this->guru = User::factory()->guru()->create();
        $this->admin = User::factory()->admin()->create();
        $this->mapel = MataPelajaran::create(['nama' => 'Matematika', 'jenjang' => 'SD']);
        $this->kategori = Category::create(['nama_kategori' => 'Akademik']);
        $this->semester = Semester::create([
            'semester_id' => '2026-1', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]);
    }

    /** @return array<string, mixed> */
    private function payloadPaketGuru(string $nama = 'Paket CRUD'): array
    {
        return [
            'category_id' => $this->kategori->id,
            'kode_paket' => 'PKT-CRUD',
            'nama_paket' => $nama,
            'jenjang' => 'SD',
            'tahun_paket' => 2026,
            'durasi_total_menit' => 60,
            'passing_grade' => 60,
            'subjects' => [[
                'mata_pelajaran_id' => $this->mapel->id,
                'bobot' => 1,
                'jumlah_soal' => 1,
                'durasi_menit' => 30,
                'passing_grade_mapel' => 60,
            ]],
        ];
    }

    public function test_paket_dan_mapel_dari_crud_guru_langsung_masuk_engine(): void
    {
        $this->actingAs($this->guru)
            ->post(route('tryout.packages.store'), $this->payloadPaketGuru())
            ->assertRedirect();

        $package = Package::latest('id')->firstOrFail();
        $bank = \App\Models\Exam\Bank::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_PAKET)
            ->where('sumber_id', $package->id)
            ->firstOrFail();
        $subject = $package->subjects()->firstOrFail();
        $section = \App\Models\Exam\Section::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_MAPEL)
            ->where('sumber_id', $subject->id)
            ->firstOrFail();

        $this->assertSame('Paket CRUD', $bank->nama);
        $this->assertSame('Matematika', $section->nama);
        $this->assertSame($bank->id, $section->bank_id);

        $this->actingAs($this->guru)
            ->put(route('tryout.packages.update', $package), $this->payloadPaketGuru('Paket Revisi'))
            ->assertRedirect();

        $this->assertSame('Paket Revisi', $bank->fresh()->nama);
    }

    public function test_soal_tryout_ikut_tersalin_dan_opsi_yang_dihapus_tidak_tertinggal(): void
    {
        $package = Package::create([
            'category_id' => $this->kategori->id, 'kode_paket' => 'PKT-SOAL', 'nama_paket' => 'Paket Soal',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60,
        ]);
        $subject = Subject::create([
            'package_id' => $package->id, 'mata_pelajaran_id' => $this->mapel->id,
            'urutan' => 1, 'bobot' => 1, 'durasi_menit' => 30,
        ]);
        $question = TryoutQuestion::create([
            'tryout_subject_id' => $subject->id, 'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Dua tambah dua?', 'poin' => 1, 'urutan' => 1,
        ]);
        $question->options()->createMany([
            ['huruf' => 'A', 'isi_opsi' => 'Empat', 'adalah_benar' => true, 'urutan' => 1],
            ['huruf' => 'B', 'isi_opsi' => 'Lima', 'adalah_benar' => false, 'urutan' => 2],
        ]);

        $mapper = app(LegacyExamMapper::class);
        $mapper->paketTryout($package);

        $engineQuestion = \App\Models\Exam\Question::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SOAL)
            ->where('sumber_id', $question->id)
            ->firstOrFail();
        $this->assertSame(2, $engineQuestion->options()->count());

        $question->options()->where('huruf', 'B')->delete();
        $mapper->paketTryout($package->fresh());

        $this->assertSame(1, $engineQuestion->options()->count());
        $this->assertTrue($engineQuestion->options()->firstOrFail()->adalah_benar);
    }

    public function test_sesi_dari_crud_admin_masuk_engine_dan_penghapusan_ikut(): void
    {
        $package = Package::create([
            'category_id' => $this->kategori->id, 'kode_paket' => 'PKT-SESI', 'nama_paket' => 'Paket Sesi',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.tryout.sessions.store'), [
                'kode_sesi' => 'SESI-CRUD',
                'nama_sesi' => 'Sesi CRUD',
                'package_id' => $package->id,
                'semester_id' => $this->semester->id,
                'tanggal_mulai' => now()->addHour()->toDateTimeString(),
                'tanggal_selesai' => now()->addDays(2)->toDateTimeString(),
                'durasi_menit' => 30,
            ])
            ->assertRedirect();

        $session = Session::latest('id')->firstOrFail();
        $engineSession = \App\Models\Exam\Session::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI)
            ->where('sumber_id', $session->id)
            ->firstOrFail();

        $this->assertSame('Sesi CRUD', $engineSession->nama);
        $this->assertSame('draft', $engineSession->status);

        $this->actingAs($this->admin)
            ->delete(route('admin.tryout.sessions.destroy', $session))
            ->assertRedirect();

        $this->assertSoftDeleted('exam_sessions', [
            'sumber_tabel' => LegacyExamMapper::SUMBER_TRYOUT_SESI,
            'sumber_id' => $session->id,
        ]);
    }

    public function test_hapus_paket_admin_menghapus_bank_engine(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.tryout.packages.store'), [
                'kode_paket' => 'PKT-ADM', 'nama_paket' => 'Paket Admin', 'jenjang' => 'SD',
                'tipe_ujian' => 'USBN', 'tahun_paket' => 2026, 'mata_pelajaran_id' => $this->mapel->id,
                'total_soal' => 1, 'waktu_menit' => 60, 'kkm' => 60,
            ])
            ->assertRedirect();

        $package = Package::latest('id')->firstOrFail();
        $this->assertDatabaseHas('exam_banks', [
            'sumber_tabel' => LegacyExamMapper::SUMBER_TRYOUT_PAKET,
            'sumber_id' => $package->id,
            'nama' => 'Paket Admin',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.tryout.packages.destroy', $package))
            ->assertRedirect();

        $this->assertSoftDeleted('exam_banks', [
            'sumber_tabel' => LegacyExamMapper::SUMBER_TRYOUT_PAKET,
            'sumber_id' => $package->id,
        ]);
    }
}
