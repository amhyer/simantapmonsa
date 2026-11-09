<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\PresensiMesinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class PresensiMesinSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_perintah_sync_mengimpor_scan_ke_presensi(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nik' => '7309082109930003']);

        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('baca')->once()->andReturn([
                ['nik' => '7309082109930003', 'waktu' => Carbon::parse('2026-06-01 06:55:00'), 'jenis' => 'masuk'],
                ['nik' => '7309082109930003', 'waktu' => Carbon::parse('2026-06-01 14:10:00'), 'jenis' => 'pulang'],
            ]);
        });

        $this->artisan('presensi:mesin-sync', ['--dari' => '2026-06-01', '--sampai' => '2026-06-02'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('presensi_pegawai', [
            'user_id' => $guru->id,
            'metode' => 'mesin',
        ]);
    }

    public function test_perintah_sync_mengembalikan_gagal_jika_baca_database_gagal(): void
    {
        $this->mock(AttendanceDatabaseReader::class, function ($mock) {
            $mock->shouldReceive('baca')->once()->andThrow(new RuntimeException('Database terkunci.'));
        });

        $this->artisan('presensi:mesin-sync', ['--dari' => '2026-06-01', '--sampai' => '2026-06-02'])
            ->assertExitCode(1);
    }

    public function test_scheduler_menjalankan_perintah_sync_bukan_closure(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'presensi:mesin-sync'));

        $this->assertNotEmpty($events, 'presensi:mesin-sync harus terdaftar di scheduler.');
        $this->assertTrue(
            $events->contains(fn ($event) => str_contains((string) $event->command, 'artisan')),
            'Scheduler harus memakai command() agar artisan presensi:mesin-sync benar-benar dijalankan.',
        );
    }
}
