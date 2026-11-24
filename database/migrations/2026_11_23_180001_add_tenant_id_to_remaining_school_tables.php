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

            $hasOldTglUnique = Schema::hasIndex('tanggal_rapor', 'tanggal_rapor_tahun_ajaran_semester_unique');
            $hasNewTglUnique = Schema::hasIndex('tanggal_rapor', 'tanggal_rapor_tenant_tahun_semester_unique');
            if ($hasOldTglUnique || ! $hasNewTglUnique) {
                Schema::table('tanggal_rapor', function (Blueprint $table) use ($hasOldTglUnique, $hasNewTglUnique) {
                    if ($hasOldTglUnique) {
                        $table->dropUnique('tanggal_rapor_tahun_ajaran_semester_unique');
                    }
                    if (! $hasNewTglUnique) {
                        $table->unique(['tenant_id', 'tahun_ajaran', 'semester'], 'tanggal_rapor_tenant_tahun_semester_unique');
                    }
                });
            }
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

            $hasOldDimUnique = Schema::hasIndex('tka_dimensions_enhanced', 'tka_dimensions_enhanced_kode_dimensi_unique');
            $hasNewDimUnique = Schema::hasIndex('tka_dimensions_enhanced', 'tka_dimensions_tenant_kode_unique');
            if ($hasOldDimUnique || ! $hasNewDimUnique) {
                Schema::table('tka_dimensions_enhanced', function (Blueprint $table) use ($hasOldDimUnique, $hasNewDimUnique) {
                    if ($hasOldDimUnique) {
                        $table->dropUnique('tka_dimensions_enhanced_kode_dimensi_unique');
                    }
                    if (! $hasNewDimUnique) {
                        $table->unique(['tenant_id', 'kode_dimensi'], 'tka_dimensions_tenant_kode_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('tryout_packages') && Schema::hasColumn('tryout_packages', 'tenant_id')) {
            $hasOldPkgUnique = Schema::hasIndex('tryout_packages', 'tryout_packages_kode_paket_unique');
            $hasNewPkgUnique = Schema::hasIndex('tryout_packages', 'tryout_packages_tenant_kode_unique');
            if ($hasOldPkgUnique || ! $hasNewPkgUnique) {
                Schema::table('tryout_packages', function (Blueprint $table) use ($hasOldPkgUnique, $hasNewPkgUnique) {
                    if ($hasOldPkgUnique) {
                        $table->dropUnique('tryout_packages_kode_paket_unique');
                    }
                    if (! $hasNewPkgUnique) {
                        $table->unique(['tenant_id', 'kode_paket'], 'tryout_packages_tenant_kode_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('tryout_sessions') && Schema::hasColumn('tryout_sessions', 'tenant_id')) {
            $hasOldSessUnique = Schema::hasIndex('tryout_sessions', 'tryout_sessions_kode_sesi_unique');
            $hasNewSessUnique = Schema::hasIndex('tryout_sessions', 'tryout_sessions_tenant_kode_unique');
            if ($hasOldSessUnique || ! $hasNewSessUnique) {
                Schema::table('tryout_sessions', function (Blueprint $table) use ($hasOldSessUnique, $hasNewSessUnique) {
                    if ($hasOldSessUnique) {
                        $table->dropUnique('tryout_sessions_kode_sesi_unique');
                    }
                    if (! $hasNewSessUnique) {
                        $table->unique(['tenant_id', 'kode_sesi'], 'tryout_sessions_tenant_kode_unique');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tryout_sessions')) {
            $hasNewSessUnique = Schema::hasIndex('tryout_sessions', 'tryout_sessions_tenant_kode_unique');
            $hasOldSessUnique = Schema::hasIndex('tryout_sessions', 'tryout_sessions_kode_sesi_unique');
            if ($hasNewSessUnique || ! $hasOldSessUnique) {
                Schema::table('tryout_sessions', function (Blueprint $table) use ($hasNewSessUnique, $hasOldSessUnique) {
                    if ($hasNewSessUnique) {
                        $table->dropUnique('tryout_sessions_tenant_kode_unique');
                    }
                    if (! $hasOldSessUnique) {
                        $table->unique(['kode_sesi'], 'tryout_sessions_kode_sesi_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('tryout_packages')) {
            $hasNewPkgUnique = Schema::hasIndex('tryout_packages', 'tryout_packages_tenant_kode_unique');
            $hasOldPkgUnique = Schema::hasIndex('tryout_packages', 'tryout_packages_kode_paket_unique');
            if ($hasNewPkgUnique || ! $hasOldPkgUnique) {
                Schema::table('tryout_packages', function (Blueprint $table) use ($hasNewPkgUnique, $hasOldPkgUnique) {
                    if ($hasNewPkgUnique) {
                        $table->dropUnique('tryout_packages_tenant_kode_unique');
                    }
                    if (! $hasOldPkgUnique) {
                        $table->unique(['kode_paket'], 'tryout_packages_kode_paket_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('tka_dimensions_enhanced')) {
            $hasNewDimUnique = Schema::hasIndex('tka_dimensions_enhanced', 'tka_dimensions_tenant_kode_unique');
            $hasOldDimUnique = Schema::hasIndex('tka_dimensions_enhanced', 'tka_dimensions_enhanced_kode_dimensi_unique');
            Schema::table('tka_dimensions_enhanced', function (Blueprint $table) use ($hasNewDimUnique, $hasOldDimUnique) {
                if ($hasNewDimUnique) {
                    $table->dropUnique('tka_dimensions_tenant_kode_unique');
                }
                if (Schema::hasColumn('tka_dimensions_enhanced', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                if (! $hasOldDimUnique) {
                    $table->unique(['kode_dimensi'], 'tka_dimensions_enhanced_kode_dimensi_unique');
                }
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
            $hasNewTglUnique = Schema::hasIndex('tanggal_rapor', 'tanggal_rapor_tenant_tahun_semester_unique');
            $hasOldTglUnique = Schema::hasIndex('tanggal_rapor', 'tanggal_rapor_tahun_ajaran_semester_unique');
            Schema::table('tanggal_rapor', function (Blueprint $table) use ($hasNewTglUnique, $hasOldTglUnique) {
                if ($hasNewTglUnique) {
                    $table->dropUnique('tanggal_rapor_tenant_tahun_semester_unique');
                }
                if (Schema::hasColumn('tanggal_rapor', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                if (! $hasOldTglUnique) {
                    $table->unique(['tahun_ajaran', 'semester'], 'tanggal_rapor_tahun_ajaran_semester_unique');
                }
            });
        }
    }
};
