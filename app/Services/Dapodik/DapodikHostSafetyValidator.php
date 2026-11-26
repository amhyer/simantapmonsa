<?php

namespace App\Services\Dapodik;

class DapodikHostSafetyValidator
{
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

    /**
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

    public static function validate(string $host, bool $multiSchool = false): ?string
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

        return self::$dnsResolutionCache[$normalizedHost] = array_values(array_unique($ips));
    }

    private static function extractIpv4MappedFromIpv6(string $packedV6): ?string
    {
        if (strlen($packedV6) !== 16) {
            return null;
        }

        if (substr($packedV6, 0, 12) === str_repeat("\x00", 10)."\xff\xff") {
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

            return ($unsigned & 0xFF000000) === 0x00000000
                || ($unsigned & 0xFFFF0000) === 0xA9FE0000
                || $ip === '100.100.100.200';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = @inet_pton($ip);
            if ($packed === false || strlen($packed) !== 16) {
                return true;
            }

            if ($packed === str_repeat("\x00", 16)) {
                return true;
            }

            $awsMetadata = @inet_pton('fd00:ec2::254');
            if ($awsMetadata !== false && $packed === $awsMetadata) {
                return true;
            }

            $b0 = ord($packed[0]);
            $b1 = ord($packed[1]);
            if ($b0 === 0xFE && ($b1 & 0xC0) === 0x80) {
                return true;
            }

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

            return ($unsigned & 0xFF000000) === 0x7F000000;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = @inet_pton($ip);
            if ($packed === false || strlen($packed) !== 16) {
                return false;
            }

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

        return (ord($packed[0]) & 0xFE) === 0xFC;
    }
}
