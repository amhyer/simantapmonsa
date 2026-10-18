<?php

namespace App\Services\Presensi;

use App\Models\PresensiLokasi;

class GeofenceService
{
    /** Radius bumi rata-rata dalam meter. */
    private const EARTH_RADIUS_METERS = 6_371_000;

    /**
     * Mengembalikan lokasi terdekat yang valid, atau null jika koordinat tidak
     * berada di dalam geofence aktif / akurasi GPS tidak mencukupi.
     */
    public function lokasiCocok(float $latitude, float $longitude, float $accuracy): ?PresensiLokasi
    {
        return PresensiLokasi::query()
            ->where('aktif', true)
            ->get()
            ->filter(function (PresensiLokasi $location) use ($accuracy, $latitude, $longitude): bool {
                if ($accuracy > $location->akurasi_maksimal_meter) {
                    return false;
                }

                return $this->jarakMeter($latitude, $longitude, $location->latitude, $location->longitude)
                    <= $location->radius_meter;
            })
            ->sortBy(fn (PresensiLokasi $location) => $this->jarakMeter(
                $latitude,
                $longitude,
                $location->latitude,
                $location->longitude,
            ))
            ->first();
    }

    public function jarakMeter(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $lat1 = deg2rad($latitude1);
        $lat2 = deg2rad($latitude2);
        $deltaLat = deg2rad($latitude2 - $latitude1);
        $deltaLng = deg2rad($longitude2 - $longitude1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
