<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MenuUsageTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['simantap.menu_usage_telemetry' => true]);

        Route::middleware(['web', 'auth', 'menu.telemetry'])
            ->get('/__test/menu-usage/html', fn () => response('<!doctype html><html><body>OK</body></html>', 200, ['Content-Type' => 'text/html']))
            ->name('test.menu-usage.html');

        Route::middleware(['web', 'auth', 'menu.telemetry'])
            ->get('/__test/menu-usage/json', fn () => response()->json(['ok' => true]))
            ->name('test.menu-usage.json');

        Route::middleware(['web', 'auth', 'menu.telemetry'])
            ->post('/__test/menu-usage/post', fn () => response('<html>OK</html>', 200, ['Content-Type' => 'text/html']))
            ->name('test.menu-usage.post');

        Route::middleware(['web', 'auth', 'menu.telemetry'])
            ->get('/__test/menu-usage/error', fn () => response('<html>ERROR</html>', 500, ['Content-Type' => 'text/html']))
            ->name('test.menu-usage.error');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_halaman_html_sukses_direkap_per_hari_peran_dan_tenant_tanpa_identitas_pribadi(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();

        $this->actingAs($admin)->get('/__test/menu-usage/html')->assertOk();
        $this->actingAs($admin)->get('/__test/menu-usage/html')->assertOk();
        $this->actingAs($guru)->get('/__test/menu-usage/html')->assertOk();

        $adminRow = DB::table('menu_usage_daily')->where('role', 'admin')->first();
        $guruRow = DB::table('menu_usage_daily')->where('role', 'guru')->first();

        $this->assertNotNull($adminRow);
        $this->assertSame(2, (int) $adminRow->visits);
        $this->assertSame('test.menu-usage.html', $adminRow->route_name);
        $this->assertSame('single', $adminRow->scope_key);
        $this->assertNotNull($guruRow);
        $this->assertSame(1, (int) $guruRow->visits);

        $this->assertFalse(Schema::hasColumn('menu_usage_daily', 'user_id'));
        $this->assertFalse(Schema::hasColumn('menu_usage_daily', 'ip_address'));
        $this->assertFalse(Schema::hasColumn('menu_usage_daily', 'user_agent'));
    }

    public function test_tidak_merekam_json_post_gagal_atau_telemetry_yang_dimatikan(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/__test/menu-usage/json')->assertOk();
        $this->actingAs($admin)->post('/__test/menu-usage/post')->assertOk();
        $this->actingAs($admin)->get('/__test/menu-usage/error')->assertServerError();
        config(['simantap.menu_usage_telemetry' => false]);
        $this->actingAs($admin)->get('/__test/menu-usage/html')->assertOk();

        $this->assertSame(0, DB::table('menu_usage_daily')->count());
    }

    public function test_cakupan_tenant_dicatat_dengan_id_internal_tanpa_menyimpan_user(): void
    {
        $tenant = Tenant::create(['uuid' => Str::uuid(), 'nama_sekolah' => 'Sekolah Uji', 'aktif' => true]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin)->get('/__test/menu-usage/html')->assertOk();

        $row = DB::table('menu_usage_daily')->first();
        $this->assertNotNull($row);
        $this->assertSame('tenant:'.$tenant->id, $row->scope_key);
        $this->assertSame($tenant->id, (int) $row->tenant_id);
        $this->assertContains('tenant_id', Schema::getColumnListing('menu_usage_daily'));
        $this->assertFalse(Schema::hasColumn('menu_usage_daily', 'user_id'));
    }

    public function test_laporan_cli_menampilkan_agregat_dan_prune_mempertahankan_60_hari(): void
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow('2026-11-08 12:00:00');

        try {
            foreach ([0, 59, 60] as $daysAgo) {
                $date = now()->subDays($daysAgo)->toDateString();
                DB::table('menu_usage_daily')->insert([
                    'visit_date' => $date,
                    'scope_key' => 'single',
                    'tenant_id' => null,
                    'role' => 'admin',
                    'route_name' => 'admin.dashboard',
                    'visits' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Artisan::call('menu:usage-report', ['--days' => 30]);
            $this->assertStringContainsString('admin.dashboard', Artisan::output());

            Artisan::call('menu:usage-prune');
            $this->assertSame(2, DB::table('menu_usage_daily')->count());
            $this->assertDatabaseMissing('menu_usage_daily', ['visit_date' => '2026-09-09']);
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }
}
