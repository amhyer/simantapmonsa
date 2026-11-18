<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditDefaultPasswordsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_matching_accounts_without_mutating(): void
    {
        $probe = 'ProbeSecret-9988';
        $originalHash = Hash::make($probe);
        $user = User::factory()->create([
            'kata_sandi' => $originalHash,
            'force_password_change' => false,
        ]);

        $this->artisan('akun:audit-password-bawaan', ['--password' => $probe])
            ->expectsOutputToContain('DRY-RUN')
            ->expectsOutputToContain('Jumlah akun cocok: 1')
            ->assertExitCode(0);

        $fresh = $user->fresh();
        $this->assertSame($originalHash, $fresh->kata_sandi);
        $this->assertFalse((bool) $fresh->force_password_change);
    }

    public function test_apply_resets_matching_accounts_with_tenant_filter_and_writes_export(): void
    {
        $probe = 'ProbeSecret-9988';
        $tenantA = Tenant::create(['npsn' => '31313131', 'nama_sekolah' => 'Sekolah A', 'subdomain' => 'audit-a', 'aktif' => true]);
        $tenantB = Tenant::create(['npsn' => '32323232', 'nama_sekolah' => 'Sekolah B', 'subdomain' => 'audit-b', 'aktif' => true]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'kata_sandi' => Hash::make($probe), 'force_password_change' => false]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'kata_sandi' => Hash::make($probe), 'force_password_change' => false]);

        $exportPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'audit-pw-export-'.uniqid().'.csv';

        try {
            $this->artisan('akun:audit-password-bawaan', [
                '--password' => $probe,
                '--tenant' => $tenantA->id,
                '--apply' => true,
                '--export' => $exportPath,
                '--force' => true,
            ])->assertExitCode(0);

            $freshA = $userA->fresh();
            $freshB = $userB->fresh();

            $this->assertTrue((bool) $freshA->force_password_change);
            $this->assertFalse(Hash::check($probe, $freshA->kata_sandi));

            // Tenant B tidak tersentuh
            $this->assertFalse((bool) $freshB->force_password_change);
            $this->assertTrue(Hash::check($probe, $freshB->kata_sandi));

            $this->assertFileExists($exportPath);
            $csv = file_get_contents($exportPath);
            $this->assertStringContainsString($freshA->nama_pengguna, (string) $csv);
        } finally {
            @unlink($exportPath);
        }
    }
}
