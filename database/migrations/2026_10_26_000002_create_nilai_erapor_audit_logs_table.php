<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit nilai e-Rapor dengan rantai hash.
 *
 * Setiap perubahan baris `nilai_erapor` dicatat sebagai snapshot dan diikat ke
 * catatan sebelumnya lewat `prev_hash` -> `hash`. Jika satu snapshot diubah
 * langsung di database, verifikasi akan menandai rantai itu rusak meskipun
 * kolom nilai yang terlihat tetap sama.
 *
 * `nilai_erapor_id` sengaja TIDAK memakai foreign key: audit harus tetap utuh
 * dan dapat diverifikasi setelah baris nilai dihapus. Tabel ini dicadangkan
 * bersama data akademik lain, sehingga riwayat tidak hilang saat wipe/restore.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nilai_erapor_audit_logs')) {
            return;
        }

        Schema::create('nilai_erapor_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nilai_erapor_id')->nullable()->index();
            $table->unsignedBigInteger('siswa_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->string('aksi', 30);
            $table->string('status_lama', 30)->nullable();
            $table->string('status_baru', 30)->nullable();
            $table->json('snapshot')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['nilai_erapor_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_erapor_audit_logs');
    }
};
