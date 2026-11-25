<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exam_banks')) {
            if (! Schema::hasColumn('exam_banks', 'tenant_id')) {
                Schema::table('exam_banks', function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                });
            }

            $hasOldBankUnique = Schema::hasIndex('exam_banks', 'exam_banks_kode_unique');
            $hasNewBankUnique = Schema::hasIndex('exam_banks', 'exam_banks_tenant_kode_unique');
            if ($hasOldBankUnique || ! $hasNewBankUnique) {
                Schema::table('exam_banks', function (Blueprint $table) use ($hasOldBankUnique, $hasNewBankUnique) {
                    if ($hasOldBankUnique) {
                        $table->dropUnique('exam_banks_kode_unique');
                    }
                    if (! $hasNewBankUnique) {
                        $table->unique(['tenant_id', 'kode'], 'exam_banks_tenant_kode_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('exam_sessions')) {
            if (! Schema::hasColumn('exam_sessions', 'tenant_id')) {
                Schema::table('exam_sessions', function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                });
            }

            $hasOldSessionUnique = Schema::hasIndex('exam_sessions', 'exam_sessions_kode_unique');
            $hasNewSessionUnique = Schema::hasIndex('exam_sessions', 'exam_sessions_tenant_kode_unique');
            if ($hasOldSessionUnique || ! $hasNewSessionUnique) {
                Schema::table('exam_sessions', function (Blueprint $table) use ($hasOldSessionUnique, $hasNewSessionUnique) {
                    if ($hasOldSessionUnique) {
                        $table->dropUnique('exam_sessions_kode_unique');
                    }
                    if (! $hasNewSessionUnique) {
                        $table->unique(['tenant_id', 'kode'], 'exam_sessions_tenant_kode_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('dapodik_data_cache')) {
            if (! Schema::hasColumn('dapodik_data_cache', 'tenant_id')) {
                Schema::table('dapodik_data_cache', function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                });
            }

            $hasOldCacheUnique = Schema::hasIndex('dapodik_data_cache', 'dapodik_data_cache_dapodik_id_unique');
            $hasNewCacheUnique = Schema::hasIndex('dapodik_data_cache', 'dapodik_data_cache_tenant_dapodik_id_unique');
            if ($hasOldCacheUnique || ! $hasNewCacheUnique) {
                Schema::table('dapodik_data_cache', function (Blueprint $table) use ($hasOldCacheUnique, $hasNewCacheUnique) {
                    if ($hasOldCacheUnique) {
                        $table->dropUnique('dapodik_data_cache_dapodik_id_unique');
                    }
                    if (! $hasNewCacheUnique) {
                        $table->unique(['tenant_id', 'dapodik_id'], 'dapodik_data_cache_tenant_dapodik_id_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('dapodik_import_logs')) {
            Schema::table('dapodik_import_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('dapodik_import_logs', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                    $table->index('tenant_id', 'dapodik_import_logs_tenant_id_index');
                }
                if (! Schema::hasColumn('dapodik_import_logs', 'tenant_owned')) {
                    $table->boolean('tenant_owned')->default(false)->after('tenant_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dapodik_import_logs')) {
            Schema::table('dapodik_import_logs', function (Blueprint $table) {
                if (Schema::hasColumn('dapodik_import_logs', 'tenant_owned')) {
                    $table->dropColumn('tenant_owned');
                }
                if (Schema::hasColumn('dapodik_import_logs', 'tenant_id')) {
                    if (Schema::hasIndex('dapodik_import_logs', 'dapodik_import_logs_tenant_id_index')) {
                        $table->dropIndex('dapodik_import_logs_tenant_id_index');
                    }
                    $table->dropConstrainedForeignId('tenant_id');
                }
            });
        }

        if (Schema::hasTable('dapodik_data_cache')) {
            $hasNewCacheUnique = Schema::hasIndex('dapodik_data_cache', 'dapodik_data_cache_tenant_dapodik_id_unique');
            $hasOldCacheUnique = Schema::hasIndex('dapodik_data_cache', 'dapodik_data_cache_dapodik_id_unique');
            Schema::table('dapodik_data_cache', function (Blueprint $table) use ($hasNewCacheUnique, $hasOldCacheUnique) {
                if ($hasNewCacheUnique) {
                    $table->dropUnique('dapodik_data_cache_tenant_dapodik_id_unique');
                }
                if (Schema::hasColumn('dapodik_data_cache', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                if (! $hasOldCacheUnique) {
                    $table->unique(['dapodik_id'], 'dapodik_data_cache_dapodik_id_unique');
                }
            });
        }

        if (Schema::hasTable('exam_sessions')) {
            $hasNewSessionUnique = Schema::hasIndex('exam_sessions', 'exam_sessions_tenant_kode_unique');
            $hasOldSessionUnique = Schema::hasIndex('exam_sessions', 'exam_sessions_kode_unique');
            if ($hasNewSessionUnique || ! $hasOldSessionUnique) {
                Schema::table('exam_sessions', function (Blueprint $table) use ($hasNewSessionUnique, $hasOldSessionUnique) {
                    if ($hasNewSessionUnique) {
                        $table->dropUnique('exam_sessions_tenant_kode_unique');
                    }
                    if (! $hasOldSessionUnique) {
                        $table->unique(['kode'], 'exam_sessions_kode_unique');
                    }
                });
            }
        }

        if (Schema::hasTable('exam_banks')) {
            $hasNewBankUnique = Schema::hasIndex('exam_banks', 'exam_banks_tenant_kode_unique');
            $hasOldBankUnique = Schema::hasIndex('exam_banks', 'exam_banks_kode_unique');
            if ($hasNewBankUnique || ! $hasOldBankUnique) {
                Schema::table('exam_banks', function (Blueprint $table) use ($hasNewBankUnique, $hasOldBankUnique) {
                    if ($hasNewBankUnique) {
                        $table->dropUnique('exam_banks_tenant_kode_unique');
                    }
                    if (! $hasOldBankUnique) {
                        $table->unique(['kode'], 'exam_banks_kode_unique');
                    }
                });
            }
        }
    }
};
