<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KktpInterval;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\PembelajaranMendalam\VisitasiPengawas;
use App\Models\PengaturanGuru;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PengawasRoleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $pengawas;

    private User $kepsek;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Binaan Pengawas 1',
            'subdomain' => 'sdn-binaan-1',
            'aktif' => true,
        ]);
        TenantContext::set($this->tenant->id);

        Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);

        $this->pengawas = User::factory()->pengawas()->create([
            'tenant_id' => $this->tenant->id,
            'nama_lengkap' => 'Drs. H. Baharuddin, M.Pd.',
            'nip' => '197001011995031001',
        ]);
        $this->kepsek = User::factory()->kepsek()->create([
            'tenant_id' => $this->tenant->id,
            'nama_lengkap' => 'Hj. Rahmawati, S.Pd.',
        ]);
        $this->guru = User::factory()->guru()->create([
            'tenant_id' => $this->tenant->id,
            'nama_lengkap' => 'Budi Guru Kelas 1',
            'kelas_mata_pelajaran' => 'Kelas 1',
        ]);

        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_sekolah' => 'SDN Binaan Pengawas 1',
            'kepala_sekolah' => 'Hj. Rahmawati, S.Pd.',
            'kota' => 'Makassar',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_radar_mutu_dan_verifikasi_berjenjang_guru_kepsek_pengawas(): void
    {
        // 1. Guru menyusun CP & TP
        CapaianPembelajaran::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'deskripsi' => 'CP Bilangan Fase A',
        ]);
        TujuanPembelajaranPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'kode_tp' => 'TP.1',
            'deskripsi' => 'Membilang 1-20',
            'kelas_target' => 1,
            'semester_target' => 1,
            'alokasi_jp' => 6,
        ]);

        // 2. Kepala Sekolah menyetujui supervisi tahap 1
        $this->actingAs($this->kepsek)
            ->post(route('kepsek.supervisi-pm.keputusan', $this->guru->uuid), [
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
                'status' => SupervisiPm::STATUS_DISETUJUI,
                'catatan_kepsek' => 'Perangkat lengkap dan rapi.',
            ])
            ->assertRedirect();

        // 3. Pengawas membuka Dashboard Radar Mutu & Matriks Verifikasi
        $this->actingAs($this->pengawas)
            ->get(route('pengawas.dashboard', ['tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertSee('Radar Mutu')
            ->assertSee('Budi Guru Kelas 1')
            ->assertSee('Disetujui Kepsek');

        $this->actingAs($this->pengawas)
            ->get(route('pengawas.supervisi.show', ['guru' => $this->guru->uuid, 'tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertSee('Perangkat lengkap dan rapi.');

        // 4. Pengawas menyimpan Verifikasi Tahap 2 + Catatan Pembinaan & RTL
        $this->actingAs($this->pengawas)
            ->post(route('pengawas.supervisi.verifikasi', $this->guru->uuid), [
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
                'status_pengawas' => SupervisiPm::PENGAWAS_TERVERIFIKASI,
                'catatan_pengawas' => 'Sangat baik, integrasi Mindful & Joyful terlihat jelas.',
                'rekomendasi_rtl' => 'Bagikan praktik baik ini pada KKG gugus sekolah.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_supervisi', [
            'guru_id' => $this->guru->id,
            'pengawas_id' => $this->pengawas->id,
            'status_pengawas' => SupervisiPm::PENGAWAS_TERVERIFIKASI,
            'catatan_pengawas' => 'Sangat baik, integrasi Mindful & Joyful terlihat jelas.',
        ]);

        // 5. Pastikan catatan Pengawas tampil di halaman Supervisi Kepsek dan banner Guru
        $this->actingAs($this->kepsek)
            ->get(route('kepsek.supervisi-pm.show', ['guru' => $this->guru->uuid, 'tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertSee('Terverifikasi Pengawas')
            ->assertSee('Bagikan praktik baik ini pada KKG gugus sekolah.');

        $this->actingAs($this->guru)
            ->get(route('guru.pm.cp.index', ['tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertSee('Terverifikasi Pengawas')
            ->assertSee('Bagikan praktik baik ini pada KKG gugus sekolah.');
    }

    public function test_e_visitasi_rubrik_observasi_kelas_dan_cetak_berita_acara_pdf(): void
    {
        $this->actingAs($this->pengawas)
            ->post(route('pengawas.visitasi.store'), [
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
                'tanggal_visitasi' => '2026-11-21',
                'jenis_pendampingan' => 'supervisi_akademik',
                'guru_id' => $this->guru->id,
                'skor_berkesadaran' => 4,
                'skor_bermakna' => 4,
                'skor_menggembirakan' => 3,
                'skor_memahami' => 4,
                'skor_mengaplikasi' => 3,
                'skor_merefleksi' => 4,
                'temuan' => 'Murid antusias menggunakan alat peraga konkret saat belajar bilangan.',
                'rekomendasi' => 'Perkuat pertanyaan pemantik saat sesi refleksi akhir.',
                'tindak_lanjut' => 'Observasi lanjutan bulan depan.',
            ])
            ->assertRedirect();

        $visitasi = VisitasiPengawas::firstOrFail();
        $this->assertSame(3.67, $visitasi->rataRataSkor());
        $this->assertSame('Sangat Baik (Praktik Baik)', $visitasi->predikatObservasi());

        // Cetak Berita Acara Visitasi PDF
        $this->actingAs($this->pengawas)
            ->get(route('pengawas.visitasi.pdf', $visitasi->uuid))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Hapus catatan visitasi
        $this->actingAs($this->pengawas)
            ->delete(route('pengawas.visitasi.destroy', $visitasi->uuid))
            ->assertRedirect();

        $this->assertDatabaseCount('pm_visitasi_pengawas', 0);
    }

    public function test_audit_keselarasan_kurikulum_ttd_pengawas_dan_laporan_semester_pdf(): void
    {
        Storage::fake('local');

        TujuanPembelajaranPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'kode_tp' => 'TP.1',
            'deskripsi' => 'TP belum disinkronkan ke e-Rapor',
        ]);
        PengaturanGuru::create([
            'guru_id' => $this->guru->id,
            'nama_guru' => $this->guru->nama_lengkap,
            'kkm' => 75,
            'semester' => 'Ganjil',
            'tahun_pelajaran' => '2026/2027',
        ]);
        KktpInterval::create([
            'tenant_id' => $this->tenant->id,
            'nama_mapel' => 'Matematika',
            'tingkat' => 1,
            'batas_bawah' => 60,
            'nilai_kktp' => 80,
            'batas_atas' => 90,
            'updated_by' => $this->guru->id,
        ]);

        // Halaman Audit Kurikulum mendeteksi TP belum disinkronkan & KKTP (80) != KKM (75)
        $this->actingAs($this->pengawas)
            ->get(route('pengawas.audit.index', ['tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertSee('TP Pembelajaran Mendalam belum disinkronkan ke TP e-Rapor')
            ->assertSee('Nilai KKTP (80) berbeda dengan KKM e-Rapor (75)');

        // Unggah TTD Pengawas ke disk privat
        $this->actingAs($this->pengawas)
            ->post(route('pengawas.ttd.store'), [
                'nama_pengawas' => 'Drs. H. Baharuddin, M.Pd.',
                'nip_pengawas' => '197001011995031001',
                'ttd_pengawas' => UploadedFile::fake()->image('ttd_pengawas.png', 200, 80),
            ])
            ->assertRedirect();

        $sekolah = SekolahSettings::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertNotEmpty($sekolah->pengaturan['ttd_pengawas'] ?? null);
        Storage::disk('local')->assertExists($sekolah->pengaturan['ttd_pengawas']);

        // Cetak Laporan Kepengawasan Semester PDF
        $this->actingAs($this->pengawas)
            ->get(route('pengawas.laporan.pdf', ['tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pengawas_read_only_ditolak_mengubah_data_operasional_guru_dan_admin(): void
    {
        $this->actingAs($this->pengawas)->post(route('guru.pm.cp.store'), [
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'deskripsi' => 'Coba ubah CP guru',
        ])->assertForbidden();

        $this->actingAs($this->pengawas)->post(route('admin.pm.alokasi.store'), [
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'alokasi' => [],
        ])->assertForbidden();
    }

    public function test_pengawas_multi_sekolah_binaan_switcher_dan_isolasi_sekolah_lain(): void
    {
        $sekolahBinaan2 = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Binaan Pengawas 2',
            'subdomain' => 'sdn-binaan-2',
            'aktif' => true,
        ]);
        $sekolahBukanBinaan = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Bukan Binaan',
            'subdomain' => 'sdn-bukan-binaan',
            'aktif' => true,
        ]);

        $this->pengawas->update(['terhubung_dengan' => [$sekolahBinaan2->id]]);

        // Boleh beralih ke sekolah binaan ke-2
        $this->actingAs($this->pengawas)
            ->post(route('pengawas.sekolah.ganti'), ['tenant_id' => $sekolahBinaan2->id])
            ->assertRedirect();

        $this->assertSame($sekolahBinaan2->id, session('pengawas_active_tenant_id'));

        // Ditolak 403 bila mencoba beralih ke sekolah yang bukan binaannya
        $this->actingAs($this->pengawas)
            ->post(route('pengawas.sekolah.ganti'), ['tenant_id' => $sekolahBukanBinaan->id])
            ->assertForbidden();
    }
}
