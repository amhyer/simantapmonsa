<?php

namespace Tests\Unit;

use App\Services\Presensi\GeofenceService;
use PHPUnit\Framework\TestCase;

class GeofenceServiceTest extends TestCase
{
    public function test_menghitung_jarak_dalam_meter(): void
    {
        $service = new GeofenceService();
        $distance = $service->jarakMeter(-5.147665, 119.432732, -5.147665, 119.432732);

        $this->assertSame(0.0, $distance);
    }

    public function test_jarak_berubah_saat_koordinat_berbeda(): void
    {
        $service = new GeofenceService();
        $distance = $service->jarakMeter(-5.147665, 119.432732, -5.148565, 119.432732);

        $this->assertGreaterThan(90, $distance);
        $this->assertLessThan(110, $distance);
    }
}
