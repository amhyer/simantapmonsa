<?php

namespace Tests\Feature;

use App\Models\Kehadiran;
use App\Models\PresensiPegawai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PresensiLaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private User $tendik;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-19 10:00:00', config('app.timezone'))); // Hari Senin

        $this->admin = User::factory()->admin()->create(['nama_lengkap' => 'Admin Sekolah']);
        $this->guru = User::factory()->guru()->create(['nama_lengkap' => 'Guru Budiman', 'nip' => '198001012005011001']);
        $this->tendik = User::factory()->tendik()->create(['nama_lengkap' => 'Tendik Siti', 'nip' => '198502022010012002']);

        $this->siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'nama_peserta_didik' => 'Ahmad Siswa',
            'nis' => '1001',
            'nisn' => '0011223344',
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
    }

    public function test_admin_dapat_melihat_halaman_laporan_presensi(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.presensi.laporan'));
        $response->assertOk();
        $response->assertSee('Laporan Presensi');
        $response->assertSee('Guru');
        $response->assertSee('Tenaga Kependidikan');
        $response->assertSee('Siswa');
    }

    public function test_laporan_harian_guru_menghitung_jam_hari_minggu_dan_tahun(): void
    {
        $tanggalHariIni = '2026-10-19'; // Senin
        $tanggalKemarinMingguLalu = '2026-10-15'; // Kamis minggu sebelumnya (dalam tahun 2026 yang sama)

        // Presensi hari ini: 07:30 s.d 15:30 = 8 jam (480 menit)
        PresensiPegawai::create([
            'user_id' => $this->guru->id,
            'tanggal' => $tanggalHariIni,
            'status' => 'hadir',
            'waktu_masuk' => Carbon::parse('2026-10-19 07:30:00'),
            'waktu_pulang' => Carbon::parse('2026-10-19 15:30:00'),
            'metode' => 'gps',
        ]);

        // Presensi hari lain di minggu yang sama: 2026-10-20 (Selasa) = 7 jam (420 menit)
        PresensiPegawai::create([
            'user_id' => $this->guru->id,
            'tanggal' => '2026-10-20',
            'status' => 'hadir',
            'waktu_masuk' => Carbon::parse('2026-10-20 08:00:00'),
            'waktu_pulang' => Carbon::parse('2026-10-20 15:00:00'),
            'metode' => 'gps',
        ]);

        // Presensi di bulan sebelumnya dalam tahun yang sama: 2026-09-10 = 6 jam (360 menit)
        PresensiPegawai::create([
            'user_id' => $this->guru->id,
            'tanggal' => '2026-09-10',
            'status' => 'hadir',
            'waktu_masuk' => Carbon::parse('2026-09-10 08:00:00'),
            'waktu_pulang' => Carbon::parse('2026-09-10 14:00:00'),
            'metode' => 'gps',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.presensi.laporan', [
            'kategori' => 'guru',
            'periode' => 'harian',
            'tanggal' => $tanggalHariIni,
        ]));

        $response->assertOk();
        $response->assertSee('Guru Budiman');
        $response->assertSee('07:30');
        $response->assertSee('15:30');
        // Jam hari ini: 8j 0m (480 menit)
        $response->assertSee('8j 0m');
        // Jam minggu ini: 8j + 7j = 15j 0m (900 menit)
        $response->assertSee('15j 0m');
        // Jam tahun ini: 8j + 7j + 6j = 21j 0m (1260 menit)
        $response->assertSee('21j 0m');
    }

    public function test_laporan_tendik_bulanan_dan_tahunan(): void
    {
        // Tendik Siti presensi di bulan Oktober 2026
        PresensiPegawai::create([
            'user_id' => $this->tendik->id,
            'tanggal' => '2026-10-05',
            'status' => 'hadir',
            'waktu_masuk' => Carbon::parse('2026-10-05 07:00:00'),
            'waktu_pulang' => Carbon::parse('2026-10-05 15:00:00'), // 8 jam
            'metode' => 'fingerprint',
        ]);

        $responseBulanan = $this->actingAs($this->admin)->get(route('admin.presensi.laporan', [
            'kategori' => 'tendik',
            'periode' => 'bulanan',
            'bulan' => 10,
            'tahun' => 2026,
        ]));

        $responseBulanan->assertOk();
        $responseBulanan->assertSee('Tendik Siti');
        $responseBulanan->assertSee('8j 0m');

        $responseTahunan = $this->actingAs($this->admin)->get(route('admin.presensi.laporan', [
            'kategori' => 'tendik',
            'periode' => 'tahunan',
            'tahun' => 2026,
        ]));

        $responseTahunan->assertOk();
        $responseTahunan->assertSee('Tendik Siti');
        $responseTahunan->assertSee('8j 0m');
    }

    public function test_laporan_siswa_harian_bulanan_tahunan(): void
    {
        Kehadiran::create([
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'tanggal' => '2026-10-19',
            'status' => 'H',
            'waktu_masuk' => Carbon::parse('2026-10-19 07:15:00'),
            'metode' => 'qr',
        ]);

        // Harian Siswa
        $resHarian = $this->actingAs($this->admin)->get(route('admin.presensi.laporan', [
            'kategori' => 'siswa',
            'periode' => 'harian',
            'tanggal' => '2026-10-19',
        ]));
        $resHarian->assertOk();
        $resHarian->assertSee('Ahmad Siswa');
        $resHarian->assertSee('07:15');
        $resHarian->assertSee('Hadir');

        // Bulanan Siswa
        $resBulanan = $this->actingAs($this->admin)->get(route('admin.presensi.laporan', [
            'kategori' => 'siswa',
            'periode' => 'bulanan',
            'bulan' => 10,
            'tahun' => 2026,
        ]));
        $resBulanan->assertOk();
        $resBulanan->assertSee('Ahmad Siswa');

        // Tahunan Siswa
        $resTahunan = $this->actingAs($this->admin)->get(route('admin.presensi.laporan', [
            'kategori' => 'siswa',
            'periode' => 'tahunan',
            'tahun' => 2026,
        ]));
        $resTahunan->assertOk();
        $resTahunan->assertSee('Ahmad Siswa');
    }

    public function test_export_csv_laporan_pegawai_dan_siswa(): void
    {
        // Export CSV Pegawai
        $resExportPegawai = $this->actingAs($this->admin)->get(route('admin.presensi.laporan.export', [
            'kategori' => 'guru',
            'periode' => 'harian',
            'tanggal' => '2026-10-19',
        ]));
        $resExportPegawai->assertOk();
        $resExportPegawai->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Export CSV Siswa
        $resExportSiswa = $this->actingAs($this->admin)->get(route('admin.presensi.laporan.export', [
            'kategori' => 'siswa',
            'periode' => 'harian',
            'tanggal' => '2026-10-19',
        ]));
        $resExportSiswa->assertOk();
        $resExportSiswa->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_halaman_cetak_khusus_daftar_presensi_menampilkan_semua_tanggal_sesuai_bulan_dan_jam_harian(): void
    {
        PresensiPegawai::create([
            'user_id' => $this->guru->id,
            'tanggal' => '2026-10-01',
            'status' => 'hadir',
            'waktu_masuk' => Carbon::parse('2026-10-01 07:30:00'),
            'waktu_pulang' => Carbon::parse('2026-10-01 15:30:00'), // 8 jam (480 menit)
            'metode' => 'gps',
        ]);

        PresensiPegawai::create([
            'user_id' => $this->guru->id,
            'tanggal' => '2026-10-19',
            'status' => 'hadir',
            'waktu_masuk' => Carbon::parse('2026-10-19 07:15:00'),
            'waktu_pulang' => Carbon::parse('2026-10-19 15:45:00'), // 8 jam 30 menit (510 menit)
            'metode' => 'gps',
        ]);

        // 1. Bulan Oktober (31 hari): wajib menampilkan semua tanggal 1 sampai 31
        $responseOkt = $this->actingAs($this->admin)->get(route('admin.presensi.laporan.cetak', [
            'kategori' => 'guru',
            'bulan' => 10,
            'tahun' => 2026,
        ]));

        $responseOkt->assertOk();
        $responseOkt->assertSee('DAFTAR PRESENSI GURU');
        $responseOkt->assertSee('Tanggal 1 s.d. 31');
        $responseOkt->assertSee('Nama Pegawai');
        $responseOkt->assertSee('Jumlah Jam');
        $responseOkt->assertSee('Guru Budiman');

        $htmlOkt = $responseOkt->getContent();
        for ($d = 1; $d <= 31; $d++) {
            $this->assertMatchesRegularExpression('/<th[^>]*>' . $d . '<\/th>/', $htmlOkt, "Kolom tanggal {$d} harus ada pada bulan 31 hari.");
        }

        // Jam harian dihitung dari waktu masuk & pulang: 8j dan 8j 30m, total bulan = 16j 30m
        $responseOkt->assertSee('8j 30m');
        $responseOkt->assertSee('16j 30m');
        // Tidak memuat elemen layout aplikasi
        $responseOkt->assertDontSee('sidebar-nav');
        $responseOkt->assertDontSee('admin-tabs');

        // 2. Bulan November (30 hari): wajib menampilkan 1 s.d. 30, tidak ada tanggal 31
        $responseNov = $this->actingAs($this->admin)->get(route('admin.presensi.laporan.cetak', [
            'kategori' => 'guru',
            'bulan' => 11,
            'tahun' => 2026,
        ]));
        $responseNov->assertOk();
        $responseNov->assertSee('Tanggal 1 s.d. 30');
        $htmlNov = $responseNov->getContent();
        $this->assertMatchesRegularExpression('/<th[^>]*>30<\/th>/', $htmlNov);
        $this->assertDoesNotMatchRegularExpression('/<th[^>]*>31<\/th>/', $htmlNov);

        // 3. Bulan Februari 2026 (28 hari): wajib menampilkan 1 s.d. 28, tidak ada 29/30/31
        $responseFeb = $this->actingAs($this->admin)->get(route('admin.presensi.laporan.cetak', [
            'kategori' => 'guru',
            'bulan' => 2,
            'tahun' => 2026,
        ]));
        $responseFeb->assertOk();
        $responseFeb->assertSee('Tanggal 1 s.d. 28');
        $htmlFeb = $responseFeb->getContent();
        $this->assertMatchesRegularExpression('/<th[^>]*>28<\/th>/', $htmlFeb);
        $this->assertDoesNotMatchRegularExpression('/<th[^>]*>29<\/th>/', $htmlFeb);
    }

    public function test_halaman_cetak_mendukung_pilihan_tanggal_cetak_dan_tampilkan_jam_masuk_pulang(): void
    {
        PresensiPegawai::create([
            'user_id' => $this->guru->id,
            'tanggal' => '2026-10-19',
            'status' => 'hadir',
            'waktu_masuk' => Carbon::parse('2026-10-19 07:15:00'),
            'waktu_pulang' => Carbon::parse('2026-10-19 15:45:00'),
            'metode' => 'gps',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.presensi.laporan.cetak', [
            'kategori' => 'guru',
            'bulan' => 10,
            'tahun' => 2026,
            'tanggal_cetak' => '2026-10-31',
            'tampilkan_waktu' => 1,
        ]));

        $response->assertOk();

        // 1. Kolom input tanggal cetak terisi & titimangsa tanda tangan sesuai pilihan
        $response->assertSee('name="tanggal_cetak"', false);
        $response->assertSee('value="2026-10-31"', false);
        $response->assertSee('31 Oktober 2026');

        // 2. Mode tampilkan jam masuk & jam pulang aktif dan bertahan saat submit form
        $response->assertSee('<body class="tampilkan-waktu">', false);
        $response->assertSee('name="tampilkan_waktu"', false);
        $response->assertSee('07:15');
        $response->assertSee('15:45');
        $response->assertSee('8j 30m');
        $response->assertSee('detail-kosong', false);

        // 3. Halaman laporan utama juga memiliki kolom tanggal cetak
        $resLaporan = $this->actingAs($this->admin)->get(route('admin.presensi.laporan', [
            'kategori' => 'guru',
            'periode' => 'bulanan',
            'bulan' => 10,
            'tahun' => 2026,
            'tanggal_cetak' => '2026-10-31',
        ]));
        $resLaporan->assertOk();
        $resLaporan->assertSee('Tanggal Cetak');
        $resLaporan->assertSee('value="2026-10-31"', false);
    }

    public function test_non_admin_tidak_bisa_akses_laporan_presensi(): void
    {
        $this->actingAs($this->guru)->get(route('admin.presensi.laporan'))
            ->assertForbidden();

        $this->actingAs($this->guru)->get(route('admin.presensi.laporan.cetak'))
            ->assertForbidden();
    }
}
