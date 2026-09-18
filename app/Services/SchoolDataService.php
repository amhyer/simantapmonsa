<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SchoolDataService
{
    public function getByNpsn(string $npsn): ?array
    {
        if (!preg_match('/^[0-9]{8}$/', $npsn)) {
            return null;
        }

        return Cache::remember("sekolah_npsn_{$npsn}", 86400, function () use ($npsn) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => 'SIMANTAP/1.0',
                    ])
                    ->get("https://api.fazriansyah.eu.org/v1/sekolah", [
                        'npsn' => $npsn,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'npsn' => $data['npsn'] ?? $npsn,
                        'nama_sekolah' => $data['nama_sekolah'] ?? $data['nama'] ?? '',
                        'status' => $this->normalizeStatus($data['statusSatuanPendidikan'] ?? $data['status'] ?? ''),
                        'alamat' => $data['alamat'] ?? '',
                        'kecamatan' => $data['kecamatan'] ?? '',
                        'kabupaten' => $data['kabupaten'] ?? '',
                        'provinsi' => $data['provinsi'] ?? '',
                        'akreditasi' => $data['akreditasi'] ?? '',
                        'email' => $data['email'] ?? '',
                        'telepon' => $data['telepon'] ?? '',
                        'updated_at' => $data['updated_at'] ?? now()->toIso8601String(),
                    ];
                }

                Log::warning("API sekolah NPSN {$npsn} tidak ditemukan", [
                    'status' => $response->status(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error("Gagal mengambil data sekolah NPSN {$npsn}: " . $e->getMessage());
                return null;
            }
        });
    }

    private function normalizeStatus(?string $status): string
    {
        if (empty($status)) return '';

        $status = strtoupper(trim($status));

        if (in_array($status, ['NEGERI', 'N', 'PUBLIC'])) return 'NEGERI';
        if (in_array($status, ['SWASTA', 'S', 'PRIVATE'])) return 'SWASTA';

        return $status;
    }
}
