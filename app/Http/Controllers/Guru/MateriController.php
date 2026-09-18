<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Materi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MateriController extends Controller
{
    public function index()
    {
        $materi = Materi::where('guru_id', auth()->id())
            ->orderBy('tanggal', 'desc')
            ->get();
        return view('guru.materi.index', compact('materi'));
    }

    public function create()
    {
        return view('guru.materi.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'mata_pelajaran' => 'required|string|max:100',
            'kelas' => 'required|string|max:50',
            'tanggal' => 'required|date',
            'tujuan' => 'required|string',
            'konten' => 'required|string',
            'tautan' => 'nullable|url',
            'pm' => 'nullable|array',
            'dimensi' => 'nullable|array',
            'pengalaman_memahami' => 'nullable|string',
            'pengalaman_mengaplikasi' => 'nullable|string',
            'pengalaman_merefleksi' => 'nullable|string',
            'sematkan' => 'boolean',
        ]);

        $guru = auth()->user();

        $materi = Materi::create([
            'uuid' => Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'judul' => $validated['judul'],
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'kelas' => $validated['kelas'],
            'tanggal' => $validated['tanggal'],
            'tujuan_pembelajaran' => $validated['tujuan'],
            'materi_pokok' => $validated['konten'],
            'tautan_sumber' => $validated['tautan'] ?? null,
            'pm' => $validated['pm'] ?? [],
            'dimensi' => $validated['dimensi'] ?? [],
            'pengalaman' => [
                'memahami' => $validated['pengalaman_memahami'] ?? '',
                'mengaplikasi' => $validated['pengalaman_mengaplikasi'] ?? '',
                'merefleksi' => $validated['pengalaman_merefleksi'] ?? '',
            ],
            'sematkan' => $validated['sematkan'] ?? false,
        ]);

        return redirect()->route('guru.materi.index')
            ->with('success', 'Materi berhasil ditambahkan.');
    }

    public function edit(Materi $materi)
    {
        if ($materi->guru_id !== auth()->id()) {
            abort(403);
        }
        return view('guru.materi.edit', compact('materi'));
    }

    public function update(Request $request, Materi $materi)
    {
        if ($materi->guru_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'mata_pelajaran' => 'required|string|max:100',
            'kelas' => 'required|string|max:50',
            'tanggal' => 'required|date',
            'tujuan' => 'required|string',
            'konten' => 'required|string',
            'tautan' => 'nullable|url',
            'pm' => 'nullable|array',
            'dimensi' => 'nullable|array',
            'pengalaman_memahami' => 'nullable|string',
            'pengalaman_mengaplikasi' => 'nullable|string',
            'pengalaman_merefleksi' => 'nullable|string',
            'sematkan' => 'boolean',
        ]);

        $materi->update([
            'judul' => $validated['judul'],
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'kelas' => $validated['kelas'],
            'tanggal' => $validated['tanggal'],
            'tujuan_pembelajaran' => $validated['tujuan'],
            'materi_pokok' => $validated['konten'],
            'tautan_sumber' => $validated['tautan'] ?? null,
            'pm' => $validated['pm'] ?? [],
            'dimensi' => $validated['dimensi'] ?? [],
            'pengalaman' => [
                'memahami' => $validated['pengalaman_memahami'] ?? '',
                'mengaplikasi' => $validated['pengalaman_mengaplikasi'] ?? '',
                'merefleksi' => $validated['pengalaman_merefleksi'] ?? '',
            ],
            'sematkan' => $validated['sematkan'] ?? false,
        ]);

        return redirect()->route('guru.materi.index')
            ->with('success', 'Materi berhasil diperbarui.');
    }

    public function destroy(Materi $materi)
    {
        if ($materi->guru_id !== auth()->id()) {
            abort(403);
        }

        $materi->delete();

        return redirect()->route('guru.materi.index')
            ->with('success', 'Materi berhasil dihapus.');
    }

    public function show(Materi $materi)
    {
        if ($materi->guru_id !== auth()->id()) {
            abort(403);
        }
        return view('guru.materi.show', compact('materi'));
    }
}
