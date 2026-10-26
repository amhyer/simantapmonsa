<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result;
use App\Models\Tryout\Session;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tryout\Subject;
use App\Models\Tryout\TryoutQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard modul Tryout (Fase 1, T-01..T-07).
 *
 * Setiap assertion merepresentasikan perilaku yang BENAR. Sebelum perbaikan
 * Fase 1, test ini merah (500 saat mulai, IDOR, kebocoran kunci, dll).
 */
class TryoutTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $akun;
    private Siswa $siswa;
    private Session $sesi;
    private Subject $mapel;
    private TryoutQuestion $soal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['simantap.cbt_enabled' => true]);

        $this->guru = User::factory()->guru()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'TRY001', 'nama_peserta_didik' => 'Siswa Tryout',
            'kelas' => '6A', 'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
        $this->akun = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);

        $semester = Semester::create([
            'semester_id' => '2026-1', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'jenjang' => 'SD']);
        $kategori = Category::create(['nama_kategori' => 'Akademik']);
        $paket = Package::create([
            'category_id' => $kategori->id, 'kode_paket' => 'PKT-1', 'nama_paket' => 'Paket 1',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60,
        ]);
        $this->mapel = Subject::create([
            'package_id' => $paket->id, 'mata_pelajaran_id' => $mapel->id, 'durasi_menit' => 30,
        ]);
        $this->soal = TryoutQuestion::create([
            'tryout_subject_id' => $this->mapel->id, 'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Dua tambah dua?', 'poin' => 1,
        ]);
        $this->soal->options()->createMany([
            ['huruf' => 'A', 'isi_opsi' => 'Empat', 'adalah_benar' => true],
            ['huruf' => 'B', 'isi_opsi' => 'Lima', 'adalah_benar' => false],
        ]);
        $this->sesi = Session::create([
            'kode_sesi' => 'SESI-1', 'nama_sesi' => 'Tryout 1',
            'package_id' => $paket->id, 'semester_id' => $semester->id, 'guru_id' => $this->guru->id,
            'kelas' => '6A', 'tanggal_mulai' => now()->subMinute(), 'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 30, 'status' => 'ongoing',
        ]);
    }

    public function test_mulai_tryout_membuat_percobaan_tanpa_500(): void
    {
        $this->actingAs($this->akun)
            ->get(route('tryout.student.start', $this->sesi))
            ->assertOk();

        $result = Result::where('tryout_session_id', $this->sesi->id)->firstOrFail();
        $this->assertNull($result->waktu_selesai);
        $this->assertSame(1, $result->attempt_number);
    }

    public function test_jawaban_tidak_membocorkan_kunci_atau_poin(): void
    {
        $result = $this->mulai();

        $response = $this->postJson(route('tryout.student.answer', $result), [
            'subject_id' => $this->mapel->id,
            'question_id' => $this->soal->id,
            'jawaban' => 'A',
        ])->assertOk();

        $this->assertSame(['success'], array_keys($response->json()));
        $this->assertArrayNotHasKey('is_correct', $response->json());
        $this->assertArrayNotHasKey('poin', $response->json());

        $answer = StudentAnswer::where('question_id', $this->soal->id)->firstOrFail();
        $this->assertTrue($answer->adalah_benar);
        $this->assertSame(1, $answer->attempt_number);
    }

    public function test_menyelesaikan_tryout_menghitung_nilai(): void
    {
        $result = $this->mulai();
        $this->jawab($result, 'A');

        $this->post(route('tryout.student.complete', $result))
            ->assertRedirect(route('tryout.student.result', $result));

        $result->refresh();
        $this->assertSame(100.0, $result->persentase);
        $this->assertTrue($result->lulus);
        $this->assertNotNull($result->waktu_selesai);
    }

    public function test_alur_siswa_tryout_end_to_end_mulai_autosave_complete_hasil(): void
    {
        $result = $this->mulai();
        $this->jawab($result, 'A');

        $this->post(route('tryout.student.complete', $result))
            ->assertRedirect(route('tryout.student.result', $result));

        $this->assertSame(100.0, $result->fresh()->persentase);
        $this->actingAs($this->akun)
            ->get(route('tryout.student.result', $result->fresh()))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('100.0%');
    }

    public function test_jawaban_setelah_selesai_ditolak_409(): void
    {
        $result = $this->mulai();
        $this->post(route('tryout.student.complete', $result));

        $this->postJson(route('tryout.student.answer', $result->fresh()), [
            'subject_id' => $this->mapel->id,
            'question_id' => $this->soal->id,
            'jawaban' => 'A',
        ])->assertStatus(409);
    }

    public function test_siswa_lain_tidak_bisa_melihat_menjawab_atau_menyelesaikan(): void
    {
        $result = $this->mulai();
        $lain = User::factory()->siswa()->create(['nama_pengguna' => 'lain-999']);
        Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'lain-999', 'nama_peserta_didik' => 'Siswa Lain',
            'kelas' => '6A', 'jenis_kelamin' => 'P', 'aktif' => true,
        ]);

        $this->actingAs($lain)->get(route('tryout.student.result', $result))->assertForbidden();
        $this->actingAs($lain)->postJson(route('tryout.student.answer', $result), [
            'subject_id' => $this->mapel->id, 'question_id' => $this->soal->id, 'jawaban' => 'A',
        ])->assertForbidden();
        $this->actingAs($lain)->post(route('tryout.student.complete', $result))->assertForbidden();
    }

    public function test_percobaan_ulang_tidak_menimpa_jawaban_percobaan_pertama(): void
    {
        $pertama = $this->mulai();
        $this->jawab($pertama, 'B');                 // salah
        $this->post(route('tryout.student.complete', $pertama))->assertRedirect();
        $this->assertSame(0.0, $pertama->fresh()->persentase);

        // Percobaan kedua (sesi masih berjalan).
        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();
        $kedua = Result::where('tryout_session_id', $this->sesi->id)->latest('id')->firstOrFail();
        $this->assertSame(2, $kedua->attempt_number);
        $this->assertNotSame($pertama->id, $kedua->id);

        $this->jawab($kedua, 'A');                   // benar
        $this->post(route('tryout.student.complete', $kedua))->assertRedirect();

        $this->assertSame(100.0, $kedua->fresh()->persentase);
        $this->assertSame(0.0, $pertama->fresh()->persentase); // tidak tertimpa
        $this->assertSame(2, StudentAnswer::count());
    }

    public function test_soal_dari_sesi_lain_ditolak(): void
    {
        $result = $this->mulai();

        $this->postJson(route('tryout.student.answer', $result), [
            'subject_id' => $this->mapel->id,
            'question_id' => 999999,
            'jawaban' => 'A',
        ])->assertUnprocessable();
    }

    public function test_siswa_tanpa_data_tidak_500(): void
    {
        $akun = User::factory()->siswa()->create(['nama_pengguna' => 'tidak-ada']);
        $this->actingAs($akun)
            ->get(route('tryout.student.start', $this->sesi))
            ->assertRedirect(route('siswa.dashboard'));
    }

    public function test_admin_sesi_dan_force_complete_tidak_500(): void
    {
        $this->mulai();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.tryout.sessions.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.tryout.sessions.show', $this->sesi))->assertOk();
        $this->actingAs($admin)->post(route('admin.tryout.sessions.force-complete', $this->sesi))
            ->assertRedirect();

        $this->assertNotNull(Result::first()->waktu_selesai);
        $this->assertSame('completed', $this->sesi->fresh()->status);
    }

    public function test_admin_detail_hasil_tryout_tidak_500(): void
    {
        $result = $this->mulai();
        $this->jawab($result, 'A');
        $this->post(route('tryout.student.complete', $result))->assertRedirect();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.tryout.results.show', $result->fresh()))
            ->assertOk();
    }

    public function test_hasil_historis_menghitung_rincian_mapel_dari_jawaban_bila_ringkasan_kosong(): void
    {
        $result = $this->mulai();
        StudentAnswer::create([
            'tryout_session_id' => $result->tryout_session_id,
            'tryout_subject_id' => $this->mapel->id,
            'siswa_id' => $result->siswa_id,
            'question_id' => $this->soal->id,
            'attempt_number' => $result->attempt_number,
            'jawaban' => 'A',
            'adalah_benar' => true,
            'poin_diperoleh' => 1,
        ]);
        $result->update(['detail_nilai_mapel' => [], 'analisis_kelemahan' => []]);

        $this->actingAs($this->akun)
            ->get(route('tryout.student.result', $result))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('100.0%');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.tryout.results.show', $result->fresh()))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('100.0%');
    }

    public function test_guru_lain_tidak_bisa_mengelola_sesi_tryout(): void
    {
        $guruLain = User::factory()->guru()->create();

        $this->actingAs($guruLain)->get(route('tryout.sessions.show', $this->sesi))->assertForbidden();
        $this->actingAs($guruLain)->get(route('tryout.sessions.monitoring', $this->sesi))->assertForbidden();
        $this->actingAs($guruLain)->post(route('tryout.sessions.publish', $this->sesi))->assertForbidden();
        $this->actingAs($guruLain)->post(route('tryout.sessions.start', $this->sesi))->assertForbidden();
        $this->actingAs($guruLain)->post(route('tryout.sessions.complete', $this->sesi))->assertForbidden();

        $this->assertSame('ongoing', $this->sesi->fresh()->status);
    }

    public function test_siswa_kelas_lain_tidak_bisa_memulai(): void
    {
        $lain = User::factory()->siswa()->create(['nama_pengguna' => 'kelas-lain']);
        Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'kelas-lain', 'nama_peserta_didik' => 'Siswa Kelas Lain',
            'kelas' => '6B', 'jenis_kelamin' => 'P', 'aktif' => true,
        ]);

        $this->actingAs($lain)->get(route('tryout.student.start', $this->sesi))->assertRedirect();

        $this->assertDatabaseCount('tryout_results', 0);
    }

    private function mulai(): Result
    {
        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();

        return Result::where('tryout_session_id', $this->sesi->id)->latest('id')->firstOrFail();
    }

    private function jawab(Result $result, string $jawaban): void
    {
        $this->actingAs($this->akun)->postJson(route('tryout.student.answer', $result), [
            'subject_id' => $this->mapel->id,
            'question_id' => $this->soal->id,
            'jawaban' => $jawaban,
        ])->assertOk();
    }
}
