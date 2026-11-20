<?php

namespace App\Services\Elearning;

use App\Models\Elearning\KelasDigital;
use App\Models\Elearning\KelasDigitalPengumpulan;
use App\Models\Elearning\KelasDigitalPertemuan;
use App\Models\Elearning\KelasDigitalProgress;
use App\Models\Elearning\KelasDigitalTugas;
use App\Models\Nilai;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pusat otorisasi, visibilitas, rekap progress, dan sinkronisasi nilai
 * untuk modul E-Learning / Kelas Digital.
 */
class KelasDigitalAccessService
{
    public function __construct(
        private readonly SiswaMateriVisibilityService $materiVisibility,
    ) {}

    /**
     * Query Kelas Digital aktif yang boleh diakses oleh `$siswa`.
     *
     * Aturan:
     *  - Tenant sama dengan siswa (terisolasi multi-tenant).
     *  - Kelas aktif.
     *  - Cocok dengan rombel_id siswa ATAU label kelas siswa (case-insensitive via LOWER/TRIM),
     *    DAN gurunya merupakan pengajar rombel/kelas siswa di JadwalPelajaran atau wali kelas siswa
     *    (atau kelas digital terikat langsung ke `rombel_id` milik siswa pada sekolah yang sama).
     */
    public function queryForSiswa(Siswa $siswa): Builder
    {
        $kelasLabels = $this->materiVisibility->normalizedKelasLabels($siswa);
        $jadwalPairs = $this->materiVisibility->jadwalPairsForSiswa($siswa, $kelasLabels);
        $waliGuruId = $this->materiVisibility->waliGuruId($siswa);

        $query = KelasDigital::query()->where('kelas_digital.aktif', true);

        if ($siswa->tenant_id !== null) {
            $query->where('kelas_digital.tenant_id', $siswa->tenant_id);
        } else {
            $query->whereNull('kelas_digital.tenant_id');
        }

        if (! $siswa->rombel_id && $kelasLabels === []) {
            if ($waliGuruId !== null) {
                return $query->where('kelas_digital.guru_id', $waliGuruId);
            }

            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $q) use ($siswa, $kelasLabels, $jadwalPairs, $waliGuruId) {
            $hasRule = false;

            if ($siswa->rombel_id) {
                $q->where('kelas_digital.rombel_id', $siswa->rombel_id);
                $hasRule = true;
            }

            if ($kelasLabels !== []) {
                $placeholders = implode(',', array_fill(0, count($kelasLabels), '?'));
                $method = $hasRule ? 'orWhere' : 'where';

                $q->{$method}(function (Builder $byKelas) use ($placeholders, $kelasLabels, $jadwalPairs, $waliGuruId) {
                    $byKelas->whereRaw("LOWER(TRIM(kelas_digital.kelas)) IN ({$placeholders})", $kelasLabels)
                        ->where(function (Builder $teacherRule) use ($jadwalPairs, $waliGuruId) {
                            $applied = false;

                            foreach ($jadwalPairs as $pair) {
                                $m = $applied ? 'orWhere' : 'where';
                                $teacherRule->{$m}(function (Builder $sub) use ($pair) {
                                    $sub->where('kelas_digital.guru_id', $pair['guru_id'])
                                        ->whereRaw('LOWER(TRIM(kelas_digital.mata_pelajaran)) = ?', [$pair['mapel']]);
                                });
                                $applied = true;
                            }

                            if ($waliGuruId !== null) {
                                $m = $applied ? 'orWhere' : 'where';
                                $teacherRule->{$m}('kelas_digital.guru_id', $waliGuruId);
                                $applied = true;
                            }

                            if (! $applied) {
                                // Jika jadwal dan wali kelas sama-sama belum terisi, izinkan guru
                                // pada sekolah yang sama hanya bila rombel/kelas memang cocok eksplisit.
                                $teacherRule->whereRaw('1 = 0');
                            }
                        });
                });
            }
        });

        return $query;
    }

    public function siswaCanAccessKelas(Siswa $siswa, KelasDigital $kelas): bool
    {
        return $this->queryForSiswa($siswa)
            ->where('kelas_digital.id', $kelas->id)
            ->exists();
    }

    public function authorizeGuruKelas(User $guru, KelasDigital $kelas): void
    {
        if ((int) $kelas->guru_id !== (int) $guru->id) {
            abort(403, 'Anda tidak berwenang mengelola kelas digital ini.');
        }

        if ($guru->tenant_id !== null && (int) $kelas->tenant_id !== (int) $guru->tenant_id) {
            abort(403, 'Kelas digital berada di luar sekolah Anda.');
        }
    }

    /**
     * Daftar siswa aktif yang menjadi peserta suatu Kelas Digital.
     *
     * @return Collection<int, Siswa>
     */
    public function pesertaKelas(KelasDigital $kelas): Collection
    {
        $query = Siswa::query()
            ->where('aktif', true)
            ->whereNull('archived_at')
            ->when($kelas->tenant_id !== null, fn (Builder $q) => $q->where('tenant_id', $kelas->tenant_id))
            ->when($kelas->tenant_id === null, fn (Builder $q) => $q->whereNull('tenant_id'));

        $kelasNorm = mb_strtolower(trim((string) $kelas->kelas));

        $query->where(function (Builder $q) use ($kelas, $kelasNorm) {
            $applied = false;
            if ($kelas->rombel_id) {
                $q->where('rombel_id', $kelas->rombel_id);
                $applied = true;
            }
            if ($kelasNorm !== '') {
                $m = $applied ? 'orWhereRaw' : 'whereRaw';
                $q->{$m}('LOWER(TRIM(kelas)) = ?', [$kelasNorm]);
            }
        });

        return $query->orderBy('nama_peserta_didik')->get();
    }

    /**
     * Hitung ringkasan progress (selesai / total pertemuan dipublikasikan) untuk
     * sekumpulan Kelas Digital bagi satu siswa — bebas N+1 (2 query agregat).
     *
     * @param  Collection<int, KelasDigital>  $kelasList
     * @return array<int, array{selesai: int, total: int, persen: int}>
     */
    public function progressSummaryForSiswa(Siswa $siswa, Collection $kelasList): array
    {
        if ($kelasList->isEmpty()) {
            return [];
        }

        $kelasIds = $kelasList->pluck('id')->all();

        $pertemuanRows = KelasDigitalPertemuan::query()
            ->join('kelas_digital_modul', 'kelas_digital_modul.id', '=', 'kelas_digital_pertemuan.modul_id')
            ->whereIn('kelas_digital_modul.kelas_digital_id', $kelasIds)
            ->where('kelas_digital_modul.dipublikasikan', true)
            ->whereNull('kelas_digital_modul.deleted_at')
            ->where('kelas_digital_pertemuan.dipublikasikan', true)
            ->whereNull('kelas_digital_pertemuan.deleted_at')
            ->get([
                'kelas_digital_pertemuan.id as pertemuan_id',
                'kelas_digital_modul.kelas_digital_id',
            ]);

        $pertemuanIds = $pertemuanRows->pluck('pertemuan_id')->all();
        $doneSet = $pertemuanIds === []
            ? []
            : array_flip(
                KelasDigitalProgress::query()
                    ->where('siswa_id', $siswa->id)
                    ->where('selesai', true)
                    ->whereIn('pertemuan_id', $pertemuanIds)
                    ->pluck('pertemuan_id')
                    ->all()
            );

        $grouped = $pertemuanRows->groupBy('kelas_digital_id');
        $summary = [];

        foreach ($kelasIds as $kid) {
            $rows = $grouped->get($kid, collect());
            $total = $rows->count();
            $selesai = $rows->filter(fn ($r) => isset($doneSet[$r->pertemuan_id]))->count();
            $summary[$kid] = [
                'selesai' => $selesai,
                'total' => $total,
                'persen' => $total > 0 ? (int) round(($selesai / $total) * 100) : 0,
            ];
        }

        return $summary;
    }

    /**
     * Hitung rekap progress siswa untuk halaman guru (bebas N+1).
     *
     * @param  Collection<int, Siswa>  $peserta
     * @param  array<int, int>  $pertemuanIds
     * @return array<int, array{selesai: int, total: int, persen: int}>
     */
    public function progressPerSiswa(Collection $peserta, array $pertemuanIds): array
    {
        $total = count($pertemuanIds);
        if ($peserta->isEmpty() || $total === 0) {
            return $peserta->mapWithKeys(fn (Siswa $s) => [
                $s->id => ['selesai' => 0, 'total' => $total, 'persen' => 0],
            ])->all();
        }

        $counts = KelasDigitalProgress::query()
            ->whereIn('siswa_id', $peserta->pluck('id')->all())
            ->whereIn('pertemuan_id', $pertemuanIds)
            ->where('selesai', true)
            ->selectRaw('siswa_id, COUNT(*) as jml')
            ->groupBy('siswa_id')
            ->pluck('jml', 'siswa_id');

        $out = [];
        foreach ($peserta as $s) {
            $selesai = (int) ($counts[$s->id] ?? 0);
            $out[$s->id] = [
                'selesai' => $selesai,
                'total' => $total,
                'persen' => (int) round(($selesai / $total) * 100),
            ];
        }

        return $out;
    }

    /**
     * Alirkan nilai tugas Kelas Digital ke tabel `nilai` (jenis = 'Tugas') agar
     * tampil di Daftar Nilai guru dan siap dihitung pada e-Rapor.
     */
    public function syncNilaiKeDaftarNilai(
        KelasDigitalPengumpulan $pengumpulan,
        KelasDigitalTugas $tugas,
        KelasDigital $kelas,
        User $guru,
        Siswa $siswa,
        float $nilaiSkala100,
    ): ?Nilai {
        if (! $tugas->sinkron_ke_nilai) {
            return null;
        }

        $skala = max(0, min(100, round($nilaiSkala100, 2)));
        $judulPenilaian = Str::limit('[Kelas Digital] '.$tugas->judul, 255, '');

        $nilaiRecord = $pengumpulan->nilai_id ? Nilai::find($pengumpulan->nilai_id) : null;

        if ($nilaiRecord) {
            $nilaiRecord->update([
                'tanggal' => now()->toDateString(),
                'judul_penilaian' => $judulPenilaian,
                'mata_pelajaran' => $kelas->mata_pelajaran,
                'nilai' => $skala,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswa->nama_peserta_didik,
            ]);
        } else {
            $nilaiRecord = Nilai::create([
                'tenant_id' => $kelas->tenant_id,
                'uuid' => (string) Str::uuid(),
                'guru_id' => $guru->id,
                'siswa_id' => $siswa->id,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswa->nama_peserta_didik,
                'tanggal' => now()->toDateString(),
                'jenis' => 'Tugas',
                'judul_penilaian' => $judulPenilaian,
                'mata_pelajaran' => $kelas->mata_pelajaran,
                'nilai' => $skala,
                'rekaman' => [
                    'sumber' => 'kelas_digital',
                    'kelas_digital_id' => $kelas->id,
                    'tugas_id' => $tugas->id,
                    'pengumpulan_id' => $pengumpulan->id,
                ],
            ]);
        }

        return $nilaiRecord;
    }

    /**
     * Daftar rombel & label kelas yang relevan untuk form pembuatan Kelas Digital guru.
     *
     * @return Collection<int, Rombel>
     */
    public function rombelOptionsForGuru(User $guru): Collection
    {
        return Rombel::query()
            ->when($guru->tenant_id !== null, fn (Builder $q) => $q->where('tenant_id', $guru->tenant_id))
            ->orderBy('nama_rombel')
            ->get();
    }
}
