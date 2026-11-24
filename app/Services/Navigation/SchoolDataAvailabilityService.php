<?php

namespace App\Services\Navigation;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Support\TenantMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menentukan ketersediaan tabel dan data sekolah aktif untuk mengatur
 * visibilitas menu/tab Admin secara dinamis.
 *
 * Aturan bisnis:
 * - Ketika data sekolah masih kosong (misalnya baru selesai "Kosongkan Data"),
 *   seluruh menu yang bergantung pada data tersebut disembunyikan sehingga
 *   hanya menu inti Administrator yang tampil.
 * - Begitu data ditarik dari Dapodik (atau diisi ke tabel terkait), menu-menu
 *   otomatis muncul sesuai jenis data yang tersedia dan tabel yang ada.
 *
 * Penting (AGENTS.md): HANYA menyimpan array skalar boolean di dalam Cache
 * (tidak pernah menyimpan objek Model Eloquent).
 */
class SchoolDataAvailabilityService
{
    private const CACHE_PREFIX = 'school-menu-availability:v1:';

    private const REQUEST_ATTR_PREFIX = '_simantap_menu_avail_';

    /** @var array<string, array<string, bool>> */
    private static array $tableMapMemo = [];

    /** @var array<string, bool> */
    private static array $columnMemo = [];

    /**
     * Ambil peta status tabel & ketersediaan data untuk sekolah yang sedang aktif.
     *
     * @return array<string, bool>
     */
    public static function status(?int $explicitTenantId = null): array
    {
        $tenantId = $explicitTenantId ?? TenantContext::id() ?? Auth::user()?->tenant_id;
        $isMultiSchool = TenantMode::isMultiSchool() || $tenantId !== null;

        if ($isMultiSchool && $tenantId === null) {
            return self::emptyStatus(self::tableMap());
        }

        $normalizedTenantId = $tenantId !== null ? (int) $tenantId : null;
        $reqAttrKey = self::REQUEST_ATTR_PREFIX.($normalizedTenantId ?? 'single').':'.($isMultiSchool ? 'm' : 's');

        $req = app()->bound('request') ? request() : null;
        if ($req && ! app()->runningUnitTests() && $req->attributes->has($reqAttrKey)) {
            /** @var array<string, bool> $cachedInRequest */
            $cachedInRequest = $req->attributes->get($reqAttrKey);

            return $cachedInRequest;
        }

        $compute = fn (): array => self::computeStatus($normalizedTenantId, $isMultiSchool);

        $resolved = app()->runningUnitTests()
            ? $compute()
            : Cache::remember(self::cacheKey($normalizedTenantId), 60, $compute);

        if ($req && ! app()->runningUnitTests()) {
            $req->attributes->set($reqAttrKey, $resolved);
        }

        return $resolved;
    }

    /**
     * Hapus cache ketersediaan menu (serta cache ringkasan sidebar & dasbor sekolah)
     * agar perubahan data/penarikan Dapodik langsung tercermin seketika.
     */
    public static function forget(?int $explicitTenantId = null): void
    {
        $tenantId = $explicitTenantId ?? TenantContext::id() ?? Auth::user()?->tenant_id;

        if (app()->bound('request')) {
            $req = request();
            foreach (array_keys($req->attributes->all()) as $attrKey) {
                if (str_starts_with((string) $attrKey, self::REQUEST_ATTR_PREFIX)) {
                    $req->attributes->remove((string) $attrKey);
                }
            }
        }

        if ($tenantId !== null) {
            $tid = (int) $tenantId;
            Cache::forget(self::cacheKey($tid));
            Cache::forget('dapodik-menu-sync:v2:tenant:'.$tid);
            Cache::forget('admin-dashboard:tenant:v3:'.$tid);
            Cache::forget('tenant-nama-'.$tid);
        } else {
            Cache::forget(self::cacheKey(null));
            Cache::forget('dapodik-menu-sync:v2:single');
            Cache::forget('admin-dashboard:single:v3');
            Cache::forget('root-sekolah-nama');
        }
    }

    /**
     * Reset memoisasi skema (berguna bila koneksi database berganti).
     */
    public static function flushSchemaMemo(): void
    {
        self::$tableMapMemo = [];
        self::$columnMemo = [];
    }

    /**
     * Tentukan apakah sebuah rute menu Admin boleh ditampilkan berdasarkan
     * tabel yang ada dan data yang telah terisi.
     *
     * @param  array<string, bool>|null  $avail
     */
    public static function canShowAdminRoute(string $routeName, ?array $avail = null): bool
    {
        $avail ??= self::status();

        return match ($routeName) {
            // ── Menu inti Administrator (tetap tampil saat data kosong) ──
            'admin.dashboard' => true,
            'admin.dapodik.index' => true,
            'admin.profile' => $avail['table_users'],
            'admin.users.index' => $avail['table_users'],
            'admin.api-keys.index' => $avail['table_api_keys'],
            'admin.backup.index' => true,

            // ── Integrasi lanjutan ──
            'admin.dapodik.push.index' => $avail['has_siswa'] || $avail['has_nilai_erapor'],

            // ── Data Sekolah & Periode ──
            'admin.sekolah.index' => $avail['has_sekolah'],
            'admin.semester.index' => $avail['has_semester'],
            'admin.validasi-data.index' => $avail['has_siswa'] || $avail['has_ptk'] || $avail['has_rombel'],

            // ── Pendidik & Peserta Didik ──
            'admin.referensi.guru' => $avail['has_ptk'],
            'admin.users.siswa' => $avail['has_siswa'],
            'admin.referensi.foto-siswa' => $avail['has_siswa'],

            // ── Akun & Kode Akses tambahan ──
            'admin.kode-akses.index' => $avail['has_non_admin_users'] || $avail['has_siswa'] || $avail['has_ptk'],

            // ── Kelas & Pembelajaran ──
            'admin.peta-kelas.daftar',
            'admin.peta-kelas.index' => $avail['has_rombel'] || $avail['has_siswa'],
            'admin.mapel.index',
            'admin.referensi.kelompok-mapel' => $avail['has_mapel'],
            'admin.pm.alokasi.index' => $avail['table_pm_alokasi_waktu'] && ($avail['has_mapel'] || $avail['has_rombel']),
            'admin.pm.kalender.index' => $avail['table_pm_kalender'] && $avail['has_any_data'],
            'admin.referensi.pembelajaran' => $avail['has_jadwal'] || ($avail['has_ptk'] && $avail['has_rombel'] && $avail['has_mapel']),
            'admin.referensi.ekstrakurikuler' => $avail['has_ekstrakurikuler'],
            'admin.rapor.teknik-penilaian' => $avail['has_siswa'],
            'admin.rapor.siswa-belajar' => $avail['has_siswa'] && $avail['table_siswa_belajar'],

            // ── Presensi ──
            'admin.presensi.dashboard.mesin',
            'admin.presensi.index',
            'admin.presensi.laporan',
            'admin.presensi.mesin.index',
            'admin.presensi.kartu.index' => $avail['has_presensi'],
            'admin.presensi.mesin.sinkron' => $avail['has_presensi'] && $avail['table_presensi_mesin_sync_logs'],

            // ── Kokurikuler ──
            'admin.kokurikuler.tema' => $avail['has_kokurikuler'],
            'admin.kokurikuler.kegiatan' => $avail['has_kokurikuler'] && $avail['table_kegiatan_kokurikuler'],
            'admin.kokurikuler.kelompok' => $avail['has_kokurikuler'] && $avail['table_kelompok_kokurikuler'],

            // ── Sistem & Keamanan tambahan ──
            'admin.modul.index',
            'admin.sheet.index' => $avail['has_any_data'],
            'admin.log.index' => $avail['has_aktivitas'],

            // ── Tab E-Rapor ──
            'admin.penilaian.status',
            'admin.penilaian.statistik',
            'admin.perkembangan.index',
            'admin.perkembangan.grafik',
            'admin.referensi.mapping-rapor',
            'admin.referensi.tanggal-rapor',
            'admin.referensi.logo-ttd',
            'admin.referensi.alignment',
            'admin.bobot.index',
            'admin.rapor.naik-kelas',
            'admin.rapor.p5.index',
            'admin.transkrip.setting',
            'admin.transkrip.nomor',
            'admin.transkrip.input',
            'admin.transkrip.import',
            'admin.transkrip.cetak',
            'admin.cetak.kelengkapan',
            'admin.cetak.leger',
            'admin.cetak.pelengkap',
            'admin.cetak.nilai',
            'admin.cetak.pengaturan-rapor',
            'admin.cetak.modifikasi-tampilan' => $avail['has_erapor'],

            // ── Tab CBT & Tryout ──
            'admin.cbt.sessions.index',
            'admin.cbt.banks.index' => $avail['has_cbt'],
            'admin.tryout.statistics',
            'admin.tryout.packages.index',
            'admin.tryout.sessions.index',
            'admin.tryout.results.index' => $avail['has_tryout'],

            // ── Tab TKA ──
            'admin.tka-kesiapan.index',
            'admin.tka-kesiapan.pengaturan',
            'admin.tka.dashboard',
            'admin.tka.kognitif.index',
            'admin.tka.laporan-kelas.index',
            'admin.tka.dimensions.index',
            'admin.tka.assessments.index',
            'admin.tka.warnings.index' => $avail['has_tka'],

            default => $avail['has_any_data'],
        };
    }

    /**
     * Tentukan apakah sebuah tab atas Admin (`setup`, `erapor`, `cbt`, `tka`) ditampilkan.
     *
     * @param  array<string, bool>|null  $avail
     */
    public static function canShowAdminTab(string $tabKey, ?array $avail = null): bool
    {
        $avail ??= self::status();

        return match ($tabKey) {
            'setup' => true,
            'erapor' => $avail['has_erapor'],
            'cbt' => $avail['has_cbt'] || $avail['has_tryout'],
            'tka' => $avail['has_tka'],
            default => $avail['has_any_data'],
        };
    }

    private static function cacheKey(?int $tenantId): string
    {
        return self::CACHE_PREFIX.($tenantId !== null ? 'tenant:'.$tenantId : 'single');
    }

    /**
     * @return array<string, bool>
     */
    private static function tableMap(): array
    {
        $conn = DB::getDefaultConnection();
        if (isset(self::$tableMapMemo[$conn]) && self::$tableMapMemo[$conn] !== []) {
            return self::$tableMapMemo[$conn];
        }

        try {
            $raw = Schema::getTableListing();
            $normalized = [];
            foreach ($raw as $entry) {
                $name = (string) $entry;
                if (str_contains($name, '.')) {
                    $name = substr($name, strrpos($name, '.') + 1);
                }
                $normalized[strtolower($name)] = true;
            }
            if ($normalized !== []) {
                self::$tableMapMemo[$conn] = $normalized;
            }

            return $normalized;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, bool>  $tables
     */
    private static function hasTable(array $tables, string $table): bool
    {
        $key = strtolower($table);
        if (isset($tables[$key])) {
            return true;
        }

        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $conn = DB::getDefaultConnection();
        $key = $conn.':'.strtolower($table).':'.strtolower($column);
        if (array_key_exists($key, self::$columnMemo)) {
            return self::$columnMemo[$key];
        }

        try {
            return self::$columnMemo[$key] = Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function scopedQuery(string $table, ?int $tenantId, bool $isMultiSchool, bool $excludeSoftDeleted = false): Builder
    {
        $query = DB::table($table);

        if ($isMultiSchool && $tenantId !== null && self::hasColumn($table, 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        if ($excludeSoftDeleted && self::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /**
     * @param  array<string, bool>  $tables
     * @return array<string, bool>
     */
    private static function emptyStatus(array $tables): array
    {
        return [
            'table_users' => self::hasTable($tables, 'users'),
            'table_api_keys' => self::hasTable($tables, 'api_keys'),
            'table_pm_alokasi_waktu' => self::hasTable($tables, 'pm_alokasi_waktu'),
            'table_pm_kalender' => self::hasTable($tables, 'pm_kalender_pendidikan') && self::hasTable($tables, 'pm_hari_libur'),
            'table_siswa_belajar' => self::hasTable($tables, 'siswa_belajar'),
            'table_presensi_mesin_sync_logs' => self::hasTable($tables, 'presensi_mesin_sync_logs'),
            'table_kegiatan_kokurikuler' => self::hasTable($tables, 'kegiatan_kokurikuler'),
            'table_kelompok_kokurikuler' => self::hasTable($tables, 'kelompok_kokurikuler'),
            'has_sekolah' => false,
            'has_semester' => false,
            'has_ptk' => false,
            'has_siswa' => false,
            'has_rombel' => false,
            'has_mapel' => false,
            'has_jadwal' => false,
            'has_non_admin_users' => false,
            'has_nilai_erapor' => false,
            'has_presensi' => false,
            'has_ekstrakurikuler' => false,
            'has_kokurikuler' => false,
            'has_erapor' => false,
            'has_cbt' => false,
            'has_tryout' => false,
            'has_tka' => false,
            'has_aktivitas' => false,
            'has_any_data' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private static function computeStatus(?int $tenantId, bool $isMultiSchool): array
    {
        $tables = self::tableMap();
        $base = self::emptyStatus($tables);

        $hasSekolah = self::hasTable($tables, 'sekolah_settings')
            && self::scopedQuery('sekolah_settings', $tenantId, $isMultiSchool)->exists();

        $hasSiswa = self::hasTable($tables, 'siswa')
            && self::scopedQuery('siswa', $tenantId, $isMultiSchool, true)->exists();

        $hasRombel = self::hasTable($tables, 'rombel')
            && self::scopedQuery('rombel', $tenantId, $isMultiSchool, true)->exists();

        $hasPtkTable = self::hasTable($tables, 'ptk')
            && self::scopedQuery('ptk', $tenantId, $isMultiSchool, true)->exists();

        $hasStaffUsers = false;
        $hasNonAdminUsers = false;
        if ($base['table_users']) {
            $nonAdminQuery = self::scopedQuery('users', $tenantId, $isMultiSchool, true)
                ->whereNotIn('peran', ['admin', 'super_admin']);
            $hasNonAdminUsers = (clone $nonAdminQuery)->exists();
            $hasStaffUsers = (clone $nonAdminQuery)->whereIn('peran', ['guru', 'kepsek', 'tendik'])->exists();
        }

        $hasPtk = $hasPtkTable || $hasStaffUsers;

        $hasJadwal = self::hasTable($tables, 'jadwal_pelajaran')
            && self::scopedQuery('jadwal_pelajaran', $tenantId, $isMultiSchool)->exists();

        $hasSyncLog = self::hasTable($tables, 'dapodik_sync_logs')
            && self::scopedQuery('dapodik_sync_logs', $tenantId, $isMultiSchool)->exists();

        $hasSyncMapel = self::hasTable($tables, 'dapodik_sync_logs')
            && self::scopedQuery('dapodik_sync_logs', $tenantId, $isMultiSchool)
                ->whereIn('tipe', ['mapel', 'jadwal', 'semua'])
                ->exists();

        $hasMapelRows = false;
        if (self::hasTable($tables, 'mata_pelajaran')) {
            if ($isMultiSchool && $tenantId !== null && ! self::hasColumn('mata_pelajaran', 'tenant_id')) {
                $hasMapelRows = DB::table('mata_pelajaran')->exists()
                    && ($hasSyncMapel || $hasJadwal || $hasRombel || $hasSiswa);
            } else {
                $hasMapelRows = self::scopedQuery('mata_pelajaran', $tenantId, $isMultiSchool)->exists();
            }
        }

        $hasCoreDapodikData = $hasSekolah || $hasSiswa || $hasPtk || $hasRombel || $hasMapelRows || $hasJadwal || $hasSyncLog;

        $hasSemester = self::hasTable($tables, 'semesters')
            && self::scopedQuery('semesters', $tenantId, $isMultiSchool)->exists();

        $hasNilaiErapor = self::hasTable($tables, 'nilai_erapor')
            && self::scopedQuery('nilai_erapor', $tenantId, $isMultiSchool)->exists();

        $hasPresensiData = (self::hasTable($tables, 'kehadiran') && self::scopedQuery('kehadiran', $tenantId, $isMultiSchool)->exists())
            || (self::hasTable($tables, 'presensi_pegawai') && self::scopedQuery('presensi_pegawai', $tenantId, $isMultiSchool)->exists())
            || (self::hasTable($tables, 'presensi_lokasi') && self::scopedQuery('presensi_lokasi', $tenantId, $isMultiSchool)->exists());

        $hasPresensi = (self::hasTable($tables, 'kehadiran') || self::hasTable($tables, 'presensi_pegawai'))
            && ($hasSiswa || $hasPtk || $hasPresensiData);

        $hasEkstrakurikuler = self::hasTable($tables, 'ekstrakurikuler')
            && ($hasSiswa || self::scopedQuery('ekstrakurikuler', $tenantId, $isMultiSchool)->exists());

        $hasKokurikulerRows = false;
        if (self::hasTable($tables, 'tema_kokurikuler') && ! $isMultiSchool) {
            $hasKokurikulerRows = DB::table('tema_kokurikuler')->exists();
        }
        $hasKokurikuler = self::hasTable($tables, 'tema_kokurikuler')
            && ($hasSiswa || $hasKokurikulerRows);

        $hasErapor = $hasSiswa || $hasNilaiErapor;

        $hasCbtBanks = self::hasTable($tables, 'cbt_question_banks')
            && self::scopedQuery('cbt_question_banks', $tenantId, $isMultiSchool)->exists();
        $hasCbt = self::hasTable($tables, 'cbt_question_banks')
            && ($hasSiswa || $hasPtk || $hasCbtBanks);

        $hasTryoutPackages = self::hasTable($tables, 'tryout_packages')
            && self::scopedQuery('tryout_packages', $tenantId, $isMultiSchool)->exists();
        $hasTryout = self::hasTable($tables, 'tryout_packages')
            && ($hasSiswa || $hasPtk || $hasTryoutPackages);

        $hasTkaData = (self::hasTable($tables, 'tka_student_assessments') && self::scopedQuery('tka_student_assessments', $tenantId, $isMultiSchool)->exists())
            || (self::hasTable($tables, 'tka_kesiapan_pemetaan') && self::scopedQuery('tka_kesiapan_pemetaan', $tenantId, $isMultiSchool)->exists())
            || (! $isMultiSchool && self::hasTable($tables, 'tka_dimensions_enhanced') && DB::table('tka_dimensions_enhanced')->exists());

        $hasTka = (self::hasTable($tables, 'tka_student_assessments') || self::hasTable($tables, 'tka_kesiapan_pengaturan'))
            && ($hasSiswa || $hasTkaData);

        $hasAktivitas = self::hasTable($tables, 'aktivitas')
            && self::scopedQuery('aktivitas', $tenantId, $isMultiSchool)->exists();

        $hasAnyData = $hasCoreDapodikData
            || $hasSemester
            || $hasNilaiErapor
            || $hasPresensiData
            || $hasCbtBanks
            || $hasTryoutPackages
            || $hasTkaData;

        return array_merge($base, [
            'has_sekolah' => $hasSekolah,
            'has_semester' => $hasSemester,
            'has_ptk' => $hasPtk,
            'has_siswa' => $hasSiswa,
            'has_rombel' => $hasRombel,
            'has_mapel' => $hasMapelRows,
            'has_jadwal' => $hasJadwal,
            'has_non_admin_users' => $hasNonAdminUsers,
            'has_nilai_erapor' => $hasNilaiErapor,
            'has_presensi' => $hasPresensi,
            'has_ekstrakurikuler' => $hasEkstrakurikuler,
            'has_kokurikuler' => $hasKokurikuler,
            'has_erapor' => $hasErapor,
            'has_cbt' => $hasCbt,
            'has_tryout' => $hasTryout,
            'has_tka' => $hasTka,
            'has_aktivitas' => $hasAktivitas,
            'has_any_data' => $hasAnyData,
        ]);
    }
}
