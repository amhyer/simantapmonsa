<?php

namespace Tests\Feature;

use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tka\AdaptiveSimulation;
use App\Models\Tka\EraporSyncLog;
use App\Models\Tka\LearningRecommendation;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Tka\TkaAdaptiveBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TkaInnovationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private User $adminA;

    private User $kepsekA;

    private User $guruA;

    private Siswa $siswaUnggul;

    private Siswa $siswaSedang;

    private Siswa $siswaLemah;

    private User $akunSiswaLemah;

    private User $ortuSiswaLemah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40312345',
            'nama_sekolah' => 'SDN Unggulan Inovasi A',
            'subdomain' => 'inovasi-a',
            'aktif' => true,
        ]);

        Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $this->adminA = User::factory()->admin()->create(['tenant_id' => $this->tenantA->id]);
        $this->kepsekA = User::factory()->kepsek()->create(['tenant_id' => $this->tenantA->id]);
        $this->guruA = User::factory()->guru()->create(['tenant_id' => $this->tenantA->id]);

        $this->siswaUnggul = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'nama_guru' => $this->guruA->nama_lengkap,
            'nis' => 'INOV001',
            'nama_peserta_didik' => 'Andi Unggul Kognitif',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $this->siswaSedang = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'nama_guru' => $this->guruA->nama_lengkap,
            'nis' => 'INOV002',
            'nama_peserta_didik' => 'Bela Rata Kognitif',
            'kelas' => '6A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);

        $this->siswaLemah = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'nama_guru' => $this->guruA->nama_lengkap,
            'nis' => 'INOV003',
            'nama_peserta_didik' => 'Citra Perlu Penguatan',
            'kelas' => '6A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);

        $this->akunSiswaLemah = User::factory()->siswa()->create([
            'tenant_id' => $this->tenantA->id,
            'nama_pengguna' => 'INOV003',
            'terhubung_dengan' => [$this->siswaLemah->id],
        ]);

        $this->ortuSiswaLemah = User::factory()->ortu()->create([
            'tenant_id' => $this->tenantA->id,
            'terhubung_dengan' => [$this->siswaLemah->id],
        ]);
    }

    public function test_simulasi_adaptif_menyesuaikan_kesulitan_dan_melaporkan_ke_orang_tua(): void
    {
        // Mulai simulasi adaptif paket logika
        $start = $this->actingAs($this->akunSiswaLemah)
            ->post(route('siswa.tka-simulasi.adaptif.start', 'logika'));

        $sim = AdaptiveSimulation::where('siswa_id', $this->siswaLemah->id)->firstOrFail();
        $this->assertSame('sedang', $sim->tingkat_awal);
        $start->assertRedirect(route('siswa.tka-simulasi.adaptif.play', $sim));

        $bank = app(TkaAdaptiveBankService::class);

        // Langkah 1: soal sedang dijawab BENAR -> tingkat kesulitan naik menjadi sulit
        $q1 = $bank->nextQuestionFor($sim->fresh());
        $this->assertNotNull($q1);
        $this->assertSame('sedang', $q1['tingkat']);

        $this->actingAs($this->akunSiswaLemah)
            ->post(route('siswa.tka-simulasi.adaptif.answer', $sim), [
                'question_id' => $q1['id'],
                'answer' => $q1['kunci'],
                'durasi_detik' => 25,
            ])
            ->assertRedirect(route('siswa.tka-simulasi.adaptif.play', $sim));

        $q2 = $bank->nextQuestionFor($sim->fresh());
        $this->assertNotNull($q2);
        $this->assertSame('sulit', $q2['tingkat']);

        // Langkah 2..6: jawab sampai selesai (sengaja salah agar skor logika rendah -> memicu paket latihan logika)
        for ($step = 2; $step <= 6; $step++) {
            $qNext = $bank->nextQuestionFor($sim->fresh());
            $this->assertNotNull($qNext);
            $wrongOption = $qNext['kunci'] === 'A' ? 'B' : 'A';

            $resp = $this->actingAs($this->akunSiswaLemah)
                ->post(route('siswa.tka-simulasi.adaptif.answer', $sim), [
                    'question_id' => $qNext['id'],
                    'answer' => $wrongOption,
                    'durasi_detik' => 20,
                ]);

            if ($step === 6) {
                $resp->assertRedirect(route('siswa.tka-simulasi.adaptif.result', $sim));
            }
        }

        $sim->refresh();
        $this->assertSame('selesai', $sim->status);
        $this->assertSame(6, $sim->total_soal);
        $this->assertSame(125, $sim->durasi_detik);

        // Rekomendasi personalisasi otomatis tersusun dengan Paket Latihan Logika
        $rekomendasi = LearningRecommendation::where('siswa_id', $this->siswaLemah->id)->first();
        $this->assertNotNull($rekomendasi);
        $judulPaket = array_column($rekomendasi->paket_tindak_lanjut, 'judul_paket');
        $this->assertContains('Paket Latihan Logika Deduktif, Pola & Silogisme', $judulPaket);

        // Orang tua dapat melihat laporan simulasi, durasi, tingkat kesulitan, dan memberi konfirmasi
        $ortuIndex = $this->actingAs($this->ortuSiswaLemah)
            ->get(route('ortu.tka-riwayat.index'));
        $ortuIndex->assertOk();
        $ortuIndex->assertSee('Simulasi Adaptif: Penalaran Logika & Berpikir Kritis');
        $ortuIndex->assertSee('2 mnt 5 dtk');

        $ortuShow = $this->actingAs($this->ortuSiswaLemah)
            ->get(route('ortu.tka-riwayat.show', $sim));
        $ortuShow->assertOk();
        $this->assertNull($sim->fresh()->dibaca_ortu_at, 'GET tidak boleh mengubah status baca');

        $this->actingAs($this->ortuSiswaLemah)
            ->post(route('ortu.tka-riwayat.konfirmasi', $sim), [
                'catatan_ortu' => 'Ananda sudah kami dampingi latihan logika di rumah.',
            ])
            ->assertRedirect();

        $this->assertNotNull($sim->fresh()->dibaca_ortu_at);
        $this->assertSame('Ananda sudah kami dampingi latihan logika di rumah.', $sim->fresh()->catatan_ortu);

        // Orang tua lain ditolak (403)
        $ortuLain = User::factory()->ortu()->create([
            'tenant_id' => $this->tenantA->id,
            'terhubung_dengan' => [$this->siswaUnggul->id],
        ]);
        $this->actingAs($ortuLain)
            ->get(route('ortu.tka-riwayat.show', $sim))
            ->assertForbidden();
    }

    public function test_dashboard_kognitif_zona_tren_dan_rekomendasi_personalisasi(): void
    {
        // Buat 2 riwayat uji untuk siswaUnggul (tren meningkat: 80 -> 94)
        AdaptiveSimulation::create([
            'tenant_id' => $this->tenantA->id,
            'siswa_id' => $this->siswaUnggul->id,
            'paket_kode' => 'literasi',
            'paket_judul' => 'Simulasi Literasi #1',
            'mode_simulasi' => 'adaptif',
            'tingkat_awal' => 'sedang',
            'tingkat_akhir' => 'sulit',
            'target_soal' => 6,
            'total_soal' => 6,
            'benar' => 5,
            'skor_akhir' => 80,
            'level_capaian' => 'Cakap',
            'durasi_detik' => 150,
            'skor_indikator' => ['Literasi Membaca' => 80, 'Numerasi Kuantitatif' => 82],
            'status' => 'selesai',
            'submitted_at' => now()->subDays(7),
        ]);
        AdaptiveSimulation::create([
            'tenant_id' => $this->tenantA->id,
            'siswa_id' => $this->siswaUnggul->id,
            'paket_kode' => 'numerasi',
            'paket_judul' => 'Simulasi Numerasi #2',
            'mode_simulasi' => 'adaptif',
            'tingkat_awal' => 'sedang',
            'tingkat_akhir' => 'sulit',
            'target_soal' => 6,
            'total_soal' => 6,
            'benar' => 6,
            'skor_akhir' => 94,
            'level_capaian' => 'Mahir',
            'durasi_detik' => 140,
            'skor_indikator' => ['Literasi Membaca' => 92, 'Numerasi Kuantitatif' => 96],
            'status' => 'selesai',
            'submitted_at' => now()->subDay(),
        ]);

        // Siswa sedang: skor ~72
        AdaptiveSimulation::create([
            'tenant_id' => $this->tenantA->id,
            'siswa_id' => $this->siswaSedang->id,
            'paket_kode' => 'sains',
            'paket_judul' => 'Simulasi Sains',
            'mode_simulasi' => 'adaptif',
            'tingkat_awal' => 'sedang',
            'tingkat_akhir' => 'sedang',
            'target_soal' => 6,
            'total_soal' => 6,
            'benar' => 4,
            'skor_akhir' => 72,
            'level_capaian' => 'Cakap',
            'durasi_detik' => 180,
            'skor_indikator' => ['Penalaran Sains' => 72],
            'status' => 'selesai',
            'submitted_at' => now()->subDay(),
        ]);

        // Siswa lemah: skor ~50
        AdaptiveSimulation::create([
            'tenant_id' => $this->tenantA->id,
            'siswa_id' => $this->siswaLemah->id,
            'paket_kode' => 'logika',
            'paket_judul' => 'Simulasi Logika',
            'mode_simulasi' => 'adaptif',
            'tingkat_awal' => 'sedang',
            'tingkat_akhir' => 'mudah',
            'target_soal' => 6,
            'total_soal' => 6,
            'benar' => 3,
            'skor_akhir' => 50,
            'level_capaian' => 'Dasar',
            'durasi_detik' => 210,
            'skor_indikator' => ['Logika & Penalaran Kritis' => 50],
            'status' => 'selesai',
            'submitted_at' => now()->subDay(),
        ]);

        // Guru melihat dashboard rekap kognitif otomatis + 3 zona kemampuan
        $resGuru = $this->actingAs($this->guruA)->get(route('guru.tka.kognitif.index'));
        $resGuru->assertOk();
        $resGuru->assertSee('Di Atas Rata-rata');
        $resGuru->assertSee('Rata-rata');
        $resGuru->assertSee('Di Bawah Rata-rata');
        $resGuru->assertSee('Andi Unggul Kognitif');
        $resGuru->assertSee('+14.0'); // tren meningkat 80 -> 94

        // Guru menyusun rekomendasi otomatis massal se-kelas
        $this->actingAs($this->guruA)
            ->post(route('guru.tka.rekomendasi.generate'), ['kelas' => '6A'])
            ->assertRedirect();

        $this->assertSame(3, LearningRecommendation::where('tenant_id', $this->tenantA->id)->count());

        $rekUnggul = LearningRecommendation::where('siswa_id', $this->siswaUnggul->id)->firstOrFail();
        $this->assertSame('di_atas_rata_rata', $rekUnggul->zona_kemampuan);

        $rekLemah = LearningRecommendation::where('siswa_id', $this->siswaLemah->id)->firstOrFail();
        $this->assertSame('di_bawah_rata_rata', $rekLemah->zona_kemampuan);

        // Guru memperbarui status tindak lanjut menjadi berjalan
        $this->actingAs($this->guruA)
            ->post(route('guru.tka.rekomendasi.update', $rekLemah), [
                'status_tindak_lanjut' => 'berjalan',
                'catatan_guru' => 'Pendampingan klinik logika setiap Rabu siang.',
            ])
            ->assertRedirect();

        $this->assertSame('berjalan', $rekLemah->fresh()->status_tindak_lanjut);

        // Detail per siswa & validasi UUID aman untuk PostgreSQL
        $this->actingAs($this->guruA)
            ->get(route('guru.tka.kognitif.show', $this->siswaUnggul->uuid))
            ->assertOk()
            ->assertSee('Uji #1')
            ->assertSee('Uji #2');

        $this->actingAs($this->guruA)
            ->get(route('guru.tka.kognitif.show', 'bukan-uuid'))
            ->assertNotFound();

        // Siswa dapat melihat profil kognitif dan paket latihannya sendiri
        $this->actingAs($this->akunSiswaLemah)
            ->get(route('siswa.tka-simulasi.kognitif'))
            ->assertOk()
            ->assertSee('Paket Latihan Logika Deduktif, Pola & Silogisme');
    }

    public function test_integrasi_tka_ke_penilaian_dan_erapor(): void
    {
        AdaptiveSimulation::create([
            'tenant_id' => $this->tenantA->id,
            'siswa_id' => $this->siswaUnggul->id,
            'paket_kode' => 'numerasi',
            'paket_judul' => 'Simulasi Numerasi',
            'mode_simulasi' => 'adaptif',
            'tingkat_awal' => 'sedang',
            'tingkat_akhir' => 'sulit',
            'target_soal' => 6,
            'total_soal' => 6,
            'benar' => 5,
            'skor_akhir' => 88,
            'level_capaian' => 'Mahir',
            'durasi_detik' => 160,
            'skor_indikator' => ['Numerasi Kuantitatif' => 88, 'Logika & Penalaran Kritis' => 84],
            'status' => 'selesai',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->guruA)
            ->get(route('guru.tka.integrasi-erapor.index'))
            ->assertOk()
            ->assertSee('Andi Unggul Kognitif');

        $response = $this->actingAs($this->guruA)
            ->post(route('guru.tka.integrasi-erapor.sync'), [
                'kelas' => '6A',
                'mata_pelajaran' => 'Matematika',
                'komponen_target' => 'sumatif',
                'semester' => 'Ganjil',
                'tahun_ajaran' => '2026/2027',
            ]);

        $response->assertRedirect();

        // Diagnostik: bila sinkronisasi gagal, tampilkan pesan error sesi/validasi
        // agar penyebabnya tidak sekadar "Session is missing expected key [success]".
        $session = $this->app['session.store'];
        $errorBag = $session->get('errors');
        $this->assertTrue(
            $session->has('success'),
            'Sinkronisasi e-Rapor tidak mengembalikan pesan sukses. session.error: '
            .json_encode($session->get('error'))
            .' | validation: '.json_encode($errorBag ? $errorBag->all() : [])
            ." | status: {$response->getStatusCode()} | target: {$response->headers->get('Location')}"
        );

        $nilai = Nilai::where('siswa_id', $this->siswaUnggul->id)
            ->where('mata_pelajaran', 'Matematika')
            ->where('jenis', 'ulangan')
            ->first();
        $this->assertNotNull($nilai);
        $this->assertEquals(86.0, (float) $nilai->nilai);

        $erapor = NilaiErapot::where('siswa_id', $this->siswaUnggul->id)
            ->where('mata_pelajaran', 'Matematika')
            ->where('semester', 'Ganjil')
            ->where('tahun_ajaran', '2026/2027')
            ->first();
        $this->assertNotNull($erapor);
        $this->assertEquals(86.0, (float) $erapor->nilai_sumatif);
        $this->assertEquals(86.0, (float) $erapor->nilai_akhir);
        $this->assertNotEmpty($erapor->predikat);
        $this->assertStringContainsString('indikator TKA', (string) $erapor->deskripsi_capaian);

        $this->assertSame(1, EraporSyncLog::where('siswa_id', $this->siswaUnggul->id)->count());
    }

    public function test_laporan_sekolah_per_kelas_heatmap_csv_dan_isolasi_multi_tenant(): void
    {
        AdaptiveSimulation::create([
            'tenant_id' => $this->tenantA->id,
            'siswa_id' => $this->siswaLemah->id,
            'paket_kode' => 'logika',
            'paket_judul' => 'Simulasi Logika 6A',
            'mode_simulasi' => 'adaptif',
            'tingkat_awal' => 'sedang',
            'tingkat_akhir' => 'mudah',
            'target_soal' => 6,
            'total_soal' => 6,
            'benar' => 2,
            'skor_akhir' => 45,
            'level_capaian' => 'Perlu Intervensi Khusus',
            'durasi_detik' => 190,
            'skor_indikator' => ['Logika & Penalaran Kritis' => 45],
            'status' => 'selesai',
            'submitted_at' => now(),
        ]);

        // Buat Sekolah B (tenant lain) dan pastikan datanya tidak bocor ke Sekolah A
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40399999',
            'nama_sekolah' => 'SDN Tetangga B',
            'subdomain' => 'tetangga-b',
            'aktif' => true,
        ]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $tenantB->id]);
        $siswaSekolahLain = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'guru_id' => $guruB->id,
            'nama_guru' => $guruB->nama_lengkap,
            'nis' => 'LAIN999',
            'nama_peserta_didik' => 'Siswa Rahasia Sekolah B',
            'kelas' => '6Z',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        // Admin Sekolah A melihat Heatmap & tidak melihat kelas/siswa Sekolah B
        $adminHeatmap = $this->actingAs($this->adminA)
            ->get(route('admin.tka.laporan-kelas.index', ['target' => 70]));
        $adminHeatmap->assertOk();
        $adminHeatmap->assertSee('6A');
        $adminHeatmap->assertSee('-25.0'); // kesenjangan 45 - 70 = -25.0
        $adminHeatmap->assertDontSee('6Z');
        $adminHeatmap->assertDontSee('Siswa Rahasia Sekolah B');

        // Kepsek Sekolah A melihat Heatmap & mengunduh CSV
        $kepsekHeatmap = $this->actingAs($this->kepsekA)
            ->get(route('kepsek.tka-laporan.index'));
        $kepsekHeatmap->assertOk();
        $kepsekHeatmap->assertSee('6A');
        $kepsekHeatmap->assertDontSee('6Z');

        $csv = $this->actingAs($this->kepsekA)
            ->get(route('kepsek.tka-laporan.export'));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('6A', $csv->streamedContent());
        $this->assertStringNotContainsString('6Z', $csv->streamedContent());

        // Admin Sekolah A tidak dapat mengakses profil kognitif siswa Sekolah B
        $this->actingAs($this->adminA)
            ->get(route('admin.tka.kognitif.show', $siswaSekolahLain->uuid))
            ->assertNotFound();
    }
}
