<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\E2eFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(E2eFixtureSeeder::class);
    }

    public function test_download_bridge_redirects_when_download_url_is_configured(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);
        $url = 'https://github.com/example/simantap-releases/releases/download/v1.0.0/SIMANTAP-Bridge.exe';

        config([
            'dapodik.bridge_download_url' => $url,
            'dapodik.bridge_version' => '1.0.0',
            'dapodik.bridge_sha256' => str_repeat('a', 64),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dapodik.download-bridge'))
            ->assertRedirect($url);

        $indexResponse = $this->actingAs($admin)->get(route('admin.dapodik.index'))->assertOk();
        $indexResponse->assertSee('Unduh SIMANTAP-Bridge (.exe)');
        $indexResponse->assertSee('Versi 1.0.0');
        $indexResponse->assertSee(str_repeat('a', 64));
        $indexResponse->assertSee('Windows SmartScreen mungkin menampilkan peringatan karena file belum ditandatangani.');
    }

    public function test_download_bridge_streams_private_file_when_url_is_empty(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);
        $tmpFile = tempnam(sys_get_temp_dir(), 'bridge_');
        file_put_contents($tmpFile, 'dummy-bridge-binary');

        try {
            config([
                'dapodik.bridge_download_url' => null,
                'dapodik.bridge_storage_path' => $tmpFile,
                'dapodik.bridge_version' => null,
                'dapodik.bridge_sha256' => null,
            ]);

            $response = $this->actingAs($admin)->get(route('admin.dapodik.download-bridge'));
            $response->assertOk();
            $response->assertHeader('Content-Disposition', 'attachment; filename=SIMANTAP-Bridge.exe');
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_download_bridge_handles_missing_url_and_file_gracefully(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);

        config([
            'dapodik.bridge_download_url' => null,
            'dapodik.bridge_storage_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'non-existent-bridge-file.exe',
            'dapodik.bridge_version' => null,
            'dapodik.bridge_sha256' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dapodik.download-bridge'))
            ->assertRedirect(route('admin.dapodik.index'))
            ->assertSessionHas('error');

        $indexResponse = $this->actingAs($admin)->get(route('admin.dapodik.index'))->assertOk();
        $indexResponse->assertDontSee('Unduh SIMANTAP-Bridge (.exe)');
        $indexResponse->assertSee('belum tersedia di server');
    }

    public function test_non_admin_and_guest_cannot_download_bridge(): void
    {
        $this->get(route('admin.dapodik.download-bridge'))
            ->assertRedirect(route('login'));

        foreach (['guru_a', 'siswa_a1', 'ortu_a1'] as $roleKey) {
            $user = User::findOrFail(E2eFixtureSeeder::USER_IDS[$roleKey]);
            $this->actingAs($user)
                ->get(route('admin.dapodik.download-bridge'))
                ->assertForbidden();
        }
    }
}
