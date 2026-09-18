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
        $request->validate([
            'npsn' => 'required|string|size:8|regex:/^[0-9]+$/',
        ], [
            'npsn.required' => 'NPSN wajib diisi.',
            'npsn.size' => 'NPSN harus 8 digit.',
            'npsn.regex' => 'NPSN hanya boleh berisi angka.',
        ]);

        $sekolah = $this->schoolService->getByNpsn($request->npsn);

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
