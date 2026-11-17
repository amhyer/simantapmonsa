<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
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

    public function test_admin_can_manage_cbt_banks_but_sessions_return_404_when_flag_false(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);
        $guru = User::findOrFail(E2eFixtureSeeder::USER_IDS['guru_a']);
        $bank = CbtQuestionBank::findOrFail(E2eFixtureSeeder::CBT_BANK_ID);

        // Bank Soal (persiapan): admin dapat mengakses index, create, store, show, edit, update
        $indexResponse = $this->actingAs($admin)->get(route('admin.cbt.banks.index'))->assertOk();
        $indexResponse->assertSee('Mode persiapan: CBT belum dibuka untuk siswa.');
        $indexResponse->assertSee('Bank Soal CBT (Persiapan)');
        $indexResponse->assertDontSee('/admin/cbt/sessions');
        $this->actingAs($admin)->get(route('admin.cbt.banks.create'))->assertOk();
        $showResponse = $this->actingAs($admin)->get(route('admin.cbt.banks.show', $bank))->assertOk();
        $showResponse->assertSee('Mode persiapan: CBT belum dibuka untuk siswa.');
        $showResponse->assertDontSee('/admin/cbt/sessions');
        $this->actingAs($admin)->get(route('admin.cbt.banks.edit', $bank))->assertOk();

        $this->actingAs($admin)->post(route('admin.cbt.banks.store'), [
            'title' => 'Bank Persiapan Matematika',
            'subject' => 'Matematika',
            'guru_id' => $guru->id,
        ])->assertRedirect(route('admin.cbt.banks.index'));

        $this->actingAs($admin)->put(route('admin.cbt.banks.update', $bank), [
            'title' => 'Bank Diperbarui',
            'subject' => 'Matematika',
            'guru_id' => $guru->id,
        ])->assertRedirect(route('admin.cbt.banks.index'));

        // Sesi, Hasil, Analisis, Ekspor: terkunci 404 termasuk untuk admin
        $sessionId = E2eFixtureSeeder::CBT_SESSION_ID;
        $sessionUrls = [
            route('admin.cbt.sessions.index'),
            route('admin.cbt.sessions.create'),
            route('admin.cbt.sessions.show', $sessionId),
            route('admin.cbt.sessions.analisis-butir', $sessionId),
            route('admin.cbt.sessions.export', ['session' => $sessionId, 'format' => 'csv']),
            route('admin.cbt.sessions.export-engine', ['session' => $sessionId, 'format' => 'csv']),
        ];

        foreach ($sessionUrls as $url) {
            $this->actingAs($admin)->get($url)->assertNotFound();
        }
    }

    public function test_non_admin_roles_and_direct_super_admin_cannot_access_admin_cbt_when_flag_false(): void
    {
        $sessionId = E2eFixtureSeeder::CBT_SESSION_ID;
        $adminCbtUrls = [
            route('admin.cbt.banks.index'),
            route('admin.cbt.sessions.index'),
            route('admin.cbt.sessions.analisis-butir', $sessionId),
            route('admin.cbt.sessions.export', ['session' => $sessionId, 'format' => 'csv']),
        ];

        foreach ($adminCbtUrls as $url) {
            $status = $this->get($url)->getStatusCode();
            $this->assertContains($status, [302, 404]);
        }

        $extraUsers = [
            User::findOrFail(E2eFixtureSeeder::USER_IDS['guru_a']),
            User::findOrFail(E2eFixtureSeeder::USER_IDS['siswa_a1']),
            User::findOrFail(E2eFixtureSeeder::USER_IDS['ortu_a1']),
            User::factory()->create(['peran' => 'kepsek', 'aktif' => true, 'force_password_change' => false]),
            User::factory()->create(['peran' => 'tendik', 'aktif' => true, 'force_password_change' => false]),
            User::factory()->create(['peran' => 'super_admin', 'tenant_id' => null, 'aktif' => true, 'force_password_change' => false]),
        ];

        foreach ($extraUsers as $user) {
            foreach ($adminCbtUrls as $url) {
                $this->actingAs($user)->get($url)->assertNotFound();
            }
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

    public function test_admin_can_access_both_banks_and_sessions_when_flag_true(): void
    {
        config(['simantap.cbt_enabled' => true]);
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);

        $this->actingAs($admin)->get(route('admin.cbt.banks.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.cbt.sessions.index'))->assertOk();
    }

    public function test_cbt_bank_rejects_non_guru_or_other_tenant_guru_id(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);
        $siswa = User::findOrFail(E2eFixtureSeeder::USER_IDS['siswa_a1']);

        // Non-guru (siswa) harus ditolak 422 (validasi redirect/session errors atau JSON 422)
        $this->actingAs($admin)
            ->postJson(route('admin.cbt.banks.store'), [
                'title' => 'Bank Tidak Valid',
                'subject' => 'IPA',
                'guru_id' => $siswa->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('guru_id');

        // Guru dari tenant lain harus ditolak 422 saat TenantContext aktif
        $tenantA = Tenant::create([
            'npsn' => '10101010',
            'nama_sekolah' => 'Sekolah A',
            'subdomain' => 'sekolah-a-cbt',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'npsn' => '20202020',
            'nama_sekolah' => 'Sekolah B',
            'subdomain' => 'sekolah-b-cbt',
            'aktif' => true,
        ]);

        $adminTenantA = User::factory()->create([
            'peran' => 'admin',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guruTenantB = User::factory()->create([
            'peran' => 'guru',
            'tenant_id' => $tenantB->id,
            'tenant_owned' => true,
            'aktif' => true,
            'force_password_change' => false,
        ]);

        TenantContext::run($tenantA->id, function () use ($adminTenantA, $guruTenantB) {
            $this->actingAs($adminTenantA)
                ->postJson(route('admin.cbt.banks.store'), [
                    'title' => 'Bank Lintas Tenant',
                    'subject' => 'IPS',
                    'guru_id' => $guruTenantB->id,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('guru_id');
        });
    }
}
