<?php

namespace Tests\Feature;

use App\Services\PresensiMesinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PresensiMesinJamKerjaTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_masuk_memakai_jam_default_sekolah(): void
    {
        $service = app(PresensiMesinService::class);

        $this->assertSame('07:15', $service->jamMasuk());
        $this->assertSame('07:15', $service->jamMasuk('sd'));

        $status = $service->statusMasuk([
            'nik' => '7309082109930003',
            'tanggal' => Carbon::parse('2026-06-01'),
            'masuk' => Carbon::parse('2026-06-01 07:15:00'),
            'pulang' => null,
        ]);

        $this->assertSame('hadir', $status);
    }

    public function test_status_masuk_memakai_jam_per_jenjang(): void
    {
        config(['presensi.mesin_jam_masuk_jenjang' => ['SMP' => '08:00', 'SD' => '09:00']]);
        $service = app(PresensiMesinService::class);

        // SMP: batas 08:00, jam 07:50 -> masih awal (hadir).
        $this->assertSame(
            'hadir',
            $service->statusMasuk([
                'nik' => '7309082109930003',
                'tanggal' => Carbon::parse('2026-06-01'),
                'masuk' => Carbon::parse('2026-06-01 07:50:00'),
                'pulang' => null,
            ], 'SMP'),
        );

        // SD: batas 09:00, jam 08:30 -> belum terlambat.
        $this->assertSame(
            'hadir',
            $service->statusMasuk([
                'nik' => '7309082109930003',
                'tanggal' => Carbon::parse('2026-06-01'),
                'masuk' => Carbon::parse('2026-06-01 08:30:00'),
                'pulang' => null,
            ], 'SD'),
        );

        // SMP: batas 08:00, jam 08:30 -> masuk lebih lambat dari batas -> terlambat.
        $this->assertSame(
            'terlambat',
            $service->statusMasuk([
                'nik' => '7309082109930003',
                'tanggal' => Carbon::parse('2026-06-01'),
                'masuk' => Carbon::parse('2026-06-01 08:30:00'),
                'pulang' => null,
            ], 'SMP'),
        );
    }
}
