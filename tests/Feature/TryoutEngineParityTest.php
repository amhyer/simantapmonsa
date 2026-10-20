<?php

namespace Tests\Feature;

use App\Models\Exam\Answer as ExamAnswer;
use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Exam\Question as ExamQuestion;
use App\Models\Exam\Result as ExamResult;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tryout\Subject;
use App\Models\Tryout\TryoutQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3, langkah 5: bukti bahwa alur ujian Tryout benar-benar berjalan di
 * engine terpadu `exam_*`, dan tabel `tryout_*` hanya cermin yang nilainya
 * konsisten (parity) dengan engine baru.
 *
 * Kalau suatu saat alur dikembalikan ke engine lama, test ini gagal — sehingga
 * "langkah 5" tidak bisa diam-diam dibatalkan.
 */
class TryoutEngineParityTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $akun;
    private Siswa $siswa;
    private TryoutSession $sesi;
    private Subject $mapel;
    private TryoutQuestion $soalBenar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['simantap.cbt_enabled' => true, 'simantap.exam_engine_tryout' => true]);

        $this->guru = User::factory()->guru()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'PAR001', 'nama_peserta_didik' => 'Siswa Parity',
            'kelas' => '6A', 'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
        $this->akun = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);

        $semester = Semester::create([
            'semester_id' => '2026-1', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'jenjang' => 'SD']);
        $kategori = Category::create(['nama_kategori' => 'Akademik']);
        $paket = Package::create([
            'category_id' => $kategori->id, 'kode_paket' => 'PKT-P', 'nama_paket' => 'Paket Parity',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60,
            'passing_grade' => 60,
        ]);
        $this->mapel = Subject::create([
            'package_id' => $paket->id, 'mata_pelajaran_id' => $mapel->id, 'durasi_menit' => 30,
        ]);

        $this->soalBenar = $this->buatSoal('Dua tambah dua?', 'A', 1);

        $this->sesi = TryoutSession::create([
            'kode_sesi' => 'SESI-P', 'nama_sesi' => 'Tryout Parity',
            'package_id' => $paket->id, 'semester_id' => $semester->id, 'guru_id' => $this->guru->id,
            'kelas' => '6A', 'tanggal_mulai' => now()->subMinute(), 'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 30, 'status' => 'ongoing',
        ]);
    }

    private function buatSoal(string $pertanyaan, string $kunci, float $poin): TryoutQuestion
    {
        $soal = TryoutQuestion::create([
            'tryout_subject_id' => $this->mapel->id, 'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => $pertanyaan, 'poin' => $poin,
        ]);
        $soal->options()->createMany([
            ['huruf' => 'A', 'isi_opsi' => 'Empat', 'adalah_benar' => $kunci === 'A'],
            ['huruf' => 'B', 'isi_opsi' => 'Lima', 'adalah_benar' => $kunci === 'B'],
        ]);

        return $soal;
    }

    private function jawab(TryoutResult $result, int $questionId, string $jawaban): void
    {
        $this->actingAs($this->akun)->postJson(route('tryout.student.answer', $result), [
            'subject_id' => $this->mapel->id,
            'question_id' => $questionId,
            'jawaban' => $jawaban,
        ])->assertOk();
    }

    public function test_jawaban_ditulis_ke_exam_answers_bukan_hanya_tabel_lama(): void
    {
        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();

        $result = TryoutResult::where('tryout_session_id', $this->sesi->id)->firstOrFail();
        $this->jawab($result, $this->soalBenar->id, 'A');

        // Sumber kebenaran: engine terpadu.
        $this->assertSame(1, ExamAnswer::count());
        $examQuestion = ExamQuestion::where('sumber_tabel', 'tryout_questions')
            ->where('sumber_id', $this->soalBenar->id)->firstOrFail();
        $this->assertSame(1, ExamAnswer::where('question_id', $examQuestion->id)->count());

        // Cermin lama tetap terisi (dibaca admin/ekspor).
        $legacy = StudentAnswer::where('question_id', $this->soalBenar->id)->firstOrFail();
        $this->assertSame('A', $legacy->jawaban);
        $this->assertTrue((bool) $legacy->adalah_benar);

        // Dan attempt engine menunjuk balik ke baris lama.
        $attempt = ExamAttempt::where('sumber_tabel', 'tryout_results')->firstOrFail();
        $this->assertSame($result->id, (int) $attempt->sumber_id);
    }

    public function test_nilai_engine_baru_sama_dengan_nilai_tabel_lama(): void
    {
        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();
        $result = TryoutResult::where('tryout_session_id', $this->sesi->id)->firstOrFail();

        $this->jawab($result, $this->soalBenar->id, 'A');
        $this->jawab($result, $this->buatSoal('Tiga tambah tiga?', 'B', 2)->id, 'A'); // sengaja salah

        $this->actingAs($this->akun)->post(route('tryout.student.complete', $result))
            ->assertRedirect(route('tryout.student.result', $result));

        $examResult = ExamResult::firstOrFail();
        $legacy = $result->fresh();

        // Poin: 1 benar (1 poin) + 1 salah (0), maksimal 3 → 33.33%.
        $this->assertSame(1.0, (float) $examResult->nilai_total);
        $this->assertSame(3.0, (float) $examResult->nilai_maksimal);
        $this->assertSame(33.33, (float) $examResult->persentase);

        // Cermin harus identik dengan engine.
        $this->assertSame((float) $examResult->nilai_total, (float) $legacy->nilai_total);
        $this->assertSame((float) $examResult->nilai_maksimal, (float) $legacy->nilai_maksimal);
        $this->assertSame((float) $examResult->persentase, (float) $legacy->persentase);
        $this->assertSame((bool) $examResult->lulus, (bool) $legacy->lulus);

        // Detail per mapel: nilai & persentase harus sama (bentuk array boleh
        // berbeda karena cermin mengikuti skema lama yang dibaca admin/ekspor).
        $detailExam = collect($examResult->detail_per_section);
        $detailLegacy = collect($legacy->detail_nilai_mapel);
        $this->assertCount($detailExam->count(), $detailLegacy);
        foreach ($detailExam as $i => $baris) {
            $this->assertSame($baris['nama_mapel'], $detailLegacy[$i]['nama_mapel']);
            $this->assertSame((float) $baris['skor'], (float) $detailLegacy[$i]['skor']);
            $this->assertSame((float) $baris['maksimal'], (float) $detailLegacy[$i]['maksimal']);
            $this->assertSame((float) $baris['persentase'], (float) $detailLegacy[$i]['persentase']);
        }
    }

    public function test_resume_tidak_menggandakan_attempt_engine(): void
    {
        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();
        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();
        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();

        $this->assertSame(1, ExamAttempt::count());
        $this->assertSame(1, TryoutResult::count());
    }

    public function test_flag_mati_kembali_ke_alur_lama(): void
    {
        config(['simantap.exam_engine_tryout' => false]);

        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();
        $result = TryoutResult::where('tryout_session_id', $this->sesi->id)->firstOrFail();
        $this->jawab($result, $this->soalBenar->id, 'A');

        // Tanpa flag, engine terpadu tidak boleh tersentuh sama sekali.
        $this->assertSame(0, ExamAttempt::count());
        $this->assertSame(0, ExamAnswer::count());
        $this->assertSame(1, StudentAnswer::count());
    }
}
