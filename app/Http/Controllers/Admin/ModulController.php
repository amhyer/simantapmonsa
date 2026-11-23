<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SekolahSettings;
use Illuminate\Http\Request;

class ModulController extends Controller
{
    public function index()
    {
        $sekolahSettings = SekolahSettings::first();
        $pengaturan = optional($sekolahSettings)->pengaturan ?? [];
        $modul = $pengaturan['modul'] ?? [
            'materi' => true,
            'kuis' => true,
            'nilai' => true,
            'kehadiran' => true,
            'catatan' => true,
            'dimensi' => true,
            'kebiasaan' => true,
            'laporan' => true,
        ];

        return view('admin.modul.index', compact('modul', 'sekolahSettings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'modul' => 'required|array',
            'modul.*' => 'boolean',
        ]);

        $sekolahSettings = SekolahSettings::firstOrCreate(
            [],
            ['nama_sekolah' => config('app.name', 'SIMANTAP'), 'pengaturan' => []]
        );

        $currentSettings = is_array($sekolahSettings->pengaturan) ? $sekolahSettings->pengaturan : [];
        $currentSettings['modul'] = $validated['modul'];
        $sekolahSettings->update(['pengaturan' => $currentSettings]);

        return redirect()->route('admin.modul.index')
            ->with('success', 'Pengaturan modul berhasil diperbarui.');
    }
}
