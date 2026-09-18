<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->string('nama_siswa', 255);
            $table->date('tanggal');
            $table->enum('jenis', ['apresiasi', 'perhatian', 'umum'])->default('umum');
            $table->text('catatan');
            $table->json('rekaman')->nullable();
            $table->timestamps();
            
            $table->index(['siswa_id', 'jenis']);
            $table->index('guru_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan');
    }
};
