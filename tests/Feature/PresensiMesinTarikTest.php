<?php

namespace Tests\Feature;

use App\Models\PresensiPegawai;
use App\Models\User;
use App\Services\MesinPresensi\AttendanceDatabaseReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class PresensiMesinTarikTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array{nik: string, waktu: Carbon, jenis: string}>  $scans
     */
    private function readerDenganScan(array $scans): void
    {
        $this->mock(AttendanceDatabaseReader::class, function ($mock) use ($scans) {
            $mock->shouldReceive('baca')->once()->andReturn($scans);
            $mock->shouldReceive('status')->andReturn([
                'ok' => true,
                'pesan' => 'Database berhasil dibaca.',
                'path' => 'D:\\ZKTime\\bin\\att2000.mdb',
                'engine' => 'powershell',
                'jumlah_pegawai' => 10,
                'jumlah_scan' => 100,
            ]);
        });
    }

    public function test_tarik_langsung_membuat_presensi_mesin(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nama_lengkap' => 'Amirullah',
            'nik' => '7309082109930003',
        ]);

        $this->readerDenganScan([
            ['nik' => '7309082109930003', 'waktu' => Carbon::parse('2026-06-01 06:55:00'), 'jenis' => 'masuk'],
            ['nik' => '7309082109930003', 'waktu' => Carbon::parse('2026-06-01 14:10:00'), 'jenis' => 'pulang'],
            ['nik' => '7309082109930003', 'waktu' => Carbon::parse('2026-06-02 07:25:00'), 'jenis' => 'masuk'],
            ['nik' => '7309082109930003', 'waktu' => Carbon::parse('2026-06-02 14:05:00'), 'jenis' => 'pulang'],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik'), [
                'dari' => '2026-06-01',
                'sampai' => '2026-06-02',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $pertama = PresensiPegawai::where('user_id', $guru->id)->whereDate('tanggal', '2026-06-01')->firstOrFail();
        $this->assertSame('mesin', $pertama->metode);
        $this->assertSame('hadir', $pertama->status);
        $this->assertSame('06:55:00', $pertama->waktu_masuk->format('H:i:s'));
        $this->assertSame('14:10:00', $pertama->waktu_pulang->format('H:i:s'));

        $kedua = PresensiPegawai::where('user_id', $guru->id)->whereDate('tanggal', '2026-06-02')->firstOrFail();
        $this->assertSame('terlambat', $kedua->status);
        $this->assertSame('07:25:00', $kedua->waktu_masuk->format('H:i:s'));
    }

    public function test_tarik_menghormati_koreksi_manual_dan_status_izin(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nama_lengkap' => 'Guru Manual',
            'nik' => '7309082109930003',
        ]);

        PresensiPegawai::create([
            'user_id' => $guru->id,
            'tanggal' => '2026-06-01',
            'status' => 'hadir',
            'waktu_masuk' => '2026-06-01 08:00:00',
            'waktu_pulang' => '2026-06-01 14:00:00',
            'metode' => 'admin',
            'alasan_koreksi' => 'Koreksi manual.',
        ]);

        PresensiPegawai::create([
            'user_id' => $guru->id,
            'tanggal' => '2026-06-02',
            'status' => 'izin',
            'metode' => 'admin',
        ]);

        $this->readerDenganScan([
            ['nik' => '7309082109930003', 'waktu' => Carbon::parse('2026-06-01 06:00:00'), 'jenis' => 'masuk'],
            ['nik' => '7309082109930003', 'waktu' => Carbon::parse('2026-06-02 06:00:00'), 'jenis' => 'masuk'],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik'), [
                'dari' => '2026-06-01',
                'sampai' => '2026-06-02',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $manual = PresensiPegawai::where('user_id', $guru->id)->whereDate('tanggal', '2026-06-01')->firstOrFail();
        $this->assertSame('admin', $manual->metode);
        $this->assertSame('08:00:00', $manual->waktu_masuk->format('H:i:s'));

        $izin = PresensiPegawai::where('user_id', $guru->id)->whereDate('tanggal', '2026-06-02')->firstOrFail();
        $this->assertSame('izin', $izin->status);
        $this->assertNull($izin->waktu_masuk);
    }

    public function test_tarik_menolak_rentang_lebih_dari_31_hari(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('baca')->never();
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik'), [
                'dari' => '2026-05-01',
                'sampai' => '2026-06-30',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('dari');
    }

    public function test_tarik_melaporkan_gagal_baca_database(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('baca')->once()->andThrow(new RuntimeException('Database terkunci.'));
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik'), [
                'dari' => '2026-06-01',
                'sampai' => '2026-06-02',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('mesin');

        $this->assertDatabaseCount('presensi_pegawai', 0);
    }

    public function test_tarik_tanpa_scan_tetap_berhasil(): void
    {
        $admin = User::factory()->admin()->create();

        $this->readerDenganScan([]);

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik'), [
                'dari' => '2026-06-01',
                'sampai' => '2026-06-02',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('presensi_pegawai', 0);
    }

    public function test_tes_koneksi_mengembalikan_status_json(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('status')->once()->andReturn([
                'ok' => true,
                'pesan' => 'Database berhasil dibaca.',
                'path' => 'D:\\ZKTime\\bin\\att2000.mdb',
                'engine' => 'powershell',
                'jumlah_pegawai' => 10,
                'jumlah_scan' => 100,
            ]);
            $mock->shouldReceive('rentang')->once()->andReturn([
                'min' => Carbon::parse('2026-06-01'),
                'maks' => Carbon::parse('2026-06-02'),
            ]);
        });

        $this->actingAs($admin)
            ->postJson(route('admin.presensi.mesin.tes'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'engine' => 'powershell',
                'jumlah_pegawai' => 10,
            ]);
    }

    public function test_tarik_semua_memproses_seluruh_rentang_scan(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nama_lengkap' => 'Amirullah', 'nik' => '0000000002']);

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('rentang')->once()->andReturn([
                'min' => Carbon::parse('2026-06-01'),
                'maks' => Carbon::parse('2026-07-31'),
            ]);
            $mock->shouldReceive('baca')->twice()->andReturn([
                ['nik' => '0000000002', 'waktu' => Carbon::parse('2026-06-01 06:55:00'), 'jenis' => 'masuk'],
                ['nik' => '0000000002', 'waktu' => Carbon::parse('2026-06-01 14:10:00'), 'jenis' => 'pulang'],
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik.semua'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('presensi_pegawai', 1);
    }

    public function test_tarik_semua_mendapatkan_error_jika_tidak_bisa_membaca(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('rentang')->once()->andThrow(new RuntimeException('Database terkunci.'));
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik.semua'))
            ->assertRedirect()
            ->assertSessionHasErrors('mesin');

        $this->assertDatabaseCount('presensi_pegawai', 0);
    }

    public function test_tarik_semua_dengan_tanpa_scan_membuat_tidak_ada_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('rentang')->once()->andReturn([
                'min' => null,
                'maks' => null,
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.tarik.semua'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_baca_menggunakan_engine_tcp_mengembalikan_data_via_fallback(): void
    {
        config([
            'presensi.mesin_db_engine' => 'tcp',
            'presensi.mesin_db_tcp_host' => '127.0.0.1',
            'presensi.mesin_db_tcp_port' => 59999,
        ]);

        // TCP gagal -> fallback ke database -> tetap mengembalikan daftar scan (bukan exception).
        $scans = app(AttendanceDatabaseReader::class)->baca(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-04'));

        $this->assertIsArray($scans);
        $this->assertNotEmpty($scans);
    }

    public function test_status_dengan_engine_tcp_mengembalikan_gagal_koneksi(): void
    {
        config([
            'presensi.mesin_db_engine' => 'tcp',
            'presensi.mesin_db_tcp_host' => '127.0.0.1',
            'presensi.mesin_db_tcp_port' => 59999,
        ]);

        $status = app(AttendanceDatabaseReader::class)->status();

        $this->assertFalse($status['ok']);
        $this->assertSame('tcp', $status['engine']);
    }

    public function test_engine_mengembalikan_tcp(): void
    {
        config([
            'presensi.mesin_db_engine' => 'tcp',
            'presensi.mesin_db_tcp_host' => '192.168.1.7',
            'presensi.mesin_db_tcp_port' => 4370,
        ]);

        $this->assertSame('tcp', app(AttendanceDatabaseReader::class)->engine());
    }

    public function test_non_admin_tidak_boleh_menarik_data_mesin(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)
            ->post(route('admin.presensi.mesin.tarik'), [
                'dari' => '2026-06-01',
                'sampai' => '2026-06-02',
            ])
            ->assertForbidden();
    }

    public function test_tarik_karyawan_mengisi_nik_berdasarkan_nama(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nama_lengkap' => 'Amirullah']);

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('pegawai')->once()->andReturn([
                ['nik' => '0000000002', 'nama' => 'Amirullah, S.Pd'],
                ['nik' => '0000000003', 'nama' => 'Bukan Pegawai Sekolah Ini'],
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.karyawan.tarik'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('0000000002', $guru->fresh()->nik);
    }

    public function test_tarik_karyawan_tidak_menimpa_nik_berbeda(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nama_lengkap' => 'Amirullah',
            'nik' => '7309082109930003',
        ]);

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('pegawai')->once()->andReturn([
                ['nik' => '0000000002', 'nama' => 'Amirullah, S.Pd'],
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.karyawan.tarik'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('7309082109930003', $guru->fresh()->nik);
    }

    public function test_tarik_karyawan_tanpa_data_pegawai_tetap_berhasil(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('pegawai')->once()->andReturn([]);
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.karyawan.tarik'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_tarik_karyawan_melaporkan_gagal_baca_database(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('pegawai')->once()->andThrow(new RuntimeException('Database terkunci.'));
        });

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.karyawan.tarik'))
            ->assertRedirect()
            ->assertSessionHasErrors('mesin');
    }

    public function test_non_admin_tidak_boleh_menarik_karyawan(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)
            ->post(route('admin.presensi.mesin.karyawan.tarik'))
            ->assertForbidden();
    }

    public function test_simpan_pengaturan_mesin_mengubah_engine(): void
    {
        $admin = User::factory()->admin()->create();
        \App\Models\SekolahSettings::create([
            'nama_sekolah' => 'Sekolah Uji Mesin',
            'pengaturan' => ['presensi_mesin_engine' => 'powershell'],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.pengaturan'), ['mesin_engine' => 'tcp'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $sekolah = \App\Models\SekolahSettings::query()->first();
        $this->assertSame('tcp', $sekolah?->pengaturan['presensi_mesin_engine'] ?? null);
    }

    public function test_simpan_pengaturan_mesin_menolak_engine_tidak_valid(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.presensi.mesin.pengaturan'), ['mesin_engine' => 'engine-ngawur'])
            ->assertRedirect()
            ->assertSessionHasErrors('mesin_engine');
    }

    public function test_reader_menggunakan_engine_dari_pengaturan_website(): void
    {
        config(['presensi.mesin_db_engine' => 'powershell']);
        \App\Models\SekolahSettings::query()->firstOrCreate([], [
            'nama_sekolah' => 'Sekolah Uji Mesin',
            'pengaturan' => ['presensi_mesin_engine' => 'tcp'],
        ]);

        $this->assertSame('tcp', app(AttendanceDatabaseReader::class)->engineTerpilih());
    }
}
