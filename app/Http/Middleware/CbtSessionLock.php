<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Cbt\TestResult;

class CbtSessionLock
{
    public function handle(Request $request, Closure $next)
    {
        $testResultId = $request->route('result');

        if ($testResultId) {
            $result = TestResult::find($testResultId);

            if ($result && !$result->waktu_selesai) {
                // Check if IP matches
                if ($result->ip_address !== $request->ip()) {
                    return response()->json([
                        'error' => 'Ujian hanya dapat diakses dari IP yang sama'
                    ], 403);
                }

                // Check if user agent matches (basic session security)
                if ($result->user_agent !== $request->userAgent()) {
                    return response()->json([
                        'error' => 'Session tidak valid - browser berbeda'
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
