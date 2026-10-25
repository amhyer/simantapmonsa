<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\SeedGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi temuan kesiapan launching (LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md).
 *
 * Celah yang dikunci di sini:
 *
 * 1. B4 — `Admin/UserController::update()` mengganti sandi tanpa ikut menandai
 *    `force_password_change`, sehingga sandi sementara buatan admin berlaku
 *    selamanya. Sekarang pemilik akun dipaksa menggantinya saat login berikutnya.
 * 2. B4 — `Admin/UserController::store()` membuat akun tanpa penanda yang sama.
 * 3. B3 — `DatabaseSeeder` menulis akun `admin/admin123` dkk. yang sandinya
 *    tercantum di repositori. Seeder kini menolak berjalan di production dan
 *    memaksa ganti sandi di luar lingkungan local.
 * 4. B5 — Dua grup route CBT tidak memasang `force.password.change`, berbeda
 *    dari 7 grup route lain.
 */
class KesiapanLaunchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'kata_sandi' => bcrypt('rahasia-admin'),
            'aktif' => true,
            'force_password_change' => false,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     * B4 — admin mereset sandi pengguna lain
     * ────────────────────────────────────────────────────────────── */

    public function test_admin_mereset_sandi_pengguna_lain_memicu_ganti_sandi_wajib(): void
    {
        $admin = $this->admin();
        $guru = User::factory()->guru()->create([
            'kata_sandi' => bcrypt('sandi-lama-123'),
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $guru), [
                'nama_lengkap' => $guru->nama_lengkap,
                'nama_pengguna' => $guru->nama_pengguna,
                'peran' => 'guru',
                'kata_sandi' => 'sandi-sementara-1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $guru->refresh();

        $this->assertTrue(
            (bool) $guru->force_password_change,
            'Sandi yang direset admin harus memaksa pengguna menggantinya saat login.'
        );
        $this->assertTrue(
            Hash::check('sandi-sementara-1', $guru->kata_sandi),
            'Sandi baru harus benar-benar tersimpan (dan tidak ter-hash dua kali).'
        );
    }

    public function test_admin_mengubah_data_tanpa_sandi_tidak_memaksa_ganti_sandi(): void
    {
        $admin = $this->admin();
        $guru = User::factory()->guru()->create([
            'kata_sandi' => bcrypt('sandi-lama-123'),
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $guru), [
                'nama_lengkap' => 'Nama Sudah Diganti',
                'nama_pengguna' => $guru->nama_pengguna,
                'peran' => 'guru',
            ])
            ->assertRedirect(route('admin.users.index'));

        $guru->refresh();

        $this->assertSame('Nama Sudah Diganti', $guru->nama_lengkap);
        $this->assertFalse(
            (bool) $guru->force_password_change,
            'Mengubah data biasa tidak boleh memaksa pengguna mengganti sandi.'
        );
    }

    public function test_admin_mengganti_sandi_sendiri_tidak_dikunci_di_halaman_ganti_sandi(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'nama_lengkap' => $admin->nama_lengkap,
                'nama_pengguna' => $admin->nama_pengguna,
                'peran' => 'admin',
                'kata_sandi' => 'sandi-baru-admin-1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $admin->refresh();

        $this->assertFalse(
            (bool) $admin->force_password_change,
            'Admin yang mengganti sandinya sendiri sudah tahu sandi barunya.'
        );
    }

    public function test_akun_baru_buatan_admin_wajib_ganti_sandi(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'nama_lengkap' => 'Guru Baru',
                'nama_pengguna' => 'guru.baru',
                'kata_sandi' => 'sandi-sementara-1',
                'peran' => 'kepsek',
                'aktif' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        $baru = User::where('nama_pengguna', 'guru.baru')->firstOrFail();

        $this->assertTrue(
            (bool) $baru->force_password_change,
            'Akun yang sandinya diketik admin harus dipaksa menggantinya.'
        );
    }

    /* ──────────────────────────────────────────────────────────────
     * B3 — seeder & kredensial contoh
     * ────────────────────────────────────────────────────────────── */

    public function test_seeder_menolak_berjalan_di_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new DatabaseSeeder)->run();

        $this->assertSame(
            0,
            User::count(),
            'Seeder tidak boleh membuat akun contoh di production.'
        );
    }

    public function test_akun_hasil_seed_di_lingkungan_non_local_wajib_ganti_sandi(): void
    {
        // phpunit.xml menyetel APP_ENV=testing, jadi ini jalur non-local.
        $this->assertTrue(SeedGuard::paksaGantiSandi());

        (new DatabaseSeeder)->run();

        $this->assertGreaterThan(0, User::count(), 'Seeder harus berjalan di lingkungan non-production.');

        $tanpaPenanda = User::where('force_password_change', false)->pluck('nama_pengguna');

        $this->assertCount(
            0,
            $tanpaPenanda,
            'Semua akun contoh wajib mengganti sandi: ' . $tanpaPenanda->implode(', ')
        );
    }

    public function test_seed_guard_mengizinkan_lingkungan_pengembangan(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->assertTrue(SeedGuard::bolehJalan());
        $this->assertFalse(SeedGuard::paksaGantiSandi());
    }

    public function test_seed_guard_menolak_production_tanpa_izin_eksplisit(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertFalse(SeedGuard::bolehJalan());
    }

    /* ──────────────────────────────────────────────────────────────
     * B5 — middleware grup route CBT
     *
     * Route CBT hanya terdaftar saat SIMANTAP_CBT_ENABLED=true, jadi
     * pemeriksaannya membaca sumber routes/web.php.
     * ────────────────────────────────────────────────────────────── */

    public function test_seluruh_grup_route_cbt_memasang_force_password_change(): void
    {
        $sumber = (string) file_get_contents(base_path('routes/web.php'));

        preg_match_all(
            '/Route::middleware\(\[([^\]]*)\]\)->prefix\(\'cbt[^\']*\'\)/',
            $sumber,
            $cocok
        );

        $this->assertNotEmpty(
            $cocok[1],
            'Pola pencarian grup route CBT tidak cocok — perbarui test bila routes/web.php berubah.'
        );

        foreach ($cocok[1] as $middleware) {
            $this->assertStringContainsString(
                'force.password.change',
                $middleware,
                'Grup route CBT tanpa force.password.change: ' . $middleware
            );
        }
    }

    public function test_template_env_produksi_mematikan_debug_dan_mengamankan_cookie(): void
    {
        $template = (string) file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('APP_ENV=production', $template);
        $this->assertStringContainsString('APP_DEBUG=false', $template);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $template);
        $this->assertStringContainsString('TELESCOPE_ENABLED=false', $template);

        $this->assertDoesNotMatchRegularExpression(
            '/^APP_KEY=.+$/m',
            $template,
            'APP_KEY harus kosong dan diisi `php artisan key:generate`.'
        );
    }
}
