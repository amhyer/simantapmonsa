<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email')) {
            return;
        }

        // Normalisasi data historis yang belum berhuruf kecil penuh (dengan aman apabila ada konflik).
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement("
                UPDATE users
                SET email = LOWER(TRIM(email))
                WHERE email IS NOT NULL
                  AND email != LOWER(TRIM(email))
                  AND NOT EXISTS (
                      SELECT 1 FROM users u2
                      WHERE u2.id != users.id
                        AND LOWER(TRIM(u2.email)) = LOWER(TRIM(users.email))
                  )
            ");
        }

        // Tambahkan functional index LOWER(email) agar pencarian lupa-sandi
        // case-insensitive tetap menggunakan index scan di PostgreSQL & SQLite.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS users_email_lower_idx ON users ((LOWER(email))) WHERE email IS NOT NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement('CREATE INDEX IF NOT EXISTS users_email_lower_idx ON users (LOWER(email)) WHERE email IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS users_email_lower_idx');
        }
    }
};
