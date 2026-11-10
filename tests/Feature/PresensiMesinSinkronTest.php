<?php

namespace Tests\Feature;

use App\Models\PresensiMesinSyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PresensiMesinSinkronTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_melihat_dashboard_sinkron_mesin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.presensi.mesin.sinkron'))
            ->assertOk()
            ->assertSee('Riwayat Sinkron Mesin');
    }

    public function test_dashboard_sinkron_menampilkan_hasil_sinkron_terakhir(): void
    {
        $admin = User::factory()->admin()->create();

        PresensiMesinSyncLog::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'source' => 'presensi-bridge',
            'status' => 'berhasil',
            'jumlah_scan' => 3,
            'dibuat' => 1,
            'diperbarui' => 2,
            'tetap' => 0,
            'dilindungi' => 0,
            'nik_tidak_dikenal' => 0,
            'contoh_nik_tidak_dikenal' => ['0000000011'],
            'started_at' => now(),
            'finished_at' => now(),
            'duration_seconds' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.presensi.mesin.sinkron'))
            ->assertOk()
            ->assertSee('Berhasil')
            ->assertSee('presensi-bridge');
    }

    public function test_admin_tidak_dapat_melihat_dashboard_sinkron_sebagai_guru(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)
            ->get(route('admin.presensi.mesin.sinkron'))
            ->assertForbidden();
    }
}
