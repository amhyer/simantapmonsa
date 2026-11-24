<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mata_pelajaran')) {
            Schema::table('mata_pelajaran', function (Blueprint $table) {
                if (! Schema::hasColumn('mata_pelajaran', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                }
            });

            $hasOldMapelUnique = Schema::hasIndex('mata_pelajaran', 'mata_pelajaran_nama_jenjang_unique');
            $hasNewMapelUnique = Schema::hasIndex('mata_pelajaran', 'mata_pelajaran_tenant_nama_jenjang_unique');
            if ($hasOldMapelUnique || ! $hasNewMapelUnique) {
                Schema::table('mata_pelajaran', function (Blueprint $table) use ($hasOldMapelUnique, $hasNewMapelUnique) {
                    if ($hasOldMapelUnique) {
                        $table->dropUnique('mata_pelajaran_nama_jenjang_unique');
                    }
                    if (! $hasNewMapelUnique) {
                        $table->unique(['tenant_id', 'nama', 'jenjang'], 'mata_pelajaran_tenant_nama_jenjang_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('semesters')) {
            Schema::table('semesters', function (Blueprint $table) {
                if (! Schema::hasColumn('semesters', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                }
            });

            $hasOldSemesterUnique = Schema::hasIndex('semesters', 'semesters_semester_id_unique');
            $hasNewSemesterUnique = Schema::hasIndex('semesters', 'semesters_tenant_semester_id_unique');
            if ($hasOldSemesterUnique || ! $hasNewSemesterUnique) {
                Schema::table('semesters', function (Blueprint $table) use ($hasOldSemesterUnique, $hasNewSemesterUnique) {
                    if ($hasOldSemesterUnique) {
                        $table->dropUnique('semesters_semester_id_unique');
                    }
                    if (! $hasNewSemesterUnique) {
                        $table->unique(['tenant_id', 'semester_id'], 'semesters_tenant_semester_id_unique');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mata_pelajaran')) {
            $hasNewMapelUnique = Schema::hasIndex('mata_pelajaran', 'mata_pelajaran_tenant_nama_jenjang_unique');
            $hasOldMapelUnique = Schema::hasIndex('mata_pelajaran', 'mata_pelajaran_nama_jenjang_unique');
            Schema::table('mata_pelajaran', function (Blueprint $table) use ($hasNewMapelUnique, $hasOldMapelUnique) {
                if ($hasNewMapelUnique) {
                    $table->dropUnique('mata_pelajaran_tenant_nama_jenjang_unique');
                }
                if (Schema::hasColumn('mata_pelajaran', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                if (! $hasOldMapelUnique) {
                    $table->unique(['nama', 'jenjang'], 'mata_pelajaran_nama_jenjang_unique');
                }
            });
        }

        if (Schema::hasTable('semesters')) {
            $hasNewSemesterUnique = Schema::hasIndex('semesters', 'semesters_tenant_semester_id_unique');
            $hasOldSemesterUnique = Schema::hasIndex('semesters', 'semesters_semester_id_unique');
            Schema::table('semesters', function (Blueprint $table) use ($hasNewSemesterUnique, $hasOldSemesterUnique) {
                if ($hasNewSemesterUnique) {
                    $table->dropUnique('semesters_tenant_semester_id_unique');
                }
                if (Schema::hasColumn('semesters', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                if (! $hasOldSemesterUnique) {
                    $table->unique(['semester_id'], 'semesters_semester_id_unique');
                }
            });
        }
    }
};
