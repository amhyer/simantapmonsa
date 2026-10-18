<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tka_early_warnings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->foreignId('dimension_id')->constrained('tka_dimensions_enhanced')->onDelete('cascade');
            $table->enum('tingkat_peringatan', ['rendah', 'sedang', 'tinggi', 'kritis']);
            $table->text('alasan_peringatan');
            $table->text('rekomendasi_tindakan');
            $table->enum('status', ['pending', 'sedang_diproses', 'selesai'])->default('pending');
            $table->foreignId('ditangani_oleh')->nullable()->constrained('users')->onDelete('set null')->comment('Guru yang menangani');
            $table->datetime('tanggal_selesai')->nullable();
            $table->timestamps();

            $table->index('siswa_id');
            $table->index('semester_id');
            $table->index('dimension_id');
            $table->index('tingkat_peringatan');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tka_early_warnings');
    }
};
