<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reset sandi mandiri membutuhkan kanal verifikasi. Akun SIMANTAP memakai
 * `nama_pengguna` (bukan email) untuk login, jadi kolom email ditambahkan
 * sebagai opsional: pengguna yang belum punya email tetap dapat meminta admin
 * mereset sandi. Email tidak pernah dipakai untuk login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('nama_pengguna');
        });

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }
};
