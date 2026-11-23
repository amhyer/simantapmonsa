<?php

namespace App\Services\PembelajaranMendalam;

use App\Models\JadwalPelajaran;
use App\Models\PembelajaranMendalam\HariLibur;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GeneratorRencanaService
{
    public const HARI_ISO = [
        'senin' => 1,
        'selasa' => 2,
        'rabu' => 3,
        'kamis' => 4,
        'jumat' => 5,
        'jum\'at' => 5,
        'sabtu' => 6,
        'minggu' => 7,
    ];

    public const NAMA_HARI_ISO = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /**
     * Ambil ringkasan hari mengajar per mata pelajaran dari tabel jadwal_pelajaran.
     *
     * @return array<string, array<int, array{iso: int, hari: string, jam: string}>>
     */
    public function jadwalGuruPerMapel(?int $tenantId, int $guruId, ?string $kelasFilter = null): array
    {
        $rows = JadwalPelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(function ($q) use ($guruId): void {
                $q->where('guru_id', $guruId)->orWhereNull('guru_id');
            })
            ->when($kelasFilter !== null && trim($kelasFilter) !== '', function ($q) use ($kelasFilter): void {
                $q->where(function ($qq) use ($kelasFilter): void {
                    $qq->where('kelas', $kelasFilter)->orWhereNull('kelas')->orWhere('kelas', '');
                });
            })
            ->get(['mata_pelajaran', 'hari', 'jam_mulai', 'jam_selesai']);

        $map = [];
        foreach ($rows as $row) {
            $mapelKey = mb_strtolower(trim((string) $row->mata_pelajaran));
            $hariKey = mb_strtolower(trim((string) $row->hari));
            $iso = self::HARI_ISO[$hariKey] ?? null;
            if ($mapelKey === '' || $iso === null) {
                continue;
            }
            // PostgreSQL mengembalikan kolom time sebagai "07:30:00", SQLite
            // sebagai "07:30". Normalisasi ke H:i agar label jam konsisten.
            $jam = $this->formatJam($row->jam_mulai);
            $jamSelesai = $this->formatJam($row->jam_selesai);
            if ($jam !== '' && $jamSelesai !== '') {
                $jam .= '-'.$jamSelesai;
            }
            $map[$mapelKey][$iso] = [
                'iso' => $iso,
                'hari' => self::NAMA_HARI_ISO[$iso] ?? (string) $row->hari,
                'jam' => $jam !== '' ? $jam : '1-2',
            ];
        }

        foreach ($map as $k => $items) {
            ksort($items);
            $map[$k] = array_values($items);
        }

        return $map;
    }

    /** Normalisasi nilai kolom time (SQLite "07:30" / PostgreSQL "07:30:00") ke H:i. */
    private function formatJam(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Susun rencana pertemuan batch menggabungkan Jadwal Pelajaran + Hari Libur + Prosem/Prota/ATP.
     *
     * @param  array<int, string>  $mapelDipilih
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     ringkasan: array{total_pertemuan: int, libur_dilewati: int, pakai_jadwal: int, fallback_efektif: int}
     * }
     */
    public function susunRencana(
        ?int $tenantId,
        int $guruId,
        string $tahunAjaran,
        int $tingkat,
        int $semester,
        ?string $kelasLabel,
        array $mapelDipilih,
        string $dariTanggal,
        string $sampaiTanggal,
        string $mode = 'cepat'
    ): array {
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);

        $liburRows = HariLibur::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->get(['tanggal_mulai', 'tanggal_selesai']);

        $jadwalMap = $this->jadwalGuruPerMapel($tenantId, $guruId, $kelasLabel);

        // Kumpulkan semua tanggal kerja non-libur dalam rentang
        $hariEfektifSemua = [];
        $liburDilewati = 0;
        foreach (CarbonPeriod::create($dariTanggal, $sampaiTanggal) as $tgl) {
            /** @var Carbon $tgl */
            if ($tgl->dayOfWeekIso === 7) {
                continue; // Minggu selalu libur
            }
            $isLibur = $liburRows->contains(fn (HariLibur $hl) => $tgl->between($hl->tanggal_mulai, $hl->tanggal_selesai));
            if ($isLibur) {
                $liburDilewati++;
                continue;
            }
            $hariEfektifSemua[] = $tgl->copy();
        }

        $items = [];
        $pakaiJadwal = 0;
        $fallbackEfektif = 0;

        foreach ($mapelDipilih as $namaMapel) {
            $mapelKey = mb_strtolower(trim($namaMapel));
            $jadwalMapel = $jadwalMap[$mapelKey] ?? [];

            // Pilih tanggal yang cocok dengan jadwal pelajaran asli (jika ada), atau fallback Senin-Jumat
            $slotTanggal = [];
            if (! empty($jadwalMapel)) {
                $byIso = [];
                foreach ($jadwalMapel as $j) {
                    $byIso[$j['iso']] = $j['jam'];
                }
                foreach ($hariEfektifSemua as $tgl) {
                    if (isset($byIso[$tgl->dayOfWeekIso])) {
                        $slotTanggal[] = [
                            'tanggal' => $tgl,
                            'jam_ke' => $byIso[$tgl->dayOfWeekIso],
                            'sumber_jadwal' => 'jadwal_pelajaran',
                        ];
                    }
                }
            } else {
                foreach ($hariEfektifSemua as $tgl) {
                    if ($tgl->dayOfWeekIso <= 5) {
                        $slotTanggal[] = [
                            'tanggal' => $tgl,
                            'jam_ke' => '1-2',
                            'sumber_jadwal' => 'fallback_efektif',
                        ];
                    }
                }
            }

            if (empty($slotTanggal)) {
                continue;
            }

            $sumberTopik = $this->kumpulkanTopik($tenantId, $guruId, $tahunAjaran, $tingkat, $semester, $fase, $namaMapel);
            $limit = min(count($sumberTopik), count($slotTanggal), $mode === 'ai' ? 5 : 20);

            for ($i = 0; $i < $limit; $i++) {
                $topik = $sumberTopik[$i];
                $slot = $slotTanggal[$i];
                /** @var Carbon $tgl */
                $tgl = $slot['tanggal'];

                if ($slot['sumber_jadwal'] === 'jadwal_pelajaran') {
                    $pakaiJadwal++;
                } else {
                    $fallbackEfektif++;
                }

                $items[] = [
                    'pertemuan_ke' => $i + 1,
                    'tanggal' => $tgl->toDateString(),
                    'tanggal_id' => $tgl->format('d/m/Y'),
                    'hari' => self::NAMA_HARI_ISO[$tgl->dayOfWeekIso] ?? '',
                    'jam_ke' => $slot['jam_ke'],
                    'mapel' => $namaMapel,
                    'materi' => $topik['materi'],
                    'tp_id' => $topik['tp_id'],
                    'tp_teks' => $topik['tp_teks'],
                    'jp' => $topik['jp'],
                    'sumber_jadwal' => $slot['sumber_jadwal'],
                ];
            }
        }

        usort($items, fn (array $a, array $b) => strcmp($a['tanggal'], $b['tanggal']));

        return [
            'items' => $items,
            'ringkasan' => [
                'total_pertemuan' => count($items),
                'libur_dilewati' => $liburDilewati,
                'pakai_jadwal' => $pakaiJadwal,
                'fallback_efektif' => $fallbackEfektif,
            ],
        ];
    }

    /**
     * @return array<int, array{materi: string, tp_id: int|null, tp_teks: string, jp: int}>
     */
    private function kumpulkanTopik(
        ?int $tenantId,
        int $guruId,
        string $tahunAjaran,
        int $tingkat,
        int $semester,
        string $fase,
        string $namaMapel
    ): array {
        $prosemRows = ProsemItem::query()
            ->with('tp:id,kode_tp,deskripsi')
            ->where('guru_id', $guruId)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $namaMapel)
            ->where('semester', $semester)
            ->orderBy('bulan')
            ->orderBy('minggu_ke')
            ->get()
            ->unique('materi')
            ->values();

        $out = [];
        if ($prosemRows->isNotEmpty()) {
            foreach ($prosemRows as $ps) {
                $out[] = [
                    'materi' => $ps->materi,
                    'tp_id' => $ps->tp_id,
                    'tp_teks' => $ps->tp?->deskripsi ?? '',
                    'jp' => max(2, (int) $ps->alokasi_jp),
                ];
            }

            return $out;
        }

        $protaRows = ProtaItem::query()
            ->with('tp:id,kode_tp,deskripsi')
            ->where('guru_id', $guruId)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $namaMapel)
            ->where('semester', $semester)
            ->orderBy('urutan')
            ->get();

        foreach ($protaRows as $pr) {
            $out[] = [
                'materi' => $pr->materi,
                'tp_id' => $pr->tp_id,
                'tp_teks' => $pr->tp?->deskripsi ?? '',
                'jp' => max(2, (int) $pr->alokasi_jp),
            ];
        }

        if (! empty($out)) {
            return $out;
        }

        $tpRows = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $namaMapel)
            ->where('fase', $fase)
            ->orderBy('urutan')
            ->limit(10)
            ->get();

        foreach ($tpRows as $tp) {
            $out[] = [
                'materi' => $tp->materi_pokok ?: mb_substr($tp->deskripsi, 0, 100),
                'tp_id' => $tp->id,
                'tp_teks' => $tp->deskripsi,
                'jp' => $tp->alokasi_jp > 0 ? $tp->alokasi_jp : 4,
            ];
        }

        if (empty($out)) {
            $out[] = [
                'materi' => "Pembelajaran {$namaMapel} Kelas {$tingkat} Semester {$semester}",
                'tp_id' => null,
                'tp_teks' => '',
                'jp' => 4,
            ];
        }

        return $out;
    }
}
