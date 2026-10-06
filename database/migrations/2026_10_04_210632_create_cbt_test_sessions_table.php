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
        Schema::create('cbt_test_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kode_sesi', 20)->unique();
            $table->string('nama_sesi', 255);
            $table->foreignId('question_bank_id')->constrained('cbt_question_banks')->onDelete('cascade');
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('kelas', 50)->nullable()->comment('Kosong = multi-kelas');
            $table->foreignId('rombel_id')->nullable()->constrained('rombel')->onDelete('set null');
            $table->dateTime('tanggal_mulai');
            $table->dateTime('tanggal_selesai');
            $table->integer('durasi_menit');
            $table->integer('jumlah_soal_tampil')->comment('Jumlah soal yang ditampilkan ke siswa');
            $table->boolean('acak_soal')->default(true);
            $table->boolean('acak_opsi')->default(true);
            $table->boolean('tampilkan_nilai')->default(false);
            $table->boolean('boleh_ulang')->default(false);
            $table->integer('maksimal_percobaan')->default(1);
            $table->float('passing_grade')->default(60.0);
            $table->enum('status', ['draft', 'published', 'ongoing', 'completed', 'archived'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index('question_bank_id');
            $table->index(['mata_pelajaran_id', 'semester_id']);
            $table->index('guru_id');
            $table->index('status');
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_test_sessions');
    }
};
