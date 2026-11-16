<?php

namespace App\Services\Tka\Kesiapan;

use Illuminate\Http\Request;

/**
 * Filter dasbor/profil kesiapan. Dibuat dari input request yang divalidasi/dibersihkan di sini
 * (bukan dipercaya mentah). `rombelId` hanya mempersempit; otorisasi tetap dari KesiapanAkses.
 */
final class KesiapanFilter
{
    /**
     * @param  array<int, string>  $sumber
     */
    public function __construct(
        public readonly ?string $tahunAjaran = null,
        public readonly ?string $semester = null,
        public readonly ?int $tingkat = null,
        public readonly ?int $rombelId = null,
        public readonly ?string $indikator = null,
        public readonly ?string $dari = null,
        public readonly ?string $sampai = null,
        public readonly array $sumber = [],
    ) {}

    public static function dariRequest(Request $r): self
    {
        $tahun = trim((string) $r->query('tahun_ajaran', ''));
        $tingkat = (int) $r->query('tingkat', 0);
        $indikator = (string) $r->query('indikator', '');
        $sumberValid = array_keys((array) config('tka_kesiapan.sumber', []));

        return new self(
            tahunAjaran: KesiapanPeriode::tahunMulai($tahun) !== null ? $tahun : null,
            semester: KesiapanPeriode::normalisasiSemester((string) $r->query('semester', '')),
            tingkat: in_array($tingkat, [4, 5, 6], true) ? $tingkat : null,
            rombelId: ((int) $r->query('rombel_id', 0)) ?: null,
            indikator: array_key_exists($indikator, (array) config('tka_kesiapan.indikator', [])) ? $indikator : null,
            dari: self::tanggal((string) $r->query('dari', '')),
            sampai: self::tanggal((string) $r->query('sampai', '')),
            sumber: array_values(array_intersect($sumberValid, (array) $r->query('sumber', []))),
        );
    }

    private static function tanggal(string $v): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : null;
    }

    /** @return array<string, mixed> untuk query string tautan/ekspor */
    public function keQuery(): array
    {
        return array_filter([
            'tahun_ajaran' => $this->tahunAjaran,
            'semester' => $this->semester,
            'tingkat' => $this->tingkat,
            'rombel_id' => $this->rombelId,
            'indikator' => $this->indikator,
            'dari' => $this->dari,
            'sampai' => $this->sampai,
            'sumber' => $this->sumber ?: null,
        ], fn ($v) => $v !== null && $v !== []);
    }
}
