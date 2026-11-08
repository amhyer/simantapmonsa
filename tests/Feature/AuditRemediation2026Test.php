<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\Tryout\Subject as TryoutSubject;
use App\Models\Tryout\TryoutQuestion;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\CbtExamService;
use App\Services\Exam\DistributedConsistencyGuard;
use App\Services\Exam\ExamAnswerBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditRemediation2026Test extends TestCase
{
    use RefreshDatabase;

    private array $previousFlag;

    protected function setUp(): void
    {
        $this->previousFlag = [
            getenv('SIMANTAP_CBT_ENABLED'),
            $_ENV['SIMANTAP_CBT_ENABLED'] ?? null,
            $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null,
        ];
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        \Illuminate\Support\Env::enablePutenv();

        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();

        [$envVal, $arrayEnv, $server] = $this->previousFlag;
        $envVal === false ? putenv('SIMANTAP_CBT_ENABLED') : putenv('SIMANTAP_CBT_ENABLED=' . $envVal);
        if ($arrayEnv === null) {
            unset($_ENV['SIMANTAP_CBT_ENABLED']);
        } else {
            $_ENV['SIMANTAP_CBT_ENABLED'] = $arrayEnv;
        }
        if ($server === null) {
            unset($_SERVER['SIMANTAP_CBT_ENABLED']);
        } else {
            $_SERVER['SIMANTAP_CBT_ENABLED'] = $server;
        }
        \Illuminate\Support\Env::enablePutenv();
    }

    private function buatSesiCbtBerjalan(): array
    {
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'AUD001',
            'nama_peserta_didik' => 'Siswa Audit',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $akun = User::factory()->siswa()->create(['nama_pengguna' => $siswa->nis]);
        $bank = CbtQuestionBank::create([
            'guru_id' => $guru->id,
            'title' => 'Bank Audit',
            'subject' => 'Matematika',
        ]);
        $q1 = $bank->questions()->create([
            'prompt' => 'Berapa $2 + 3$?',
            'options' => ['A' => '5', 'B' => '6', 'C' => '7', 'D' => '8'],
            'correct_answer' => 'A',
        ]);
        $q2 = $bank->questions()->create([
            'prompt' => 'Berapa $4 \times 2$?',
            'options' => ['A' => '6', 'B' => '8', 'C' => '10', 'D' => '12'],
            'correct_answer' => 'B',
        ]);

        $session = CbtTestSession::create([
            'guru_id' => $guru->id,
            'bank_id' => $bank->id,
            'title' => 'Ujian Audit',
            'kelas' => '6A',
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addHours(2),
            'duration_minutes' => 60,
        ]);
        app(CbtExamService::class)->publish($session);
        app(CbtExamService::class)->open($session);

        $this->actingAs($akun)->post(route('cbt.student.begin', $session))->assertRedirect();
        $result = CbtResult::where('session_id', $session->id)->firstOrFail();

        return [$guru, $siswa, $akun, $session->fresh(), $result, $q1, $q2];
    }

    public function test_p1_autosave_cbt_bebas_lock_sesi_dan_mendukung_mode_buffer_asinkron(): void
    {
        [$guru, , $akun, $session, $result, $q1, $q2] = $this->buatSesiCbtBerjalan();

        // 1. Mode default: pastikan autosave TIDAK memakai lockForUpdate pada cbt_test_sessions
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($akun)
            ->postJson(route('cbt.student.answer', $result), ['question_id' => $q1->id, 'answer' => 'A'])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $queries = array_column(DB::getQueryLog(), 'query');
        foreach ($queries as $sql) {
            if (str_contains(strtolower($sql), 'cbt_test_sessions')) {
                $this->assertStringNotContainsStringIgnoringCase(
                    'for update',
                    $sql,
                    'Autosave dilarang mengunci baris cbt_test_sessions secara eksklusif.'
                );
            }
        }

        // 2. Mode Redis-first murni (cbt_buffer_async = true): zero write ke cbt_results saat klik opsi
        config(['simantap.cbt_buffer_async' => true]);
        DB::flushQueryLog();

        $this->actingAs($akun)
            ->postJson(route('cbt.student.answer', $result), ['question_id' => $q2->id, 'answer' => 'B'])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $asyncQueries = array_column(DB::getQueryLog(), 'query');
        foreach ($asyncQueries as $sql) {
            $this->assertStringNotContainsStringIgnoringCase(
                'update "cbt_results"',
                $sql,
                'Mode buffer asinkron tidak boleh melakukan UPDATE ke cbt_results per klik jawaban.'
            );
        }

        // Buffer harus menyimpan kedua jawaban skalar
        $buffered = app(ExamAnswerBuffer::class)->all($result->id);
        $this->assertSame('B', $buffered[$q2->id]);

        // Monitoring pengawas tetap membaca jumlah terjawab secara real-time (2 butir)
        Cache::forget("cbt:session:{$session->id}:monitoring:p1");
        $mon = $this->actingAs($guru)->getJson(route('cbt.sessions.api.monitoring', $session))->assertOk()->json();
        $this->assertSame(2, $mon['results']['data'][0]['answered']);

        // Saat submit final, jawaban di buffer digabung ke DB & dinilai 100, lalu buffer dibersihkan
        $this->actingAs($akun)->post(route('cbt.student.submit', $result))->assertRedirect(route('cbt.student.result', $result));
        $result->refresh();
        $this->assertSame(100.0, $result->score);
        $this->assertSame(2, $result->correct_count);
        $this->assertSame([], app(ExamAnswerBuffer::class)->all($result->id));
    }

    public function test_p1_distributed_consistency_guard_mencegah_double_submit_cbt_dan_tryout(): void
    {
        [, , $akun, , $result, $q1] = $this->buatSesiCbtBerjalan();

        $guard = app(DistributedConsistencyGuard::class);
        $cbtLock = Cache::lock($guard->lockKey('cbt-submit', $result->id), 10);
        $this->assertTrue($cbtLock->get());

        try {
            // Request paralel yang datang saat lock masih dipegang harus langsung ditolak 409
            $this->actingAs($akun)
                ->postJson(route('cbt.student.submit', $result), ['answers' => [$q1->id => 'A']])
                ->assertStatus(409);
            $this->assertNull($result->fresh()->submitted_at);
        } finally {
            $cbtLock->release();
        }

        // Setelah lock dilepas, pengumpulan berjalan normal
        $this->actingAs($akun)
            ->post(route('cbt.student.submit', $result), ['answers' => [$q1->id => 'A']])
            ->assertRedirect(route('cbt.student.result', $result));
        $this->assertNotNull($result->fresh()->submitted_at);

        // Verifikasi juga untuk Tryout
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'guru_id' => $guru->id, 'nama_guru' => $guru->nama_lengkap,
            'nis' => 'TRY-LOCK-1', 'nama_peserta_didik' => 'Siswa Tryout Lock',
            'kelas' => '6A', 'jenis_kelamin' => 'P', 'aktif' => true,
        ]);
        $akunTryout = User::factory()->siswa()->create(['nama_pengguna' => $siswa->nis]);
        $semester = Semester::create(['semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']);
        $mapel = MataPelajaran::create(['nama' => 'IPA', 'jenjang' => 'SD']);
        $kategori = Category::create(['nama_kategori' => 'Akademik']);
        $paket = Package::create([
            'category_id' => $kategori->id, 'kode_paket' => 'PKT-LCK', 'nama_paket' => 'Paket Lock',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60,
        ]);
        $sub = TryoutSubject::create(['package_id' => $paket->id, 'mata_pelajaran_id' => $mapel->id, 'durasi_menit' => 30]);
        $soal = TryoutQuestion::create(['tryout_subject_id' => $sub->id, 'tipe_soal' => 'pilihan_ganda', 'pertanyaan' => '1+1?', 'poin' => 1]);
        $soal->options()->createMany([
            ['huruf' => 'A', 'isi_opsi' => '2', 'adalah_benar' => true],
            ['huruf' => 'B', 'isi_opsi' => '3', 'adalah_benar' => false],
        ]);
        $sesiTryout = TryoutSession::create([
            'kode_sesi' => 'SESI-LCK', 'nama_sesi' => 'Sesi Lock',
            'package_id' => $paket->id, 'semester_id' => $semester->id, 'guru_id' => $guru->id,
            'kelas' => '6A', 'tanggal_mulai' => now()->subMinute(), 'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 30, 'status' => 'ongoing',
        ]);

        $this->actingAs($akunTryout)->get(route('tryout.student.start', $sesiTryout))->assertOk();
        $tryoutResult = TryoutResult::where('tryout_session_id', $sesiTryout->id)->firstOrFail();

        $tryoutLock = Cache::lock($guard->lockKey('tryout-submit', $tryoutResult->id), 10);
        $this->assertTrue($tryoutLock->get());
        try {
            $this->actingAs($akunTryout)
                ->postJson(route('tryout.student.complete', $tryoutResult))
                ->assertStatus(409);
            $this->assertNull($tryoutResult->fresh()->waktu_selesai);
        } finally {
            $tryoutLock->release();
        }
    }

    public function test_p2_monitoring_cache_3_detik_hanya_menyimpan_skalar_dan_menghemat_query(): void
    {
        [$guru, , , $session] = $this->buatSesiCbtBerjalan();
        $cacheKey = "cbt:session:{$session->id}:monitoring:p1";
        Cache::forget($cacheKey);

        // Panggilan pertama mengisi cache
        $this->actingAs($guru)->getJson(route('cbt.sessions.api.monitoring', $session))->assertOk();
        $cachedValue = Cache::get($cacheKey);
        $this->assertIsArray($cachedValue);

        // Pastikan TIDAK ADA satu pun objek (Model Eloquent, Collection, Carbon) di dalam cache!
        $containsObject = false;
        array_walk_recursive($cachedValue, function ($item) use (&$containsObject) {
            if (is_object($item)) {
                $containsObject = true;
            }
        });
        $this->assertFalse($containsObject, 'Cache monitoring dilarang menyimpan instance objek/Model Eloquent.');

        // Panggilan kedua dalam rentang TTL 3 detik tidak boleh mengeksekusi query ke cbt_results
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($guru)->getJson(route('cbt.sessions.api.monitoring', $session))->assertOk();
        $queries = implode(' | ', array_column(DB::getQueryLog(), 'query'));
        $this->assertStringNotContainsStringIgnoringCase('cbt_results', $queries);
    }

    public function test_p2_seluruh_tabel_web_ui_memakai_table_wrapper_dan_katex_self_hosted(): void
    {
        $viewsDir = resource_path('views');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        $pelanggaran = [];

        foreach ($iterator as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/vendor/')
                || str_contains($path, '/exports/')
                || str_contains($path, 'pdf')
                || str_contains($path, 'cetak')) {
                continue;
            }

            $content = (string) file_get_contents($path);
            $isWebLayout = (bool) preg_match('/@extends\(\s*[\'"](layouts\.|components\.)/i', $content);
            if (! $isWebLayout) {
                continue;
            }

            if (preg_match_all('/<table\b[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $before = substr($content, max(0, $offset - 300), 300);
                    if (! preg_match('/table-wrapper|table-responsive/i', $before)) {
                        $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                        $pelanggaran[] = str_replace(str_replace('\\', '/', base_path()) . '/', '', $path) . ':' . $line;
                    }
                }
            }
        }

        $this->assertSame([], $pelanggaran, 'Masih ada tabel Web UI tanpa .table-wrapper: ' . implode(', ', $pelanggaran));

        foreach (['cbt/banks/show.blade.php', 'cbt/student/exam.blade.php'] as $rel) {
            $blade = (string) file_get_contents(resource_path('views/' . $rel));
            $this->assertStringNotContainsString('cdn.jsdelivr.net', $blade);
            $this->assertStringContainsString('resources/css/katex.css', $blade);
            $this->assertStringContainsString('resources/js/katex.js', $blade);
        }
    }

    public function test_p3_dapodik_bridge_sync_atomik_per_batch_dengan_savepoint_isolasi_error(): void
    {
        config(['dapodik.batch_size' => 2]);

        $admin = User::factory()->admin()->create();
        $keyGen = ApiKey::generate($admin->id, 'Audit Batch Key');
        $plainKey = $keyGen['plaintext_key'];

        // Kirim 3 data peserta didik (2 batch), dengan 1 item korup (bukan array sehingga memicu TypeError di rowHandler)
        $response = $this->withHeaders(['X-API-Key' => $plainKey])
            ->postJson('/api/dapodik/sync/peserta-didik', [
                'semester_id' => '20261',
                'tahun_ajaran' => '2026/2027',
                'nama_semester' => 'ganjil',
                'data' => [
                    [
                        'peserta_didik_id' => 'pd-valid-1',
                        'nis' => 'PD001',
                        'nisn' => '0011223344',
                        'nama' => 'Siswa Valid Satu',
                        'kelas' => '6A',
                        'jenis_kelamin' => 'L',
                    ],
                    'baris-korup-bukan-array',
                    [
                        'peserta_didik_id' => 'pd-valid-2',
                        'nis' => 'PD002',
                        'nisn' => '0011223345',
                        'nama' => 'Siswa Valid Dua',
                        'kelas' => '6B',
                        'jenis_kelamin' => 'P',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'berhasil' => 2,
            'gagal' => 1,
        ]);

        $this->assertDatabaseHas('siswa', ['nis' => 'PD001']);
        $this->assertDatabaseHas('siswa', ['nis' => 'PD002']);
    }

    public function test_p4_multi_tenancy_mengisolasi_data_antar_sekolah_dan_menolak_cross_tenant(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenantA = Tenant::create([
            'npsn' => '40300001',
            'nama_sekolah' => 'SDN 1 Makassar',
            'subdomain' => 'sdn1-mks',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'npsn' => '40300002',
            'nama_sekolah' => 'SDN 2 Makassar',
            'subdomain' => 'sdn2-mks',
            'aktif' => true,
        ]);

        $guruA = TenantContext::run($tenantA->id, function () {
            $guru = User::factory()->guru()->create(['force_password_change' => false]);
            $siswa = Siswa::create([
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nis' => 'TNA-01',
                'nama_peserta_didik' => 'Siswa Sekolah A',
                'kelas' => '6A',
                'jenis_kelamin' => 'L',
                'aktif' => true,
            ]);
            CbtQuestionBank::create([
                'guru_id' => $guru->id,
                'title' => 'Bank Soal Sekolah A',
                'subject' => 'Matematika',
            ]);
            NilaiErapot::create([
                'siswa_id' => $siswa->id,
                'guru_id' => $guru->id,
                'mata_pelajaran' => 'Matematika',
                'kelas' => '6A',
                'semester' => 'Ganjil',
                'tahun_ajaran' => '2026/2027',
                'nilai_akhir' => 92,
            ]);

            return $guru;
        });

        TenantContext::run($tenantB->id, function () {
            $guru = User::factory()->guru()->create(['force_password_change' => false]);
            Siswa::create([
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nis' => 'TNB-01',
                'nama_peserta_didik' => 'Siswa Sekolah B',
                'kelas' => '6A',
                'jenis_kelamin' => 'P',
                'aktif' => true,
            ]);
            CbtQuestionBank::create([
                'guru_id' => $guru->id,
                'title' => 'Bank Soal Sekolah B',
                'subject' => 'IPA',
            ]);
        });

        // 1. Isolasi ORM: Konteks Tenant A hanya boleh melihat data Tenant A
        TenantContext::run($tenantA->id, function () use ($tenantA) {
            $this->assertSame(1, Siswa::count());
            $this->assertSame('Siswa Sekolah A', Siswa::first()->nama_peserta_didik);
            $this->assertSame($tenantA->id, Siswa::first()->tenant_id);
            $this->assertSame(1, CbtQuestionBank::count());
            $this->assertSame(1, NilaiErapot::count());
        });

        // 2. Isolasi ORM: Konteks Tenant B hanya boleh melihat data Tenant B
        TenantContext::run($tenantB->id, function () {
            $this->assertSame(1, Siswa::count());
            $this->assertSame('Siswa Sekolah B', Siswa::first()->nama_peserta_didik);
            $this->assertSame(1, CbtQuestionBank::count());
            $this->assertSame(0, NilaiErapot::count());
        });

        // 3. Middleware IdentifyTenant: Guru Sekolah A ditolak (403) jika mencoba menyamar ke subdomain Sekolah B
        $this->actingAs($guruA)
            ->withHeaders(['X-Tenant-Subdomain' => 'sdn2-mks'])
            ->get(route('guru.dashboard'))
            ->assertForbidden();

        // 4. Subdomain sekolah yang tidak terdaftar menghasilkan 404
        $this->withHeaders(['X-Tenant-Subdomain' => 'sekolah-fiktif'])
            ->get('/login')
            ->assertNotFound();

        // 5. Guru Sekolah A mengakses subdomain miliknya sendiri berhasil 200 OK
        $this->actingAs($guruA)
            ->withHeaders(['X-Tenant-Subdomain' => 'sdn1-mks'])
            ->get(route('guru.dashboard'))
            ->assertOk();
    }
}
