<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TkaStatistikService
{
    private string $baseUrl = 'https://tka.kemendikdasmen.go.id';
    private int $cacheMinutes = 360; // 6 hours

    /**
     * Fetch all TKA statistics and cache them.
     */
    public function fetchAll(): array
    {
        try {
            $provinsi = $this->post('/api/v1/provinsi');
            $pie = $this->post('/api/v1/pie');
            $statistikJenjang = $this->post('/api/v1/statistik/jenjang');
            $statistikPeserta = $this->post('/api/v1/statistik/peserta');
            $statistikWilayah = $this->post('/api/v1/statistik/wilayah');

            $data = [
                'provinsi' => $provinsi,
                'pie' => $pie,
                'jenjang' => $statistikJenjang,
                'peserta' => $statistikPeserta,
                'wilayah' => $statistikWilayah,
                'last_update' => now()->toDateTimeString(),
            ];

            Cache::put('tka_statistik', $data, now()->addMinutes($this->cacheMinutes));

            return $data;
        } catch (\Exception $e) {
            Log::error('TKA fetch failed: ' . $e->getMessage());
            return Cache::get('tka_statistik', [
                'error' => true,
                'message' => 'Gagal mengambil data TKA',
                'last_update' => null,
            ]);
        }
    }

    /**
     * Get TKA statistics, fetching them only when the cache is actually empty.
     */
    public function get(): array
    {
        $cached = Cache::get('tka_statistik');

        return $cached ?? $this->fetchAll();
    }

    /**
     * Read the cached snapshot without making a request to the external TKA API.
     * This is used by the public landing page, which should remain available even
     * when the external service is slow or unreachable.
     */
    public function getCached(): array
    {
        return Cache::get('tka_statistik', [
            'error' => true,
            'message' => 'Data TKA belum tersedia.',
            'last_update' => null,
        ]);
    }

    /**
     * Force refresh TKA statistics.
     */
    public function refresh(): array
    {
        Cache::forget('tka_statistik');
        return $this->fetchAll();
    }

    /**
     * Get bar chart data for a specific province and jenjang.
     */
    public function getChart(string $type, ?string $kdProp = null, ?string $kdJenjang = null): ?array
    {
        $data = $this->get();
        if (isset($data['error'])) return null;

        return $this->post('/api/v1/chart', [
            'kd_prop' => $kdProp,
            'kd_jenjang' => $kdJenjang,
        ]);
    }

    private function post(string $path, array $payload = []): mixed
    {
        $response = Http::timeout(15)
            ->withoutVerifying()
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post($this->baseUrl . $path, $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \RuntimeException("TKA API error: {$response->status()} on {$path}");
    }
}
