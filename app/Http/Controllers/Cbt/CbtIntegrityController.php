<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\StoreCbtIntegrityViolationRequest;
use App\Models\CbtResult;
use App\Models\Siswa;
use App\Services\CbtExamService;

class CbtIntegrityController extends Controller
{
    public function __construct(private CbtExamService $exam) {}

    // Telemetri bersifat indikasi, BUKAN bukti kecurangan atau alasan nilai nol.
    public function logViolation(StoreCbtIntegrityViolationRequest $request, CbtResult $result)
    {
        $this->authorize('recordViolation', $result);
        $student = Siswa::find($result->siswa_id);
        abort_unless($student && $this->exam->eligible($result->session, $student), 403);
        $data = $request->validated();
        $this->exam->recordViolation($result, $student, $data['type']);
        return response()->json(['recorded' => true]);
    }

    public function getViolations(CbtResult $result)
    {
        $this->authorize('viewViolations', $result);
        return response()->json($result->violations()->latest('id')->paginate(50));
    }
}
