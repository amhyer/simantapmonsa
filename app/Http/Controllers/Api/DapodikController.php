<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DapodikConfig;
use App\Services\Dapodik\DapodikClient;
use App\Services\Dapodik\DapodikSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DapodikController extends Controller
{
    public function config(): JsonResponse
    {
        $config = DapodikConfig::getInstance();

        return response()->json([
            'success' => true,
            'data' => [
                'npsn' => $config->npsn,
                'host' => $config->host,
                'port' => $config->port,
                'protocol' => $config->protocol,
                'token' => $config->masked_token,
                'allow_insecure_in_production' => $config->allow_insecure_in_production,
                'archive_unlisted' => $config->archive_unlisted,
                'auto_sync_enabled' => $config->auto_sync_enabled,
                'auto_sync_interval_hours' => $config->auto_sync_interval_hours,
                'auto_sync_last_run_at' => $config->auto_sync_last_run_at,
                'auto_sync_run_status' => $config->auto_sync_run_status,
                'auto_sync_run_error' => $config->auto_sync_run_error,
                'last_sync_at' => $config->last_sync_at,
                'last_sync_by' => $config->last_sync_by,
                'cf_access' => $config->cf_access,
            ],
        ]);
    }

    public function saveConfig(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'npsn' => 'required|string|size:8',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'protocol' => 'nullable|in:http,https',
            'token' => 'nullable|string',
            'allow_insecure_in_production' => 'nullable|boolean',
            'archive_unlisted' => 'nullable|boolean',
            'auto_sync_enabled' => 'nullable|boolean',
            'auto_sync_interval_hours' => 'nullable|integer|min:1|max:168',
            'cf_access' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $config = DapodikConfig::getInstance();
        $data = $validator->validated();

        if (empty($data['token']) || $data['token'] === $config->masked_token) {
            unset($data['token']);
        }

        $config->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi Dapodik berhasil disimpan.',
            'data' => [
                'npsn' => $config->npsn,
                'host' => $config->host,
                'port' => $config->port,
                'protocol' => $config->protocol,
                'token' => $config->masked_token,
            ],
        ]);
    }

    public function testConnection(): JsonResponse
    {
        set_time_limit(60);

        $config = DapodikConfig::getInstance();

        if (empty($config->npsn) || empty($config->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi belum lengkap. NPSN dan token harus diisi.',
            ], 422);
        }

        try {
            $client = new DapodikClient(
                npsn: $config->npsn,
                token: $config->token,
                host: $config->host,
                port: (int) $config->port,
                protocol: $config->protocol,
                cfAccess: $config->cf_access ?? [],
            );

            $sekolah = $client->getSekolah();

            $namaSekolah = $sekolah[0]['nama'] ?? $sekolah[0]['nama_sekolah'] ?? 'Tidak diketahui';
            $npsn = $sekolah[0]['npsn'] ?? $config->npsn;

            return response()->json([
                'success' => true,
                'message' => 'Koneksi ke Dapodik berhasil.',
                'data' => [
                    'nama_sekolah' => $namaSekolah,
                    'npsn' => $npsn,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke Dapodik: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function preview(Request $request): JsonResponse
    {
        set_time_limit(300);

        $validator = Validator::make($request->all(), [
            'semester_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $config = DapodikConfig::getInstance();

        if (empty($config->npsn) || empty($config->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi belum lengkap. NPSN dan token harus diisi.',
            ], 422);
        }

        try {
            $service = new DapodikSyncService();
            $semesterId = $request->input('semester_id');
            $result = $service->preview($semesterId);

            return response()->json([
                'success' => true,
                'message' => 'Preview data dari Dapodik.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dari Dapodik: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function pull(Request $request): JsonResponse
    {
        set_time_limit(300);

        $validator = Validator::make($request->all(), [
            'semester_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $config = DapodikConfig::getInstance();

        if (empty($config->npsn) || empty($config->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi belum lengkap. NPSN dan token harus diisi.',
            ], 422);
        }

        try {
            $service = new DapodikSyncService();
            $semesterId = $request->input('semester_id');
            $result = $service->dryRun($semesterId);

            return response()->json([
                'success' => true,
                'message' => 'Pull data dari Dapodik (tanpa menyimpan ke database).',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dari Dapodik: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function semesters(): JsonResponse
    {
        set_time_limit(120);

        $config = DapodikConfig::getInstance();

        if (empty($config->npsn) || empty($config->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi belum lengkap. NPSN dan token harus diisi.',
            ], 422);
        }

        try {
            $client = new DapodikClient(
                npsn: $config->npsn,
                token: $config->token,
                host: $config->host,
                port: (int) $config->port,
                protocol: $config->protocol,
                cfAccess: $config->cf_access ?? [],
            );

            $semesters = $client->getSemestersWithCounts();

            return response()->json([
                'success' => true,
                'message' => 'Daftar semester dari Dapodik.',
                'data' => $semesters,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data semester dari Dapodik: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function sync(Request $request): JsonResponse
    {
        set_time_limit(300);

        $validator = Validator::make($request->all(), [
            'mode' => 'nullable|in:dry-run,commit',
            'semester_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $config = DapodikConfig::getInstance();

        if (empty($config->npsn) || empty($config->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi belum lengkap. NPSN dan token harus diisi.',
            ], 422);
        }

        $mode = $request->input('mode', 'dry-run');
        $semesterId = $request->input('semester_id');

        try {
            $service = new DapodikSyncService();

            if ($mode === 'commit') {
                $result = $service->commit($semesterId);

                $config->update([
                    'last_sync_at' => now(),
                    'last_sync_by' => $request->user()->nama_lengkap ?? 'System',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Sinkronisasi data ke database berhasil.',
                    'data' => $result,
                ]);
            } else {
                $result = $service->dryRun($semesterId);

                return response()->json([
                    'success' => true,
                    'message' => 'Dry-run sinkronisasi (tanpa menyimpan ke database).',
                    'data' => $result,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan sinkronisasi: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'SIMANTAP API siap menerima data.',
            'timestamp' => now()->toIso8601String(),
            'version' => '2.0',
        ]);
    }
}
