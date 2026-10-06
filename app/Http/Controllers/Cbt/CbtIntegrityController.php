<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\TestResult;
use App\Models\Cbt\IntegrityLog;
use Illuminate\Http\Request;

class CbtIntegrityController extends Controller
{
    public function logViolation(Request $request, TestResult $result)
    {
        $validated = $request->validate([
            'jenis_pelanggaran' => 'required|in:tab_switch,copy_paste,screen_capture,focus_lost,multiple_tabs,suspicious_activity',
            'deskripsi' => 'nullable|string',
            'data_tambahan' => 'nullable|array',
        ]);

        IntegrityLog::create([
            'test_result_id' => $result->id,
            'siswa_id' => $result->siswa_id,
            'jenis_pelanggaran' => $validated['jenis_pelanggaran'],
            'timestamp' => now(),
            'deskripsi' => $validated['deskripsi'] ?? null,
            'data_tambahan' => $validated['data_tambahan'] ?? null,
        ]);

        // Update integrity score
        $violations = $result->integrityLogs()->count();
        $score = max(0, 100 - ($violations * 10)); // 10 points per violation

        $result->update([
            'integrity_score' => $score,
            'integrity_violations' => $result->integrity_violations ?? [],
        ]);

        return response()->json(['success' => true, 'integrity_score' => $score]);
    }

    public function getViolations(TestResult $result)
    {
        $violations = $result->integrityLogs()->latest()->get();

        return response()->json(['violations' => $violations]);
    }
}
