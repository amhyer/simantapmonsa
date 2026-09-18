<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\DapodikConfig;
use App\Models\DapodikSyncLog;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Rombel;
use App\Models\User;
use App\Services\Dapodik\DapodikSyncService;

class DapodikSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('siswa', function ($table) {
            $table->foreignId('guru_id')->nullable()->change();
        });

        Schema::table('sekolah_settings', function ($table) {
            $table->foreignId('guru_id')->nullable()->change();
        });

        Schema::dropIfExists('dapodik_sync_logs');
        Schema::create('dapodik_sync_logs', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('sekolah_npsn', 20)->nullable();
            $table->string('nama_sekolah')->nullable();
            $table->string('tipe')->nullable();
            $table->string('tahun_ajaran', 20)->nullable();
            $table->string('semester', 10)->nullable();
            $table->integer('total_data')->default(0);
            $table->integer('berhasil')->default(0);
            $table->integer('gagal')->default(0);
            $table->integer('diperbarui')->default(0);
            $table->integer('dilewati')->default(0);
            $table->json('errors')->nullable();
            $table->json('summary')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    private function createAdminUser(): User
    {
        return User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi' => bcrypt('password123'),
            'peran' => 'admin',
            'aktif' => true,
        ]);
    }

    private function setupConfig(): DapodikConfig
    {
        DB::table('dapodik_configs')->updateOrInsert(
            ['id' => 'singleton'],
            [
                'npsn' => '12345678',
                'token' => null,
                'host' => 'localhost',
                'port' => 5774,
                'protocol' => 'http',
                'archive_unlisted' => true,
                'allow_insecure_in_production' => false,
                'auto_sync_enabled' => false,
                'auto_sync_interval_hours' => 24,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return DapodikConfig::getInstance();
    }

    private function getSekolahResponse(array $rows): array
    {
        return ['rows' => $rows, 'count' => count($rows)];
    }

    private function getPaginatedResponse(array $rows): array
    {
        return ['rows' => $rows, 'count' => count($rows)];
    }

    private function fakeDapodikApi(array $overrides = []): void
    {
        $sekolah = $overrides['sekolah'] ?? [
            [
                'nama' => 'SD Test',
                'npsn' => '12345678',
                'alamat_jalan' => 'Jl Test No. 1',
                'telepon' => '02112345678',
                'email' => 'sd@test.com',
                'kepala_sekolah' => 'Pak Kepsek',
                'jenjang_pendidikan_id' => 'SD',
            ],
        ];

        $pesertaDidik = $overrides['peserta_didik'] ?? [
            [
                'peserta_didik_id' => 'PD001',
                'nipd' => '001',
                'nisn' => '0001',
                'nama' => 'Ani',
                'jenis_kelamin' => 'P',
                'nama_rombel' => '1.a',
                'rombongan_belajar_id' => 'RB001',
                'semester_id' => '20261',
                'nama_ayah' => 'Pak Ayah',
                'nama_ibu' => 'Bu Ibu',
            ],
            [
                'peserta_didik_id' => 'PD002',
                'nipd' => '002',
                'nisn' => '0002',
                'nama' => 'Budi',
                'jenis_kelamin' => 'L',
                'nama_rombel' => '1.a',
                'rombongan_belajar_id' => 'RB001',
                'semester_id' => '20261',
                'nama_ayah' => 'Pak Ayah2',
                'nama_ibu' => 'Bu Ibu2',
            ],
        ];

        $gtk = $overrides['gtk'] ?? [
            [
                'ptk_id' => 'PTK001',
                'nama' => 'Pak Guru',
                'nuptk' => 'NUPTK001',
                'nip' => 'NIP001',
                'jenis_ptk_id_str' => 'Guru Mapel',
                'status_aktif' => 'aktif',
                'bidang_studi' => 'Matematika',
            ],
        ];

        $rombel = $overrides['rombel'] ?? [
            [
                'rombongan_belajar_id' => 'RB001',
                'nama' => '1.a',
                'tingkat_pendidikan_id' => '1',
                'tingkat_pendidikan_id_str' => '1',
                'ptk_id_str' => 'Pak Guru',
                'semester_id' => '20261',
            ],
        ];

        Http::fake([
            '*getSekolah*' => Http::response($this->getSekolahResponse($sekolah)),
            '*getPesertaDidik*' => Http::response($this->getPaginatedResponse($pesertaDidik)),
            '*getGtk*' => Http::response($this->getPaginatedResponse($gtk)),
            '*getRombonganBelajar*' => Http::response($this->getPaginatedResponse($rombel)),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // 1. Test config save and retrieve
    // ─────────────────────────────────────────────────────────

    public function test_config_save_and_retrieve(): void
    {
        $config = $this->setupConfig();

        $this->assertEquals('12345678', $config->npsn);
        $this->assertEquals('localhost', $config->host);
        $this->assertEquals(5774, $config->port);
        $this->assertEquals('http', $config->protocol);
        $this->assertTrue($config->archive_unlisted);

        $retrieved = DapodikConfig::getInstance();
        $this->assertEquals('12345678', $retrieved->npsn);
        $this->assertEquals('localhost', $retrieved->host);
        $this->assertEquals(5774, $retrieved->port);

        DB::table('dapodik_configs')
            ->where('id', 'singleton')
            ->update([
                'npsn' => '87654321',
                'port' => 5775,
                'archive_unlisted' => false,
                'updated_at' => now(),
            ]);

        $fresh = DapodikConfig::getInstance();
        $this->assertEquals('87654321', $fresh->npsn);
        $this->assertEquals(5775, $fresh->port);
        $this->assertFalse($fresh->archive_unlisted);
    }

    // ─────────────────────────────────────────────────────────
    // 2. Test connection to Dapodik (mocked)
    // ─────────────────────────────────────────────────────────

    public function test_connection_to_dapodik_returns_data(): void
    {
        $this->setupConfig();
        $this->fakeDapodikApi();

        $service = new DapodikSyncService();
        $result = $service->preview('20261');

        $this->assertArrayHasKey('sekolah', $result);
        $this->assertArrayHasKey('peserta_didik_count', $result);
        $this->assertEquals(2, $result['peserta_didik_count']);
        $this->assertEquals(1, $result['gtk_count']);
        $this->assertEquals(1, $result['rombongan_belajar_count']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'getSekolah');
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'getPesertaDidik');
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'getGtk');
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'getRombonganBelajar');
        });
    }

    // ─────────────────────────────────────────────────────────
    // 3. Test preview returns data without writing DB
    // ─────────────────────────────────────────────────────────

    public function test_preview_returns_data_without_writing_db(): void
    {
        $this->setupConfig();
        $this->fakeDapodikApi();

        $service = new DapodikSyncService();
        $result = $service->preview('20261');

        $this->assertEquals('20261', $result['semester_id']);
        $this->assertCount(1, $result['sekolah']);
        $this->assertEquals('SD Test', $result['sekolah'][0]['nama']);
        $this->assertEquals(2, $result['peserta_didik_count']);
        $this->assertCount(2, $result['sample_peserta_didik']);
        $this->assertEquals(1, $result['gtk_count']);
        $this->assertEquals(1, $result['rombongan_belajar_count']);

        $this->assertDatabaseCount('siswa', 0);
        $this->assertDatabaseCount('rombel', 0);
        $this->assertDatabaseCount('sekolah_settings', 0);
        $this->assertDatabaseCount('semesters', 0);
    }

    public function test_preview_sample_data_is_limited_to_five(): void
    {
        $this->setupConfig();

        $siswa = collect(range(1, 10))->map(fn ($i) => [
            'peserta_didik_id' => "PD{$i}",
            'nipd' => (string) $i,
            'nisn' => str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'nama' => "Siswa {$i}",
            'jenis_kelamin' => $i % 2 === 0 ? 'L' : 'P',
            'nama_rombel' => '1.a',
            'rombongan_belajar_id' => 'RB001',
            'semester_id' => '20261',
        ])->toArray();

        $this->fakeDapodikApi(['peserta_didik' => $siswa]);

        $service = new DapodikSyncService();
        $result = $service->preview('20261');

        $this->assertEquals(10, $result['peserta_didik_count']);
        $this->assertCount(5, $result['sample_peserta_didik']);
    }

    // ─────────────────────────────────────────────────────────
    // 4. Test dry-run calculates changes without writing
    // ─────────────────────────────────────────────────────────

    public function test_dry_run_calculates_changes_without_writing(): void
    {
        $this->setupConfig();
        $this->fakeDapodikApi();

        $service = new DapodikSyncService();
        $result = $service->dryRun('20261');

        $this->assertEquals('dry-run', $result['mode']);
        $this->assertEquals('20261', $result['semester_id']);

        $this->assertEquals(1, $result['rombel']['baru']);
        $this->assertEquals(0, $result['rombel']['update']);
        $this->assertEquals(0, $result['rombel']['dilewati']);

        $this->assertEquals(1, $result['gtk']['baru']);
        $this->assertEquals(0, $result['gtk']['update']);

        $this->assertEquals(2, $result['siswa']['baru']);
        $this->assertEquals(0, $result['siswa']['update']);

        $this->assertDatabaseCount('siswa', 0);
        $this->assertDatabaseCount('rombel', 0);
        $this->assertDatabaseCount('sekolah_settings', 0);
        $this->assertEquals(1, DB::table('semesters')->count());
        $this->assertDatabaseHas('semesters', ['semester_id' => '20261']);
    }

    public function test_dry_run_detects_existing_records_as_updates(): void
    {
        $this->setupConfig();

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        Rombel::create([
            'dapodik_id' => 'RB001',
            'semester_id' => $semester->id,
            'nama_rombel' => '1.a',
            'tingkat' => '1',
        ]);

        $this->fakeDapodikApi();

        $service = new DapodikSyncService();
        $result = $service->dryRun('20261');

        $this->assertEquals(0, $result['rombel']['baru']);
        $this->assertEquals(1, $result['rombel']['update']);
    }

    // ─────────────────────────────────────────────────────────
    // 5. Test commit sync writes data correctly (idempotent)
    // ─────────────────────────────────────────────────────────

    public function test_commit_syncs_all_entities_correctly(): void
    {
        $this->setupConfig();
        $this->fakeDapodikApi();
        $admin = $this->createAdminUser();
        Auth::login($admin);

        $service = new DapodikSyncService();
        $result = $service->commit('20261');

        $this->assertEquals('commit', $result['mode']);
        $this->assertNotEmpty($result['log_id']);

        $this->assertDatabaseCount('siswa', 2);
        $this->assertDatabaseHas('siswa', [
            'dapodik_id' => 'PD001',
            'nisn' => '0001',
            'nama_peserta_didik' => 'Ani',
            'jenis_kelamin' => 'P',
        ]);
        $this->assertDatabaseHas('siswa', [
            'dapodik_id' => 'PD002',
            'nisn' => '0002',
            'nama_peserta_didik' => 'Budi',
            'jenis_kelamin' => 'L',
        ]);

        $this->assertDatabaseCount('rombel', 1);
        $this->assertDatabaseHas('rombel', [
            'dapodik_id' => 'RB001',
            'nama_rombel' => '1.a',
            'tingkat' => '1',
        ]);

        $this->assertDatabaseCount('sekolah_settings', 1);
        $this->assertDatabaseHas('sekolah_settings', [
            'npsn' => '12345678',
            'nama_sekolah' => 'SD Test',
        ]);

        $this->assertDatabaseCount('semesters', 1);
        $this->assertDatabaseHas('semesters', [
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $this->assertDatabaseHas('users', [
            'nama_lengkap' => 'Pak Guru',
            'nama_pengguna' => 'NIP001',
            'peran' => 'guru',
        ]);

        $this->assertDatabaseHas('dapodik_sync_logs', [
            'user_id' => $admin->id,
            'sekolah_npsn' => '12345678',
            'tipe' => 'semua',
            'total_data' => 4,
        ]);

        $siswa1 = Siswa::where('dapodik_id', 'PD001')->first();
        $this->assertNotNull($siswa1);
        $this->assertEquals('Pak Ayah / Bu Ibu', $siswa1->nama_orang_tua);
    }

    public function test_commit_is_idempotent_no_duplicates(): void
    {
        $this->setupConfig();
        $this->fakeDapodikApi();
        $admin = $this->createAdminUser();
        Auth::login($admin);

        $service = new DapodikSyncService();

        $result1 = $service->commit('20261');
        $this->assertDatabaseCount('siswa', 2);
        $this->assertDatabaseCount('rombel', 1);
        $this->assertDatabaseCount('sekolah_settings', 1);

        $result2 = $service->commit('20261');
        $this->assertDatabaseCount('siswa', 2);
        $this->assertDatabaseCount('rombel', 1);
        $this->assertDatabaseCount('sekolah_settings', 1);

        $this->assertDatabaseHas('siswa', ['dapodik_id' => 'PD001', 'nisn' => '0001']);
        $this->assertDatabaseHas('siswa', ['dapodik_id' => 'PD002', 'nisn' => '0002']);

        $siswa1 = Siswa::where('dapodik_id', 'PD001')->first();
        $this->assertEquals('Ani', $siswa1->nama_peserta_didik);

        $rombel = Rombel::where('dapodik_id', 'RB001')->first();
        $this->assertEquals('1.a', $rombel->nama_rombel);

        $this->assertDatabaseCount('dapodik_sync_logs', 2);
    }

    public function test_commit_updates_existing_records(): void
    {
        $this->setupConfig();
        $admin = $this->createAdminUser();
        Auth::login($admin);

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        Rombel::create([
            'dapodik_id' => 'RB001',
            'semester_id' => $semester->id,
            'nama_rombel' => '1.b',
            'tingkat' => '1',
        ]);

        $this->fakeDapodikApi();

        $service = new DapodikSyncService();
        $result = $service->commit('20261');

        $this->assertEquals(1, $result['rombel']['diperbarui']);
        $this->assertEquals(0, $result['rombel']['berhasil']);

        $rombel = Rombel::where('dapodik_id', 'RB001')->first();
        $this->assertEquals('1.a', $rombel->nama_rombel);
    }

    public function test_commit_creates_sync_log_with_timing(): void
    {
        $this->setupConfig();
        $this->fakeDapodikApi();
        $admin = $this->createAdminUser();
        Auth::login($admin);

        $service = new DapodikSyncService();
        $result = $service->commit('20261');

        $log = DapodikSyncLog::find($result['log_id']);
        $this->assertNotNull($log);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->finished_at);
        $this->assertGreaterThanOrEqual(0, $log->duration_seconds);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertIsArray($log->summary);
        $this->assertArrayHasKey('sekolah', $log->summary);
        $this->assertArrayHasKey('rombel', $log->summary);
        $this->assertArrayHasKey('gtk', $log->summary);
        $this->assertArrayHasKey('siswa', $log->summary);
    }

    // ─────────────────────────────────────────────────────────
    // 6. Test archive only removes old rows
    // ─────────────────────────────────────────────────────────

    public function test_archive_removes_old_students_not_in_remote(): void
    {
        $this->setupConfig();
        $admin = $this->createAdminUser();
        Auth::login($admin);

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        Rombel::create([
            'dapodik_id' => 'RB001',
            'semester_id' => $semester->id,
            'nama_rombel' => '1.a',
            'tingkat' => '1',
        ]);

        Siswa::create([
            'uuid' => Str::uuid(),
            'guru_id' => null,
            'nama_guru' => null,
            'dapodik_id' => 'PD_OLD',
            'semester_id' => $semester->id,
            'nis' => '999',
            'nisn' => '9999',
            'nama_peserta_didik' => 'Siswa Lama',
            'kelas' => '1.a',
            'jenis_kelamin' => 'L',
            'aktif' => true,
            'aktif' => true,
            'rekaman' => [],
        ]);

        $this->fakeDapodikApi([
            'peserta_didik' => [
                [
                    'peserta_didik_id' => 'PD001',
                    'nipd' => '001',
                    'nisn' => '0001',
                    'nama' => 'Ani',
                    'jenis_kelamin' => 'P',
                    'nama_rombel' => '1.a',
                    'rombongan_belajar_id' => 'RB001',
                    'semester_id' => '20261',
                    'nama_ayah' => 'Ayah Ani',
                    'nama_ibu' => 'Ibu Ani',
                ],
            ],
        ]);

        $service = new DapodikSyncService();
        $result = $service->commit('20261');

        $this->assertDatabaseHas('siswa', [
            'dapodik_id' => 'PD_OLD',
            'aktif' => 0,
        ]);
        $this->assertDatabaseMissing('siswa', [
            'dapodik_id' => 'PD_OLD',
            'archived_at' => null,
        ]);

        $this->assertDatabaseHas('siswa', [
            'dapodik_id' => 'PD001',
        ]);
        $this->assertDatabaseMissing('siswa', [
            'dapodik_id' => 'PD001',
            'archived_at' => null,
        ]);
    }

    public function test_archive_removes_old_gtk_not_in_remote(): void
    {
        $this->setupConfig();
        $admin = $this->createAdminUser();
        Auth::login($admin);

        Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $oldGuru = User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => 'Guru Lama',
            'nama_pengguna' => 'NIP_OLD',
            'kata_sandi' => bcrypt('password'),
            'peran' => 'guru',
            'aktif' => true,
        ]);
        $oldGuru->forceFill([
            'aktif' => true,
            'dapodik_id' => 'PTK_OLD',
        ])->save();

        $this->fakeDapodikApi([
            'gtk' => [
                [
                    'ptk_id' => 'PTK001',
                    'nama' => 'Pak Guru',
                    'nuptk' => 'NUPTK001',
                    'nip' => 'NIP001',
                    'jenis_ptk_id_str' => 'Guru Mapel',
                    'status_aktif' => 'aktif',
                ],
            ],
        ]);

        $service = new DapodikSyncService();
        $result = $service->commit('20261');

        $this->assertDatabaseHas('users', [
            'dapodik_id' => 'PTK_OLD',
            'aktif' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'dapodik_id' => 'PTK001',
            'aktif' => 1,
        ]);
    }

    public function test_archive_skipped_when_disabled(): void
    {
        DB::table('dapodik_configs')->updateOrInsert(
            ['id' => 'singleton'],
            [
                'npsn' => '12345678',
                'token' => null,
                'host' => 'localhost',
                'port' => 5774,
                'protocol' => 'http',
                'archive_unlisted' => false,
                'allow_insecure_in_production' => false,
                'auto_sync_enabled' => false,
                'auto_sync_interval_hours' => 24,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $admin = $this->createAdminUser();
        Auth::login($admin);

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        Rombel::create([
            'dapodik_id' => 'RB001',
            'semester_id' => $semester->id,
            'nama_rombel' => '1.a',
            'tingkat' => '1',
        ]);

        Siswa::create([
            'uuid' => Str::uuid(),
            'guru_id' => null,
            'nama_guru' => null,
            'dapodik_id' => 'PD_OLD',
            'semester_id' => $semester->id,
            'nis' => '999',
            'nisn' => '9999',
            'nama_peserta_didik' => 'Siswa Lama',
            'kelas' => '1.a',
            'jenis_kelamin' => 'L',
            'aktif' => true,
            'aktif' => true,
            'rekaman' => [],
        ]);

        $this->fakeDapodikApi([
            'peserta_didik' => [
                [
                    'peserta_didik_id' => 'PD001',
                    'nipd' => '001',
                    'nisn' => '0001',
                    'nama' => 'Ani',
                    'jenis_kelamin' => 'P',
                    'nama_rombel' => '1.a',
                    'rombongan_belajar_id' => 'RB001',
                    'semester_id' => '20261',
                    'nama_ayah' => 'Ayah Ani',
                    'nama_ibu' => 'Ibu Ani',
                ],
            ],
        ]);

        $service = new DapodikSyncService();
        $result = $service->commit('20261');

        $this->assertDatabaseHas('siswa', [
            'dapodik_id' => 'PD_OLD',
            'aktif' => 1,
        ]);
        $this->assertDatabaseHas('siswa', [
            'dapodik_id' => 'PD_OLD',
            'archived_at' => null,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // 7. Test error handling for failed Dapodik connection
    // ─────────────────────────────────────────────────────────

    public function test_connection_failure_throws_runtime_exception(): void
    {
        $this->setupConfig();

        Http::fake(function ($request) {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $service = new DapodikSyncService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tidak dapat terhubung ke Dapodik WebService');

        $service->preview('20261');
    }

    public function test_http_500_after_retries_throws_runtime_exception(): void
    {
        $this->setupConfig();

        Http::fake([
            '*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $service = new DapodikSyncService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Server error from Dapodik API');

        $service->preview('20261');
    }

    public function test_http_400_client_error_throws_runtime_exception(): void
    {
        $this->setupConfig();

        Http::fake([
            '*' => Http::response('Bad Request', 400),
        ]);

        $service = new DapodikSyncService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client error from Dapodik API');

        $service->preview('20261');
    }

    public function test_access_denied_response_throws_runtime_exception(): void
    {
        $this->setupConfig();

        Http::fake([
            '*' => Http::response('Akses ditolak oleh server', 403),
        ]);

        $service = new DapodikSyncService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client error from Dapodik API');

        $service->preview('20261');
    }

    public function test_non_json_response_throws_runtime_exception(): void
    {
        $this->setupConfig();

        Http::fake([
            '*' => Http::response('<html>Not JSON</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $service = new DapodikSyncService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Non-JSON response');

        $service->preview('20261');
    }

    public function test_dapodik_api_error_response_throws_runtime_exception(): void
    {
        $this->setupConfig();

        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 200),
        ]);

        $service = new DapodikSyncService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dapodik API error');

        $service->preview('20261');
    }
}
