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
        Schema::create('tryout_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tryout_session_id')->constrained('tryout_sessions')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->integer('attempt_number')->default(1);
            $table->float('nilai_total');
            $table->float('nilai_maksimal');
            $table->float('persentase');
            $table->integer('peringkat')->nullable()->comment('Peringkat dalam sesi');
            $table->boolean('lulus');
            $table->json('prediksi_peminatan')->nullable()->comment('Prediksi jurusan/minat');
            $table->json('detail_nilai_mapel')->comment('Nilai per mata pelajaran');
            $table->json('analisis_kelemahan')->nullable()->comment('Mapel yang lemah');
            $table->text('rekomendasi_pembelajaran')->nullable();
            $table->datetime('waktu_mulai');
            $table->datetime('waktu_selesai');
            $table->integer('durasi_total_detik');
            $table->timestamps();

            $table->unique(['tryout_session_id', 'siswa_id', 'attempt_number'], 'unique_siswa_session_attempt');
            $table->index('tryout_session_id');
            $table->index('siswa_id');
            $table->index('lulus');
            $table->index('peringkat');
            $table->index('waktu_mulai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tryout_results');
    }
};
