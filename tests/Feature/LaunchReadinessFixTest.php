<?php

namespace Tests\Feature;

use App\Jobs\ExportBackupJob;
use App\Jobs\ImportDapodikJob;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LaunchReadinessFixTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_konfigurasi_queue_retry_after_lebih_besar_dari_timeout_job_panjang(): void
    {
        $importJob = new ImportDapodikJob([]);
        $backupJob = new ExportBackupJob;

        $this->assertSame(300, $importJob->timeout);
        $this->assertSame(300, $backupJob->timeout);

        $envProd = file_get_contents(base_path('.env.production.example'));
        $this->assertIsString($envProd);
        $this->assertMatchesRegularExpression('/^DB_QUEUE_RETRY_AFTER=(\d+)/m', $envProd);

        preg_match('/^DB_QUEUE_RETRY_AFTER=(\d+)/m', $envProd, $matches);
        $retryAfter = (int) ($matches[1] ?? 0);
        $this->assertGreaterThan($importJob->timeout, $retryAfter);
        $this->assertGreaterThan($backupJob->timeout, $retryAfter);

        $deploymentDoc = file_get_contents(base_path('DEPLOYMENT.md'));
        $this->assertIsString($deploymentDoc);
        $this->assertStringContainsString('--timeout=300', $deploymentDoc);
        $this->assertStringContainsString('DB_QUEUE_RETRY_AFTER', $deploymentDoc);
    }

    public function test_dashboard_admin_tenant_tidak_membocorkan_metadata_backup_global(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/backup-simantap-global-rahasia.json', str_repeat('x', 4096));
        Cache::flush();

        config(['simantap.multi_tenant_enabled' => true]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40320001',
            'nama_sekolah' => 'SDN Isolasi Backup',
            'subdomain' => 'sdnisolasibackup',
            'aktif' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'peran' => 'admin',
            'aktif' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('backup-simantap-global-rahasia.json');
        $response->assertDontSee('4.0 KB');
    }

    public function test_dashboard_single_school_tetap_menampilkan_metadata_backup(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/backup-simantap-single.json', str_repeat('x', 2048));
        Cache::flush();

        config(['simantap.multi_tenant_enabled' => false]);

        $admin = User::factory()->create([
            'tenant_id' => null,
            'peran' => 'admin',
            'aktif' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('1 file');
        $response->assertSee('2.0 KB');
    }

    public function test_deployment_md_section_6_sesuai_implementasi_multi_tenant_dan_reset_sandi(): void
    {
        $deploymentDoc = file_get_contents(base_path('DEPLOYMENT.md'));
        $this->assertIsString($deploymentDoc);

        $this->assertStringNotContainsString('tidak punya fitur "lupa password"', $deploymentDoc);
        $this->assertStringNotContainsString('tidak memiliki kolom `email`', $deploymentDoc);
        $this->assertStringContainsString('php artisan superadmin:create', $deploymentDoc);
        $this->assertStringContainsString('php artisan tenant:create', $deploymentDoc);
        $this->assertStringContainsString('/daftar-sekolah', $deploymentDoc);
        $this->assertStringContainsString('/lupa-sandi', $deploymentDoc);
    }
}
