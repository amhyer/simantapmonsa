<?php

namespace App\Http\Controllers\Siswa;

use App\Models\Siswa;

trait HasSiswaLookup
{
    /**
     * Tautan eksplisit (terhubung_dengan) diutamakan; lalu NIS = username,
     * termasuk username akun massal berformat "siswa{nis}".
     */
    protected function getSiswa(): ?Siswa
    {
        $user = auth()->user();

        $ids = array_map('intval', (array) ($user->terhubung_dengan ?? []));
        if ($ids) {
            $siswa = Siswa::whereIn('id', $ids)->orderBy('id')->first();
            if ($siswa) {
                return $siswa;
            }
        }

        $kandidatNis = array_unique(array_filter([
            $user->nama_pengguna,
            preg_replace('/^siswa/i', '', (string) $user->nama_pengguna),
        ]));

        return Siswa::whereIn('nis', $kandidatNis)->orderBy('id')->first();
    }
}
