<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGuru;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    public function index()
    {
        $pengaturan = PengaturanGuru::where('guru_id', auth()->id())->first();
        return view('guru.pengaturan.index', compact('pengaturan'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mata_pelajaran' => 'nullable|string|max:100',
            'kelas' => 'nullable|string|max:50',
            'kkm' => 'required|integer|min:0|max:100',
            'semester' => 'nullable|string|max:10',
            'tahun_pelajaran' => 'nullable|string|max:20',
            'fase' => 'nullable|string|max:20',
        ]);

        PengaturanGuru::updateOrCreate(
            ['guru_id' => auth()->id()],
            array_merge($validated, [
                'nama_guru' => auth()->user()->nama_lengkap,
                'pembaruan' => now(),
            ])
        );

        // Update user kelas_mata_pelajaran
        if (!empty($validated['kelas'])) {
            auth()->user()->update(['kelas_mata_pelajaran' => $validated['kelas']]);
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
