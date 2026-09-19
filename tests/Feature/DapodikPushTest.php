<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DapodikPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_mengakses_status_dapodik_push_tanpa_server_error(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/dapodik/push/status');

        // Regresi bug: DapodikPushController::status() memakai $this->config
        // yang tidak ada sehingga melempar Error (HTTP 500).
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => ['npsn', 'host', 'port', 'protocol', 'configured'],
        ]);
    }

    public function test_guest_dialihkan_ke_login_saat_akses_push_status(): void
    {
        $this->get('/admin/dapodik/push/status')->assertRedirect(route('login'));
    }

    public function test_non_admin_ditolak_akses_push_status(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get('/admin/dapodik/push/status')->assertForbidden();
    }

    public function test_telescope_gate_mengizinkan_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('viewTelescope'));
    }

    public function test_telescope_gate_menolak_non_admin(): void
    {
        $guru = User::factory()->guru()->create();

        $this->assertFalse(Gate::forUser($guru)->allows('viewTelescope'));
    }
}
