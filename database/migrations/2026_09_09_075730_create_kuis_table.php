<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kuis', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->date('tanggal');
            $table->string('judul', 255);
            $table->string('mata_pelajaran', 100);
            $table->string('kelas', 50);
            $table->enum('mode', ['harian', 'tka'])->default('harian');
            $table->enum('sumber', ['lokal', 'luar'])->default('lokal');
            $table->string('tautan_soal')->nullable();
            $table->foreignId('materi_id')->nullable()->constrained('materi')->onDelete('set null');
            $table->integer('kkm')->default(70);
            $table->integer('batas_waktu')->default(0);
            $table->boolean('aktif')->default(true);
            $table->integer('jumlah_soal')->default(0);
            $table->text('stimulus')->nullable();
            $table->enum('jenis', ['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS'])->default('Ulangan Harian');
            $table->text('petunjuk')->nullable();
            $table->enum('cara_nilai', ['siswa', 'guru'])->default('siswa');
            $table->json('soal')->nullable();
            $table->json('rekaman')->nullable();
            $table->timestamps();
            
            $table->index(['guru_id', 'kelas']);
            $table->index('aktif');
            $table->index('mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kuis');
    }
};
