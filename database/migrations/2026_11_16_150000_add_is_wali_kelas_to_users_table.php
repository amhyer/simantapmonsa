<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda eksplisit wali kelas pada akun guru (`users.is_wali_kelas`).
 *
 * Sengaja nullable tanpa backfill tebakan:
 *  - true  : ditandai eksplisit sebagai wali kelas
 *  - false : ditandai eksplisit bukan wali kelas
 *  - null  : dievaluasi otomatis dari `rombel.guru_id` (dan fallback siswa tanpa rombel)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_wali_kelas')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_wali_kelas')->nullable()->after('kelas_mata_pelajaran');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_wali_kelas')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_wali_kelas');
            });
        }
    }
};
