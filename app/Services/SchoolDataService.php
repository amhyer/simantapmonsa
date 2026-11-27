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
                'jabatan_kepala_sekolah' => '',
                'operator' => '',
                'jabatan_operator' => '',
                'email' => '',
                'telepon' => '',
                'kelurahan' => '',
                'kodepos' => '',
                'updated_at' => now()->toIso8601String(),
                'source' => 'Kemendikdasmen RI',
            ];

            $foundOfficial = false;

            // 1. Portal Resmi Dapodik Kemendikdasmen RI (Tarik Kepala Sekolah & Identitas Utama)
            // Bila DAPODIK_TOKEN tidak disetel, coba tarik token baca publik dari env.js portal dapo.
            $tokenMissing = trim((string) config('dapodik.token', '')) === '';

            try {
                $token = $this->resolveDapodikLookupToken();
                if ($token === '') {
                    throw new DapodikTokenMissingException('Token Dapodik belum dikonfigurasi.');
                }
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
                        $data['kelurahan'] = trim($item['desa_kelurahan'] ?? '');
                        $data['kecamatan'] = trim($item['kecamatan'] ?? '');
                        $data['kabupaten'] = trim($item['kabupaten'] ?? '');
                        $data['provinsi'] = trim($item['provinsi'] ?? '');
                        $data['kodepos'] = trim((string) ($item['kode_pos'] ?? ''));
                        $data['akreditasi'] = trim($item['akreditasi'] ?? '');
                        $kepsekRaw = trim($item['nama_kepsek'] ?? '');
                        if ($kepsekRaw !== '') {
                            [$namaKepsek, $jabatanKepsek] = $this->extractKepsekAndJabatan($kepsekRaw, $item['jabatan_kepsek'] ?? null);
                            $data['kepala_sekolah'] = $namaKepsek;
                            $data['jabatan_kepala_sekolah'] = $jabatanKepsek;
                        }
                        $operatorRaw = trim($item['nama_operator'] ?? $item['operator'] ?? '');
                        if ($operatorRaw !== '') {
                            $data['operator'] = $operatorRaw;
                            $data['jabatan_operator'] = trim($item['jabatan_operator'] ?? '') ?: 'Operator Sekolah (OPS)';
                        }
                        $foundOfficial = true;
                    }
                }
            } catch (DapodikTokenMissingException $e) {
                Log::info('Lookup NPSN: token Dapodik kosong, memakai sumber publik.');
            } catch (\Exception $e) {
                Log::info('Dapodik Kemendikdasmen API timeout atau gagal: '.$e->getMessage());
            }

            // 2. Portal Data Referensi Kemendikdasmen (halaman publik, tanpa token):
            //    /satpen/detail/{npsn} memuat identitas, alamat, kontak, dan koordinat.
            try {
                $response = Http::timeout(8)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    ])
                    ->get("https://referensi.data.kemendikdasmen.go.id/satpen/detail/{$npsn}");

                if ($response->successful()) {
                    $ref = $this->parseReferensiDetail($response->body(), $npsn);
                    if ($ref !== null) {
                        // Data referensi menjadi dasar; kepala sekolah dari Dapodik (jika ada) dipertahankan.
                        foreach ($ref as $key => $value) {
                            if ($value !== '' && (($data[$key] ?? '') === '' || in_array($key, ['email', 'telepon', 'kodepos', 'kelurahan'], true))) {
                                $data[$key] = $value;
                            }
                        }
                        if ($data['kepala_sekolah'] !== '' && $data['jabatan_kepala_sekolah'] === '') {
                            [$data['kepala_sekolah'], $data['jabatan_kepala_sekolah']] = $this->extractKepsekAndJabatan($data['kepala_sekolah']);
                        }
                        if ($data['jabatan_operator'] === '') {
                            $data['jabatan_operator'] = 'Operator Sekolah (OPS)';
                        }
                        $data['jenjang'] = $ref['jenjang'] ?: $data['jenjang'];
                        $foundOfficial = true;
                    }
                }
            } catch (\Exception $e) {
                Log::info('Referensi Kemendikdasmen gagal: '.$e->getMessage());
            }

            if ($foundOfficial && ! empty($data['nama_sekolah'])) {
                if ($data['kepala_sekolah'] !== '' && $data['jabatan_kepala_sekolah'] === '') {
                    $data['jabatan_kepala_sekolah'] = 'Kepala Sekolah';
                }
                if ($data['jabatan_operator'] === '') {
                    $data['jabatan_operator'] = 'Operator Sekolah (OPS)';
                }

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
                        $kepsekRaw = trim($item['kepala_sekolah'] ?? $item['kepsek'] ?? $item['nama_kepala_sekolah'] ?? '');
                        [$namaKepsek, $jabatanKepsek] = $kepsekRaw !== ''
                            ? $this->extractKepsekAndJabatan($kepsekRaw)
                            : ['', ''];

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
                            'kepala_sekolah' => $namaKepsek,
                            'jabatan_kepala_sekolah' => $jabatanKepsek,
                            'operator' => trim($item['operator'] ?? ''),
                            'jabatan_operator' => 'Operator Sekolah (OPS)',
                            'email' => '',
                            'telepon' => '',
                            'kelurahan' => '',
                            'kodepos' => '',
                            'updated_at' => now()->toIso8601String(),
                            'source' => 'Kemendikdasmen Open Data',
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::info('Kemendikdasmen Open Data fallback gagal: '.$e->getMessage());
            }

            // 4. Fallback database lokal (hanya identitas publik sekolah, tanpa kontak internal)
            $local = SekolahSettings::where('npsn', $npsn)->first();
            if ($local) {
                $pengaturan = is_array($local->pengaturan) ? $local->pengaturan : [];
                $kepsekRaw = trim((string) ($local->kepala_sekolah ?? ''));
                [$namaKepsek, $jabatanKepsek] = $kepsekRaw !== ''
                    ? $this->extractKepsekAndJabatan($kepsekRaw, $pengaturan['jabatan_kepala_sekolah'] ?? null)
                    : ['', ''];

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
                    'kepala_sekolah' => $namaKepsek,
                    'jabatan_kepala_sekolah' => $jabatanKepsek,
                    'operator' => trim((string) ($pengaturan['nama_operator'] ?? '')),
                    'jabatan_operator' => trim((string) ($pengaturan['jabatan_operator'] ?? '')) ?: 'Operator Sekolah (OPS)',
                    'email' => '',
                    'telepon' => '',
                    'kelurahan' => '',
                    'kodepos' => $local->kode_pos ?? '',
                    'source' => 'Database Lokal',
                ];
            }

            if ($tokenMissing) {
                throw new DapodikTokenMissingException('Token Dapodik belum dikonfigurasi.');
            }

            return null;
        });
    }

    /**
     * Parse halaman publik /satpen/detail/{npsn} (blok .detail-row label/value).
     * Mengembalikan null bila halaman tidak memuat data sekolah untuk NPSN tsb.
     */
    private function parseReferensiDetail(string $html, string $npsn): ?array
    {
        if (! preg_match_all('/<div class="detail-row">\s*<div class="detail-label">(.*?)<\/div>\s*<div class="detail-value">(.*?)<\/div>/is', $html, $rows, PREG_SET_ORDER)) {
            return null;
        }

        $f = [];
        foreach ($rows as $row) {
            $label = strtolower(trim(html_entity_decode(strip_tags($row[1]))));
            $value = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($row[2]))));
            $value = trim(str_replace('↗', '', $value));
            $f[$label] = $value === '-' ? '' : $value;
        }

        $nama = $f['nama'] ?? '';
        if ($nama === '' || preg_replace('/\D/', '', $f['npsn'] ?? '') !== $npsn) {
            return null;
        }

        $email = $f['email'] ?? '';
        $kepsekRaw = $f['kepala sekolah'] ?? $f['nama kepala sekolah'] ?? '';
        [$namaKepsek, $jabatanKepsek] = $kepsekRaw !== ''
            ? $this->extractKepsekAndJabatan($kepsekRaw, $f['jabatan kepala sekolah'] ?? null)
            : ['', ''];
        $operatorRaw = $f['operator'] ?? $f['operator sekolah'] ?? $f['ops'] ?? '';

        return [
            'nama_sekolah' => $nama,
            'jenjang' => strtoupper($f['bentuk pendidikan'] ?? ''),
            'status' => $this->normalizeStatus($f['status sekolah'] ?? ''),
            'alamat' => $f['alamat'] ?? '',
            'kelurahan' => $f['desa/kelurahan'] ?? '',
            'kecamatan' => $f['kecamatan'] ?? '',
            'kabupaten' => $f['kabupaten/kota'] ?? '',
            'provinsi' => $f['provinsi'] ?? '',
            'kodepos' => $f['kode pos'] ?? '',
            'kepala_sekolah' => $namaKepsek,
            'jabatan_kepala_sekolah' => $jabatanKepsek,
            'operator' => $operatorRaw,
            'jabatan_operator' => $f['jabatan operator'] ?? ($operatorRaw !== '' ? 'Operator Sekolah (OPS)' : ''),
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            'telepon' => $f['telepon'] ?? '',
        ];
    }

    /**
     * Ambil token Dapodik dari config, atau tarik token baca publik dari env.js portal dapo.
     */
    private function resolveDapodikLookupToken(): string
    {
        $configured = trim((string) config('dapodik.token', ''));
        if ($configured !== '') {
            return $configured;
        }

        try {
            $resp = Http::timeout(4)
                ->withHeaders(['User-Agent' => 'SIMANTAP/2.0 (Mozilla/5.0)'])
                ->get('https://dapo.kemendikdasmen.go.id/env.js');

            if ($resp->successful() && preg_match('/VITE_API_TOKEN\s*:\s*["\']([a-f0-9]{32,})["\']/i', $resp->body(), $m)) {
                return $m[1];
            }
        } catch (\Exception $e) {
            Log::info('Gagal menarik public VITE_API_TOKEN dari dapo env.js: '.$e->getMessage());
        }

        return '';
    }

    /**
     * Ekstrak nama Kepala Sekolah dan jabatannya (Kepala Sekolah / Plt. Kepala Sekolah / Pjs. Kepala Sekolah).
     *
     * @return array{0: string, 1: string}
     */
    private function extractKepsekAndJabatan(string $rawName, ?string $explicitJabatan = null): array
    {
        $name = trim($rawName);
        $jabatan = trim((string) $explicitJabatan);

        if (preg_match('/^(plt\.?|pjs\.?|plh\.?|pelaksana\s+tugas)\s+(.+)$/i', $name, $m)) {
            $prefix = strtolower(rtrim($m[1], '.'));
            $name = trim($m[2]);
            if ($jabatan === '') {
                $jabatan = ($prefix === 'pjs') ? 'Pjs. Kepala Sekolah' : 'Plt. Kepala Sekolah';
            }
        } elseif (preg_match('/\((plt\.?|pjs\.?|plh\.?)\)/i', $name, $m)) {
            $prefix = strtolower(rtrim($m[1], '.'));
            $name = trim(preg_replace('/\((plt\.?|pjs\.?|plh\.?)\)/i', '', $name));
            if ($jabatan === '') {
                $jabatan = ($prefix === 'pjs') ? 'Pjs. Kepala Sekolah' : 'Plt. Kepala Sekolah';
            }
        }

        if ($jabatan === '') {
            $jabatan = 'Kepala Sekolah';
        }

        return [$name, $jabatan];
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
