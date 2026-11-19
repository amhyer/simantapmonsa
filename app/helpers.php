<?php

use Illuminate\Support\Facades\Cache;
use App\Models\PengaturanGuru;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;

if (!function_exists('setting')) {
    function setting(string|array|null $key = null, mixed $default = null): mixed
    {
        $path = storage_path('app/settings.json');

        $settings = Cache::remember('app_settings', 300, function () use ($path) {
            return file_exists($path) ? json_decode(file_get_contents($path), true) ?? [] : [];
        });

        if (is_array($key)) {
            $settings = array_merge($settings, $key);
            if (auth()->check() && in_array(auth()->user()->peran, ['admin', 'guru'])) {
                file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                Cache::forget('app_settings');
            }
            return true;
        }

        if ($key === null) {
            return $settings;
        }

        return data_get($settings, $key, $default);
    }
}

if (! function_exists('forgetKKMCache')) {
    function forgetKKMCache(?int $guruId = null, ?int $tenantId = null): void
    {
        $scopes = ['single'];
        if ($tenantId !== null) {
            $scopes[] = 't' . $tenantId;
        }
        if (TenantContext::check()) {
            $scopes[] = 't' . TenantContext::id();
        }
        $scopes = array_values(array_unique($scopes));

        foreach ($scopes as $scope) {
            // Naikkan versi scope agar seluruh cache guru (termasuk fallback) langsung usang.
            $verKey = "kkm-ver-{$scope}";
            $nextVer = ((int) Cache::get($verKey, 1)) + 1;
            Cache::forever($verKey, $nextVer);
            Cache::forget("kkm-{$scope}-default");
            if ($guruId !== null) {
                Cache::forget("kkm-{$scope}-guru-{$guruId}");
            }
        }

        if ($guruId !== null) {
            Cache::forget('kkm-guru-' . $guruId);
        }
        Cache::forget('kkm-global');
    }
}

if (! function_exists('getKKM')) {
    function getKKM(?int $guruId = null): int
    {
        $hasTenantContext = TenantContext::check();
        $tenantId = $hasTenantContext ? (int) TenantContext::id() : null;
        $isMultiSchool = $hasTenantContext
            || (bool) config('simantap.multi_tenant_enabled', false)
            || Tenant::hasAnyTenant();

        // Di instalasi multi-tenant tanpa konteks sekolah aktif, jangan pernah
        // menarik KKM acak dari sekolah lain; bila guruId disuplai, batasi ke
        // guru itu sendiri dan tenant miliknya.
        if ($isMultiSchool && $tenantId === null) {
            if ($guruId === null) {
                return 70;
            }
            $guruTenantId = User::whereKey($guruId)->value('tenant_id');
            if ($guruTenantId === null) {
                return 70;
            }
            $tenantId = (int) $guruTenantId;
        }

        $scope = $tenantId !== null ? 't' . $tenantId : 'single';
        $ver = (int) Cache::get("kkm-ver-{$scope}", 1);

        // Cache 5 menit per scope sekolah + versi: dipanggil per-baris nilai, tanpa cache jadi N+1 query.
        if ($guruId) {
            return Cache::remember("kkm-{$scope}-v{$ver}-guru-{$guruId}", 300, function () use ($guruId, $tenantId) {
                $guruQuery = PengaturanGuru::query()->where('guru_id', $guruId);
                if ($tenantId !== null) {
                    $guruQuery->whereHas('guru', fn ($q) => $q->where('users.tenant_id', $tenantId));
                } else {
                    $guruQuery->forSchool();
                }

                $pengaturan = $guruQuery->first();
                if ($pengaturan && $pengaturan->kkm) {
                    return (int) $pengaturan->kkm;
                }

                $fallbackQuery = PengaturanGuru::query();
                if ($tenantId !== null) {
                    $fallbackQuery->whereHas('guru', fn ($q) => $q->where('users.tenant_id', $tenantId));
                } else {
                    $fallbackQuery->forSchool();
                }

                $latest = $fallbackQuery->orderByDesc('id')->first();

                return $latest && $latest->kkm ? (int) $latest->kkm : 70;
            });
        }

        return Cache::remember("kkm-{$scope}-v{$ver}-default", 300, function () use ($tenantId) {
            $fallbackQuery = PengaturanGuru::query();
            if ($tenantId !== null) {
                $fallbackQuery->whereHas('guru', fn ($q) => $q->where('users.tenant_id', $tenantId));
            } else {
                $fallbackQuery->forSchool();
            }

            $latest = $fallbackQuery->orderByDesc('id')->first();

            return $latest && $latest->kkm ? (int) $latest->kkm : 70;
        });
    }
}

if (!function_exists('sidebarRouteActive')) {
    /** Cocokkan halaman daftar/profil dengan halaman detail atau editnya,
     * tanpa mengaktifkan menu saudara seperti statistik atau daftar lain.
     */
    function sidebarRouteActive(string $route): bool
    {
        if (request()->routeIs($route)) {
            return true;
        }

        if (str_ends_with($route, '.index') || str_ends_with($route, '.show')) {
            $prefix = substr($route, 0, strrpos($route, '.'));

            return request()->routeIs(
                $prefix.'.show',
                $prefix.'.detail',
                $prefix.'.edit',
                $prefix.'.create',
                $prefix.'.pertemuan',
                $prefix.'.tugas.*'
            );
        }

        return request()->routeIs($route.'.*');
    }
}

if (!function_exists('activeAdminTab')) {
    function activeAdminTab(): string
    {
        // CBT tab
        if (request()->routeIs('admin.cbt.*', 'admin.tryout.*')) {
            return 'cbt';
        }

        // TKA tab
        if (request()->routeIs('admin.tka.*', 'admin.tka-kesiapan.*')) {
            return 'tka';
        }

        // Setup tab (Dasbor, Master Data Sekolah, Siswa, Guru, Rombel, Akun, Dapodik, Sistem)
        if (request()->routeIs(
            'admin.dashboard',
            'admin.sekolah.*',
            'admin.dapodik.*',
            'admin.users.*',
            'admin.referensi.guru',
            'admin.referensi.pembelajaran',
            'admin.referensi.foto-siswa',
            'admin.referensi.ekstrakurikuler',
            'admin.referensi.kelompok-mapel',
            'admin.peta-kelas.*',
            'admin.mapel.*',
            'admin.semester.*',
            'admin.kode-akses.*',
            'admin.presensi.*',
            'admin.kokurikuler.*',
            'admin.rapor.teknik-penilaian*',
            'admin.rapor.siswa-belajar*',
            'admin.modul.*',
            'admin.bobot.*',
            'admin.backup.*',
            'admin.api-keys.*',
            'admin.log.*',
            'admin.sheet.*'
        )) {
            return 'setup';
        }

        // E-Rapor tab
        if (request()->routeIs(
            'admin.penilaian.*',
            'admin.perkembangan.*',
            'admin.transkrip.*',
            'admin.cetak.*',
            'admin.referensi.*',
            'admin.rapor.*',
            'admin.profile'
        )) {
            return 'erapor';
        }

        return 'setup';
    }
}

if (!function_exists('modulAsesmenAktif')) {
    /**
     * Apakah modul asesmen (CBT / Tryout / TKA) dapat diakses.
     *
     * Menu CBT, Tryout dan TKA yang bergantung pada flag disembunyikan saat
     * `simantap.cbt_enabled` false. Sebagian route CBT tidak didaftarkan
     * sama sekali; middleware modul lainnya memiliki pengecualian admin.
     * TKA guru dan simulasi siswa mandiri tidak bergantung pada flag ini.
     */
    function modulAsesmenAktif(): bool
    {
        return (bool) config('simantap.cbt_enabled', false);
    }
}

if (!function_exists('kesiapanTkaAktif')) {
    /**
     * Apakah modul Pemantauan Kesiapan TKA aktif.
     */
    function kesiapanTkaAktif(): bool
    {
        return (bool) config('tka_kesiapan.aktif', true);
    }
}

if (!function_exists('guruAdalahWaliKelas')) {
    /**
     * Apakah pengguna aktif (atau pengguna yang diberikan) adalah guru Wali Kelas.
     * Aman dipanggil ketika belum ada pengguna terautentikasi (mengembalikan false).
     */
    function guruAdalahWaliKelas(?\App\Models\User $user = null): bool
    {
        $target = $user ?? auth()->user();

        if (! $target instanceof \App\Models\User) {
            return false;
        }

        return $target->isWaliKelas();
    }
}

if (!function_exists('semesterAktifLabel')) {
    /**
     * Label semester dan tahun ajaran aktif untuk konteks header.
     * Hanya menyimpan string skalar di cache (bukan model Eloquent).
     */
    function semesterAktifLabel(): ?string
    {
        return Cache::remember('app_semester_aktif_label:v1', 300, function () {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('semesters')) {
                    return null;
                }
                // Skema Dapodik standar tidak memiliki kolom is_aktif.
                // Jangan mencoba query yang gagal: PostgreSQL membatalkan
                // transaksi meskipun exception kemudian ditangkap.
                $semester = \Illuminate\Support\Facades\Schema::hasColumn('semesters', 'is_aktif')
                    ? \App\Models\Semester::where('is_aktif', true)->first()
                    : null;
                if (!$semester) {
                    $semester = \App\Models\Semester::orderByDesc('semester_id')->first();
                }
                if (!$semester) {
                    return null;
                }
                $ta = $semester->tahun_ajaran ?? '';
                $smt = $semester->nama_semester ?? $semester->semester ?? '';
                $label = trim($ta . ($smt ? " ({$smt})" : ''));
                return $label !== '' ? $label : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
