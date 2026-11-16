<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\Tka\Kesiapan\TindakLanjut;
use App\Models\User;
use App\Services\Tka\Kesiapan\KesiapanAkses;

class TkaKesiapanPolicy
{
    public function viewProfil(User $user, Siswa $siswa): bool
    {
        return KesiapanAkses::bolehProfilIndividu($user)
            && KesiapanAkses::siswaQuery($user)->where('siswa.id', $siswa->id)->exists();
    }

    public function updateTindakLanjut(User $user, TindakLanjut $tindakLanjut): bool
    {
        return KesiapanAkses::bolehProfilIndividu($user)
            && KesiapanAkses::siswaQuery($user)->where('siswa.id', $tindakLanjut->siswa_id)->exists();
    }
}
