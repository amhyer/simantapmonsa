<?php

namespace App\Services\PembelajaranMendalam;

use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KktpItem;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\User;
use Illuminate\Support\Collection;

class SupervisiPmService
{
    /**
     * Hitung matriks kelengkapan 9 dokumen Pembelajaran Mendalam untuk seluruh guru dalam tenant aktif.
     *
     * @return array{
     *     rows: Collection,
     *     kpi: array{total_guru: int, rata_kelengkapan: int, disetujui: int, revisi: int, menunggu: int}
     * }
     */
    public function ringkasanSemuaGuru(
        ?int $tenantId,
        string $tahunAjaran,
        int $semester,
        string $cari = '',
        string $statusFilter = ''
    ): array {
        $guruQuery = User::query()
            ->where('peran', 'guru')
            ->where('aktif', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($cari !== '', fn ($q) => $q->where('nama_lengkap', 'like', "%{$cari}%"))
            ->orderBy('nama_lengkap');

        $gurus = $guruQuery->get(['id', 'uuid', 'nama_lengkap', 'kelas_mata_pelajaran', 'is_wali_kelas']);
        $guruIds = $gurus->pluck('id')->all();

        if (empty($guruIds)) {
            return [
                'rows' => collect(),
                'kpi' => ['total_guru' => 0, 'rata_kelengkapan' => 0, 'disetujui' => 0, 'revisi' => 0, 'menunggu' => 0],
            ];
        }

        $countByGuru = function (string $modelClass, ?callable $extra = null) use ($tenantId, $guruIds): array {
            $q = $modelClass::query()
                ->whereIn('guru_id', $guruIds)
                ->when($tenantId !== null, fn ($qq) => $qq->where('tenant_id', $tenantId));
            if ($extra) {
                $extra($q);
            }

            return $q->selectRaw('guru_id, COUNT(*) as total')
                ->groupBy('guru_id')
                ->pluck('total', 'guru_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        };

        $cpCounts = $countByGuru(CapaianPembelajaran::class);
        $tpCounts = $countByGuru(TujuanPembelajaranPm::class);
        $atpCounts = $countByGuru(TujuanPembelajaranPm::class, fn ($q) => $q->whereNotNull('kelas_target'));
        $kktpCounts = $countByGuru(KktpItem::class);
        $protaCounts = $countByGuru(ProtaItem::class, fn ($q) => $q->where('tahun_ajaran', $tahunAjaran));
        $prosemCounts = $countByGuru(ProsemItem::class, fn ($q) => $q->where('tahun_ajaran', $tahunAjaran)->where('semester', $semester));
        $rpmCounts = $countByGuru(PerangkatPembelajaran::class, fn ($q) => $q->where('jenis', PerangkatPembelajaran::JENIS_MODUL_AJAR));
        $jurnalCounts = $countByGuru(JurnalMengajar::class);
        $kokuCounts = $countByGuru(KokurikulerPm::class, fn ($q) => $q->where('tahun_ajaran', $tahunAjaran));

        $supervisiMap = SupervisiPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->whereIn('guru_id', $guruIds)
            ->get()
            ->keyBy('guru_id');

        $rows = $gurus->map(function (User $guru) use (
            $cpCounts, $tpCounts, $atpCounts, $kktpCounts,
            $protaCounts, $prosemCounts, $rpmCounts, $jurnalCounts, $kokuCounts,
            $supervisiMap
        ) {
            $counts = [
                'cp' => $cpCounts[$guru->id] ?? 0,
                'tp' => $tpCounts[$guru->id] ?? 0,
                'atp' => $atpCounts[$guru->id] ?? 0,
                'kktp' => $kktpCounts[$guru->id] ?? 0,
                'prota' => $protaCounts[$guru->id] ?? 0,
                'prosem' => $prosemCounts[$guru->id] ?? 0,
                'rpm' => $rpmCounts[$guru->id] ?? 0,
                'jurnal' => $jurnalCounts[$guru->id] ?? 0,
                'kokurikuler' => $kokuCounts[$guru->id] ?? 0,
            ];

            $terisi = collect($counts)->filter(fn (int $c) => $c > 0)->count();
            $persen = (int) round(($terisi / 9) * 100);
            /** @var SupervisiPm|null $sup */
            $sup = $supervisiMap->get($guru->id);
            $status = $sup?->status ?? SupervisiPm::STATUS_MENUNGGU;

            return [
                'guru' => $guru,
                'counts' => $counts,
                'terisi' => $terisi,
                'persen' => $persen,
                'status' => $status,
                'status_label' => SupervisiPm::STATUS_LABELS[$status] ?? ucfirst($status),
                'status_badge' => SupervisiPm::STATUS_BADGES[$status] ?? 'badge-warning',
                'supervisi' => $sup,
            ];
        });

        $kpi = [
            'total_guru' => $rows->count(),
            'rata_kelengkapan' => $rows->isNotEmpty() ? (int) round($rows->avg('persen')) : 0,
            'disetujui' => $rows->where('status', SupervisiPm::STATUS_DISETUJUI)->count(),
            'revisi' => $rows->where('status', SupervisiPm::STATUS_REVISI)->count(),
            'menunggu' => $rows->whereIn('status', [SupervisiPm::STATUS_MENUNGGU, SupervisiPm::STATUS_DIREVIEW])->count(),
        ];

        if ($statusFilter !== '' && array_key_exists($statusFilter, SupervisiPm::STATUS_LABELS)) {
            $rows = $rows->where('status', $statusFilter)->values();
        }

        return [
            'rows' => $rows,
            'kpi' => $kpi,
        ];
    }

    /**
     * Ambil seluruh rincian 9 dokumen Pembelajaran Mendalam milik satu guru (read-only untuk supervisi Kepsek).
     *
     * @return array<string, mixed>
     */
    public function detailGuru(?int $tenantId, User $guru, string $tahunAjaran, int $semester): array
    {
        $cp = CapaianPembelajaran::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_mapel')->orderBy('fase')->orderBy('urutan')->get();

        $tp = TujuanPembelajaranPm::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_mapel')->orderBy('fase')->orderBy('urutan')->get();

        $atp = $tp->filter(fn (TujuanPembelajaranPm $item) => $item->kelas_target !== null)->values();

        $kktp = KktpItem::query()
            ->with('tp:id,nama_mapel,kode_tp,deskripsi')
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $prota = ProtaItem::query()
            ->where('guru_id', $guru->id)
            ->where('tahun_ajaran', $tahunAjaran)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_mapel')->orderBy('semester')->orderBy('urutan')->get();

        $prosem = ProsemItem::query()
            ->where('guru_id', $guru->id)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_mapel')->orderBy('bulan')->orderBy('minggu_ke')->get();

        $rpm = PerangkatPembelajaran::query()
            ->where('guru_id', $guru->id)
            ->where('jenis', PerangkatPembelajaran::JENIS_MODUL_AJAR)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('updated_at')->get();

        $jurnal = JurnalMengajar::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('tanggal')->limit(50)->get();

        $kokurikuler = KokurikulerPm::query()
            ->where('guru_id', $guru->id)
            ->where('tahun_ajaran', $tahunAjaran)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('tanggal_mulai')->get();

        $counts = [
            'cp' => $cp->count(),
            'tp' => $tp->count(),
            'atp' => $atp->count(),
            'kktp' => $kktp->count(),
            'prota' => $prota->count(),
            'prosem' => $prosem->count(),
            'rpm' => $rpm->count(),
            'jurnal' => $jurnal->count(),
            'kokurikuler' => $kokurikuler->count(),
        ];

        $terisi = collect($counts)->filter(fn (int $c) => $c > 0)->count();
        $persen = (int) round(($terisi / 9) * 100);

        $supervisi = SupervisiPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('guru_id', $guru->id)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->first();

        return compact(
            'cp', 'tp', 'atp', 'kktp', 'prota', 'prosem', 'rpm', 'jurnal', 'kokurikuler',
            'counts', 'terisi', 'persen', 'supervisi'
        );
    }
}
