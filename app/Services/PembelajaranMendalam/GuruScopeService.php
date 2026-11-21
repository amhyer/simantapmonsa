<?php

namespace App\Services\PembelajaranMendalam;

use App\Models\JadwalPelajaran;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\PengaturanGuru;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menentukan cakupan ketat (Kelas / Tingkat / Fase / Rombel / Mata Pelajaran) yang diampu seorang Guru.
 *
 * Sumber otoritatif:
 *  1. `rombel` (guru_id = guru) -> Wali Kelas / Guru Kelas SD
 *  2. `jadwal_pelajaran` (guru_id = guru) -> Guru Mapel & jadwal mengajar aktual
 *  3. Fallback `siswa` (guru_id = guru) / `pengaturan_guru` bila rombel & jadwal belum terisi
 *
 * Jika guru memiliki penugasan eksplisit (`is_scoped = true`), guru HANYA dapat melihat & mengubah
 * data pada tingkat/fase/rombel (dan mapel untuk Guru Mapel) miliknya sendiri.
 */
class GuruScopeService
{
    /**
     * @return array{
     *     is_scoped: bool,
     *     is_wali_kelas: bool,
     *     tingkat_list: array<int, int>,
     *     fase_list: array<int, string>,
     *     rombel_ids: array<int, int>,
     *     kelas_list: array<int, string>,
     *     mapel_list: array<int, string>
     * }
     */
    public function resolveForGuru(User $guru, ?int $tenantId = null): array
    {
        $rombels = Rombel::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'nama_rombel', 'tingkat']);

        $jadwals = JadwalPelajaran::query()
            ->with('rombel:id,nama_rombel,tingkat')
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['rombel_id', 'kelas', 'mata_pelajaran']);

        $tingkatSet = [];
        $rombelIds = [];
        $kelasSet = [];
        $mapelSet = [];

        foreach ($rombels as $r) {
            $rombelIds[] = (int) $r->id;
            if (! empty($r->nama_rombel)) {
                $kelasSet[] = trim((string) $r->nama_rombel);
            }
            $t = $this->parseTingkat($r->tingkat, $r->nama_rombel);
            if ($t !== null) {
                $tingkatSet[] = $t;
            }
        }

        foreach ($jadwals as $j) {
            if ($j->rombel_id) {
                $rombelIds[] = (int) $j->rombel_id;
            }
            $namaKelas = $j->rombel?->nama_rombel ?: $j->kelas;
            if (! empty($namaKelas)) {
                $kelasSet[] = trim((string) $namaKelas);
            }
            $t = $this->parseTingkat($j->rombel?->tingkat, $namaKelas);
            if ($t !== null) {
                $tingkatSet[] = $t;
            }
            if (! empty($j->mata_pelajaran)) {
                $mapelSet[] = trim((string) $j->mata_pelajaran);
            }
        }

        $isScoped = $rombels->isNotEmpty() || $jadwals->isNotEmpty();

        // Fallback ringan bila rombel & jadwal belum diinput sama sekali
        if (! $isScoped) {
            $siswaKelas = Siswa::query()
                ->where('guru_id', $guru->id)
                ->where('aktif', true)
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->whereNotNull('kelas')
                ->distinct()
                ->pluck('kelas');

            foreach ($siswaKelas as $kls) {
                $kelasSet[] = trim((string) $kls);
                $t = $this->parseTingkat(null, (string) $kls);
                if ($t !== null) {
                    $tingkatSet[] = $t;
                }
            }

            if (empty($tingkatSet)) {
                $peng = PengaturanGuru::query()->where('guru_id', $guru->id)->first(['kelas', 'mata_pelajaran']);
                if ($peng && ! empty($peng->kelas)) {
                    $t = $this->parseTingkat(null, (string) $peng->kelas);
                    if ($t !== null) {
                        $tingkatSet[] = $t;
                        $kelasSet[] = trim((string) $peng->kelas);
                    }
                }
            }
        }

        $tingkatList = array_values(array_unique(array_filter($tingkatSet, fn (int $t) => $t >= 1 && $t <= 6)));
        sort($tingkatList);

        if (empty($tingkatList)) {
            $tingkatList = [1, 2, 3, 4, 5, 6];
        }

        $faseList = array_values(array_unique(array_map(
            fn (int $t) => TujuanPembelajaranPm::faseUntukKelas($t),
            $tingkatList
        )));
        sort($faseList);

        return [
            'is_scoped' => $isScoped,
            'is_wali_kelas' => $guru->isWaliKelas(),
            'tingkat_list' => $tingkatList,
            'fase_list' => $faseList,
            'rombel_ids' => array_values(array_unique($rombelIds)),
            'kelas_list' => array_values(array_unique(array_filter($kelasSet))),
            'mapel_list' => array_values(array_unique(array_filter($mapelSet))),
        ];
    }

    public function authorizeTingkat(User $guru, int $tingkat, ?int $tenantId = null): void
    {
        $scope = $this->resolveForGuru($guru, $tenantId);
        if (! $scope['is_scoped']) {
            return;
        }

        if (! in_array($tingkat, $scope['tingkat_list'], true)) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengakses kelas yang Anda ampu (Kelas '.implode(', ', $scope['tingkat_list']).').');
        }
    }

    public function authorizeFase(User $guru, string $fase, ?int $tenantId = null): void
    {
        $scope = $this->resolveForGuru($guru, $tenantId);
        if (! $scope['is_scoped']) {
            return;
        }

        if (! in_array(strtoupper($fase), $scope['fase_list'], true)) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengakses Fase '.implode(', ', $scope['fase_list']).' sesuai kelas yang Anda ampu.');
        }
    }

    /**
     * Untuk Guru Mata Pelajaran (bukan Wali Kelas) yang memiliki jadwal mapel, batasi hanya pada mapel yang diajarnya.
     */
    public function authorizeMapel(User $guru, string $namaMapel, ?int $tenantId = null): void
    {
        $scope = $this->resolveForGuru($guru, $tenantId);
        if (! $scope['is_scoped'] || $scope['is_wali_kelas'] || empty($scope['mapel_list'])) {
            return;
        }

        $target = mb_strtolower(trim($namaMapel));
        $allowed = collect($scope['mapel_list'])->contains(
            fn (string $m) => mb_strtolower(trim($m)) === $target
        );

        if (! $allowed) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengakses mata pelajaran yang Anda ampu ('.implode(', ', $scope['mapel_list']).').');
        }
    }

    /**
     * Validasi nama kelas/rombel (misal "1A" atau "1") berada pada kelas/tingkat yang diampu guru.
     */
    public function authorizeKelas(User $guru, string $kelas, ?int $tenantId = null): void
    {
        $scope = $this->resolveForGuru($guru, $tenantId);
        if (! $scope['is_scoped']) {
            return;
        }

        $target = mb_strtolower(trim($kelas));
        $allowedKelas = collect($scope['kelas_list'])->contains(
            fn (string $k) => mb_strtolower(trim($k)) === $target
        );

        if ($allowedKelas) {
            return;
        }

        $tingkat = $this->parseTingkat(null, $kelas);
        if ($tingkat !== null && in_array($tingkat, $scope['tingkat_list'], true)) {
            return;
        }

        abort(403, 'Akses ditolak: Anda hanya dapat mengakses kelas yang Anda ampu.');
    }

    /**
     * Tolak mutasi/overwrite perangkat pembelajaran apabila sudah disetujui Kepala Sekolah (status = disetujui).
     */
    public function authorizeNotLockedBySupervisi(
        User $guru,
        ?int $tenantId = null,
        ?string $tahunAjaran = null,
        ?int $semester = null
    ): void {
        $locked = SupervisiPm::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($tahunAjaran !== null && $tahunAjaran !== '', fn ($q) => $q->where('tahun_ajaran', $tahunAjaran))
            ->when($semester !== null && in_array($semester, [1, 2], true), fn ($q) => $q->where('semester', $semester))
            ->where('status', SupervisiPm::STATUS_DISETUJUI)
            ->exists();

        if ($locked) {
            abort(403, 'Akses ditolak: Dokumen perangkat pembelajaran Anda telah disetujui Kepala Sekolah dan dikunci.');
        }
    }

    /**
     * Saring daftar mapel untuk dropdown/tab UI (khusus Guru Mapel hanya tampil mapel yang diampunya).
     */
    public function filterMapelCollection(Collection $mapelList, User $guru, ?int $tenantId = null): Collection
    {
        $scope = $this->resolveForGuru($guru, $tenantId);
        if (! $scope['is_scoped'] || $scope['is_wali_kelas'] || empty($scope['mapel_list'])) {
            return $mapelList;
        }

        $allowedLower = array_map(fn (string $m) => mb_strtolower(trim($m)), $scope['mapel_list']);
        $filtered = $mapelList->filter(
            fn ($m) => in_array(mb_strtolower(trim((string) $m->nama)), $allowedLower, true)
        )->values();

        if ($filtered->isNotEmpty()) {
            return $filtered;
        }

        return collect($scope['mapel_list'])->map(fn (string $nama) => (object) [
            'id' => null,
            'nama' => $nama,
            'kode' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nama) ?: 'MP', 0, 5)),
        ]);
    }

    /**
     * Daftar rombel untuk dropdown form guru: hanya rombel yang diampu guru bila `is_scoped = true`.
     */
    public function rombelOptionsForGuru(User $guru, ?int $tenantId = null): Collection
    {
        $scope = $this->resolveForGuru($guru, $tenantId);

        $query = Rombel::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_rombel');

        if ($scope['is_scoped'] && ! empty($scope['rombel_ids'])) {
            $query->whereIn('id', $scope['rombel_ids']);
        }

        return $query->get(['id', 'nama_rombel', 'tingkat']);
    }

    private function parseTingkat(mixed $tingkat, ?string $namaKelas): ?int
    {
        if (is_numeric($tingkat)) {
            $n = (int) $tingkat;
            if ($n >= 1 && $n <= 6) {
                return $n;
            }
        }

        if ($namaKelas && preg_match('/([1-6])/', $namaKelas, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
