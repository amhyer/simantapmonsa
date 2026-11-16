<?php

namespace App\Services\Elearning;

use App\Models\JadwalPelajaran;
use App\Models\Materi;
use App\Models\Rombel;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menentukan materi ajar yang sah dilihat oleh seorang siswa berdasarkan
 * rombel, kelas, mata pelajaran pada JadwalPelajaran, dan isolasi tenant.
 *
 * Kompatibel dengan SQLite (PHPUnit) dan PostgreSQL 16 (produksi/CI) —
 * menggunakan LOWER(TRIM(...)) standar SQL tanpa operator khusus PostgreSQL.
 */
class SiswaMateriVisibilityService
{
    /**
     * Kumpulkan label kelas ter-normalisasi (huruf kecil & trim) milik siswa.
     *
     * @return array<int, string>
     */
    public function normalizedKelasLabels(Siswa $siswa): array
    {
        $labels = [];

        if (! empty($siswa->kelas)) {
            $labels[] = mb_strtolower(trim((string) $siswa->kelas));
        }

        $rombel = $this->resolveRombel($siswa);
        if ($rombel && ! empty($rombel->nama_rombel)) {
            $labels[] = mb_strtolower(trim((string) $rombel->nama_rombel));
        }

        return array_values(array_unique(array_filter($labels, fn (string $v) => $v !== '')));
    }

    /**
     * Ambil daftar pasangan (guru_id, mata_pelajaran ternormalisasi) dari JadwalPelajaran
     * untuk rombel/kelas siswa pada sekolah yang sama.
     *
     * @param  array<int, string>  $kelasLabels
     * @return array<string, array{guru_id: int, mapel: string}>
     */
    public function jadwalPairsForSiswa(Siswa $siswa, array $kelasLabels): array
    {
        if (! $siswa->rombel_id && $kelasLabels === []) {
            return [];
        }

        $query = JadwalPelajaran::query()
            ->when($siswa->tenant_id !== null, fn (Builder $q) => $q->where('tenant_id', $siswa->tenant_id))
            ->when($siswa->tenant_id === null, fn (Builder $q) => $q->whereNull('tenant_id'))
            ->where(function (Builder $q) use ($siswa, $kelasLabels) {
                $hasCondition = false;

                if ($siswa->rombel_id) {
                    $q->where('rombel_id', $siswa->rombel_id);
                    $hasCondition = true;
                }

                if ($kelasLabels !== []) {
                    $placeholders = implode(',', array_fill(0, count($kelasLabels), '?'));
                    $method = $hasCondition ? 'orWhereRaw' : 'whereRaw';
                    $q->{$method}("LOWER(TRIM(kelas)) IN ({$placeholders})", $kelasLabels);
                }
            });

        $pairs = [];
        foreach ($query->get(['guru_id', 'mata_pelajaran']) as $jadwal) {
            if (! $jadwal->guru_id) {
                continue;
            }
            $mapel = mb_strtolower(trim((string) $jadwal->mata_pelajaran));
            if ($mapel === '') {
                continue;
            }
            $key = $jadwal->guru_id.'|'.$mapel;
            $pairs[$key] = [
                'guru_id' => (int) $jadwal->guru_id,
                'mapel' => $mapel,
            ];
        }

        return $pairs;
    }

    /**
     * Tentukan ID wali kelas otoritatif siswa (`rombel.guru_id`, fallback `siswa.guru_id`).
     */
    public function waliGuruId(Siswa $siswa): ?int
    {
        $rombel = $this->resolveRombel($siswa);
        if ($rombel && $rombel->guru_id) {
            return (int) $rombel->guru_id;
        }

        return $siswa->guru_id ? (int) $siswa->guru_id : null;
    }

    /**
     * Query builder Materi yang berhak diakses oleh `$siswa`.
     */
    public function queryForSiswa(Siswa $siswa): Builder
    {
        $kelasLabels = $this->normalizedKelasLabels($siswa);
        $jadwalPairs = $this->jadwalPairsForSiswa($siswa, $kelasLabels);
        $waliGuruId = $this->waliGuruId($siswa);

        $query = Materi::query();

        if ($siswa->tenant_id !== null) {
            $query->where('materi.tenant_id', $siswa->tenant_id);
        } else {
            $query->whereNull('materi.tenant_id');
        }

        if ($kelasLabels === []) {
            if ($waliGuruId !== null) {
                return $query->where('materi.guru_id', $waliGuruId);
            }

            return $query->whereRaw('1 = 0');
        }

        $placeholders = implode(',', array_fill(0, count($kelasLabels), '?'));
        $query->whereRaw("LOWER(TRIM(materi.kelas)) IN ({$placeholders})", $kelasLabels);

        $query->where(function (Builder $q) use ($jadwalPairs, $waliGuruId) {
            $applied = false;

            foreach ($jadwalPairs as $pair) {
                $method = $applied ? 'orWhere' : 'where';
                $q->{$method}(function (Builder $sub) use ($pair) {
                    $sub->where('materi.guru_id', $pair['guru_id'])
                        ->whereRaw('LOWER(TRIM(materi.mata_pelajaran)) = ?', [$pair['mapel']]);
                });
                $applied = true;
            }

            if ($waliGuruId !== null) {
                $method = $applied ? 'orWhere' : 'where';
                $q->{$method}('materi.guru_id', $waliGuruId);
                $applied = true;
            }

            if (! $applied) {
                $q->whereRaw('1 = 0');
            }
        });

        return $query;
    }

    private function resolveRombel(Siswa $siswa): ?Rombel
    {
        if (! $siswa->rombel_id) {
            return null;
        }

        if ($siswa->relationLoaded('rombel')) {
            return $siswa->getRelation('rombel');
        }

        return Rombel::query()
            ->when($siswa->tenant_id !== null, fn (Builder $q) => $q->where('tenant_id', $siswa->tenant_id))
            ->find($siswa->rombel_id);
    }
}
