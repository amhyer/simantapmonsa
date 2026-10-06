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
        Schema::create('cbt_test_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('test_session_id')->constrained('cbt_test_sessions')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->integer('attempt_number')->default(1);
            $table->float('nilai_akhir');
            $table->float('nilai_maksimal');
            $table->float('persentase');
            $table->boolean('lulus');
            $table->integer('jumlah_benar')->default(0);
            $table->integer('jumlah_salah')->default(0);
            $table->integer('jumlah_tidak_dijawab')->default(0);
            $table->dateTime('waktu_mulai');
            $table->dateTime('waktu_selesai');
            $table->integer('durasi_detik');
            $table->enum('submitted_by', ['siswa', 'auto', 'guru'])->default('siswa');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->float('integrity_score')->default(100.0)->comment('Score kejujuran (0-100)');
            $table->json('integrity_violations')->nullable()->comment('Log pelanggaran kejujuran');
            $table->timestamps();

            $table->unique(['test_session_id', 'siswa_id', 'attempt_number']);
            $table->index('test_session_id');
            $table->index('siswa_id');
            $table->index('lulus');
            $table->index('nilai_akhir');
            $table->index('waktu_mulai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_test_results');
    }
};
