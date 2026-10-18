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
        Schema::create('tryout_student_answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tryout_session_id')->constrained('tryout_sessions')->onDelete('cascade');
            $table->foreignId('tryout_subject_id')->constrained('tryout_subjects')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('tryout_questions')->onDelete('cascade');
            $table->text('jawaban')->nullable();
            $table->boolean('adalah_benar')->nullable()->comment('NULL = belum dinilai');
            $table->float('poin_diperoleh')->default(0.0);
            $table->datetime('waktu_mulai_jawab')->nullable();
            $table->datetime('waktu_selesai_jawab')->nullable();
            $table->integer('durasi_detik')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->timestamps();

            $table->unique(['tryout_session_id', 'siswa_id', 'question_id', 'attempt_number'], 'unique_siswa_question_attempt');
            $table->index(['tryout_session_id', 'siswa_id']);
            $table->index('tryout_subject_id');
            $table->index('question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tryout_student_answers');
    }
};
