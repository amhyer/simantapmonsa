<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGuru;
use Illuminate\Http\Request;

class KodeAksesController extends Controller
{
    public function index()
    {
        $pengaturanGuru = PengaturanGuru::with('guru')->get();

        return view('admin.kode-akses.index', compact('pengaturanGuru'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'kode_akses' => 'nullable|string|max:100',
        ]);

        $pengaturan = PengaturanGuru::findOrFail($id);

        $currentSettings = $pengaturan->pengaturan ?? [];
        $currentSettings['kode_akses'] = $validated['kode_akses'] ?? null;

        $pengaturan->update([
            'pengaturan' => $currentSettings,
            'pembaruan' => now(),
        ]);

        return redirect()->route('admin.kode-akses.index')
            ->with('success', 'Kode akses berhasil diperbarui.');
    }
}
