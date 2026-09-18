<?php

namespace App\Http\Controllers\Siswa;

use App\Models\Siswa;

trait HasSiswaLookup
{
    protected function getSiswa()
    {
        $user = auth()->user();
        $siswa = Siswa::where('nis', $user->nama_pengguna)->first();

        if (!$siswa && !empty($user->terhubung_dengan)) {
            $siswa = Siswa::whereIn('id', $user->terhubung_dengan)->first();
        }

        return $siswa;
    }
}
