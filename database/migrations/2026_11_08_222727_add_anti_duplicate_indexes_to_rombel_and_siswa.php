<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Rombel anti-duplicate index
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS rombel_tenant_dapodik_unique ON rombel (tenant_id, dapodik_id, semester_id) WHERE dapodik_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS rombel_tenant_nama_unique ON rombel (tenant_id, nama_rombel, semester_id) WHERE nama_rombel IS NOT NULL');

            // Siswa anti-duplicate index
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS siswa_tenant_dapodik_unique ON siswa (tenant_id, dapodik_id, semester_id) WHERE dapodik_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS siswa_tenant_nisn_unique ON siswa (tenant_id, nisn, semester_id) WHERE nisn IS NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite partial unique index support
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS rombel_tenant_dapodik_unique ON rombel (tenant_id, dapodik_id, semester_id) WHERE dapodik_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS rombel_tenant_nama_unique ON rombel (tenant_id, nama_rombel, semester_id) WHERE nama_rombel IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS siswa_tenant_dapodik_unique ON siswa (tenant_id, dapodik_id, semester_id) WHERE dapodik_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS siswa_tenant_nisn_unique ON siswa (tenant_id, nisn, semester_id) WHERE nisn IS NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS rombel_tenant_dapodik_unique');
            DB::statement('DROP INDEX IF EXISTS rombel_tenant_nama_unique');
            DB::statement('DROP INDEX IF EXISTS siswa_tenant_dapodik_unique');
            DB::statement('DROP INDEX IF EXISTS siswa_tenant_nisn_unique');
        }
    }
};
