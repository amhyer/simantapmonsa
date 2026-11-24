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
        if ($driver !== 'pgsql') {
            Schema::disableForeignKeyConstraints();
        }
        DB::beginTransaction();
        if ($driver === 'pgsql') {
            Schema::disableForeignKeyConstraints();
        }

        try {
            $siswaIds = isset($tables['siswa'])
                ? DB::table('siswa')->where('tenant_id', $tenantId)->pluck('id')->all()
                : [];
            $rombelIds = isset($tables['rombel'])
                ? DB::table('rombel')->where('tenant_id', $tenantId)->pluck('id')->all()
                : [];
            $userIds = isset($tables['users'])
                ? DB::table('users')->where('tenant_id', $tenantId)->pluck('id')->all()
                : [];

            $orderedChildTables = [
                'cbt_student_answers', 'cbt_student_attempts', 'cbt_violations', 'cbt_results', 'cbt_test_sessions', 'cbt_questions', 'cbt_question_banks',
                'tryout_student_answers', 'tryout_results', 'tryout_sessions', 'tryout_question_options', 'tryout_questions', 'tryout_subjects', 'tryout_packages',
                'tka_assessment_responses', 'tka_parent_reports', 'tka_early_warnings', 'tka_historical_tracking', 'tka_student_assessments', 'tka_assessment_items', 'tka_dimensions_enhanced',
                'exam_answers', 'exam_results', 'exam_violations', 'exam_attempts', 'exam_sessions', 'exam_question_options', 'exam_questions', 'exam_sections', 'exam_banks',
                'presensi_sesi_qr', 'presensi_pegawai', 'pengajuan_izin_presensi',
                'anggota_kelompok', 'kelompok_kokurikuler', 'kegiatan_kokurikuler', 'tema_kokurikuler',
                'hasil_kuis', 'kuis', 'materi', 'nilai_erapor', 'nilai', 'kehadiran', 'catatan', 'dimensi', 'kebiasaan',
                'anggota_rombel', 'pembelajaran', 'jadwal_pelajaran', 'siswa', 'ptk', 'rombel', 'mata_pelajaran', 'semesters',
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
            Schema::enableForeignKeyConstraints();
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
            'visitasi_pengawas' => ['guru_id'],
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

        $directDeleteRefs = [
            'cbt_student_attempts' => ['user_id'],
            'cbt_results' => ['user_id'],
            'cbt_test_sessions' => ['guru_id'],
            'presensi_sesi_qr' => ['guru_id'],
            'pengajuan_izin_presensi' => ['orang_tua_id', 'diproses_oleh'],
            'presensi_pegawai' => ['user_id'],
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
}
