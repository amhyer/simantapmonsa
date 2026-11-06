<?php

use Illuminate\Support\Facades\Cache;
use App\Models\PengaturanGuru;

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

if (!function_exists('getKKM')) {
    function getKKM(?int $guruId = null): int
    {
        // Cache 5 menit: dipanggil per-baris nilai, tanpa cache jadi N+1 query.
        if ($guruId) {
            return Cache::remember('kkm-guru-' . $guruId, 300, function () use ($guruId) {
                $pengaturan = PengaturanGuru::where('guru_id', $guruId)->first();
                if ($pengaturan && $pengaturan->kkm) {
                    return (int) $pengaturan->kkm;
                }

                $latest = PengaturanGuru::orderByDesc('id')->first();
                return $latest && $latest->kkm ? (int) $latest->kkm : 70;
            });
        }

        return Cache::remember('kkm-global', 300, function () {
            $latest = PengaturanGuru::orderByDesc('id')->first();
            return $latest && $latest->kkm ? (int) $latest->kkm : 70;
        });
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
        if (request()->routeIs('admin.tka.*')) {
            return 'tka';
        }

        // Setup tab (Master Data Sekolah, Siswa, Guru, Rombel, Akun, Dapodik, Sistem)
        if (request()->routeIs(
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
            'admin.dashboard',
            'admin.penilaian.*',
            'admin.perkembangan.*',
            'admin.transkrip.*',
            'admin.cetak.*',
            'admin.referensi.*',
            'admin.profile'
        )) {
            return 'erapor';
        }

        return 'erapor';
    }
}

if (!function_exists('modulAsesmenAktif')) {
    /**
     * Apakah modul asesmen (CBT / Tryout / TKA) dapat diakses.
     *
     * Seluruh route modul asesmen dilindungi middleware `module.asesmen`
     * (App\Http\Middleware\EnsureModuleEnabled) yang menjawab 404 selama
     * `config('simantap.cbt_enabled')` bernilai false. Karena route-nya tetap
     * terdaftar, menu yang menaut ke sana tampak normal padahal selalu gagal —
     * jadi menu disembunyikan dengan penjaga ini, bukan dibiarkan menipu.
     */
    function modulAsesmenAktif(): bool
    {
        return (bool) config('simantap.cbt_enabled', false);
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
                $semester = \App\Models\Semester::where('is_aktif', true)->first();
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
