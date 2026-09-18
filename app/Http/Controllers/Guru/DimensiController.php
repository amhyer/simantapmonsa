<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Dimensi;
use App\Models\Siswa;
use App\Traits\SyncableToSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DimensiController extends Controller
{
    use SyncableToSheet;
    public function index()
    {
        $dimensi = Dimensi::where('guru_id', auth()->id())->get();
        $siswa = Siswa::where('guru_id', auth()->id())->where('aktif', true)->get();
        return view('guru.dimensi.index', compact('dimensi', 'siswa'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'no_dimensi' => 'required|integer|min:1|max:8',
            'dimensi' => 'required|string|max:100',
            'skor' => 'required|integer|min:1|max:4',
        ]);

        $guru = auth()->user();
        $siswa = Siswa::find($validated['siswa_id']);
        if (!$siswa || $siswa->guru_id !== $guru->id) {
            return back()->withErrors(['siswa_id' => 'Siswa tidak ditemukan atau bukan milik Anda.'])->withInput();
        }

        Dimensi::updateOrCreate(
            ['siswa_id' => $validated['siswa_id'], 'no_dimensi' => $validated['no_dimensi']],
            [
                'uuid' => Str::uuid(),
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswa->nama_peserta_didik,
                'dimensi' => $validated['dimensi'],
                'skor' => $validated['skor'],
            ]
        );

        $dimensi = Dimensi::where('siswa_id', $validated['siswa_id'])
            ->where('no_dimensi', $validated['no_dimensi'])
            ->with('siswa')
            ->first();
        $this->syncToGoogleSheet('dimensi', $dimensi);

        return back()->with('success', 'Dimensi berhasil disimpan.');
    }
}
