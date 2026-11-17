<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ValidasiDataSekolahService;
use Illuminate\View\View;

/**
 * Gelombang 3A + 7B: Pusat Validasi & Kelengkapan Data Admin (read-only per tenant).
 */
class ValidasiDataController extends Controller
{
    public function __construct(private readonly ValidasiDataSekolahService $validasi) {}

    public function index(): View
    {
        $ringkasan = $this->validasi->ringkasan();

        return view('admin.validasi-data.index', [
            'ringkasan' => $ringkasan,
        ]);
    }
}
