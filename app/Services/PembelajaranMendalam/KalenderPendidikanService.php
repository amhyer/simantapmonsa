<?php

namespace App\Services\PembelajaranMendalam;

use App\Models\PembelajaranMendalam\AlokasiWaktu;
use App\Models\PembelajaranMendalam\KalenderPendidikan;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use Illuminate\Support\Collection;

class KalenderPendidikanService
{
    /**
     * Pastikan kalender 12 bulan untuk tahun ajaran tersedia.
     */
    public function ensureDefaults(?int $tenantId, string $tahunAjaran, ?int $userId = null): void
    {
        $exists = KalenderPendidikan::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->exists();

        if ($exists) {
            return;
        }

        $this->isiDefault($tenantId, $tahunAjaran, $userId);
    }

    public function isiDefault(?int $tenantId, string $tahunAjaran, ?int $userId = null): void
    {
        $parts = explode('/', $tahunAjaran);
        $tahunAwal = isset($parts[0]) && is_numeric($parts[0]) ? (int) $parts[0] : (int) date('Y');
        $tahunAkhir = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : ($tahunAwal + 1);

        // Pola standar kalender pendidikan SD Indonesia (~18 minggu efektif Sem 1, ~18 minggu efektif Sem 2)
        $defaults = [
            // Semester 1 (Jul–Des)
            ['sem' => 1, 'bulan' => 7, 'tahun' => $tahunAwal, 'mgg' => 5, 'non' => 2, 'hari' => 15, 'libur' => [1, 2], 'ket' => 'Libur Kenaikan Kelas & MPLS'],
            ['sem' => 1, 'bulan' => 8, 'tahun' => $tahunAwal, 'mgg' => 4, 'non' => 0, 'hari' => 21, 'libur' => [], 'ket' => 'Efektif penuh'],
            ['sem' => 1, 'bulan' => 9, 'tahun' => $tahunAwal, 'mgg' => 4, 'non' => 0, 'hari' => 21, 'libur' => [], 'ket' => 'STS / Efektif'],
            ['sem' => 1, 'bulan' => 10, 'tahun' => $tahunAwal, 'mgg' => 5, 'non' => 0, 'hari' => 23, 'libur' => [], 'ket' => 'Efektif penuh'],
            ['sem' => 1, 'bulan' => 11, 'tahun' => $tahunAwal, 'mgg' => 4, 'non' => 0, 'hari' => 21, 'libur' => [], 'ket' => 'Efektif penuh'],
            ['sem' => 1, 'bulan' => 12, 'tahun' => $tahunAwal, 'mgg' => 4, 'non' => 3, 'hari' => 5, 'libur' => [2, 3, 4], 'ket' => 'SAS, Rapor & Libur Semester 1'],
            // Semester 2 (Jan–Jun)
            ['sem' => 2, 'bulan' => 1, 'tahun' => $tahunAkhir, 'mgg' => 4, 'non' => 0, 'hari' => 20, 'libur' => [], 'ket' => 'Awal Semester Genap'],
            ['sem' => 2, 'bulan' => 2, 'tahun' => $tahunAkhir, 'mgg' => 4, 'non' => 0, 'hari' => 19, 'libur' => [], 'ket' => 'Efektif penuh'],
            ['sem' => 2, 'bulan' => 3, 'tahun' => $tahunAkhir, 'mgg' => 4, 'non' => 1, 'hari' => 16, 'libur' => [4], 'ket' => 'STS / Libur Hari Raya'],
            ['sem' => 2, 'bulan' => 4, 'tahun' => $tahunAkhir, 'mgg' => 5, 'non' => 1, 'hari' => 19, 'libur' => [1], 'ket' => 'Libur Hari Raya & Efektif'],
            ['sem' => 2, 'bulan' => 5, 'tahun' => $tahunAkhir, 'mgg' => 4, 'non' => 0, 'hari' => 20, 'libur' => [], 'ket' => 'Efektif / Persiapan SAT'],
            ['sem' => 2, 'bulan' => 6, 'tahun' => $tahunAkhir, 'mgg' => 4, 'non' => 3, 'hari' => 5, 'libur' => [2, 3, 4], 'ket' => 'SAT, Rapor & Libur Kenaikan Kelas'],
        ];

        foreach ($defaults as $d) {
            KalenderPendidikan::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => $d['sem'],
                    'bulan' => $d['bulan'],
                ],
                [
                    'tahun' => $d['tahun'],
                    'jumlah_minggu' => $d['mgg'],
                    'minggu_tidak_efektif' => $d['non'],
                    'hari_efektif' => $d['hari'],
                    'minggu_libur' => $d['libur'],
                    'keterangan' => $d['ket'],
                    'updated_by' => $userId,
                ]
            );
        }
    }

    /**
     * Ambil ringkasan kalender per semester dan hitung JP tersedia.
     *
     * @return array{rows: Collection, total_minggu: int, total_tidak_efektif: int, total_efektif: int, total_hari_efektif: int, jp_tersedia: int}
     */
    public function ringkasanSemester(?int $tenantId, string $tahunAjaran, int $semester, int $jpPerMinggu): array
    {
        $this->ensureDefaults($tenantId, $tahunAjaran);
        $bulanUrut = KalenderPendidikan::bulanUntukSemester($semester);

        $rows = KalenderPendidikan::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->get()
            ->sortBy(fn (KalenderPendidikan $k) => array_search($k->bulan, $bulanUrut, true))
            ->values();

        $totalMinggu = (int) $rows->sum('jumlah_minggu');
        $totalTidak = (int) $rows->sum('minggu_tidak_efektif');
        $totalEfektif = max(0, $totalMinggu - $totalTidak);
        $totalHari = (int) $rows->sum('hari_efektif');

        return [
            'rows' => $rows,
            'total_minggu' => $totalMinggu,
            'total_tidak_efektif' => $totalTidak,
            'total_efektif' => $totalEfektif,
            'total_hari_efektif' => $totalHari,
            'jp_tersedia' => $totalEfektif * max(1, $jpPerMinggu),
        ];
    }

    public function jpPerMingguMapel(?int $tenantId, string $tahunAjaran, string $namaMapel, int $tingkat): int
    {
        $row = AlokasiWaktu::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('nama_mapel', $namaMapel)
            ->where('tingkat', $tingkat)
            ->first();

        if ($row && $row->jp_intra > 0) {
            return (int) $row->jp_intra;
        }

        return AlokasiWaktu::STANDAR_PERMENDIKDASMEN_2025[$namaMapel][$tingkat][0] ?? 4;
    }

    /**
     * Distribusikan baris Prota ke minggu-minggu efektif Prosem secara otomatis.
     */
    public function susunProsemOtomatis(
        ?int $tenantId,
        int $guruId,
        string $tahunAjaran,
        int $tingkat,
        string $namaMapel,
        int $semester,
        int $jpPerMinggu
    ): int {
        $jpPerMinggu = max(1, min(20, $jpPerMinggu));
        $ringkasan = $this->ringkasanSemester($tenantId, $tahunAjaran, $semester, $jpPerMinggu);

        // Bangun daftar slot minggu efektif (bulan, minggu_ke)
        $slots = [];
        foreach ($ringkasan['rows'] as $kal) {
            /** @var KalenderPendidikan $kal */
            $libur = is_array($kal->minggu_libur) ? array_map('intval', $kal->minggu_libur) : [];
            for ($m = 1; $m <= $kal->jumlah_minggu; $m++) {
                if (in_array($m, $libur, true)) {
                    continue;
                }
                $slots[] = ['bulan' => $kal->bulan, 'minggu_ke' => $m, 'sisa_jp' => $jpPerMinggu];
            }
        }

        if (empty($slots)) {
            return 0;
        }

        $protaRows = ProtaItem::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $namaMapel)
            ->where('semester', $semester)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        if ($protaRows->isEmpty()) {
            return 0;
        }

        // Bersihkan prosem lama untuk scope ini sebelum menyusun ulang
        ProsemItem::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $namaMapel)
            ->where('semester', $semester)
            ->delete();

        $slotIdx = 0;
        $created = 0;

        foreach ($protaRows as $idx => $prota) {
            $butuhJp = max(1, (int) $prota->alokasi_jp);
            $babLabel = 'Bab '.($idx + 1);

            while ($butuhJp > 0 && $slotIdx < count($slots)) {
                $slot = &$slots[$slotIdx];
                if ($slot['sisa_jp'] <= 0) {
                    $slotIdx++;
                    continue;
                }

                $ambil = min($butuhJp, $slot['sisa_jp']);
                ProsemItem::create([
                    'tenant_id' => $tenantId,
                    'guru_id' => $guruId,
                    'tahun_ajaran' => $tahunAjaran,
                    'tingkat' => $tingkat,
                    'nama_mapel' => $namaMapel,
                    'semester' => $semester,
                    'prota_id' => $prota->id,
                    'tp_id' => $prota->tp_id,
                    'bab' => $babLabel,
                    'materi' => $prota->materi,
                    'bulan' => $slot['bulan'],
                    'minggu_ke' => $slot['minggu_ke'],
                    'alokasi_jp' => $ambil,
                    'keterangan' => $prota->keterangan,
                ]);

                $slot['sisa_jp'] -= $ambil;
                $butuhJp -= $ambil;
                $created++;

                if ($slot['sisa_jp'] <= 0) {
                    $slotIdx++;
                }
            }
        }

        return $created;
    }
}
