<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtResult;
use App\Models\Siswa;
use App\Services\CbtExamService;
use Illuminate\Http\Request;

class CbtIntegrityController extends Controller
{
    public function __construct(private CbtExamService $exam) {}

    // Telemetri bersifat indikasi, BUKAN bukti kecurangan atau alasan nilai nol.
    public function logViolation(Request $request, CbtResult $result)
    {
        abort_unless((int) $result->user_id === (int) auth()->id(), 403);
        $student = Siswa::find($result->siswa_id);
        abort_unless($student && $this->exam->eligible($result->session, $student), 403);
        $data = $request->validate(['type' => 'required|in:tab_hidden,fullscreen_exit']);
        $this->exam->recordViolation($result, $student, $data['type']);
        return response()->json(['recorded' => true]);
    }

    public function getViolations(CbtResult $result)
    {
        abort_unless((int) $result->session->guru_id === (int) auth()->id(), 403);
        return response()->json($result->violations()->latest('id')->paginate(50));
    }
}
