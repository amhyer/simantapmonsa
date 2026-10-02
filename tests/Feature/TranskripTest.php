<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\TranskripNilai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class TranskripTest extends TestCase
{
    use RefreshDatabase;

    private function buatSiswa(string $nisn = '1234567890'): Siswa
    {
        $guru = User::factory()->guru()->create();

        return Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'S' . $nisn,
            'nisn' => $nisn,
            'nama_peserta_didik' => 'Budi Transkrip',
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
        ]);
    }

    private function buatMapel(): MataPelajaran
    {
        return MataPelajaran::create([
            'nama' => 'Matematika',
            'kode' => 'MTK',
            'kelompok' => 'A',
            'urutan' => 1,
            'masuk_transkrip' => true,
            'aktif' => true,
        ]);
    }

    public function test_guest_dialihkan_ke_login(): void
    {
        $this->get(route('admin.transkrip.setting'))->assertRedirect(route('login'));
    }

    public function test_guru_ditolak_akses_transkrip(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get(route('admin.transkrip.setting'))->assertForbidden();
        $this->actingAs($guru)->get(route('admin.transkrip.cetak'))->assertForbidden();
    }

    public function test_admin_dapat_membuka_semua_halaman(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $this->buatMapel();

        foreach (['admin.transkrip.setting', 'admin.transkrip.nomor', 'admin.transkrip.input',
            'admin.transkrip.import', 'admin.transkrip.cetak'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
        $this->actingAs($admin)->get(route('admin.transkrip.input.form', $siswa))->assertOk();
        $this->actingAs($admin)->get(route('admin.transkrip.cetak.show', $siswa))->assertOk();
        $this->actingAs($admin)->get(route('admin.transkrip.import.template'))->assertOk();
    }

    public function test_setting_tersimpan_di_pengaturan_json(): void
    {
        $admin = User::factory()->admin()->create();
        SekolahSettings::create(['guru_id' => $admin->id, 'nama_sekolah' => 'SDN 1 Contoh']);

        $this->actingAs($admin)->put(route('admin.transkrip.setting.update'), [
            'tempat' => 'Cirebon',
            'tahun_lulus' => '2025/2026',
            'nip_kepala_sekolah' => '196001011985031001',
        ])->assertRedirect();

        $pengaturan = SekolahSettings::first()->pengaturan;
        $this->assertSame('Cirebon', $pengaturan['transkrip']['tempat']);
        $this->assertSame('2025/2026', $pengaturan['transkrip']['tahun_lulus']);
    }

    public function test_nomor_ijazah_tersimpan_dan_unik(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $lain = $this->buatSiswa('0987654321');

        $this->actingAs($admin)->put(route('admin.transkrip.nomor.update', $siswa), [
            'nomor_ijazah' => 'DN-001',
        ])->assertSessionHasNoErrors();
        $this->assertSame('DN-001', $siswa->fresh()->nomor_ijazah);

        $this->actingAs($admin)->put(route('admin.transkrip.nomor.update', $lain), [
            'nomor_ijazah' => 'DN-001',
        ])->assertSessionHasErrors('nomor_ijazah');
    }

    public function test_input_nilai_upsert_dan_predikat_otomatis(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $mapel = $this->buatMapel();

        $this->actingAs($admin)->put(route('admin.transkrip.input.store', $siswa), [
            'nilai' => [$mapel->id => 95],
        ])->assertRedirect();

        $nilai = TranskripNilai::where('siswa_id', $siswa->id)->first();
        $this->assertNotNull($nilai);
        $this->assertSame('A', $nilai->predikat);
        $this->assertSame('Matematika', $nilai->nama_mapel);

        // PUT kedua menimpa, bukan duplikat.
        $this->actingAs($admin)->put(route('admin.transkrip.input.store', $siswa), [
            'nilai' => [$mapel->id => 72],
        ])->assertRedirect();
        $this->assertSame(1, TranskripNilai::where('siswa_id', $siswa->id)->count());
        $this->assertSame('C', TranskripNilai::first()->predikat);
    }

    public function test_input_nilai_menolak_di_luar_rentang(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $mapel = $this->buatMapel();

        $this->actingAs($admin)->put(route('admin.transkrip.input.store', $siswa), [
            'nilai' => [$mapel->id => 150],
        ])->assertSessionHasErrors('nilai.' . $mapel->id);
        $this->assertSame(0, TranskripNilai::count());
    }

    public function test_import_nilai_csv_upsert(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $this->buatMapel();

        $csv = "nisn,kode_mapel,nilai_akhir\n1234567890,MTK,85.5\n9999999999,MTK,90\n";
        $file = UploadedFile::fake()->createWithContent('nilai.csv', $csv);

        $this->actingAs($admin)->post(route('admin.transkrip.import.store'), [
            'file' => $file,
        ])->assertSessionHasNoErrors();

        // Hanya NISN terdaftar yang masuk; NISN asing dilewati.
        $this->assertSame(1, TranskripNilai::count());
        $this->assertSame('B', TranskripNilai::first()->predikat);
    }

    public function test_import_nilai_csv_menolak_header_salah(): void
    {
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->createWithContent('salah.csv', "a,b,c\n1,2,3\n");

        $this->actingAs($admin)->post(route('admin.transkrip.import.store'), [
            'file' => $file,
        ])->assertSessionHasErrors('file');
    }

    public function test_import_nomor_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();

        $csv = "nisn,nomor_ijazah\n1234567890,DN-007\n";
        $file = UploadedFile::fake()->createWithContent('nomor.csv', $csv);

        $this->actingAs($admin)->post(route('admin.transkrip.nomor.import'), [
            'file' => $file,
        ])->assertSessionHasNoErrors();
        $this->assertSame('DN-007', $siswa->fresh()->nomor_ijazah);
    }

    public function test_cetak_menampilkan_nama_dan_rata_rata(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = $this->buatSiswa();
        $mapel = $this->buatMapel();
        TranskripNilai::create([
            'siswa_id' => $siswa->id,
            'mata_pelajaran_id' => $mapel->id,
            'nama_mapel' => 'Matematika',
            'nilai_akhir' => 80,
            'predikat' => 'B',
        ]);

        $res = $this->actingAs($admin)->get(route('admin.transkrip.cetak.show', $siswa))->assertOk();
        $res->assertSee('Budi Transkrip');
        $res->assertSee('Matematika');
    }

    public function test_tentukan_predikat_mengikuti_kkm(): void
    {
        $this->assertSame('A', TranskripNilai::tentukanPredikat(95));
        $this->assertSame('B', TranskripNilai::tentukanPredikat(85));
        $this->assertSame('D', TranskripNilai::tentukanPredikat(10));
    }
}
