<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilController extends Controller
{
    use HasSiswaLookup;

    public function show()
    {
        $siswa = $this->getSiswa();
        if (!$siswa) {
            return redirect()->route('siswa.dashboard')->with('error', 'Data siswa tidak ditemukan.');
        }
        return view('siswa.profil.show', compact('siswa'));
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
