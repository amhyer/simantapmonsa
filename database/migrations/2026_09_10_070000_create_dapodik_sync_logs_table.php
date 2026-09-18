<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dapodik_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('sekolah_npsn', 20)->nullable();
            $table->string('nama_sekolah')->nullable();
            $table->enum('tipe', ['siswa', 'gtk', 'rombel', 'mapel', 'nilai', 'semua', 'full-sync']);
            $table->string('tahun_ajaran', 20)->nullable();
            $table->string('semester', 10)->nullable();
            $table->integer('total_data')->default(0);
            $table->integer('berhasil')->default(0);
            $table->integer('gagal')->default(0);
            $table->integer('diperbarui')->default(0);
            $table->integer('dilewati')->default(0);
            $table->json('errors')->nullable();
            $table->json('summary')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();
        });

        Schema::create('dapodik_data_cache', function (Blueprint $table) {
            $table->id();
            $table->string('dapodik_id', 100)->unique();
            $table->enum('tipe', ['siswa', 'gtk', 'rombel', 'mapel', 'sekolah']);
            $table->json('data');
            $table->string('checksum', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dapodik_data_cache');
        Schema::dropIfExists('dapodik_sync_logs');
    }
};
