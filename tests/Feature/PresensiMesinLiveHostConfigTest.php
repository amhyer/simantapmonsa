<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\PresensiMesinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresensiMesinLiveHostConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'peran' => 'admin',
            'aktif' => true,
            'force_password_change' => false,
        ]);
    }

    public function test_default_tcp_host_is_empty_and_live_timeout_is_at_most_3_seconds(): void
    {
        $rawConfig = require config_path('presensi.php');

        // Saat env SIMANTAP_MESIN_HOST / PRESENSI_MESIN_DB_TCP_HOST tidak diset, default harus kosong (bukan IP hardcode).
        $defaultHost = env('SIMANTAP_MESIN_HOST', env('PRESENSI_MESIN_DB_TCP_HOST', ''));
        if ($defaultHost === '') {
            $this->assertSame('', $rawConfig['mesin_db_tcp_host']);
        }
        $this->assertStringNotContainsString(
            '192.168.1.7',
            file_get_contents(config_path('presensi.php')),
            'IP LAN 192.168.1.7 tidak boleh di-hardcode di config/presensi.php.'
        );
        $this->assertLessThanOrEqual(3, (int) $rawConfig['mesin_db_live_timeout']);
    }

    public function test_live_endpoint_does_not_open_socket_when_host_is_empty(): void
    {
        config()->set('presensi.mesin_db_engine', 'tcp');
        config()->set('presensi.mesin_db_tcp_host', '');
        config()->set('presensi.mesin_db_live_timeout', 3);

        $socketDipanggil = false;
        $this->app->bind(AttendanceDatabaseReader::class, function ($app) use (&$socketDipanggil) {
            return new class($app->make(PresensiMesinService::class), $socketDipanggil) extends AttendanceDatabaseReader
            {
                private bool $flag;

                public function __construct(PresensiMesinService $presensi, bool &$flag)
                {
                    parent::__construct($presensi);
                    $this->flag = &$flag;
                }

                protected function openTcpSocket(string $host, int $port, float $timeout, int &$errno, string &$errstr)
                {
                    $this->flag = true;

                    return false;
                }
            };
        });

        $admin = $this->admin();

        $res = $this->actingAs($admin)->getJson(route('admin.presensi.mesin.live'));
        $res->assertOk()
            ->assertJson([
                'ok' => false,
                'scans' => [],
            ]);
        $this->assertStringContainsString(
            'Mesin tidak dikonfigurasi; gunakan jalur Bridge',
            (string) $res->json('pesan')
        );
        $this->assertFalse($socketDipanggil, 'fsockopen tidak boleh dipanggil saat host mesin kosong.');
    }

    public function test_tarik_endpoint_returns_bridge_message_without_opening_socket_when_host_is_empty(): void
    {
        config()->set('presensi.mesin_db_engine', 'tcp');
        config()->set('presensi.mesin_db_tcp_host', '');

        $socketDipanggil = false;
        $this->app->bind(AttendanceDatabaseReader::class, function ($app) use (&$socketDipanggil) {
            return new class($app->make(PresensiMesinService::class), $socketDipanggil) extends AttendanceDatabaseReader
            {
                private bool $flag;

                public function __construct(PresensiMesinService $presensi, bool &$flag)
                {
                    parent::__construct($presensi);
                    $this->flag = &$flag;
                }

                protected function openTcpSocket(string $host, int $port, float $timeout, int &$errno, string &$errstr)
                {
                    $this->flag = true;

                    return false;
                }
            };
        });

        $admin = $this->admin();

        $res = $this->actingAs($admin)->post(route('admin.presensi.mesin.tarik'), [
            'dari' => '2026-10-01',
            'sampai' => '2026-10-05',
        ]);

        $res->assertRedirect();
        $res->assertSessionHasErrors('mesin');
        $this->assertStringContainsString(
            'Mesin tidak dikonfigurasi; gunakan jalur Bridge',
            (string) session('errors')->first('mesin')
        );
        $this->assertFalse($socketDipanggil, 'fsockopen tidak boleh dipanggil saat host mesin kosong.');
    }
}
