<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tema_kokurikuler', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255);
            $table->string('deskripsi', 500)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kegiatan_kokurikuler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tema_id')->constrained('tema_kokurikuler')->onDelete('cascade');
            $table->string('nama', 255);
            $table->string('kelas', 50)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('deskripsi', 500)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kelompok_kokurikuler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan_kokurikuler')->onDelete('cascade');
            $table->string('nama', 255);
            $table->string('keterangan', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('anggota_kelompok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_kokurikuler_id')->constrained('kelompok_kokurikuler')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['kelompok_kokurikuler_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggota_kelompok');
        Schema::dropIfExists('kelompok_kokurikuler');
        Schema::dropIfExists('kegiatan_kokurikuler');
        Schema::dropIfExists('tema_kokurikuler');
    }
};
