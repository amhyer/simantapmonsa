<?php

namespace App\Policies;

use App\Models\JadwalPelajaran;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;

class SiswaFotoPolicy
{
    public function viewFoto(User $user, Siswa $siswa): bool
    {
        if (! $this->sameTenant($user, $siswa)) {
            return false;
        }

        return match ($user->peran) {
            'admin' => true,
            'siswa' => $this->isOwnSiswa($user, $siswa),
            'ortu' => $this->isLinkedParent($user, $siswa),
            'guru' => $this->isTeacherOfSiswa($user, $siswa),
            default => false,
        };
    }

    private function sameTenant(User $user, Siswa $siswa): bool
    {
        $userTenant = $user->tenant_id !== null ? (int) $user->tenant_id : null;
        $siswaTenant = $siswa->tenant_id !== null ? (int) $siswa->tenant_id : null;

        return $userTenant === $siswaTenant;
    }

    private function isOwnSiswa(User $user, Siswa $siswa): bool
    {
        $linkedIds = array_values(array_filter(
            array_map('intval', (array) ($user->terhubung_dengan ?? [])),
            fn (int $id): bool => $id > 0
        ));

        // Jika tautan eksplisit terhubung_dengan sudah terisi, jadikan satu-satunya sumber otoritatif
        // agar tidak jatuh ke pencocokan NIS/NISN yang bisa bertabrakan dengan siswa lain.
        if ($linkedIds !== []) {
            return in_array((int) $siswa->id, $linkedIds, true);
        }

        $namaPengguna = trim((string) $user->nama_pengguna);
        if ($namaPengguna === '') {
            return false;
        }

        $kandidat = array_values(array_unique(array_filter([
            $namaPengguna,
            preg_replace('/^siswa/i', '', $namaPengguna),
        ], fn ($v) => $v !== '' && $v !== null)));

        if ($kandidat === []) {
            return false;
        }

        return ($siswa->nis !== null && in_array((string) $siswa->nis, $kandidat, true))
            || ($siswa->nisn !== null && in_array((string) $siswa->nisn, $kandidat, true));
    }

    private function isLinkedParent(User $user, Siswa $siswa): bool
    {
        $linkedIds = array_map('intval', (array) ($user->terhubung_dengan ?? []));

        return in_array((int) $siswa->id, $linkedIds, true);
    }

    private function isTeacherOfSiswa(User $user, Siswa $siswa): bool
    {
        $guruId = (int) $user->id;

        if ($siswa->guru_id !== null && (int) $siswa->guru_id === $guruId) {
            return true;
        }

        if ($siswa->rombel_id !== null && Rombel::query()
            ->where('id', $siswa->rombel_id)
            ->where('guru_id', $guruId)
            ->exists()) {
            return true;
        }

        $kelasNorm = mb_strtolower(trim((string) ($siswa->kelas ?? '')));

        return JadwalPelajaran::query()
            ->where('guru_id', $guruId)
            ->where(function ($query) use ($siswa, $kelasNorm): void {
                $hasCondition = false;
                if ($siswa->rombel_id !== null) {
                    $query->where('rombel_id', $siswa->rombel_id);
                    $hasCondition = true;
                }
                if ($kelasNorm !== '') {
                    if ($hasCondition) {
                        $query->orWhereRaw('LOWER(TRIM(COALESCE(kelas, \'\'))) = ?', [$kelasNorm]);
                    } else {
                        $query->whereRaw('LOWER(TRIM(COALESCE(kelas, \'\'))) = ?', [$kelasNorm]);
                    }
                    $hasCondition = true;
                }
                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->exists();
    }
}
