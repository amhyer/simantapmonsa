<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->string('nama_prestasi', 255);
            $table->string('tingkat', 50);
            $table->string('peringkat', 50)->nullable();
            $table->date('tanggal')->nullable();
            $table->string('penyelenggara', 255)->nullable();
            $table->string('sertifikat', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestasi');
    }
};
