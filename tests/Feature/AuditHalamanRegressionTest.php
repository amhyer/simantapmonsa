<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\HasilKuis;
use App\Models\Kebiasaan;
use App\Models\Kehadiran;
use App\Models\Kuis;
use App\Models\PengaturanGuru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi untuk temuan audit halaman/tombol: semua kasus di bawah sebelumnya
 * menghasilkan HTTP 500 saat halaman dibuka atau tombol diklik.
 */
class AuditHalamanRegressionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Siswa} */
    private function guruDanSiswa(): array
    {
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::forceCreate([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'AUD'.Str::random(8),
            'nama_peserta_didik' => 'Siswa Audit',
            'kelas' => '7A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        return [$guru, $siswa];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_halaman_kebiasaan_guru_rata_rata_memakai_nilai_angka(): void
    {
        [$guru, $siswa] = $this->guruDanSiswa();

        Kebiasaan::forceCreate([
            'uuid' => (string) Str::uuid(),
            'siswa_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'bangun_pagi' => 4,
            'beribadah' => 3,
            'diisi_oleh' => 'Orang Tua',
        ]);

        // Sebelumnya $semuaSkor berisi NAMA kolom (string) lalu di-average,
        // dan $kebiasaanByDate di-toArray() sehingga view membaca properti array.
        $this->actingAs($guru)
            ->get(route('guru.kebiasaan.index'))
            ->assertOk()
            ->assertSee('Rata-rata Nilai');
    }

    public function test_dashboard_kepsek_aman_saat_kehadiran_dikelompokkan(): void
    {
        [$guru, $siswa] = $this->guruDanSiswa();

        Kehadiran::forceCreate([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'siswa_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'status' => 'H',
        ]);

        // Sebelumnya filter() memanggil ->siswa_id pada collection hasil groupBy.
        $this->actingAs(User::factory()->kepsek()->create())
            ->get(route('kepsek.dashboard'))
            ->assertOk();
    }

    public function test_hapus_peta_kelas_tidak_memanggil_relasi_yang_tidak_ada(): void
    {
        // Guru tanpa siswa: tombol Hapus harus benar-benar menghapus.
        $guruKosong = User::factory()->guru()->create();
        $pengaturan = PengaturanGuru::forceCreate([
            'guru_id' => $guruKosong->id,
            'nama_guru' => $guruKosong->nama_lengkap,
            'kelas' => '7A',
            'kkm' => 70,
            'pembaruan' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.peta-kelas.destroy', $pengaturan->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('pengaturan_guru', ['id' => $pengaturan->id]);

        // Guru dengan siswa: tombol Hapus ditolak dengan pesan, bukan 500.
        [$guru] = $this->guruDanSiswa();
        $terkait = PengaturanGuru::forceCreate([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'kelas' => '7A',
            'kkm' => 70,
            'pembaruan' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.peta-kelas.destroy', $terkait->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('pengaturan_guru', ['id' => $terkait->id]);
    }

    public function test_update_nomor_ijazah_tanpa_field_opsional_tidak_error(): void
    {
        [, $siswa] = $this->guruDanSiswa();

        // Field nomor_ijazah `nullable`: tidak ikut di $validated saat kosong.
        $this->actingAs($this->admin())
            ->put(route('admin.transkrip.nomor.update', $siswa), [])
            ->assertRedirect();

        $this->assertNull($siswa->fresh()->nomor_ijazah);
    }

    public function test_simpan_logo_ttd_tanpa_identitas_sekolah_memberi_pesan_bukan_500(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.referensi.logo-ttd.store'), [])
            ->assertRedirect(route('admin.sekolah.index'))
            ->assertSessionHas('error');

        // Tidak boleh membuat baris sekolah_settings setengah jadi.
        $this->assertDatabaseCount('sekolah_settings', 0);
    }

    public function test_export_backup_aman_untuk_tabel_tanpa_model(): void
    {
        // Tabel `capaian_pembelajaran` memakai query builder (stdClass), bukan model.
        DB::table('capaian_pembelajaran')->insert([
            'mata_pelajaran' => 'Matematika',
            'fase' => 'D',
            'elemen' => 'Bilangan',
            'deskripsi' => 'Contoh',
            'kode_cp' => 'CP-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.backup.export'))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_halaman_nilai_siswa_aman_saat_ada_hasil_kuis(): void
    {
        [$guru, $siswa] = $this->guruDanSiswa();

        $kuis = Kuis::forceCreate([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'tanggal' => now()->toDateString(),
            'judul' => 'Kuis Audit',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '7A',
        ]);

        HasilKuis::forceCreate([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'kuis_id' => $kuis->id,
            'siswa_id' => $siswa->id,
            'nama_guru' => $guru->nama_lengkap,
            'judul_kuis' => $kuis->judul,
            'nama_siswa' => $siswa->nama_peserta_didik,
            'waktu' => now(),
            'skor' => 80,
            'sumber_soal' => 'lokal',
        ]);

        $akunSiswa = User::factory()->siswa()->create([
            'terhubung_dengan' => [$siswa->id],
        ]);

        // Sebelumnya Eloquent\Collection::merge() memanggil getKey() pada stdClass.
        $this->actingAs($akunSiswa)
            ->get(route('siswa.nilai.index'))
            ->assertOk();

        // Halaman kuis yang SUDAH dikerjakan juga sempat 500 karena $soal
        // hanya didefinisikan di cabang @else, sementara @push('scripts') tetap jalan.
        $this->actingAs($akunSiswa)
            ->get(route('siswa.kuis.show', $kuis->id))
            ->assertOk()
            ->assertSee('Sudah Mengerjakan')
            ->assertDontSee("getElementById('startTime')");
    }

    public function test_relasi_guru_bank_soal_cbt_tersedia(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new CbtQuestionBank())->guru());
    }

    public function test_tidak_ada_route_di_dalam_komentar_html(): void
    {
        // Blade tetap mengeksekusi {{ route(...) }} di dalam <!-- ... -->.
        // Komentar nonaktif harus memakai {{-- --}} agar tidak 500.
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $kode = preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file->getPathname()));
            preg_match_all('/<!--.*?-->/s', $kode ?? '', $komentar);
            foreach ($komentar[0] as $isi) {
                $this->assertStringNotContainsString(
                    'route(', $isi, "Komentar HTML memuat route(): {$file->getRelativePathname()}"
                );
            }
        }
    }

    public function test_halaman_utama_tidak_menggunakan_tautan_atau_kontak_palsu(): void
    {
        $response = $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Newsletter email belum tersedia')
            ->assertSee('Video demo belum tersedia')
            ->assertSee('Akses Aplikasi SIMANTAP')
            ->assertSee('Lihat repositori dan rilis')
            ->assertDontSee('Coba Demo')
            ->assertDontSee('videoTrigger')
            ->assertDontSee('videoModal')
            ->assertDontSee('dQw4w9WgXcQ')
            ->assertDontSee('6281234567890')
            ->assertDontSee('newsletterForm');

        $this->assertStringNotContainsString('href="#"', $response->getContent());
    }

    public function test_landing_page_anchor_memiliki_target_dan_tidak_ada_link_placeholder(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/landing.blade.php'));
        $this->assertStringNotContainsString('href="#"', $sumber);

        preg_match_all('/href="#([A-Za-z][A-Za-z0-9_-]*)"/', $sumber, $tautan);
        preg_match_all('/id="([A-Za-z][A-Za-z0-9_-]*)"/', $sumber, $ids);
        $idTersedia = array_fill_keys($ids[1], true);
        foreach ($tautan[1] as $id) {
            $this->assertArrayHasKey($id, $idTersedia, "Anchor #{$id} tidak punya target di landing page.");
        }
    }

    public function test_landing_page_tidak_menawarkan_pendaftaran_mandiri(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSee('Daftar Sekarang')
            ->assertDontSee('Daftar di sini')
            ->assertDontSee('Buat Akun')
            ->assertDontSee('Guru · Login')
            ->assertDontSee('Siswa · Login')
            ->assertDontSee('Orang Tua · Login')
            ->assertDontSee('Kepala Sekolah · Login')
            ->assertSee('Siapkan Akun');

        $this->get(route('register'))
            ->assertRedirect(route('login'));
    }

    public function test_method_spoofing_pada_form_sesuai_verb_route(): void
    {
        $daftar = [];
        foreach (Route::getRoutes() as $route) {
            if ($route->getName()) {
                $daftar[$route->getName()] = $route->methods();
            }
        }

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $kode = (string) file_get_contents($file->getPathname());
            preg_match_all('/<form\b[^>]*>/', $kode, $form, PREG_OFFSET_CAPTURE);

            preg_match_all('/@method\(\s*[\'"]([A-Za-z]+)[\'"]\s*\)/', $kode, $metode, PREG_OFFSET_CAPTURE);
            foreach ($metode[1] as $i => $match) {
                $verb = strtoupper($match[0]);
                $posisi = $metode[0][$i][1];
                $tag = '';
                foreach ($form[0] as $buka) {
                    if ($buka[1] < $posisi) {
                        $tag = $buka[0];
                    } else {
                        break;
                    }
                }
                if (! preg_match('/route\(\s*[\'"]([\w.\-]+)[\'"]/', $tag, $nama)) {
                    continue;
                }
                if (! isset($daftar[$nama[1]])) {
                    continue; // rute milik modul yang sedang nonaktif (mis. CBT)
                }
                $this->assertContains(
                    $verb,
                    $daftar[$nama[1]],
                    "@method('{$verb}') tidak cocok dengan rute {$nama[1]} di {$file->getRelativePathname()}"
                );
            }
        }
    }
}
