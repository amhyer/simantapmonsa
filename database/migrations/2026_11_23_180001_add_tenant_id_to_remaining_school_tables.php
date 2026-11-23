<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tanggal_rapor')) {
            Schema::table('tanggal_rapor', function (Blueprint $table) {
                if (! Schema::hasColumn('tanggal_rapor', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                }
            });

            Schema::table('tanggal_rapor', function (Blueprint $table) {
                try {
                    $table->dropUnique('tanggal_rapor_tahun_ajaran_semester_unique');
                } catch (\Throwable) {
                }
                $table->unique(['tenant_id', 'tahun_ajaran', 'semester'], 'tanggal_rapor_tenant_tahun_semester_unique');
            });
        }

        foreach (['tema_kokurikuler', 'kegiatan_kokurikuler', 'kelompok_kokurikuler', 'tka_assessment_items'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (! Schema::hasColumn($tbl, 'tenant_id')) {
                        $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                        $table->index('tenant_id');
                    }
                });
            }
        }

        if (Schema::hasTable('tka_dimensions_enhanced')) {
            Schema::table('tka_dimensions_enhanced', function (Blueprint $table) {
                if (! Schema::hasColumn('tka_dimensions_enhanced', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                }
            });

            Schema::table('tka_dimensions_enhanced', function (Blueprint $table) {
                try {
                    $table->dropUnique('tka_dimensions_enhanced_kode_dimensi_unique');
                } catch (\Throwable) {
                }
                $table->unique(['tenant_id', 'kode_dimensi'], 'tka_dimensions_tenant_kode_unique');
            });
        }

        if (Schema::hasTable('tryout_packages') && Schema::hasColumn('tryout_packages', 'tenant_id')) {
            Schema::table('tryout_packages', function (Blueprint $table) {
                try {
                    $table->dropUnique('tryout_packages_kode_paket_unique');
                } catch (\Throwable) {
                }
                $table->unique(['tenant_id', 'kode_paket'], 'tryout_packages_tenant_kode_unique');
            });
        }

        if (Schema::hasTable('tryout_sessions') && Schema::hasColumn('tryout_sessions', 'tenant_id')) {
            Schema::table('tryout_sessions', function (Blueprint $table) {
                try {
                    $table->dropUnique('tryout_sessions_kode_sesi_unique');
                } catch (\Throwable) {
                }
                $table->unique(['tenant_id', 'kode_sesi'], 'tryout_sessions_tenant_kode_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tryout_sessions')) {
            Schema::table('tryout_sessions', function (Blueprint $table) {
                try {
                    $table->dropUnique('tryout_sessions_tenant_kode_unique');
                } catch (\Throwable) {
                }
                $table->unique(['kode_sesi'], 'tryout_sessions_kode_sesi_unique');
            });
        }

        if (Schema::hasTable('tryout_packages')) {
            Schema::table('tryout_packages', function (Blueprint $table) {
                try {
                    $table->dropUnique('tryout_packages_tenant_kode_unique');
                } catch (\Throwable) {
                }
                $table->unique(['kode_paket'], 'tryout_packages_kode_paket_unique');
            });
        }

        if (Schema::hasTable('tka_dimensions_enhanced')) {
            Schema::table('tka_dimensions_enhanced', function (Blueprint $table) {
                try {
                    $table->dropUnique('tka_dimensions_tenant_kode_unique');
                } catch (\Throwable) {
                }
                if (Schema::hasColumn('tka_dimensions_enhanced', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                $table->unique(['kode_dimensi'], 'tka_dimensions_enhanced_kode_dimensi_unique');
            });
        }

        foreach (['tka_assessment_items', 'kelompok_kokurikuler', 'kegiatan_kokurikuler', 'tema_kokurikuler'] as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'tenant_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }

        if (Schema::hasTable('tanggal_rapor')) {
            Schema::table('tanggal_rapor', function (Blueprint $table) {
                try {
                    $table->dropUnique('tanggal_rapor_tenant_tahun_semester_unique');
                } catch (\Throwable) {
                }
                if (Schema::hasColumn('tanggal_rapor', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                $table->unique(['tahun_ajaran', 'semester'], 'tanggal_rapor_tahun_ajaran_semester_unique');
            });
        }
    }
};
