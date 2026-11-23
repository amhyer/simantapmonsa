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

            Schema::table('mata_pelajaran', function (Blueprint $table) {
                try {
                    $table->dropUnique('mata_pelajaran_nama_jenjang_unique');
                } catch (\Throwable) {
                    // Index sudah dihapus atau bernama lain
                }
                $table->unique(['tenant_id', 'nama', 'jenjang'], 'mata_pelajaran_tenant_nama_jenjang_unique');
            });
        }

        if (Schema::hasTable('semesters')) {
            Schema::table('semesters', function (Blueprint $table) {
                if (! Schema::hasColumn('semesters', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                }
            });

            Schema::table('semesters', function (Blueprint $table) {
                try {
                    $table->dropUnique('semesters_semester_id_unique');
                } catch (\Throwable) {
                    // Index sudah dihapus atau bernama lain
                }
                $table->unique(['tenant_id', 'semester_id'], 'semesters_tenant_semester_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mata_pelajaran')) {
            Schema::table('mata_pelajaran', function (Blueprint $table) {
                try {
                    $table->dropUnique('mata_pelajaran_tenant_nama_jenjang_unique');
                } catch (\Throwable) {
                }
                if (Schema::hasColumn('mata_pelajaran', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                $table->unique(['nama', 'jenjang'], 'mata_pelajaran_nama_jenjang_unique');
            });
        }

        if (Schema::hasTable('semesters')) {
            Schema::table('semesters', function (Blueprint $table) {
                try {
                    $table->dropUnique('semesters_tenant_semester_id_unique');
                } catch (\Throwable) {
                }
                if (Schema::hasColumn('semesters', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                $table->unique(['semester_id'], 'semesters_semester_id_unique');
            });
        }
    }
};
