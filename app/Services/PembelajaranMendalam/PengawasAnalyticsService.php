<?php

namespace App\Services\PembelajaranMendalam;

use App\Models\Kebiasaan;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\PembelajaranMendalam\KktpInterval;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\PembelajaranMendalam\VisitasiPengawas;
use App\Models\PengaturanGuru;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Collection;

class PengawasAnalyticsService
{
    public function __construct(
        private readonly SupervisiPmService $supervisiService,
    ) {}

    /**
     * Hitung Radar Mutu & Kepatuhan Satuan Pendidikan untuk Dashboard Eksekutif Pengawas.
     *
     * @return array<string, mixed>
     */
    public function radarMutu(?int $tenantId, string $tahunAjaran, int $semester): array
    {
        $rekapGuru = $this->supervisiService->ringkasanSemuaGuru($tenantId, $tahunAjaran, $semester);
        /** @var Collection $rows */
        $rows = $rekapGuru['rows'];
        $totalGuru = $rows->count();

        $sudahDisupervisiKepsek = $rows->filter(function (array $r): bool {
            return in_array($r['status'], [SupervisiPm::STATUS_DISETUJUI, SupervisiPm::STATUS_REVISI], true);
        })->count();

        $slaKepsek = $totalGuru > 0 ? (int) round(($sudahDisupervisiKepsek / $totalGuru) * 100) : 0;

        $terverifikasiPengawas = $rows->filter(function (array $r): bool {
            /** @var SupervisiPm|null $sup */
            $sup = $r['supervisi'] ?? null;

            return $sup && $sup->status_pengawas === SupervisiPm::PENGAWAS_TERVERIFIKASI;
        })->count();

        $catatanPembinaan = $rows->filter(function (array $r): bool {
            /** @var SupervisiPm|null $sup */
            $sup = $r['supervisi'] ?? null;

            return $sup && $sup->status_pengawas === SupervisiPm::PENGAWAS_PEMBINAAN;
        })->count();

        $persenVerifikasiPengawas = $totalGuru > 0
            ? (int) round((($terverifikasiPengawas + $catatanPembinaan) / $totalGuru) * 100)
            : 0;

        $totalSiswa = Siswa::query()
            ->where('aktif', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        $totalRombel = Rombel::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        $totalJurnal = JurnalMengajar::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        $totalKebiasaan = Kebiasaan::query()->count();

        $visitasiList = VisitasiPengawas::query()
            ->with(['guru:id,nama_lengkap', 'pengawas:id,nama_lengkap'])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->orderByDesc('tanggal_visitasi')
            ->orderByDesc('id')
            ->get();

        $rataSkorVisitasi = $visitasiList->isNotEmpty()
            ? round($visitasiList->avg(fn (VisitasiPengawas $v) => $v->rataRataSkor()), 2)
            : 0.0;

        $indeksKesehatan = (int) round(
            ($rekapGuru['kpi']['rata_kelengkapan'] * 0.5)
            + ($slaKepsek * 0.3)
            + ($persenVerifikasiPengawas * 0.2)
        );

        return [
            'kpi' => [
                'indeks_kesehatan' => $indeksKesehatan,
                'rata_kelengkapan_pm' => $rekapGuru['kpi']['rata_kelengkapan'],
                'sla_supervisi_kepsek' => $slaKepsek,
                'persen_verifikasi_pengawas' => $persenVerifikasiPengawas,
                'total_guru' => $totalGuru,
                'disetujui_kepsek' => $rekapGuru['kpi']['disetujui'],
                'revisi_kepsek' => $rekapGuru['kpi']['revisi'],
                'menunggu_kepsek' => $rekapGuru['kpi']['menunggu'],
                'terverifikasi_pengawas' => $terverifikasiPengawas,
                'pembinaan_pengawas' => $catatanPembinaan,
                'total_siswa' => $totalSiswa,
                'total_rombel' => $totalRombel,
                'total_jurnal' => $totalJurnal,
                'total_kebiasaan' => $totalKebiasaan,
                'total_visitasi' => $visitasiList->count(),
                'rata_skor_visitasi' => $rataSkorVisitasi,
            ],
            'guru_rows' => $rows,
            'visitasi_terbaru' => $visitasiList->take(5),
        ];
    }

    /**
     * Audit Keselarasan Kurikulum (TP & KKTP Pembelajaran Mendalam <-> PengaturanGuru & Nilai e-Rapor).
     *
     * @return array{
     *     guru_audit: Collection,
     *     peta_kelas: array<int, array<string, mixed>>,
     *     ringkasan: array{total_guru: int, guru_sinkron_tp: int, guru_selaras_kktp: int, guru_aktif_menilai: int}
     * }
     */
    public function auditKeselarasan(?int $tenantId, string $tahunAjaran, int $semester): array
    {
        $gurus = User::query()
            ->where('peran', 'guru')
            ->where('aktif', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_lengkap')
            ->get(['id', 'uuid', 'nama_lengkap', 'kelas_mata_pelajaran', 'is_wali_kelas']);

        $guruIds = $gurus->pluck('id')->all();

        $tpCounts = empty($guruIds) ? [] : TujuanPembelajaranPm::query()
            ->whereIn('guru_id', $guruIds)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('guru_id, COUNT(*) as total')
            ->groupBy('guru_id')
            ->pluck('total', 'guru_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $pengaturanMap = empty($guruIds) ? collect() : PengaturanGuru::query()
            ->whereIn('guru_id', $guruIds)
            ->get()
            ->keyBy('guru_id');

        $kktpIntervals = KktpInterval::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $nilaiCounts = empty($guruIds) ? [] : Nilai::query()
            ->whereIn('guru_id', $guruIds)
            ->selectRaw('guru_id, COUNT(*) as total')
            ->groupBy('guru_id')
            ->pluck('total', 'guru_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $nilaiEraporCounts = empty($guruIds) ? [] : NilaiErapot::query()
            ->whereIn('guru_id', $guruIds)
            ->selectRaw('guru_id, COUNT(*) as total')
            ->groupBy('guru_id')
            ->pluck('total', 'guru_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $guruAudit = $gurus->map(function (User $guru) use (
            $tpCounts, $pengaturanMap, $kktpIntervals, $nilaiCounts, $nilaiEraporCounts
        ): array {
            $tpPm = $tpCounts[$guru->id] ?? 0;
            /** @var PengaturanGuru|null $peng */
            $peng = $pengaturanMap->get($guru->id);
            $cfg = is_array($peng?->pengaturan) ? $peng->pengaturan : [];
            $tpErapor = is_array($cfg['tujuan_pembelajaran'] ?? null) ? count($cfg['tujuan_pembelajaran']) : 0;
            $kkmErapor = (int) ($peng?->kkm ?? 75);

            $kktpGuru = $kktpIntervals->where('updated_by', $guru->id)->first();
            $nilaiKktp = $kktpGuru ? (int) $kktpGuru->nilai_kktp : $kkmErapor;
            $selarasKktp = $nilaiKktp === $kkmErapor;

            $totalEntriNilai = ($nilaiCounts[$guru->id] ?? 0) + ($nilaiEraporCounts[$guru->id] ?? 0);
            $sinkronTp = $tpPm > 0 && $tpErapor > 0;

            $temuan = [];
            if ($tpPm === 0) {
                $temuan[] = 'Belum menyusun Bank TP Pembelajaran Mendalam';
            } elseif ($tpErapor === 0) {
                $temuan[] = 'TP Pembelajaran Mendalam belum disinkronkan ke TP e-Rapor';
            }
            if (! $selarasKktp) {
                $temuan[] = "Nilai KKTP ({$nilaiKktp}) berbeda dengan KKM e-Rapor ({$kkmErapor})";
            }
            if ($totalEntriNilai === 0) {
                $temuan[] = 'Belum ada entri nilai formatif/sumatif e-Rapor';
            }

            return [
                'guru' => $guru,
                'tp_pm' => $tpPm,
                'tp_erapor' => $tpErapor,
                'sinkron_tp' => $sinkronTp,
                'nilai_kktp' => $nilaiKktp,
                'kkm_erapor' => $kkmErapor,
                'selaras_kktp' => $selarasKktp,
                'total_entri_nilai' => $totalEntriNilai,
                'temuan' => $temuan,
                'status_audit' => empty($temuan) ? 'selaras' : 'perlu_tindak_lanjut',
            ];
        });

        // Peta Ketuntasan & Rata-rata Hasil Belajar per Kelas 1..6
        $siswaByTingkat = Siswa::query()
            ->where('aktif', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'kelas'])
            ->groupBy(function (Siswa $s): int {
                if ($s->kelas && preg_match('/([1-6])/', (string) $s->kelas, $m)) {
                    return (int) $m[1];
                }

                return 1;
            });

        $petaKelas = [];
        for ($t = 1; $t <= 6; $t++) {
            $siswaIds = ($siswaByTingkat->get($t) ?? collect())->pluck('id')->all();
            $rataNilai = 0.0;
            if (! empty($siswaIds)) {
                $avgErapor = NilaiErapot::query()->whereIn('siswa_id', $siswaIds)->avg('nilai_rapor');
                if ($avgErapor !== null) {
                    $rataNilai = round((float) $avgErapor, 1);
                } else {
                    $avgHarian = Nilai::query()->whereIn('siswa_id', $siswaIds)->avg('nilai');
                    $rataNilai = $avgHarian !== null ? round((float) $avgHarian, 1) : 0.0;
                }
            }
            $kktpTingkat = (int) round((float) ($kktpIntervals->where('tingkat', $t)->avg('nilai_kktp') ?: 75));

            $petaKelas[] = [
                'tingkat' => $t,
                'fase' => TujuanPembelajaranPm::faseUntukKelas($t),
                'jumlah_siswa' => count($siswaIds),
                'kktp_rata' => $kktpTingkat,
                'rata_nilai' => $rataNilai,
                'status_ketuntasan' => $rataNilai <= 0
                    ? 'Belum Ada Nilai'
                    : ($rataNilai >= $kktpTingkat ? 'Tuntas Di Atas KKTP' : 'Di Bawah KKTP (Perlu Pendampingan)'),
            ];
        }

        return [
            'guru_audit' => $guruAudit,
            'peta_kelas' => $petaKelas,
            'ringkasan' => [
                'total_guru' => $guruAudit->count(),
                'guru_sinkron_tp' => $guruAudit->where('sinkron_tp', true)->count(),
                'guru_selaras_kktp' => $guruAudit->where('selaras_kktp', true)->count(),
                'guru_aktif_menilai' => $guruAudit->where('total_entri_nilai', '>', 0)->count(),
            ],
        ];
    }
}
