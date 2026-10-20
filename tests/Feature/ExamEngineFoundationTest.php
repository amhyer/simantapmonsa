<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Bank;
use App\Models\Exam\Engine;
use App\Models\Exam\Question;
use App\Models\Exam\Result;
use App\Models\Exam\Section;
use App\Models\Exam\Session;
use App\Models\Exam\Violation;
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
 * Fase 3 (fondasi): skema exam_*, relasi, dan backfill idempoten.
 * Tabel lama harus tetap utuh setelah backfill dijalankan.
 */
class ExamEngineFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guru = User::factory()->guru()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'EXAM01', 'nama_peserta_didik' => 'Siswa Engine',
            'kelas' => '6A', 'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
    }

    public function test_skema_exam_terbentuk(): void
    {
        foreach ([
            'exam_engines', 'exam_banks', 'exam_sections', 'exam_questions',
            'exam_question_options', 'exam_sessions', 'exam_attempts',
            'exam_answers', 'exam_results',
        ] as $tabel) {
            $this->assertTrue(\Schema::hasTable($tabel), "tabel $tabel tidak ada");
        }
    }

    public function test_kunci_soal_tidak_ikut_serialisasi(): void
    {
        $engine = Engine::create(['kode' => 'x', 'nama' => 'X', 'jenis' => 'cbt']);
        $bank = Bank::create(['engine_id' => $engine->id, 'nama' => 'Bank']);
        $question = Question::create([
            'bank_id' => $bank->id, 'tipe' => 'isian_singkat',
            'pertanyaan' => 'Ibu kota?', 'kunci_jawaban' => 'Jakarta', 'poin' => 2,
        ]);

        $this->assertArrayNotHasKey('kunci_jawaban', $question->toArray());
    }

    public function test_backfill_menyalin_cbt_dan_tryout_lalu_idempoten(): void
    {
        $this->buatDataCbt();
        $this->buatDataTryout();

        $this->artisan('exam:backfill')->assertSuccessful();
        $this->artisan('exam:backfill')->assertSuccessful(); // idempoten

        // 1 engine cbt + 1 engine tryout.
        $this->assertSame(2, Engine::count());
        // 1 bank CBT + 1 paket Tryout.
        $this->assertSame(2, Bank::count());
        // 1 mapel Tryout -> 1 section.
        $this->assertSame(1, Section::count());
        // 2 soal CBT + 1 soal Tryout.
        $this->assertSame(3, Question::count());
        // 1 sesi CBT + 1 sesi Tryout.
        $this->assertSame(2, Session::count());
        // 1 hasil CBT + 1 hasil Tryout.
        $this->assertSame(2, Attempt::count());
        $this->assertSame(2, Result::count());
        // 1 pelanggaran CBT ikut dipetakan, dan tidak berlipat setelah 2 kali.
        $this->assertSame(1, Violation::count());
        $this->assertSame('tab_hidden', Violation::firstOrFail()->tipe);

        // Kunci CBT ikut tersalin ke opsi.
        $cbtExam = Question::where('sumber_tabel', 'cbt_questions')->orderBy('sumber_id')->first();
        $this->assertSame('C', $cbtExam->options->firstWhere('adalah_benar', true)->huruf);

        // Snapshot sesi CBT terbawa.
        $cbtSession = Session::where('sumber_tabel', 'cbt_test_sessions')->firstOrFail();
        $this->assertNotNull($cbtSession->soal_snapshot);

        // Hasil Tryout: poin per section + nilai ikut.
        $tryoutResult = Result::where('sumber_tabel', 'tryout_results')->firstOrFail();
        $this->assertSame(80.0, $tryoutResult->persentase);

        // Tabel lama tidak disentuh.
        $this->assertSame(1, CbtQuestionBank::count());
        $this->assertSame(1, CbtTestSession::count());
        $this->assertSame(1, CbtResult::count());
        $this->assertSame(1, Package::count());
        $this->assertSame(1, TryoutSession::count());
        $this->assertSame(1, TryoutResult::count());
        $this->assertSame(1, StudentAnswer::count());
    }

    private function buatDataCbt(): void
    {
        $bank = CbtQuestionBank::create([
            'guru_id' => $this->guru->id, 'title' => 'Bank CBT', 'subject' => 'IPA',
        ]);
        $q1 = $bank->questions()->create([
            'prompt' => 'Soal 1', 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'correct_answer' => 'C',
        ]);
        $bank->questions()->create([
            'prompt' => 'Soal 2', 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'correct_answer' => 'A',
        ]);

        $akun = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);
        $sesi = CbtTestSession::create([
            'guru_id' => $this->guru->id, 'bank_id' => $bank->id, 'title' => 'Sesi CBT',
            'kelas' => '6A', 'opens_at' => now()->subHour(), 'closes_at' => now()->addHour(),
            'duration_minutes' => 60, 'status' => 'completed',
            'question_snapshot' => [['id' => $q1->id, 'prompt' => 'Soal 1', 'correct_answer' => 'C']],
            'participant_ids' => [$this->siswa->id],
        ]);
        CbtResult::create([
            'session_id' => $sesi->id, 'siswa_id' => $this->siswa->id, 'user_id' => $akun->id,
            'started_at' => now()->subMinutes(30), 'deadline_at' => now()->subMinute(),
            'submitted_at' => now(), 'answers' => [$q1->id => 'C'], 'correct_count' => 1, 'score' => 100.0,
        ]);
        CbtResult::firstOrFail()->violations()->create(['type' => 'tab_hidden']);
    }

    private function buatDataTryout(): void
    {
        $semester = Semester::create([
            'semester_id' => '2026-1', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'jenjang' => 'SD']);
        $kategori = Category::create(['nama_kategori' => 'Akademik']);
        $paket = Package::create([
            'category_id' => $kategori->id, 'kode_paket' => 'PKT-1', 'nama_paket' => 'Paket 1',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60,
        ]);
        $subject = Subject::create([
            'package_id' => $paket->id, 'mata_pelajaran_id' => $mapel->id, 'durasi_menit' => 30,
        ]);
        $soal = TryoutQuestion::create([
            'tryout_subject_id' => $subject->id, 'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Dua tambah dua?', 'poin' => 5,
        ]);
        $soal->options()->createMany([
            ['huruf' => 'A', 'isi_opsi' => 'Empat', 'adalah_benar' => true],
            ['huruf' => 'B', 'isi_opsi' => 'Lima', 'adalah_benar' => false],
        ]);

        $sesi = TryoutSession::create([
            'kode_sesi' => 'SESI-1', 'nama_sesi' => 'Tryout 1', 'package_id' => $paket->id,
            'semester_id' => $semester->id, 'guru_id' => $this->guru->id, 'kelas' => '6A',
            'tanggal_mulai' => now()->subHour(), 'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 30, 'status' => 'completed',
        ]);
        TryoutResult::create([
            'tryout_session_id' => $sesi->id, 'siswa_id' => $this->siswa->id, 'attempt_number' => 1,
            'nilai_total' => 4, 'nilai_maksimal' => 5, 'persentase' => 80, 'lulus' => true,
            'detail_nilai_mapel' => ['Matematika' => 80],
            'waktu_mulai' => now()->subMinutes(20), 'waktu_selesai' => now(), 'durasi_total_detik' => 1200,
        ]);
        StudentAnswer::create([
            'tryout_session_id' => $sesi->id, 'tryout_subject_id' => $subject->id,
            'siswa_id' => $this->siswa->id, 'question_id' => $soal->id,
            'jawaban' => 'A', 'adalah_benar' => true, 'poin_diperoleh' => 4, 'attempt_number' => 1,
        ]);
    }

    public function test_attempt_menyimpan_jawaban_dan_hasil_terkait(): void
    {
        $engine = Engine::create(['kode' => 'y', 'nama' => 'Y', 'jenis' => 'cbt']);
        $bank = Bank::create(['engine_id' => $engine->id, 'nama' => 'Bank']);
        $question = Question::create([
            'bank_id' => $bank->id, 'tipe' => 'pilihan_ganda', 'pertanyaan' => 'q', 'poin' => 2,
        ]);
        $session = Session::create([
            'bank_id' => $bank->id, 'nama' => 'S', 'status' => 'ongoing', 'durasi_menit' => 30,
        ]);
        $attempt = Attempt::create([
            'session_id' => $session->id, 'siswa_id' => $this->siswa->id,
            'attempt_number' => 1, 'mulai_at' => now(),
        ]);
        Answer::create([
            'attempt_id' => $attempt->id, 'question_id' => $question->id,
            'jawaban' => 'A', 'adalah_benar' => true, 'poin_diperoleh' => 2,
        ]);
        Result::create(['attempt_id' => $attempt->id, 'nilai_total' => 2, 'nilai_maksimal' => 2, 'persentase' => 100]);

        $this->assertSame(1, $attempt->answers()->count());
        $this->assertSame(2.0, $attempt->result->nilai_total);
        $this->assertSame($question->id, $attempt->answers->first()->question->id);
    }
}
