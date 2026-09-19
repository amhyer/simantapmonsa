<?php

namespace App\Services\Dapodik;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DapodikClient
{
    public function __construct(
        private string $npsn,
        private string $token,
        private string $host = 'localhost',
        private int $port = 5774,
        private string $protocol = 'http',
        private array $cfAccess = [],
    ) {}

    private function base(): string
    {
        $base = $this->protocol . '://' . $this->host;

        $defaultPort = $this->protocol === 'https' ? 443 : 80;
        if ($this->port !== $defaultPort) {
            $base .= ':' . $this->port;
        }

        return $base . '/WebService';
    }

    private function requestRaw(string $endpoint, array $params = []): mixed
    {
        $url = $this->base() . '/' . $endpoint;
        $maxAttempts = 3;
        $delays = [0, 400, 1200];

        $accessDeniedPatterns = ['/akses ditolak', '/access denied', '/unauthorized', '/forbidden'];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                usleep($delays[$attempt - 1] * 1000);
            }

            try {
                $request = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ]);

                foreach ($this->cfAccess as $key => $value) {
                    $request = $request->withHeader($key, $value);
                }

                $response = $request->get($url, array_merge(['npsn' => $this->npsn], $params));

                if ($response->successful()) {
                    $body = $response->body();
                    $decoded = json_decode($body, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        if ($attempt < $maxAttempts) {
                            continue;
                        }
                        throw new RuntimeException(
                            "Non-JSON response from {$endpoint} after {$maxAttempts} attempts"
                        );
                    }

                    if (isset($decoded['success']) && $decoded['success'] === false) {
                        $message = $decoded['message'] ?? $decoded['error'] ?? 'Unknown Dapodik API error';
                        throw new RuntimeException("Dapodik API error on {$endpoint}: {$message}");
                    }

                    return $decoded;
                }

                $status = $response->status();
                $body = $response->body();
                $lowerBody = mb_strtolower($body);

                $isAccessDenied = false;
                foreach ($accessDeniedPatterns as $pattern) {
                    if (str_contains($lowerBody, $pattern)) {
                        $isAccessDenied = true;
                        break;
                    }
                }

                if ($isAccessDenied) {
                    throw new RuntimeException(
                        "Access denied by Dapodik API on {$endpoint}: {$status}"
                    );
                }

                if ($status >= 400 && $status < 500) {
                    throw new RuntimeException(
                        "Client error from Dapodik API on {$endpoint}: HTTP {$status}"
                    );
                }

                if ($status >= 500) {
                    if ($attempt < $maxAttempts) {
                        continue;
                    }
                    throw new RuntimeException(
                        "Server error from Dapodik API on {$endpoint}: HTTP {$status}"
                    );
                }

                if ($attempt < $maxAttempts) {
                    continue;
                }

                throw new RuntimeException(
                    "Unexpected response from Dapodik API on {$endpoint}: HTTP {$status}"
                );
            } catch (RuntimeException $e) {
                throw $e;
            } catch (ConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Tidak dapat terhubung ke Dapodik WebService di {$this->protocol}://{$this->host}:{$this->port}. " .
                    "Pastikan aplikasi Dapodik berjalan dan WebService aktif. " .
                    "Error teknis: " . $e->getMessage(),
                    0,
                    $e
                );
            } catch (\Exception $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Request failed to Dapodik API on {$endpoint}: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        throw new RuntimeException("Request failed to Dapodik API on {$endpoint}: exhausted all retry attempts");
    }

    private function allPages(string $endpoint, array $extra = []): array
    {
        $allRows = [];
        $start = 0;
        $limit = 100;
        $prevKeys = [];

        while (true) {
            $params = array_merge($extra, [
                'limit' => $limit,
                'start' => $start,
            ]);

            $response = $this->requestRaw($endpoint, $params);

            $rows = $response['data'] ?? $response['rows'] ?? $response['records'] ?? [];

            if (!is_array($rows)) {
                $rows = [];
            }

            if (empty($rows)) {
                break;
            }

            $currentKeys = array_map(function ($row) {
                return $row['peserta_didik_id'] ?? $row['ptk_id'] ?? $row['rombongan_belajar_id'] ?? $row['sekolah_id'] ?? $row['mata_pelajaran_id'] ?? md5(serialize($row));
            }, $rows);

            if ($prevKeys === $currentKeys) {
                break;
            }

            $prevKeys = $currentKeys;
            $allRows = array_merge($allRows, $rows);
            $start += count($rows);

            $count = $response['count'] ?? $response['total'] ?? $response['recordsTotal'] ?? null;

            if ($count !== null && $start >= (int) $count) {
                break;
            }

            if (count($rows) < $limit) {
                break;
            }
        }

        return $allRows;
    }

    private function requestSingle(string $endpoint, array $params = []): array
    {
        $response = $this->requestRaw($endpoint, $params);

        $data = $response['data'] ?? $response['rows'] ?? $response;

        if (is_array($data) && !empty($data) && !isset($data[0])) {
            $data = [$data];
        }

        if (!is_array($data)) {
            $data = [$data];
        }

        return $data;
    }

    /**
     * POST request to Dapodik API
     */
    private function requestPost(string $endpoint, array $data = []): mixed
    {
        $url = $this->base() . '/' . $endpoint;
        $maxAttempts = 3;
        $delays = [0, 400, 1200];

        $accessDeniedPatterns = ['/akses ditolak', '/access denied', '/unauthorized', '/forbidden'];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                usleep($delays[$attempt - 1] * 1000);
            }

            try {
                $request = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ]);

                foreach ($this->cfAccess as $key => $value) {
                    $request = $request->withHeader($key, $value);
                }

                $response = $request->post($url, $data);

                if ($response->successful()) {
                    $body = $response->body();
                    $decoded = json_decode($body, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        if ($attempt < $maxAttempts) {
                            continue;
                        }
                        throw new RuntimeException(
                            "Non-JSON response from {$endpoint} after {$maxAttempts} attempts"
                        );
                    }

                    if (isset($decoded['success']) && $decoded['success'] === false) {
                        $message = $decoded['message'] ?? $decoded['error'] ?? 'Unknown Dapodik API error';
                        throw new RuntimeException("Dapodik API error on {$endpoint}: {$message}");
                    }

                    return $decoded;
                }

                $status = $response->status();
                $body = $response->body();
                $lowerBody = mb_strtolower($body);

                $isAccessDenied = false;
                foreach ($accessDeniedPatterns as $pattern) {
                    if (str_contains($lowerBody, $pattern)) {
                        $isAccessDenied = true;
                        break;
                    }
                }

                if ($isAccessDenied) {
                    throw new RuntimeException(
                        "Access denied by Dapodik API on {$endpoint}: {$status}"
                    );
                }

                if ($status >= 400 && $status < 500) {
                    throw new RuntimeException(
                        "Client error from Dapodik API on {$endpoint}: HTTP {$status}"
                    );
                }

                if ($status >= 500) {
                    if ($attempt < $maxAttempts) {
                        continue;
                    }
                    throw new RuntimeException(
                        "Server error from Dapodik API on {$endpoint}: HTTP {$status}"
                    );
                }

                if ($attempt < $maxAttempts) {
                    continue;
                }

                throw new RuntimeException(
                    "Unexpected response from Dapodik API on {$endpoint}: HTTP {$status}"
                );
            } catch (RuntimeException $e) {
                throw $e;
            } catch (ConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Tidak dapat terhubung ke Dapodik WebService di {$this->protocol}://{$this->host}:{$this->port}. " .
                    "Pastikan aplikasi Dapodik berjalan dan WebService aktif. " .
                    "Error teknis: " . $e->getMessage(),
                    0,
                    $e
                );
            } catch (\Exception $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Request failed to Dapodik API on {$endpoint}: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        throw new RuntimeException("Request failed to Dapodik API on {$endpoint}: exhausted all retry attempts");
    }

    /**
     * PUT request to Dapodik API
     */
    private function requestPut(string $endpoint, array $data = []): mixed
    {
        $url = $this->base() . '/' . $endpoint;
        $maxAttempts = 3;
        $delays = [0, 400, 1200];

        $accessDeniedPatterns = ['/akses ditolak', '/access denied', '/unauthorized', '/forbidden'];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                usleep($delays[$attempt - 1] * 1000);
            }

            try {
                $request = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ]);

                foreach ($this->cfAccess as $key => $value) {
                    $request = $request->withHeader($key, $value);
                }

                $response = $request->put($url, $data);

                if ($response->successful()) {
                    $body = $response->body();
                    $decoded = json_decode($body, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        if ($attempt < $maxAttempts) {
                            continue;
                        }
                        throw new RuntimeException(
                            "Non-JSON response from {$endpoint} after {$maxAttempts} attempts"
                        );
                    }

                    if (isset($decoded['success']) && $decoded['success'] === false) {
                        $message = $decoded['message'] ?? $decoded['error'] ?? 'Unknown Dapodik API error';
                        throw new RuntimeException("Dapodik API error on {$endpoint}: {$message}");
                    }

                    return $decoded;
                }

                $status = $response->status();
                $body = $response->body();
                $lowerBody = mb_strtolower($body);

                $isAccessDenied = false;
                foreach ($accessDeniedPatterns as $pattern) {
                    if (str_contains($lowerBody, $pattern)) {
                        $isAccessDenied = true;
                        break;
                    }
                }

                if ($isAccessDenied) {
                    throw new RuntimeException(
                        "Access denied by Dapodik API on {$endpoint}: {$status}"
                    );
                }

                if ($status >= 400 && $status < 500) {
                    throw new RuntimeException(
                        "Client error from Dapodik API on {$endpoint}: HTTP {$status}"
                    );
                }

                if ($status >= 500) {
                    if ($attempt < $maxAttempts) {
                        continue;
                    }
                    throw new RuntimeException(
                        "Server error from Dapodik API on {$endpoint}: HTTP {$status}"
                    );
                }

                if ($attempt < $maxAttempts) {
                    continue;
                }

                throw new RuntimeException(
                    "Unexpected response from Dapodik API on {$endpoint}: HTTP {$status}"
                );
            } catch (RuntimeException $e) {
                throw $e;
            } catch (ConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Tidak dapat terhubung ke Dapodik WebService di {$this->protocol}://{$this->host}:{$this->port}. " .
                    "Pastikan aplikasi Dapodik berjalan dan WebService aktif. " .
                    "Error teknis: " . $e->getMessage(),
                    0,
                    $e
                );
            } catch (\Exception $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Request failed to Dapodik API on {$endpoint}: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        throw new RuntimeException("Request failed to Dapodik API on {$endpoint}: exhausted all retry attempts");
    }

    public function getSekolah(): array
    {
        return $this->requestSingle('getSekolah');
    }

    public function getPesertaDidik(?string $semesterId = null): array
    {
        $extra = [];
        if ($semesterId) {
            $extra['semester_id'] = $semesterId;
            $extra['tahun_ajaran_id'] = semester_to_tahun_ajaran($semesterId);
        }

        return $this->allPages('getPesertaDidik', $extra);
    }

    public function getGtk(?string $semesterId = null): array
    {
        $extra = [];
        if ($semesterId) {
            $extra['semester_id'] = $semesterId;
            $extra['tahun_ajaran_id'] = semester_to_tahun_ajaran($semesterId);
        }

        return $this->allPages('getGtk', $extra);
    }

    public function getRombonganBelajar(?string $semesterId = null): array
    {
        $extra = [];
        if ($semesterId) {
            $extra['semester_id'] = $semesterId;
            $extra['tahun_ajaran_id'] = semester_to_tahun_ajaran($semesterId);
        }

        return $this->allPages('getRombonganBelajar', $extra);
    }

    public function getMataPelajaran(?string $semesterId = null): array
    {
        $extra = [];
        if ($semesterId) {
            $extra['semester_id'] = $semesterId;
            $extra['tahun_ajaran_id'] = semester_to_tahun_ajaran($semesterId);
        }

        return $this->allPages('getMataPelajaran', $extra);
    }

    public function getAllData(?string $semesterId = null): array
    {
        return [
            'sekolah' => $this->getSekolah(),
            'peserta_didik' => $this->getPesertaDidik($semesterId),
            'gtk' => $this->getGtk($semesterId),
            'rombongan_belajar' => $this->getRombonganBelajar($semesterId),
            'mata_pelajaran' => $this->getMataPelajaran($semesterId),
        ];
    }

    // ─── PUSH METHODS (Kirim data KE Dapodik) ──────────────────

    /**
     * Push sekolah data TO Dapodik
     */
    public function pushSekolah(array $data): array
    {
        return $this->requestPost('saveSekolah', $data);
    }

    /**
     * Push peserta didik data TO Dapodik
     */
    public function pushPesertaDidik(array $data): array
    {
        return $this->requestPost('savePesertaDidik', $data);
    }

    /**
     * Push GTK (Guru/Tendik) data TO Dapodik
     */
    public function pushGtk(array $data): array
    {
        return $this->requestPost('saveGtk', $data);
    }

    /**
     * Push rombongan belajar data TO Dapodik
     */
    public function pushRombonganBelajar(array $data): array
    {
        return $this->requestPost('saveRombonganBelajar', $data);
    }

    /**
     * Push jadwal pelajaran data TO Dapodik
     */
    public function pushJadwal(array $data): array
    {
        return $this->requestPost('saveJadwalPelajaran', $data);
    }

    /**
     * Push nilai rapor TO Dapodik
     */
    public function pushNilaiRapor(array $data): array
    {
        return $this->requestPost('saveNilaiRapor', $data);
    }

    /**
     * Push kehadiran data TO Dapodik
     */
    public function pushKehadiran(array $data): array
    {
        return $this->requestPost('saveKehadiran', $data);
    }

    public function getSemestersWithCounts(): array
    {
        $siswa = $this->getPesertaDidik();
        $rombel = $this->getRombonganBelajar();

        $semesterMap = [];

        foreach ($siswa as $s) {
            $semesterId = $s['semester_id'] ?? null;
            if ($semesterId === null) {
                continue;
            }

            if (!isset($semesterMap[$semesterId])) {
                $semesterMap[$semesterId] = [
                    'semester_id' => $semesterId,
                    'tahun_ajaran' => semester_to_tahun_ajaran($semesterId),
                    'jumlah_peserta_didik' => 0,
                    'jumlah_rombongan_belajar' => 0,
                ];
            }

            $semesterMap[$semesterId]['jumlah_peserta_didik']++;
        }

        foreach ($rombel as $r) {
            $semesterId = $r['semester_id'] ?? null;
            if ($semesterId === null) {
                continue;
            }

            if (!isset($semesterMap[$semesterId])) {
                $semesterMap[$semesterId] = [
                    'semester_id' => $semesterId,
                    'tahun_ajaran' => semester_to_tahun_ajaran($semesterId),
                    'jumlah_peserta_didik' => 0,
                    'jumlah_rombongan_belajar' => 0,
                ];
            }

            $semesterMap[$semesterId]['jumlah_rombongan_belajar']++;
        }

        usort($semesterMap, function ($a, $b) {
            return $b['semester_id'] <=> $a['semester_id'];
        });

        return array_values($semesterMap);
    }
}
