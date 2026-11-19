<?php

namespace App\Services\Tka\Kesiapan;

use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Otorisasi data siswa untuk pemantauan kesiapan — ditegakkan di server.
 *
 *  - guru   : siswa aktif yang guru_id-nya = guru tsb ATAU berada di rombel yang diampu (rombel.guru_id).
 *  - kepsek : seluruh siswa aktif sekolahnya — hanya AGREGAT (tanpa profil/daftar individual).
 *  - admin  : seluruh siswa aktif sekolahnya — agregat + konfigurasi (tanpa profil individual).
 *  - siswa  : siswa yang terhubung via `terhubung_dengan` atau `nama_pengguna` (NIS/NISN/`siswa{NIS}`),
 *             mengikuti pola `HasSiswaLookup`.
 *  - ortu   : hanya siswa aktif pada `terhubung_dengan` akun orang tua.
 */
class KesiapanAkses
{
    public static function peranDiizinkan(User $user): bool
    {
        return in_array($user->peran, ['guru', 'kepsek', 'admin', 'siswa', 'ortu'], true);
    }

    public static function bolehProfilIndividu(User $user): bool
    {
        return $user->peran === 'guru';
    }

    public static function bolehAgregat(User $user): bool
    {
        return in_array($user->peran, ['guru', 'kepsek', 'admin'], true);
    }

    /** @return Builder<Siswa> */
    public static function siswaQuery(User $user): Builder
    {
        $q = Siswa::query()->where('aktif', true)->whereNull('archived_at');

        if ($user->tenant_id !== null) {
            $q->where('siswa.tenant_id', $user->tenant_id);
        } elseif (Schema::hasTable('tenants') && Tenant::hasAnyTenant()) {
            $q->whereNull('siswa.tenant_id');
        }

        return match ($user->peran) {
            'guru' => $q->where(function (Builder $w) use ($user) {
                $w->where('siswa.guru_id', $user->id)
                    ->orWhereIn('siswa.rombel_id', Rombel::query()
                        ->when($user->tenant_id !== null, fn ($rq) => $rq->where('tenant_id', $user->tenant_id))
                        ->where('guru_id', $user->id)
                        ->select('id'));
            }),
            'kepsek', 'admin' => $q,
            'ortu' => $q->whereIn('siswa.id', self::linkedIds($user)),
            'siswa' => self::applySiswaFilter($q, $user),
            default => $q->whereRaw('1 = 0'),
        };
    }

    public static function siswaUuid(User $user, string $uuid): ?Siswa
    {
        return self::siswaQuery($user)->where('siswa.uuid', $uuid)->first();
    }

    /** Pastikan `rombel_id` dari filter memang berada dalam kewenangan pengguna & tenant. */
    public static function rombelDiizinkan(User $user, int $rombelId): bool
    {
        if (! self::bolehAgregat($user)) {
            return false;
        }

        $q = Rombel::query()->whereKey($rombelId);
        if ($user->tenant_id !== null) {
            $q->where('tenant_id', $user->tenant_id);
        } elseif (Schema::hasTable('tenants') && Tenant::hasAnyTenant()) {
            $q->whereNull('tenant_id');
        }

        if ($user->peran === 'guru') {
            $q->where(function (Builder $w) use ($user) {
                $w->where('guru_id', $user->id)
                    ->orWhereIn('id', Siswa::query()->where('guru_id', $user->id)->whereNotNull('rombel_id')->select('rombel_id'));
            });
        }

        return $q->exists();
    }

    /** @return array<int, int> */
    private static function linkedIds(User $user): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($user->terhubung_dengan ?? [])), fn ($id) => $id > 0));

        return $ids === [] ? [-1] : $ids;
    }

    /** @param Builder<Siswa> $q */
    private static function applySiswaFilter(Builder $q, User $user): Builder
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($user->terhubung_dengan ?? [])), fn ($id) => $id > 0));
        if ($ids !== []) {
            return $q->whereIn('siswa.id', $ids);
        }

        $namaPengguna = trim((string) $user->nama_pengguna);
        $kandidatNis = array_values(array_unique(array_filter([
            $namaPengguna,
            preg_replace('/^siswa/i', '', $namaPengguna),
        ], fn ($nis) => $nis !== '' && $nis !== null)));

        if ($kandidatNis === []) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where(function (Builder $w) use ($kandidatNis) {
            $w->whereIn('siswa.nis', $kandidatNis)->orWhereIn('siswa.nisn', $kandidatNis);
        });
    }
}
