<?php

namespace App\Support;

use App\Models\SekolahSettings;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;

/**
 * Pusat aturan jenjang sekolah, tingkat kelas (1–12), dan Fase Kurikulum Merdeka (A–F).
 *
 * Mendukung penuh:
 *  - SD / MI       : Kelas 1–6  (Fase A: 1–2, Fase B: 3–4, Fase C: 5–6)
 *  - SMP / MTs     : Kelas 7–9  (Fase D: 7–9)
 *  - SMA / MA / SMK: Kelas 10–12 (Fase E: 10, Fase F: 11–12)
 *  - SLB / Lintas  : Kelas 1–12 (Fase A–F)
 */
final class SchoolLevel
{
    public const VALID_JENJANG = ['SD', 'MI', 'SMP', 'MTs', 'SMA', 'MA', 'SMK', 'SLB'];

    public const VALID_FASE = ['A', 'B', 'C', 'D', 'E', 'F'];

    /** @var array<string, string> */
    public const FASE_LABELS = [
        'A' => 'Fase A (Kelas 1–2)',
        'B' => 'Fase B (Kelas 3–4)',
        'C' => 'Fase C (Kelas 5–6)',
        'D' => 'Fase D (Kelas 7–9)',
        'E' => 'Fase E (Kelas 10)',
        'F' => 'Fase F (Kelas 11–12)',
    ];

    private const ROMAN_TO_INT = [
        'XII' => 12,
        'XI' => 11,
        'X' => 10,
        'IX' => 9,
        'VIII' => 8,
        'VII' => 7,
        'VI' => 6,
        'V' => 5,
        'IV' => 4,
        'III' => 3,
        'II' => 2,
        'I' => 1,
    ];

    public static function normalize(?string $raw, ?string $default = 'SD'): ?string
    {
        if ($raw === null) {
            return $default;
        }

        $clean = strtoupper(trim($raw));
        if ($clean === '') {
            return $default;
        }

        return match (true) {
            $clean === 'SD' || str_contains($clean, 'SEKOLAH DASAR') => 'SD',
            $clean === 'MI' || str_contains($clean, 'IBTIDAIYAH') => 'MI',
            $clean === 'SMP' || str_contains($clean, 'MENENGAH PERTAMA') => 'SMP',
            $clean === 'MTS' || str_contains($clean, 'TSANAWIYAH') => 'MTs',
            $clean === 'SMA' || str_contains($clean, 'MENENGAH ATAS') => 'SMA',
            $clean === 'SMK' || str_contains($clean, 'MENENGAH KEJURUAN') => 'SMK',
            $clean === 'MA' || str_contains($clean, 'MADRASAH ALIYAH') => 'MA',
            $clean === 'SLB' || str_contains($clean, 'LUAR BIASA') => 'SLB',
            default => $default,
        };
    }

    /**
     * Resolusi jenjang sekolah aktif saat ini (prioritas: SekolahSettings -> Tenant.meta -> nama sekolah -> 'SD').
     */
    public static function current(?int $tenantId = null): string
    {
        $resolvedTenantId = $tenantId ?? (TenantContext::check() ? (int) TenantContext::id() : null);

        $settingsQuery = SekolahSettings::withoutGlobalScopes();
        if ($resolvedTenantId !== null) {
            $settingsQuery->where('tenant_id', $resolvedTenantId);
        } elseif (config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()) {
            $settingsQuery->whereRaw('1 = 0');
        } else {
            $settingsQuery->whereNull('tenant_id');
        }

        $settings = $settingsQuery
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first(['jenjang', 'nama_sekolah']);
        if ($settings && ! empty($settings->jenjang)) {
            return self::normalize($settings->jenjang, 'SD') ?? 'SD';
        }

        $tenant = $resolvedTenantId !== null ? Tenant::find($resolvedTenantId) : null;

        $metaJenjang = is_array($tenant?->meta ?? null) ? ($tenant->meta['jenjang'] ?? null) : null;
        if (! empty($metaJenjang)) {
            return self::normalize((string) $metaJenjang, 'SD') ?? 'SD';
        }

        $namaSekolah = (string) ($settings?->nama_sekolah ?: ($tenant?->nama_sekolah ?? ''));
        if ($namaSekolah !== '') {
            $inferred = self::inferFromSchoolName($namaSekolah);
            if ($inferred !== null) {
                return $inferred;
            }
        }

        return 'SD';
    }

    public static function inferFromSchoolName(string $name): ?string
    {
        $u = strtoupper(trim($name));

        return match (true) {
            (bool) preg_match('/\b(SMKN|SMKS|SMK)\b/', $u) => 'SMK',
            (bool) preg_match('/\b(SMAN|SMAS|SMA)\b/', $u) => 'SMA',
            (bool) preg_match('/\b(MTSN|MTSS|MTS)\b/', $u) => 'MTs',
            (bool) preg_match('/\b(MAN|MAS)\b/', $u) => 'MA',
            (bool) preg_match('/\b(SMPN|SMPS|SMP)\b/', $u) => 'SMP',
            (bool) preg_match('/\b(MIN|MIS)\b/', $u) => 'MI',
            (bool) preg_match('/\b(SDN|SDS|SD)\b/', $u) => 'SD',
            (bool) preg_match('/\bSLB\b/', $u) => 'SLB',
            default => null,
        };
    }

    /**
     * @return array<int, int>
     */
    public static function tingkatForJenjang(?string $jenjang): array
    {
        return match (self::normalize($jenjang, 'SD')) {
            'SMP', 'MTs' => [7, 8, 9],
            'SMA', 'MA', 'SMK' => [10, 11, 12],
            'SLB' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            default => [1, 2, 3, 4, 5, 6],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function faseForJenjang(?string $jenjang): array
    {
        return match (self::normalize($jenjang, 'SD')) {
            'SMP', 'MTs' => ['D'],
            'SMA', 'MA', 'SMK' => ['E', 'F'],
            'SLB' => ['A', 'B', 'C', 'D', 'E', 'F'],
            default => ['A', 'B', 'C'],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function faseLabelsForJenjang(?string $jenjang = null): array
    {
        if ($jenjang === null) {
            return self::FASE_LABELS;
        }

        $allowed = self::faseForJenjang($jenjang);
        $labels = [];
        foreach ($allowed as $kode) {
            if (isset(self::FASE_LABELS[$kode])) {
                $labels[$kode] = self::FASE_LABELS[$kode];
            }
        }

        return $labels ?: self::FASE_LABELS;
    }

    public static function faseUntukKelas(int $tingkat): string
    {
        return match (true) {
            $tingkat <= 2 => 'A',
            $tingkat <= 4 => 'B',
            $tingkat <= 6 => 'C',
            $tingkat <= 9 => 'D',
            $tingkat === 10 => 'E',
            default => 'F',
        };
    }

    /**
     * @return array<int, int>
     */
    public static function kelasUntukFase(string $fase): array
    {
        return match (strtoupper(trim($fase))) {
            'A' => [1, 2],
            'B' => [3, 4],
            'C' => [5, 6],
            'D' => [7, 8, 9],
            'E' => [10],
            'F' => [11, 12],
            default => [1, 2, 3, 4, 5, 6],
        };
    }

    public static function mingguEfektifPerTahun(int $tingkat): int
    {
        return in_array($tingkat, [6, 9, 12], true) ? 32 : 36;
    }

    /**
     * Daftar tingkat kelas yang diizinkan untuk pengguna aktif (mengikuti cakupan guru bila peran = guru,
     * atau seluruh tingkat sesuai jenjang sekolah aktif).
     *
     * @return array<int, int>
     */
    public static function allowedTingkatForUser(?\App\Models\User $user = null, ?int $tenantId = null): array
    {
        $resolvedTenantId = $tenantId ?? ($user?->tenant_id !== null ? (int) $user->tenant_id : null);
        if ($user && $user->peran === 'guru') {
            $scope = app(\App\Services\PembelajaranMendalam\GuruScopeService::class)->resolveForGuru($user, $resolvedTenantId);
            if (! empty($scope['tingkat_list'])) {
                return $scope['tingkat_list'];
            }
        }

        return self::tingkatForJenjang(self::current($resolvedTenantId));
    }

    public static function labelSekolah(?string $jenjang): string
    {
        return match (self::normalize($jenjang, 'SD')) {
            'MI' => 'Madrasah Ibtidaiyah',
            'SMP' => 'Sekolah Menengah Pertama',
            'MTs' => 'Madrasah Tsanawiyah',
            'SMA' => 'Sekolah Menengah Atas',
            'MA' => 'Madrasah Aliyah',
            'SMK' => 'Sekolah Menengah Kejuruan',
            'SLB' => 'Sekolah Luar Biasa',
            default => 'Sekolah Dasar',
        };
    }

    /**
     * Ekstrak tingkat kelas (1..12) baik dari kolom `tingkat` maupun dari label rombel/kelas
     * seperti "7A", "VIII B", "10 IPA 1", "XII TKJ 2", "Kelas 11", atau "VI-A".
     */
    public static function parseTingkat(mixed $tingkat, ?string $namaKelas = null): ?int
    {
        if (is_numeric($tingkat)) {
            $n = (int) $tingkat;
            if ($n >= 1 && $n <= 12) {
                return $n;
            }
        } elseif (is_string($tingkat) && trim($tingkat) !== '') {
            $upper = strtoupper(trim($tingkat));
            if (isset(self::ROMAN_TO_INT[$upper])) {
                return self::ROMAN_TO_INT[$upper];
            }
            if (preg_match('/\b(1[0-2]|[1-9])\b/', $upper, $m)) {
                return (int) $m[1];
            }
        }

        if ($namaKelas !== null && trim($namaKelas) !== '') {
            $cleaned = strtoupper(trim($namaKelas));
            $withoutPrefix = trim((string) preg_replace('/^KELAS[\s\-:\.]*/i', '', $cleaned));

            if (preg_match('/^(1[0-2]|[1-9])(?=\D|$)/', $withoutPrefix, $m)) {
                return (int) $m[1];
            }

            foreach (self::ROMAN_TO_INT as $roman => $num) {
                if (preg_match('/^'.preg_quote($roman, '/').'(?:[\s\-\._]|$)/', $withoutPrefix)) {
                    return $num;
                }
            }

            if (preg_match('/(?:^|\D)(1[0-2]|[1-9])(?=\D|$)/', $withoutPrefix, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }
}
