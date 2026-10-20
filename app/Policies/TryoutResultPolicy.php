<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\Tryout\Result;
use App\Models\User;

/**
 * Hasil tryout boleh dilihat siswa pemilik, guru pemilik sesi, dan admin.
 * Menjawab/menyelesaikan hanya siswa pemilik.
 */
class TryoutResultPolicy
{
    public function view(User $user, Result $result): bool
    {
        return $user->peran === 'admin'
            || (int) $result->session->guru_id === (int) $user->id
            || $this->owner($user, $result);
    }

    public function answer(User $user, Result $result): bool
    {
        return $this->owner($user, $result);
    }

    public function complete(User $user, Result $result): bool
    {
        return $this->owner($user, $result);
    }

    private function owner(User $user, Result $result): bool
    {
        $siswa = Siswa::where('nis', $user->nama_pengguna)->first();

        if (! $siswa) {
            $terhubung = $user->terhubung_dengan;
            if (is_array($terhubung) && $terhubung !== []) {
                $siswa = Siswa::whereIn('id', $terhubung)->first();
            }
        }

        return $siswa && (int) $result->siswa_id === (int) $siswa->id;
    }
}
