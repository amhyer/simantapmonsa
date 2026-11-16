<?php

namespace Tests\Feature;

use App\Models\Exam\Attempt;
use App\Models\Exam\Bank;
use App\Models\Exam\Engine;
use App\Models\Exam\Result;
use App\Models\Exam\Session;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tka\AdaptiveSimulation;
use App\Models\Tka\Kesiapan\Audit;
use App\Models\Tka\Kesiapan\Pemetaan;
use App\Models\Tka\Kesiapan\Pengaturan;
use App\Models\Tka\Kesiapan\TindakLanjut;
use App\Models\Tka\StudentAssessment;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Backup\BackupStreamWriter;
use App\Services\BackupRestoreService;
use App\Support\BackupTables;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TkaKesiapanHttpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $kepsekA;

    private User $guruA;

    private User $guruLain;

    private User $guruB;

    private Rombel $rombelA;

    private Rombel $rombelLain;

    private Siswa $siswaA;

    private Siswa $siswaLain;

    private User $akunSiswaA;

    private User $ortuA;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-02-15 08:00:00');

        $this->tenantA = Tenant::create(['uuid' => (string) Str::uuid(), 'npsn' => '40310001', 'nama_sekolah' => 'SD A', 'subdomain' => 'sda', 'aktif' => true]);
        $this->tenantB = Tenant::create(['uuid' => (string) Str::uuid(), 'npsn' => '40310002', 'nama_sekolah' => 'SD B', 'subdomain' => 'sdb', 'aktif' => true]);

        $this->semester = Semester::create(['semester_id' => '20262', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'genap']);

        $this->adminA = User::factory()->admin()->create(['tenant_id' => $this->tenantA->id]);
        $this->kepsekA = User::factory()->kepsek()->create(['tenant_id' => $this->tenantA->id]);
        $this->guruA = User::factory()->guru()->create(['tenant_id' => $this->tenantA->id]);
        $this->guruLain = User::factory()->guru()->create(['tenant_id' => $this->tenantA->id]);
        $this->guruB = User::factory()->guru()->create(['tenant_id' => $this->tenantB->id]);

        $this->rombelA = Rombel::create([
            'tenant_id' => $this->tenantA->id, 'dapodik_id' => 'R-6A', 'semester_id' => $this->semester->id,
            'nama_rombel' => '6A', 'tingkat' => '6', 'guru_id' => $this->guruA->id,
        ]);
        $this->rombelLain = Rombel::create([
            'tenant_id' => $this->tenantA->id, 'dapodik_id' => 'R-6B', 'semester_id' => $this->semester->id,
            'nama_rombel' => '6B', 'tingkat' => '6', 'guru_id' => $this->guruLain->id,
        ]);

        $this->siswaA = Siswa::create([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'guru_id' => $this->guruA->id,
            'rombel_id' => $this->rombelA->id, 'nama_guru' => $this->guruA->nama_lengkap, 'nis' => 'A01',
            'nama_peserta_didik' => 'Rina Kelas Enam', 'kelas' => '6A', 'jenis_kelamin' => 'P', 'aktif' => true,
        ]);
        $this->siswaLain = Siswa::create([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'guru_id' => $this->guruLain->id,
            'rombel_id' => $this->rombelLain->id, 'nama_guru' => $this->guruLain->nama_lengkap, 'nis' => 'A02',
            'nama_peserta_didik' => 'Dodi Kelas Lain', 'kelas' => '6B', 'jenis_kelamin' => 'L', 'aktif' => true,
        ]);

        $this->akunSiswaA = User::factory()->siswa()->create([
            'tenant_id' => $this->tenantA->id, 'nama_pengguna' => 'A01', 'terhubung_dengan' => [$this->siswaA->id],
        ]);
        $this->ortuA = User::factory()->ortu()->create([
            'tenant_id' => $this->tenantA->id, 'terhubung_dengan' => [$this->siswaA->id],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function isiBuktiLengkap(Siswa $s, float $skor = 52.0): void
    {
        foreach (['2027-01-10', '2027-01-17', '2027-01-24'] as $tgl) {
            DB::table('nilai')->insert([
                'uuid' => (string) Str::uuid(), 'tenant_id' => $s->tenant_id, 'guru_id' => $s->guru_id, 'siswa_id' => $s->id,
                'nama_guru' => 'G', 'nama_siswa' => $s->nama_peserta_didik, 'tanggal' => $tgl,
                'jenis' => 'Ulangan Harian', 'judul_penilaian' => 'UH Matematika', 'mata_pelajaran' => 'Matematika', 'nilai' => $skor,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_integrasi_exam_engine_asesmen_dan_simulasi_tka_serta_deduplikasi_percobaan(): void
    {
        $engine = Engine::create(['kode' => 'cbt', 'nama' => 'CBT', 'jenis' => 'cbt', 'aktif' => true]);
        $bank = Bank::create(['tenant_id' => $this->tenantA->id, 'engine_id' => $engine->id, 'guru_id' => $this->guruA->id, 'nama' => 'Bank B.Indo', 'mata_pelajaran' => 'Bahasa Indonesia']);
        $session = Session::create(['tenant_id' => $this->tenantA->id, 'bank_id' => $bank->id, 'guru_id' => $this->guruA->id, 'semester_id' => $this->semester->id, 'nama' => 'Tryout B.Indo', 'kelas' => '6A', 'status' => 'aktif']);

        $a1 = Attempt::create(['tenant_id' => $this->tenantA->id, 'session_id' => $session->id, 'siswa_id' => $this->siswaA->id, 'attempt_number' => 1, 'mulai_at' => '2027-01-10 08:00:00', 'selesai_at' => '2027-01-10 09:00:00', 'status' => 'dinilai', 'nilai_total' => 40, 'nilai_maksimal' => 100]);
        Result::create(['attempt_id' => $a1->id, 'nilai_total' => 40, 'nilai_maksimal' => 100, 'persentase' => 40]);
        $a2 = Attempt::create(['tenant_id' => $this->tenantA->id, 'session_id' => $session->id, 'siswa_id' => $this->siswaA->id, 'attempt_number' => 2, 'mulai_at' => '2027-01-11 08:00:00', 'selesai_at' => '2027-01-11 09:00:00', 'status' => 'dinilai', 'nilai_total' => 80, 'nilai_maksimal' => 100]);
        Result::create(['attempt_id' => $a2->id, 'nilai_total' => 80, 'nilai_maksimal' => 100, 'persentase' => 80]);

        StudentAssessment::create([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'siswa_id' => $this->siswaA->id,
            'semester_id' => $this->semester->id, 'guru_id' => $this->guruA->id, 'tanggal_penilaian' => '2027-01-18',
            'total_score' => 30, 'max_score' => 40, 'persentase' => 75, 'status_kesiapan' => 'siap',
            'skor_per_dimensi' => [['nama_dimensi' => 'Literasi Membaca', 'total_skor' => 30, 'maksimal' => 40]],
        ]);

        AdaptiveSimulation::create([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'siswa_id' => $this->siswaA->id,
            'paket_kode' => 'literasi', 'paket_judul' => 'Simulasi Literasi', 'status' => 'selesai',
            'skor_akhir' => 85, 'skor_indikator' => ['Literasi Membaca' => 85], 'submitted_at' => '2027-01-25 10:00:00',
        ]);

        $res = $this->actingAs($this->guruA)->get(route('guru.tka-kesiapan.siswa', $this->siswaA->uuid));
        $res->assertOk();
        $res->assertSee('Cukup mantap');
        $res->assertSee('Estimasi indeks internal SIMANTAP: 80.0 / 100');
        $res->assertSee('Pemetaan sementara — perlu verifikasi sekolah');
    }

    public function test_guru_hanya_mengakses_siswanya_dan_dapat_menyusun_serta_menyunting_tindak_lanjut(): void
    {
        $this->isiBuktiLengkap($this->siswaA, 50.0);
        $this->isiBuktiLengkap($this->siswaLain, 50.0);

        $index = $this->actingAs($this->guruA)->get(route('guru.tka-kesiapan.index'));
        $index->assertOk()
            ->assertSee('Rina Kelas Enam')
            ->assertDontSee('Dodi Kelas Lain')
            ->assertSee('Hasil ini adalah pemantauan internal untuk membantu perencanaan dukungan belajar');

        // IDOR terhadap rombel milik guru lain atau tenant lain ditolak (403)
        $this->actingAs($this->guruA)->get(route('guru.tka-kesiapan.index', ['rombel_id' => $this->rombelLain->id]))->assertForbidden();
        $this->actingAs($this->guruA)->get(route('guru.tka-kesiapan.ekspor', ['rombel_id' => $this->rombelLain->id]))->assertForbidden();

        // Guru tidak bisa membuka profil siswa guru lain
        $this->actingAs($this->guruA)->get(route('guru.tka-kesiapan.siswa', $this->siswaLain->uuid))->assertNotFound();

        // Guru menyusun rekomendasi untuk siswanya
        $this->actingAs($this->guruA)->post(route('guru.tka-kesiapan.susun', $this->siswaA->uuid))->assertRedirect();
        $tl = TindakLanjut::where('siswa_id', $this->siswaA->id)->firstOrFail();

        // Guru lain tidak boleh mengubah tindak lanjut siswa ini
        $this->actingAs($this->guruLain)->patch(route('guru.tka-kesiapan.perbarui', $tl->uuid), [
            'status' => 'berjalan', 'rekomendasi' => 'Bajakan',
        ])->assertNotFound();

        // Guru pemilik menyunting & menandai status beserta alasan
        $this->actingAs($this->guruA)->patch(route('guru.tka-kesiapan.perbarui', $tl->uuid), [
            'status' => 'berjalan',
            'rekomendasi' => 'Latihan pecahan senilai 2x per minggu; evaluasi pekan depan.',
            'catatan_guru' => 'Didampingi di kelompok kecil.',
            'alasan' => 'Diselaraskan dengan jadwal remedial pekan ini',
        ])->assertRedirect();

        $this->assertSame('berjalan', $tl->fresh()->status);
        $revisi = $tl->revisi()->firstOrFail();
        $this->assertSame('Diselaraskan dengan jadwal remedial pekan ini', $revisi->alasan);

        // Ekspor CSV berjalan dan memuat disclaimer resmi
        $csv = $this->actingAs($this->guruA)->get(route('guru.tka-kesiapan.ekspor'));
        $csv->assertOk();
        $isiCsv = $csv->streamedContent();
        $this->assertStringContainsString('Rina Kelas Enam', $isiCsv);
        $this->assertStringContainsString('Hasil ini adalah pemantauan internal', $isiCsv);
    }

    public function test_kepsek_dan_admin_hanya_melihat_agregat_tanpa_individu_dan_kelompok_kecil_dilindungi(): void
    {
        $this->isiBuktiLengkap($this->siswaA, 50.0);
        $this->isiBuktiLengkap($this->siswaLain, 85.0);

        // Baru 2 siswa total (< min_kelompok 3) -> rincian agregat disupresi & nama siswa TIDAK tampil
        $resKecil = $this->actingAs($this->kepsekA)->get(route('kepsek.tka-kesiapan.index'));
        $resKecil->assertOk()
            ->assertDontSee('Rina Kelas Enam')
            ->assertDontSee('Dodi Kelas Lain')
            ->assertSee('Rincian distribusi, tren, dan agregat kelas disembunyikan untuk menjaga privasi');

        $csvKecil = $this->actingAs($this->kepsekA)->get(route('kepsek.tka-kesiapan.ekspor'));
        $csvKecil->assertOk();
        $this->assertStringNotContainsString('Rina Kelas Enam', $csvKecil->streamedContent());
        $this->assertStringContainsString('disembunyikan', $csvKecil->streamedContent());

        // Tambah 2 siswa di 6A sehingga total 6A = 3 siswa (>= min_kelompok), total sekolah = 4
        $s3 = Siswa::create(['uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'guru_id' => $this->guruA->id, 'rombel_id' => $this->rombelA->id, 'nama_guru' => $this->guruA->nama_lengkap, 'nis' => 'A03', 'nama_peserta_didik' => 'Budi Enam A', 'kelas' => '6A', 'jenis_kelamin' => 'L', 'aktif' => true]);
        $s4 = Siswa::create(['uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'guru_id' => $this->guruA->id, 'rombel_id' => $this->rombelA->id, 'nama_guru' => $this->guruA->nama_lengkap, 'nis' => 'A04', 'nama_peserta_didik' => 'Sari Enam A', 'kelas' => '6A', 'jenis_kelamin' => 'P', 'aktif' => true]);
        $this->isiBuktiLengkap($s3, 80.0);
        $this->isiBuktiLengkap($s4, 70.0);

        $resCukup = $this->actingAs($this->kepsekA)->get(route('kepsek.tka-kesiapan.index'));
        $resCukup->assertOk()
            ->assertSee('Ringkasan agregat per kelas / rombel')
            ->assertSee('6A')
            ->assertSee('Rincian disembunyikan demi privasi') // 6B hanya 1 siswa -> disembunyikan
            ->assertDontSee('Rina Kelas Enam')
            ->assertDontSee('Dodi Kelas Lain');

        $csvCukup = $this->actingAs($this->kepsekA)->get(route('kepsek.tka-kesiapan.ekspor'));
        $csvCukup->assertOk();
        $isi = $csvCukup->streamedContent();
        $this->assertStringContainsString('6A', $isi);
        $this->assertStringNotContainsString('Rina Kelas Enam', $isi);
        $this->assertStringNotContainsString('Dodi Kelas Lain', $isi);

        // Kepsek & Admin ditolak membuka profil individu guru (403)
        $this->actingAs($this->kepsekA)->get(route('guru.tka-kesiapan.siswa', $this->siswaA->uuid))->assertForbidden();
        $this->actingAs($this->adminA)->get(route('guru.tka-kesiapan.siswa', $this->siswaA->uuid))->assertForbidden();
    }

    public function test_admin_mengelola_aturan_dan_pemetaan_dengan_audit_log_beserta_alasan(): void
    {
        foreach (['2027-01-10', '2027-01-17', '2027-01-24'] as $tgl) {
            DB::table('nilai')->insert([
                'uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'guru_id' => $this->guruA->id, 'siswa_id' => $this->siswaA->id,
                'nama_guru' => 'G', 'nama_siswa' => $this->siswaA->nama_peserta_didik, 'tanggal' => $tgl,
                'jenis' => 'Tugas', 'judul_penilaian' => 'Soal Cerita', 'mata_pelajaran' => 'Aritmetika Dasar', 'nilai' => 72,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Admin membuka halaman pengaturan (juga memeriksa tab TKA aktif di layout admin-with-tabs)
        $this->actingAs($this->adminA)->get(route('admin.tka-kesiapan.pengaturan'))->assertOk()->assertSee('Pemetaan sementara');

        $this->actingAs($this->adminA)->post(route('admin.tka-kesiapan.pemetaan.simpan'), [
            'label_sumber' => 'Aritmetika Dasar',
            'indikator_kode' => 'matematika',
            'alasan' => 'Mulok penguatan hitung SD',
        ])->assertRedirect();

        $this->actingAs($this->adminA)->post(route('admin.tka-kesiapan.aturan.simpan'), [
            'min_bukti' => 3,
            'min_tanggal_berbeda' => 2,
            'ambang_perlu_dukungan' => 55,
            'ambang_cukup_mantap' => 70,
            'alasan' => 'Kesepakatan rapat dewan guru',
        ])->assertRedirect();

        $audit = Audit::where('tenant_id', $this->tenantA->id)->orderBy('id')->get();
        $this->assertCount(2, $audit);
        $this->assertSame('Mulok penguatan hitung SD', $audit[0]->alasan);
        $this->assertSame('Kesepakatan rapat dewan guru', $audit[1]->alasan);

        // Tenant B tidak terdampak pemetaan/aturan Tenant A
        $this->assertSame(0, Pemetaan::withoutGlobalScopes()->where('tenant_id', $this->tenantB->id)->count());

        $profil = $this->actingAs($this->guruA)->get(route('guru.tka-kesiapan.siswa', $this->siswaA->uuid));
        $profil->assertOk()->assertSee('Cukup mantap');
    }

    public function test_siswa_dan_orang_tua_melihat_ringkasan_ramah_tanpa_peringkat_atau_catatan_guru(): void
    {
        $this->isiBuktiLengkap($this->siswaA, 65.0);
        $this->actingAs($this->guruA)->post(route('guru.tka-kesiapan.susun', $this->siswaA->uuid));
        $tl = TindakLanjut::where('siswa_id', $this->siswaA->id)->firstOrFail();
        $this->actingAs($this->guruA)->patch(route('guru.tka-kesiapan.perbarui', $tl->uuid), [
            'status' => 'berjalan',
            'rekomendasi' => 'Latihan soal cerita 2x/minggu.',
            'catatan_guru' => 'CATATAN_PRIVAT_GURU_RAHASIA',
        ]);

        $siswaRes = $this->actingAs($this->akunSiswaA)->get(route('siswa.tka-kesiapan.index'));
        $siswaRes->assertOk()
            ->assertSee('Rina Kelas Enam')
            ->assertSee('Sedang berkembang')
            ->assertDontSee('Peringkat')
            ->assertDontSee('CATATAN_PRIVAT_GURU_RAHASIA')
            ->assertDontSee('Dodi Kelas Lain');

        $ortuRes = $this->actingAs($this->ortuA)->get(route('ortu.tka-kesiapan.index'));
        $ortuRes->assertOk()
            ->assertSee('Rina Kelas Enam')
            ->assertDontSee('CATATAN_PRIVAT_GURU_RAHASIA')
            ->assertSee('Saran latihan &amp; pendampingan', false);

        // IDOR oleh orang tua / siswa ke siswa lain ditolak
        $this->actingAs($this->ortuA)->get(route('ortu.tka-kesiapan.index', ['siswa_uuid' => $this->siswaLain->uuid]))->assertForbidden();
        $this->actingAs($this->akunSiswaA)->get(route('siswa.tka-kesiapan.index', ['siswa_uuid' => $this->siswaLain->uuid]))->assertForbidden();
        $this->actingAs($this->akunSiswaA)->get(route('guru.tka-kesiapan.index'))->assertForbidden();
        $this->actingAs($this->ortuA)->get(route('admin.tka-kesiapan.pengaturan'))->assertForbidden();
    }

    public function test_backup_restore_dan_feature_flag_aman(): void
    {
        foreach ([
            'tka_kesiapan_pengaturan',
            'tka_kesiapan_pemetaan',
            'tka_kesiapan_tindak_lanjut',
            'tka_kesiapan_tindak_lanjut_revisi',
            'tka_kesiapan_audit',
        ] as $t) {
            $this->assertContains($t, BackupTables::tabel());
            $this->assertContains($t, BackupTables::dikosongkan());
        }

        // Buat data di tabel baru, ekspor via BackupStreamWriter, hapus, lalu pulihkan via BackupRestoreService
        $this->actingAs($this->adminA)->post(route('admin.tka-kesiapan.pemetaan.simpan'), [
            'label_sumber' => 'Matematika Tambahan', 'indikator_kode' => 'matematika', 'alasan' => 'Uji backup',
        ])->assertRedirect();
        $this->isiBuktiLengkap($this->siswaA, 50.0);
        $this->actingAs($this->guruA)->post(route('guru.tka-kesiapan.susun', $this->siswaA->uuid));

        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        app(BackupStreamWriter::class)->writeTo($stream);
        rewind($stream);
        $payload = json_decode(stream_get_contents($stream), true, 512, JSON_THROW_ON_ERROR);
        fclose($stream);

        $this->assertCount(1, $payload['tka_kesiapan_pemetaan']);
        $this->assertCount(1, $payload['tka_kesiapan_tindak_lanjut']);
        $this->assertCount(1, $payload['tka_kesiapan_audit']);

        // Kosongkan tabel anak-ke-induk lalu restore
        DB::table('tka_kesiapan_audit')->delete();
        DB::table('tka_kesiapan_tindak_lanjut')->delete();
        DB::table('tka_kesiapan_pemetaan')->delete();

        app(BackupRestoreService::class)->restore($payload);
        $this->assertSame(1, Pemetaan::withoutGlobalScopes()->count());
        $this->assertSame(1, TindakLanjut::withoutGlobalScopes()->count());
        $this->assertSame(1, Audit::withoutGlobalScopes()->count());

        // Feature flag mati: rute kesiapan mengembalikan 404, menu di sidebar sembunyi, modul lama tetap jalan
        config(['tka_kesiapan.aktif' => false]);
        $this->actingAs($this->guruA)->get(route('guru.tka-kesiapan.index'))->assertNotFound();
        $this->actingAs($this->adminA)->get(route('admin.tka-kesiapan.pengaturan'))->assertNotFound();
        $this->actingAs($this->guruA)->get(route('guru.tka.index'))
            ->assertOk()
            ->assertDontSee('guru/tka-kesiapan');
    }
}
