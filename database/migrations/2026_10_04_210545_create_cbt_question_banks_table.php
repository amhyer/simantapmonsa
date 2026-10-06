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
        Schema::create('cbt_question_banks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('kode_bank', 50)->unique();
            $table->string('nama_bank', 255);
            $table->text('deskripsi')->nullable();
            $table->enum('tingkat_kesulitan', ['mudah', 'sedang', 'sulit'])->default('sedang');
            $table->string('kategori_soal', 100)->nullable()->comment('Kategori: materi, hots, dsb');
            $table->integer('jumlah_soal')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mata_pelajaran_id', 'semester_id']);
            $table->index('guru_id');
            $table->index('tingkat_kesulitan');
            $table->index('aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_question_banks');
    }
};
