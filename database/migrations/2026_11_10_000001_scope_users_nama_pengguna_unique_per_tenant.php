<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nama_pengguna']);
            $table->unique(['tenant_id', 'nama_pengguna'], 'users_tenant_nama_pengguna_unique');
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            // Pengguna global / super_admin (tanpa tenant_id) tetap unik antar-sesama akun global
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_single_school_nama_pengguna_unique ON users (nama_pengguna) WHERE tenant_id IS NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS users_single_school_nama_pengguna_unique');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_nama_pengguna_unique');
            $table->unique('nama_pengguna');
        });
    }
};
