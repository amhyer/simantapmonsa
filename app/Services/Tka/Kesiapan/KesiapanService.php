<?php

namespace App\Services\Tka\Kesiapan;

use App\Models\Siswa;
use App\Models\Tka\Kesiapan\Pengaturan;
use Illuminate\Support\Collection;

/**
 * Orkestrasi pemantauan kesiapan: kumpulkan bukti (read-only) → saring → hitung → susun profil/ringkasan.
 * Profil siswa dan ringkasan kelas memakai kalkulator & bukti yang sama sehingga angkanya konsisten.
 */
class KesiapanService
{
    private KesiapanCalculator $calc;

    private BuktiCollector $collector;

    /** @var array<string, mixed> */
    private array $aturan;

    public function __construct(private readonly ?int $tenantId = null, ?BuktiCollector $collector = null)
    {
        $this->aturan = self::aturanTenant($tenantId);
        $this->calc = new KesiapanCalculator($this->aturan);
        $this->collector = $collector ?? new BuktiCollector($tenantId);
    }

    /** Aturan efektif = default config + override sekolah (hanya kunci yang dikenal). */
    public static function aturanTenant(?int $tenantId): array
    {
        $default = (array) config('tka_kesiapan.aturan', []);
        $override = Pengaturan::query()->withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->value('aturan');

        $override = is_array($override) ? array_intersect_key($override, $default) : [];

        return array_replace($default, $override);
    }

    public function aturan(): array
    {
        return $this->aturan;
    }

    // ── profil individual ─────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function profil(Siswa $siswa, KesiapanFilter $filter): array
    {
        $koleksi = $this->collector->collectBukti(collect([$siswa]));

        return $this->susunProfil(
            $siswa,
            $koleksi['bukti'],
            $filter,
            $koleksi['diagnostik'][$siswa->id] ?? [],
            $koleksi['histori_kondisi'][$siswa->id] ?? []
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $semua
     * @param  array<string, int>  $diagnostik
     * @param  array<string, mixed>  $historiKondisi
     * @return array<string, mixed>
     */
    private function susunProfil(
        Siswa $siswa,
        Collection $semua,
        KesiapanFilter $filter,
        array $diagnostik = [],
        array $historiKondisi = []
    ): array {
        $milik = $semua->where('siswa_id', $siswa->id)->values();
        $riwayat = $this->saring($milik, $filter, false);
        $fokus = $this->saring($milik, $filter, true);

        $terpetakanFokus = $fokus->whereNotNull('indikator')->values();
        $terpetakanRiwayat = $riwayat->whereNotNull('indikator')->values();
        $kode = $filter->indikator ? [$filter->indikator] : null;
        $indikator = $this->calc->hitung($terpetakanFokus, $terpetakanRiwayat, $kode);

        $utama = collect($indikator)->whereIn('asal', ['kerangka_terverifikasi', 'pemetaan_sementara']);
        $cukupUtama = $utama->where('bukti_status', 'cukup')->count();
        $statusData = match (true) {
            $utama->isEmpty() || $cukupUtama === 0 => 'belum_cukup',
            $cukupUtama === $utama->count() => 'memadai',
            default => 'sebagian',
        };

        $tanpaJenjang = $riwayat->where('tingkat_valid', false)->values();

        return [
            'siswa' => $siswa,
            'status_data' => $statusData,
            'indikator' => $indikator,
            'bukti' => $fokus->sortByDesc('tanggal')->values()->all(),
            'tidak_terpetakan' => $fokus->whereNull('indikator')->groupBy('sumber')->map->count()->all(),
            'lintasan' => $this->calc->lintasan($terpetakanRiwayat),
            'tanpa_jenjang_historis' => [
                'jumlah' => $tanpaJenjang->count(),
                'daftar' => $tanpaJenjang->sortByDesc('tanggal')->take(20)->values()->all(),
            ],
            'histori_kondisi' => $historiKondisi,
            'diagnostik' => $diagnostik,
            'kelengkapan' => [
                'per_sumber' => $fokus->groupBy('sumber')->map->count()->all(),
                'indikator_tanpa_data' => collect($indikator)->where('jumlah_bukti', 0)->keys()->values()->all(),
                'indikator_belum_cukup' => collect($indikator)->where('bukti_status', 'belum_cukup')->keys()->values()->all(),
                'tanpa_jenjang_historis' => $fokus->where('tingkat_valid', false)->count(),
            ],
            'perlu_ditinjau' => $this->perluDitinjau($indikator),
        ];
    }

    /** @param array<string, array<string, mixed>> $indikator */
    private function perluDitinjau(array $indikator): array
    {
        $alasan = [];
        foreach ($indikator as $kode => $i) {
            if (($i['kecenderungan'] ?? null) === 'perlu_dukungan') {
                $alasan[] = ['kode' => $kode, 'jenis' => 'dukungan'];
            }
            if (($i['tren']['arah'] ?? null) === 'menurun') {
                $alasan[] = ['kode' => $kode, 'jenis' => 'tren_menurun'];
            }
        }

        return $alasan;
    }

    // ── ringkasan kelas ───────────────────────────────────────────────────

    /**
     * @param  Collection<int, Siswa>  $siswaList  sudah diotorisasi oleh pemanggil (KesiapanAkses)
     * @return array<string, mixed>
     */
    public function kelas(Collection $siswaList, KesiapanFilter $filter, bool $modeAgregatSaja = false): array
    {
        $koleksi = $this->collector->collectBukti($siswaList); // O(1) query per sumber untuk seluruh kelas
        $semua = $koleksi['bukti'];

        $baris = [];
        foreach ($siswaList as $siswa) {
            $p = $this->susunProfil(
                $siswa,
                $semua,
                $filter,
                $koleksi['diagnostik'][$siswa->id] ?? [],
                $koleksi['histori_kondisi'][$siswa->id] ?? []
            );

            if ($filter->tingkat !== null) {
                $punyaBuktiFaktual = $semua->where('siswa_id', $siswa->id)
                    ->where('tingkat_valid', true)
                    ->where('tingkat', $filter->tingkat)
                    ->isNotEmpty();

                $kelasSaatIniCocok = $filter->tahunAjaran === null
                    && KesiapanPeriode::tingkatDariKelas($siswa->rombel?->tingkat ? (string) $siswa->rombel->tingkat : $siswa->kelas) === $filter->tingkat;

                if (! $punyaBuktiFaktual && ! $kelasSaatIniCocok) {
                    continue;
                }
            }

            $baris[] = $p;
        }

        $baris = collect($baris);
        $kode = $filter->indikator ? [$filter->indikator] : array_keys((array) config('tka_kesiapan.indikator', []));
        $minKelompok = max(1, (int) ($this->aturan['min_kelompok_agregat'] ?? 3));

        // Pada mode agregat saja (kepsek/admin), kelompok kecil (< min_kelompok) disupresi
        // agar filter berulang tidak dapat menyingkap capaian individu.
        $agregatTerlindungi = $modeAgregatSaja && $baris->count() > 0 && $baris->count() < $minKelompok;

        $distribusi = [];
        $trenAgregat = [];
        foreach ($kode as $k) {
            $distribusi[$k] = ['perlu_dukungan' => 0, 'berkembang' => 0, 'cukup_mantap' => 0, 'belum_cukup' => 0];
            $trenAgregat[$k] = ['meningkat' => 0, 'stabil' => 0, 'menurun' => 0, 'belum_cukup' => 0];
            foreach ($baris as $p) {
                $kc = $p['indikator'][$k]['kecenderungan'] ?? null;
                $distribusi[$k][$kc ?? 'belum_cukup']++;
                $trenAgregat[$k][$p['indikator'][$k]['tren']['arah'] ?? 'belum_cukup']++;
            }
        }

        $buktiFokus = $semua->filter(fn ($b) => $baris->contains(fn ($p) => $p['siswa']->id === $b['siswa_id']));
        $agregatRombel = $this->agregatPerRombel($baris, $kode, $minKelompok);

        $diagnostikTotal = [
            'duplikat_kuis_nilai' => 0,
            'duplikat_tka_sync' => 0,
            'duplikat_attempt_ulang' => 0,
            'ujian_belum_final' => 0,
            'ujian_dibatalkan_atau_draft' => 0,
            'skala_atau_nilai_kosong' => 0,
            'kalender_tidak_valid' => 0,
        ];
        foreach ($baris as $p) {
            foreach ($diagnostikTotal as $dk => $_) {
                $diagnostikTotal[$dk] += (int) ($p['diagnostik'][$dk] ?? 0);
            }
        }

        return [
            'baris' => $modeAgregatSaja ? [] : $baris->all(),
            'ringkas' => [
                'total' => $baris->count(),
                'memadai' => $baris->where('status_data', 'memadai')->count(),
                'sebagian' => $baris->where('status_data', 'sebagian')->count(),
                'belum_cukup' => $baris->where('status_data', 'belum_cukup')->count(),
                'perlu_ditinjau' => $baris->filter(fn ($p) => $p['perlu_ditinjau'] !== [])->count(),
            ],
            'agregat_terlindungi' => $agregatTerlindungi,
            'distribusi' => $agregatTerlindungi ? [] : $distribusi,
            'tren' => $agregatTerlindungi ? [] : $trenAgregat,
            'peta_tingkat' => $agregatTerlindungi ? [] : $this->petaTingkat($baris, $kode, $minKelompok),
            'agregat_rombel' => $agregatTerlindungi ? [] : $agregatRombel,
            'kelengkapan' => [
                'per_sumber' => $this->saring($buktiFokus->values(), $filter, true)->groupBy('sumber')->map->count()->all(),
                'siswa_tanpa_bukti' => $baris->filter(fn ($p) => $p['bukti'] === [])->count(),
                'tanpa_jenjang_historis' => $this->saring($buktiFokus->values(), $filter, true)->where('tingkat_valid', false)->count(),
            ],
            'diagnostik' => $diagnostikTotal,
            'min_kelompok' => $minKelompok,
        ];
    }

    /**
     * Ringkasan per kelas/rombel untuk dashboard & ekspor agregat (kepsek/admin).
     * Kelompok < min_kelompok disembunyikan distribusi & rata-ratanya demi privasi.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @param  array<int, string>  $kode
     * @return array<int, array<string, mixed>>
     */
    private function agregatPerRombel(Collection $baris, array $kode, int $minKelompok): array
    {
        $grup = $baris->groupBy(fn ($p) => $p['siswa']->rombel?->nama_rombel ?: ($p['siswa']->kelas ?: 'Tanpa Kelas'));
        $out = [];
        foreach ($grup as $namaKelas => $anggota) {
            $n = $anggota->count();
            $terlindungi = $n < $minKelompok;
            $perInd = [];
            foreach ($kode as $k) {
                if ($terlindungi) {
                    $perInd[$k] = null;

                    continue;
                }
                $c = ['perlu_dukungan' => 0, 'berkembang' => 0, 'cukup_mantap' => 0, 'belum_cukup' => 0];
                foreach ($anggota as $p) {
                    $kc = $p['indikator'][$k]['kecenderungan'] ?? null;
                    $c[$kc ?? 'belum_cukup']++;
                }
                $perInd[$k] = $c;
            }
            $out[] = [
                'kelas' => (string) $namaKelas,
                'jumlah_siswa' => $n,
                'privasi_terlindungi' => $terlindungi,
                'memadai' => $terlindungi ? null : $anggota->where('status_data', 'memadai')->count(),
                'belum_cukup' => $terlindungi ? null : $anggota->where('status_data', 'belum_cukup')->count(),
                'per_indikator' => $perInd,
            ];
        }

        return $out;
    }

    /**
     * Rata-rata estimasi indeks internal per tingkat (4/5/6) & indikator dari bukti jenjang faktual.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @param  array<int, string>  $kode
     * @return array<int, array<string, array{jumlah_siswa: int, skor_rata: float|null, privasi_terlindungi: bool}>>
     */
    private function petaTingkat(Collection $baris, array $kode, int $minKelompok): array
    {
        $out = [];
        foreach ([4, 5, 6] as $t) {
            foreach ($kode as $k) {
                $skor = [];
                foreach ($baris as $p) {
                    $titik = collect($p['indikator'][$k]['lintasan'] ?? [])
                        ->where('tingkat', $t)
                        ->where('tingkat_valid', true)
                        ->where('memenuhi_minimum', true);
                    if ($titik->isNotEmpty()) {
                        $skor[] = (float) $titik->sortBy('urutan')->last()['skor'];
                    }
                }
                $n = count($skor);
                $terlindungi = $n > 0 && $n < $minKelompok;
                $out[$t][$k] = [
                    'jumlah_siswa' => $n,
                    'skor_rata' => ($n >= $minKelompok) ? round(array_sum($skor) / $n, 2) : null,
                    'privasi_terlindungi' => $terlindungi,
                ];
            }
        }

        return $out;
    }

    // ── penyaringan bukti ─────────────────────────────────────────────────

    /**
     * @param  Collection<int, array<string, mixed>>  $bukti
     * @return Collection<int, array<string, mixed>>
     */
    private function saring(Collection $bukti, KesiapanFilter $f, bool $denganPeriode): Collection
    {
        return $bukti->filter(function (array $b) use ($f, $denganPeriode) {
            if ($f->sumber !== [] && ! in_array($b['sumber'], $f->sumber, true)) {
                return false;
            }
            if ($f->dari !== null && $b['tanggal'] < $f->dari) {
                return false;
            }
            if ($f->sampai !== null && $b['tanggal'] > $f->sampai) {
                return false;
            }
            if ($denganPeriode) {
                if ($f->tahunAjaran !== null && $b['tahun_ajaran'] !== $f->tahunAjaran) {
                    return false;
                }
                if ($f->semester !== null && $b['semester'] !== $f->semester) {
                    return false;
                }
                if ($f->tingkat !== null && (empty($b['tingkat_valid']) || $b['tingkat'] !== $f->tingkat)) {
                    return false;
                }
            }

            return true;
        })->values();
    }
}
