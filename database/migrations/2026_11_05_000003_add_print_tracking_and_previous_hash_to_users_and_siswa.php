<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'qr_previous_token_hash')) {
                $table->string('qr_previous_token_hash', 64)->nullable()->index();
            }
            if (!Schema::hasColumn('users', 'printed_at')) {
                $table->timestamp('printed_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'printed_version')) {
                $table->unsignedInteger('printed_version')->default(0);
            }
        });

        Schema::table('siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siswa', 'qr_previous_token_hash')) {
                $table->string('qr_previous_token_hash', 64)->nullable()->index();
            }
            if (!Schema::hasColumn('siswa', 'printed_at')) {
                $table->timestamp('printed_at')->nullable();
            }
            if (!Schema::hasColumn('siswa', 'printed_version')) {
                $table->unsignedInteger('printed_version')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['qr_previous_token_hash', 'printed_at', 'printed_version']);
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn(['qr_previous_token_hash', 'printed_at', 'printed_version']);
        });
    }
};
