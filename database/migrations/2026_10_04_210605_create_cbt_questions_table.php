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
        Schema::create('cbt_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('question_bank_id')->constrained('cbt_question_banks')->onDelete('cascade');
            $table->integer('urutan')->default(0);
            $table->enum('tipe_soal', ['pilihan_ganda', 'isian_singkat', 'benar_salah', 'menjodohkan', 'essay'])->default('pilihan_ganda');
            $table->text('pertanyaan');
            $table->enum('media_type', ['text', 'image', 'audio', 'video'])->default('text');
            $table->string('media_url', 500)->nullable()->comment('URL gambar/audio/video');
            $table->float('poin')->default(1.0);
            $table->integer('waktu_jawab_detik')->default(0)->comment('0 = unlimited');
            $table->text('pembahasan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('question_bank_id');
            $table->index('tipe_soal');
            $table->index('aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_questions');
    }
};
