<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Presensi\IdentityCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiswaFotoPrivateUploadTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    public function test_all_student_photo_pages_and_layout_use_private_media_route_without_storage_url(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Cache::flush();

        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true, 'force_password_change' => false]);
        $guru = User::factory()->create(['peran' => 'guru', 'aktif' => true, 'force_password_change' => false]);

        $jadwal = JadwalPelajaran::create([
            'guru_id' => $guru->id,
            'kelas' => '4A',
            'mata_pelajaran' => 'Matematika',
            'hari' => now()->locale('id')->translatedFormat('l'),
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:00',
        ]);

        $siswa = Siswa::create([
            'guru_id' => $guru->id,
            'nis' => '20268801',
            'nama_peserta_didik' => 'Siswa Route Foto',
            'jenis_kelamin' => 'L',
            'kelas' => '4A',
            'foto' => 'foto-siswa/priv12345678.png',
            'aktif' => true,
        ]);
        Storage::disk('local')->put($siswa->foto, $this->tinyPngBytes());

        $akunSiswa = User::factory()->create([
            'peran' => 'siswa',
            'nama_pengguna' => '20268801',
            'terhubung_dengan' => [$siswa->id],
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $expectedUrl = route('media.siswa.foto', $siswa);

        // 1. Admin referensi foto siswa
        $this->actingAs($admin)->get(route('admin.referensi.foto-siswa'))
            ->assertOk()
            ->assertSee($expectedUrl, false)
            ->assertDontSee('storage/foto-siswa');

        // 2. Siswa dashboard, profil show, profil edit (sekaligus avatar layout)
        foreach ([route('siswa.dashboard'), route('siswa.profil.show'), route('siswa.profil.edit')] as $url) {
            $this->actingAs($akunSiswa)->get($url)
                ->assertOk()
                ->assertSee($expectedUrl, false)
                ->assertDontSee('storage/foto-siswa');
        }
        $this->assertSame($expectedUrl, Cache::get('layout-foto-v2-'.$akunSiswa->id));

        // 3. Guru kehadiran jadwal
        $this->actingAs($guru)->get(route('guru.kehadiran.jadwal', $jadwal))
            ->assertOk()
            ->assertSee($expectedUrl, false)
            ->assertDontSee('storage/foto-siswa');

        // 4. IdentityCardService & cetak kartu siswa
        $card = app(IdentityCardService::class)->buildSiswaCardViewModel($siswa, null, false);
        $this->assertSame($expectedUrl, $card['foto_url']);

        $this->actingAs($admin)->get(route('admin.presensi.kartu.siswa.cetak', $siswa))
            ->assertOk()
            ->assertSee($expectedUrl, false)
            ->assertDontSee('storage/foto-siswa');
    }

    public function test_uploads_store_to_private_disk_only_and_deletes_remove_both_disks(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true, 'force_password_change' => false]);
        $siswa = Siswa::create([
            'nis' => '20268802',
            'nama_peserta_didik' => 'Siswa Unggah Private',
            'jenis_kelamin' => 'P',
            'kelas' => '4B',
            'foto' => 'foto-siswa/legacyold123.png',
            'aktif' => true,
        ]);
        Storage::disk('public')->put($siswa->foto, $this->tinyPngBytes());
        Storage::disk('local')->put($siswa->foto, $this->tinyPngBytes());

        $upload = UploadedFile::fake()->createWithContent('foto.png', $this->tinyPngBytes());

        // Unggah satuan admin -> simpan di local, hapus lama di KEDUA disk, tidak menulis ke public
        $this->actingAs($admin)
            ->post(route('admin.referensi.foto-siswa.satuan', $siswa), ['foto' => $upload])
            ->assertRedirect();

        $siswa->refresh();
        $newPath = $siswa->foto;
        $this->assertNotNull($newPath);
        $this->assertNotSame('foto-siswa/legacyold123.png', $newPath);
        Storage::disk('local')->assertExists($newPath);
        Storage::disk('public')->assertMissing($newPath);
        Storage::disk('local')->assertMissing('foto-siswa/legacyold123.png');
        Storage::disk('public')->assertMissing('foto-siswa/legacyold123.png');

        // Simulasikan salinan lama juga ada di public sebelum hapus
        Storage::disk('public')->put($newPath, $this->tinyPngBytes());
        $this->actingAs($admin)
            ->delete(route('admin.referensi.foto-siswa.destroy', $siswa))
            ->assertRedirect();

        $this->assertNull($siswa->fresh()->foto);
        Storage::disk('local')->assertMissing($newPath);
        Storage::disk('public')->assertMissing($newPath);

        // Unggah lewat profil siswa -> simpan di local ONLY
        $akunSiswa = User::factory()->create([
            'peran' => 'siswa',
            'nama_pengguna' => '20268802',
            'terhubung_dengan' => [$siswa->id],
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $upload2 = UploadedFile::fake()->createWithContent('profil.png', $this->tinyPngBytes());
        $this->actingAs($akunSiswa)
            ->put(route('siswa.profil.update'), ['foto' => $upload2])
            ->assertRedirect(route('siswa.profil.show'));

        $profilPath = $siswa->fresh()->foto;
        $this->assertNotNull($profilPath);
        Storage::disk('local')->assertExists($profilPath);
        Storage::disk('public')->assertMissing($profilPath);
    }

    public function test_zip_upload_rejects_fake_images_and_svg_and_accepts_valid_png_with_mime_extension(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('Ekstensi ZipArchive tidak tersedia.');
        }

        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true, 'force_password_change' => false]);
        $siswaFake = Siswa::create([
            'nis' => '20267701',
            'nama_peserta_didik' => 'Siswa Teks Palsu',
            'jenis_kelamin' => 'L',
            'kelas' => '5A',
            'aktif' => true,
        ]);
        $siswaSvg = Siswa::create([
            'nis' => '20267702',
            'nama_peserta_didik' => 'Siswa SVG',
            'jenis_kelamin' => 'P',
            'kelas' => '5A',
            'aktif' => true,
        ]);
        $siswaValid = Siswa::create([
            'nis' => '20267703',
            'nama_peserta_didik' => 'Siswa PNG Asli',
            'jenis_kelamin' => 'L',
            'kelas' => '5A',
            'aktif' => true,
        ]);

        $tmpZip = tempnam(sys_get_temp_dir(), 'foto_zip_test_').'.zip';
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('20267701.jpg', 'ini bukan gambar, cuma teks biasa');
        $zip->addFromString('20267702.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $zip->addFromString('20267703.jpg', $this->tinyPngBytes()); // PNG asli dinamai .jpg -> harus disimpan .png
        $zip->close();

        try {
            $uploadedZip = new UploadedFile($tmpZip, 'foto.zip', 'application/zip', null, true);
            $this->actingAs($admin)
                ->post(route('admin.referensi.foto-siswa.store'), ['file_zip' => $uploadedZip])
                ->assertRedirect(route('admin.referensi.foto-siswa'));
        } finally {
            @unlink($tmpZip);
        }

        $this->assertNull($siswaFake->fresh()->foto);
        $this->assertNull($siswaSvg->fresh()->foto);

        $validPath = $siswaValid->fresh()->foto;
        $this->assertNotNull($validPath);
        $this->assertStringEndsWith('.png', $validPath);
        Storage::disk('local')->assertExists($validPath);
        Storage::disk('public')->assertMissing($validPath);
    }

    public function test_benchmark_cetak_siswa_massal_100_students_and_100_image_requests(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true, 'force_password_change' => false]);
        $png = $this->tinyPngBytes();
        $siswas = [];

        for ($i = 1; $i <= 100; $i++) {
            $path = 'foto-siswa/bench'.str_pad((string) $i, 4, '0', STR_PAD_LEFT).'.png';
            Storage::disk('local')->put($path, $png);
            $siswas[] = Siswa::create([
                'nis' => '8800'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nama_peserta_didik' => "Siswa Bench {$i}",
                'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                'kelas' => '6A',
                'foto' => $path,
                'aktif' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $tCetak = microtime(true);
        $cetakRes = $this->actingAs($admin)
            ->get(route('admin.presensi.kartu.siswa.cetak-massal', ['kelas' => '6A']))
            ->assertOk();
        $cetakMs = round((microtime(true) - $tCetak) * 1000, 1);
        $cetakQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $cetakRes->assertDontSee('storage/foto-siswa');

        $tFetch = microtime(true);
        foreach ($siswas as $s) {
            $this->actingAs($admin)
                ->get(route('media.siswa.foto', $s))
                ->assertOk();
        }
        $fetch100Ms = round((microtime(true) - $tFetch) * 1000, 1);

        fwrite(STDOUT, "CETAK_MASSAL_100_BENCHMARK: cetak_queries={$cetakQueries}, cetak_ms={$cetakMs}, fetch_100_fotos_ms={$fetch100Ms}\n");
        $this->assertCount(100, $siswas);
    }
}
