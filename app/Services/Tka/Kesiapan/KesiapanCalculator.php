<?php

namespace App\Services\Tka\Kesiapan;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Kalkulator kesiapan — murni (tanpa akses DB), deterministik, dapat diaudit.
 *
 * Prinsip (Tahap 3 & 4):
 *  - Normalisasi 0–100 hanya untuk ringkasan per jenis sumber; instrumen berbeda tidak otomatis sebanding.
 *  - Indeks estimasi per indikator (`estimasi_indeks_internal` / alias `skor_rata`) hanya dihitung bila bukti
 *    memenuhi syarat minimum jumlah DAN sebaran tanggal. Jika belum cukup → null ("Data belum cukup dinilai").
 *  - Tidak ada skor gabungan tunggal lintas indikator.
 *  - Keyakinan "tinggi" mensyaratkan variasi jenis sumber (≥ 2), bukti terbaru, dan sebaran ≥ 3 tanggal berbeda
 *    agar rekaman berulang dari satu sumber tidak menghasilkan keyakinan tinggi palsu.
 *  - Tren & lintasan kelas 4 → 5 → 6 hanya memakai bukti yang memiliki jenjang historis tervalidasi (`tingkat_valid`).
 */
class KesiapanCalculator
{
    /** @var array<string, mixed> */
    private array $aturan;

    /** @param array<string, mixed>|null $aturan */
    public function __construct(?array $aturan = null)
    {
        $this->aturan = array_replace((array) config('tka_kesiapan.aturan', []), $aturan ?? []);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $buktiFokus
     * @param  Collection<int, array<string, mixed>>  $buktiRiwayat
     * @return array<string, array<string, mixed>>
     */
    public function hitung(Collection $buktiFokus, Collection $buktiRiwayat, ?array $kodeIndikator = null): array
    {
        $kode = $kodeIndikator ?? array_keys((array) config('tka_kesiapan.indikator', []));
        $hasil = [];

        foreach ($kode as $k) {
            $fokus = $buktiFokus->where('indikator', $k)->values();
            $riwayat = $buktiRiwayat->where('indikator', $k)->values();
            $hasil[$k] = $this->hitungIndikator($k, $fokus, $riwayat);
        }

        return $hasil;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $fokus
     * @param  Collection<int, array<string, mixed>>  $riwayat
     * @return array<string, mixed>
     */
    public function hitungIndikator(string $kode, Collection $fokus, Collection $riwayat): array
    {
        $def = (array) config("tka_kesiapan.indikator.$kode", []);
        $jumlah = $fokus->count();
        $tanggalBeda = $fokus->pluck('tanggal')->unique()->count();
        $cukup = $jumlah >= (int) $this->aturan['min_bukti']
            && $tanggalBeda >= (int) $this->aturan['min_tanggal_berbeda'];

        $ringkasanSumber = $this->ringkasanPerSumber($fokus);
        [$keyakinan, $rincianKeyakinan, $alasanKeyakinan] = $cukup
            ? $this->keyakinan($fokus)
            : [null, [], 'Jumlah bukti atau sebaran tanggal belum memenuhi ambang minimum untuk penilaian.'];

        $lintasanFaktual = $this->lintasan($riwayat);
        $tanpaJenjang = $riwayat->where('tingkat_valid', false)->count();

        $out = [
            'kode' => $kode,
            'nama' => $def['nama'] ?? $kode,
            'asal' => $def['asal'] ?? 'penunjang_internal',
            'jumlah_bukti' => $jumlah,
            'jumlah_tanggal' => $tanggalBeda,
            'jenis_sumber' => $fokus->pluck('sumber')->unique()->values()->all(),
            'bukti_status' => $cukup ? 'cukup' : 'belum_cukup',
            'ringkasan_sumber' => $ringkasanSumber,
            'skor_per_sumber' => array_map(fn ($r) => $r['skor_rata'], $ringkasanSumber),
            'estimasi_indeks_internal' => null,
            'skor_rata' => null,
            'kecenderungan' => null,
            'keyakinan' => $keyakinan,
            'keyakinan_rincian' => $rincianKeyakinan,
            'keyakinan_alasan' => $alasanKeyakinan,
            'bukti_ids' => $fokus->pluck('id')->all(),
            'tren' => $this->tren($riwayat),
            'lintasan' => $lintasanFaktual,
            'jumlah_tanpa_jenjang_historis' => $tanpaJenjang,
        ];

        if (! $cukup) {
            return $out;
        }

        [$skor] = $this->skorTerbobot($fokus);
        $out['estimasi_indeks_internal'] = $skor;
        $out['skor_rata'] = $skor;
        $out['kecenderungan'] = match (true) {
            $skor < (float) $this->aturan['ambang_perlu_dukungan'] => 'perlu_dukungan',
            $skor < (float) $this->aturan['ambang_cukup_mantap'] => 'berkembang',
            default => 'cukup_mantap',
        };

        return $out;
    }

    /**
     * Ringkasan per jenis sumber (jumlah, skala asli, tanggal terbaru, rata-rata, rentang).
     *
     * @param  Collection<int, array<string, mixed>>  $bukti
     * @return array<string, array<string, mixed>>
     */
    private function ringkasanPerSumber(Collection $bukti): array
    {
        $out = [];
        foreach ($bukti->groupBy('sumber') as $sumber => $grup) {
            $terbaru = $grup->max('tanggal');
            $hariSejak = $terbaru
                ? (int) Carbon::parse($terbaru)->startOfDay()->diffInDays(now()->startOfDay(), false)
                : null;
            $out[$sumber] = [
                'sumber' => $sumber,
                'label' => (string) config("tka_kesiapan.sumber.$sumber.label", $sumber),
                'skala' => (string) config("tka_kesiapan.sumber.$sumber.skala", ''),
                'jumlah' => $grup->count(),
                'skor_rata' => round((float) $grup->avg('skor'), 2),
                'skor_min' => round((float) $grup->min('skor'), 2),
                'skor_maks' => round((float) $grup->max('skor'), 2),
                'tanggal_terakhir' => $terbaru,
                'hari_sejak' => $hariSejak,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $bukti
     * @return array{0: float, 1: array<string, float>}
     */
    private function skorTerbobot(Collection $bukti): array
    {
        $perSumber = $bukti->groupBy('sumber')->map(fn (Collection $g) => round((float) $g->avg('skor'), 2));
        $bobotCfg = (array) ($this->aturan['bobot_sumber'] ?? []);

        $jumlahBobot = 0.0;
        $jumlah = 0.0;
        foreach ($perSumber as $sumber => $skor) {
            $b = isset($bobotCfg[$sumber]) && (float) $bobotCfg[$sumber] > 0 ? (float) $bobotCfg[$sumber] : 1.0;
            $jumlahBobot += $b;
            $jumlah += $b * $skor;
        }

        return [round($jumlah / max($jumlahBobot, 1e-9), 2), $perSumber->all()];
    }

    /**
     * Kualitas/keyakinan analisis berdasarkan jumlah+sebaran tanggal, kebaruan, variasi sumber, dan konsistensi.
     * Rekaman berulang dari sumber tunggal TIDAK pernah diberi keyakinan "tinggi".
     *
     * @param  Collection<int, array<string, mixed>>  $bukti
     * @return array{0: string, 1: array<string, bool>, 2: string}
     */
    private function keyakinan(Collection $bukti): array
    {
        $skor = $bukti->pluck('skor')->map(fn ($v) => (float) $v);
        $rata = (float) $skor->avg();
        $sd = sqrt((float) $skor->map(fn ($v) => ($v - $rata) ** 2)->avg());
        $tanggalUnik = $bukti->pluck('tanggal')->unique()->count();
        $jenisSumberUnik = $bukti->pluck('sumber')->unique()->count();

        $terbaru = $bukti->max('tanggal');
        $hariSejak = $terbaru
            ? (int) Carbon::parse($terbaru)->startOfDay()->diffInDays(now()->startOfDay(), false)
            : PHP_INT_MAX;

        $rincian = [
            'jumlah' => $bukti->count() >= (int) $this->aturan['keyakinan_target_jumlah'] && $tanggalUnik >= 3,
            'kebaruan' => $hariSejak >= 0 && $hariSejak <= (int) $this->aturan['keyakinan_kebaruan_hari'],
            'cakupan' => $jenisSumberUnik >= (int) $this->aturan['keyakinan_min_jenis_sumber'],
            'konsistensi' => $sd <= (float) $this->aturan['keyakinan_maks_sebaran'] && $tanggalUnik >= 2,
        ];
        $terpenuhi = count(array_filter($rincian));

        $syaratTinggi = $rincian['cakupan'] && $rincian['kebaruan'] && $tanggalUnik >= 3 && $terpenuhi >= 3;
        $level = $syaratTinggi ? 'tinggi' : ($terpenuhi >= 2 ? 'sedang' : 'rendah');

        $kurang = [];
        if (! $rincian['jumlah']) {
            $kurang[] = 'jumlah/sebaran tanggal masih terbatas';
        }
        if (! $rincian['kebaruan']) {
            $kurang[] = $hariSejak < 0 ? 'tanggal bukti melebihi tanggal analisis' : 'bukti terbaru sudah cukup lama';
        }
        if (! $rincian['cakupan']) {
            $kurang[] = 'baru berasal dari '.$jenisSumberUnik.' jenis sumber';
        }
        if (! $rincian['konsistensi']) {
            $kurang[] = 'sebaran skor antar-bukti cukup lebar';
        }

        $alasan = $kurang === []
            ? 'Didukung banyak bukti terbaru dari beragam jenis sumber yang konsisten.'
            : 'Keyakinan '.$level.' karena '.implode(', ', $kurang).'.';

        return [$level, $rincian, $alasan];
    }

    /**
     * Periode berurutan kronologis untuk bukti yang valid periodenya.
     *
     * @param  Collection<int, array<string, mixed>>  $riwayat
     * @return array<int, array<string, mixed>>
     */
    public function periode(Collection $riwayat): array
    {
        return $riwayat
            ->filter(fn ($b) => ! empty($b['periode_valid']))
            ->groupBy(fn ($b) => $b['tahun_ajaran'].'|'.$b['semester'])
            ->map(function (Collection $g, string $kunci) {
                [$tahun, $sem] = explode('|', $kunci);
                $minPeriode = (int) $this->aturan['min_bukti_per_periode'];
                $tanggalBeda = $g->pluck('tanggal')->unique()->count();
                $layak = $g->count() >= $minPeriode && $tanggalBeda >= min($minPeriode, 2);

                // Hanya pakai jenjang faktual (tingkat_valid = true) pada periode ini.
                $tingkatFaktual = $g->where('tingkat_valid', true)->pluck('tingkat')->filter()->unique()->values();
                $tingkat = $tingkatFaktual->count() === 1 ? (int) $tingkatFaktual->first() : null;

                return [
                    'tahun_ajaran' => $tahun,
                    'semester' => $sem,
                    'urutan' => KesiapanPeriode::urutan($tahun, $sem),
                    'tingkat' => $tingkat,
                    'tingkat_valid' => $tingkat !== null,
                    'jumlah_bukti' => $g->count(),
                    'memenuhi_minimum' => $layak,
                    'skor' => $layak ? $this->skorTerbobot($g)[0] : null,
                ];
            })
            ->sortBy('urutan')->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $riwayat
     * @return array{arah: string, selisih: float|null, dari: string|null, ke: string|null}
     */
    public function tren(Collection $riwayat): array
    {
        $layak = array_values(array_filter($this->periode($riwayat), fn ($p) => $p['memenuhi_minimum']));
        if (count($layak) < 2) {
            return ['arah' => 'belum_cukup', 'selisih' => null, 'dari' => null, 'ke' => null];
        }

        $a = $layak[count($layak) - 2];
        $b = $layak[count($layak) - 1];
        $selisih = round($b['skor'] - $a['skor'], 2);
        $ambang = (float) $this->aturan['ambang_tren'];

        return [
            'arah' => $selisih >= $ambang ? 'meningkat' : ($selisih <= -$ambang ? 'menurun' : 'stabil'),
            'selisih' => $selisih,
            'dari' => $a['tahun_ajaran'].' '.$a['semester'],
            'ke' => $b['tahun_ajaran'].' '.$b['semester'],
        ];
    }

    /**
     * Lintasan faktual kelas 4 → 5 → 6: HANYA menyertakan bukti yang punya jenjang tervalidasi,
     * diurutkan kronologis berdasarkan tahun ajaran & semester (menjaga histori tinggal kelas tetap utuh).
     *
     * @param  Collection<int, array<string, mixed>>  $riwayat
     * @return array<int, array<string, mixed>>
     */
    public function lintasan(Collection $riwayat): array
    {
        $hanyaValid = $riwayat->where('tingkat_valid', true)->values();

        return collect($this->periode($hanyaValid))
            ->filter(fn ($p) => in_array($p['tingkat'], [4, 5, 6], true))
            ->sortBy('urutan')
            ->values()
            ->all();
    }
}
