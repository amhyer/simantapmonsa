<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
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
use App\Services\CbtExamService;
use App\Services\Exam\ExamReportService;
use App\Services\ExamDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3, langkah 5b: mengunci keluaran pembaca lama (laporan/ekspor/dasbor).
 *
 * Pembaca ini sebelumnya tanpa test. Sebelum dipindahkan ke engine terpadu,
 * keluarannya harus dibekukan lebih dulu supaya perubahan tidak bisa
 * menyelundupkan regresi diam-diam.
 */
class ExamReaderTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $admin;
    private User $akun;
    private Siswa $siswa;
    private array $previousFlag;

    protected function setUp(): void
    {
        $this->previousFlag = [getenv('SIMANTAP_CBT_ENABLED'), $_ENV['SIMANTAP_CBT_ENABLED'] ?? null, $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null];
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        \Illuminate\Support\Env::enablePutenv();

        parent::setUp();
        config(['simantap.cbt_enabled' => true, 'simantap.exam_engine_cbt' => true, 'simantap.exam_engine_tryout' => true]);

        $this->guru = User::factory()->guru()->create();
        $this->admin = User::factory()->admin()->create();
        $this->siswa = Siswa::create([
            'guru_id' => $this->guru->id, 'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'RDR001', 'nama_peserta_didik' => 'Siswa Pembaca', 'kelas' => '6A',
            'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
        $this->akun = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        [$env, $arrayEnv, $server] = $this->previousFlag;
        $env === false ? putenv('SIMANTAP_CBT_ENABLED') : putenv('SIMANTAP_CBT_ENABLED='.$env);
        if ($arrayEnv === null) { unset($_ENV['SIMANTAP_CBT_ENABLED']); } else { $_ENV['SIMANTAP_CBT_ENABLED'] = $arrayEnv; }
        if ($server === null) { unset($_SERVER['SIMANTAP_CBT_ENABLED']); } else { $_SERVER['SIMANTAP_CBT_ENABLED'] = $server; }
        \Illuminate\Support\Env::enablePutenv();
    }

    /** Mulai satu ujian CBT dan jawab 2 soal (1 benar). Belum disubmit. */
    private function mulaiCbt(): CbtTestSession
    {
        $bank = CbtQuestionBank::create(['guru_id' => $this->guru->id, 'title' => 'IPA', 'subject' => 'IPA']);
        $bank->questions()->createMany([
            ['prompt' => 'Soal satu', 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'], 'correct_answer' => 'A'],
            ['prompt' => 'Soal dua', 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'], 'correct_answer' => 'B'],
        ]);

        $session = CbtTestSession::create([
            'guru_id' => $this->guru->id, 'bank_id' => $bank->id, 'title' => 'Ujian IPA',
            'kelas' => '6A', 'opens_at' => now()->subMinute(), 'closes_at' => now()->addHours(2),
            'duration_minutes' => 60,
        ]);
        $exam = app(CbtExamService::class);
        $exam->publish($session);
        $exam->open($session);

        $this->actingAs($this->akun)->post(route('cbt.student.begin', $session));
        $result = CbtResult::firstOrFail();
        [$q1, $q2] = $session->fresh()->question_snapshot;
        $this->actingAs($this->akun)->postJson(route('cbt.student.answer', $result), ['question_id' => $q1['id'], 'answer' => 'A']);
        $this->actingAs($this->akun)->postJson(route('cbt.student.answer', $result), ['question_id' => $q2['id'], 'answer' => 'A']);

        return $session->fresh();
    }

    /** Selesaikan satu ujian CBT penuh (2 soal, 1 benar) → skor 50. */
    private function selesaikanCbt(): CbtTestSession
    {
        $session = $this->mulaiCbt();
        $this->actingAs($this->akun)->post(route('cbt.student.submit', CbtResult::firstOrFail()));

        return $session;
    }

    /** Selesaikan satu tryout penuh (1 soal, benar) → 100%. */
    private function selesaikanTryout(): TryoutResult
    {
        $semester = Semester::create([
            'semester_id' => '2026-1', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'jenjang' => 'SD']);
        $kategori = Category::create(['nama_kategori' => 'Akademik']);
        $paket = Package::create([
            'category_id' => $kategori->id, 'kode_paket' => 'PKT-R', 'nama_paket' => 'Paket Pembaca',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60, 'passing_grade' => 60,
        ]);
        $subject = Subject::create([
            'package_id' => $paket->id, 'mata_pelajaran_id' => $mapel->id, 'durasi_menit' => 30,
        ]);
        $soal = TryoutQuestion::create([
            'tryout_subject_id' => $subject->id, 'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Dua tambah dua?', 'poin' => 1,
        ]);
        $soal->options()->createMany([
            ['huruf' => 'A', 'isi_opsi' => 'Empat', 'adalah_benar' => true],
            ['huruf' => 'B', 'isi_opsi' => 'Lima', 'adalah_benar' => false],
        ]);

        $sesi = TryoutSession::create([
            'kode_sesi' => 'SESI-R', 'nama_sesi' => 'Tryout Pembaca', 'package_id' => $paket->id,
            'semester_id' => $semester->id, 'guru_id' => $this->guru->id, 'kelas' => '6A',
            'tanggal_mulai' => now()->subMinute(), 'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 30, 'status' => 'ongoing',
        ]);

        $this->actingAs($this->akun)->get(route('tryout.student.start', $sesi));
        $result = TryoutResult::where('tryout_session_id', $sesi->id)->firstOrFail();
        $this->actingAs($this->akun)->postJson(route('tryout.student.answer', $result), [
            'subject_id' => $subject->id, 'question_id' => $soal->id, 'jawaban' => 'A',
        ]);
        $this->actingAs($this->akun)->post(route('tryout.student.complete', $result));

        return $result->fresh();
    }

    // ------------------------------------------------------------- pembaca

    public function test_ekspor_csv_cbt_memuat_nilai_dan_pelanggaran(): void
    {
        $session = $this->selesaikanCbt();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cbt.sessions.export', [$session, 'csv']))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Sesi', $csv);
        $this->assertStringContainsString('Siswa Pembaca', $csv);
        $this->assertStringContainsString('RDR001', $csv);
        // 1 benar dari 2 → 50.
        $this->assertStringContainsString('50', $csv);
    }

    public function test_halaman_hasil_tryout_admin_menampilkan_nilai_benar(): void
    {
        $result = $this->selesaikanTryout();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.tryout.results.index'))
            ->assertOk()
            ->assertSee('Siswa Pembaca')
            ->assertSee('Paket Pembaca');

        // Nilai sebenarnya (persentase 100%) & hitungan jawaban harus tampil,
        // bukan 0.0 dan kosong seperti sebelum accessor ditambahkan.
        $html = $response->getContent();
        $this->assertStringContainsString('100.0', $html);
        $this->assertSame(100.0, $result->skor_total);
        $this->assertSame(100.0, $result->persentase);
        $this->assertSame(1.0, $result->nilai_total);
        $this->assertSame(1, $result->jumlah_benar);
        $this->assertSame(0, $result->jumlah_salah);
        $this->assertSame(0, $result->jumlah_kosong);
        $this->assertNotNull($result->waktu_pengerjaan_detik);

        $this->actingAs($this->admin)
            ->get(route('admin.tryout.results.show', $result))
            ->assertOk();
    }

    public function test_dasbor_ortu_menampilkan_hasil_cbt_anak(): void
    {
        $this->selesaikanCbt();

        $ortu = User::factory()->ortu()->create(['terhubung_dengan' => [$this->siswa->id]]);

        $this->actingAs($ortu)
            ->get(route('ortu.dashboard'))
            ->assertOk()
            ->assertSee('Ujian IPA');
    }

    /**
     * Langkah 5b: pembaca pengganti murni `exam_*` harus menghasilkan laporan
     * yang sama dengan jalur lama, supaya tabel `cbt_*` boleh dihapus (langkah 6).
     */
    public function test_ekspor_dari_engine_sama_dengan_ekspor_lama(): void
    {
        $session = $this->mulaiCbt();

        // Catat satu pelanggaran (sebelum submit) agar kolomnya juga dibandingkan.
        $result = CbtResult::firstOrFail();
        $this->actingAs($this->akun)
            ->postJson(route('cbt.student.integrity', $result), ['type' => 'tab_hidden'])
            ->assertOk();

        $this->actingAs($this->akun)->post(route('cbt.student.submit', $result));

        $lama = $this->parseCsv($this->actingAs($this->admin)
            ->get(route('admin.cbt.sessions.export', [$session, 'csv']))
            ->assertOk()
            ->streamedContent());

        $baru = $this->parseCsv($this->actingAs($this->admin)
            ->get(route('admin.cbt.sessions.export-engine', [$session, 'csv']))
            ->assertOk()
            ->streamedContent());

        $this->assertCount(2, $baru); // header + 1 peserta
        $this->assertSame($lama[0], $baru[0]);

        // Seluruh kolom (termasuk Pelanggaran) harus identik.
        $this->assertSame($lama[1], $baru[1], 'Laporan engine harus identik dengan laporan lama.');
        $this->assertSame('Siswa Pembaca', $baru[1][1]);
        $this->assertSame('50', (string) $baru[1][6]);
        $this->assertSame('1', (string) $baru[1][7]);
    }

    /** Pelanggaran harus tercermin ke `exam_violations`. */
    public function test_pelanggaran_cbt_masuk_engine(): void
    {
        $this->mulaiCbt();
        $result = CbtResult::firstOrFail();

        $this->actingAs($this->akun)
            ->postJson(route('cbt.student.integrity', $result), ['type' => 'fullscreen_exit'])
            ->assertOk();

        $this->assertSame(1, \App\Models\Exam\Violation::count());
        $violation = \App\Models\Exam\Violation::firstOrFail();
        $this->assertSame('fullscreen_exit', $violation->tipe);
        $this->assertSame('cbt_violations', $violation->sumber_tabel);
        $this->assertSame('cbt_results', $violation->attempt->sumber_tabel);
    }

    /** Flag mati: pelanggaran tidak boleh menyentuh `exam_violations`. */
    public function test_pelanggaran_tidak_masuk_engine_saat_flag_mati(): void
    {
        config(['simantap.exam_engine_cbt' => false]);
        $this->mulaiCbt();
        $result = CbtResult::firstOrFail();

        $this->actingAs($this->akun)
            ->postJson(route('cbt.student.integrity', $result), ['type' => 'tab_hidden'])
            ->assertOk();

        $this->assertSame(1, \App\Models\CbtViolation::count());
        $this->assertSame(0, \App\Models\Exam\Violation::count());
    }

    // ------------------------------------------- dasbor: parity ke engine

    public function test_ringkasan_cbt_guru_dan_sekolah_engine_sama_dengan_pembaca_lama(): void
    {
        $this->selesaikanCbt();

        $engineGuru = app(ExamDashboardService::class)->ringkasanCbtGuru($this->guru->id);

        $this->assertSame(
            \App\Models\CbtQuestionBank::where('guru_id', $this->guru->id)->count(),
            $engineGuru['total_bank']
        );
        $this->assertSame(
            \App\Models\CbtTestSession::where('guru_id', $this->guru->id)
                ->whereIn('status', ['published', 'running'])->count(),
            $engineGuru['sesi_aktif']
        );
        $this->assertSame(
            \App\Models\CbtTestSession::where('guru_id', $this->guru->id)->where('status', 'completed')->count(),
            $engineGuru['sesi_selesai']
        );
        $this->assertSame(
            \App\Models\CbtResult::whereHas('session', fn ($q) => $q->where('guru_id', $this->guru->id))->count(),
            $engineGuru['total_peserta']
        );

        $legacyTotal = \App\Models\CbtResult::count();
        $legacyAvg = (float) (\App\Models\CbtResult::avg('score') ?? 0);
        $legacyPass = \App\Models\CbtResult::where('score', '>=', 70)->count() / max($legacyTotal, 1) * 100;

        $engine = app(ExamDashboardService::class)->ringkasanCbtSekolah();

        $this->assertSame(\App\Models\CbtTestSession::count(), $engine['total_sessions']);
        $this->assertSame(
            \App\Models\CbtTestSession::whereIn('status', ['published', 'running'])->count(),
            $engine['ongoing']
        );
        $this->assertSame(\App\Models\CbtTestSession::where('status', 'completed')->count(), $engine['completed']);
        $this->assertSame($legacyTotal, $engine['total_participants']);
        $this->assertEqualsWithDelta($legacyAvg, $engine['avg_score'], 0.01);
        $this->assertEqualsWithDelta($legacyPass, $engine['pass_rate'], 0.01);
    }

    public function test_hasil_cbt_terbaru_engine_sama_dengan_pembaca_lama(): void
    {
        $this->selesaikanCbt();

        $legacy = \App\Models\CbtResult::where('siswa_id', $this->siswa->id)
            ->with('session.bank')
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'judul' => $r->session?->title ?? '-',
                'mata_pelajaran' => $r->session?->bank?->subject ?? '-',
                'score' => (float) ($r->score ?? 0),
                'lulus' => (float) ($r->score ?? 0) >= 70,
            ])
            ->values();

        $engine = app(ExamDashboardService::class)->hasilCbtTerbaru($this->siswa->id);

        $this->assertSame($legacy->count(), $engine->count());
        $this->assertSame($legacy[0]['judul'], $engine[0]['judul']);
        $this->assertSame($legacy[0]['mata_pelajaran'], $engine[0]['mata_pelajaran']);
        $this->assertSame($legacy[0]['score'], $engine[0]['score']);
        $this->assertSame($legacy[0]['lulus'], $engine[0]['lulus']);
        $this->assertNotNull($engine[0]['submitted_at']);
    }

    public function test_ujian_tersedia_engine_sama_dengan_pembaca_lama(): void
    {
        $this->mulaiCbt();

        $legacy = \App\Models\CbtTestSession::with('bank')
            ->whereIn('status', ['published', 'running'])
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>', now())
            ->orderBy('opens_at')
            ->limit(50)
            ->get()
            ->filter(fn ($s) => in_array($this->siswa->id, $s->participant_ids ?? []))
            ->take(3)
            ->values();

        $engine = app(ExamDashboardService::class)->ujianTersedia($this->siswa);

        $this->assertSame($legacy->count(), $engine->count());
        $this->assertSame($legacy[0]->title, $engine[0]->nama);
        $this->assertSame($legacy[0]->bank?->subject, $engine[0]->bank?->mata_pelajaran);
    }

    public function test_statistik_tryout_engine_sama_dengan_halaman_statistik_lama(): void
    {
        $result = $this->selesaikanTryout();

        $legacy = $this->actingAs($this->admin)
            ->get(route('admin.tryout.statistics'))
            ->assertOk()
            ->viewData('stats');

        $engine = app(ExamReportService::class)->ringkasanTryout();

        $this->assertSame($legacy['total_packages'], $engine['total_packages']);
        $this->assertSame($legacy['total_sessions'], $engine['total_sessions']);
        $this->assertSame($legacy['active_sessions'], $engine['active_sessions']);
        $this->assertSame($legacy['total_results'], $engine['total_results']);
        $this->assertEqualsWithDelta((float) $legacy['average_pass_rate'], (float) $engine['average_pass_rate'], 0.01);

        $baris = app(ExamReportService::class)->hasilSesiTryout($result->session);
        $this->assertCount(1, $baris);
        $this->assertSame('Siswa Pembaca', $baris[0]['peserta']);
        $this->assertSame(100.0, $baris[0]['persentase']);
        $this->assertTrue($baris[0]['lulus']);
    }

    // ---------------------------------- langkah 6b: provider dasbor berflag

    /**
     * Provider dasbor harus mengembalikan bentuk & angka yang sama dari kedua
     * sumber data, supaya flag `exam_engine_readers` bisa dinyalakan tanpa
     * mengubah tampilan.
     */
    public function test_provider_dasbor_parity_legacy_vs_engine(): void
    {
        $this->selesaikanCbt();

        $provider = app(\App\Services\Exam\CbtDashboardProvider::class);

        config(['simantap.exam_engine_readers' => false]);
        $lama = $this->potretDasbor($provider);

        config(['simantap.exam_engine_readers' => true]);
        $baru = $this->potretDasbor($provider);

        $this->assertSame($lama, $baru, 'Provider dasbor harus identik pada kedua sumber data.');
        $this->assertCount(1, $baru['ujian']);
        $this->assertSame('Ujian IPA', $baru['hasil'][0]['judul']);
    }

    /**
     * Bukti pembaca benar-benar lepas dari tabel lama: kosongkan `cbt_*`,
     * data dasbor harus tetap tampil dari `exam_*`.
     */
    public function test_provider_dasbor_tetap_benar_setelah_tabel_lama_dikosongkan(): void
    {
        $this->selesaikanCbt();

        \App\Models\CbtResult::query()->delete();
        \App\Models\CbtTestSession::query()->delete();
        \App\Models\CbtQuestionBank::query()->delete();

        $provider = app(\App\Services\Exam\CbtDashboardProvider::class);
        config(['simantap.exam_engine_readers' => true]);

        $potret = $this->potretDasbor($provider);

        $this->assertCount(1, $potret['ujian']);
        $this->assertSame('Ujian IPA', $potret['ujian'][0]['judul']);
        $this->assertSame('IPA', $potret['ujian'][0]['mapel']);
        $this->assertSame('Ujian IPA', $potret['hasil'][0]['judul']);
        $this->assertSame(50.0, $potret['hasil'][0]['score']);
        $this->assertSame(1, $potret['guru']['total_bank']);
        $this->assertSame(1, $potret['sekolah']['total_participants']);
        $this->assertSame(50.0, $potret['sekolah']['avg_score']);
    }

    /** Keempat dasbor peran harus tetap tampil saat reader engine aktif. */
    public function test_dasbor_peran_tampil_dengan_reader_engine(): void
    {
        $this->selesaikanCbt();
        config(['simantap.exam_engine_readers' => true]);

        $this->actingAs($this->akun)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee('Ujian IPA');

        $this->actingAs($this->guru)
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee('Manajemen Ujian CBT');

        $this->actingAs(User::factory()->kepsek()->create())
            ->get(route('kepsek.dashboard'))
            ->assertOk()
            ->assertSee('Analisis CBT Sekolah');

        $this->actingAs(User::factory()->ortu()->create(['terhubung_dengan' => [$this->siswa->id]]))
            ->get(route('ortu.dashboard'))
            ->assertOk()
            ->assertSee('Ujian IPA');
    }

    /**
     * Potret ternormalisasi dari provider (tanggal diformat agar dua cabang
     * bisa dibandingkan tanpa peduli objek Carbon).
     *
     * @return array<string, mixed>
     */
    private function potretDasbor(\App\Services\Exam\CbtDashboardProvider $provider): array
    {
        $waktu = fn ($nilai) => $nilai instanceof \DateTimeInterface
            ? $nilai->format('Y-m-d H:i')
            : $nilai;

        return [
            'ujian' => array_map(
                fn (array $u) => [...$u, 'closes_at' => $waktu($u['closes_at'])],
                $provider->ujianTersedia($this->siswa)
            ),
            'hasil' => array_map(
                fn (array $h) => [...$h, 'submitted_at' => $waktu($h['submitted_at'])],
                $provider->hasilTerbaru($this->siswa->id)
            ),
            'guru' => $provider->ringkasanGuru($this->guru->id),
            'sekolah' => $provider->ringkasanSekolah(),
        ];
    }

    /** Provider daftar hasil Tryout harus identik pada kedua sumber data. */
    public function test_daftar_hasil_tryout_provider_parity_legacy_vs_engine(): void
    {
        $this->selesaikanTryout();

        $provider = app(\App\Services\Exam\TryoutResultProvider::class);

        config(['simantap.exam_engine_readers' => false]);
        $lama = $provider->daftar([]);

        config(['simantap.exam_engine_readers' => true]);
        $baru = $provider->daftar([]);

        $this->assertSame($lama['ringkasan'], $baru['ringkasan']);
        $this->assertCount(1, $baru['hasil']->items());

        $lamaBaris = $lama['hasil']->items()[0];
        $baruBaris = $baru['hasil']->items()[0];

        foreach (['siswa_nama', 'siswa_kelas', 'sesi_nama', 'paket_nama', 'skor_total', 'jumlah_benar', 'jumlah_salah', 'jumlah_kosong', 'lulus'] as $kolom) {
            $this->assertSame($lamaBaris[$kolom], $baruBaris[$kolom], "Kolom {$kolom} harus sama.");
        }
        $this->assertEqualsWithDelta($lamaBaris['waktu_detik'], $baruBaris['waktu_detik'], 2);
    }

    /**
     * Daftar hasil harus tetap benar saat `tryout_results` dikosongkan, asal
     * katalog sesi/paket (masih dibaca filter) tetap ada.
     */
    public function test_daftar_hasil_tryout_engine_tetap_benar_setelah_hasil_lama_dikosongkan(): void
    {
        $this->selesaikanTryout();
        TryoutResult::query()->delete();
        config(['simantap.exam_engine_readers' => true]);

        $this->assertSame(1, app(\App\Services\Exam\TryoutResultProvider::class)->daftar([])['ringkasan']['total']);

        $this->actingAs($this->admin)
            ->get(route('admin.tryout.results.index'))
            ->assertOk()
            ->assertSee('Siswa Pembaca')
            ->assertSee('Paket Pembaca')
            ->assertSee('100.0');
    }

    /** Halaman rincian versi engine menampilkan nilai yang sama. */
    public function test_rincian_hasil_tryout_engine_tetap_benar_setelah_hasil_lama_dikosongkan(): void
    {
        $this->selesaikanTryout();
        TryoutResult::query()->delete();
        config(['simantap.exam_engine_readers' => true]);

        $attempt = \App\Models\Exam\Attempt::firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.tryout.results.engine.show', $attempt))
            ->assertOk()
            ->assertSee('Siswa Pembaca')
            ->assertSee('Paket Pembaca')
            ->assertSee('100.0')
            ->assertSee('Lulus');
    }

    /** Statistik Tryout dari engine tetap benar tanpa tabel hasil lama. */
    public function test_statistik_tryout_engine_tetap_benar_setelah_hasil_lama_dikosongkan(): void
    {
        $this->selesaikanTryout();
        TryoutResult::query()->delete();
        config(['simantap.exam_engine_readers' => true]);

        $stats = $this->actingAs($this->admin)
            ->get(route('admin.tryout.statistics'))
            ->assertOk()
            ->assertSee('100.0%')
            ->viewData('stats');

        $this->assertSame(1, $stats['total_packages']);
        $this->assertSame(1, $stats['total_sessions']);
        $this->assertSame(1, $stats['total_results']);
        $this->assertEqualsWithDelta(100.0, (float) $stats['average_pass_rate'], 0.01);
    }

    /** @return array<int, array<int, string|null>> */
    private function parseCsv(string $csv): array
    {
        $rows = [];
        foreach (array_filter(explode("\n", trim($csv))) as $line) {
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }
}
