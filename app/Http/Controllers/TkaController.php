<?php

namespace App\Http\Controllers;

use App\Services\TkaStatistikService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TkaController extends Controller
{
    public function __construct(
        protected TkaStatistikService $tkaService
    ) {}

    /**
     * Get cached TKA statistics for landing page.
     */
    public function index()
    {
        $data = $this->tkaService->get();

        return view('landing.tka-statistik', [
            'tka' => $data,
        ]);
    }

    /**
     * Get TKA statistics as JSON (for AJAX).
     */
    public function api(): JsonResponse
    {
        $data = $this->tkaService->get();
        return response()->json($data);
    }

    /**
     * Force refresh TKA statistics cache.
     */
    public function refresh(): JsonResponse
    {
        $data = $this->tkaService->refresh();
        return response()->json([
            'success' => !isset($data['error']),
            'last_update' => $data['last_update'] ?? null,
        ]);
    }
}
