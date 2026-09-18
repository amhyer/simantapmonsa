<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_mapel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->foreignId('guru_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('mata_pelajaran', 100);
            $table->string('kelas', 50);
            $table->enum('semester', ['Ganjil', 'Genap']);
            $table->string('tahun_ajaran', 20);
            $table->decimal('nilai_tugas', 5, 2)->default(0);
            $table->decimal('nilai_uh', 5, 2)->default(0);
            $table->decimal('nilai_pts', 5, 2)->default(0);
            $table->decimal('nilai_pas', 5, 2)->default(0);
            $table->decimal('nilai_praktik', 5, 2)->default(0);
            $table->decimal('nilai_akhir', 5, 2)->default(0);
            $table->char('predikat', 1)->nullable();
            $table->text('deskripsi_capaian')->nullable();
            $table->text('capaian_tertinggi')->nullable();
            $table->text('capaian_terendah')->nullable();
            $table->timestamps();
            $table->unique(['siswa_id', 'mata_pelajaran', 'semester', 'tahun_ajaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_mapel');
    }
};
