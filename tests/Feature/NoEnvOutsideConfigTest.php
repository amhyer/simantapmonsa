<?php

namespace Tests\Feature;

use App\Providers\TelescopeServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class NoEnvOutsideConfigTest extends TestCase
{
    /**
     * Seeder khusus lokal/testing yang memang membaca env CLI secara langsung.
     *
     * @var array<int, string>
     */
    private const ALLOWED_DEV_SEEDERS = [
        'database/seeders/Support/SeedGuard.php',
        'database/seeders/LoadTestSeeder.php',
        'database/seeders/E2eFixtureSeeder.php',
    ];

    public function test_no_php_env_calls_exist_outside_config_directory(): void
    {
        $roots = ['app', 'routes', 'resources/views', 'database'];
        $violations = [];

        foreach ($roots as $root) {
            $dir = base_path($root);
            if (! is_dir($dir)) {
                continue;
            }

            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()))), '/');
                if (in_array($rel, self::ALLOWED_DEV_SEEDERS, true)) {
                    continue;
                }

                $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];
                foreach ($lines as $idx => $line) {
                    if (preg_match('/(?<![A-Za-z0-9_>])env\s*\(/', $line) === 1) {
                        $violations[] = $rel.':'.($idx + 1).': '.trim($line);
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Ditemukan pemanggilan env() di luar config/:\n".implode("\n", $violations));
    }

    public function test_telescope_is_disabled_by_default_and_not_hardcoded_in_bootstrap_providers(): void
    {
        $this->assertFalse((bool) config('telescope.enabled'));
        $providers = require base_path('bootstrap/providers.php');
        $this->assertNotContains(TelescopeServiceProvider::class, $providers);
    }
}
