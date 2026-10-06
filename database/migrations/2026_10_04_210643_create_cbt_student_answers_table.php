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
        Schema::create('cbt_student_answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('test_session_id')->constrained('cbt_test_sessions')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('cbt_questions')->onDelete('cascade');
            $table->text('jawaban')->nullable()->comment('Untuk pilihan ganda: huruf, untuk essay: text');
            $table->boolean('adalah_benar')->nullable()->comment('NULL = belum dinilai (essay)');
            $table->float('poin_diperoleh')->default(0.0);
            $table->dateTime('waktu_mulai_jawab')->nullable();
            $table->dateTime('waktu_selesai_jawab')->nullable();
            $table->integer('durasi_detik')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->boolean('ragu_ragu')->default(false);
            $table->timestamps();

            $table->unique(['test_session_id', 'siswa_id', 'question_id', 'attempt_number']);
            $table->index(['test_session_id', 'siswa_id']);
            $table->index('question_id');
            $table->index('adalah_benar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_student_answers');
    }
};
