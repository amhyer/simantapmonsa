<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Inventaris menu statis: memetakan setiap entri sidebar ke rute yang
 * sebenarnya terdaftar, lalu menulis laporan untuk ditinjau manusia.
 *
 * Yang diuji (gagal CI):
 *  - setiap entri menu menunjuk nama rute yang ada;
 *  - tidak ada rute yang muncul dua kali dalam satu peran + tab yang sama.
 *
 * Yang dilaporkan saja (tidak menggagalkan CI):
 *  - menu yang rutenya diblokir middleware mode sekolah tunggal;
 *  - rute halaman GET tanpa parameter yang tidak punya pintu masuk menu.
 */
class MenuInventoryTest extends TestCase
{
    /** @var array<string, string> peran => path sidebar relatif terhadap resources/views */
    private const SIDEBAR = [
        'super_admin' => 'superadmin/partials/sidebar.blade.php',
        'admin' => 'admin/partials/sidebar.blade.php',
        'kepsek' => 'kepsek/partials/sidebar.blade.php',
        'guru' => 'guru/partials/sidebar.blade.php',
        'pegawai' => 'pegawai/partials/sidebar.blade.php',
        'siswa' => 'siswa/partials/sidebar.blade.php',
        'ortu' => 'ortu/partials/sidebar.blade.php',
    ];

    /** Middleware yang menolak permintaan pada instalasi ber-sekolah-banyak. */
    private const MIDDLEWARE_TENANT_BLOCK = ['data.single-school', 'dapodik.single-school'];

    /** Rute yang memang bukan halaman menu (auth, berkas, endpoint data). */
    private const TANPA_MENU_DIIZINKAN = [
        'login', 'logout', 'register', 'home', 'dashboard',
        'password.', 'verification.', 'force-password-change.',
        'storage.', 'sanctum.', 'livewire.', '_ignition', 'telescope.',
        'api.',
    ];

    private array $cbtFlagBeforeBoot = [];

    protected function setUp(): void
    {
        // Rute CBT/Tryout hanya didaftarkan saat boot bila flag ini aktif.
        $this->cbtFlagBeforeBoot = [
            getenv('SIMANTAP_CBT_ENABLED'),
            $_ENV['SIMANTAP_CBT_ENABLED'] ?? null,
            $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null,
        ];
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        parent::setUp();

        config([
            'simantap.cbt_enabled' => true,
            'simantap.exam_engine_cbt' => true,
            'simantap.exam_engine_tryout' => true,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        [$env, $arrayEnv, $server] = $this->cbtFlagBeforeBoot;
        $env === false
            ? putenv('SIMANTAP_CBT_ENABLED')
            : putenv('SIMANTAP_CBT_ENABLED='.$env);

        if ($arrayEnv === null) {
            unset($_ENV['SIMANTAP_CBT_ENABLED']);
        } else {
            $_ENV['SIMANTAP_CBT_ENABLED'] = $arrayEnv;
        }

        if ($server === null) {
            unset($_SERVER['SIMANTAP_CBT_ENABLED']);
        } else {
            $_SERVER['SIMANTAP_CBT_ENABLED'] = $server;
        }

    }

    public function test_inventaris_menu_konsisten_dan_laporannya_dibuat(): void
    {
        $inventory = $this->buildInventory();

        $report = $this->buildReport($inventory);
        File::ensureDirectoryExists(storage_path('logs'));
        file_put_contents(storage_path('logs/menu-inventory.md'), $report);

        $this->assertGreaterThan(100, count($inventory), 'Inventaris menu terlalu kecil; parser sidebar kemungkinan rusak.');

        $missing = [];
        foreach ($inventory as $entry) {
            if (! Route::has($entry['route'])) {
                $missing[] = "{$entry['role']} / {$entry['label']} -> {$entry['route']}";
            }
        }
        $this->assertSame([], $missing, "Entri menu menunjuk rute yang tidak terdaftar:\n".implode("\n", $missing));

        $this->assertSame([], $this->duplicatesWithinRoleAndTab($inventory), 'Ada rute menu ganda dalam satu peran + tab.');

        fwrite(STDOUT, "\nInventaris menu: ".count($inventory)." entri; laporan di storage/logs/menu-inventory.md\n");
    }

    /** @return array<int, array{role: string, tab: string, label: string, route: string, flag: string}> */
    private function buildInventory(): array
    {
        $inventory = [];

        foreach (self::SIDEBAR as $role => $relative) {
            $path = resource_path('views/'.$relative);
            $this->assertFileExists($path);
            $source = (string) file_get_contents($path);

            foreach ($this->extractEntries($source) as $entry) {
                $inventory[] = [
                    'role' => $role,
                    'tab' => $role === 'admin' ? $this->adminTabAt($source, $entry['offset']) : '—',
                    'label' => $entry['label'],
                    'route' => $entry['route'],
                    'flag' => $entry['flag'],
                ];
            }
        }

        return $inventory;
    }

    /**
     * Sidebar punya dua gaya penulisan: item array `['route' => ..., 'label' => ...]`
     * dan komponen `<x-sidebar-link :href="route(...)" label="..." />`.
     *
     * @return array<int, array{route: string, label: string, flag: string, offset: int}>
     */
    private function extractEntries(string $source): array
    {
        $entries = [];

        // Gaya array. Karakter `[` dan `]` sengaja dilarang agar header grup
        // yang punya `children` tidak ikut terbaca sebagai satu entri.
        preg_match_all(
            "/\[[^\[\]]*'route'\s*=>\s*'([^']+)'[^\[\]]*?'label'\s*=>\s*'([^']*)'[^\[\]]*\]/",
            $source,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $match) {
            $entries[] = ['route' => $match[1][0], 'label' => $match[2][0], 'flag' => str_contains($match[0][0], "'asesmen' => true") ? 'modul asesmen' : '—', 'offset' => $match[0][1]];
        }

        // Gaya komponen Blade.
        preg_match_all(
            "/route\('([^']+)'\)[^>]*?label=\"([^\"]*)\"/",
            $source,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $match) {
            $entries[] = ['route' => $match[1][0], 'label' => $match[2][0], 'flag' => '—', 'offset' => $match[0][1]];
        }

        usort($entries, fn (array $a, array $b) => $a['offset'] <=> $b['offset']);

        return $entries;
    }

    /** Tentukan tab admin (`erapor`, `cbt`, `tka`, `setup`) pada posisi tertentu. */
    private function adminTabAt(string $source, int $offset): string
    {
        $markers = [];
        foreach (['erapor', 'cbt', 'tka', 'setup'] as $tab) {
            if (preg_match_all("/^\s*'{$tab}'\s*=>\s*\[/m", $source, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $markers[$match[1]] = $tab;
                }
            }
        }
        ksort($markers);

        $current = 'utama';
        foreach ($markers as $position => $tab) {
            if ($position > $offset) {
                break;
            }
            $current = $tab;
        }

        return $current;
    }

    /** @return array<int, string> */
    private function duplicatesWithinRoleAndTab(array $inventory): array
    {
        $grouped = [];
        foreach ($inventory as $entry) {
            $grouped[$entry['role'].' / '.$entry['tab']][] = $entry['route'];
        }

        $duplicates = [];
        foreach ($grouped as $key => $routes) {
            foreach (array_count_values($routes) as $route => $count) {
                if ($count > 1) {
                    $duplicates[] = "{$key}: {$route} x{$count}";
                }
            }
        }

        return $duplicates;
    }

    private function buildReport(array $inventory): string
    {
        $lines = [];
        $lines[] = '# Inventaris menu (dibuat otomatis)';
        $lines[] = '';
        $lines[] = 'Dibuat oleh `tests/Feature/MenuInventoryTest.php` pada '.now()->toDateTimeString().'.';
        $lines[] = 'Jalankan ulang: `php artisan test --filter=MenuInventoryTest`.';
        $lines[] = '';

        $lines[] = '## Jumlah entri per peran';
        $lines[] = '';
        $lines[] = '| Peran | Entri menu | Rute unik |';
        $lines[] = '|---|---|---|';
        foreach ($this->byRole($inventory) as $role => $entries) {
            $lines[] = "| {$role} | ".count($entries).' | '.count(array_unique(array_column($entries, 'route'))).' |';
        }
        $lines[] = '';

        $lines[] = '## Rute berulang antar-tab Admin';
        $lines[] = '';
        $crossTabDuplicates = $this->adminDuplicatesAcrossTabs($inventory);
        if ($crossTabDuplicates === []) {
            $lines[] = 'Tidak ada rute Admin berulang antar-tab.';
        } else {
            $lines[] = '| Rute | Tab | Jumlah kemunculan |';
            $lines[] = '|---|---|---:|';
            foreach ($crossTabDuplicates as $route => $tabs) {
                $lines[] = '| `'.$route.'` | '.implode(', ', array_keys($tabs)).' | '.array_sum($tabs).' |';
            }
        }
        $lines[] = '';

        $lines[] = '## Menu yang rutenya diblokir di instalasi ber-sekolah-banyak';
        $lines[] = '';
        $blocked = $this->tenantBlockedMenus($inventory);
        if ($blocked === []) {
            $lines[] = 'Tidak ada entri menu yang langsung diblokir middleware.';
        } else {
            $lines[] = '| Peran | Tab/grup | Label | Rute | Middleware |';
            $lines[] = '|---|---|---|---|---|';
            foreach ($blocked as $row) {
                $lines[] = "| {$row['role']} | {$row['tab']} | {$row['label']} | `{$row['route']}` | {$row['middleware']} |";
            }
        }
        $lines[] = '';

        $lines[] = '## Rute halaman GET tanpa pintu masuk menu';
        $lines[] = '';
        $orphans = $this->routesWithoutMenuEntry($inventory);
        if ($orphans === []) {
            $lines[] = 'Tidak ada rute halaman yang yatim.';
        } else {
            $lines[] = 'Rute tanpa parameter yang tidak dirujuk sidebar maupun tautan lain.';
            $lines[] = 'Sebagian sah (cetak, unduh, ajax); periksa satu per satu.';
            $lines[] = '';
            foreach ($orphans as $uri) {
                $lines[] = '- `/'.ltrim($uri, '/').'`';
            }
        }
        $lines[] = '';

        $lines[] = '## Matriks menu';
        $lines[] = '';
        foreach ($this->byRole($inventory) as $role => $entries) {
            $lines[] = "### {$role}";
            $lines[] = '';
            $lines[] = '| Tab/grup | Label | Rute | URI | Controller@method | Middleware | Flag |';
            $lines[] = '|---|---|---|---|---|---|---|';
            foreach ($entries as $entry) {
                $route = Route::getRoutes()->getByName($entry['route']);
                if ($route === null) {
                    $lines[] = "| {$entry['tab']} | {$entry['label']} | `{$entry['route']}` | — | — (rute tidak terdaftar) | — | {$entry['flag']} |";
                    continue;
                }
                $uri = '/'.ltrim($route->uri(), '/');
                $handler = $route->getActionName();
                $middleware = implode(', ', $route->gatherMiddleware());
                $lines[] = "| {$entry['tab']} | {$entry['label']} | `{$entry['route']}` | `{$uri}` | `{$handler}` | `{$middleware}` | {$entry['flag']} |";
            }
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    /** @return array<string, array<string, int>> route => tab => kemunculan */
    private function adminDuplicatesAcrossTabs(array $inventory): array
    {
        $routes = [];
        foreach ($inventory as $entry) {
            if ($entry['role'] !== 'admin') {
                continue;
            }
            $routes[$entry['route']][$entry['tab']] = ($routes[$entry['route']][$entry['tab']] ?? 0) + 1;
        }

        return array_filter($routes, fn (array $tabs) => count($tabs) > 1);
    }

    private function byRole(array $inventory): array
    {
        $grouped = [];
        foreach ($inventory as $entry) {
            $grouped[$entry['role']][] = $entry;
        }

        return $grouped;
    }

    /** @return array<int, array<string, string>> */
    private function tenantBlockedMenus(array $inventory): array
    {
        $rows = [];
        foreach ($inventory as $entry) {
            $route = Route::getRoutes()->getByName($entry['route']);
            if ($route === null) {
                continue;
            }

            $hit = array_values(array_intersect($route->gatherMiddleware(), self::MIDDLEWARE_TENANT_BLOCK));
            if ($hit === []) {
                continue;
            }

            $rows[] = [
                'role' => $entry['role'],
                'tab' => $entry['tab'],
                'label' => $entry['label'],
                'route' => $entry['route'],
                'middleware' => implode(', ', $hit),
            ];
        }

        return $rows;
    }

    /**
     * Rute GET tanpa parameter yang tidak disebut di sidebar, command palette,
     * bottom nav, maupun tautan Blade lain.
     *
     * @return array<int, string>
     */
    private function routesWithoutMenuEntry(array $inventory): array
    {
        $referenced = array_flip(array_column($inventory, 'route'));

        foreach (['components/command-palette.blade.php', 'components/mobile-bottom-nav.blade.php'] as $extra) {
            $path = resource_path('views/'.$extra);
            if (! is_file($path)) {
                continue;
            }
            preg_match_all("/route\('([^']+)'/", (string) file_get_contents($path), $matches);
            foreach ($matches[1] as $name) {
                $referenced[$name] = true;
            }
        }

        $linked = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all("/route\('([^']+)'/", (string) file_get_contents($file->getPathname()), $matches);
            foreach ($matches[1] as $name) {
                $linked[$name] = true;
            }
        }

        $orphans = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $name = $route->getName();
            if ($name === null || isset($referenced[$name]) || isset($linked[$name])) {
                continue;
            }

            if ($route->parameterNames() !== []) {
                continue;
            }

            $uri = $route->uri();
            if ($this->isAllowedWithoutMenu($name, $uri)) {
                continue;
            }

            $orphans[] = $uri;
        }

        sort($orphans);

        return $orphans;
    }

    private function isAllowedWithoutMenu(string $name, string $uri): bool
    {
        foreach (self::TANPA_MENU_DIIZINKAN as $allowed) {
            if (str_starts_with($name, $allowed) || str_starts_with($uri, $allowed)) {
                return true;
            }
        }

        return str_contains($uri, '_ignition')
            || str_contains($uri, 'telescope')
            || str_contains($uri, 'sanctum')
            || str_contains($uri, 'livewire');
    }
}
