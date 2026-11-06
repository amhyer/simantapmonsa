<?php

namespace Tests\Feature;

use App\Models\PresensiPegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PresensiMesinImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $header, array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return (string) $content;
    }

    private function upload(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_import_data_karyawan_mencocokkan_nama_dan_mengisi_nik(): void
    {
        $admin = User::factory()->admin()->create();
        $amir = User::factory()->guru()->create(['nama_lengkap' => 'Amirullah']);
        $apti = User::factory()->guru()->create(['nama_lengkap' => 'A. APTI ASPIRANDI']);
        $geo = User::factory()->guru()->create(['nama_lengkap' => 'Geovani. A.Z.Ponno']);
        $nurafni = User::factory()->guru()->create(['nama_lengkap' => 'Nurafni M.noesa S.pd']);

        $csv = $this->csv(['nik', 'nama'], [
            ['7309082109930003', 'Amirullah, S.Pd'],
            ['7371140103860006', 'A. Apti Aspirandi, S.Pd'],
            ['7371140103860007', 'Geovani. A.Z.Ponno., M.Th'],
            ['7371140103860008', 'Nurafni M.Noesa, S.Pd'],
        ]);

        $this->actingAs($admin)->post(route('admin.presensi.mesin.karyawan.import'), [
            'file' => $this->upload('karyawan.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('7309082109930003', $amir->fresh()->nik);
        $this->assertSame('7371140103860006', $apti->fresh()->nik);
        $this->assertSame('7371140103860007', $geo->fresh()->nik);
        $this->assertSame('7371140103860008', $nurafni->fresh()->nik);
    }

    public function test_nama_yang_tidak_cocok_dilewati_tanpa_mengubah_nik(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nama_lengkap' => 'Orang Lain']);

        $csv = $this->csv(['nik', 'nama'], [
            ['7309082109930003', 'Nama Tidak Terdaftar, S.Pd'],
        ]);

        $this->actingAs($admin)->post(route('admin.presensi.mesin.karyawan.import'), [
            'file' => $this->upload('karyawan.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertNull($guru->fresh()->nik);
    }

    public function test_import_log_mesin_mengambil_masuk_awal_dan_pulang_akhir(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nama_lengkap' => 'Amirullah',
            'nik' => '7309082109930003',
        ]);

        $header = ['Departemen', 'Nama', 'No.ID', 'Tgl/Waktu', 'Status', 'Lokasi ID', 'No.PIN', 'Kode Kerja', 'Kode Verifikasi', 'No.Kartu'];
        $csv = $this->csv($header, [
            ['SD', 'Amirullah, S.Pd', '2', '2026-06-01 06:55:00', 'C/Masuk', '1', '7309082109930003', '0', 'FP', ''],
            ['SD', 'Amirullah, S.Pd', '2', '2026-06-01 07:20:00', 'C/Masuk', '1', '7309082109930003', '0', 'FP', ''],
            ['SD', 'Amirullah, S.Pd', '2', '2026-06-01 13:55:00', 'C/Keluar', '1', '7309082109930003', '0', 'FP', ''],
            ['SD', 'Amirullah, S.Pd', '2', '2026-06-01 14:10:00', 'C/Keluar', '1', '7309082109930003', '0', 'FP', ''],
            ['SD', 'Amirullah, S.Pd', '2', '2026-06-02 07:25:00', 'C/Masuk', '1', '7309082109930003', '0', 'FP', ''],
            ['SD', 'Amirullah, S.Pd', '2', '2026-06-02 14:05:00', 'C/Keluar', '1', '7309082109930003', '0', 'FP', ''],
        ]);

        $this->actingAs($admin)->post(route('admin.presensi.mesin.log.import'), [
            'file' => $this->upload('InOutData.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $pertama = PresensiPegawai::where('user_id', $guru->id)->whereDate('tanggal', '2026-06-01')->firstOrFail();
        $this->assertSame('hadir', $pertama->status);
        $this->assertSame('mesin', $pertama->metode);
        $this->assertSame('06:55:00', $pertama->waktu_masuk->format('H:i:s'));
        $this->assertSame('14:10:00', $pertama->waktu_pulang->format('H:i:s'));

        $kedua = PresensiPegawai::where('user_id', $guru->id)->whereDate('tanggal', '2026-06-02')->firstOrFail();
        $this->assertSame('terlambat', $kedua->status);
        $this->assertSame('07:25:00', $kedua->waktu_masuk->format('H:i:s'));
        $this->assertSame('14:05:00', $kedua->waktu_pulang->format('H:i:s'));
    }

    public function test_import_template_log_dengan_kolom_jam_terpisah(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nama_lengkap' => 'Guru Template',
            'nik' => '7371140103860006',
        ]);

        $csv = $this->csv(['nik', 'tanggal', 'jam_masuk', 'jam_pulang'], [
            ['7371140103860006', '2026-06-03', '07:30', '14:00'],
        ]);

        $this->actingAs($admin)->post(route('admin.presensi.mesin.log.import'), [
            'file' => $this->upload('template-log.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $record = PresensiPegawai::where('user_id', $guru->id)->firstOrFail();
        $this->assertSame('terlambat', $record->status);
        $this->assertSame('2026-06-03', $record->tanggal->format('Y-m-d'));
        $this->assertSame('07:30:00', $record->waktu_masuk->format('H:i:s'));
        $this->assertSame('14:00:00', $record->waktu_pulang->format('H:i:s'));
        $this->assertSame('mesin', $record->metode);
    }

    public function test_koreksi_manual_tidak_ditimpa_import_mesin(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nama_lengkap' => 'Guru Manual',
            'nik' => '7309082109930003',
        ]);

        PresensiPegawai::create([
            'user_id' => $guru->id,
            'tanggal' => '2026-06-04',
            'status' => 'hadir',
            'waktu_masuk' => '2026-06-04 08:00:00',
            'waktu_pulang' => '2026-06-04 14:00:00',
            'metode' => 'admin',
            'alasan_koreksi' => 'Sudah dikoreksi admin.',
        ]);

        $csv = $this->csv(['nik', 'tanggal', 'jam_masuk', 'jam_pulang'], [
            ['7309082109930003', '2026-06-04', '06:45', '14:30'],
        ]);

        $this->actingAs($admin)->post(route('admin.presensi.mesin.log.import'), [
            'file' => $this->upload('template-log.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $record = PresensiPegawai::where('user_id', $guru->id)->whereDate('tanggal', '2026-06-04')->firstOrFail();
        $this->assertSame('admin', $record->metode);
        $this->assertSame('08:00:00', $record->waktu_masuk->format('H:i:s'));
        $this->assertSame('14:00:00', $record->waktu_pulang->format('H:i:s'));
        $this->assertSame('Sudah dikoreksi admin.', $record->alasan_koreksi);
    }

    public function test_status_izin_tidak_diubah_import_mesin(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nama_lengkap' => 'Guru Izin',
            'nik' => '7309082109930003',
        ]);

        PresensiPegawai::create([
            'user_id' => $guru->id,
            'tanggal' => '2026-06-05',
            'status' => 'izin',
            'metode' => 'admin',
        ]);

        $csv = $this->csv(['nik', 'tanggal', 'jam_masuk', 'jam_pulang'], [
            ['7309082109930003', '2026-06-05', '07:00', '14:00'],
        ]);

        $this->actingAs($admin)->post(route('admin.presensi.mesin.log.import'), [
            'file' => $this->upload('template-log.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $record = PresensiPegawai::where('user_id', $guru->id)->whereDate('tanggal', '2026-06-05')->firstOrFail();
        $this->assertSame('izin', $record->status);
        $this->assertNull($record->waktu_masuk);
    }

    public function test_nik_tidak_dikenal_dilewati(): void
    {
        User::factory()->admin()->create();

        $csv = $this->csv(['nik', 'tanggal', 'jam_masuk', 'jam_pulang'], [
            ['9999999999999999', '2026-06-06', '07:00', '14:00'],
        ]);

        $this->actingAs(User::where('peran', 'admin')->first())
            ->post(route('admin.presensi.mesin.log.import'), [
                'file' => $this->upload('template-log.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('presensi_pegawai', 0);
    }

    public function test_halaman_dan_template_dapat_diakses_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.presensi.mesin.index'))
            ->assertOk()
            ->assertSee('Import Presensi Mesin');

        $this->actingAs($admin)->get(route('admin.presensi.mesin.template.karyawan'))->assertOk();
        $this->actingAs($admin)->get(route('admin.presensi.mesin.template.log'))->assertOk();
    }

    public function test_non_admin_tidak_boleh_membuka_halaman_import_mesin(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get(route('admin.presensi.mesin.index'))->assertForbidden();
    }
}
