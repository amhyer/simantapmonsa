<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KuisController extends Controller
{
    public function index()
    {
        $kuis = Kuis::where('guru_id', auth()->id())
            ->orderBy('tanggal', 'desc')
            ->get();
        $siswaCount = Siswa::where('guru_id', auth()->id())->where('aktif', true)->count();
        return view('guru.kuis.index', compact('kuis', 'siswaCount'));
    }

    public function create(Request $request)
    {
        $materiId = $request->get('materi_id');
        $materi = Materi::where('guru_id', auth()->id())->get();
        $pengaturan = auth()->user()->pengaturanGuru;

        return view('guru.kuis.create', compact('materi', 'materiId', 'pengaturan'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'mata_pelajaran' => 'required|string|max:100',
            'kelas' => 'required|string|max:50',
            'tanggal' => 'required|date',
            'mode' => 'required|in:harian,tka',
            'sumber' => 'required|in:lokal,luar',
            'tautan' => 'nullable|url',
            'materi_id' => 'nullable|exists:materi,id',
            'kkm' => 'required|integer|min:0|max:100',
            'batas_waktu' => 'required|integer|min:0',
            'aktif' => 'boolean',
            'jenis' => 'required|in:Tugas,Ulangan Harian,Praktik,PTS,PAS',
            'petunjuk' => 'nullable|string',
            'cara_nilai' => 'required|in:siswa,guru',
            'soal' => 'nullable|array',
        ]);

        $guru = auth()->user();

        $kuis = Kuis::create([
            'uuid' => Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'judul' => $validated['judul'],
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'kelas' => $validated['kelas'],
            'tanggal' => $validated['tanggal'],
            'mode' => $validated['mode'],
            'sumber' => $validated['sumber'],
            'tautan_soal' => $validated['tautan'] ?? null,
            'materi_id' => $validated['materi_id'] ?? null,
            'kkm' => $validated['kkm'],
            'batas_waktu' => $validated['batas_waktu'],
            'aktif' => $validated['aktif'] ?? true,
            'jenis' => $validated['jenis'],
            'petunjuk' => $validated['petunjuk'] ?? null,
            'cara_nilai' => $validated['cara_nilai'],
            'soal' => $validated['soal'] ?? [],
            'jumlah_soal' => count($validated['soal'] ?? []),
        ]);

        return redirect()->route('guru.kuis.index')
            ->with('success', 'Kuis berhasil ditambahkan.');
    }

    public function edit(Kuis $kuis)
    {
        if ($kuis->guru_id !== auth()->id()) {
            abort(403);
        }

        $materi = Materi::where('guru_id', auth()->id())->get();
        $pengaturan = auth()->user()->pengaturanGuru;

        return view('guru.kuis.edit', compact('kuis', 'materi', 'pengaturan'));
    }

    public function update(Request $request, Kuis $kuis)
    {
        if ($kuis->guru_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'mata_pelajaran' => 'required|string|max:100',
            'kelas' => 'required|string|max:50',
            'tanggal' => 'required|date',
            'mode' => 'required|in:harian,tka',
            'sumber' => 'required|in:lokal,luar',
            'tautan' => 'nullable|url',
            'materi_id' => 'nullable|exists:materi,id',
            'kkm' => 'required|integer|min:0|max:100',
            'batas_waktu' => 'required|integer|min:0',
            'aktif' => 'boolean',
            'jenis' => 'required|in:Tugas,Ulangan Harian,Praktik,PTS,PAS',
            'petunjuk' => 'nullable|string',
            'cara_nilai' => 'required|in:siswa,guru',
            'soal' => 'nullable|array',
        ]);

        $kuis->update([
            'judul' => $validated['judul'],
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'kelas' => $validated['kelas'],
            'tanggal' => $validated['tanggal'],
            'mode' => $validated['mode'],
            'sumber' => $validated['sumber'],
            'tautan_soal' => $validated['tautan'] ?? null,
            'materi_id' => $validated['materi_id'] ?? null,
            'kkm' => $validated['kkm'],
            'batas_waktu' => $validated['batas_waktu'],
            'aktif' => $validated['aktif'] ?? true,
            'jenis' => $validated['jenis'],
            'petunjuk' => $validated['petunjuk'] ?? null,
            'cara_nilai' => $validated['cara_nilai'],
            'soal' => $validated['soal'] ?? [],
            'jumlah_soal' => count($validated['soal'] ?? []),
        ]);

        return redirect()->route('guru.kuis.index')
            ->with('success', 'Kuis berhasil diperbarui.');
    }

    public function destroy(Kuis $kuis)
    {
        if ($kuis->guru_id !== auth()->id()) {
            abort(403);
        }

        $kuis->hasilKuis()->delete();
        $kuis->delete();

        return redirect()->route('guru.kuis.index')
            ->with('success', 'Kuis berhasil dihapus.');
    }

    public function toggle(Kuis $kuis)
    {
        if ($kuis->guru_id !== auth()->id()) {
            abort(403);
        }

        $kuis->aktif = !$kuis->aktif;
        $kuis->save();

        return back()->with('success', $kuis->aktif ? 'Kuis dibuka untuk siswa.' : 'Kuis ditutup.');
    }

    public function hasil(Kuis $kuis)
    {
        if ($kuis->guru_id !== auth()->id()) {
            abort(403);
        }

        $hasil = $kuis->hasilKuis()->with('siswa')->get();
        $siswa = Siswa::where('guru_id', auth()->id())->where('aktif', true)->get();
        $belum = $siswa->whereNotIn('id', $hasil->pluck('siswa_id'));

        return view('guru.kuis.hasil', compact('kuis', 'hasil', 'belum'));
    }
}
