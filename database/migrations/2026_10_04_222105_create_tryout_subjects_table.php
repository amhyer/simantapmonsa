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
        Schema::create('tryout_subjects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('package_id')->constrained('tryout_packages')->onDelete('cascade');
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->onDelete('cascade');
            $table->integer('urutan')->default(0);
            $table->float('bobot')->default(1.0)->comment('Bobot penilaian');
            $table->integer('jumlah_soal')->default(0);
            $table->integer('durasi_menit');
            $table->float('passing_grade_mapel')->default(60.0);
            $table->timestamps();

            $table->index('package_id');
            $table->index('mata_pelajaran_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tryout_subjects');
    }
};
