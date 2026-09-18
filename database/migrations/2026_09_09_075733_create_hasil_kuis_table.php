<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_kuis', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('kuis_id')->constrained('kuis')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->string('judul_kuis', 255);
            $table->string('nama_siswa', 255);
            $table->timestamp('waktu');
            $table->integer('benar')->default(0);
            $table->integer('total')->default(0);
            $table->integer('skor')->default(0);
            $table->boolean('tuntas')->default(false);
            $table->integer('durasi')->default(0);
            $table->string('sumber_soal', 50)->nullable();
            $table->string('diisi_oleh', 100)->nullable();
            $table->text('catatan_siswa')->nullable();
            $table->json('jawaban')->nullable();
            $table->json('rekaman')->nullable();
            $table->timestamps();
            
            $table->unique(['kuis_id', 'siswa_id']);
            $table->index(['siswa_id', 'kuis_id']);
            $table->index('guru_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_kuis');
    }
};
