<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\SekolahSettings;
use App\Services\Presensi\IdentityCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilController extends Controller
{
    use HasSiswaLookup;

    public function show(IdentityCardService $cards)
    {
        $siswa = $this->getSiswa();
        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan.');
        }

        $siswa->loadMissing('rombel');
        $sekolah = SekolahSettings::query()->first();
        $kartuSaya = $cards->buildSiswaCardViewModel($siswa, $sekolah, true);

        return view('siswa.profil.show', compact('siswa', 'kartuSaya'));
    }

    public function kartu(IdentityCardService $cards)
    {
        $siswa = $this->getSiswa();
        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan.');
        }

        $siswa->loadMissing('rombel');
        $sekolah = SekolahSettings::query()->first();

        return view('presensi.kartu-cetak', [
            'judul' => 'Kartu Identitas Siswa — ' . $siswa->nama_peserta_didik,
            'subjudul' => $siswa->rombel?->nama_rombel ?? $siswa->kelas ?? 'Siswa Aktif',
            'cards' => [$cards->buildSiswaCardViewModel($siswa, $sekolah, true)],
            'kembaliUrl' => route('siswa.profil.show'),
        ]);
    }

    public function edit()
    {
        $siswa = $this->getSiswa();
        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan.');
        }
        return view('siswa.profil.edit', compact('siswa'));
    }

    public function update(Request $request)
    {
        $siswa = $this->getSiswa();
        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan.');
        }

        $validated = $request->validate([
            'telepon' => 'nullable|string|max:20',
            'alamat' => 'nullable|string|max:500',
            'foto' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ], [
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format gambar harus jpeg, jpg, atau png.',
            'foto.max' => 'Ukuran gambar maksimal 2MB.',
        ]);

        if ($request->hasFile('foto')) {
            if ($siswa->foto && Storage::disk('public')->exists($siswa->foto)) {
                Storage::disk('public')->delete($siswa->foto);
            }
            $validated['foto'] = $request->file('foto')->store('foto-siswa', 'public');
        }

        $siswa->update($validated);

        return redirect()->route('siswa.profil.show')->with('success', 'Profil berhasil diperbarui.');
    }
}
