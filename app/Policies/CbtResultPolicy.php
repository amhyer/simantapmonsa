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

        return $result->session !== null && (int) $result->session->guru_id === (int) $user->id;
    }

    public function answer(User $user, CbtResult $result): bool
    {
        if ((int) $result->user_id !== (int) $user->id || $result->session === null) {
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
        return $result->session !== null && (int) $result->session->guru_id === (int) $user->id;
    }

    private function siswa(User $user): ?Siswa
    {
        $terhubung = $user->terhubung_dengan;
        if (is_array($terhubung) && $terhubung !== []) {
            $kandidat = Siswa::whereIn('id', $terhubung)->limit(2)->get();
            if ($kandidat->count() === 1) {
                return $kandidat->first();
            }
        }

        $username = trim((string) $user->nama_pengguna);
        if ($username === '') {
            return null;
        }

        $siswa = Siswa::where(fn ($q) => $q->where('nis', $username)->orWhere('nisn', $username))->first();
        if ($siswa) {
            return $siswa;
        }

        $tanpaPrefix = trim((string) preg_replace('/^siswa/i', '', $username));
        if ($tanpaPrefix !== '' && $tanpaPrefix !== $username) {
            return Siswa::where(fn ($q) => $q->where('nis', $tanpaPrefix)->orWhere('nisn', $tanpaPrefix))->first();
        }

        return null;
    }
}
