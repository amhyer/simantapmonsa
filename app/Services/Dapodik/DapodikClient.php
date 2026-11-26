<?php

namespace App\Services\Dapodik;

use App\Modules\Tenant\Support\TenantMode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DapodikClient
{
    private ?string $validatedBaseUrl = null;

    /**
     * Cache hasil resolusi DNS per host selama proses/job berjalan agar
     * sinkronisasi ratusan halaman tidak mengulang blocking DNS lookup.
     *
     * @var array<string, list<string>>
     */
    private static array $dnsResolutionCache = [];

    /**
     * Resolver kustom (opsional) untuk pengujian deterministik IPv4/IPv6 tanpa DNS nyata.
     *
     * @var (\Closure(string): list<string>)|null
     */
    private static ?\Closure $dnsResolver = null;

    public function __construct(
        private string $npsn,
        private string $token,
        private string $host = 'localhost',
        private int $port = 5774,
        private string $protocol = 'http',
        private array $cfAccess = [],
    ) {}

    /**
     * Pasang atau reset DNS resolver kustom (khusus pengujian) sekaligus membersihkan cache DNS.
     *
     * @param  (\Closure(string): list<string>)|null  $resolver
     */
    public static function useDnsResolver(?\Closure $resolver): void
    {
        self::$dnsResolver = $resolver;
        self::$dnsResolutionCache = [];
    }

    public static function clearDnsCache(): void
    {
        self::$dnsResolutionCache = [];
    }

    public static function validateHostSafety(string $host, bool $multiSchool = false): ?string
    {
        $raw = strtolower(trim($host));
        if ($raw === '') {
            return 'Host Dapodik harus berupa nama host atau alamat IP yang valid.';
        }

        // Dukung literal IPv6 ber tanda kurung siku seperti [::1] atau [2001:db8::1]
        $unbracketed = (str_starts_with($raw, '[') && str_ends_with($raw, ']'))
            ? substr($raw, 1, -1)
            : $raw;

        $isIpLiteral = filter_var($unbracketed, FILTER_VALIDATE_IP) !== false;
        if (! $isIpLiteral && ! preg_match('/^[a-z0-9.-]+$/', $raw)) {
            return 'Host Dapodik harus berupa nama host atau alamat IP yang valid.';
        }

        $normalized = $isIpLiteral ? $unbracketed : $raw;

        $metadataHosts = [
            'metadata.google.internal',
            'metadata.goog',
            'instance-data',
            '100.100.100.200',
            'fd00:ec2::254',
        ];
        if (in_array($normalized, $metadataHosts, true)) {
            return 'Host metadata cloud tidak diizinkan.';
        }

        $allowLoopback = (bool) (
            config('dapodik.allow_loopback_in_multi_school', false)
            || config('dapodik.allow_private_host', false)
        );

        if ($multiSchool && ! $allowLoopback) {
            if ($normalized === 'localhost' || str_ends_with($normalized, '.localhost') || $normalized === 'localhost.localdomain') {
                return 'Host localhost tidak diizinkan sebagai host Dapodik pada mode multi-sekolah. Isi alamat IP publik/domain server sekolah atau gunakan SIMANTAP Bridge.';
            }
        }

        $ips = $isIpLiteral ? [$normalized] : self::resolveHostIps($normalized);

        foreach ($ips as $ip) {
            if (self::isLinkLocalOrUnspecifiedOrMetadataIp($ip)) {
                return 'Alamat IP link-local/metadata tidak diizinkan sebagai host Dapodik.';
            }
            if ($multiSchool && ! $allowLoopback && (self::isLoopbackIp($ip) || self::isUniqueLocalIpv6($ip))) {
                return 'Alamat loopback/internal IPv6 tidak diizinkan sebagai host Dapodik pada mode multi-sekolah.';
            }
        }

        return null;
    }

    /**
     * Resolve catatan A (IPv4) dan AAAA (IPv6) dengan memoization per host.
     *
     * @return list<string>
     */
    private static function resolveHostIps(string $normalizedHost): array
    {
        if (isset(self::$dnsResolutionCache[$normalizedHost])) {
            return self::$dnsResolutionCache[$normalizedHost];
        }

        if (self::$dnsResolver !== null) {
            $resolved = array_values(array_unique(array_filter(
                (self::$dnsResolver)($normalizedHost),
                fn (mixed $ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false
            )));

            return self::$dnsResolutionCache[$normalizedHost] = $resolved;
        }

        if (app()->runningUnitTests()) {
            return self::$dnsResolutionCache[$normalizedHost] = [];
        }

        $ips = [];
        $v4 = @gethostbynamel($normalizedHost);
        if (is_array($v4)) {
            foreach ($v4 as $ip) {
                if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    $ips[] = $ip;
                }
            }
        }

        if (function_exists('dns_get_record')) {
            $v6Records = @dns_get_record($normalizedHost, DNS_AAAA);
            if (is_array($v6Records)) {
                foreach ($v6Records as $record) {
                    $ipv6 = $record['ipv6'] ?? null;
                    if (is_string($ipv6) && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                        $ips[] = $ipv6;
                    }
                }
            }
        }

        $ips = array_values(array_unique($ips));

        return self::$dnsResolutionCache[$normalizedHost] = $ips;
    }

    private static function extractIpv4MappedFromIpv6(string $packedV6): ?string
    {
        if (strlen($packedV6) !== 16) {
            return null;
        }

        // ::ffff:0:0/96 (IPv4-mapped IPv6) atau ::0:0/96 (IPv4-compatible deprecated, kecuali :: dan ::1)
        $prefix12 = substr($packedV6, 0, 12);
        if ($prefix12 === str_repeat("\x00", 10)."\xff\xff") {
            $v4 = @inet_ntop(substr($packedV6, 12, 4));

            return $v4 !== false ? $v4 : null;
        }

        return null;
    }

    private static function isLinkLocalOrUnspecifiedOrMetadataIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }
            $unsigned = (int) sprintf('%u', $long);

            // 0.0.0.0/8, 169.254.0.0/16 (AWS/GCP/Azure metadata), dan 100.100.100.200 (Alibaba metadata)
            return ($unsigned & 0xFF000000) === 0x00000000
                || ($unsigned & 0xFFFF0000) === 0xA9FE0000
                || $ip === '100.100.100.200';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = @inet_pton($ip);
            if ($packed === false || strlen($packed) !== 16) {
                return true;
            }

            // Unspecified ::/128
            if ($packed === str_repeat("\x00", 16)) {
                return true;
            }

            // AWS IMDSv2 IPv6 metadata (fd00:ec2::254)
            $awsMetadata = @inet_pton('fd00:ec2::254');
            if ($awsMetadata !== false && $packed === $awsMetadata) {
                return true;
            }

            // Link-local fe80::/10 (10 bit pertama = 11111110 10xxxxxx)
            $b0 = ord($packed[0]);
            $b1 = ord($packed[1]);
            if ($b0 === 0xFE && ($b1 & 0xC0) === 0x80) {
                return true;
            }

            // IPv4-mapped IPv6 (mis. ::ffff:169.254.169.254 atau ::ffff:0.0.0.0)
            $mappedV4 = self::extractIpv4MappedFromIpv6($packed);
            if ($mappedV4 !== null) {
                return self::isLinkLocalOrUnspecifiedOrMetadataIp($mappedV4);
            }
        }

        return false;
    }

    private static function isLoopbackIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);
            if ($long === false) {
                return false;
            }
            $unsigned = (int) sprintf('%u', $long);

            // 127.0.0.0/8
            return ($unsigned & 0xFF000000) === 0x7F000000;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = @inet_pton($ip);
            if ($packed === false || strlen($packed) !== 16) {
                return false;
            }

            // ::1/128
            if ($packed === str_repeat("\x00", 15)."\x01") {
                return true;
            }

            $mappedV4 = self::extractIpv4MappedFromIpv6($packed);
            if ($mappedV4 !== null) {
                return self::isLoopbackIp($mappedV4);
            }
        }

        return false;
    }

    private static function isUniqueLocalIpv6(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }

        // fc00::/7 (byte pertama 0xFC atau 0xFD)
        return (ord($packed[0]) & 0xFE) === 0xFC;
    }

    private function base(): string
    {
        if ($this->validatedBaseUrl !== null) {
            return $this->validatedBaseUrl;
        }

        $trimmedHost = trim($this->host);
        if ($trimmedHost === '') {
            throw new RuntimeException('Host Dapodik belum dikonfigurasi. Silakan atur host WebService Dapodik atau gunakan aplikasi SIMANTAP Bridge.');
        }

        // Pertahanan berlapis: host dari DB/config tetap divalidasi satu kali per instance
        // (hostname/IP polos tanpa skema/path/@ serta bukan loopback/metadata) sebelum dipakai membangun URL.
        $hostError = self::validateHostSafety($trimmedHost, TenantMode::isMultiSchool());
        if ($hostError !== null) {
            throw new RuntimeException($hostError);
        }

        $unbracketed = (str_starts_with($trimmedHost, '[') && str_ends_with($trimmedHost, ']'))
            ? substr($trimmedHost, 1, -1)
            : $trimmedHost;

        $formattedHost = filter_var($unbracketed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? '['.$unbracketed.']'
            : $trimmedHost;

        $base = $this->protocol.'://'.$formattedHost;

        $defaultPort = $this->protocol === 'https' ? 443 : 80;
        if ($this->port !== $defaultPort) {
            $base .= ':'.$this->port;
        }

        return $this->validatedBaseUrl = $base.'/WebService';
    }

    private function requestRaw(string $endpoint, array $params = []): mixed
    {
        $url = $this->base().'/'.$endpoint;
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
                        'Authorization' => 'Bearer '.$this->token,
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
                            'Sekolah Anda tidak ditemukan. Data tidak ditemukan di Dapodik. Periksa NPSN, token, dan pastikan WebService Dapodik aktif.'
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
                    "Tidak dapat terhubung ke Dapodik WebService di {$this->protocol}://{$this->host}:{$this->port}. ".
                    'Pastikan aplikasi Dapodik berjalan dan WebService aktif. '.
                    'Error teknis: '.$e->getMessage(),
                    0,
                    $e
                );
            } catch (\Exception $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Request failed to Dapodik API on {$endpoint}: ".$e->getMessage(),
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

            if (! is_array($rows)) {
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

        if (is_array($data) && ! empty($data) && ! isset($data[0])) {
            $data = [$data];
        }

        if (! is_array($data)) {
            $data = [$data];
        }

        return $data;
    }

    /**
     * POST request to Dapodik API
     */
    private function requestPost(string $endpoint, array $data = []): mixed
    {
        $url = $this->base().'/'.$endpoint;
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
                        'Authorization' => 'Bearer '.$this->token,
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
                            'Sekolah Anda tidak ditemukan. Data tidak ditemukan di Dapodik. Periksa NPSN, token, dan pastikan WebService Dapodik aktif.'
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
                    "Tidak dapat terhubung ke Dapodik WebService di {$this->protocol}://{$this->host}:{$this->port}. ".
                    'Pastikan aplikasi Dapodik berjalan dan WebService aktif. '.
                    'Error teknis: '.$e->getMessage(),
                    0,
                    $e
                );
            } catch (\Exception $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Request failed to Dapodik API on {$endpoint}: ".$e->getMessage(),
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
        $url = $this->base().'/'.$endpoint;
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
                        'Authorization' => 'Bearer '.$this->token,
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
                            'Sekolah Anda tidak ditemukan. Data tidak ditemukan di Dapodik. Periksa NPSN, token, dan pastikan WebService Dapodik aktif.'
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
                    "Tidak dapat terhubung ke Dapodik WebService di {$this->protocol}://{$this->host}:{$this->port}. ".
                    'Pastikan aplikasi Dapodik berjalan dan WebService aktif. '.
                    'Error teknis: '.$e->getMessage(),
                    0,
                    $e
                );
            } catch (\Exception $e) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                throw new RuntimeException(
                    "Request failed to Dapodik API on {$endpoint}: ".$e->getMessage(),
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

    public function getPembelajaran(?string $semesterId = null): array
    {
        $extra = [];
        if ($semesterId) {
            $extra['semester_id'] = $semesterId;
            $extra['tahun_ajaran_id'] = semester_to_tahun_ajaran($semesterId);
        }

        try {
            return $this->allPages('getPembelajaran', $extra);
        } catch (\Exception $e) {
            try {
                return $this->allPages('getJadwal', $extra);
            } catch (\Exception) {
                return [];
            }
        }
    }

    public function getAllData(?string $semesterId = null): array
    {
        return [
            'sekolah' => $this->getSekolah(),
            'peserta_didik' => $this->getPesertaDidik($semesterId),
            'gtk' => $this->getGtk($semesterId),
            'rombongan_belajar' => $this->getRombonganBelajar($semesterId),
            'mata_pelajaran' => $this->getMataPelajaran($semesterId),
            'pembelajaran' => $this->getPembelajaran($semesterId),
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

            if (! isset($semesterMap[$semesterId])) {
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

            if (! isset($semesterMap[$semesterId])) {
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
