<?php

namespace App\Http\Controllers\Siswa;

use App\Models\Siswa;

trait HasSiswaLookup
{
    /**
     * Tentukan data siswa milik akun yang sedang login.
     *
     * Urutan pencarian:
     * 1. Tautan eksplisit `terhubung_dengan` (paling otoritatif).
     * 2. NIS yang sama dengan `nama_pengguna`.
     * 3. NIS tanpa awalan "siswa" — akun massal dibuat dengan format `siswa{NIS}`
     *    sehingga pencocokan langsung ke `nama_pengguna` selalu gagal.
     */
    protected function getSiswa(): ?Siswa
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $ids = array_map('intval', (array) ($user->terhubung_dengan ?? []));
        if ($ids !== []) {
            $siswa = Siswa::whereIn('id', $ids)->orderBy('id')->first();
            if ($siswa) {
                return $siswa;
            }
        }

        $namaPengguna = (string) $user->nama_pengguna;
        $kandidatNis = array_values(array_unique(array_filter([
            $namaPengguna,
            preg_replace('/^siswa/i', '', $namaPengguna),
        ], fn ($nis) => $nis !== '' && $nis !== null)));

        if ($kandidatNis === []) {
            return null;
        }

        return Siswa::whereIn('nis', $kandidatNis)->orderBy('id')->first();
    }
}
