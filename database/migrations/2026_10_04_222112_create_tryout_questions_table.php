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
        Schema::create('tryout_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tryout_subject_id')->constrained('tryout_subjects')->onDelete('cascade');
            $table->integer('urutan')->default(0);
            $table->enum('tipe_soal', ['pilihan_ganda', 'isian_singkat', 'benar_salah'])->default('pilihan_ganda');
            $table->text('pertanyaan');
            $table->enum('media_type', ['text', 'image', 'audio', 'video'])->default('text');
            $table->string('media_url')->nullable();
            $table->float('poin')->default(1.0);
            $table->enum('tingkat_kesulitan', ['mudah', 'sedang', 'sulit'])->default('sedang');
            $table->text('pembahasan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tryout_subject_id');
            $table->index('tingkat_kesulitan');
            $table->index('aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tryout_questions');
    }
};
