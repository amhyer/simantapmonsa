<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Exam\Bank;
use App\Models\Exam\Question;
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
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3: bukti bahwa backfill bukan hanya menyalin jumlah baris, tetapi
 * benar-benar menyalin isi. Rekonsiliasi harus gagal saat satu kolom, opsi,
 * jawaban, atau relasi engine diubah.
 */
class ExamReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guru = User::factory()->guru()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id,
            'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'REKON01',
            'nama_peserta_didik' => 'Siswa Rekonsiliasi',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $this->buatDataCbt();
        $this->buatDataTryout();
    }

    public function test_backfill_lalu_rekonsiliasi_menyatakan_isi_sama(): void
    {
        $this->artisan('exam:backfill')->assertSuccessful();

        $this->artisan('exam:rekonsiliasi')
            ->expectsOutputToContain('Rekonsiliasi cocok')
            ->assertSuccessful();
    }

    public function test_verifikasi_tidak_menyalin_apa_pun(): void
    {
        $this->artisan('exam:backfill', ['--verify' => true])
            ->expectsOutputToContain('masalah ditemukan')
            ->assertFailed();

        $this->assertSame(0, Bank::count());
        $this->assertSame(0, Question::count());
    }

    public function test_dry_run_tidak_menyimpan_perubahan(): void
    {
        $this->artisan('exam:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('di-rollback')
            ->assertSuccessful();

        $this->assertSame(0, Bank::count());
        $this->assertSame(0, Question::count());
        $this->assertSame(1, CbtQuestionBank::count());
        $this->assertSame(1, Package::count());
    }

    public function test_perubahan_kolom_di_engine_terdeteksi(): void
    {
        $this->artisan('exam:backfill')->assertSuccessful();

        Bank::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_BANK)
            ->firstOrFail()
            ->update(['nama' => 'Nama yang diubah']);

        $this->artisan('exam:rekonsiliasi')
            ->expectsOutputToContain("kolom 'nama' berbeda")
            ->assertFailed();
    }

    public function test_opsi_soal_yang_berbeda_terdeteksi(): void
    {
        $this->artisan('exam:backfill')->assertSuccessful();

        $question = Question::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SOAL)
            ->firstOrFail();

        $question->options()->where('huruf', 'A')->update(['isi_opsi' => 'Bukan empat']);

        $this->artisan('exam:rekonsiliasi')
            ->expectsOutputToContain("opsi 'A' kolom 'isi_opsi' berbeda")
            ->assertFailed();
    }

    public function test_jawaban_engine_yang_hilang_terdeteksi(): void
    {
        $this->artisan('exam:backfill')->assertSuccessful();

        \App\Models\Exam\Answer::query()->firstOrFail()->delete();

        $this->artisan('exam:rekonsiliasi')
            ->expectsOutputToContain('jawaban tidak ada di engine')
            ->assertFailed();
    }

    public function test_rekonsiliasi_bisa_dibatasi_per_area(): void
    {
        $this->artisan('exam:backfill')->assertSuccessful();

        Bank::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_BANK)
            ->firstOrFail()
            ->update(['nama' => 'Diubah']);

        $this->artisan('exam:rekonsiliasi', ['--only' => 'tryout'])->assertSuccessful();
        $this->artisan('exam:rekonsiliasi', ['--only' => 'cbt'])->assertFailed();
    }

    private function buatDataCbt(): void
    {
        $bank = CbtQuestionBank::create([
            'guru_id' => $this->guru->id,
            'title' => 'Bank CBT',
            'subject' => 'IPA',
        ]);

        $q1 = $bank->questions()->create([
            'prompt' => 'Soal 1',
            'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'correct_answer' => 'C',
        ]);
        $bank->questions()->create([
            'prompt' => 'Soal 2',
            'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'correct_answer' => 'A',
        ]);

        $akun = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);
        $sesi = CbtTestSession::create([
            'guru_id' => $this->guru->id,
            'bank_id' => $bank->id,
            'title' => 'Sesi CBT',
            'kelas' => '6A',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'duration_minutes' => 60,
            'status' => 'completed',
            'question_snapshot' => [['id' => $q1->id, 'prompt' => 'Soal 1', 'correct_answer' => 'C']],
            'participant_ids' => [$this->siswa->id],
        ]);
        $hasil = CbtResult::create([
            'session_id' => $sesi->id,
            'siswa_id' => $this->siswa->id,
            'user_id' => $akun->id,
            'started_at' => now()->subMinutes(30),
            'deadline_at' => now()->subMinute(),
            'submitted_at' => now(),
            'answers' => [$q1->id => 'C'],
            'correct_count' => 1,
            'score' => 100.0,
        ]);
        $hasil->violations()->create(['type' => 'tab_hidden']);
    }

    private function buatDataTryout(): void
    {
        $semester = Semester::create([
            'semester_id' => '2026-1',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'jenjang' => 'SD']);
        $kategori = Category::create(['nama_kategori' => 'Akademik']);
        $paket = Package::create([
            'category_id' => $kategori->id,
            'kode_paket' => 'PKT-1',
            'nama_paket' => 'Paket 1',
            'jenjang' => 'SD',
            'tahun_paket' => 2026,
            'durasi_total_menit' => 60,
        ]);
        $subject = Subject::create([
            'package_id' => $paket->id,
            'mata_pelajaran_id' => $mapel->id,
            'durasi_menit' => 30,
        ]);
        $soal = TryoutQuestion::create([
            'tryout_subject_id' => $subject->id,
            'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Dua tambah dua?',
            'poin' => 5,
        ]);
        $soal->options()->createMany([
            ['huruf' => 'A', 'isi_opsi' => 'Empat', 'adalah_benar' => true],
            ['huruf' => 'B', 'isi_opsi' => 'Lima', 'adalah_benar' => false],
        ]);

        $sesi = TryoutSession::create([
            'kode_sesi' => 'SESI-1',
            'nama_sesi' => 'Tryout 1',
            'package_id' => $paket->id,
            'semester_id' => $semester->id,
            'guru_id' => $this->guru->id,
            'kelas' => '6A',
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 30,
            'status' => 'completed',
        ]);
        TryoutResult::create([
            'tryout_session_id' => $sesi->id,
            'siswa_id' => $this->siswa->id,
            'attempt_number' => 1,
            'nilai_total' => 4,
            'nilai_maksimal' => 5,
            'persentase' => 80,
            'lulus' => true,
            'detail_nilai_mapel' => ['Matematika' => 80],
            'waktu_mulai' => now()->subMinutes(20),
            'waktu_selesai' => now(),
            'durasi_total_detik' => 1200,
        ]);
        StudentAnswer::create([
            'tryout_session_id' => $sesi->id,
            'tryout_subject_id' => $subject->id,
            'siswa_id' => $this->siswa->id,
            'question_id' => $soal->id,
            'jawaban' => 'A',
            'adalah_benar' => true,
            'poin_diperoleh' => 4,
            'attempt_number' => 1,
        ]);
    }
}
