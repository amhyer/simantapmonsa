<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Cbt\TestResult;

class CbtSecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $testResultId = $request->route('result');

        if ($testResultId) {
            $result = TestResult::find($testResultId);

            // Check if exam is still valid
            if (!$result || $result->waktu_selesai) {
                return response()->json(['error' => 'Ujian sudah selesai'], 403);
            }

            // Check if time has expired
            $session = $result->testSession;
            $timeElapsed = now()->diffInSeconds($result->waktu_mulai);
            $maxTime = $session->durasi_menit * 60;

            if ($timeElapsed > $maxTime) {
                // Auto-submit if time expired
                return $this->autoSubmit($result);
            }

            // Check integrity score
            if ($result->integrity_score < 50) {
                return response()->json(['error' => 'Ujian dikunci karena pelanggaran'], 403);
            }
        }

        return $next($request);
    }

    protected function autoSubmit(TestResult $result)
    {
        // Calculate current score and submit
        $controller = new \App\Http\Controllers\Cbt\CbtStudentController();
        return $controller->submit($result);
    }
}
