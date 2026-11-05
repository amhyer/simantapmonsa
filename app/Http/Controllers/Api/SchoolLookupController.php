<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SchoolDataService;
use Illuminate\Http\Request;

class SchoolLookupController extends Controller
{
    public function __construct(
        protected SchoolDataService $schoolService
    ) {}

    public function lookup(Request $request)
    {
        $npsn = trim((string) ($request->input('npsn') ?? $request->json('npsn') ?? ''));

        if (!preg_match('/^[0-9]{8}$/', $npsn)) {
            return response()->json([
                'success' => false,
                'message' => 'NPSN harus terdiri dari 8 digit angka.',
            ], 422);
        }

        $sekolah = $this->schoolService->getByNpsn($npsn);

        if (!$sekolah) {
            return response()->json([
                'success' => false,
                'message' => 'Data sekolah tidak ditemukan. Periksa kembali NPSN Anda.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $sekolah,
        ]);
    }
}
