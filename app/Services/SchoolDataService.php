<?php

namespace App\Services;

use App\Exceptions\DapodikTokenMissingException;
use App\Models\SekolahSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SchoolDataService
{
    public function getByNpsn(string $npsn): ?array
    {
        if (! preg_match('/^[0-9]{8}$/', $npsn)) {
            return null;
        }

        return Cache::remember("sekolah_npsn_v3_{$npsn}", 86400, function () use ($npsn) {
            $data = [
                'npsn' => $npsn,
                'nama_sekolah' => '',
                'jenjang' => 'SD',
                'status' => '',
                'alamat' => '',
                'kecamatan' => '',
                'kabupaten' => '',
                'provinsi' => '',
                'akreditasi' => '',
                'kepala_sekolah' => '',
                'operator' => '',
                'email' => '',
                'telepon' => '',
                'updated_at' => now()->toIso8601String(),
                'source' => 'Kemendikdasmen RI',
            ];

            $foundOfficial = false;

            // 1. Portal Resmi Dapodik Kemendikdasmen RI (Tarik Kepala Sekolah & Identitas Utama)
            try {
                $token = $this->getDapodikToken();
                $response = Http::timeout(6)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/json',
                        'User-Agent' => 'SIMANTAP/2.0 (Mozilla/5.0)',
                    ])
                    ->get('https://dapo.kemendikdasmen.go.id/api/detail-sekolah', [
                        'npsn' => $npsn,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if (! empty($json['data'][0])) {
                        $item = $json['data'][0];
                        $data['nama_sekolah'] = trim($item['nama'] ?? '');
                        $data['jenjang'] = strtoupper(trim($item['bentuk_pendidikan'] ?? $item['jenjang'] ?? 'SD'));
                        $data['status'] = $this->normalizeStatus($item['status_sekolah'] ?? '');
                        $data['alamat'] = trim($item['alamat_jalan'] ?? '');
                        $data['kecamatan'] = trim($item['kecamatan'] ?? '');
                        $data['kabupaten'] = trim($item['kabupaten'] ?? '');
                        $data['provinsi'] = trim($item['provinsi'] ?? '');
                        $data['akreditasi'] = trim($item['akreditasi'] ?? '');
                        $data['kepala_sekolah'] = trim($item['nama_kepsek'] ?? '');
                        $foundOfficial = true;
                    }
                }
            } catch (DapodikTokenMissingException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::info('Dapodik Kemendikdasmen API timeout atau gagal: '.$e->getMessage());
            }

            // 2. Data Referensi Kemendikdasmen (Tarik Operator Sekolah & Kontak)
            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    ])
                    ->get("https://referensi.data.kemendikdasmen.go.id/pendidikan/npsn/{$npsn}");

                if ($response->successful()) {
                    $html = $response->body();
                    if (preg_match('/Operator\s*<\/td>\s*<td[^>]*>:\s*<\/td>\s*<td[^>]*>(.*?)<\/td>/is', $html, $m)) {
                        $data['operator'] = trim(strip_tags($m[1]));
                    }
                    if (preg_match('/Email\s*<\/td>\s*<td[^>]*>:\s*<\/td>\s*<td[^>]*>(.*?)<\/td>/is', $html, $m)) {
                        $cleanEmail = trim(strip_tags($m[1]));
                        if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                            $data['email'] = $cleanEmail;
                        }
                    }
                    if (preg_match('/Telepon\s*<\/td>\s*<td[^>]*>:\s*<\/td>\s*<td[^>]*>(.*?)<\/td>/is', $html, $m)) {
                        $telp = trim(strip_tags($m[1]));
                        if ($telp !== '-' && ! empty($telp)) {
                            $data['telepon'] = $telp;
                        }
                    }
                    if (empty($data['nama_sekolah']) && preg_match('/Nama\s*<\/td>\s*<td[^>]*>:\s*<\/td>\s*<td[^>]*>(.*?)<\/td>/is', $html, $m)) {
                        $data['nama_sekolah'] = trim(strip_tags($m[1]));
                        $foundOfficial = true;
                    }
                }
            } catch (\Exception $e) {
                Log::info('Referensi Kemendikdasmen scrap timeout atau gagal: '.$e->getMessage());
            }

            if ($foundOfficial && ! empty($data['nama_sekolah'])) {
                return $data;
            }

            // 3. Fallback Open Data API Indonesia jika Dapodik Pusat lambat/sibuk
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => 'SIMANTAP/2.0',
                    ])
                    ->get('https://api-sekolah-indonesia.vercel.app/sekolah', [
                        'npsn' => $npsn,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if (! empty($json['dataSekolah']) && is_array($json['dataSekolah'])) {
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
                            'kepala_sekolah' => trim($item['kepala_sekolah'] ?? $item['kepsek'] ?? $item['nama_kepala_sekolah'] ?? ''),
                            'operator' => '',
                            'email' => '',
                            'telepon' => '',
                            'updated_at' => now()->toIso8601String(),
                            'source' => 'Kemendikdasmen Open Data',
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::info('Kemendikdasmen Open Data fallback gagal: '.$e->getMessage());
            }

            // 4. Fallback database lokal (jika sekolah pernah tersimpan)
            $local = SekolahSettings::where('npsn', $npsn)->first();
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
                    'kepala_sekolah' => $local->kepala_sekolah ?? '',
                    'operator' => $local->guru?->nama_lengkap ?? '',
                    'email' => $local->email ?? '',
                    'telepon' => $local->telepon ?? '',
                    'source' => 'Database Lokal',
                ];
            }

            return null;
        });
    }

    private function getDapodikToken(): string
    {
        $token = trim((string) config('dapodik.token', ''));
        if ($token === '') {
            throw new DapodikTokenMissingException('Token Dapodik belum dikonfigurasi.');
        }

        return $token;
    }

    private function normalizeStatus(?string $status): string
    {
        if (empty($status)) {
            return '';
        }

        $status = strtoupper(trim($status));

        if (in_array($status, ['NEGERI', 'N', 'PUBLIC'])) {
            return 'NEGERI';
        }
        if (in_array($status, ['SWASTA', 'S', 'PRIVATE'])) {
            return 'SWASTA';
        }

        return $status;
    }
}
