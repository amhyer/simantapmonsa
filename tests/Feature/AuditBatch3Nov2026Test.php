<?php

namespace Tests\Feature;

use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\DapodikSyncLog;
use App\Models\JadwalPelajaran;
use App\Models\Kehadiran;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\PresensiSesiQr;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Models\TemaKokurikuler;
use App\Models\Tka\AssessmentItem;
use App\Models\Tka\Dimension;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Admin\SchoolDataResetService;
use App\Services\Navigation\RoleTaskSummaryService;
use App\Services\Navigation\SchoolDataAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditBatch3Nov2026Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function buatSekolah(string $npsn, ?string $customDomain = null): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => $npsn,
            'nama_sekolah' => 'Sekolah '.$npsn,
            'subdomain' => 'sekolah-'.$npsn,
            'domain_kustom' => $customDomain,
            'aktif' => true,
        ]);
    }

    public function test_p0_1_admin_sekolah_tidak_bisa_eskalasi_pengawas_lintas_sekolah_via_terhubung_dengan(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $sekolahA = $this->buatSekolah('60100001');
        $sekolahB = $this->buatSekolah('60100002');

        $adminB = User::factory()->admin()->create(['tenant_id' => $sekolahB->id]);
        $siswaB = TenantContext::run($sekolahB->id, fn () => Siswa::create([
            'nis' => 'SIS-B-01',
            'nama_peserta_didik' => 'Siswa Sekolah B',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        // Admin Sekolah B mencoba membuat akun pengawas dengan menyisipkan terhubung_dengan
        $this->actingAs($adminB)->post(route('admin.users.store'), [
            'nama_lengkap' => 'Pengawas Buatan Admin B',
            'nama_pengguna' => 'pengawas_b',
            'kata_sandi' => 'SandiKuat#2026',
            'peran' => 'pengawas',
            'terhubung_dengan' => [$siswaB->id],
            'aktif' => true,
        ])->assertRedirect(route('admin.users.index'));

        $pengawasB = User::withoutGlobalScopes()->where('nama_pengguna', 'pengawas_b')->firstOrFail();
        $this->assertSame([], $pengawasB->terhubung_dengan ?? []);

        // Update akun pengawas oleh Admin Sekolah B juga wajib memaksa terhubung_dengan = []
        $this->actingAs($adminB)->put(route('admin.users.update', $pengawasB), [
            'nama_lengkap' => 'Pengawas Buatan Admin B Update',
            'nama_pengguna' => 'pengawas_b',
            'peran' => 'pengawas',
            'terhubung_dengan' => [$siswaB->id],
            'aktif' => true,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame([], $pengawasB->fresh()->terhubung_dengan ?? []);

        $pengawasB->forceFill(['force_password_change' => false])->save();

        // Pengawas tersebut tidak dapat beralih ke Sekolah A
        $this->actingAs($pengawasB->fresh())
            ->post(route('pengawas.sekolah.ganti'), ['tenant_id' => $sekolahA->id])
            ->assertForbidden();
    }

    public function test_p0_2_hapus_guru_dan_tenant_dengan_cbt_attempts_answers_siswa_lain_tidak_crash_fk(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $tenant = $this->buatSekolah('60200001');

        $guruPembuatBank = User::factory()->guru()->create(['tenant_id' => $tenant->id]);
        $guruPemilikSesi = User::factory()->guru()->create(['tenant_id' => $tenant->id]);
        $siswaUser = User::factory()->siswa()->create(['tenant_id' => $tenant->id]);
        $siswa = TenantContext::run($tenant->id, fn () => Siswa::create([
            'nis' => 'CBT-602',
            'nama_peserta_didik' => 'Siswa Ujian CBT',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));

        $jadwal = TenantContext::run($tenant->id, fn () => JadwalPelajaran::create([
            'guru_id' => $guruPemilikSesi->id,
            'kelas' => '6A',
            'mata_pelajaran' => 'Matematika',
            'hari' => 'Senin',
            'jam_mulai' => '07:30',
            'jam_selesai' => '09:00',
        ]));
        $kehadiranMapelId = DB::table('kehadiran_mapel')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'jadwal_pelajaran_id' => $jadwal->id,
            'siswa_id' => $siswa->id,
            'guru_id' => $guruPemilikSesi->id,
            'tanggal' => now()->toDateString(),
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'status' => 'H',
            'metode' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bank = TenantContext::run($tenant->id, fn () => CbtQuestionBank::create([
            'guru_id' => $guruPembuatBank->id,
            'title' => 'Bank CBT P0-2',
            'subject' => 'Matematika',
            'grade_level' => '6',
        ]));
        $question = CbtQuestion::create([
            'bank_id' => $bank->id,
            'prompt' => '2 + 2 = ?',
            'options' => ['A' => '3', 'B' => '4'],
            'correct_answer' => 'B',
        ]);
        $session = TenantContext::run($tenant->id, fn () => CbtTestSession::create([
            'guru_id' => $guruPemilikSesi->id,
            'bank_id' => $bank->id,
            'title' => 'Sesi CBT P0-2',
            'kelas' => '6A',
            'duration_minutes' => 30,
            'status' => 'published',
            'opens_at' => now()->subMinutes(10),
            'closes_at' => now()->addMinutes(20),
        ]));

        // Siswa (user_id != guru_id) mengerjakan sesi ujian milik guruPemilikSesi
        $result = TenantContext::run($tenant->id, fn () => CbtResult::create([
            'session_id' => $session->id,
            'user_id' => $siswaUser->id,
            'siswa_id' => $siswa->id,
            'started_at' => now(),
            'deadline_at' => now()->addMinutes(30),
            'answers' => [$question->id => 'B'],
        ]));
        DB::table('cbt_violations')->insert([
            'result_id' => $result->id,
            'type' => 'tab_switch',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Hapus guruPemilikSesi meskipun bank dibuat guru lain, kehadiran_mapel tercatat, dan cbt_results dimiliki siswaUser
        $this->actingAs($superAdmin)
            ->delete(route('superadmin.users.destroy', $guruPemilikSesi))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $guruPemilikSesi->id]);
        $this->assertDatabaseMissing('kehadiran_mapel', ['id' => $kehadiranMapelId]);
        $this->assertDatabaseMissing('cbt_violations', ['result_id' => $result->id]);
        $this->assertDatabaseMissing('cbt_results', ['id' => $result->id]);
        $this->assertDatabaseMissing('cbt_test_sessions', ['id' => $session->id]);
    }

    public function test_p0_3_school_data_reset_service_menghapus_rantai_fk_berurutan_dan_mempertahankan_sekolah_settings(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $tenant = $this->buatSekolah('60300001');
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $guru = User::factory()->guru()->create(['tenant_id' => $tenant->id]);
        $siswaUser = User::factory()->siswa()->create(['tenant_id' => $tenant->id]);

        $settings = TenantContext::run($tenant->id, fn () => SekolahSettings::create([
            'tenant_id' => $tenant->id,
            'guru_id' => $guru->id,
            'nama_sekolah' => 'SDN Registrasi Awal',
            'npsn' => '60300001',
            'jenjang' => 'SD',
            'kepala_sekolah' => 'Drs. Kepala Sekolah',
            'pengaturan' => ['kunci_input_rapor_global' => true],
        ]));

        $sem = TenantContext::run($tenant->id, fn () => Semester::create([
            'semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]));
        $rombel = TenantContext::run($tenant->id, fn () => Rombel::create([
            'dapodik_id' => 'romb-603-6a',
            'semester_id' => $sem->id,
            'nama_rombel' => '6A',
            'tingkat' => '6',
        ]));
        $siswa = TenantContext::run($tenant->id, fn () => Siswa::create([
            'semester_id' => $sem->id,
            'rombel_id' => $rombel->id,
            'nis' => 'RST-01',
            'nama_peserta_didik' => 'Siswa Reset',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]));
        $jadwal = TenantContext::run($tenant->id, fn () => JadwalPelajaran::create([
            'guru_id' => $guru->id,
            'rombel_id' => $rombel->id,
            'kelas' => '6A',
            'mata_pelajaran' => 'IPA',
            'hari' => 'Senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:00',
        ]));
        $kehadiranMapelId = DB::table('kehadiran_mapel')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'jadwal_pelajaran_id' => $jadwal->id,
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'mata_pelajaran' => 'IPA',
            'kelas' => '6A',
            'status' => 'H',
            'metode' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bank = TenantContext::run($tenant->id, fn () => CbtQuestionBank::create([
            'guru_id' => $guru->id,
            'title' => 'Bank Reset',
            'subject' => 'IPA',
        ]));
        $q = CbtQuestion::create([
            'bank_id' => $bank->id,
            'prompt' => 'Soal?',
            'options' => ['A' => '1', 'B' => '2'],
            'correct_answer' => 'A',
        ]);
        $cbtSess = TenantContext::run($tenant->id, fn () => CbtTestSession::create([
            'guru_id' => $guru->id,
            'bank_id' => $bank->id,
            'title' => 'Sesi Reset',
            'kelas' => '6A',
            'duration_minutes' => 30,
            'status' => 'published',
            'opens_at' => now(),
            'closes_at' => now()->addHour(),
        ]));
        $cbtRes = TenantContext::run($tenant->id, fn () => CbtResult::create([
            'session_id' => $cbtSess->id,
            'user_id' => $siswaUser->id,
            'siswa_id' => $siswa->id,
            'started_at' => now(),
            'deadline_at' => now()->addMinutes(30),
            'answers' => [$q->id => 'A'],
        ]));

        $stats = app(SchoolDataResetService::class)->resetSchoolKeepAdmins($tenant->id, $admin->id);

        $this->assertSame(1, $stats['siswa']);
        $this->assertDatabaseMissing('cbt_results', ['id' => $cbtRes->id]);
        $this->assertDatabaseMissing('cbt_test_sessions', ['id' => $cbtSess->id]);
        $this->assertDatabaseMissing('cbt_question_banks', ['id' => $bank->id]);
        $this->assertDatabaseMissing('kehadiran_mapel', ['id' => $kehadiranMapelId]);
        $this->assertDatabaseMissing('jadwal_pelajaran', ['id' => $jadwal->id]);
        $this->assertDatabaseMissing('siswa', ['id' => $siswa->id]);
        $this->assertDatabaseMissing('rombel', ['id' => $rombel->id]);

        // Identitas dasar sekolah tetap dipertahankan
        $freshSettings = SekolahSettings::withoutGlobalScopes()->find($settings->id);
        $this->assertNotNull($freshSettings);
        $this->assertSame('SDN Registrasi Awal', $freshSettings->nama_sekolah);
        $this->assertSame('60300001', $freshSettings->npsn);
        $this->assertSame($admin->id, (int) $freshSettings->guru_id);
    }

    public function test_p0_4_hapus_akun_login_siswa_tidak_menghapus_data_pokok_nilai_dan_rapor_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();

        $siswa = Siswa::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'SIS-KEEP-99',
            'nama_peserta_didik' => 'Siswa Arsip Aman',
            'kelas' => '6A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        $akunSiswa = User::factory()->siswa()->create([
            'nama_pengguna' => 'SIS-KEEP-99',
            'nama_lengkap' => 'Siswa Arsip Aman',
        ]);

        $nilai = Nilai::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa->nama_peserta_didik,
            'kelas' => '6A',
            'mata_pelajaran' => 'Matematika',
            'judul_penilaian' => 'Ulangan Harian 1',
            'jenis' => 'Formatif',
            'nilai' => 92,
            'tanggal' => now()->toDateString(),
        ]);
        $erapor = NilaiErapot::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
            'nilai_akhir' => 90,
            'predikat' => 'A',
        ]);
        $kehadiran = Kehadiran::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa->nama_peserta_didik,
            'tanggal' => now()->toDateString(),
            'status' => 'H',
            'kelas' => '6A',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $akunSiswa))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $akunSiswa->id]);
        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('nilai', ['id' => $nilai->id, 'nilai' => 92]);
        $this->assertDatabaseHas('nilai_erapor', ['id' => $erapor->id, 'nilai_akhir' => 90]);
        $this->assertDatabaseHas('kehadiran', ['id' => $kehadiran->id, 'status' => 'H']);
    }

    public function test_p0_5_identify_tenant_mendukung_www_app_dan_domain_sch_id_dengan_benar(): void
    {
        Cache::flush();
        config([
            'simantap.multi_tenant_enabled' => true,
            'app.url' => 'https://simantap.sch.id',
        ]);

        $tenantSub = $this->buatSekolah('60500001');
        $tenantSub->update(['subdomain' => 'sdn1']);

        $tenantCustom = $this->buatSekolah('60500002', 'ujian.sdn2makassar.sch.id');

        // 1. Domain pusat (simantap.sch.id, www.simantap.sch.id, app.simantap.sch.id) -> 200 OK, bukan 404
        $this->get('https://simantap.sch.id/login')->assertOk();
        $this->get('https://www.simantap.sch.id/login')->assertOk();
        $this->get('https://app.simantap.sch.id/login')->assertOk();

        // 2. Subdomain valid di bawah .simantap.sch.id -> 200 OK
        $this->get('https://sdn1.simantap.sch.id/login')->assertOk();

        // 3. Subdomain tidak dikenal di bawah .simantap.sch.id -> 404
        $this->get('https://fiktif.simantap.sch.id/login')->assertNotFound();

        // 4. Custom domain terdaftar -> 200 OK; custom domain asing -> 404
        $this->get('https://ujian.sdn2makassar.sch.id/login')->assertOk();
        $this->get('https://ujian.tidak-terdaftar.sch.id/login')->assertNotFound();
    }

    public function test_p1_6_pengawas_multi_sekolah_binaan_berpindah_tenant_scope_dengan_mulus(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $sekolahA = $this->buatSekolah('60600001');
        $sekolahB = $this->buatSekolah('60600002');

        $guruA = User::factory()->guru()->create(['tenant_id' => $sekolahA->id, 'nama_lengkap' => 'Guru Binaan A']);
        $guruB1 = User::factory()->guru()->create(['tenant_id' => $sekolahB->id, 'nama_lengkap' => 'Guru Binaan B1']);
        $guruB2 = User::factory()->guru()->create(['tenant_id' => $sekolahB->id, 'nama_lengkap' => 'Guru Binaan B2']);

        // Super Admin menugaskan Pengawas ke Sekolah A & Sekolah B
        $pengawas = User::factory()->pengawas()->create([
            'tenant_id' => $sekolahA->id,
            'terhubung_dengan' => [$sekolahA->id, $sekolahB->id],
        ]);

        // Ganti konteks ke Sekolah B
        $this->actingAs($pengawas)
            ->post(route('pengawas.sekolah.ganti'), ['tenant_id' => $sekolahB->id])
            ->assertRedirect()
            ->assertSessionHas('pengawas_active_tenant_id', $sekolahB->id);

        // Buka dasbor pengawas: harus menampilkan data Sekolah B (2 guru), bukan 0 karena bentrok TenantScope
        $res = $this->actingAs($pengawas)
            ->withSession(['pengawas_active_tenant_id' => $sekolahB->id])
            ->get(route('pengawas.dashboard'));

        $res->assertOk()
            ->assertViewHas('activeTenantId', $sekolahB->id)
            ->assertSee('Guru Binaan B1')
            ->assertSee('Guru Binaan B2')
            ->assertDontSee('Guru Binaan A');

        // Simpan tanda tangan Pengawas pada konteks Sekolah B tidak menabrak unique constraint Sekolah A
        $this->actingAs($pengawas)
            ->withSession(['pengawas_active_tenant_id' => $sekolahB->id])
            ->post(route('pengawas.ttd.store'), [
                'nama_pengawas' => 'Drs. Pengawas Lintas Binaan',
                'nip_pengawas' => '197501012000031001',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(
            SekolahSettings::withoutGlobalScopes()->where('tenant_id', $sekolahB->id)->exists()
        );
    }

    public function test_p1_7_tenant_backfill_command_membackfill_seluruh_tabel_bisnis_baru(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => null]);
        $guru = User::factory()->guru()->create(['tenant_id' => null]);

        DB::table('mata_pelajaran')->insert([
            'tenant_id' => null,
            'nama' => 'IPAS Backfill',
            'kode' => 'IPAS',
            'jenjang' => 'SD',
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $semId = DB::table('semesters')->insertGetId([
            'tenant_id' => null,
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tanggal_rapor')->insert([
            'tenant_id' => null,
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'tempat' => 'Makassar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tema_kokurikuler')->insert([
            'tenant_id' => null,
            'nama' => 'Tema Backfill',
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dimId = DB::table('tka_dimensions_enhanced')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => null,
            'semester_id' => $semId,
            'kode_dimensi' => 'DIM-BF',
            'nama_dimensi' => 'Dimensi Backfill',
            'kategori' => 'mandiri',
            'bobot' => 1,
            'rubrik_penilaian' => json_encode([1 => 'a']),
            'threshold_siap' => 3,
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tka_assessment_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => null,
            'dimension_id' => $dimId,
            'urutan' => 1,
            'indikator' => 'Indikator Backfill',
            'pertanyaan_penilaian' => 'Pertanyaan Backfill',
            'tipe_jawaban' => 'skala_1_4',
            'bobot_item' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ptk')->insert([
            'tenant_id' => null,
            'dapodik_id' => 'ptk-backfill-001',
            'semester_id' => $semId,
            'nama' => 'PTK Backfill',
            'jenis_ptk' => 'Guru Kelas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('tenant:backfill-existing')->assertExitCode(0);

        $tenant = Tenant::firstOrFail();
        $this->assertNull($superAdmin->fresh()->tenant_id);
        $this->assertSame($tenant->id, (int) $guru->fresh()->tenant_id);
        $this->assertSame(0, DB::table('mata_pelajaran')->whereNull('tenant_id')->count());
        $this->assertSame(0, DB::table('semesters')->whereNull('tenant_id')->count());
        $this->assertSame(0, DB::table('tanggal_rapor')->whereNull('tenant_id')->count());
        $this->assertSame(0, DB::table('tema_kokurikuler')->whereNull('tenant_id')->count());
        $this->assertSame(0, DB::table('tka_dimensions_enhanced')->whereNull('tenant_id')->count());
        $this->assertSame(0, DB::table('tka_assessment_items')->whereNull('tenant_id')->count());
        $this->assertSame(0, DB::table('ptk')->whereNull('tenant_id')->count());
    }

    public function test_p1_8_migrasi_tenant_menggunakan_schema_has_index_dan_idempoten(): void
    {
        $mig1 = require base_path('database/migrations/2026_11_23_170001_add_tenant_id_to_mata_pelajaran_and_semesters.php');
        $mig2 = require base_path('database/migrations/2026_11_23_180001_add_tenant_id_to_remaining_school_tables.php');

        // Re-run up() ketika indeks lama sudah tidak ada & indeks baru sudah ada -> tidak boleh melempar QueryException
        $mig1->up();
        $mig2->up();

        $this->assertTrue(Schema::hasIndex('mata_pelajaran', 'mata_pelajaran_tenant_nama_jenjang_unique'));
        $this->assertTrue(Schema::hasIndex('semesters', 'semesters_tenant_semester_id_unique'));
        $this->assertTrue(Schema::hasIndex('tanggal_rapor', 'tanggal_rapor_tenant_tahun_semester_unique'));
        $this->assertTrue(Schema::hasIndex('tka_dimensions_enhanced', 'tka_dimensions_tenant_kode_unique'));
    }

    public function test_p1_9_validasi_semester_id_dan_filter_tryout_tka_terisolasi_per_tenant(): void
    {
        config(['simantap.multi_tenant_enabled' => true, 'simantap.cbt_enabled' => true]);
        $sekolahA = $this->buatSekolah('60900001');
        $sekolahB = $this->buatSekolah('60900002');

        $adminB = User::factory()->admin()->create(['tenant_id' => $sekolahB->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $sekolahB->id]);

        $semA = TenantContext::run($sekolahA->id, fn () => Semester::create([
            'semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]));
        $dimA = TenantContext::run($sekolahA->id, fn () => Dimension::create([
            'semester_id' => $semA->id,
            'kode_dimensi' => 'DIM-A-609',
            'nama_dimensi' => 'Dimensi A',
            'kategori' => 'mandiri',
            'rubrik_penilaian' => [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'],
        ]));
        $mapelA = TenantContext::run($sekolahA->id, fn () => MataPelajaran::create([
            'nama' => 'Mapel A', 'kode' => 'MPA', 'jenjang' => 'SD', 'aktif' => true,
        ]));
        $cat = Category::create(['nama_kategori' => 'Umum', 'jenis' => 'akademik', 'aktif' => true]);
        $pkgA = TenantContext::run($sekolahA->id, fn () => Package::create([
            'category_id' => $cat->id,
            'kode_paket' => 'PKT-A-609',
            'nama_paket' => 'Paket A',
            'jenjang' => 'SD',
            'tahun_paket' => 2026,
            'durasi_total_menit' => 60,
            'passing_grade' => 70,
            'aktif' => true,
        ]));

        // Guru B ditolak membuat dimensi TKA memakai semester_id milik Sekolah A
        $this->actingAs($guruB)->post(route('tka.dimensions.store'), [
            'semester_id' => $semA->id,
            'kode_dimensi' => 'DIM-B-INJECT',
            'nama_dimensi' => 'Dimensi Injeksi',
            'kategori' => 'mandiri',
            'bobot' => 1,
            'rubrik_penilaian' => [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'],
            'threshold_siap' => 3,
        ])->assertSessionHasErrors('semester_id');

        // Admin B ditolak membuat dimensi TKA, filter earlyWarnings, atau filter Tryout dengan ID milik Sekolah A
        $this->actingAs($adminB)->post(route('admin.tka.dimensions.store'), [
            'semester_id' => $semA->id,
            'kode_dimensi' => 'DIM-ADM-INJECT',
            'nama_dimensi' => 'Dimensi Admin Injeksi',
            'kategori' => 'mandiri',
        ])->assertSessionHasErrors('semester_id');

        $this->actingAs($adminB)
            ->get(route('admin.tka.warnings.index', ['dimension_id' => $dimA->id]))
            ->assertSessionHasErrors('dimension_id');

        $this->actingAs($adminB)
            ->get(route('admin.tryout.packages.index', ['mata_pelajaran_id' => $mapelA->id]))
            ->assertSessionHasErrors('mata_pelajaran_id');

        $this->actingAs($adminB)
            ->get(route('admin.tryout.sessions.index', ['package_id' => $pkgA->id]))
            ->assertSessionHasErrors('package_id');

        $this->actingAs($adminB)
            ->get(route('admin.tryout.results.index', ['package_id' => $pkgA->id]))
            ->assertSessionHasErrors('package_id');
    }

    public function test_p1_10_dan_p2_11_isolasi_notifikasi_failed_jobs_dan_cache_key_dashboard_peta_kelas(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $sekolahA = $this->buatSekolah('61000001');
        $adminA = User::factory()->admin()->create(['tenant_id' => $sekolahA->id]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException',
            'failed_at' => now(),
        ]);

        // P1 #10: Admin sekolah pada mode multi-sekolah tidak boleh menerima bocoran failed_jobs global
        $notifs = TenantContext::run($sekolahA->id, fn () => app(RoleTaskSummaryService::class)->notificationsFor($adminA));
        foreach ($notifs as $n) {
            $this->assertStringNotContainsString('job antrean gagal', $n['teks']);
        }

        // P2 #11: SchoolDataAvailabilityService::forget($tenantId) tidak menghapus cache 'single'
        Cache::put('admin-dashboard:single:v3', ['marker' => 'single'], 120);
        Cache::put('admin-dashboard:tenant:v3:'.$sekolahA->id, ['marker' => 'tenantA'], 120);

        SchoolDataAvailabilityService::forget($sekolahA->id);
        $this->assertNotNull(Cache::get('admin-dashboard:single:v3'));
        $this->assertNull(Cache::get('admin-dashboard:tenant:v3:'.$sekolahA->id));

        // P2 #11: PetaKelasController::toggleKunciSemua menghapus key 'admin-dashboard:tenant:v3:{id}' yang tepat
        Cache::put('admin-dashboard:tenant:v3:'.$sekolahA->id, ['marker' => 'tenantA-2'], 120);
        $this->actingAs($adminA)
            ->post(route('admin.peta-kelas.toggle-kunci-semua'))
            ->assertRedirect();
        $this->assertNull(Cache::get('admin-dashboard:tenant:v3:'.$sekolahA->id));
    }

    public function test_p2_12_13_14_backup_export_post_js_confirm_submitter_dapodik_ptk_dan_profil_mandiri(): void
    {
        // P2 #12: GET /admin/backup/export harus ditolak (405), hanya POST yang diizinkan
        $singleAdmin = User::factory()->admin()->create();
        $this->actingAs($singleAdmin)->get('/admin/backup/export')->assertStatus(405);
        $this->actingAs($singleAdmin)->post(route('admin.backup.export'))->assertOk()->assertJson(['success' => true]);

        // P2 #13: resources/js/app.js mempertahankan event.submitter & membersihkan confirmAccepted
        $appJs = (string) file_get_contents(base_path('resources/js/app.js'));
        $this->assertStringContainsString('const submitter = event.submitter;', $appJs);
        $this->assertStringContainsString('form.requestSubmit(submitter && form.contains(submitter) ? submitter : undefined)', $appJs);
        $this->assertStringContainsString('delete form.dataset.confirmAccepted;', $appJs);

        // P2 #14a: Statistik & daftar PTK tampil di /admin/dapodik pada mode multi-sekolah
        config(['simantap.multi_tenant_enabled' => true]);
        $sekolah = $this->buatSekolah('61400001');
        $adminSekolah = User::factory()->admin()->create(['tenant_id' => $sekolah->id]);
        $sem = TenantContext::run($sekolah->id, fn () => Semester::create([
            'semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil',
        ]));
        TenantContext::run($sekolah->id, fn () => Ptk::create([
            'semester_id' => $sem->id,
            'nama' => 'Ibu Guru Dapodik Multi',
            'nip' => '198801012015032001',
            'jenis_ptk' => 'Guru Kelas',
        ]));

        $resDapodik = $this->actingAs($adminSekolah)->get(route('admin.dapodik.index'));
        $resDapodik->assertOk()
            ->assertSee('Ibu Guru Dapodik Multi')
            ->assertDontSee('PTK tidak tersedia');
        $this->assertSame(1, $resDapodik->viewData('stats')['ptk']);

        // logDetail & logDestroy menolak ID non-numerik dengan 404 (bukan 500 di PostgreSQL)
        $this->actingAs($adminSekolah)->get('/admin/dapodik/log/bukan-angka')->assertNotFound();
        $this->actingAs($adminSekolah)->delete('/admin/dapodik/log/bukan-angka')->assertNotFound();

        // P2 #14b: AccountProfileController menormalisasi email strtolower & mereset sandi_awal_diterbitkan_at
        $guru = User::factory()->guru()->create([
            'tenant_id' => $sekolah->id,
            'kata_sandi' => Hash::make('SandiAwal#123'),
            'force_password_change' => true,
            'sandi_awal_diterbitkan_at' => now(),
        ]);

        $this->actingAs($guru)->put(route('account.profile.password'), [
            'kata_sandi_saat_ini' => 'SandiAwal#123',
            'password' => 'SandiBaru#456',
            'password_confirmation' => 'SandiBaru#456',
        ])->assertRedirect()->assertSessionHas('success');

        $freshGuru = $guru->fresh();
        $this->assertFalse((bool) $freshGuru->force_password_change);
        $this->assertNull($freshGuru->sandi_awal_diterbitkan_at);
        $this->assertTrue(Hash::check('SandiBaru#456', (string) $freshGuru->kata_sandi));

        $this->actingAs($freshGuru)->put(route('account.profile.update'), [
            'email' => '  Guru.Baru@SIMANTAP.SCH.ID ',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('guru.baru@simantap.sch.id', $freshGuru->fresh()->email);
    }
}
