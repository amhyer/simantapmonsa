<?php

namespace App\Policies;

use App\Models\CbtResult;
use App\Models\Siswa;
use App\Models\User;
use App\Services\CbtExamService;

/**
 * Hasil ujian boleh dilihat siswa pemiliknya dan guru pemilik sesi.
 * Menjawab/telemetri hanya siswa pemilik yang masih memenuhi syarat.
 */
class CbtResultPolicy
{
    public function view(User $user, CbtResult $result): bool
    {
        if ((int) $result->user_id === (int) $user->id) {
            return true;
        }

        return (int) $result->session->guru_id === (int) $user->id;
    }

    public function answer(User $user, CbtResult $result): bool
    {
        if ((int) $result->user_id !== (int) $user->id) {
            return false;
        }

        $siswa = $this->siswa($user);

        return $siswa
            && (int) $result->siswa_id === (int) $siswa->id
            && $siswa->aktif
            && ! $siswa->archived_at
            && app(CbtExamService::class)->eligible($result->session, $siswa);
    }

    public function recordViolation(User $user, CbtResult $result): bool
    {
        return $this->answer($user, $result);
    }

    public function viewViolations(User $user, CbtResult $result): bool
    {
        return (int) $result->session->guru_id === (int) $user->id;
    }

    private function siswa(User $user): ?Siswa
    {
        $siswa = Siswa::where('nis', $user->nama_pengguna)->first();

        if ($siswa) {
            return $siswa;
        }

        $terhubung = $user->terhubung_dengan;
        if (is_array($terhubung) && $terhubung !== []) {
            $kandidat = Siswa::whereIn('id', $terhubung)->limit(2)->get();

            return $kandidat->count() === 1 ? $kandidat->first() : null;
        }

        return null;
    }
}
