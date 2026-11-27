<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TranskripNilai;
use App\Models\User;
use App\Services\Backup\EraporXspBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class EraporXspBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_restore_and_export_erapor_xsp_via_backup_controller(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create([
            'peran' => 'admin',
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $svc = app(EraporXspBackupService::class);
        $key = $svc->keyBinary();

        $idSekolah = '508de7c4-0d26-e111-afd3-abf2101f474f';
        $ptkUuid = '6ac57284-ad42-4130-885e-45a6b6b99d56';
        $ptkTerdaftarUuid = '106a690a-9973-464d-b193-70721820c0be';
        $rombelUuid = '29604be2-5ae4-4d15-975b-176b852984be';
        $anggotaUuid = 'eafb1f31-1566-48d7-a2c0-aca47ee74226';
        $siswaUuid = 'a09e6253-ff49-46e7-88a8-0826161e1c6d';

        $entries = [
            1 => ['nm_tabel' => 'semester', 'tabel' => 'public.semester', 'data' => [[
                'semester_id' => '20252',
                'tahun_ajaran_id' => '2025',
                'nama_semester' => '2025/2026 Genap',
                'semester' => '2',
                'periode_aktif' => '1',
            ]]],
            2 => ['nm_tabel' => 'tabel sekolah', 'tabel' => 'public.tabel_sekolah', 'data' => [[
                'sekolah_id' => $idSekolah,
                'nama' => 'UPT SPF SD NEGERI UNGGULAN MONGISIDI 1',
                'npsn' => '40313912',
                'alamat' => 'Jln. R.W. Monginsidi No.13',
                'kd_pos' => '90142',
                'telepon' => '04118918116',
                'kecamatan' => 'Kec. Makasar',
                'kab_kota' => 'Kota Makassar',
                'propinsi' => 'Prov. Sulawesi Selatan',
                'email' => 'sdnungmongisidi1@gmail.com',
                'nm_kepsek' => 'ANDI WINDAYANI',
                'jenjang' => 'SD',
            ]]],
            3 => ['nm_tabel' => 'tabel ptk', 'tabel' => 'public.tabel_ptk', 'data' => [[
                'ptk_id' => $ptkUuid,
                'nama' => 'HAIRUDDIN',
                'nip' => '198705172020121003',
                'nuptk' => '6849765666130272',
                'jenis_kelamin' => 'L',
            ]]],
            4 => ['nm_tabel' => 'tabel terdaftar', 'tabel' => 'public.tabel_ptk_terdaftar', 'data' => [[
                'ptk_terdaftar_id' => $ptkTerdaftarUuid,
                'ptk_id' => $ptkUuid,
                'tahun_ajaran_id' => '2025',
            ]]],
            6 => ['nm_tabel' => 'tabel siswa', 'tabel' => 'public.tabel_siswa', 'data' => [[
                'peserta_didik_id' => $siswaUuid,
                'nis' => '2021167003',
                'nisn' => '3134143823',
                'nm_siswa' => 'MUH. ISYRAQ DJIBRIL GANENDRA',
                'tempat_lahir' => 'Makassar',
                'tanggal_lahir' => '2013-01-16',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
            ], [
                // e-Rapor sering menulis NIS '-' untuk siswa tanpa NIS; baris seperti ini
                // tidak boleh saling menimpa saat restore.
                'peserta_didik_id' => 'b6b3a1d0-6f1b-4a8a-9f9f-0f0a2b6e1c11',
                'nis' => '-',
                'nisn' => '3134143824',
                'nm_siswa' => 'SITI RAHMAWATI',
                'tempat_lahir' => 'Makassar',
                'tanggal_lahir' => '2013-03-11',
                'jenis_kelamin' => 'P',
                'agama' => 'Islam',
            ]]],
            9 => ['nm_tabel' => 'tabel kelas', 'tabel' => 'public.tabel_kelas', 'data' => [[
                'rombongan_belajar_id' => $rombelUuid,
                'sekolah_id' => $idSekolah,
                'semester_id' => '20252',
                'ptk_id' => $ptkUuid,
                'nm_kelas' => 'Kelas 4-B',
                'tingkat_pendidikan_id' => '4',
                'jenis_rombel' => '1',
            ]]],
            11 => ['nm_tabel' => 'tabel anggotakelas', 'tabel' => 'public.tabel_anggotakelas', 'data' => [[
                'anggota_rombel_id' => $anggotaUuid,
                'peserta_didik_id' => $siswaUuid,
                'rombongan_belajar_id' => $rombelUuid,
                'semester_id' => '20252',
            ]]],
            12 => ['nm_tabel' => 'tabel mapel', 'tabel' => 'public.tabel_mapel', 'data' => [[
                'mata_pelajaran_id' => '401000000',
                'nm_mapel' => 'Matematika',
                'nm_lokal' => 'Matematika',
                'kelompok' => 'A',
                'urut_rapor' => '1',
            ]]],
            13 => ['nm_tabel' => 'tabel pembelajaran', 'tabel' => 'public.tabel_pembelajaran', 'data' => [[
                'pembelajaran_id' => 'faae94ae-5e6c-4c68-be44-a34d24bbe18d',
                'rombongan_belajar_id' => $rombelUuid,
                'mata_pelajaran_id' => '401000000',
                'semester_id' => '20252',
                'ptk_terdaftar_id' => $ptkTerdaftarUuid,
                'nama_mata_pelajaran' => 'Matematika',
            ]]],
            29 => ['nm_tabel' => 'tabel nilaiakhir', 'tabel' => 'public.tabel_nilaiakhir', 'data' => [[
                'id_nilai_akhir' => '5c05a3c6-0e23-4bf6-bbad-d6f83dbbb285',
                'anggota_rombel_id' => $anggotaUuid,
                'peserta_didik_id' => $siswaUuid,
                'mata_pelajaran_id' => '401000000',
                'semester_id' => '20252',
                'nilai_peng' => '92',
            ]]],
            31 => ['nm_tabel' => 'tabel nilaiskl', 'tabel' => 'public.tabel_nilaiskl', 'data' => [[
                'id_nilai_skl' => '972cc9c4-9604-4d59-b03d-f410e8e303fe',
                'semester_id' => '20252',
                'peserta_didik_id' => $siswaUuid,
                'mata_pelajaran_id' => '401000000',
                'nilai' => '94.00',
            ]]],
            40 => ['nm_tabel' => 'tabel deskripsi', 'tabel' => 'public.tabel_deskripsi', 'data' => [[
                'deskripsi_id' => '4066ed2f-f5c8-4392-b968-8411c1bf05b7',
                'peserta_didik_id' => $siswaUuid,
                'mata_pelajaran_id' => '401000000',
                'semester_id' => '20252',
                'deskripsi_peng_m' => 'Mencapai kompetensi sangat baik dalam operasi bilangan.',
            ]]],
        ];

        $innerTmp = tempnam(sys_get_temp_dir(), 't_in_');
        $outerTmp = tempnam(sys_get_temp_dir(), 't_out_').'.xsp';

        $zIn = new ZipArchive;
        $zIn->open($innerTmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $idx => $spec) {
            $env = [[
                'id_sekolah' => $idSekolah,
                'nm_tabel' => $spec['nm_tabel'],
                'semester' => '20252',
                'tahun' => '2025',
                'tabel' => $spec['tabel'],
                'jumlah' => count($spec['data']),
                'data' => $spec['data'],
            ]];
            $zIn->addFromString("bcke_{$idx}.enc", $svc->encryptEntry((string) json_encode($env), $key));
        }
        $zIn->close();

        $zOut = new ZipArchive;
        $zOut->open($outerTmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zOut->addFile($innerTmp, 'file_hasilbackuprsp.xsp');
        $zOut->close();

        $uploaded = new UploadedFile($outerTmp, '4031391220252.xsp', 'application/octet-stream', null, true);

        $res = $this->actingAs($admin)->post(route('admin.backup.import'), [
            'backup_file' => $uploaded,
        ]);

        $res->assertRedirect(route('admin.backup.index'));
        if (session('error')) {
            $this->fail('Restore .xsp gagal: '.session('error'));
        }
        $res->assertSessionHas('success');

        $this->assertSame(1, Semester::where('semester_id', '20252')->count());
        $this->assertSame('UPT SPF SD NEGERI UNGGULAN MONGISIDI 1', SekolahSettings::first()?->nama_sekolah);
        $this->assertSame(1, Ptk::where('nama', 'HAIRUDDIN')->count());
        $this->assertSame(1, Rombel::where('nama_rombel', '4-B')->count());
        $this->assertSame(1, MataPelajaran::where('nama', 'Matematika')->count());
        $this->assertSame(1, Siswa::where('nisn', '3134143823')->count());
        $this->assertSame(2, Siswa::count());

        $nilai = NilaiErapot::first();
        $this->assertNotNull($nilai);
        $this->assertEquals(92.0, (float) $nilai->nilai_akhir);
        $this->assertSame('A', $nilai->predikat);
        $this->assertSame('Mencapai kompetensi sangat baik dalam operasi bilangan.', $nilai->deskripsi_capaian);
        $this->assertNotNull($nilai->jadwal_pelajaran_id);

        $this->assertSame(1, TranskripNilai::where('nama_mapel', 'Matematika')->count());

        // Uji juga tombol Backup & Unduh Format e-Rapor (.xsp)
        $exportRes = $this->actingAs($admin)->post(route('admin.backup.export-erapor-xsp'));
        $exportRes->assertOk();

        @unlink($innerTmp);
        @unlink($outerTmp);
    }
}
