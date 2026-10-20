<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan skema Tryout (Fase 1, T-01/T-05 + drift skema form admin).
 *
 * - tryout_results.waktu_selesai: NOT NULL -> nullable (start() menyisipkan null).
 * - tryout_sessions.semester_id: NOT NULL -> nullable (validasi admin nullable).
 * - tryout_sessions.durasi_menit & instruksi: kolom yang dipakai form/controller
 *   tetapi belum pernah dibuat.
 *
 * Migrasi lama TIDAK diedit (aturan Fase 1.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tryout_results', function (Blueprint $table) {
            $table->dateTime('waktu_selesai')->nullable()->change();
        });

        Schema::table('tryout_sessions', function (Blueprint $table) {
            $table->integer('durasi_menit')->default(120)->after('tanggal_selesai');
            $table->text('instruksi')->nullable()->after('durasi_menit');
        });

        Schema::table('tryout_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('semester_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tryout_sessions', function (Blueprint $table) {
            $table->dropColumn(['durasi_menit', 'instruksi']);
        });

        DB::table('tryout_results')
            ->whereNull('waktu_selesai')
            ->update(['waktu_selesai' => DB::raw('waktu_mulai')]);

        Schema::table('tryout_results', function (Blueprint $table) {
            $table->dateTime('waktu_selesai')->nullable(false)->change();
        });
    }
};
