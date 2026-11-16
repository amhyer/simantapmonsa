<?php

namespace App\Services\Tka\Kesiapan;

use Carbon\CarbonInterface;

/**
 * Utilitas periode akademik & jenjang untuk pemantauan kesiapan TKA.
 *
 * Aturan (dokumentasi: docs/TKA-KESIAPAN.md):
 *  - Tahun ajaran dimulai Juli: Juli–Desember = Ganjil tahun Y/Y+1; Januari–Juni = Genap tahun Y-1/Y.
 *  - Tahun ajaran valid wajib berbentuk "YYYY/YYYY+1" (selisih tepat 1 tahun).
 *  - Tingkat diambil dari teks kelas ("4A", "VI B", "Kelas 5"). Hanya 1–12 yang valid.
 */
final class KesiapanPeriode
{
    /** @return array{tahun_ajaran: string, semester: string, mulai: int} */
    public static function dariTanggal(CarbonInterface $tanggal): array
    {
        $bulan = (int) $tanggal->format('n');
        $tahun = (int) $tanggal->format('Y');
        $mulai = $bulan >= 7 ? $tahun : $tahun - 1;

        return [
            'tahun_ajaran' => $mulai.'/'.($mulai + 1),
            'semester' => $bulan >= 7 ? 'Ganjil' : 'Genap',
            'mulai' => $mulai,
        ];
    }

    public static function tahunAjaranValid(?string $tahunAjaran): bool
    {
        if (! is_string($tahunAjaran) || ! preg_match('/^(\d{4})\s*\/\s*(\d{4})$/', trim($tahunAjaran), $m)) {
            return false;
        }

        return (int) $m[2] === ((int) $m[1] + 1);
    }

    public static function normalisasiTahunAjaran(?string $tahunAjaran): ?string
    {
        if (! self::tahunAjaranValid($tahunAjaran)) {
            return null;
        }

        preg_match('/^(\d{4})\s*\/\s*(\d{4})$/', trim((string) $tahunAjaran), $m);

        return $m[1].'/'.$m[2];
    }

    public static function tahunMulai(?string $tahunAjaran): ?int
    {
        if (! self::tahunAjaranValid($tahunAjaran)) {
            return null;
        }

        preg_match('/^(\d{4})\s*\/\s*(\d{4})$/', trim((string) $tahunAjaran), $m);

        return (int) $m[1];
    }

    public static function normalisasiSemester(?string $semester): ?string
    {
        $s = strtolower(trim((string) $semester));

        return match (true) {
            str_contains($s, 'ganjil') || $s === '1' => 'Ganjil',
            str_contains($s, 'genap') || $s === '2' => 'Genap',
            default => null,
        };
    }

    public static function tanggalCocokTahunAjaran(CarbonInterface $tanggal, string $tahunAjaran): bool
    {
        $mulai = self::tahunMulai($tahunAjaran);
        if ($mulai === null) {
            return false;
        }

        $awal = sprintf('%04d-07-01', $mulai);
        $akhir = sprintf('%04d-06-30', $mulai + 1);
        $tgl = $tanggal->format('Y-m-d');

        return $tgl >= $awal && $tgl <= $akhir;
    }

    public static function tingkatDariKelas(?string $kelas): ?int
    {
        $k = strtoupper(trim((string) $kelas));
        if ($k === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})/', $k, $m)) {
            $n = (int) $m[1];

            return $n >= 1 && $n <= 12 ? $n : null;
        }

        $romawi = [
            'XII' => 12, 'XI' => 11, 'IX' => 9, 'VIII' => 8, 'VII' => 7,
            'VI' => 6, 'IV' => 4, 'III' => 3, 'II' => 2, 'X' => 10, 'V' => 5, 'I' => 1,
        ];
        foreach ($romawi as $r => $n) {
            if (preg_match('/^(?:KELAS\s+)?'.$r.'(?:[\s.\-\/]+[A-Z0-9]+|[A-D])?$/', $k)) {
                return $n;
            }
        }

        return null;
    }

    /** Urutan kronologis periode: tahun mulai*2 + (Genap=1). */
    public static function urutan(?string $tahunAjaran, ?string $semester): int
    {
        $m = self::tahunMulai($tahunAjaran);
        if ($m === null || $semester === null) {
            return 0;
        }

        return ($m * 2) + ($semester === 'Genap' ? 1 : 0);
    }
}
