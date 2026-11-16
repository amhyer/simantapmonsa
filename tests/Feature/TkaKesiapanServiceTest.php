<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Exam\Attempt;
use App\Models\Exam\Bank;
use App\Models\Exam\Engine;
use App\Models\Exam\Result;
use App\Models\Exam\Session;
use App\Models\KenaikanKelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tka\AdaptiveSimulation;
use App\Models\Tka\Kesiapan\TindakLanjut;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Tka\Kesiapan\KesiapanAkses;
use App\Services\Tka\Kesiapan\KesiapanFilter;
use App\Services\Tka\Kesiapan\KesiapanService;
use App\Services\Tka\Kesiapan\RekomendasiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TkaKesiapanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $guruA;

    protected User $guruA2;

    protected User $guruB;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-02-15 08:00:00'); // Genap 2026/2027

        $this->tenantA = $this->tenant('40300001', 'sd-a');
        $this->tenantB = $this->tenant('40300002', 'sd-b');
        $this->guruA = User::factory()->guru()->create(['tenant_id' => $this->tenantA->id]);
        $this->guruA2 = User::factory()->guru()->create(['tenant_id' => $this->tenantA->id]);
        $this->guruB = User::factory()->guru()->create(['tenant_id' => $this->tenantB->id]);
        $this->siswa = $this->siswa($this->tenantA, $this->guruA, 'S001', '6A');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── helper ──────────────────────────────────────────────────────────

    protected function tenant(string $npsn, string $sub): Tenant
    {
        return Tenant::create(['uuid' => (string) Str::uuid(), 'npsn' => $npsn, 'nama_sekolah' => 'SD '.$sub, 'subdomain' => $sub, 'aktif' => true]);
    }

    protected function siswa(Tenant $t, User $guru, string $nis, string $kelas): Siswa
    {
        return Siswa::create([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $t->id, 'guru_id' => $guru->id, 'nama_guru' => $guru->nama_lengkap,
            'nis' => $nis, 'nama_peserta_didik' => 'Siswa '.$nis, 'kelas' => $kelas, 'jenis_kelamin' => 'L', 'aktif' => true,
        ]);
    }

    protected function nilai(Siswa $s, string $mapel, float $nilai, string $tgl, ?int $tenantId = null, ?int $kuisId = null, ?array $rekaman = null): int
    {
        return DB::table('nilai')->insertGetId([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId ?? $s->tenant_id, 'guru_id' => $s->guru_id, 'siswa_id' => $s->id,
            'kuis_id' => $kuisId, 'nama_guru' => 'G', 'nama_siswa' => $s->nama_peserta_didik, 'tanggal' => $tgl,
            'jenis' => 'Ulangan Harian', 'judul_penilaian' => 'UH '.$mapel, 'mata_pelajaran' => $mapel, 'nilai' => $nilai,
            'rekaman' => $rekaman !== null ? json_encode($rekaman) : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function kuis(Siswa $s, string $mapel, int $benar, int $total, string $tgl, string $kelas = '6A'): int
    {
        $kuisId = DB::table('kuis')->insertGetId([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $s->tenant_id, 'guru_id' => $s->guru_id, 'nama_guru' => 'G', 'tanggal' => $tgl,
            'judul' => 'Kuis '.$mapel.' '.$tgl, 'mata_pelajaran' => $mapel, 'kelas' => $kelas, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hasil_kuis')->insert([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $s->tenant_id, 'guru_id' => $s->guru_id, 'kuis_id' => $kuisId, 'siswa_id' => $s->id,
            'nama_guru' => 'G', 'judul_kuis' => 'Kuis', 'nama_siswa' => $s->nama_peserta_didik, 'waktu' => $tgl.' 09:00:00',
            'benar' => $benar, 'total' => $total, 'skor' => (int) round($benar / max($total, 1) * 100), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $kuisId;
    }

    protected function erapor(Siswa $s, string $mapel, float $akhir, string $kelas, string $tahun, string $semester, ?float $formatif = null, ?float $sumatif = null, ?float $sas = null): int
    {
        return DB::table('nilai_erapor')->insertGetId([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $s->tenant_id, 'siswa_id' => $s->id, 'guru_id' => $s->guru_id,
            'mata_pelajaran' => $mapel, 'kelas' => $kelas, 'semester' => $semester, 'tahun_ajaran' => $tahun,
            'nilai_formatif' => $formatif ?? 0, 'nilai_sumatif' => $sumatif ?? 0, 'nilai_sumatif_akhir' => $sas ?? 0,
            'nilai_akhir' => $akhir, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function svc(?Tenant $t = null): KesiapanService
    {
        return new KesiapanService(($t ?? $this->tenantA)->id);
    }

    protected function matematika(array $profil): array
    {
        return $profil['indikator']['matematika'];
    }

    // ── agregasi & aturan ───────────────────────────────────────────────

    public function test_agregasi_beragam_sumber_dengan_bobot_sama_antar_jenis_sumber(): void
    {
        $this->nilai($this->siswa, 'Matematika', 60, '2027-01-10');
        $this->nilai($this->siswa, 'Matematika', 80, '2027-01-17');     // rata nilai_harian = 70
        $this->kuis($this->siswa, 'Matematika', 8, 10, '2027-01-24');   // 80
        $this->erapor($this->siswa, 'Matematika', 90, '6A', '2026/2027', 'Ganjil'); // 90

        $m = $this->matematika($this->svc()->profil($this->siswa, new KesiapanFilter));

        $this->assertSame('cukup', $m['bukti_status']);
        $this->assertSame(4, $m['jumlah_bukti']);
        $this->assertEquals(70.0, $m['skor_per_sumber']['nilai_harian']);
        $this->assertEquals(80.0, $m['skor_per_sumber']['kuis']);
        $this->assertEquals(90.0, $m['skor_per_sumber']['erapor']);
        $this->assertEquals(80.0, $m['estimasi_indeks_internal']); // (70+80+90)/3, bukan rata 4 butir
        $this->assertEquals(80.0, $m['skor_rata']);
        $this->assertSame('cukup_mantap', $m['kecenderungan']);
        $this->assertSame(2, $m['ringkasan_sumber']['nilai_harian']['jumlah']);
        $this->assertSame('0–100 (nilai guru)', $m['ringkasan_sumber']['nilai_harian']['skala']);
    }

    public function test_tanpa_data_atau_data_kurang_adalah_belum_cukup_bukan_nol(): void
    {
        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $this->assertSame('belum_cukup', $profil['status_data']);
        foreach ($profil['indikator'] as $i) {
            $this->assertSame('belum_cukup', $i['bukti_status']);
            $this->assertNull($i['skor_rata']);
            $this->assertNull($i['estimasi_indeks_internal']);
            $this->assertNull($i['kecenderungan']);
            $this->assertNull($i['keyakinan']);
        }

        // 2 bukti (< min 3) tetap belum cukup meski skornya rendah — tidak boleh dicap "perlu dukungan".
        $this->nilai($this->siswa, 'Matematika', 10, '2027-01-10');
        $this->nilai($this->siswa, 'Matematika', 20, '2027-01-17');
        $m = $this->matematika($this->svc()->profil($this->siswa, new KesiapanFilter));
        $this->assertSame('belum_cukup', $m['bukti_status']);
        $this->assertNull($m['kecenderungan']);
        $this->assertNull($m['skor_rata']);
        $this->assertSame([], $this->svc()->profil($this->siswa, new KesiapanFilter)['perlu_ditinjau']);
    }

    public function test_bukti_pada_satu_tanggal_saja_tidak_dianggap_cukup(): void
    {
        foreach ([50, 55, 60] as $n) {
            $this->nilai($this->siswa, 'Matematika', $n, '2027-01-10');
        }
        $m = $this->matematika($this->svc()->profil($this->siswa, new KesiapanFilter));
        $this->assertSame(3, $m['jumlah_bukti']);
        $this->assertSame('belum_cukup', $m['bukti_status']);
    }

    public function test_keyakinan_tinggi_ditolak_bila_hanya_satu_sumber_meski_banyak_rekaman(): void
    {
        // 10 rekaman konsisten dan terbaru tetapi HANYA dari nilai_harian (1 jenis sumber)
        for ($i = 1; $i <= 10; $i++) {
            $this->nilai($this->siswa, 'Matematika', 80, sprintf('2027-01-%02d', $i));
        }

        $m = $this->matematika($this->svc()->profil($this->siswa, new KesiapanFilter));
        $this->assertSame('cukup', $m['bukti_status']);
        $this->assertNotSame('tinggi', $m['keyakinan']);
        $this->assertSame('sedang', $m['keyakinan']);
        $this->assertFalse($m['keyakinan_rincian']['cakupan']);
    }

    public function test_skala_berbeda_dinormalisasi_dan_tidak_dijumlah_mentah(): void
    {
        $this->nilai($this->siswa, 'Matematika', 100, '2027-01-10');
        $this->kuis($this->siswa, 'Matematika', 1, 2, '2027-01-17');   // 50%, skala 2 soal
        $this->kuis($this->siswa, 'Matematika', 3, 4, '2027-01-24');   // 75%, skala 4 soal

        $m = $this->matematika($this->svc()->profil($this->siswa, new KesiapanFilter));
        $this->assertEquals(62.5, $m['skor_per_sumber']['kuis']);       // rata persentase, bukan (1+3)/(2+4)
        $this->assertEquals(100.0, $m['skor_per_sumber']['nilai_harian']);
        $this->assertEquals(81.25, $m['skor_rata']);
    }

    public function test_duplikasi_lintas_sumber_kuis_dan_tka_sync_dieksklusi(): void
    {
        $kuisId = $this->kuis($this->siswa, 'Matematika', 9, 10, '2027-01-10');
        $this->nilai($this->siswa, 'Matematika', 90, '2027-01-10', null, $kuisId); // salinan nilai dari kuis
        $this->erapor($this->siswa, 'Matematika', 0, '6A', '2026/2027', 'Ganjil');  // belum diisi

        // Simulasi TKA + nilai/erapor hasil sinkronisasi tka_kognitif (tidak boleh dihitung ulang!)
        AdaptiveSimulation::create([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'siswa_id' => $this->siswa->id,
            'paket_kode' => 'numerasi', 'paket_judul' => 'Simulasi Numerasi', 'status' => 'selesai',
            'skor_akhir' => 75, 'skor_indikator' => ['Numerasi Kuantitatif' => 75], 'submitted_at' => '2027-01-15 10:00:00',
        ]);
        $nilaiSyncId = $this->nilai($this->siswa, 'Matematika', 75, '2027-01-16', null, null, ['sumber' => 'tka_kognitif']);
        $eraporSyncId = $this->erapor($this->siswa, 'Matematika', 75, '6A', '2026/2027', 'Genap', 75, 75, 75);
        DB::table('tka_erapor_sync_logs')->insert([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->id, 'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guruA->id, 'nilai_id' => $nilaiSyncId, 'nilai_erapor_id' => $eraporSyncId,
            'mata_pelajaran' => 'Matematika', 'komponen_target' => 'sumatif',
            'skor_tka_konversi' => 75, 'nilai_akhir_erapor' => 75, 'synced_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $m = $this->matematika($profil);
        // Hanya 1 kuis + 1 simulasi = 2 bukti sah (salinan nilai kuis & sinkronisasi TKA dieksklusi)
        $this->assertSame(2, $m['jumlah_bukti']);
        $this->assertSame(1, $profil['diagnostik']['duplikat_kuis_nilai']);
        $this->assertSame(2, $profil['diagnostik']['duplikat_tka_sync']);
    }

    public function test_ujian_status_draft_dibatalkan_belum_final_dieksklusi_dan_tryout_multi_mapel_terbaca(): void
    {
        $semester = Semester::create(['semester_id' => '20262', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'genap']);
        $engineTo = Engine::create(['kode' => 'tryout', 'nama' => 'Tryout', 'jenis' => 'tryout', 'aktif' => true]);
        $bankTo = Bank::create(['tenant_id' => $this->tenantA->id, 'engine_id' => $engineTo->id, 'guru_id' => $this->guruA->id, 'nama' => 'Paket Tryout SD', 'mata_pelajaran' => null]);

        // 1) Sesi Tryout selesai dengan multi-mapel di detail_per_section
        $sesiSah = Session::create(['tenant_id' => $this->tenantA->id, 'bank_id' => $bankTo->id, 'guru_id' => $this->guruA->id, 'semester_id' => $semester->id, 'nama' => 'TO 1', 'kelas' => '6A', 'status' => 'completed']);
        $attSah = Attempt::create(['tenant_id' => $this->tenantA->id, 'session_id' => $sesiSah->id, 'siswa_id' => $this->siswa->id, 'attempt_number' => 1, 'mulai_at' => '2027-01-12 08:00:00', 'selesai_at' => '2027-01-12 09:00:00', 'status' => 'dinilai', 'nilai_total' => 70, 'nilai_maksimal' => 100]);
        Result::create([
            'attempt_id' => $attSah->id, 'nilai_total' => 70, 'nilai_maksimal' => 100, 'persentase' => 70,
            'detail_per_section' => [
                ['nama_mapel' => 'Matematika', 'skor' => 16, 'maksimal' => 20, 'persentase' => 80],
                ['nama_mapel' => 'Bahasa Indonesia', 'skor' => 12, 'maksimal' => 20, 'persentase' => 60],
            ],
        ]);

        // 2) Sesi dibatalkan & attempt berjalan (belum final) tidak boleh masuk bukti
        $sesiBatal = Session::create(['tenant_id' => $this->tenantA->id, 'bank_id' => $bankTo->id, 'guru_id' => $this->guruA->id, 'semester_id' => $semester->id, 'nama' => 'TO Batal', 'kelas' => '6A', 'status' => 'dibatalkan']);
        Attempt::create(['tenant_id' => $this->tenantA->id, 'session_id' => $sesiBatal->id, 'siswa_id' => $this->siswa->id, 'attempt_number' => 1, 'mulai_at' => '2027-01-13 08:00:00', 'selesai_at' => '2027-01-13 09:00:00', 'status' => 'dinilai', 'nilai_total' => 20, 'nilai_maksimal' => 100]);
        Attempt::create(['tenant_id' => $this->tenantA->id, 'session_id' => $sesiSah->id, 'siswa_id' => $this->siswa->id, 'attempt_number' => 2, 'mulai_at' => '2027-01-14 08:00:00', 'selesai_at' => null, 'status' => 'berjalan']);

        // 3) Legacy CBT & Tryout yang belum di-backfill tetap terbaca bila sudah selesai
        $cbtBank = CbtQuestionBank::create(['tenant_id' => $this->tenantA->id, 'guru_id' => $this->guruA->id, 'title' => 'Bank CBT MTK', 'subject' => 'Matematika']);
        $cbtSesi = CbtTestSession::create(['tenant_id' => $this->tenantA->id, 'guru_id' => $this->guruA->id, 'bank_id' => $cbtBank->id, 'title' => 'CBT MTK', 'kelas' => '6A', 'opens_at' => now()->subDays(5), 'closes_at' => now()->addDay(), 'duration_minutes' => 30, 'status' => 'completed']);
        $userSiswa = User::factory()->siswa()->create(['tenant_id' => $this->tenantA->id, 'nama_pengguna' => 'S001']);
        CbtResult::create(['tenant_id' => $this->tenantA->id, 'session_id' => $cbtSesi->id, 'siswa_id' => $this->siswa->id, 'user_id' => $userSiswa->id, 'started_at' => '2027-01-15 08:00:00', 'deadline_at' => '2027-01-15 08:30:00', 'submitted_at' => '2027-01-15 08:20:00', 'answers' => [], 'correct_count' => 9, 'score' => 90]);

        $cat = Category::create(['nama_kategori' => 'SD', 'jenis' => 'akademik', 'aktif' => true]);
        $pkg = Package::create(['tenant_id' => $this->tenantA->id, 'category_id' => $cat->id, 'kode_paket' => 'TO-L1', 'nama_paket' => 'TO Legacy', 'jenjang' => 'SD', 'tahun_paket' => 2027, 'durasi_total_menit' => 60]);
        $toSesi = TryoutSession::create(['tenant_id' => $this->tenantA->id, 'package_id' => $pkg->id, 'semester_id' => $semester->id, 'guru_id' => $this->guruA->id, 'nama_sesi' => 'TO Legacy 1', 'kelas' => '6A', 'tanggal_mulai' => now()->subDays(5), 'tanggal_selesai' => now()->addDay(), 'durasi_menit' => 60, 'status' => 'completed']);
        TryoutResult::create([
            'tenant_id' => $this->tenantA->id, 'tryout_session_id' => $toSesi->id, 'siswa_id' => $this->siswa->id, 'attempt_number' => 1,
            'nilai_total' => 70, 'nilai_maksimal' => 100, 'persentase' => 70, 'lulus' => true,
            'detail_nilai_mapel' => [['nama_mapel' => 'Matematika', 'skor' => 70, 'maksimal' => 100]],
            'waktu_mulai' => '2027-01-20 08:00:00', 'waktu_selesai' => '2027-01-20 08:40:00', 'durasi_total_detik' => 2400,
        ]);

        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $this->assertSame(3, $profil['indikator']['matematika']['jumlah_bukti']); // 80 (exam) + 90 (cbt) + 70 (to)
        $this->assertEquals(80.0, $profil['indikator']['matematika']['skor_rata']);
        $this->assertSame(1, $profil['indikator']['bahasa_indonesia']['jumlah_bukti']);
        $this->assertSame(1, $profil['diagnostik']['ujian_dibatalkan_atau_draft']);
        $this->assertSame(1, $profil['diagnostik']['ujian_belum_final']);
    }

    public function test_mapel_tidak_terpetakan_dilaporkan_bukan_dipaksa(): void
    {
        foreach (['2027-01-10', '2027-01-11', '2027-01-12'] as $t) {
            $this->nilai($this->siswa, 'Seni Budaya', 70, $t);
        }
        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $this->assertSame(['nilai_harian' => 3], $profil['tidak_terpetakan']);
        $this->assertSame(0, $profil['indikator']['matematika']['jumlah_bukti']);
    }

    // ── isolasi ──────────────────────────────────────────────────────────

    public function test_bukti_dari_tenant_lain_tidak_bocor_dan_akses_siswa_dibatasi(): void
    {
        $siswaB = $this->siswa($this->tenantB, $this->guruB, 'SB01', '6A');
        foreach (['2027-01-10', '2027-01-17', '2027-01-24'] as $t) {
            $this->nilai($siswaB, 'Matematika', 90, $t);
            $this->nilai($this->siswa, 'Matematika', 10, $t, $this->tenantB->id);
        }

        $this->assertSame(0, $this->matematika($this->svc()->profil($this->siswa, new KesiapanFilter))['jumlah_bukti']);

        $this->assertNull(KesiapanAkses::siswaUuid($this->guruA, $siswaB->uuid));
        $this->assertNull(KesiapanAkses::siswaUuid($this->guruA2, $this->siswa->uuid));
        $this->assertNotNull(KesiapanAkses::siswaUuid($this->guruA, $this->siswa->uuid));

        // Akun siswa yang tidak mengisi terhubung_dengan tetap dapat mengakses profilnya via NIS.
        $akunTanpaTautan = User::factory()->siswa()->create(['tenant_id' => $this->tenantA->id, 'nama_pengguna' => 'S001']);
        $this->assertSame($this->siswa->id, KesiapanAkses::siswaQuery($akunTanpaTautan)->value('siswa.id'));
    }

    public function test_sumber_tidak_berubah_setelah_analisis(): void
    {
        $this->nilai($this->siswa, 'Matematika', 70, '2027-01-10');
        $this->kuis($this->siswa, 'Matematika', 7, 10, '2027-01-17');
        $this->erapor($this->siswa, 'Matematika', 75, '6A', '2026/2027', 'Ganjil');
        $snap = fn () => md5(json_encode([
            DB::table('nilai')->orderBy('id')->get(), DB::table('hasil_kuis')->orderBy('id')->get(), DB::table('nilai_erapor')->orderBy('id')->get(),
        ]));
        $sebelum = $snap();

        $svc = $this->svc();
        $profil = $svc->profil($this->siswa, new KesiapanFilter);
        $svc->kelas(collect([$this->siswa]), new KesiapanFilter);
        app(RekomendasiService::class)->susun($this->siswa, $profil, $this->guruA, '2026/2027', 'Genap');

        $this->assertSame($sebelum, $snap());
    }

    // ── histori kelas 4 → 5 → 6 ──────────────────────────────────────────

    public function test_lintasan_kelas_4_5_6_dan_tren_antar_periode(): void
    {
        foreach ([['4A', '2024/2025', 60, 62], ['5A', '2025/2026', 70, 72], ['6A', '2026/2027', 85, 88]] as [$kelas, $ta, $a, $b]) {
            $this->erapor($this->siswa, 'Matematika', $a, $kelas, $ta, 'Ganjil');
            $this->erapor($this->siswa, 'Matematika', $b, $kelas, $ta, 'Genap');
        }
        foreach ([['2024-10-10', '2024-10-17'], ['2025-03-10', '2025-03-17'], ['2025-10-10', '2025-10-17'], ['2026-03-10', '2026-03-17'], ['2026-10-10', '2026-10-17'], ['2027-01-10', '2027-01-17']] as $pair) {
            $this->nilai($this->siswa, 'Matematika', 80, $pair[0]);
            $this->nilai($this->siswa, 'Matematika', 80, $pair[1]);
        }

        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $tingkat = array_values(array_unique(array_column($profil['lintasan'], 'tingkat')));
        $this->assertSame([4, 5, 6], $tingkat);

        $m = $this->matematika($profil);
        $this->assertCount(6, $m['lintasan']);
        $this->assertSame('2024/2025', $m['lintasan'][0]['tahun_ajaran']);
        $this->assertSame(4, $m['lintasan'][0]['tingkat']);
        $this->assertTrue($m['lintasan'][0]['tingkat_valid']);
        $this->assertContains($m['tren']['arah'], ['meningkat', 'stabil', 'menurun']);

        $f = new KesiapanFilter(tingkat: 5);
        $p5 = $this->svc()->profil($this->siswa, $f);
        $this->assertNotEmpty($p5['bukti']);
        $this->assertSame([5], array_values(array_unique(array_column($p5['bukti'], 'tingkat'))));
    }

    public function test_tidak_menebak_jenjang_historis_dari_kelas_saat_ini(): void
    {
        // Siswa saat ini di 6A, tapi tidak ada e-Rapor/rombel pada 2024/2025.
        // Nilai harian di 2024-10-10 & 2024-10-17 tidak boleh otomatis ditebak menjadi kelas 4!
        $this->nilai($this->siswa, 'Matematika', 70, '2024-10-10');
        $this->nilai($this->siswa, 'Matematika', 75, '2024-10-17');

        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $m = $this->matematika($profil);
        $this->assertSame([], $m['lintasan']);
        $this->assertSame(2, $m['jumlah_tanpa_jenjang_historis']);
        $this->assertNull($profil['bukti'][0]['tingkat']);
        $this->assertFalse($profil['bukti'][0]['tingkat_valid']);
        $this->assertSame('tidak_diketahui', $profil['bukti'][0]['tingkat_sumber']);
    }

    public function test_deteksi_tinggal_kelas_pindah_rombel_akselerasi_dan_kalender_tidak_konsisten(): void
    {
        KenaikanKelas::create([
            'tenant_id' => $this->tenantA->id, 'siswa_id' => $this->siswa->id,
            'tahun_ajaran' => '2024/2025', 'semester' => 'Genap', 'naik_kelas' => false,
        ]);
        // Mengulang kelas 5 pada 2 tahun ajaran, lalu lompat kelas (4 -> 6 / atau 5 di 2024/2025 & 2025/2026),
        // serta dua rombel berbeda di 2026/2027 Ganjil + tahun ajaran cacat 2025/2028
        $this->erapor($this->siswa, 'Matematika', 60, '5A', '2024/2025', 'Ganjil');
        $this->erapor($this->siswa, 'Matematika', 65, '5A', '2025/2026', 'Ganjil');
        $this->erapor($this->siswa, 'Matematika', 70, '6A', '2026/2027', 'Ganjil');
        $this->erapor($this->siswa, 'Bahasa Indonesia', 75, '6B', '2026/2027', 'Ganjil');
        $this->erapor($this->siswa, 'IPAS', 80, '5A', '2025/2028', 'Ganjil'); // tahun ajaran tidak konsisten
        $this->nilai($this->siswa, 'Matematika', 72, '2026-10-10'); // periode konflik 6A vs 6B -> jenjang tidak pasti

        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $hk = $profil['histori_kondisi'];
        $this->assertNotEmpty($hk['tinggal_kelas']);
        $this->assertNotEmpty($hk['pindah_rombel']);
        $this->assertNotEmpty($hk['kalender_tidak_konsisten']);

        // Nilai pada periode yang memiliki perpindahan rombel tidak ditebak kelasnya
        $buktiNilai = collect($profil['bukti'])->firstWhere('sumber', 'nilai_harian');
        $this->assertNull($buktiNilai['tingkat']);
        $this->assertSame('pindah_rombel_konflik', $buktiNilai['tingkat_sumber']);
    }

    public function test_tren_menurun_terdeteksi_dan_masuk_perlu_ditinjau(): void
    {
        foreach ([['2026-09-10', '2026-09-20', 90], ['2027-01-10', '2027-01-20', 60]] as [$a, $b, $n]) {
            $this->nilai($this->siswa, 'Matematika', $n, $a);
            $this->nilai($this->siswa, 'Matematika', $n, $b);
        }
        $m = $this->matematika($this->svc()->profil($this->siswa, new KesiapanFilter));
        $this->assertSame('menurun', $m['tren']['arah']);
        $this->assertEquals(-30.0, $m['tren']['selisih']);
    }

    // ── kelas konsisten dengan profil ────────────────────────────────────

    public function test_ringkasan_kelas_konsisten_dengan_profil_dan_filter(): void
    {
        $s2 = $this->siswa($this->tenantA, $this->guruA, 'S002', '6A');
        foreach (['2026-09-10', '2026-09-17', '2026-09-24'] as $t) {
            $this->nilai($this->siswa, 'Matematika', 50, $t);
            $this->nilai($s2, 'Matematika', 90, $t);
        }

        $filter = new KesiapanFilter(tahunAjaran: '2026/2027', semester: 'Ganjil');
        $kelas = $this->svc()->kelas(collect([$this->siswa, $s2]), $filter);

        $this->assertSame(2, $kelas['ringkas']['total']);
        $this->assertSame(['perlu_dukungan' => 1, 'berkembang' => 0, 'cukup_mantap' => 1, 'belum_cukup' => 0], $kelas['distribusi']['matematika']);
        $this->assertSame(1, $kelas['ringkas']['perlu_ditinjau']);

        foreach ($kelas['baris'] as $baris) {
            $profil = $this->svc()->profil($baris['siswa'], $filter);
            $this->assertEquals($profil['indikator']['matematika']['skor_rata'], $baris['indikator']['matematika']['skor_rata']);
            $this->assertSame($profil['indikator']['matematika']['kecenderungan'], $baris['indikator']['matematika']['kecenderungan']);
        }

        $genap = $this->svc()->kelas(collect([$this->siswa, $s2]), new KesiapanFilter(tahunAjaran: '2026/2027', semester: 'Genap'));
        $this->assertSame(2, $genap['distribusi']['matematika']['belum_cukup']);
    }

    public function test_agregat_kelompok_kecil_disembunyikan(): void
    {
        $this->erapor($this->siswa, 'Matematika', 80, '6A', '2026/2027', 'Ganjil');
        foreach (['2026-09-10', '2026-09-17', '2026-09-24'] as $t) {
            $this->nilai($this->siswa, 'Matematika', 80, $t);
        }
        $kelas = $this->svc()->kelas(collect([$this->siswa]), new KesiapanFilter, true);
        $this->assertTrue($kelas['agregat_terlindungi']);
        $this->assertSame([], $kelas['distribusi']);
        $this->assertSame([], $kelas['peta_tingkat']);
    }

    public function test_ringkasan_kelas_tidak_n_plus_satu(): void
    {
        $buat = function (int $n) {
            $list = collect();
            for ($i = 0; $i < $n; $i++) {
                $s = $this->siswa($this->tenantA, $this->guruA, 'N'.$n.'_'.$i, '6A');
                $this->nilai($s, 'Matematika', 80, '2027-01-10');
                $list->push($s);
            }

            return $list;
        };
        $hitung = function ($list) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->svc()->kelas($list, new KesiapanFilter);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $kecil = $hitung($buat(3));
        $besar = $hitung($buat(15));
        $this->assertSame($kecil, $besar, 'Jumlah query tidak boleh bertambah seiring jumlah siswa.');
    }

    // ── rekomendasi ──────────────────────────────────────────────────────

    public function test_rekomendasi_terhubung_ke_bukti_dan_status_tersimpan_dengan_riwayat(): void
    {
        foreach (['2027-01-10', '2027-01-17', '2027-01-24'] as $i => $t) {
            $this->nilai($this->siswa, 'Matematika', 40 + $i, $t);
        }
        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $this->assertSame('perlu_dukungan', $this->matematika($profil)['kecenderungan']);

        $rek = app(RekomendasiService::class);
        $this->assertSame(1, $rek->susun($this->siswa, $profil, $this->guruA, '2026/2027', 'Genap'));
        $this->assertSame(0, $rek->susun($this->siswa, $profil, $this->guruA, '2026/2027', 'Genap'));

        $tl = TindakLanjut::where('siswa_id', $this->siswa->id)->firstOrFail();
        $this->assertSame('matematika', $tl->indikator_kode);
        $this->assertSame('disarankan', $tl->status);
        $this->assertCount(3, $tl->bukti_ref);
        $this->assertStringContainsString('Alasan:', $tl->rekomendasi);
        $this->assertStringContainsString('Matematika', $tl->rekomendasi);

        $rek->perbarui($tl, $this->guruA, [
            'status' => 'berjalan',
            'rekomendasi' => 'Latihan pecahan 3x/minggu.',
            'catatan_guru' => 'Dimulai pekan ini',
            'alasan' => 'Disepakati saat diskusi kelas',
        ]);
        $tl->refresh();
        $this->assertSame('berjalan', $tl->status);
        $this->assertTrue($tl->disunting_guru);
        $revisi = $tl->revisi()->first();
        $this->assertSame('Disepakati saat diskusi kelas', $revisi->alasan);

        $rek->susun($this->siswa, $profil, $this->guruA, '2026/2027', 'Genap');
        $this->assertSame('Latihan pecahan 3x/minggu.', $tl->fresh()->rekomendasi);
    }

    public function test_tidak_ada_rekomendasi_bila_bukti_belum_cukup(): void
    {
        $this->nilai($this->siswa, 'Matematika', 10, '2027-01-10');
        $profil = $this->svc()->profil($this->siswa, new KesiapanFilter);
        $this->assertSame(0, app(RekomendasiService::class)->susun($this->siswa, $profil, $this->guruA, '2026/2027', 'Genap'));
    }
}
