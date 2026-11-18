<?php

namespace Tests\Feature;

use App\Models\Ptk;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Dapodik\DapodikSyncService;
use App\Services\DapodikService;
use App\Support\InitialPassword;
use Database\Seeders\E2eFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InitialPasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(E2eFixtureSeeder::class);
    }

    public function test_initial_password_helper_generates_readable_strong_passwords(): void
    {
        $p1 = InitialPassword::generate();
        $p2 = InitialPassword::generate();

        $this->assertGreaterThanOrEqual(10, strlen($p1));
        $this->assertNotSame($p1, $p2);
        $this->assertDoesNotMatchRegularExpression('/[0Oo1lI]/', $p1);
    }

    public function test_dapodik_services_create_unique_passwords_and_force_password_change(): void
    {
        $service = app(DapodikService::class);
        $service->syncGTK([
            ['nama' => 'Guru Dapodik Satu', 'nip' => '199001012020011001'],
            ['nama' => 'Guru Dapodik Dua', 'nip' => '199001012020011002'],
        ]);

        $g1 = User::where('nama_pengguna', '199001012020011001')->firstOrFail();
        $g2 = User::where('nama_pengguna', '199001012020011002')->firstOrFail();

        $this->assertTrue((bool) $g1->force_password_change);
        $this->assertTrue((bool) $g2->force_password_change);
        $this->assertNotSame($g1->kata_sandi, $g2->kata_sandi);

        $syncService = app(DapodikSyncService::class);
        $s1Record = Siswa::create(['nama_peserta_didik' => 'Siswa Sync Satu', 'nisn' => '0099887701', 'nis' => '998801', 'jenis_kelamin' => 'L', 'kelas' => '4A', 'aktif' => true]);
        $s2Record = Siswa::create(['nama_peserta_didik' => 'Siswa Sync Dua', 'nisn' => '0099887702', 'nis' => '998802', 'jenis_kelamin' => 'P', 'kelas' => '4A', 'aktif' => true]);

        $method = new \ReflectionMethod($syncService, 'ensureStudentUser');
        $method->setAccessible(true);
        $method->invoke($syncService, $s1Record, $s1Record->nisn, true);
        $method->invoke($syncService, $s2Record, $s2Record->nisn, true);

        $s1 = User::where('nama_pengguna', '0099887701')->firstOrFail();
        $s2 = User::where('nama_pengguna', '0099887702')->firstOrFail();

        $this->assertTrue((bool) $s1->force_password_change);
        $this->assertTrue((bool) $s2->force_password_change);
        $this->assertNotSame($s1->kata_sandi, $s2->kata_sandi);
    }

    public function test_generate_semua_pengguna_provides_one_time_export_with_valid_hashes(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);
        $guru = User::findOrFail(E2eFixtureSeeder::USER_IDS['guru_a']);

        Ptk::create(['nama' => 'PTK Baru Satu', 'nip' => '198501012010011001', 'jenis_ptk' => 'Guru Kelas', 'aktif' => true]);
        Siswa::create(['nama_peserta_didik' => 'Siswa Baru Satu', 'nisn' => '0011223344', 'nis' => '112233', 'jenis_kelamin' => 'L', 'kelas' => '5A', 'aktif' => true]);

        $response = $this->actingAs($admin)->postJson(route('admin.users.generate-semua'));
        $response->assertOk()->assertJsonPath('success', true);

        $eksporUrl = $response->json('ekspor_url');
        $this->assertNotEmpty($eksporUrl);

        // Tamu dan non-admin ditolak
        auth()->logout();
        $this->get($eksporUrl)->assertRedirect(route('login'));
        $this->actingAs($guru)->get($eksporUrl)->assertForbidden();

        // Admin mengunduh sekali
        $download = $this->actingAs($admin)->get($eksporUrl);
        $download->assertOk();
        $csv = $download->streamedContent();

        $lines = array_values(array_filter(array_map('trim', explode("\n", $csv))));
        $this->assertGreaterThanOrEqual(3, count($lines));

        foreach (array_slice($lines, 1) as $line) {
            $cols = str_getcsv($line);
            $username = $cols[2] ?? '';
            $plainPassword = $cols[3] ?? '';
            $user = User::where('nama_pengguna', $username)->firstOrFail();

            $this->assertTrue((bool) $user->force_password_change);
            $this->assertTrue(Hash::check($plainPassword, $user->kata_sandi));
        }

        // Unduhan kedua harus 404 (sekali-unduh)
        $this->actingAs($admin)->get($eksporUrl)->assertNotFound();
    }

    public function test_no_literal_passwords_passed_to_hash_make_or_bcrypt_in_app(): void
    {
        $offenses = [];
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = File::get($file->getPathname());
            if (preg_match('/(?:Hash::make|bcrypt)\s*\(\s*[\'"][^\'"]+[\'"]\s*\)/', $content, $match)) {
                $offenses[] = $file->getRelativePathname().': '.$match[0];
            }
        }

        $this->assertSame([], $offenses, 'Ditemukan password literal pada Hash::make/bcrypt di app/: '.implode('; ', $offenses));
    }
}
