<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Cbt\TestResult;
use App\Models\Cbt\IntegrityLog;

class CbtSessionLock
{
    public function handle(Request $request, Closure $next)
    {
        $testResultId = $request->route('result');

        if ($testResultId) {
            $result = TestResult::find($testResultId);

            if ($result && !$result->waktu_selesai) {
                // Check if IP matches (allow same /24 subnet for VPN/mobile switching)
                if (!$this->ipMatches($result->ip_address, $request->ip())) {
                    $this->logViolation($result, 'ip_mismatch', 'IP address tidak cocok', [
                        'expected_ip' => $result->ip_address,
                        'actual_ip' => $request->ip(),
                    ]);
                    return response()->json([
                        'error' => 'Ujian hanya dapat diakses dari IP yang sama (subnet /24)'
                    ], 403);
                }

                // Check if user agent matches (allow same browser family + major version)
                if (!$this->userAgentMatches($result->user_agent, $request->userAgent())) {
                    $this->logViolation($result, 'user_agent_mismatch', 'User-Agent tidak cocok', [
                        'expected_ua' => $result->user_agent,
                        'actual_ua' => $request->userAgent(),
                    ]);
                    return response()->json([
                        'error' => 'Session tidak valid - browser berbeda (family/major version)'
                    ], 403);
                }
            }
        }

        return $next($request);
    }

    protected function ipMatches(string $expectedIp, string $actualIp): bool
    {
        if ($expectedIp === $actualIp) {
            return true;
        }

        // Allow same /24 subnet (first 3 octets) for VPN/mobile network switching
        $expectedParts = explode('.', $expectedIp);
        $actualParts = explode('.', $actualIp);

        if (count($expectedParts) === 4 && count($actualParts) === 4) {
            return $expectedParts[0] === $actualParts[0]
                && $expectedParts[1] === $actualParts[1]
                && $expectedParts[2] === $actualParts[2];
        }

        return false;
    }

    protected function userAgentMatches(string $expectedUa, string $actualUa): bool
    {
        if ($expectedUa === $actualUa) {
            return true;
        }

        // Parse browser family and major version
        $expectedParsed = $this->parseUserAgent($expectedUa);
        $actualParsed = $this->parseUserAgent($actualUa);

        return $expectedParsed['family'] === $actualParsed['family']
            && $expectedParsed['major_version'] === $actualParsed['major_version'];
    }

    protected function parseUserAgent(string $ua): array
    {
        $family = 'Unknown';
        $majorVersion = '0';

        // Chrome
        if (preg_match('/Chrome\/(\d+)/', $ua, $matches)) {
            $family = 'Chrome';
            $majorVersion = $matches[1];
        }
        // Firefox
        elseif (preg_match('/Firefox\/(\d+)/', $ua, $matches)) {
            $family = 'Firefox';
            $majorVersion = $matches[1];
        }
        // Safari (not Chrome)
        elseif (preg_match('/Version\/(\d+).*Safari/', $ua, $matches) && !str_contains($ua, 'Chrome')) {
            $family = 'Safari';
            $majorVersion = $matches[1];
        }
        // Edge
        elseif (preg_match('/Edg\/(\d+)/', $ua, $matches)) {
            $family = 'Edge';
            $majorVersion = $matches[1];
        }
        // MSIE/Trident
        elseif (preg_match('/(?:MSIE |Trident\/.*rv:)(\d+)/', $ua, $matches)) {
            $family = 'IE';
            $majorVersion = $matches[1];
        }

        return [
            'family' => $family,
            'major_version' => $majorVersion,
        ];
    }

    protected function logViolation(TestResult $result, string $type, string $description, array $data): void
    {
        IntegrityLog::create([
            'test_result_id' => $result->id,
            'siswa_id' => $result->siswa_id,
            'jenis_pelanggaran' => $type,
            'timestamp' => now(),
            'deskripsi' => $description,
            'data_tambahan' => $data,
        ]);

        // Update integrity score
        $violations = $result->integrityLogs()->count();
        $score = max(0, 100 - ($violations * 10));

        $result->update([
            'integrity_score' => $score,
            'integrity_violations' => $result->integrity_violations ?? [],
        ]);
    }
}
