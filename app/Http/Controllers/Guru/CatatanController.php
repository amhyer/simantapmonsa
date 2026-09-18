<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Catatan;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatatanController extends Controller
{
    public function index()
    {
        $catatan = Catatan::where('guru_id', auth()->id())->latest('tanggal')->get();
        $siswa = Siswa::where('guru_id', auth()->id())->where('aktif', true)->get();
        return view('guru.catatan.index', compact('catatan', 'siswa'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'tanggal' => 'required|date',
            'jenis' => 'required|in:apresiasi,perhatian,umum',
            'catatan' => 'required|string',
        ]);

        $guru = auth()->user();
        $siswa = Siswa::find($validated['siswa_id']);
        if (!$siswa || $siswa->guru_id !== $guru->id) {
            return back()->withErrors(['siswa_id' => 'Siswa tidak ditemukan atau bukan milik Anda.'])->withInput();
        }

        Catatan::create([
            'uuid' => Str::uuid(),
            'guru_id' => $guru->id,
            'siswa_id' => $validated['siswa_id'],
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa->nama_peserta_didik,
            'tanggal' => $validated['tanggal'],
            'jenis' => $validated['jenis'],
            'catatan' => $validated['catatan'],
        ]);

        return back()->with('success', 'Catatan berhasil disimpan.');
    }

    public function destroy(Catatan $catatan)
    {
        if ($catatan->guru_id !== auth()->id()) abort(403);
        $catatan->delete();
        return back()->with('success', 'Catatan berhasil dihapus.');
    }
}
