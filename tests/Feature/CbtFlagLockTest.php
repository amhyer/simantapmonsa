<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\E2eFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

class CbtFlagLockTest extends TestCase
{
    use RefreshDatabase;

    private array $cbtFlagBeforeBoot = [];

    protected function setUp(): void
    {
        $this->cbtFlagBeforeBoot = [
            getenv('SIMANTAP_CBT_ENABLED'),
            $_ENV['SIMANTAP_CBT_ENABLED'] ?? null,
            $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null,
        ];

        putenv('SIMANTAP_CBT_ENABLED=false');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'false';
        Env::enablePutenv();

        parent::setUp();

        $this->withoutVite();
        config(['simantap.cbt_enabled' => false]);
        $this->seed(E2eFixtureSeeder::class);
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

        Env::enablePutenv();
    }

    public function test_cbt_guru_and_student_routes_return_404_for_all_roles_and_guest_when_flag_false(): void
    {
        $urls = [
            '/cbt/question-banks',
            '/cbt/sessions',
            '/cbt/student',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertNotFound();
        }

        foreach (['admin', 'guru_a', 'siswa_a1', 'ortu_a1'] as $roleKey) {
            $user = User::findOrFail(E2eFixtureSeeder::USER_IDS[$roleKey]);
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertNotFound();
            }
        }
    }

    public function test_admin_cbt_routes_return_404_for_admin_and_super_admin_when_flag_false(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);
        $superAdmin = User::factory()->create([
            'peran' => 'super_admin',
            'tenant_id' => null,
            'aktif' => true,
            'force_password_change' => false,
        ]);

        foreach (['/admin/cbt/sessions', '/admin/cbt/banks'] as $url) {
            $this->actingAs($admin)->get($url)->assertNotFound();
            $this->actingAs($superAdmin)->get($url)->assertNotFound();
        }
    }

    public function test_tryout_and_tka_admin_routes_are_not_404_when_only_cbt_flag_is_false(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);

        $tryoutResponse = $this->actingAs($admin)->get(route('admin.tryout.statistics'));
        $this->assertNotEquals(404, $tryoutResponse->getStatusCode(), 'admin.tryout.statistics should not be 404');

        $tkaResponse = $this->actingAs($admin)->get(route('admin.tka.dashboard'));
        $this->assertNotEquals(404, $tkaResponse->getStatusCode(), 'admin.tka.dashboard should not be 404');
    }

    public function test_admin_cbt_routes_are_accessible_for_admin_when_flag_true(): void
    {
        config(['simantap.cbt_enabled' => true]);
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);

        $this->actingAs($admin)->get('/admin/cbt/sessions')->assertOk();
        $this->actingAs($admin)->get('/admin/cbt/banks')->assertOk();
    }
}
