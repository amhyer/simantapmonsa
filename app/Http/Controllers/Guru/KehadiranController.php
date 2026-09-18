<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kehadiran;
use App\Models\Siswa;
use App\Traits\SyncableToSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KehadiranController extends Controller
{
    use SyncableToSheet;
    public function index()
    {
        $kehadiran = Kehadiran::where('guru_id', auth()->id())->latest('tanggal')->get();
        $siswa = Siswa::where('guru_id', auth()->id())->where('aktif', true)->get();
        return view('guru.kehadiran.index', compact('kehadiran', 'siswa'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'tanggal' => 'required|date',
            'status' => 'required|in:H,S,I,A',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $guru = auth()->user();

        // Authorization: verify siswa belongs to this guru
        $siswa = Siswa::where('id', $validated['siswa_id'])->where('guru_id', $guru->id)->first();
        if (!$siswa) {
            abort(403, 'Anda tidak memiliki akses ke siswa ini.');
        }

        $existing = Kehadiran::where('siswa_id', $validated['siswa_id'])->where('tanggal', $validated['tanggal'])->first();

        if ($existing) {
            $existing->update([
                'guru_id' => $guru->id,
                'status' => $validated['status'],
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
            $this->syncToGoogleSheet('kehadiran', $existing->fresh('siswa', 'guru'));
        } else {
            $kehadiran = Kehadiran::create([
                'uuid' => Str::uuid(),
                'guru_id' => $guru->id,
                'siswa_id' => $validated['siswa_id'],
                'tanggal' => $validated['tanggal'],
                'status' => $validated['status'],
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
            $this->syncToGoogleSheet('kehadiran', $kehadiran->fresh('siswa', 'guru'));
        }

        return back()->with('success', 'Kehadiran berhasil disimpan.');
    }

    public function destroy(Kehadiran $kehadiran)
    {
        if ($kehadiran->guru_id !== auth()->id()) abort(403);
        $kehadiran->delete();
        return back()->with('success', 'Kehadiran berhasil dihapus.');
    }
}
