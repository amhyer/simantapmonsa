<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DapodikConfig;
use App\Services\Dapodik\DapodikPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DapodikPushController extends Controller
{
    public function __construct(private DapodikPushService $pushService)
    {
    }

    /**
     * Push data sekolah ke Dapodik
     */
    public function pushSekolah(): JsonResponse
    {
        $result = $this->pushService->pushSekolah();

        return response()->json([
            'success' => $result['gagal'] === 0,
            'message' => $result['gagal'] === 0 ? 'Push sekolah berhasil' : 'Push sekolah gagal',
            'data' => $result,
        ], $result['gagal'] === 0 ? 200 : 400);
    }

    /**
     * Push peserta didik ke Dapodik
     */
    public function pushPesertaDidik(Request $request): JsonResponse
    {
        $request->validate([
            'semester_id' => 'required|string|exists:semesters,semester_id',
        ]);

        $result = $this->pushService->pushPesertaDidik($request->input('semester_id'));

        return response()->json([
            'success' => $result['gagal'] === 0,
            'message' => $result['gagal'] === 0 ? 'Push peserta didik berhasil' : 'Push peserta didik gagal',
            'data' => $result,
        ], $result['gagal'] === 0 ? 200 : 400);
    }

    /**
     * Push GTK (Guru/Tendik) ke Dapodik
     */
    public function pushGtk(): JsonResponse
    {
        $result = $this->pushService->pushGtk();

        return response()->json([
            'success' => $result['gagal'] === 0,
            'message' => $result['gagal'] === 0 ? 'Push GTK berhasil' : 'Push GTK gagal',
            'data' => $result,
        ], $result['gagal'] === 0 ? 200 : 400);
    }

    /**
     * Push Rombel ke Dapodik
     */
    public function pushRombel(): JsonResponse
    {
        $result = $this->pushService->pushRombel();

        return response()->json([
            'success' => $result['gagal'] === 0,
            'message' => $result['gagal'] === 0 ? 'Push rombel berhasil' : 'Push rombel gagal',
            'data' => $result,
        ], $result['gagal'] === 0 ? 200 : 400);
    }

    /**
     * Push Jadwal ke Dapodik
     */
    public function pushJadwal(): JsonResponse
    {
        $result = $this->pushService->pushJadwal();

        return response()->json([
            'success' => $result['gagal'] === 0,
            'message' => $result['gagal'] === 0 ? 'Push jadwal berhasil' : 'Push jadwal gagal',
            'data' => $result,
        ], $result['gagal'] === 0 ? 200 : 400);
    }

    /**
     * Push Nilai Rapor ke Dapodik
     */
    public function pushNilaiRapor(Request $request): JsonResponse
    {
        $request->validate([
            'semester_id' => 'required|string|exists:semesters,semester_id',
        ]);

        $result = $this->pushService->pushNilaiRapor($request->input('semester_id'));

        return response()->json([
            'success' => $result['gagal'] === 0,
            'message' => $result['gagal'] === 0 ? 'Push nilai rapor berhasil' : 'Push nilai rapor gagal',
            'data' => $result,
        ], $result['gagal'] === 0 ? 200 : 400);
    }

    /**
     * Push Kehadiran ke Dapodik
     */
    public function pushKehadiran(Request $request): JsonResponse
    {
        $request->validate([
            'semester_id' => 'required|string|exists:semesters,semester_id',
        ]);

        $result = $this->pushService->pushKehadiran($request->input('semester_id'));

        return response()->json([
            'success' => $result['gagal'] === 0,
            'message' => $result['gagal'] === 0 ? 'Push kehadiran berhasil' : 'Push kehadiran gagal',
            'data' => $result,
        ], $result['gagal'] === 0 ? 200 : 400);
    }

    /**
     * Status push Dapodik
     */
    public function status(): JsonResponse
    {
        $config = DapodikConfig::getInstance();

        return response()->json([
            'success' => true,
            'data' => [
                'npsn' => $config->npsn ?? 'Not configured',
                'host' => $config->host ?? 'localhost',
                'port' => $config->port ?? 5774,
                'protocol' => $config->protocol ?? 'http',
                'configured' => !empty($config->npsn) && !empty($config->token),
                'last_sync' => $config->last_sync_at,
                'last_sync_by' => $config->last_sync_by,
            ],
        ]);
    }
}
