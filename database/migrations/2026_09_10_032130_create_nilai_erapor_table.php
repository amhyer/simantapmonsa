<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_erapor', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('mata_pelajaran', 100);
            $table->string('kelas', 50);
            $table->enum('semester', ['Ganjil', 'Genap']);
            $table->string('tahun_ajaran', 20);
            $table->char('fase', 2)->nullable();
            $table->decimal('nilai_formatif', 5, 2)->default(0);
            $table->decimal('nilai_sumatif', 5, 2)->default(0);
            $table->decimal('nilai_sumatif_akhir', 5, 2)->default(0);
            $table->decimal('nilai_akhir', 5, 2)->default(0);
            $table->char('predikat', 1)->nullable();
            $table->text('deskripsi_capaian')->nullable();
            $table->string('capaian_tertinggi', 255)->nullable();
            $table->string('capaian_terendah', 255)->nullable();
            $table->boolean('terkirim_erapor')->default(false);
            $table->timestamp('erapor_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['siswa_id', 'mata_pelajaran', 'semester', 'tahun_ajaran'], 'nilai_erapor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_erapor');
    }
};
