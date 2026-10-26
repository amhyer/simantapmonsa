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
use App\Models\Exam\QuestionOption;
use App\Models\Exam\Session as ExamSession;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Exam\ExamItemAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamItemAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function siswa(User $guru, int $n): Siswa
    {
        return Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'BUTIR-' . $n . '-' . $guru->id,
            'nama_peserta_didik' => 'Siswa Butir ' . $n,
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
        ]);
    }

    private function engineData(User $guru): array
    {
        $engine = Engine::create(['kode' => 'cbt', 'nama' => 'CBT', 'jenis' => 'cbt', 'aktif' => true]);
        $bank = Bank::create(['engine_id' => $engine->id, 'guru_id' => $guru->id, 'nama' => 'Bank Butir']);
        $session = ExamSession::create([
            'bank_id' => $bank->id,
            'guru_id' => $guru->id,
            'nama' => 'Sesi Butir',
            'status' => 'completed',
        ]);

        $q1 = Question::create([
            'bank_id' => $bank->id, 'urutan' => 1, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Ibu kota Indonesia?', 'poin' => 1,
        ]);
        foreach (['A' => 'Jakarta', 'B' => 'Bandung', 'C' => 'Surabaya', 'D' => 'Medan'] as $huruf => $isi) {
            QuestionOption::create([
                'question_id' => $q1->id,
                'huruf' => $huruf,
                'isi_opsi' => $isi,
                'adalah_benar' => $huruf === 'A',
                'urutan' => ord($huruf),
            ]);
        }

        $q2 = Question::create([
            'bank_id' => $bank->id, 'urutan' => 2, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => '2 + 2 = ?', 'poin' => 1,
        ]);
        foreach (['A' => '3', 'B' => '4', 'C' => '5'] as $huruf => $isi) {
            QuestionOption::create([
                'question_id' => $q2->id,
                'huruf' => $huruf,
                'isi_opsi' => $isi,
                'adalah_benar' => $huruf === 'B',
                'urutan' => ord($huruf),
            ]);
        }

        $nilai = [100, 80, 60, 40];

        foreach ($nilai as $i => $skor) {
            $attempt = Attempt::create([
                'session_id' => $session->id,
                'siswa_id' => $this->siswa($guru, $i + 1)->id,
                'attempt_number' => 1,
                'mulai_at' => now()->subMinutes(30),
                'selesai_at' => now(),
                'status' => Attempt::STATUS_DINILAI,
                'nilai_total' => $skor,
                'nilai_maksimal' => 2,
            ]);

            // Q1: tiga peserta menjawab benar, peserta skor terendah salah.
            Answer::create([
                'attempt_id' => $attempt->id, 'question_id' => $q1->id,
                'jawaban' => $i === 3 ? 'C' : 'A',
                'adalah_benar' => $i !== 3,
                'poin_diperoleh' => $i !== 3 ? 1 : 0,
            ]);

            // Q2: hanya peserta skor tertinggi menjawab benar; sisanya kosong.
            if ($i === 0) {
                Answer::create([
                    'attempt_id' => $attempt->id, 'question_id' => $q2->id,
                    'jawaban' => 'B', 'adalah_benar' => true, 'poin_diperoleh' => 1,
                ]);
            }
        }

        return [$session, $q1, $q2];
    }

    public function test_engine_menghitung_p_daya_beda_dan_distraktor(): void
    {
        $guru = User::factory()->guru()->create(['aktif' => true]);
        [$session, $q1] = $this->engineData($guru);

        $hasil = app(ExamItemAnalysisService::class)->analisisEngine($session);
        $butir = collect($hasil['butir'])->keyBy('id');

        $this->assertSame('engine', $hasil['sumber']);
        $this->assertSame(4, $hasil['jumlah_peserta']);
        $this->assertSame(2, $hasil['jumlah_soal']);

        $this->assertSame(0.75, $butir[$q1->id]['p']);
        $this->assertSame(1.0, $butir[$q1->id]['d']);
        $this->assertSame(1, $butir[$q1->id]['distraktor']['C']);
        $this->assertSame(0, $butir[$q1->id]['distraktor']['B']);
        $this->assertSame('Sedang', $butir[$q1->id]['kategori_p']);
        $this->assertSame('Baik', $butir[$q1->id]['kategori_d']);

        $q2 = collect($hasil['butir'])->firstWhere('nomor', 2);
        $this->assertSame(0.25, $q2['p']);
        $this->assertSame(3, $q2['kosong']);
        $this->assertSame('Sulit', $q2['kategori_p']);
    }

    public function test_sesi_lama_tanpa_engine_dianalisis_dari_snapshot(): void
    {
        $guru = User::factory()->guru()->create(['aktif' => true]);
        $bank = CbtQuestionBank::create(['guru_id' => $guru->id, 'title' => 'Bank Lama', 'subject' => 'Matematika']);
        $session = CbtTestSession::create([
            'guru_id' => $guru->id,
            'bank_id' => $bank->id,
            'title' => 'Sesi Lama',
            'kelas' => '6.A',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'duration_minutes' => 60,
            'status' => 'completed',
            'question_snapshot' => [[
                'id' => 10,
                'prompt' => 'Soal lama?',
                'options' => ['A' => 'Benar', 'B' => 'Salah'],
                'correct_answer' => 'A',
            ]],
            'participant_ids' => [],
        ]);

        foreach ([1, 2, 3, 4] as $i) {
            $siswa = $this->siswa($guru, $i);
            CbtResult::create([
                'session_id' => $session->id,
                'siswa_id' => $siswa->id,
                'user_id' => $guru->id,
                'started_at' => now()->subMinutes(20),
                'deadline_at' => now()->addMinutes(20),
                'submitted_at' => now(),
                'answers' => ['10' => $i === 4 ? 'B' : 'A'],
                'correct_count' => $i === 4 ? 0 : 1,
                'score' => $i === 4 ? 0 : 100,
            ]);
        }

        $hasil = app(ExamItemAnalysisService::class)->untukSesiCbt($session);

        $this->assertSame('legacy', $hasil['sumber']);
        $this->assertSame(4, $hasil['jumlah_peserta']);
        $this->assertSame(0.75, $hasil['butir'][0]['p']);
        $this->assertSame(1.0, $hasil['butir'][0]['d']);
        $this->assertSame(1, $hasil['butir'][0]['distraktor']['B']);
    }

    public function test_halaman_analisis_butir_dapat_diakses_admin(): void
    {
        config(['simantap.cbt_enabled' => true]);

        $admin = User::factory()->admin()->create(['aktif' => true]);
        $guru = User::factory()->guru()->create(['aktif' => true]);
        $bank = CbtQuestionBank::create(['guru_id' => $guru->id, 'title' => 'Bank Admin', 'subject' => 'IPA']);
        $session = CbtTestSession::create([
            'guru_id' => $guru->id,
            'bank_id' => $bank->id,
            'title' => 'Sesi Admin',
            'kelas' => '6.A',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'duration_minutes' => 60,
            'status' => 'completed',
            'question_snapshot' => [[
                'id' => 1, 'prompt' => 'Pilih A', 'options' => ['A' => 'Ya', 'B' => 'Tidak'], 'correct_answer' => 'A',
            ]],
            'participant_ids' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cbt.sessions.analisis-butir', $session))
            ->assertOk()
            ->assertSee('Analisis Butir')
            ->assertSee('Pilih A');
    }
}
