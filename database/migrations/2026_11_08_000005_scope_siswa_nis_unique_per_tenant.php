<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('siswa')) {
            return;
        }

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropUnique(['nis']);
            $table->unique(['tenant_id', 'nis'], 'siswa_tenant_nis_unique');
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS siswa_single_school_nis_unique ON siswa (nis) WHERE tenant_id IS NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('siswa')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS siswa_single_school_nis_unique');
        }

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropUnique('siswa_tenant_nis_unique');
            $table->unique('nis');
        });
    }
};
