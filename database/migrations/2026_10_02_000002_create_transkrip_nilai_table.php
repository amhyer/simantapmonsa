<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transkrip_nilai', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            $table->string('nama_mapel', 255);
            $table->decimal('nilai_akhir', 5, 2);
            $table->char('predikat', 1)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['siswa_id', 'mata_pelajaran_id']);
            $table->index('siswa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transkrip_nilai');
    }
};
