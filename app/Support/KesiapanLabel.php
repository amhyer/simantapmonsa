<?php

namespace App\Support;

/** Label & kelas CSS untuk pemantauan kesiapan TKA (bahasa mendukung, transparan, tanpa stigma). */
final class KesiapanLabel
{
    public static function kecenderungan(?string $v): array
    {
        return match ($v) {
            'perlu_dukungan' => ['Perlu dukungan', 'tag-warn'],
            'berkembang' => ['Berkembang', 'tag-primary'],
            'cukup_mantap' => ['Cukup mantap', 'tag-ok'],
            default => ['Data belum cukup dinilai', 'tag-secondary'],
        };
    }

    public static function kecenderunganMandiri(?string $v): array
    {
        return match ($v) {
            'perlu_dukungan' => ['Perlu latihan pendampingan', 'tag-warn'],
            'berkembang' => ['Sedang berkembang', 'tag-primary'],
            'cukup_mantap' => ['Sudah cukup mantap', 'tag-ok'],
            default => ['Data belajar belum cukup dinilai', 'tag-secondary'],
        };
    }

    public static function keyakinan(?string $v): string
    {
        return match ($v) {
            'tinggi' => 'Keyakinan analisis: tinggi',
            'sedang' => 'Keyakinan analisis: sedang',
            'rendah' => 'Keyakinan analisis: rendah',
            default => 'Keyakinan analisis: belum cukup data',
        };
    }

    public static function tren(?string $v): string
    {
        return match ($v) {
            'meningkat' => '↑ Meningkat (bukti tervalidasi)',
            'stabil' => '→ Stabil (bukti tervalidasi)',
            'menurun' => '↓ Perlu dicermati (bukti tervalidasi)',
            default => 'Tren belum cukup data tervalidasi',
        };
    }

    public static function statusData(?string $v): array
    {
        return match ($v) {
            'memadai' => ['Data memadai', 'tag-ok'],
            'sebagian' => ['Data sebagian', 'tag-primary'],
            default => ['Data belum cukup dinilai', 'tag-secondary'],
        };
    }

    public static function asal(?string $v): string
    {
        return match ($v) {
            'kerangka_terverifikasi' => 'Kerangka resmi terverifikasi sekolah',
            'pemetaan_sementara' => 'Pemetaan sementara — perlu verifikasi sekolah',
            default => 'Indikator penunjang internal SIMANTAP — perlu validasi sekolah',
        };
    }

    public static function tingkatBukti(?int $tingkat, ?string $sumber = null): string
    {
        if ($tingkat === null) {
            return $sumber === 'pindah_rombel_konflik'
                ? 'Jenjang historis tidak diketahui (ada perpindahan rombel pada periode ini)'
                : 'Jenjang historis tidak diketahui';
        }

        return 'Kelas '.$tingkat;
    }

    public static function statusTindakLanjut(string $v): string
    {
        return match ($v) {
            'disarankan' => 'Disarankan sistem (menunggu tinjauan)',
            'ditinjau' => 'Ditinjau guru',
            'berjalan' => 'Sedang dijalankan',
            'selesai' => 'Selesai',
            'ditolak' => 'Tidak dipakai guru',
            default => $v,
        };
    }
}
