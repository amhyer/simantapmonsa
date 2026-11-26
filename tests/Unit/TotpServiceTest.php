<?php

namespace Tests\Unit;

use App\Services\Auth\TotpService;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_generate_secret_dan_provisioning_uri_serta_qr_svg(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);

        $uri = $totp->provisioningUri('SIMANTAP', 'superadmin@simantap.id', $secret);
        $this->assertStringStartsWith('otpauth://totp/SIMANTAP%3Asuperadmin%40simantap.id?', $uri);
        $this->assertStringContainsString("secret={$secret}", $uri);

        $svg = $totp->qrCodeSvg($uri);
        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_vektor_rfc6238_sha1_dan_toleransi_clock_drift_serta_anti_replay(): void
    {
        $totp = new TotpService;
        // RFC 6238 test secret ASCII "12345678901234567890" dalam Base32:
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        // Timestamp 59 -> RFC 6238 8-digit = 94287082 -> 6-digit = 287082
        $this->assertSame('287082', $totp->codeAt($secret, 59));

        // Verifikasi tepat waktu (window 1)
        $window = $totp->verifyCode($secret, '287082', null, 1, 59);
        $this->assertSame(1, $window);

        // Toleransi clock drift +1 window (timestamp 89 = window 2 masih terima kode window 1)
        $driftWindow = $totp->verifyCode($secret, '287082', null, 1, 89);
        $this->assertSame(1, $driftWindow);

        // Anti-replay: window 1 sudah dipakai -> ditolak
        $this->assertNull($totp->verifyCode($secret, '287082', 1, 1, 59));
        $this->assertNull($totp->verifyCode($secret, '287082', 1, 1, 89));

        // Kode salah -> ditolak
        $this->assertNull($totp->verifyCode($secret, '000000', null, 1, 59));
    }

    public function test_recovery_codes_sekali_pakai_dan_ter_hash(): void
    {
        $totp = new TotpService;
        $generated = $totp->generateRecoveryCodes(8);

        $this->assertCount(8, $generated['plain']);
        $this->assertCount(8, $generated['hashes']);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $generated['plain'][0]);
        $this->assertNotSame($generated['plain'][0], $generated['hashes'][0]);

        // Pakai kode pertama (tanpa tanda hubung / huruf kecil tetap cocok)
        $remaining = $totp->consumeRecoveryCode($generated['hashes'], strtolower(str_replace('-', '', $generated['plain'][0])));
        $this->assertIsArray($remaining);
        $this->assertCount(7, $remaining);

        // Pakai ulang kode yang sama -> ditolak
        $this->assertNull($totp->consumeRecoveryCode($remaining, $generated['plain'][0]));
    }
}
