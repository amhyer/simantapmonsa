<?php

namespace Tests\Feature;

use App\Services\MesinPresensi\AttendanceDatabaseReader;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PresensiMesinBridgeTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-17 15:00:00', config('app.timezone')));

        $this->statePath = storage_path('framework/testing/presensi-bridge-'.uniqid().'.json');
        @mkdir(dirname($this->statePath), 0775, true);

        config([
            'presensi.bridge_url' => 'https://simantap.test',
            'presensi.bridge_key' => 'kunci-uji',
            'presensi.bridge_state_path' => $this->statePath,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if (is_file($this->statePath)) {
            @unlink($this->statePath);
        }

        parent::tearDown();
    }

    /**
     * @param  list<array{nik: string, waktu: Carbon, jenis: string}>  $scans
     */
    private function mockReader(array $scans): void
    {
        $this->mock(AttendanceDatabaseReader::class, function ($mock) use ($scans) {
            $mock->shouldReceive('baca')->once()->andReturn($scans);
        });
    }

    /**
     * @return array{nik: string, waktu: Carbon, jenis: string}
     */
    private function scan(string $nik, string $waktu, string $jenis = 'masuk'): array
    {
        return [
            'nik' => $nik,
            'waktu' => Carbon::parse($waktu, config('app.timezone')),
            'jenis' => $jenis,
        ];
    }

    private function fakeSukses(): void
    {
        Http::fake([
            'https://simantap.test/api/presensi/mesin/sinkron' => Http::response([
                'success' => true,
                'scan' => 1,
                'dibuat' => 1,
                'diperbarui' => 0,
                'tetap' => 0,
                'dilindungi' => 0,
                'nik_tidak_dikenal' => 0,
            ], 200),
        ]);
    }

    public function test_mengirim_scan_dengan_header_dan_payload_yang_benar(): void
    {
        $this->mockReader([
            $this->scan('0000000014', '2026-11-17 06:29:44', 'masuk'),
            $this->scan('0000000014', '2026-11-17 14:05:10', 'pulang'),
        ]);
        $this->fakeSukses();

        $this->artisan('presensi:mesin-bridge', [
            '--dari' => '2026-11-17',
            '--sampai' => '2026-11-17',
        ])->assertExitCode(0);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://simantap.test/api/presensi/mesin/sinkron'
                && $request->hasHeader('X-API-Key', 'kunci-uji')
                && count($data['scans']) === 2
                && $data['scans'][0] === [
                    'nik' => '0000000014',
                    'waktu' => '2026-11-17 06:29:44',
                    'jenis' => 'masuk',
                ]
                && $data['scans'][1]['jenis'] === 'pulang';
        });

        Http::assertSentCount(1);
    }

    public function test_dry_run_tidak_mengirim_apa_pun(): void
    {
        $this->mockReader([
            $this->scan('0000000014', '2026-11-17 06:29:44'),
        ]);
        Http::fake();
        Http::preventStrayRequests();

        $this->artisan('presensi:mesin-bridge', [
            '--dari' => '2026-11-17',
            '--sampai' => '2026-11-17',
            '--dry-run' => true,
        ])->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertFileDoesNotExist($this->statePath);
    }

    public function test_rentang_sepuluh_hari_dikirim_dalam_dua_permintaan(): void
    {
        $scans = [];
        for ($hari = 1; $hari <= 10; $hari++) {
            $scans[] = $this->scan('0000000014', sprintf('2026-11-%02d 06:30:00', $hari));
        }

        $this->mockReader($scans);
        $this->fakeSukses();

        $this->artisan('presensi:mesin-bridge', [
            '--dari' => '2026-11-01',
            '--sampai' => '2026-11-10',
        ])->assertExitCode(0);

        Http::assertSentCount(2);

        $terkirim = [];
        Http::assertSent(function (Request $request) use (&$terkirim): bool {
            $tanggal = array_map(
                fn (array $scan): string => substr((string) $scan['waktu'], 0, 10),
                $request->data()['scans'],
            );
            $terkirim[] = $tanggal;

            return true;
        });

        $this->assertSame('2026-11-01', $terkirim[0][0]);
        $this->assertSame('2026-11-07', $terkirim[0][6]);
        $this->assertCount(7, $terkirim[0]);
        $this->assertSame('2026-11-08', $terkirim[1][0]);
        $this->assertSame('2026-11-10', $terkirim[1][2]);
        $this->assertCount(3, $terkirim[1]);
    }

    public function test_scan_setelah_tengah_malam_ikut_dikirim_untuk_hari_sebelumnya(): void
    {
        $this->mockReader([
            $this->scan('0000000014', '2026-11-17 06:30:00', 'masuk'),
            $this->scan('0000000014', '2026-11-18 00:30:00', 'pulang'),
        ]);
        $this->fakeSukses();

        $this->artisan('presensi:mesin-bridge', [
            '--dari' => '2026-11-17',
            '--sampai' => '2026-11-17',
        ])->assertExitCode(0);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return count($data['scans']) === 2
                && $data['scans'][1]['waktu'] === '2026-11-18 00:30:00';
        });
    }

    public function test_sukses_memajukan_state_otomatis(): void
    {
        $this->mockReader([
            $this->scan('0000000014', '2026-11-17 06:30:00'),
        ]);
        $this->fakeSukses();

        $this->artisan('presensi:mesin-bridge')->assertExitCode(0);

        $this->assertFileExists($this->statePath);

        $state = json_decode((string) file_get_contents($this->statePath), true);
        $this->assertSame('2026-11-17 15:00:00', $state['terakhir_sukses']);
        $this->assertSame('ok', $state['pesan_terakhir']);
        $this->assertSame('2026-11-11', $state['rentang_terakhir']['dari']);
        $this->assertSame('2026-11-17', $state['rentang_terakhir']['sampai']);
    }

    public function test_respons_gagal_tidak_memajukan_state(): void
    {
        file_put_contents($this->statePath, json_encode([
            'terakhir_sukses' => '2026-11-10 06:00:00',
            'pesan_terakhir' => 'ok',
        ]));

        $this->mockReader([
            $this->scan('0000000014', '2026-11-16 06:30:00'),
        ]);
        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Server sedang sibuk.'], 500),
        ]);

        $this->artisan('presensi:mesin-bridge')->assertExitCode(1);

        $state = json_decode((string) file_get_contents($this->statePath), true);
        $this->assertSame('2026-11-10 06:00:00', $state['terakhir_sukses']);
        $this->assertStringStartsWith('gagal:', $state['pesan_terakhir']);
        $this->assertSame('2026-11-17 15:00:00', $state['terakhir_jalan']);
    }

    public function test_rentang_manual_tidak_menggeser_state(): void
    {
        $this->mockReader([
            $this->scan('0000000014', '2026-11-05 06:30:00'),
        ]);
        $this->fakeSukses();

        $this->artisan('presensi:mesin-bridge', [
            '--dari' => '2026-11-05',
            '--sampai' => '2026-11-05',
        ])->assertExitCode(0);

        $this->assertFileDoesNotExist($this->statePath);
    }
}
