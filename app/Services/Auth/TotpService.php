<?php

namespace App\Services\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

/**
 * Implementasi TOTP RFC 6238 (HMAC-SHA1, 6 digit, interval 30 detik)
 * beserta generator QR SVG internal dan recovery codes sekali pakai.
 */
class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const PERIOD = 30;

    public const DIGITS = 6;

    /**
     * Hasilkan secret acak dalam format Base32 (default 160-bit / 32 karakter).
     */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes(max(16, $bytes)));
    }

    /**
     * Bentuk URI otpauth://totp untuk dipindai aplikasi authenticator.
     */
    public function provisioningUri(string $issuer, string $accountName, string $secret): string
    {
        $label = rawurlencode($issuer.':'.$accountName);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * Render QR code dalam format SVG murni (100% self-hosted via bacon/bacon-qr-code).
     */
    public function qrCodeSvg(string $uri, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($uri);
    }

    /**
     * Hitung kode TOTP 6 digit pada timestamp tertentu (default waktu sekarang).
     */
    public function codeAt(string $secret, ?int $timestamp = null): string
    {
        $time = $timestamp ?? time();
        $counter = (int) floor($time / self::PERIOD);

        return $this->hotp($secret, $counter);
    }

    /**
     * Verifikasi kode TOTP dengan toleransi clock drift (±$discrepancy window)
     * dan proteksi anti-replay menggunakan `$lastUsedWindow`.
     *
     * Mengembalikan nomor window yang cocok bila valid, atau null bila tidak valid.
     */
    public function verifyCode(
        string $secret,
        string $code,
        ?int $lastUsedWindow = null,
        int $discrepancy = 1,
        ?int $timestamp = null
    ): ?int {
        $normalized = preg_replace('/\s+/', '', trim($code));
        if (! is_string($normalized) || ! preg_match('/^\d{6}$/', $normalized)) {
            return null;
        }

        $time = $timestamp ?? time();
        $currentWindow = (int) floor($time / self::PERIOD);

        for ($offset = -$discrepancy; $offset <= $discrepancy; $offset++) {
            $window = $currentWindow + $offset;
            if ($window < 0) {
                continue;
            }
            if ($lastUsedWindow !== null && $window <= $lastUsedWindow) {
                continue;
            }

            $expected = $this->hotp($secret, $window);
            if (hash_equals($expected, $normalized)) {
                return $window;
            }
        }

        return null;
    }

    /**
     * Hasilkan daftar recovery codes sekali pakai beserta hash SHA-256-nya.
     *
     * @return array{plain: list<string>, hashes: list<string>}
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashes = [];
        $alphabetLen = strlen(self::RECOVERY_ALPHABET);

        for ($i = 0; $i < $count; $i++) {
            $raw = '';
            for ($j = 0; $j < 8; $j++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, $alphabetLen - 1)];
            }
            $formatted = substr($raw, 0, 4).'-'.substr($raw, 4, 4);
            $plain[] = $formatted;
            $hashes[] = $this->hashRecoveryCode($formatted);
        }

        return [
            'plain' => $plain,
            'hashes' => $hashes,
        ];
    }

    /**
     * Verifikasi dan konsumsi satu recovery code secara constant-time.
     * Mengembalikan array sisa hash bila cocok, atau null bila tidak cocok.
     *
     * @param  array<int, string>  $storedHashes
     * @return list<string>|null
     */
    public function consumeRecoveryCode(array $storedHashes, string $input): ?array
    {
        $candidateHash = $this->hashRecoveryCode($input);
        $matchedIndex = null;

        foreach ($storedHashes as $index => $hash) {
            if (is_string($hash) && hash_equals($hash, $candidateHash)) {
                $matchedIndex = $index;
            }
        }

        if ($matchedIndex === null) {
            return null;
        }

        unset($storedHashes[$matchedIndex]);

        return array_values($storedHashes);
    }

    private function hashRecoveryCode(string $code): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($code)));

        return hash('sha256', $normalized);
    }

    private function hotp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $secret): string
    {
        $clean = strtoupper(rtrim(preg_replace('/\s+/', '', $secret) ?? '', '='));
        if ($clean === '') {
            throw new InvalidArgumentException('Secret Base32 tidak boleh kosong.');
        }

        $binary = '';
        $length = strlen($clean);
        for ($i = 0; $i < $length; $i++) {
            $pos = strpos(self::BASE32_ALPHABET, $clean[$i]);
            if ($pos === false) {
                throw new InvalidArgumentException('Secret Base32 mengandung karakter tidak valid.');
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }
}
