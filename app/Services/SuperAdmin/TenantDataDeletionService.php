<?php

namespace App\Services\SuperAdmin;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantDataDeletionService
{
    public function deleteTenantData(Tenant $tenant): void
    {
        $tenantId = $tenant->id;
        $tables = [];
        foreach (Schema::getTableListing() as $entry) {
            $name = (string) $entry;
            if (str_contains($name, '.')) {
                $name = substr($name, strrpos($name, '.') + 1);
            }
            $tables[strtolower($name)] = $name;
        }

        $driver = DB::getDriverName();
        // Jangan panggil `disableForeignKeyConstraints()` pada PostgreSQL:
        // perintah `session_replication_role` membutuhkan hak superuser dan gagal
        // pada user non-superuser (`simantap_app`). Gunakan urutan child -> parent.
        if ($driver !== 'pgsql') {
            Schema::disableForeignKeyConstraints();
        }
        DB::beginTransaction();

        try {
            $siswaIds = isset($tables['siswa'])
                ? DB::table('siswa')->where('tenant_id', $tenantId)->lockForUpdate()->pluck('id')->all()
                : [];
            $rombelIds = isset($tables['rombel'])
                ? DB::table('rombel')->where('tenant_id', $tenantId)->lockForUpdate()->pluck('id')->all()
                : [];
            $userIds = isset($tables['users'])
                ? DB::table('users')->where('tenant_id', $tenantId)->lockForUpdate()->pluck('id')->all()
                : [];

            $orderedChildTables = [
                'cbt_student_answers', 'cbt_student_attempts', 'cbt_violations', 'cbt_results', 'cbt_test_sessions', 'cbt_questions', 'cbt_question_banks',
                'tryout_student_answers', 'tryout_results', 'tryout_sessions', 'tryout_question_options', 'tryout_questions', 'tryout_subjects', 'tryout_packages', 'tryout_categories',
                'tka_assessment_responses', 'tka_parent_reports', 'tka_early_warnings', 'tka_historical_tracking', 'tka_student_assessments', 'tka_assessment_items', 'tka_dimensions_enhanced',
                'tka_kesiapan_tindak_lanjut_revisi', 'tka_kesiapan_tindak_lanjut', 'tka_kesiapan_pemetaan', 'tka_kesiapan_audit', 'tka_kesiapan_pengaturan',
                'tka_learning_recommendations', 'tka_adaptive_simulations', 'tka_erapor_sync_logs',
                'exam_answers', 'exam_results', 'exam_violations', 'exam_attempts', 'exam_sessions', 'exam_question_options', 'exam_questions', 'exam_sections', 'exam_banks',
                'kelas_digital_progress', 'kelas_digital_pengumpulan', 'kelas_digital_tugas', 'kelas_digital_materi_item', 'kelas_digital_pertemuan', 'kelas_digital_modul', 'kelas_digital',
                'jurnal_mengajar', 'perangkat_pembelajaran',
                'pm_visitasi_pengawas', 'pm_supervisi', 'pm_kktp_item', 'pm_kktp_interval', 'pm_kokurikuler', 'pm_prosem', 'pm_prota', 'pm_hari_libur', 'pm_kalender_pendidikan', 'pm_tujuan_pembelajaran', 'pm_capaian_pembelajaran', 'pm_buku_paket', 'pm_alokasi_waktu', 'pm_ai_usage_logs',
                'p5_penilaian', 'p5_siswa', 'p5_projek', 'kenaikan_kelas', 'siswa_belajar', 'prestasi',
                'kehadiran_mapel', 'presensi_sesi_qr', 'presensi_pegawai', 'presensi_lokasi', 'pengajuan_izin_presensi', 'presensi_mesin_sync_logs',
                'dapodik_import_logs', 'dapodik_sync_logs', 'dapodik_data_cache', 'dapodik_configs',
                'anggota_kelompok', 'kelompok_kokurikuler', 'kegiatan_kokurikuler', 'tema_kokurikuler', 'siswa_ekstrakurikuler', 'ekstrakurikuler',
                'nilai_cp', 'nilai_mapel', 'capaian_pembelajaran', 'nilai_erapor_audit_logs', 'transkrip_nilai',
                'hasil_kuis', 'kuis', 'materi', 'nilai_erapor', 'nilai', 'kehadiran', 'catatan', 'dimensi', 'kebiasaan', 'orang_tua_siswa',
                'tanggal_rapor', 'pengaturan_guru', 'ringkasan_guru', 'aktivitas', 'menu_usage_daily',
                'anggota_rombel', 'pembelajaran', 'jadwal_pelajaran', 'siswa', 'ptk', 'rombel', 'mata_pelajaran', 'semesters', 'sekolah_settings',
            ];

            $deleteScoped = function (string $tbl) use ($tenantId, $siswaIds, $rombelIds, $userIds): void {
                if (Schema::hasColumn($tbl, 'tenant_id')) {
                    DB::table($tbl)->where('tenant_id', $tenantId)->delete();
                }
                if ($siswaIds !== [] && Schema::hasColumn($tbl, 'siswa_id')) {
                    DB::table($tbl)->whereIn('siswa_id', $siswaIds)->delete();
                }
                if ($rombelIds !== [] && Schema::hasColumn($tbl, 'rombel_id')) {
                    DB::table($tbl)->whereIn('rombel_id', $rombelIds)->delete();
                }
                if ($userIds !== []) {
                    foreach (['guru_id', 'user_id', 'orang_tua_id', 'created_by'] as $col) {
                        if (Schema::hasColumn($tbl, $col)) {
                            DB::table($tbl)->whereIn($col, $userIds)->delete();
                        }
                    }
                }
            };

            $this->cleanCbtHierarchy($tenantId, $userIds, $siswaIds);

            foreach ($orderedChildTables as $tblKey) {
                if (isset($tables[$tblKey])) {
                    $deleteScoped($tables[$tblKey]);
                }
            }

            foreach ($tables as $lower => $actual) {
                if (in_array($lower, ['tenants', 'users', 'migrations'], true)) {
                    continue;
                }
                if (Schema::hasColumn($actual, 'tenant_id')) {
                    DB::table($actual)->where('tenant_id', $tenantId)->delete();
                }
            }

            if ($userIds !== []) {
                $this->detachAndDeleteUserDependents($userIds);
                DB::table('users')->whereIn('id', $userIds)->delete();
            }

            $tenant->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            if ($driver !== 'pgsql') {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function detachAndDeleteUserDependents(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $nullableRefs = [
            'siswa' => ['guru_id'],
            'sekolah_settings' => ['guru_id'],
            'tka_early_warnings' => ['ditangani_oleh'],
            'pm_visitasi_pengawas' => ['guru_id', 'pengawas_id'],
            'pm_supervisi' => ['kepsek_id', 'pengawas_id'],
        ];
        foreach ($nullableRefs as $tbl => $cols) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            foreach ($cols as $col) {
                if (Schema::hasColumn($tbl, $col)) {
                    DB::table($tbl)->whereIn($col, $userIds)->update([$col => null]);
                }
            }
        }

        $this->cleanCbtHierarchy(null, $userIds, []);

        if (Schema::hasTable('jadwal_pelajaran') && Schema::hasColumn('jadwal_pelajaran', 'guru_id')) {
            $jadwalIds = DB::table('jadwal_pelajaran')->whereIn('guru_id', $userIds)->pluck('id')->all();
            if ($jadwalIds !== []) {
                foreach (['kehadiran_mapel', 'presensi_sesi_qr'] as $jadwalChild) {
                    if (Schema::hasTable($jadwalChild) && Schema::hasColumn($jadwalChild, 'jadwal_pelajaran_id')) {
                        DB::table($jadwalChild)->whereIn('jadwal_pelajaran_id', $jadwalIds)->delete();
                    }
                }
            }
        }

        $directDeleteRefs = [
            'cbt_student_attempts' => ['user_id'],
            'cbt_results' => ['user_id'],
            'cbt_test_sessions' => ['guru_id'],
            'kehadiran_mapel' => ['guru_id'],
            'presensi_sesi_qr' => ['guru_id'],
            'jadwal_pelajaran' => ['guru_id'],
            'pengajuan_izin_presensi' => ['orang_tua_id', 'diproses_oleh'],
            'presensi_pegawai' => ['user_id'],
            'dapodik_import_logs' => ['user_id'],
            'dapodik_sync_logs' => ['user_id'],
            'aktivitas' => ['guru_id'],
            'api_keys' => ['user_id'],
            'sessions' => ['user_id'],
        ];
        foreach ($directDeleteRefs as $tbl => $cols) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            foreach ($cols as $col) {
                if (Schema::hasColumn($tbl, $col)) {
                    DB::table($tbl)->whereIn($col, $userIds)->delete();
                }
            }
        }
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $siswaIds
     */
    public function cleanCbtHierarchy(?int $tenantId, array $userIds, array $siswaIds): void
    {
        $bankIds = [];
        if (Schema::hasTable('cbt_question_banks')) {
            $bankIds = DB::table('cbt_question_banks')->where(function ($q) use ($tenantId, $userIds) {
                $matched = false;
                if ($tenantId !== null && Schema::hasColumn('cbt_question_banks', 'tenant_id')) {
                    $q->orWhere('tenant_id', $tenantId);
                    $matched = true;
                }
                if ($userIds !== [] && Schema::hasColumn('cbt_question_banks', 'guru_id')) {
                    $q->orWhereIn('guru_id', $userIds);
                    $matched = true;
                }
                if (! $matched) {
                    $q->whereRaw('1 = 0');
                }
            })->pluck('id')->all();
        }

        $sessIds = [];
        if (Schema::hasTable('cbt_test_sessions')) {
            $sessIds = DB::table('cbt_test_sessions')->where(function ($q) use ($tenantId, $userIds, $bankIds) {
                $matched = false;
                if ($tenantId !== null && Schema::hasColumn('cbt_test_sessions', 'tenant_id')) {
                    $q->orWhere('tenant_id', $tenantId);
                    $matched = true;
                }
                if ($bankIds !== [] && Schema::hasColumn('cbt_test_sessions', 'bank_id')) {
                    $q->orWhereIn('bank_id', $bankIds);
                    $matched = true;
                }
                if ($userIds !== [] && Schema::hasColumn('cbt_test_sessions', 'guru_id')) {
                    $q->orWhereIn('guru_id', $userIds);
                    $matched = true;
                }
                if (! $matched) {
                    $q->whereRaw('1 = 0');
                }
            })->pluck('id')->all();
        }

        if (Schema::hasTable('cbt_student_attempts')) {
            $attemptIds = DB::table('cbt_student_attempts')->where(function ($q) use ($tenantId, $sessIds, $userIds, $siswaIds) {
                $matched = false;
                if ($tenantId !== null && Schema::hasColumn('cbt_student_attempts', 'tenant_id')) {
                    $q->orWhere('tenant_id', $tenantId);
                    $matched = true;
                }
                if ($sessIds !== [] && Schema::hasColumn('cbt_student_attempts', 'session_id')) {
                    $q->orWhereIn('session_id', $sessIds);
                    $matched = true;
                }
                if ($userIds !== [] && Schema::hasColumn('cbt_student_attempts', 'user_id')) {
                    $q->orWhereIn('user_id', $userIds);
                    $matched = true;
                }
                if ($siswaIds !== [] && Schema::hasColumn('cbt_student_attempts', 'siswa_id')) {
                    $q->orWhereIn('siswa_id', $siswaIds);
                    $matched = true;
                }
                if (! $matched) {
                    $q->whereRaw('1 = 0');
                }
            })->pluck('id')->all();

            if ($attemptIds !== []) {
                if (Schema::hasTable('cbt_student_answers') && Schema::hasColumn('cbt_student_answers', 'attempt_id')) {
                    DB::table('cbt_student_answers')->whereIn('attempt_id', $attemptIds)->delete();
                }
                DB::table('cbt_student_attempts')->whereIn('id', $attemptIds)->delete();
            }
        }

        if ($bankIds !== [] && Schema::hasTable('cbt_questions')) {
            $qIds = DB::table('cbt_questions')->whereIn('bank_id', $bankIds)->pluck('id')->all();
            if ($qIds !== [] && Schema::hasTable('cbt_student_answers') && Schema::hasColumn('cbt_student_answers', 'question_id')) {
                DB::table('cbt_student_answers')->whereIn('question_id', $qIds)->delete();
            }
        }

        if (Schema::hasTable('cbt_results')) {
            $resIds = DB::table('cbt_results')->where(function ($q) use ($tenantId, $sessIds, $userIds, $siswaIds) {
                $matched = false;
                if ($tenantId !== null && Schema::hasColumn('cbt_results', 'tenant_id')) {
                    $q->orWhere('tenant_id', $tenantId);
                    $matched = true;
                }
                if ($sessIds !== [] && Schema::hasColumn('cbt_results', 'session_id')) {
                    $q->orWhereIn('session_id', $sessIds);
                    $matched = true;
                }
                if ($userIds !== [] && Schema::hasColumn('cbt_results', 'user_id')) {
                    $q->orWhereIn('user_id', $userIds);
                    $matched = true;
                }
                if ($siswaIds !== [] && Schema::hasColumn('cbt_results', 'siswa_id')) {
                    $q->orWhereIn('siswa_id', $siswaIds);
                    $matched = true;
                }
                if (! $matched) {
                    $q->whereRaw('1 = 0');
                }
            })->pluck('id')->all();

            if ($resIds !== []) {
                if (Schema::hasTable('cbt_violations') && Schema::hasColumn('cbt_violations', 'result_id')) {
                    DB::table('cbt_violations')->whereIn('result_id', $resIds)->delete();
                }
                DB::table('cbt_results')->whereIn('id', $resIds)->delete();
            }
        }

        if ($sessIds !== [] && Schema::hasTable('cbt_test_sessions')) {
            DB::table('cbt_test_sessions')->whereIn('id', $sessIds)->delete();
        }

        if ($bankIds !== []) {
            if (Schema::hasTable('cbt_questions')) {
                DB::table('cbt_questions')->whereIn('bank_id', $bankIds)->delete();
            }
            if (Schema::hasTable('cbt_question_banks')) {
                DB::table('cbt_question_banks')->whereIn('id', $bankIds)->delete();
            }
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function forceDeleteUsers(array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        $driver = DB::getDriverName();
        // Di PostgreSQL, `Schema::disableForeignKeyConstraints()` menjalankan
        // `SET session_replication_role = 'replica'` yang butuh hak superuser;
        // `detachAndDeleteUserDependents` sudah menghapus child secara berurutan.
        if ($driver !== 'pgsql') {
            Schema::disableForeignKeyConstraints();
        }
        DB::beginTransaction();
        try {
            $this->detachAndDeleteUserDependents($userIds);
            $count = \App\Models\User::withoutGlobalScopes()
                ->whereIn('id', $userIds)
                ->forceDelete();
            DB::commit();

            return (int) $count;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            if ($driver !== 'pgsql') {
                Schema::enableForeignKeyConstraints();
            }
        }
    }
}
