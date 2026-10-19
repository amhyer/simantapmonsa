<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Pagar kesehatan rute: setiap route GET dijalankan untuk SETIAP peran dan
 * TIDAK boleh menghasilkan HTTP 5xx.
 *
 * Ini menangkap regresi seperti "View [...] not found", "Call to undefined
 * method", dan "Attempt to read property on null" yang sebelumnya lolos karena
 * test suite tidak pernah menyentuh route tersebut.
 *
 * Rujukan: docs/RENCANA_PERBAIKAN_VERIFIED.md (§4, Fase 0.3).
 * Laporan disimpan di storage/logs/route-health-report.txt.
 */
#[Group('baseline')]
class RouteHealthAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_tidak_ada_route_get_yang_menghasilkan_http_5xx(): void
    {
        // Aktifkan modul asesmen agar route CBT/Tryout/TKA benar-benar diuji,
        // bukan sekadar 404 karena kill switch.
        config(['simantap.cbt_enabled' => true]);

        $peran = [
            'admin' => User::factory()->admin()->create(),
            'guru' => User::factory()->guru()->create(),
            'siswa' => User::factory()->siswa()->create(),
            'ortu' => User::factory()->ortu()->create(),
            'kepsek' => User::factory()->kepsek()->create(),
        ];

        // Route auth/aset/diagnostik yang tidak relevan untuk smoke test.
        $dilewati = [
            'login', 'logout', 'register',
            'password.request', 'password.email', 'password.reset', 'password.store',
            'verification.notice', 'verification.verify', 'verification.send',
        ];

        $laporan = [];
        $jumlahDiuji = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (str_contains($uri, '_ignition') || str_contains($uri, 'telescope')
                || str_contains($uri, 'sanctum') || str_contains($uri, 'livewire')) {
                continue;
            }

            $nama = $route->getName();
            if ($nama !== null && in_array($nama, $dilewati, true)) {
                continue;
            }

            $path = '/'.ltrim($uri, '/');
            foreach ($route->parameterNames() as $param) {
                $path = preg_replace('#\{'.$param.'\??\}#', '1', $path);
            }

            foreach ($peran as $label => $user) {
                $jumlahDiuji++;

                try {
                    $status = $this->actingAs($user)->get($path)->getStatusCode();
                } catch (\Throwable $e) {
                    $laporan[] = "{$label} GET {$path} ({$nama}) => EXCEPTION: ".$e->getMessage();

                    continue;
                }

                if ($status >= 500) {
                    $laporan[] = "{$label} GET {$path} ({$nama}) => HTTP {$status}";
                }
            }
        }

        $isi = $laporan === []
            ? "OK: 0 endpoint 5xx dari {$jumlahDiuji} kombinasi rute x peran."
            : "Ditemukan ".count($laporan)." endpoint 5xx dari {$jumlahDiuji} kombinasi:\n".implode(PHP_EOL, $laporan);

        file_put_contents(storage_path('logs/route-health-report.txt'), $isi);

        $this->assertSame(
            [],
            $laporan,
            "Terdapat endpoint HTTP 5xx:\n".implode("\n", array_slice($laporan, 0, 50))
        );
    }
}
