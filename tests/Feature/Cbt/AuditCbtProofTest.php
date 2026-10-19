<?php

namespace Tests\Feature\Cbt;

use App\Models\Cbt\Question;
use App\Models\Cbt\QuestionBank;
use App\Models\Cbt\QuestionOption;
use App\Models\Cbt\StudentAnswer;
use App\Models\Cbt\TestResult;
use App\Models\Cbt\TestSession;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * BUKTI EKSEKUSI bug modul CBT (baseline Fase 0).
 *
 * Setiap assertion merepresentasikan PERILAKU YANG BENAR. Selama bug belum
 * diperbaiki, test di kelas ini GAGAL/ERROR — itulah buktinya. Setelah Fase 1-2
 * selesai, kelas ini harus HIJAU dan berfungsi sebagai regression guard.
 *
 * Rujukan: docs/RENCANA_PERBAIKAN_VERIFIED.md (§2.1).
 *
 * Catatan: beberapa test memakai izinkanUjianBerjalan() untuk sementara membuat
 * kolom waktu_selesai nullable, agar skenario (IDOR, penilaian) bisa disusun
 * meski bug K-01 (migrasi NOT NULL) belum diperbaiki. Test K-01 sendiri TIDAK
 * memakai helper itu, sehingga tetap membuktikan bug migrasi.
 */
#[Group('baseline')]
class AuditCbtProofTest extends TestCase
{
    use RefreshDatabase;

    private User $guruA;

    private MataPelajaran $mapel;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        config(['simantap.cbt_enabled' => true]);

        $this->guruA = User::factory()->guru()->create();

        $this->mapel = MataPelajaran::create([
            'nama' => 'Matematika',
            'kode' => 'MTK',
            'jenjang' => 'SD',
            'aktif' => true,
        ]);

        $this->semester = Semester::create([
            'semester_id' => '2026-1',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);
    }

    /** @return array{0: Siswa, 1: User} */
    private function buatSiswa(string $nis): array
    {
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $this->guruA->id,
            'nama_guru' => $this->guruA->nama_lengkap,
            'nis' => $nis,
            'nisn' => '9'.random_int(100000000, 999999999),
            'nama_peserta_didik' => 'Siswa '.$nis,
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
        ]);

        $user = User::factory()->siswa()->create(['nama_pengguna' => $nis]);

        return [$siswa, $user];
    }

    private function buatBank(User $guru, string $kode): QuestionBank
    {
        return QuestionBank::create([
            'uuid' => (string) Str::uuid(),
            'mata_pelajaran_id' => $this->mapel->id,
            'semester_id' => $this->semester->id,
            'guru_id' => $guru->id,
            'kode_bank' => $kode,
            'nama_bank' => 'Bank '.$kode,
            'aktif' => true,
        ]);
    }

    private function buatSoal(QuestionBank $bank, int $n, float $poin = 10.0): Question
    {
        $q = Question::create([
            'uuid' => (string) Str::uuid(),
            'question_bank_id' => $bank->id,
            'urutan' => $n,
            'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Soal '.$n,
            'poin' => $poin,
            'aktif' => true,
        ]);

        foreach (['A', 'B', 'C', 'D'] as $huruf) {
            QuestionOption::create([
                'question_id' => $q->id,
                'huruf' => $huruf,
                'isi_opsi' => 'Opsi '.$huruf,
                'adalah_benar' => $huruf === 'A',
            ]);
        }

        return $q;
    }

    private function buatSesi(QuestionBank $bank, User $guru, array $overrides = []): TestSession
    {
        return TestSession::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'kode_sesi' => 'S'.random_int(10000, 99999),
            'nama_sesi' => 'Sesi Uji',
            'question_bank_id' => $bank->id,
            'mata_pelajaran_id' => $this->mapel->id,
            'semester_id' => $this->semester->id,
            'guru_id' => $guru->id,
            'kelas' => '6.A',
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 60,
            'jumlah_soal_tampil' => 2,
            'status' => 'ongoing',
            'passing_grade' => 60,
        ], $overrides));
    }

    /**
     * Hanya untuk test non-K-01: melonggarkan NOT NULL agar skenario bisa disusun
     * sebelum migrasi Fase 1.1 diterapkan.
     */
    private function izinkanUjianBerjalan(): void
    {
        Schema::table('cbt_test_results', function ($table) {
            $table->dateTime('waktu_selesai')->nullable()->change();
        });
    }

    private function resultBerjalan(TestSession $sesi, Siswa $siswa, int $attempt = 1): TestResult
    {
        return TestResult::create([
            'uuid' => (string) Str::uuid(),
            'test_session_id' => $sesi->id,
            'siswa_id' => $siswa->id,
            'attempt_number' => $attempt,
            'nilai_akhir' => 0,
            'nilai_maksimal' => 0,
            'persentase' => 0,
            'lulus' => false,
            'jumlah_benar' => 0,
            'jumlah_salah' => 0,
            'jumlah_tidak_dijawab' => 0,
            'waktu_mulai' => now(),
            'waktu_selesai' => null,
            'durasi_detik' => 0,
            'submitted_by' => 'siswa',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'integrity_score' => 100.0,
        ]);
    }

    /* ─────────────────────────── K-01 ─────────────────────────── */

    public function test_siswa_dapat_memulai_ujian_cbt(): void
    {
        [$siswa, $user] = $this->buatSiswa('S001');
        $bank = $this->buatBank($this->guruA, 'B001');
        $this->buatSoal($bank, 1);
        $this->buatSoal($bank, 2);
        $sesi = $this->buatSesi($bank, $this->guruA);

        $this->actingAs($user)
            ->get(route('cbt.student.start', $sesi))
            ->assertStatus(200);

        $this->assertDatabaseCount('cbt_test_results', 1);
    }

    /* ─────────────────────── K-02 & K-03 ─────────────────────── */

    public function test_siswa_dapat_menyimpan_jawaban(): void
    {
        [$siswa, $user] = $this->buatSiswa('S002');
        $bank = $this->buatBank($this->guruA, 'B002');
        $soal = $this->buatSoal($bank, 1);
        $sesi = $this->buatSesi($bank, $this->guruA);

        $this->izinkanUjianBerjalan();
        $result = $this->resultBerjalan($sesi, $siswa);

        $this->actingAs($user)
            ->postJson(route('cbt.student.answer', $result), [
                'question_id' => $soal->id,
                'jawaban' => 'A',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('cbt_student_answers', 1);
    }

    public function test_middleware_tidak_menyebabkan_500_pada_route_result(): void
    {
        [$siswa, $user] = $this->buatSiswa('S003');
        $bank = $this->buatBank($this->guruA, 'B003');
        $this->buatSoal($bank, 1);
        $sesi = $this->buatSesi($bank, $this->guruA, ['tampilkan_nilai' => true]);

        $this->izinkanUjianBerjalan();
        $result = $this->resultBerjalan($sesi, $siswa);

        $response = $this->actingAs($user)->get(route('cbt.student.result', $result));

        $this->assertLessThan(500, $response->getStatusCode(), 'Route result tidak boleh HTTP 5xx.');
    }

    /* ─────────────────────────── K-04 ─────────────────────────── */

    public function test_aset_js_cbt_dimuat_via_vite_bukan_asset(): void
    {
        $blade = file_get_contents(resource_path('views/cbt/student/take.blade.php'));

        $this->assertStringNotContainsString(
            'asset("js/cbt/',
            $blade,
            'Aset CBT harus dimuat via @vite, bukan asset() yang menghasilkan 404.'
        );

        $this->assertFileExists(resource_path('js/cbt/timer.js'));
        $this->assertFileExists(resource_path('js/cbt/auto-save.js'));
        $this->assertFileExists(resource_path('js/cbt/integrity-checker.js'));
    }

    /* ─────────────────────────── K-05 ─────────────────────────── */

    public function test_siswa_tidak_bisa_menulis_jawaban_ujian_siswa_lain(): void
    {
        [, $userA] = $this->buatSiswa('S010');
        [$siswaB] = $this->buatSiswa('S011');
        $bank = $this->buatBank($this->guruA, 'B010');
        $soal = $this->buatSoal($bank, 1);
        $sesi = $this->buatSesi($bank, $this->guruA);

        $this->izinkanUjianBerjalan();
        $resultB = $this->resultBerjalan($sesi, $siswaB);

        $this->actingAs($userA)
            ->postJson(route('cbt.student.answer', $resultB), [
                'question_id' => $soal->id,
                'jawaban' => 'A',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('cbt_student_answers', 0);
    }

    public function test_siswa_tidak_bisa_mengumpulkan_ujian_siswa_lain(): void
    {
        [, $userA] = $this->buatSiswa('S012');
        [$siswaB] = $this->buatSiswa('S013');
        $bank = $this->buatBank($this->guruA, 'B011');
        $this->buatSoal($bank, 1);
        $sesi = $this->buatSesi($bank, $this->guruA);

        $this->izinkanUjianBerjalan();
        $resultB = $this->resultBerjalan($sesi, $siswaB);

        $this->actingAs($userA)
            ->post(route('cbt.student.submit', $resultB))
            ->assertStatus(403);

        $this->assertNull($resultB->fresh()->waktu_selesai);
    }

    public function test_siswa_tidak_bisa_melihat_hasil_siswa_lain(): void
    {
        [, $userA] = $this->buatSiswa('S014');
        [$siswaB] = $this->buatSiswa('S015');
        $bank = $this->buatBank($this->guruA, 'B012');
        $this->buatSoal($bank, 1);
        $sesi = $this->buatSesi($bank, $this->guruA, ['tampilkan_nilai' => true]);

        $this->izinkanUjianBerjalan();
        $resultB = $this->resultBerjalan($sesi, $siswaB);

        $this->actingAs($userA)
            ->get(route('cbt.student.result', $resultB))
            ->assertStatus(403);
    }

    /* ─────────────────────────── K-06 ─────────────────────────── */

    public function test_guru_tidak_bisa_mengubah_bank_soal_guru_lain(): void
    {
        $guruB = User::factory()->guru()->create();
        $bankA = $this->buatBank($this->guruA, 'B100');

        $this->actingAs($guruB)
            ->put(route('cbt.question-banks.update', $bankA), [
                'nama_bank' => 'Diretas',
                'mata_pelajaran_id' => $this->mapel->id,
                'semester_id' => $this->semester->id,
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('cbt_question_banks', ['id' => $bankA->id, 'nama_bank' => 'Bank B100']);
    }

    public function test_guru_tidak_bisa_menghapus_bank_soal_guru_lain(): void
    {
        $guruB = User::factory()->guru()->create();
        $bankA = $this->buatBank($this->guruA, 'B101');

        $this->actingAs($guruB)
            ->delete(route('cbt.question-banks.destroy', $bankA))
            ->assertStatus(403);

        $this->assertDatabaseHas('cbt_question_banks', ['id' => $bankA->id, 'deleted_at' => null]);
    }

    public function test_guru_tidak_bisa_menghapus_soal_guru_lain(): void
    {
        $guruB = User::factory()->guru()->create();
        $bankA = $this->buatBank($this->guruA, 'B102');
        $soal = $this->buatSoal($bankA, 1);

        $this->actingAs($guruB)
            ->delete(route('cbt.questions.destroy', $soal))
            ->assertStatus(403);

        $this->assertDatabaseHas('cbt_questions', ['id' => $soal->id, 'deleted_at' => null]);
    }

    public function test_guru_tidak_bisa_membuka_form_edit_bank_soal_guru_lain(): void
    {
        $guruB = User::factory()->guru()->create();
        $bankA = $this->buatBank($this->guruA, 'B103');

        $this->actingAs($guruB)
            ->get(route('cbt.question-banks.edit', $bankA))
            ->assertStatus(403);
    }

    /* ─────────────────────────── K-07 ─────────────────────────── */

    public function test_api_bank_soal_tidak_mengirim_kunci_jawaban(): void
    {
        $bankA = $this->buatBank($this->guruA, 'B200');
        $this->buatSoal($bankA, 1);

        $response = $this->actingAs($this->guruA)
            ->get(route('cbt.question-banks.api.questions', $bankA));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('adalah_benar', $response->getContent());
    }

    /* ─────────────────────────── K-08 ─────────────────────────── */

    public function test_guru_tidak_bisa_mempublish_sesi_guru_lain(): void
    {
        $guruB = User::factory()->guru()->create();
        $bankA = $this->buatBank($this->guruA, 'B300');
        $sesi = $this->buatSesi($bankA, $this->guruA, ['status' => 'draft']);

        $this->actingAs($guruB)
            ->post(route('cbt.sessions.publish', $sesi))
            ->assertStatus(403);
    }

    public function test_guru_tidak_bisa_memantau_sesi_guru_lain(): void
    {
        $guruB = User::factory()->guru()->create();
        $bankA = $this->buatBank($this->guruA, 'B301');
        $sesi = $this->buatSesi($bankA, $this->guruA);

        $this->actingAs($guruB)
            ->get(route('cbt.sessions.monitoring', $sesi))
            ->assertStatus(403);
    }

    /* ─────────────────────────── K-09 ─────────────────────────── */

    public function test_nilai_maksimal_hanya_menghitung_soal_yang_ditampilkan(): void
    {
        [$siswa, $user] = $this->buatSiswa('S020');
        $bank = $this->buatBank($this->guruA, 'B400');
        for ($i = 1; $i <= 10; $i++) {
            $this->buatSoal($bank, $i, 10.0);
        }
        $sesi = $this->buatSesi($bank, $this->guruA, ['jumlah_soal_tampil' => 2]);

        $this->izinkanUjianBerjalan();
        $result = $this->resultBerjalan($sesi, $siswa);

        $soal = $bank->questions()->orderBy('urutan')->first();
        $this->actingAs($user)->postJson(route('cbt.student.answer', $result), [
            'question_id' => $soal->id,
            'jawaban' => 'A',
        ])->assertStatus(200);

        $this->actingAs($user)->post(route('cbt.student.submit', $result));

        $this->assertEquals(
            20,
            $result->fresh()->nilai_maksimal,
            'Nilai maksimal harus 2 soal x 10 poin = 20, bukan seluruh bank (100).'
        );
    }

    /* ─────────────────────────── K-10 ─────────────────────────── */

    public function test_percobaan_kedua_tidak_menghitung_jawaban_percobaan_pertama(): void
    {
        [$siswa, $user] = $this->buatSiswa('S021');
        $bank = $this->buatBank($this->guruA, 'B401');
        $soal = $this->buatSoal($bank, 1, 10.0);
        $sesi = $this->buatSesi($bank, $this->guruA, ['boleh_ulang' => true, 'maksimal_percobaan' => 2]);

        $this->izinkanUjianBerjalan();
        $attempt1 = $this->resultBerjalan($sesi, $siswa, 1);
        $attempt2 = $this->resultBerjalan($sesi, $siswa, 2);

        StudentAnswer::create([
            'uuid' => (string) Str::uuid(),
            'test_session_id' => $sesi->id,
            'test_result_id' => $attempt1->id,
            'siswa_id' => $siswa->id,
            'question_id' => $soal->id,
            'jawaban' => 'A',
            'adalah_benar' => true,
            'poin_diperoleh' => 10,
            'attempt_number' => 1,
        ]);

        StudentAnswer::create([
            'uuid' => (string) Str::uuid(),
            'test_session_id' => $sesi->id,
            'test_result_id' => $attempt2->id,
            'siswa_id' => $siswa->id,
            'question_id' => $soal->id,
            'jawaban' => 'B',
            'adalah_benar' => false,
            'poin_diperoleh' => 0,
            'attempt_number' => 2,
        ]);

        $this->actingAs($user)->post(route('cbt.student.submit', $attempt2));

        $this->assertEquals(
            0,
            $attempt2->fresh()->jumlah_benar,
            'Percobaan 2 (jawaban salah) tidak boleh mewarisi jawaban benar percobaan 1.'
        );
    }

    /* ─────────────────────────── K-11 ─────────────────────────── */

    public function test_hasil_ujian_tetap_dapat_dilihat_setelah_dikumpulkan(): void
    {
        [$siswa, $user] = $this->buatSiswa('S030');
        $bank = $this->buatBank($this->guruA, 'B500');
        $this->buatSoal($bank, 1);
        $sesi = $this->buatSesi($bank, $this->guruA, ['tampilkan_nilai' => true]);

        $this->izinkanUjianBerjalan();
        $result = $this->resultBerjalan($sesi, $siswa);
        $result->update(['waktu_selesai' => now(), 'submitted_by' => 'siswa']);

        $this->actingAs($user)
            ->get(route('cbt.student.result', $result))
            ->assertStatus(200);
    }

    /* ─────────────────────────── K-12 ─────────────────────────── */

    public function test_siswa_dapat_melaporkan_pelanggaran_integritas(): void
    {
        [$siswa, $user] = $this->buatSiswa('S040');
        $bank = $this->buatBank($this->guruA, 'B600');
        $this->buatSoal($bank, 1);
        $sesi = $this->buatSesi($bank, $this->guruA);

        $this->izinkanUjianBerjalan();
        $result = $this->resultBerjalan($sesi, $siswa);

        $this->actingAs($user)
            ->postJson(route('cbt.integrity.log', $result), [
                'jenis_pelanggaran' => 'context_menu',
                'deskripsi' => 'Percobaan akses menu kanan',
                'data_tambahan' => json_encode(['x' => 1]),
            ])
            ->assertStatus(200);

        $this->assertDatabaseCount('cbt_integrity_logs', 1);
    }
}
