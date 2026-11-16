<?php

namespace Tests\Feature;

use App\Models\Elearning\KelasDigital;
use App\Models\Elearning\KelasDigitalMateriItem;
use App\Models\Elearning\KelasDigitalModul;
use App\Models\Elearning\KelasDigitalPengumpulan;
use App\Models\Elearning\KelasDigitalPertemuan;
use App\Models\Elearning\KelasDigitalTugas;
use App\Models\JadwalPelajaran;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KelasDigitalTest extends TestCase
{
    use RefreshDatabase;

    private function createPdfUpload(string $name = 'materi.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n"
        );
    }

    public function test_alur_lengkap_kelas_digital_guru_siswa_penilaian_dan_sinkron_nilai(): void
    {
        Storage::fake('local');

        $guruWali = User::factory()->guru()->create();
        $guruMapel = User::factory()->guru()->create(['nama_lengkap' => 'Bu Rina Matematika']);
        $rombel6A = Rombel::create(['nama_rombel' => 'Kelas 6A', 'tingkat' => 6, 'guru_id' => $guruWali->id]);

        $userSiswa = User::factory()->siswa()->create(['nisn' => '9988776655']);
        $siswa = Siswa::create([
            'user_id' => $userSiswa->id,
            'guru_id' => $guruWali->id,
            'rombel_id' => $rombel6A->id,
            'nama_peserta_didik' => 'Siti Aisyah',
            'nisn' => '9988776655',
            'kelas' => 'Kelas 6A',
            'aktif' => true,
        ]);

        JadwalPelajaran::create([
            'rombel_id' => $rombel6A->id,
            'guru_id' => $guruMapel->id,
            'kelas' => 'Kelas 6A',
            'mata_pelajaran' => 'Matematika',
            'hari' => 'Selasa',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:30',
        ]);

        $materiEksisting = Materi::create([
            'guru_id' => $guruMapel->id,
            'judul' => 'Konsep Pecahan',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 6A',
            'tanggal' => now()->toDateString(),
        ]);

        $kuisEksisting = Kuis::create([
            'guru_id' => $guruMapel->id,
            'judul' => 'Kuis Cepat Pecahan',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 6A',
            'tanggal' => now()->toDateString(),
            'aktif' => true,
        ]);

        // 1. Guru membuat Kelas Digital.
        $this->actingAs($guruMapel)->post(route('guru.kelas-digital.store'), [
            'nama_kelas' => 'Matematika Kelas 6A',
            'mata_pelajaran' => 'Matematika',
            'rombel_id' => $rombel6A->id,
            'deskripsi' => 'Kelas belajar pecahan dan bangun ruang',
            'aktif' => '1',
        ])->assertRedirect();

        $kelas = KelasDigital::firstOrFail();
        $this->assertSame('Kelas 6A', $kelas->kelas);

        // 2. Guru menambah Modul dengan metadata Pembelajaran Mendalam.
        $this->actingAs($guruMapel)->post(route('guru.kelas-digital.modul.store', $kelas), [
            'judul' => 'Bab 1: Pecahan',
            'tujuan_pembelajaran' => 'Siswa mampu membandingkan pecahan senilai',
            'pm' => ['berkesadaran', 'bermakna'],
            'dimensi' => [2, 4],
            'pengalaman_memahami' => 'Eksplorasi kertas lipat',
            'pengalaman_mengaplikasi' => 'Menghitung resep makanan',
            'pengalaman_merefleksi' => 'Jurnal belajar singkat',
            'dipublikasikan' => '1',
        ])->assertRedirect(route('guru.kelas-digital.show', $kelas));

        $modul = KelasDigitalModul::firstOrFail();
        $this->assertTrue($modul->pm['berkesadaran'] ?? false);
        $this->assertSame([2, 4], $modul->dimensi);

        // 3. Guru menambah Pertemuan yang menautkan Materi & Kuis eksisting.
        $this->actingAs($guruMapel)->post(route('guru.kelas-digital.pertemuan.store', [$kelas, $modul]), [
            'judul' => 'Pertemuan 1: Pengantar Pecahan',
            'tanggal' => now()->toDateString(),
            'materi_id' => $materiEksisting->id,
            'kuis_id' => $kuisEksisting->id,
            'dipublikasikan' => '1',
        ])->assertRedirect(route('guru.kelas-digital.show', $kelas));

        $pertemuan = KelasDigitalPertemuan::firstOrFail();

        // 4. Guru menambah Konten Video & File PDF privat.
        $this->actingAs($guruMapel)->post(route('guru.kelas-digital.item.store', [$kelas, $pertemuan]), [
            'tipe' => 'video',
            'judul' => 'Video Pecahan',
            'tautan' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertRedirect(route('guru.kelas-digital.show', $kelas));

        $this->actingAs($guruMapel)->post(route('guru.kelas-digital.item.store', [$kelas, $pertemuan]), [
            'tipe' => 'file',
            'judul' => 'Lembar Kerja PDF',
            'file' => $this->createPdfUpload('lkpd-pecahan.pdf'),
        ])->assertRedirect(route('guru.kelas-digital.show', $kelas));

        $videoItem = KelasDigitalMateriItem::where('tipe', 'video')->firstOrFail();
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $videoItem->youtubeEmbedUrl());

        $fileItem = KelasDigitalMateriItem::where('tipe', 'file')->firstOrFail();
        Storage::disk('local')->assertExists($fileItem->file_path);

        // 5. Guru menambah Tugas dengan sinkronisasi ke Daftar Nilai.
        $this->actingAs($guruMapel)->post(route('guru.kelas-digital.tugas.store', [$kelas, $pertemuan]), [
            'judul' => 'Latihan Soal Pecahan',
            'instruksi' => 'Kerjakan soal nomor 1 sampai 5.',
            'tipe_pengumpulan' => 'keduanya',
            'nilai_maksimal' => 100,
            'sinkron_ke_nilai' => '1',
        ])->assertRedirect(route('guru.kelas-digital.show', $kelas));

        $tugas = KelasDigitalTugas::firstOrFail();

        // 6. Siswa membuka daftar kelas, detail kelas, pertemuan, mengunduh file, menandai selesai, dan mengumpulkan tugas.
        $this->actingAs($userSiswa)
            ->get(route('siswa.kelas-digital.index'))
            ->assertOk()
            ->assertSee('Matematika Kelas 6A');

        $this->actingAs($userSiswa)
            ->get(route('siswa.kelas-digital.show', $kelas))
            ->assertOk()
            ->assertSee('Bab 1: Pecahan');

        $this->actingAs($userSiswa)
            ->get(route('siswa.kelas-digital.pertemuan', [$kelas, $pertemuan]))
            ->assertOk()
            ->assertSee('Konsep Pecahan')
            ->assertSee('Kuis Cepat Pecahan')
            ->assertSee('Latihan Soal Pecahan');

        $this->actingAs($userSiswa)
            ->get(route('siswa.kelas-digital.item.download', [$kelas, $fileItem]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($userSiswa)
            ->post(route('siswa.kelas-digital.pertemuan.selesai', [$kelas, $pertemuan]), ['selesai' => '1'])
            ->assertRedirect(route('siswa.kelas-digital.pertemuan', [$kelas, $pertemuan]));

        $this->assertDatabaseHas('kelas_digital_progress', [
            'pertemuan_id' => $pertemuan->id,
            'siswa_id' => $siswa->id,
            'selesai' => true,
        ]);

        $this->actingAs($userSiswa)
            ->post(route('siswa.kelas-digital.tugas.kumpul', [$kelas, $tugas]), [
                'jawaban_teks' => '1/2 senilai dengan 2/4',
                'file' => $this->createPdfUpload('jawaban-siti.pdf'),
            ])
            ->assertRedirect(route('siswa.kelas-digital.pertemuan', [$kelas, $pertemuan]));

        $pengumpulan = KelasDigitalPengumpulan::firstOrFail();
        $this->assertSame('dikumpulkan', $pengumpulan->status);
        Storage::disk('local')->assertExists($pengumpulan->file_path);

        // 7. Guru menilai tugas -> otomatis sinkron ke tabel `nilai` (jenis = 'Tugas').
        $this->actingAs($guruMapel)
            ->get(route('guru.kelas-digital.tugas.pengumpulan', [$kelas, $tugas]))
            ->assertOk()
            ->assertSee('Siti Aisyah');

        $this->actingAs($guruMapel)
            ->post(route('guru.kelas-digital.tugas.nilai', [$kelas, $tugas, $pengumpulan]), [
                'nilai' => 92,
                'catatan_guru' => 'Langkah penyelesaian sangat runtut.',
                'status' => 'dinilai',
            ])
            ->assertRedirect(route('guru.kelas-digital.tugas.pengumpulan', [$kelas, $tugas]));

        $pengumpulan->refresh();
        $this->assertSame('dinilai', $pengumpulan->status);
        $this->assertNotNull($pengumpulan->nilai_id);

        $nilaiRecord = Nilai::findOrFail($pengumpulan->nilai_id);
        $this->assertSame('Tugas', $nilaiRecord->jenis);
        $this->assertSame('Matematika', $nilaiRecord->mata_pelajaran);
        $this->assertEquals(92.0, (float) $nilaiRecord->nilai);

        // Menilai ulang harus memperbarui baris Nilai yang sama (tanpa duplikasi).
        $this->actingAs($guruMapel)
            ->post(route('guru.kelas-digital.tugas.nilai', [$kelas, $tugas, $pengumpulan]), [
                'nilai' => 95,
                'catatan_guru' => 'Diperbarui setelah koreksi bonus.',
                'status' => 'dinilai',
            ])
            ->assertRedirect();

        $this->assertSame(1, Nilai::where('siswa_id', $siswa->id)->where('jenis', 'Tugas')->count());
        $this->assertEquals(95.0, (float) $nilaiRecord->fresh()->nilai);
    }

    public function test_keamanan_otorisasi_anti_idor_dan_blokir_ekstensi_berbahaya(): void
    {
        Storage::fake('local');

        $guruA = User::factory()->guru()->create();
        $guruB = User::factory()->guru()->create();
        $rombel6A = Rombel::create(['nama_rombel' => 'Kelas 6A', 'tingkat' => 6, 'guru_id' => $guruA->id]);
        $rombel6B = Rombel::create(['nama_rombel' => 'Kelas 6B', 'tingkat' => 6, 'guru_id' => $guruB->id]);

        $userSiswaA1 = User::factory()->siswa()->create(['nisn' => '1111000011']);
        $siswaA1 = Siswa::create([
            'user_id' => $userSiswaA1->id,
            'guru_id' => $guruA->id,
            'rombel_id' => $rombel6A->id,
            'nama_peserta_didik' => 'Siswa A1',
            'nisn' => '1111000011',
            'kelas' => 'Kelas 6A',
            'aktif' => true,
        ]);

        $userSiswaA2 = User::factory()->siswa()->create(['nisn' => '1111000022']);
        Siswa::create([
            'user_id' => $userSiswaA2->id,
            'guru_id' => $guruA->id,
            'rombel_id' => $rombel6A->id,
            'nama_peserta_didik' => 'Siswa A2',
            'nisn' => '1111000022',
            'kelas' => 'Kelas 6A',
            'aktif' => true,
        ]);

        $userSiswaB1 = User::factory()->siswa()->create(['nisn' => '2222000011']);
        Siswa::create([
            'user_id' => $userSiswaB1->id,
            'guru_id' => $guruB->id,
            'rombel_id' => $rombel6B->id,
            'nama_peserta_didik' => 'Siswa B1',
            'nisn' => '2222000011',
            'kelas' => 'Kelas 6B',
            'aktif' => true,
        ]);

        $kelasA = KelasDigital::create([
            'guru_id' => $guruA->id,
            'rombel_id' => $rombel6A->id,
            'nama_kelas' => 'IPAS 6A',
            'mata_pelajaran' => 'IPAS',
            'kelas' => 'Kelas 6A',
            'aktif' => true,
        ]);

        $modulA = KelasDigitalModul::create([
            'kelas_digital_id' => $kelasA->id,
            'judul' => 'Modul 1',
            'dipublikasikan' => true,
        ]);

        $pertemuanA = KelasDigitalPertemuan::create([
            'modul_id' => $modulA->id,
            'judul' => 'Pertemuan 1',
            'dipublikasikan' => true,
        ]);

        $tugasA = KelasDigitalTugas::create([
            'pertemuan_id' => $pertemuanA->id,
            'kelas_digital_id' => $kelasA->id,
            'guru_id' => $guruA->id,
            'judul' => 'Tugas IPAS',
            'instruksi' => 'Unggah laporan pengamatan.',
            'tipe_pengumpulan' => 'file',
            'nilai_maksimal' => 100,
        ]);

        // 1. Guru B tidak boleh membuka/menghapus kelas Guru A.
        $this->actingAs($guruB)->get(route('guru.kelas-digital.show', $kelasA))->assertForbidden();
        $this->actingAs($guruB)->delete(route('guru.kelas-digital.destroy', $kelasA))->assertForbidden();

        // 2. Siswa B1 (Kelas 6B) tidak boleh membuka kelas 6A.
        $this->actingAs($userSiswaB1)->get(route('siswa.kelas-digital.show', $kelasA))->assertForbidden();
        $this->actingAs($userSiswaB1)->get(route('siswa.kelas-digital.pertemuan', [$kelasA, $pertemuanA]))->assertForbidden();

        // 3. Unggahan berkas berbahaya (.php) ditolak.
        $malicious = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "hack"; ?>');
        $this->actingAs($userSiswaA1)
            ->post(route('siswa.kelas-digital.tugas.kumpul', [$kelasA, $tugasA]), ['file' => $malicious])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('kelas_digital_pengumpulan', 0);

        // 4. Siswa A1 mengunggah PDF valid; Siswa A2 (teman sekelas) TIDAK boleh mengunduh jawaban A1 (anti-IDOR).
        $this->actingAs($userSiswaA1)
            ->post(route('siswa.kelas-digital.tugas.kumpul', [$kelasA, $tugasA]), ['file' => $this->createPdfUpload('rahasia-a1.pdf')])
            ->assertRedirect();

        $subA1 = KelasDigitalPengumpulan::where('siswa_id', $siswaA1->id)->firstOrFail();

        $this->actingAs($userSiswaA1)
            ->get(route('siswa.kelas-digital.pengumpulan.download', [$kelasA, $subA1]))
            ->assertOk();

        $this->actingAs($userSiswaA2)
            ->get(route('siswa.kelas-digital.pengumpulan.download', [$kelasA, $subA1]))
            ->assertForbidden();

        // 5. Parameter non-UUID mengembalikan 404 bersih (aman di PostgreSQL).
        $this->actingAs($guruA)->get('/guru/kelas-digital/bukan-uuid-valid')->assertNotFound();
        $this->actingAs($userSiswaA1)->get('/siswa/kelas-digital/12345')->assertNotFound();
    }
}
