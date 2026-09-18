<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGuru;
use Illuminate\Http\Request;

class BobotController extends Controller
{
    public function index()
    {
        $pengaturanGuru = PengaturanGuru::with('guru')->get();

        return view('admin.bobot.index', compact('pengaturanGuru'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'kkm' => 'required|integer|min:0|max:100',
            'bobot_tugas' => 'nullable|numeric|min:0|max:100',
            'bobot_uh' => 'nullable|numeric|min:0|max:100',
            'bobot_uts' => 'nullable|numeric|min:0|max:100',
            'bobot_uas' => 'nullable|numeric|min:0|max:100',
            'bobot_formatif' => 'nullable|numeric|min:0|max:100',
            'bobot_sumatif' => 'nullable|numeric|min:0|max:100',
            'bobot_sumatif_akhir' => 'nullable|numeric|min:0|max:100',
        ]);

        $pengaturan = PengaturanGuru::findOrFail($id);

        // Validate weight sums equal 100
        $bobotSum = ($validated['bobot_tugas'] ?? 30) + ($validated['bobot_uh'] ?? 20) + ($validated['bobot_uts'] ?? 20) + ($validated['bobot_uas'] ?? 30);
        if ($bobotSum != 100) {
            return back()->withErrors(['bobot_tugas' => "Total bobot harus 100% (saat ini: {$bobotSum}%)"])->withInput();
        }

        $eraporSum = ($validated['bobot_formatif'] ?? 30) + ($validated['bobot_sumatif'] ?? 40) + ($validated['bobot_sumatif_akhir'] ?? 30);
        if ($eraporSum != 100) {
            return back()->withErrors(['bobot_formatif' => "Total bobot e-Rapor harus 100% (saat ini: {$eraporSum}%)"])->withInput();
        }

        $currentSettings = $pengaturan->pengaturan ?? [];
        $currentSettings['bobot'] = [
            'tugas' => $validated['bobot_tugas'] ?? 30,
            'uh' => $validated['bobot_uh'] ?? 20,
            'uts' => $validated['bobot_uts'] ?? 20,
            'uas' => $validated['bobot_uas'] ?? 30,
        ];
        $currentSettings['bobot_erapor'] = [
            'formatif' => ($validated['bobot_formatif'] ?? 30) / 100,
            'sumatif' => ($validated['bobot_sumatif'] ?? 40) / 100,
            'sumatif_akhir' => ($validated['bobot_sumatif_akhir'] ?? 30) / 100,
        ];

        $pengaturan->update([
            'kkm' => $validated['kkm'],
            'pengaturan' => $currentSettings,
            'pembaruan' => now(),
        ]);

        return redirect()->route('admin.bobot.index')
            ->with('success', 'Bobot dan KKM berhasil diperbarui.');
    }
}
