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
    public function index(Request $request)
    {
        $guruId = auth()->id();
        $nilai = Nilai::where('guru_id', $guruId)->latest('tanggal')->get();

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

        return view('guru.nilai.index', compact('nilai', 'siswa', 'kelasList', 'selectedKelas'));
    }

    public function create(Request $request)
    {
        $guruId = auth()->id();
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

        return view('guru.nilai.create', compact('siswa', 'kelasList', 'selectedKelas'));
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
        $siswa = Siswa::where('aktif', true)->whereNull('archived_at')->find($validated['siswa_id']);
        if (!$siswa) {
            return back()->withErrors(['siswa_id' => 'Siswa aktif tidak ditemukan.'])->withInput();
        }
        if (empty($siswa->dapodik_id) && $siswa->guru_id !== null && $siswa->guru_id !== $guru->id) {
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
