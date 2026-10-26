<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result;
use App\Models\Tryout\Session;
use App\Models\Tryout\Subject;
use App\Models\Tryout\TryoutQuestion;
use App\Models\User;
use App\Services\Exam\TryoutStudentReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 3 langkah 6d (tahap Tryout): halaman utama siswa harus bisa dibaca
 * sepenuhnya dari `exam_*`.
 *
 * Dua bukti: (1) potret pembaca identik pada kedua cabang flag, (2) setelah
 * seluruh `tryout_*` dikosongkan, pembaca engine tetap memberi hasil yang sama
 * dan halaman tetap 200.
 */
class TryoutStudentReaderTest extends TestCase
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
        config(['simantap.cbt_enabled' => true, 'simantap.exam_engine_tryout' => true]);

        $this->guru = User::factory()->guru()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'RD001', 'nama_peserta_didik' => 'Siswa Reader',
            'kelas' => '6A', 'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
        $this->akun = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);

        $semester = Semester::create([
            'semester_id' => '2026-1', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'jenjang' => 'SD']);
        $kategori = Category::create(['nama_kategori' => 'Akademik']);
        $paket = Package::create([
            'category_id' => $kategori->id, 'kode_paket' => 'PKT-R', 'nama_paket' => 'Paket Reader',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60,
            'passing_grade' => 60, 'jumlah_mapel' => 1,
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
            'kode_sesi' => 'SESI-R', 'nama_sesi' => 'Tryout Reader',
            'package_id' => $paket->id, 'semester_id' => $semester->id, 'guru_id' => $this->guru->id,
            'kelas' => '6A', 'tanggal_mulai' => now()->subMinute(), 'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 30, 'status' => 'ongoing',
        ]);

        // Jalankan satu percobaan penuh lewat rute (engine + cermin lama).
        $this->actingAs($this->akun)->get(route('tryout.student.start', $this->sesi))->assertOk();
        $result = Result::where('tryout_session_id', $this->sesi->id)->firstOrFail();
        $this->actingAs($this->akun)->postJson(route('tryout.student.answer', $result), [
            'subject_id' => $this->mapel->id,
            'question_id' => $this->soal->id,
            'jawaban' => 'A',
        ])->assertOk();
        $this->actingAs($this->akun)
            ->post(route('tryout.student.complete', $result))
            ->assertRedirect();
    }

    /** @return array{sesi: array<int, array<string, mixed>>, hasil: array<int, array<string, mixed>>} */
    private function potret(bool $engine): array
    {
        config(['simantap.exam_engine_readers' => $engine]);
        $reader = app(TryoutStudentReader::class);

        return [
            'sesi' => $reader->sesiTersedia($this->siswa),
            'hasil' => $reader->hasilSaya($this->siswa),
        ];
    }

    public function test_potret_pembaca_sama_di_kedua_sumber(): void
    {
        $legacy = $this->potret(false);
        $engine = $this->potret(true);

        $this->assertNotEmpty($engine['sesi']);
        $this->assertNotEmpty($engine['hasil']);
        $this->assertSame($legacy, $engine);
    }

    public function test_setelah_tabel_lama_dikosongkan_pembaca_engine_tetap_benar(): void
    {
        $sebelum = $this->potret(true);

        DB::table('tryout_student_answers')->delete();
        DB::table('tryout_results')->delete();
        DB::table('tryout_sessions')->delete();
        DB::table('tryout_questions')->delete();
        DB::table('tryout_question_options')->delete();
        DB::table('tryout_subjects')->delete();
        DB::table('tryout_packages')->delete();

        $sesudah = $this->potret(true);
        $this->assertSame($sebelum, $sesudah);

        $this->actingAs($this->akun)
            ->get(route('tryout.student.index'))
            ->assertOk()
            ->assertSee('Tryout Reader')
            ->assertSee('Paket Reader');
    }
}
