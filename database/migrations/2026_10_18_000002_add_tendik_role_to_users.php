<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL menamai check constraint enum Laravel sebagai users_peran_check.
        // Migrasi awal juga memuat tendik untuk instalasi baru; statement ini
        // memperbarui constraint pada database yang sudah pernah dimigrasikan.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_peran_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_peran_check CHECK (peran IN ('admin', 'guru', 'siswa', 'ortu', 'kepsek', 'tendik'))");
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY peran ENUM('admin', 'guru', 'siswa', 'ortu', 'kepsek', 'tendik') NOT NULL DEFAULT 'guru'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_peran_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_peran_check CHECK (peran IN ('admin', 'guru', 'siswa', 'ortu', 'kepsek'))");
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY peran ENUM('admin', 'guru', 'siswa', 'ortu', 'kepsek') NOT NULL DEFAULT 'guru'");
        }
    }
};
