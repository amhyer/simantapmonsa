<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi hardening operasional (S1, S2, S3, S5, S7 pada
 * LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md).
 *
 * 1. S3 — respons membawa header keamanan; HSTS hanya lewat HTTPS.
 * 2. S5 — halaman 403/404/419/500 memakai tampilan SIMANTAP, bukan Laravel bawaan,
 *         dan tidak membocorkan jejak tumpukan.
 * 3. S1 — `simantap:backup` merotasi cadangan kedaluwarsa (sebelumnya menumpuk
 *         tanpa batas).
 * 4. S2 — cadangan dapat disalin ke disk offsite, dan kegagalan salinan tidak
 *         membatalkan cadangan utama.
 */
class HardeningOperasionalTest extends TestCase
{
    use RefreshDatabase;

    /* ──────────────────────────────────────────────────────────────
     * S3 — Header keamanan
     * ────────────────────────────────────────────────────────────── */

    public function test_respons_membawa_header_keamanan_dasar(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Presensi memakai geolokasi dan pemindaian QR memakai kamera,
        // jadi keduanya dibatasi ke same-origin — bukan dimatikan.
        $kebijakan = $response->headers->get('Permissions-Policy');
        $this->assertNotNull($kebijakan);
        $this->assertStringContainsString('geolocation=(self)', $kebijakan);
        $this->assertStringContainsString('camera=(self)', $kebijakan);
        $this->assertStringNotContainsString('geolocation=()', $kebijakan);
        $this->assertStringNotContainsString('camera=()', $kebijakan);
    }

    public function test_hsts_tidak_dikirim_lewat_http_biasa(): void
    {
        $this->assertNull(
            $this->get('/login')->headers->get('Strict-Transport-Security'),
            'HSTS lewat HTTP tidak berguna dan bisa menyesatkan.'
        );

        // Dipastikan juga di tingkat middleware, agar kondisi HTTPS diuji
        // secara deterministik tanpa bergantung pada plumbing server vars harness.
        $this->assertNull($this->lewatMiddleware(['HTTPS' => 'off'])->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_dikirim_lewat_https(): void
    {
        // Tidak memakai $this->withServerVariables(['HTTPS' => 'on']): pada
        // harness ini variabel itu tidak sampai ke $request->secure(), sehingga
        // assertion-nya gagal walau middleware-nya benar. Karena itu request-nya
        // dibangun langsung dengan server bag HTTPS.
        $response = $this->lewatMiddleware(['HTTPS' => 'on']);

        $this->assertSame(
            'max-age=' . config('security.hsts_max_age'),
            $response->headers->get('Strict-Transport-Security')
        );
    }

    /** Jalankan SecurityHeaders pada request dengan server bag tertentu. */
    private function lewatMiddleware(array $server): \Illuminate\Http\Response
    {
        $request = Request::create('/login', 'GET', [], [], [], $server);

        $this->assertTrue(
            $server['HTTPS'] === 'on' ? $request->secure() : ! $request->secure(),
            'Prekondisi test salah: request->secure() tidak sesuai server bag.'
        );

        return (new SecurityHeaders)->handle($request, fn () => new Response('ok'));
    }

    public function test_header_keamanan_bisa_dimatikan_lewat_konfigurasi(): void
    {
        config(['security.enabled' => false]);

        $this->assertNull($this->get('/login')->headers->get('X-Content-Type-Options'));
    }

    public function test_csp_tidak_dipasang_secara_bawaan(): void
    {
        // Disengaja: 22 tag CDN + banyak <script> inline akan patah.
        // Lihat config/security.php. Test ini mengunci keputusan itu agar
        // tidak ada yang memasang CSP tanpa memindahkan aset CDN lebih dulu.
        $this->assertSame('', (string) config('security.content_security_policy'));
        $this->assertNull($this->get('/login')->headers->get('Content-Security-Policy'));
    }

    /* ──────────────────────────────────────────────────────────────
     * S5 — Halaman error kustom
     * ────────────────────────────────────────────────────────────── */

    public function test_halaman_404_memakai_tampilan_simantap(): void
    {
        // APP_DEBUG=false = konfigurasi production, satu-satunya kondisi tempat
        // halaman error kustom memang dipakai.
        config(['app.debug' => false]);

        $this->get('/halaman-yang-tidak-pernah-ada')
            ->assertStatus(404)
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('SIMANTAP');
    }

    public function test_halaman_403_memakai_tampilan_simantap(): void
    {
        config(['app.debug' => false]);
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)
            ->get('/admin/dashboard')
            ->assertStatus(403)
            ->assertSee('Akses ditolak');
    }

    public function test_halaman_419_memakai_tampilan_simantap(): void
    {
        // Tidak bisa dipicu lewat POST tanpa token: VerifyCsrfToken melewati
        // pemeriksaannya saat app berjalan di lingkungan testing. Karena itu
        // dilempar langsung agar tetap melewati pemetaan TokenMismatchException
        // → HttpException(419) milik framework.
        config(['app.debug' => false]);

        Route::get('/__uji-419', function () {
            throw new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.');
        });

        $this->get('/__uji-419')
            ->assertStatus(419)
            ->assertSee('Sesi Anda berakhir');
    }

    public function test_halaman_500_tidak_membocorkan_jejak_tumpukan(): void
    {
        config(['app.debug' => false]);

        Route::get('/__uji-500', function () {
            throw new \RuntimeException('rahasia-internal-yang-tidak-boleh-bocor');
        });

        $response = $this->get('/__uji-500');

        $response->assertStatus(500);
        $response->assertSee('Terjadi kesalahan pada server');
        $response->assertDontSee('rahasia-internal-yang-tidak-boleh-bocor');
        $response->assertDontSee('RuntimeException');
    }

    public function test_seluruh_halaman_error_terkirim_dapat_dirender(): void
    {
        // Dirender lewat siklus HTTP sungguhan (bukan view()->render()) supaya
        // helper asset()/auth() punya konteks request seperti di production.
        config(['app.debug' => false]);

        foreach ([403, 404, 419, 429, 500, 503] as $kode) {
            $this->assertTrue(
                view()->exists("errors.{$kode}"),
                "resources/views/errors/{$kode}.blade.php tidak ada"
            );

            Route::get("/__uji-{$kode}", fn () => abort($kode));

            $this->get("/__uji-{$kode}")
                ->assertStatus($kode)
                ->assertSee('SIMANTAP')
                ->assertSee((string) $kode);
        }
    }

    /* ──────────────────────────────────────────────────────────────
     * S7 — Favicon
     * ────────────────────────────────────────────────────────────── */

    public function test_favicon_ada_dan_berisi_ikon_valid(): void
    {
        $path = public_path('favicon.ico');

        $this->assertFileExists($path);
        $this->assertGreaterThan(100, filesize($path), 'favicon.ico masih kosong (0 byte)?');

        $isi = (string) file_get_contents($path);

        // ICONDIR: reserved=0, type=1 (ikon), jumlah gambar >= 1.
        $dirent = unpack('vreserved/vtype/vcount', substr($isi, 0, 6));
        $this->assertSame(0, $dirent['reserved']);
        $this->assertSame(1, $dirent['type']);
        $this->assertGreaterThanOrEqual(1, $dirent['count']);

        $entri = unpack('Cwidth/Cheight', substr($isi, 6, 2));
        $this->assertSame(32, $entri['width']);
        $this->assertSame(32, $entri['height']);
    }

    /* ──────────────────────────────────────────────────────────────
     * S1 — Rotasi cadangan
     * ────────────────────────────────────────────────────────────── */

    private function siapkanDiskCadangan(string $utama = 'cadangan', ?string $salinan = null): void
    {
        Storage::fake($utama);

        config([
            'backup.disk' => $utama,
            'backup.direktori' => 'backups',
            'backup.retensi_hari' => 30,
            'backup.salinan_disk' => $salinan,
        ]);

        if ($salinan !== null) {
            Storage::fake($salinan);
        }
    }

    /** Tulis berkas cadangan lalu mundurkan mtime-nya agar tampak kedaluwarsa. */
    private function buatCadanganLama(string $disk, string $nama, int $umurHari): void
    {
        Storage::disk($disk)->put("backups/{$nama}", '{"lama":true}');
        touch(Storage::disk($disk)->path("backups/{$nama}"), now()->subDays($umurHari)->getTimestamp());
    }

    public function test_backup_menghapus_cadangan_yang_lebih_tua_dari_retensi(): void
    {
        $this->siapkanDiskCadangan();

        $this->buatCadanganLama('cadangan', 'backup-simantap-20200101-000000.json', 400);
        $this->buatCadanganLama('cadangan', 'backup-simantap-20200102-000000.json', 10);

        Artisan::call('simantap:backup');

        $sisa = collect(Storage::disk('cadangan')->files('backups'))
            ->map(fn ($f) => basename($f))
            ->all();

        $this->assertNotContains(
            'backup-simantap-20200101-000000.json',
            $sisa,
            'Cadangan berusia 400 hari seharusnya dihapus (retensi 30 hari).'
        );
        $this->assertContains('backup-simantap-20200102-000000.json', $sisa);
        $this->assertCount(2, $sisa, 'Harus tersisa 1 lama + 1 baru.');
    }

    public function test_rotasi_tidak_menyentuh_berkas_yang_bukan_cadangan(): void
    {
        $this->siapkanDiskCadangan();

        Storage::disk('cadangan')->put('backups/catatan-penting.json', '{}');
        touch(Storage::disk('cadangan')->path('backups/catatan-penting.json'), now()->subDays(900)->getTimestamp());

        Artisan::call('simantap:backup');

        $this->assertTrue(
            Storage::disk('cadangan')->exists('backups/catatan-penting.json'),
            'Rotasi hanya boleh menghapus berkas berprefiks backup-simantap-.'
        );
    }

    public function test_rotasi_bisa_dilewati_dengan_opsi(): void
    {
        $this->siapkanDiskCadangan();
        $this->buatCadanganLama('cadangan', 'backup-simantap-20200101-000000.json', 400);

        Artisan::call('simantap:backup', ['--tanpa-rotasi' => true]);

        $this->assertTrue(Storage::disk('cadangan')->exists('backups/backup-simantap-20200101-000000.json'));
    }

    public function test_rotasi_nonaktif_bila_retensi_nol(): void
    {
        $this->siapkanDiskCadangan();
        config(['backup.retensi_hari' => 0]);

        $this->buatCadanganLama('cadangan', 'backup-simantap-20200101-000000.json', 400);

        Artisan::call('simantap:backup');

        $this->assertTrue(Storage::disk('cadangan')->exists('backups/backup-simantap-20200101-000000.json'));
    }

    /* ──────────────────────────────────────────────────────────────
     * S2 — Salinan offsite
     * ────────────────────────────────────────────────────────────── */

    public function test_cadangan_juga_disalin_ke_disk_offsite(): void
    {
        $this->siapkanDiskCadangan('cadangan', 'offsite');

        Artisan::call('simantap:backup');

        $utama = collect(Storage::disk('cadangan')->files('backups'))->map(fn ($f) => basename($f))->all();
        $offsite = collect(Storage::disk('offsite')->files('backups'))->map(fn ($f) => basename($f))->all();

        $this->assertCount(1, $utama);
        $this->assertSame($utama, $offsite, 'Nama berkas salinan harus sama dengan cadangan utama.');
    }

    public function test_rotasi_juga_berlaku_pada_disk_offsite(): void
    {
        $this->siapkanDiskCadangan('cadangan', 'offsite');

        $this->buatCadanganLama('offsite', 'backup-simantap-20200101-000000.json', 400);

        Artisan::call('simantap:backup');

        $this->assertFalse(Storage::disk('offsite')->exists('backups/backup-simantap-20200101-000000.json'));
    }

    public function test_tanpa_konfigurasi_offsite_tidak_ada_salinan(): void
    {
        $this->siapkanDiskCadangan('cadangan', null);

        Artisan::call('simantap:backup');

        $this->assertCount(1, Storage::disk('cadangan')->files('backups'));
        $this->assertSame('', (string) config('backup.salinan_disk'));
    }
}
