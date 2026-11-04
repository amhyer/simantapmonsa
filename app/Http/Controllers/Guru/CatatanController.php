<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Catatan;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatatanController extends Controller
{
    public function index(Request $request)
    {
        $guruId = auth()->id();
        $catatan = Catatan::where('guru_id', $guruId)->latest('tanggal')->get();

        $kelasList = Siswa::where('aktif', true)->whereNull('archived_at')
            ->distinct()->orderBy('kelas')->pluck('kelas')->filter()->values();
        $selectedKelas = $request->input('kelas')
            ?? \App\Models\Rombel::where('guru_id', $guruId)->value('nama_rombel')
            ?? \App\Models\PengaturanGuru::where('guru_id', $guruId)->value('kelas')
            ?? $kelasList->first();

        $query = Siswa::where('aktif', true)->whereNull('archived_at');
        if ($selectedKelas) {
            $query->where('kelas', $selectedKelas);
        } else {
            $query->where('guru_id', $guruId);
        }
        $siswa = $query->orderBy('nama_peserta_didik')->get();
        if ($siswa->isEmpty()) {
            $siswa = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();
        }

        return view('guru.catatan.index', compact('catatan', 'siswa', 'kelasList', 'selectedKelas'));
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
        $siswa = Siswa::where('aktif', true)->whereNull('archived_at')->find($validated['siswa_id']);
        if (!$siswa) {
            return back()->withErrors(['siswa_id' => 'Siswa aktif tidak ditemukan.'])->withInput();
        }
        if (empty($siswa->dapodik_id) && $siswa->guru_id !== null && $siswa->guru_id !== $guru->id) {
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
