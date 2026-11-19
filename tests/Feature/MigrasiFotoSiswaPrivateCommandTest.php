<?php

namespace Tests\Feature;

use App\Console\Commands\MigrasiFotoSiswaPrivateCommand;
use App\Models\Siswa;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrasiFotoSiswaPrivateCommandTest extends TestCase
{
    use RefreshDatabase;

    private ?string $tempReportDir = null;

    protected function tearDown(): void
    {
        if ($this->tempReportDir !== null && is_dir($this->tempReportDir)) {
            foreach (glob($this->tempReportDir.DIRECTORY_SEPARATOR.'*') ?: [] as $entry) {
                if (is_link($entry)) {
                    @unlink($entry);
                    @rmdir($entry);
                } elseif (is_file($entry)) {
                    @unlink($entry);
                }
            }
            @rmdir($this->tempReportDir);
        }
        $this->tempReportDir = null;

        parent::tearDown();
    }

    private function createTempReportFile(string $name = 'report.json'): string
    {
        if ($this->tempReportDir === null) {
            $baseReal = str_replace('\\', '/', rtrim((string) realpath(base_path()), '/\\'));
            $candidates = [sys_get_temp_dir(), dirname(base_path())];
            $chosenParent = null;

            foreach ($candidates as $cand) {
                $real = realpath($cand);
                if ($real === false || ! is_dir($real) || ! is_writable($real)) {
                    continue;
                }
                $norm = str_replace('\\', '/', rtrim($real, '/\\'));
                if (stripos($norm, $baseReal.'/') === 0 || strcasecmp($norm, $baseReal) === 0) {
                    continue;
                }
                $chosenParent = $real;

                break;
            }

            if ($chosenParent === null) {
                $this->markTestSkipped('Tidak ada direktori sementara yang dapat ditulis di luar repositori.');
            }

            $this->tempReportDir = $chosenParent.DIRECTORY_SEPARATOR.'simantap-test-report-'.uniqid();
            mkdir($this->tempReportDir, 0750, true);
        }

        return $this->tempReportDir.DIRECTORY_SEPARATOR.$name;
    }

    private function pngA(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    private function pngB(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
    }

    public function test_dry_run_classifies_all_categories_without_modifying_files_or_leaking_names(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $sPublic = Siswa::create(['nis' => '3001', 'nama_peserta_didik' => 'Nama Rahasia Satu', 'jenis_kelamin' => 'L', 'kelas' => '4A', 'foto' => 'foto-siswa/onlypub111.png', 'aktif' => true]);
        $sIdentik = Siswa::create(['nis' => '3002', 'nama_peserta_didik' => 'Nama Rahasia Dua', 'jenis_kelamin' => 'P', 'kelas' => '4A', 'foto' => 'foto-siswa/identik222.png', 'aktif' => true]);
        $sKonflik = Siswa::create(['nis' => '3003', 'nama_peserta_didik' => 'Nama Rahasia Tiga', 'jenis_kelamin' => 'L', 'kelas' => '4A', 'foto' => 'foto-siswa/konflik333.png', 'aktif' => true]);
        $sPrivate = Siswa::create(['nis' => '3004', 'nama_peserta_didik' => 'Nama Rahasia Empat', 'jenis_kelamin' => 'P', 'kelas' => '4A', 'foto' => 'foto-siswa/onlypriv444.png', 'aktif' => true]);
        $sHilang = Siswa::create(['nis' => '3005', 'nama_peserta_didik' => 'Nama Rahasia Lima', 'jenis_kelamin' => 'L', 'kelas' => '4A', 'foto' => 'foto-siswa/hilang555.png', 'aktif' => true]);
        $sInvalid = Siswa::create(['nis' => '3006', 'nama_peserta_didik' => 'Nama Rahasia Enam', 'jenis_kelamin' => 'P', 'kelas' => '4A', 'foto' => '../etc/passwd', 'aktif' => true]);

        Storage::disk('public')->put($sPublic->foto, $this->pngA());
        Storage::disk('public')->put($sIdentik->foto, $this->pngA());
        Storage::disk('local')->put($sIdentik->foto, $this->pngA());
        Storage::disk('public')->put($sKonflik->foto, $this->pngA());
        Storage::disk('local')->put($sKonflik->foto, $this->pngB());
        Storage::disk('local')->put($sPrivate->foto, $this->pngA());
        Storage::disk('public')->put('foto-siswa/yatim999.png', $this->pngA());

        $reportFile = $this->createTempReportFile('dryrun.json');

        try {
            $this->artisan('siswa:migrasi-foto-private', ['--report' => $reportFile])
                ->assertExitCode(0)
                ->expectsOutputToContain('hanya_public: 1')
                ->expectsOutputToContain('ada_di_keduanya_identik: 1')
                ->expectsOutputToContain('ada_di_keduanya_berbeda: 1')
                ->expectsOutputToContain('hanya_private: 1')
                ->expectsOutputToContain('hilang: 1')
                ->expectsOutputToContain('path_tidak_valid: 1')
                ->expectsOutputToContain('yatim_public: 1')
                ->doesntExpectOutputToContain('Nama Rahasia')
                ->doesntExpectOutputToContain('onlypub111.png');

            // Dry-run tidak menyalin atau menghapus apa pun
            Storage::disk('local')->assertMissing($sPublic->foto);
            Storage::disk('public')->assertExists($sPublic->foto);
            Storage::disk('public')->assertExists($sIdentik->foto);

            $this->assertFileExists($reportFile);
            $rawReport = (string) file_get_contents($reportFile);
            $this->assertStringNotContainsString('Nama Rahasia', $rawReport);
            $this->assertStringNotContainsString('onlypub111.png', $rawReport);

            $decoded = json_decode($rawReport, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([$sPublic->id], $decoded['student_ids_by_category']['hanya_public']);
            $this->assertSame([$sKonflik->id], $decoded['student_ids_by_category']['ada_di_keduanya_berbeda']);
            $this->assertSame([$sInvalid->id], $decoded['student_ids_by_category']['path_tidak_valid']);
        } finally {
            @unlink($reportFile);
        }
    }

    public function test_delete_public_requires_apply_and_yes_backup_done_and_report_must_be_outside_repo(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $s = Siswa::create(['nis' => '3010', 'nama_peserta_didik' => 'Siswa Guard', 'jenis_kelamin' => 'L', 'kelas' => '4B', 'foto' => 'foto-siswa/guard111.png', 'aktif' => true]);
        Storage::disk('public')->put($s->foto, $this->pngA());

        $this->artisan('siswa:migrasi-foto-private', ['--delete-public' => true])->assertExitCode(1);
        $this->artisan('siswa:migrasi-foto-private', ['--apply' => true, '--delete-public' => true])->assertExitCode(1);

        // Guard --report: tolak path di dalam repo, path traversal '..', dan path relatif
        $this->artisan('siswa:migrasi-foto-private', ['--report' => base_path('storage/report.json')])->assertExitCode(1);
        $this->artisan('siswa:migrasi-foto-private', ['--report' => dirname(base_path()).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.basename(dirname(base_path())).DIRECTORY_SEPARATOR.basename(base_path()).DIRECTORY_SEPARATOR.'report.json'])->assertExitCode(1);
        $this->artisan('siswa:migrasi-foto-private', ['--report' => 'report.json'])->assertExitCode(1);
        $this->artisan('siswa:migrasi-foto-private', ['--report' => '../report.json'])->assertExitCode(1);

        // Path luar repo yang sah diterima
        $validReport = $this->createTempReportFile('valid.json');
        $this->artisan('siswa:migrasi-foto-private', ['--report' => $validReport])->assertExitCode(0);
        $this->assertFileExists($validReport);

        Storage::disk('public')->assertExists($s->foto);
        Storage::disk('local')->assertMissing($s->foto);
    }

    public function test_apply_is_idempotent_protects_conflicts_and_delete_public_cleans_only_verified(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $sPub = Siswa::create(['nis' => '3020', 'nama_peserta_didik' => 'Siswa Pub', 'jenis_kelamin' => 'L', 'kelas' => '5A', 'foto' => 'foto-siswa/pub2020.png', 'aktif' => true]);
        $sKonflik = Siswa::create(['nis' => '3021', 'nama_peserta_didik' => 'Siswa Konflik', 'jenis_kelamin' => 'P', 'kelas' => '5A', 'foto' => 'foto-siswa/konflik2021.png', 'aktif' => true]);
        Storage::disk('public')->put($sPub->foto, $this->pngA());
        Storage::disk('public')->put($sKonflik->foto, $this->pngA());
        Storage::disk('local')->put($sKonflik->foto, $this->pngB());
        Storage::disk('public')->put('foto-siswa/orphan2022.png', $this->pngA());

        // 1. Apply pertama: menyalin pub -> local, memverifikasi SHA-256, tidak menghapus public
        $this->artisan('siswa:migrasi-foto-private', ['--apply' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('disalin_terverifikasi: 1')
            ->expectsOutputToContain('dihapus_public: 0');

        Storage::disk('local')->assertExists($sPub->foto);
        Storage::disk('public')->assertExists($sPub->foto);
        $this->assertSame($this->pngB(), Storage::disk('local')->get($sKonflik->foto));
        $this->assertSame($this->pngA(), Storage::disk('public')->get($sKonflik->foto));

        // 2. Apply kedua (idempotent): tidak menyalin ulang, masuk ada_di_keduanya_identik
        $this->artisan('siswa:migrasi-foto-private', ['--apply' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('hanya_public: 0')
            ->expectsOutputToContain('ada_di_keduanya_identik: 1')
            ->expectsOutputToContain('disalin_terverifikasi: 0');

        // 3. Apply + delete-public + yes-backup-done: hanya berkas identik dihapus dari public; konflik & yatim tetap aman
        $this->artisan('siswa:migrasi-foto-private', [
            '--apply' => true,
            '--delete-public' => true,
            '--yes-backup-done' => true,
        ])->assertExitCode(0)
            ->expectsOutputToContain('dihapus_public: 1');

        Storage::disk('local')->assertExists($sPub->foto);
        Storage::disk('public')->assertMissing($sPub->foto);
        Storage::disk('public')->assertExists($sKonflik->foto);
        Storage::disk('local')->assertExists($sKonflik->foto);
        Storage::disk('public')->assertExists('foto-siswa/orphan2022.png');
    }

    public function test_checksum_mismatch_does_not_delete_public_copy(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $s = Siswa::create(['nis' => '3030', 'nama_peserta_didik' => 'Siswa Checksum', 'jenis_kelamin' => 'L', 'kelas' => '6A', 'foto' => 'foto-siswa/mismatch3030.png', 'aktif' => true]);
        Storage::disk('public')->put($s->foto, $this->pngA());

        $corruptCmd = new class extends MigrasiFotoSiswaPrivateCommand
        {
            protected function hashDiskFile(string $disk, string $path): ?string
            {
                return $disk === 'local' ? str_repeat('0', 64) : parent::hashDiskFile($disk, $path);
            }
        };

        Artisan::starting(function ($artisan) use ($corruptCmd): void {
            $artisan->add($corruptCmd);
        });
        $this->app[Kernel::class]->registerCommand($corruptCmd);

        $this->artisan('siswa:migrasi-foto-private', [
            '--apply' => true,
            '--delete-public' => true,
            '--yes-backup-done' => true,
        ])->assertExitCode(1)
            ->expectsOutputToContain('gagal_verifikasi: 1')
            ->expectsOutputToContain('dihapus_public: 0');

        Storage::disk('public')->assertExists($s->foto);
    }

    public function test_report_guard_rejects_symlink_pointing_inside_repo(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->createTempReportFile('init.json');
        $linkPath = $this->tempReportDir.DIRECTORY_SEPARATOR.'link-to-repo';

        if (! @symlink(storage_path(), $linkPath)) {
            $this->markTestSkipped('Symlink tidak dapat dibuat di lingkungan ini (Windows memerlukan Developer Mode atau hak administrator).');
        }

        $this->artisan('siswa:migrasi-foto-private', [
            '--report' => $linkPath.DIRECTORY_SEPARATOR.'report-via-symlink.json',
        ])->assertExitCode(1);
    }
}
