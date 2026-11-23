<?php

namespace App\Services\Admin;

use App\Modules\Tenant\Models\Tenant;
use App\Services\Media\SiswaFotoStorage;
use App\Services\Navigation\SchoolDataAvailabilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Mengosongkan seluruh data milik satu sekolah hingga bersih dan hanya
 * menyisakan akun Administrator (`admin` / `super_admin`).
 *
 * Dijamin 100% terisolasi per `tenant_id` pada mode multi-sekolah sehingga
 * data sekolah lain tidak pernah tersentuh.
 */
class SchoolDataResetService
{
    /** Tabel infrastruktur & identitas yang ditangani secara khusus / tidak dihapus massal. */
    private const PROTECTED_TABLES = [
        'migrations',
        'tenants',
        'users',
        'api_keys',
        'sessions',
        'password_reset_tokens',
        'dapodik_configs',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * @return array{
     *     siswa: int,
     *     rombel: int,
     *     ptk: int,
     *     users: int,
     *     jadwal: int,
     *     tabel_dibersihkan: int
     * }
     */
    public function resetSchoolKeepAdmins(?int $tenantId, ?int $actingAdminId = null): array
    {
        $hasTenants = Schema::hasTable('tenants') && Tenant::hasAnyTenant();
        $isMultiSchool = config('simantap.multi_tenant_enabled', false) || $tenantId !== null || $hasTenants;

        if ($isMultiSchool && $tenantId === null) {
            throw new \RuntimeException('Akun admin belum terhubung ke sekolah.');
        }

        $tables = $this->normalizedTables();
        $fotoPaths = [];
        $stats = [
            'siswa' => 0,
            'rombel' => 0,
            'ptk' => 0,
            'users' => 0,
            'jadwal' => 0,
            'tabel_dibersihkan' => 0,
        ];

        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();
        try {
            // 1. Tentukan akun Admin yang dipertahankan vs seluruh akun lain yang dihapus total
            $preservedAdminIds = collect();
            $deletedUserIds = collect();
            $deletedEmails = collect();

            if (isset($tables['users'])) {
                $usersQuery = DB::table('users');
                if ($isMultiSchool && Schema::hasColumn('users', 'tenant_id')) {
                    $usersQuery->where('tenant_id', $tenantId);
                }
                $allUsers = $usersQuery->get(['id', 'peran', 'email', 'deleted_at']);

                foreach ($allUsers as $u) {
                    $isActiveAdmin = in_array($u->peran, ['admin', 'super_admin'], true) && $u->deleted_at === null;
                    $isCurrentActor = $actingAdminId !== null && (int) $u->id === (int) $actingAdminId;

                    if ($isActiveAdmin || $isCurrentActor) {
                        $preservedAdminIds->push((int) $u->id);
                    } else {
                        $deletedUserIds->push((int) $u->id);
                        if (! empty($u->email)) {
                            $deletedEmails->push((string) $u->email);
                        }
                    }
                }
            }

            $allSchoolUserIds = $preservedAdminIds->merge($deletedUserIds)->unique()->values();

            // 2. Kumpulkan ID induk milik sekolah ini (termasuk baris yang sudah soft-deleted)
            $siswaIds = collect();
            if (isset($tables['siswa'])) {
                $siswaQuery = DB::table('siswa');
                if ($isMultiSchool && Schema::hasColumn('siswa', 'tenant_id')) {
                    $siswaQuery->where('tenant_id', $tenantId);
                }
                $siswaRows = $siswaQuery->get(['id', 'foto']);
                $siswaIds = $siswaRows->pluck('id')->map(fn ($id) => (int) $id)->values();
                $fotoPaths = $siswaRows->pluck('foto')->filter()->unique()->values()->all();
                $stats['siswa'] = $siswaIds->count();
            }

            $rombelIds = collect();
            if (isset($tables['rombel'])) {
                $rombelQuery = DB::table('rombel');
                if ($isMultiSchool && Schema::hasColumn('rombel', 'tenant_id')) {
                    $rombelQuery->where('tenant_id', $tenantId);
                }
                $rombelIds = $rombelQuery->pluck('id')->map(fn ($id) => (int) $id)->values();
                $stats['rombel'] = $rombelIds->count();
            }

            if (isset($tables['ptk'])) {
                $ptkQuery = DB::table('ptk');
                if ($isMultiSchool && Schema::hasColumn('ptk', 'tenant_id')) {
                    $ptkQuery->where('tenant_id', $tenantId);
                }
                $stats['ptk'] = $ptkQuery->count();
            }

            if (isset($tables['jadwal_pelajaran'])) {
                $jadwalQuery = DB::table('jadwal_pelajaran');
                if ($isMultiSchool && Schema::hasColumn('jadwal_pelajaran', 'tenant_id')) {
                    $jadwalQuery->where('tenant_id', $tenantId);
                }
                $stats['jadwal'] = $jadwalQuery->count();
            }

            // 3. Bersihkan tabel cucu berantai (CBT, Tryout, TKA, Exam Engine, Kelas Digital)
            $this->cleanNestedAssessmentTables(
                $tables,
                $isMultiSchool,
                $tenantId,
                $siswaIds,
                $rombelIds,
                $allSchoolUserIds
            );

            // 4. Bersihkan seluruh tabel data operasional sekolah
            $cleanedTables = [];
            foreach ($tables as $lowerName => $actualTable) {
                if (in_array($lowerName, self::PROTECTED_TABLES, true) || $lowerName === 'semesters') {
                    continue;
                }

                if ($isMultiSchool) {
                    $touched = false;

                    if (Schema::hasColumn($actualTable, 'tenant_id')) {
                        DB::table($actualTable)->where('tenant_id', $tenantId)->delete();
                        $touched = true;
                    }
                    if ($siswaIds->isNotEmpty() && Schema::hasColumn($actualTable, 'siswa_id')) {
                        DB::table($actualTable)->whereIn('siswa_id', $siswaIds)->delete();
                        $touched = true;
                    }
                    if ($rombelIds->isNotEmpty() && Schema::hasColumn($actualTable, 'rombel_id')) {
                        DB::table($actualTable)->whereIn('rombel_id', $rombelIds)->delete();
                        $touched = true;
                    }
                    if ($allSchoolUserIds->isNotEmpty() && Schema::hasColumn($actualTable, 'guru_id')) {
                        DB::table($actualTable)->whereIn('guru_id', $allSchoolUserIds)->delete();
                        $touched = true;
                    }
                    if ($allSchoolUserIds->isNotEmpty() && Schema::hasColumn($actualTable, 'user_id')) {
                        DB::table($actualTable)->whereIn('user_id', $allSchoolUserIds)->delete();
                        $touched = true;
                    }
                    if ($allSchoolUserIds->isNotEmpty() && Schema::hasColumn($actualTable, 'created_by')) {
                        DB::table($actualTable)->whereIn('created_by', $allSchoolUserIds)->delete();
                        $touched = true;
                    }
                    if ($allSchoolUserIds->isNotEmpty() && Schema::hasColumn($actualTable, 'koordinator_id')) {
                        DB::table($actualTable)->whereIn('koordinator_id', $allSchoolUserIds)->delete();
                        $touched = true;
                    }
                    if ($allSchoolUserIds->isNotEmpty() && Schema::hasColumn($actualTable, 'orang_tua_id')) {
                        DB::table($actualTable)->whereIn('orang_tua_id', $allSchoolUserIds)->delete();
                        $touched = true;
                    }
                    if ($allSchoolUserIds->isNotEmpty() && Schema::hasColumn($actualTable, 'dibuat_oleh')) {
                        DB::table($actualTable)->whereIn('dibuat_oleh', $allSchoolUserIds)->delete();
                        $touched = true;
                    }

                    if ($touched) {
                        $cleanedTables[] = $actualTable;
                    }
                } else {
                    DB::table($actualTable)->delete();
                    $cleanedTables[] = $actualTable;
                }
            }

            // Hapus `semesters` paling akhir setelah seluruh tabel anak (`siswa`, `ptk`, `rombel`, `tryout_*`, `tka_*`)
            // dibersihkan agar tidak melanggar FK constraint (terutama pada transaksi SQLite).
            if (isset($tables['semesters'])) {
                if ($isMultiSchool && Schema::hasColumn('semesters', 'tenant_id')) {
                    DB::table('semesters')->where('tenant_id', $tenantId)->delete();
                    $cleanedTables[] = $tables['semesters'];
                } elseif (! $isMultiSchool) {
                    DB::table('semesters')->delete();
                    $cleanedTables[] = $tables['semesters'];
                }
            }

            $stats['tabel_dibersihkan'] = count(array_unique($cleanedTables));

            // 5. Hapus seluruh akun non-admin beserta sesi, API key, dan token reset miliknya
            if ($deletedUserIds->isNotEmpty()) {
                if (isset($tables['api_keys']) && Schema::hasColumn('api_keys', 'user_id')) {
                    DB::table('api_keys')->whereIn('user_id', $deletedUserIds)->delete();
                }
                if (isset($tables['sessions']) && Schema::hasColumn('sessions', 'user_id')) {
                    DB::table('sessions')->whereIn('user_id', $deletedUserIds)->delete();
                }
                if (isset($tables['password_reset_tokens']) && $deletedEmails->isNotEmpty() && Schema::hasColumn('password_reset_tokens', 'email')) {
                    DB::table('password_reset_tokens')->whereIn('email', $deletedEmails)->delete();
                }

                $stats['users'] = DB::table('users')->whereIn('id', $deletedUserIds)->delete();
            }

            // Pastikan akun Admin yang dipertahankan tidak memegang referensi siswa_id lama
            if ($preservedAdminIds->isNotEmpty() && isset($tables['users']) && Schema::hasColumn('users', 'terhubung_dengan')) {
                DB::table('users')
                    ->whereIn('id', $preservedAdminIds)
                    ->update(['terhubung_dengan' => json_encode([])]);
            }

            // 6. Reset jejak waktu sinkronisasi pada konfigurasi Dapodik milik sekolah ini
            if (isset($tables['dapodik_configs'])) {
                $cfgQuery = DB::table('dapodik_configs');
                if ($isMultiSchool && Schema::hasColumn('dapodik_configs', 'tenant_id')) {
                    $cfgQuery->where('tenant_id', $tenantId);
                }
                $cfgQuery->update([
                    'last_sync_at' => null,
                    'last_sync_by' => null,
                    'auto_sync_last_run_at' => null,
                    'auto_sync_run_status' => null,
                    'auto_sync_run_error' => null,
                ]);
            }

            // 7. Penyesuaian sequence PostgreSQL ke nilai maksimum aktual tabel
            if (DB::getDriverName() === 'pgsql') {
                foreach (['siswa', 'rombel', 'ptk', 'jadwal_pelajaran', 'dapodik_sync_logs'] as $seqTable) {
                    if (isset($tables[$seqTable]) && Schema::hasColumn($seqTable, 'id')) {
                        $seq = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS seq', [$seqTable, 'id'])?->seq;
                        if ($seq) {
                            $maxId = (int) DB::table($seqTable)->max('id');
                            DB::statement('SELECT setval(?, ?, ?)', [$seq, max($maxId, 1), $maxId > 0]);
                        }
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // 8. Hapus berkas fisik foto siswa setelah transaksi DB sukses
        if ($fotoPaths !== []) {
            try {
                $fotoStorage = app(SiswaFotoStorage::class);
                foreach ($fotoPaths as $path) {
                    $fotoStorage->deleteFromAllDisks((string) $path);
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal membersihkan sebagian file foto siswa saat reset data: '.$e->getMessage());
            }
        }

        // 9. Segarkan cache visibilitas menu, status sinkronisasi sidebar, dan dasbor
        SchoolDataAvailabilityService::forget($tenantId);

        Log::info('Pengosongan total data sekolah selesai (hanya akun admin dipertahankan).', [
            'tenant_id' => $tenantId,
            'admin_id' => $actingAdminId,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * @param  array<string, string>  $tables
     * @param  Collection<int, int>  $siswaIds
     * @param  Collection<int, int>  $rombelIds
     * @param  Collection<int, int>  $allSchoolUserIds
     */
    private function cleanNestedAssessmentTables(
        array $tables,
        bool $isMultiSchool,
        ?int $tenantId,
        Collection $siswaIds,
        Collection $rombelIds,
        Collection $allSchoolUserIds
    ): void {
        if (! $isMultiSchool) {
            // Mode sekolah tunggal akan mengosongkan seluruh tabel di langkah 4.
            return;
        }

        // ── CBT ──
        if (isset($tables['cbt_question_banks'])) {
            $bankIds = $this->scopedIds('cbt_question_banks', $tenantId, 'guru_id', $allSchoolUserIds);
            if ($bankIds->isNotEmpty() && isset($tables['cbt_questions']) && Schema::hasColumn('cbt_questions', 'bank_id')) {
                DB::table('cbt_questions')->whereIn('bank_id', $bankIds)->delete();
            }
        }
        if (isset($tables['cbt_results'])) {
            $cbtResultIds = DB::table('cbt_results')
                ->where(function ($q) use ($tenantId, $siswaIds, $allSchoolUserIds) {
                    $hasCond = false;
                    if ($tenantId !== null && Schema::hasColumn('cbt_results', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                        $hasCond = true;
                    }
                    if ($siswaIds->isNotEmpty() && Schema::hasColumn('cbt_results', 'siswa_id')) {
                        $q->orWhereIn('siswa_id', $siswaIds);
                        $hasCond = true;
                    }
                    if ($allSchoolUserIds->isNotEmpty() && Schema::hasColumn('cbt_results', 'user_id')) {
                        $q->orWhereIn('user_id', $allSchoolUserIds);
                        $hasCond = true;
                    }
                    if (! $hasCond) {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->pluck('id');

            if ($cbtResultIds->isNotEmpty() && isset($tables['cbt_violations']) && Schema::hasColumn('cbt_violations', 'result_id')) {
                DB::table('cbt_violations')->whereIn('result_id', $cbtResultIds)->delete();
            }
        }

        // ── Tryout ──
        if (isset($tables['tryout_packages']) && Schema::hasColumn('tryout_packages', 'tenant_id')) {
            $pkgIds = DB::table('tryout_packages')->where('tenant_id', $tenantId)->pluck('id');
            if ($pkgIds->isNotEmpty() && isset($tables['tryout_subjects'])) {
                $subjIds = DB::table('tryout_subjects')->whereIn('package_id', $pkgIds)->pluck('id');
                if ($subjIds->isNotEmpty() && isset($tables['tryout_questions'])) {
                    $qIds = DB::table('tryout_questions')->whereIn('tryout_subject_id', $subjIds)->pluck('id');
                    if ($qIds->isNotEmpty() && isset($tables['tryout_question_options'])) {
                        DB::table('tryout_question_options')->whereIn('question_id', $qIds)->delete();
                    }
                    DB::table('tryout_questions')->whereIn('id', $qIds)->delete();
                }
                DB::table('tryout_subjects')->whereIn('id', $subjIds)->delete();
            }
        }
        if (isset($tables['tryout_sessions'])) {
            $tryoutSessionIds = $this->scopedIds('tryout_sessions', $tenantId, 'guru_id', $allSchoolUserIds, $rombelIds);
            if ($tryoutSessionIds->isNotEmpty() && isset($tables['tryout_student_answers']) && Schema::hasColumn('tryout_student_answers', 'tryout_session_id')) {
                DB::table('tryout_student_answers')->whereIn('tryout_session_id', $tryoutSessionIds)->delete();
            }
        }

        // ── TKA ──
        if (isset($tables['tka_student_assessments'])) {
            $tkaIds = $this->scopedIds('tka_student_assessments', $tenantId, 'guru_id', $allSchoolUserIds, collect(), $siswaIds);
            if ($tkaIds->isNotEmpty() && isset($tables['tka_assessment_responses']) && Schema::hasColumn('tka_assessment_responses', 'assessment_id')) {
                DB::table('tka_assessment_responses')->whereIn('assessment_id', $tkaIds)->delete();
            }
        }

        // ── Exam Engine ──
        if (isset($tables['exam_banks'])) {
            $examBankIds = $this->scopedIds('exam_banks', $tenantId, 'guru_id', $allSchoolUserIds);
            if ($examBankIds->isNotEmpty()) {
                if (isset($tables['exam_questions']) && Schema::hasColumn('exam_questions', 'bank_id')) {
                    $examQIds = DB::table('exam_questions')->whereIn('bank_id', $examBankIds)->pluck('id');
                    if ($examQIds->isNotEmpty() && isset($tables['exam_question_options'])) {
                        DB::table('exam_question_options')->whereIn('question_id', $examQIds)->delete();
                    }
                    DB::table('exam_questions')->whereIn('id', $examQIds)->delete();
                }
                if (isset($tables['exam_sections']) && Schema::hasColumn('exam_sections', 'bank_id')) {
                    DB::table('exam_sections')->whereIn('bank_id', $examBankIds)->delete();
                }
            }
        }
        if (isset($tables['exam_attempts'])) {
            $attemptIds = $this->scopedIds('exam_attempts', $tenantId, 'user_id', $allSchoolUserIds, collect(), $siswaIds);
            if ($attemptIds->isNotEmpty()) {
                foreach (['exam_answers', 'exam_results', 'exam_violations'] as $childTbl) {
                    if (isset($tables[$childTbl]) && Schema::hasColumn($childTbl, 'attempt_id')) {
                        DB::table($childTbl)->whereIn('attempt_id', $attemptIds)->delete();
                    }
                }
            }
        }

        // ── Projek P5 ──
        if (isset($tables['p5_projek']) && $allSchoolUserIds->isNotEmpty() && Schema::hasColumn('p5_projek', 'koordinator_id')) {
            $projekIds = DB::table('p5_projek')->whereIn('koordinator_id', $allSchoolUserIds)->pluck('id');
            if ($projekIds->isNotEmpty() && isset($tables['p5_siswa']) && Schema::hasColumn('p5_siswa', 'projek_id')) {
                DB::table('p5_siswa')->whereIn('projek_id', $projekIds)->delete();
            }
        }
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @param  Collection<int, int>  $rombelIds
     * @param  Collection<int, int>  $siswaIds
     * @return Collection<int, int>
     */
    private function scopedIds(
        string $table,
        ?int $tenantId,
        string $userColumn,
        Collection $userIds,
        Collection $rombelIds = new Collection,
        Collection $siswaIds = new Collection
    ): Collection {
        return DB::table($table)
            ->where(function ($q) use ($table, $tenantId, $userColumn, $userIds, $rombelIds, $siswaIds) {
                $hasCond = false;
                if ($tenantId !== null && Schema::hasColumn($table, 'tenant_id')) {
                    $q->orWhere('tenant_id', $tenantId);
                    $hasCond = true;
                }
                if ($userIds->isNotEmpty() && Schema::hasColumn($table, $userColumn)) {
                    $q->orWhereIn($userColumn, $userIds);
                    $hasCond = true;
                }
                if ($rombelIds->isNotEmpty() && Schema::hasColumn($table, 'rombel_id')) {
                    $q->orWhereIn('rombel_id', $rombelIds);
                    $hasCond = true;
                }
                if ($siswaIds->isNotEmpty() && Schema::hasColumn($table, 'siswa_id')) {
                    $q->orWhereIn('siswa_id', $siswaIds);
                    $hasCond = true;
                }
                if (! $hasCond) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * @return array<string, string> lower_table_name => actual_table_name
     */
    private function normalizedTables(): array
    {
        $map = [];
        foreach (Schema::getTableListing() as $entry) {
            $name = (string) $entry;
            if (str_contains($name, '.')) {
                $name = substr($name, strrpos($name, '.') + 1);
            }
            $map[strtolower($name)] = $name;
        }

        return $map;
    }
}
