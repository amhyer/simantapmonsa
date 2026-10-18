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
        Schema::create('tka_student_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->date('tanggal_penilaian');
            $table->float('total_score');
            $table->float('max_score');
            $table->float('persentase');
            $table->enum('status_kesiapan', ['belum_siap', 'perlu_bimbingan', 'siap', 'sangat_siap']);
            $table->json('skor_per_dimensi')->comment('Score per dimension');
            $table->timestamps();

            $table->unique(['siswa_id', 'semester_id'], 'unique_siswa_semester');
            $table->index('siswa_id');
            $table->index('semester_id');
            $table->index('guru_id');
            $table->index('status_kesiapan');
            $table->index('tanggal_penilaian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tka_student_assessments');
    }
};
