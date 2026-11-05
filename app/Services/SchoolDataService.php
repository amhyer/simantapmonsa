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
            // 1. Coba API Publik Referensi Kemendikdasmen (Fast Indonesia Open Data)
            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => 'SIMANTAP/2.0',
                    ])
                    ->get("https://api-sekolah-indonesia.vercel.app/sekolah", [
                        'npsn' => $npsn,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if (!empty($json['dataSekolah']) && is_array($json['dataSekolah'])) {
                        $item = $json['dataSekolah'][0];
                        return [
                            'npsn' => trim($item['npsn'] ?? $npsn),
                            'nama_sekolah' => trim($item['sekolah'] ?? ''),
                            'jenjang' => strtoupper(trim($item['bentuk'] ?? 'SD')),
                            'status' => $this->normalizeStatus($item['status'] ?? ''),
                            'alamat' => trim($item['alamat_jalan'] ?? ''),
                            'kecamatan' => trim($item['kecamatan'] ?? ''),
                            'kabupaten' => trim($item['kabupaten_kota'] ?? ''),
                            'provinsi' => trim($item['propinsi'] ?? ''),
                            'akreditasi' => trim($item['akreditasi'] ?? ''),
                            'updated_at' => now()->toIso8601String(),
                            'source' => 'Kemendikdasmen RI',
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::info("Kemendikdasmen API proxy timeout atau gagal: " . $e->getMessage());
            }

            // 2. Fallback API sekunder
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => 'SIMANTAP/2.0',
                    ])
                    ->get("https://api.fazriansyah.eu.org/v1/sekolah", [
                        'npsn' => $npsn,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'npsn' => $data['npsn'] ?? $npsn,
                        'nama_sekolah' => $data['nama_sekolah'] ?? $data['nama'] ?? '',
                        'jenjang' => strtoupper($data['bentuk'] ?? $data['jenjang'] ?? 'SD'),
                        'status' => $this->normalizeStatus($data['statusSatuanPendidikan'] ?? $data['status'] ?? ''),
                        'alamat' => $data['alamat'] ?? '',
                        'kecamatan' => $data['kecamatan'] ?? '',
                        'kabupaten' => $data['kabupaten'] ?? '',
                        'provinsi' => $data['provinsi'] ?? '',
                        'akreditasi' => $data['akreditasi'] ?? '',
                        'email' => $data['email'] ?? '',
                        'telepon' => $data['telepon'] ?? '',
                        'updated_at' => $data['updated_at'] ?? now()->toIso8601String(),
                        'source' => 'Kemendikdasmen Fallback',
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Fallback API sekolah NPSN {$npsn} gagal: " . $e->getMessage());
            }

            // 3. Fallback database lokal (jika sekolah pernah tersimpan)
            $local = \App\Models\SekolahSettings::where('npsn', $npsn)->first();
            if ($local) {
                return [
                    'npsn' => $local->npsn,
                    'nama_sekolah' => $local->nama_sekolah,
                    'jenjang' => $local->jenjang ?? 'SD',
                    'status' => $local->status,
                    'alamat' => $local->alamat ?? '',
                    'kecamatan' => $local->kecamatan ?? '',
                    'kabupaten' => $local->kota ?? '',
                    'provinsi' => $local->provinsi ?? '',
                    'akreditasi' => $local->akreditasi ?? '',
                    'email' => $local->email ?? '',
                    'telepon' => $local->telepon ?? '',
                    'source' => 'Database Lokal',
                ];
            }

            return null;
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
