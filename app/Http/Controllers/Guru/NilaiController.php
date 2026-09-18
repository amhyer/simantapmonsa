<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Traits\SyncableToSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NilaiController extends Controller
{
    use SyncableToSheet;
    public function index()
    {
        $nilai = Nilai::where('guru_id', auth()->id())->latest('tanggal')->get();
        $siswa = Siswa::where('guru_id', auth()->id())->where('aktif', true)->get();
        return view('guru.nilai.index', compact('nilai', 'siswa'));
    }

    public function create()
    {
        $siswa = Siswa::where('guru_id', auth()->id())->where('aktif', true)->get();
        return view('guru.nilai.create', compact('siswa'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'tanggal' => 'required|date',
            'jenis' => 'required|in:Tugas,Ulangan Harian,Praktik,PTS,PAS',
            'judul_penilaian' => 'required|string|max:255',
            'mata_pelajaran' => 'required|string|max:100',
            'nilai' => 'required|numeric|min:0|max:100',
        ]);

        $guru = auth()->user();
        $siswa = Siswa::find($validated['siswa_id']);
        if (!$siswa || $siswa->guru_id !== $guru->id) {
            return back()->withErrors(['siswa_id' => 'Siswa tidak ditemukan atau bukan milik Anda.'])->withInput();
        }

        $nilai = Nilai::create([
            'uuid' => Str::uuid(),
            'guru_id' => $guru->id,
            'siswa_id' => $validated['siswa_id'],
            'nama_guru' => $guru->nama_lengkap,
            'nama_siswa' => $siswa->nama_peserta_didik,
            'tanggal' => $validated['tanggal'],
            'jenis' => $validated['jenis'],
            'judul_penilaian' => $validated['judul_penilaian'],
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'nilai' => $validated['nilai'],
        ]);

        $this->syncToGoogleSheet('nilai', $nilai);

        return redirect()->route('guru.nilai.index')->with('success', 'Nilai berhasil ditambahkan.');
    }

    public function edit(Nilai $nilai)
    {
        if ($nilai->guru_id !== auth()->id()) abort(403);
        $siswa = Siswa::where('guru_id', auth()->id())->where('aktif', true)->get();
        return view('guru.nilai.edit', compact('nilai', 'siswa'));
    }

    public function update(Request $request, Nilai $nilai)
    {
        if ($nilai->guru_id !== auth()->id()) abort(403);

        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'tanggal' => 'required|date',
            'jenis' => 'required|in:Tugas,Ulangan Harian,Praktik,PTS,PAS',
            'judul_penilaian' => 'required|string|max:255',
            'mata_pelajaran' => 'required|string|max:100',
            'nilai' => 'required|numeric|min:0|max:100',
        ]);

        $siswa = Siswa::find($validated['siswa_id']);
        if (!$siswa) {
            return back()->withErrors(['siswa_id' => 'Siswa tidak ditemukan.'])->withInput();
        }
        $nilai->update([
            'siswa_id' => $validated['siswa_id'],
            'nama_siswa' => $siswa->nama_peserta_didik,
            'tanggal' => $validated['tanggal'],
            'jenis' => $validated['jenis'],
            'judul_penilaian' => $validated['judul_penilaian'],
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'nilai' => $validated['nilai'],
        ]);

        return redirect()->route('guru.nilai.index')->with('success', 'Nilai berhasil diperbarui.');
    }

    public function destroy(Nilai $nilai)
    {
        if ($nilai->guru_id !== auth()->id()) abort(403);
        $nilai->delete();
        return redirect()->route('guru.nilai.index')->with('success', 'Nilai berhasil dihapus.');
    }
}
