<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SekolahSettings;
use Illuminate\Http\Request;

class SheetController extends Controller
{
    public function index()
    {
        $sekolahSettings = SekolahSettings::first();
        $pengaturan = $sekolahSettings?->pengaturan ?? [];
        $sheet = $pengaturan['google_sheet'] ?? [
            'terhubung' => false,
            'spreadsheet_id' => null,
            'spreadsheet_url' => null,
        ];

        return view('admin.sheet.index', compact('sheet', 'sekolahSettings'));
    }

    public function connect(Request $request)
    {
        $validated = $request->validate([
            'spreadsheet_id' => 'required|string|max:255',
            'spreadsheet_url' => 'required|url|max:500',
        ]);

        $sekolahSettings = SekolahSettings::first();

        if (!$sekolahSettings) {
            $sekolahSettings = SekolahSettings::create([
                'guru_id' => auth()->id(),
                'nama_sekolah' => 'SIMANTAP',
                'pengaturan' => [
                    'google_sheet' => [
                        'terhubung' => true,
                        'spreadsheet_id' => $validated['spreadsheet_id'],
                        'spreadsheet_url' => $validated['spreadsheet_url'],
                    ],
                ],
            ]);
        } else {
            $currentSettings = $sekolahSettings->pengaturan ?? [];
            $currentSettings['google_sheet'] = [
                'terhubung' => true,
                'spreadsheet_id' => $validated['spreadsheet_id'],
                'spreadsheet_url' => $validated['spreadsheet_url'],
            ];
            $sekolahSettings->update(['pengaturan' => $currentSettings]);
        }

        return redirect()->route('admin.sheet.index')
            ->with('success', 'Google Sheet berhasil terhubung.');
    }

    public function disconnect()
    {
        $sekolahSettings = SekolahSettings::first();

        if ($sekolahSettings) {
            $currentSettings = $sekolahSettings->pengaturan ?? [];
            $currentSettings['google_sheet'] = [
                'terhubung' => false,
                'spreadsheet_id' => null,
                'spreadsheet_url' => null,
            ];
            $sekolahSettings->update(['pengaturan' => $currentSettings]);
        }

        return redirect()->route('admin.sheet.index')
            ->with('success', 'Google Sheet berhasil diputuskan.');
    }
}
