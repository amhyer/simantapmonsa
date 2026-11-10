<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\MesinPresensi\PresensiMesinKaryawanService;
use App\Services\PresensiMesinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresensiMesinApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $apiKeyPlaintext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $keyGen = ApiKey::generate($this->admin->id, 'Bridge Presensi', ['presensi:import']);
        $this->apiKeyPlaintext = $keyGen['plaintext_key'];
    }

    public function test_sinkron_dengan_api_key_tanpa_ability_ditolak(): void
    {
        $keyGen = ApiKey::generate($this->admin->id, 'Bridge Dapodik', ['dapodik:import']);

        $response = $this->withHeaders([
            'X-API-Key' => $keyGen['plaintext_key'],
        ])->postJson('/api/presensi/mesin/sinkron', [
            'scans' => [
                ['nik' => '0000000002', 'waktu' => '2026-11-09T06:55:00', 'jenis' => 'masuk'],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_sinkron_dengan_payload_valid_membuat_presensi_mesin(): void
    {
        $this->mock(PresensiMesinService::class, function ($mock) {
            $mock->shouldReceive('normalizeNik')->andReturnUsing(
                fn ($value) => trim((string) preg_replace('/[^0-9A-Za-z]+/', '', (string) $value)),
            );
            $mock->shouldReceive('jenisScan')->andReturnUsing(
                fn ($tipe, $waktu) => ((string) $tipe) === 'pulang' ? 'pulang' : 'masuk',
            );
            $mock->shouldReceive('importScans')->once()->andReturn([
                'scan' => 2,
                'pegawai' => 1,
                'dibuat' => 1,
                'diperbarui' => 0,
                'tetap' => 0,
                'dilindungi' => 0,
                'nik_tidak_dikenal' => 0,
                'contoh_nik_tidak_dikenal' => [],
                'perubahan' => [],
            ]);
        });

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->postJson('/api/presensi/mesin/sinkron', [
            'pegawai' => [
                ['nik' => '0000000002', 'nama' => 'Amirullah, S.Pd.'],
            ],
            'scans' => [
                ['nik' => '0000000002', 'waktu' => '2026-11-09T06:55:00', 'jenis' => 'masuk'],
                ['nik' => '0000000002', 'waktu' => '2026-11-09T14:10:00', 'jenis' => 'pulang'],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'source' => 'presensi-bridge',
                'scan' => 2,
                'dibuat' => 1,
            ]);

        $this->assertDatabaseHas('presensi_mesin_sync_logs', [
            'source' => 'presensi-bridge',
            'status' => 'berhasil',
            'jumlah_scan' => 2,
            'dibuat' => 1,
            'nik_tidak_dikenal' => 0,
        ]);
    }

    public function test_sinkron_gagal_mencatat_log_status_gagal(): void
    {
        $this->mock(PresensiMesinService::class, function ($mock) {
            $mock->shouldReceive('normalizeNik')->andReturnUsing(
                fn ($value) => trim((string) preg_replace('/[^0-9A-Za-z]+/', '', (string) $value)),
            );
            $mock->shouldReceive('jenisScan')->andReturnUsing(
                fn ($tipe, $waktu) => ((string) $tipe) === 'pulang' ? 'pulang' : 'masuk',
            );
            $mock->shouldReceive('importScans')->once()->andThrow(new \RuntimeException('Database mesin gagal dibaca.'));
        });

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->postJson('/api/presensi/mesin/sinkron', [
            'scans' => [
                ['nik' => '0000000002', 'waktu' => '2026-11-09T06:55:00', 'jenis' => 'masuk'],
            ],
        ]);

        $response->assertStatus(500);

        $this->assertDatabaseHas('presensi_mesin_sync_logs', [
            'source' => 'presensi-bridge',
            'status' => 'gagal',
            'jumlah_scan' => 1,
        ]);
    }

    public function test_sinkron_dengan_payload_tidak_valid_ditolak(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->postJson('/api/presensi/mesin/sinkron', [
            'scans' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_status_bridge_berhasil(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->getJson('/api/presensi/mesin/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'source' => 'presensi-bridge',
                'status' => 'online',
            ]);
    }
}
