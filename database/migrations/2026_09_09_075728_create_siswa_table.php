<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->string('nis', 50)->unique();
            $table->string('nama_peserta_didik', 255);
            $table->string('kelas', 50);
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('nama_orang_tua', 255)->nullable();
            $table->boolean('aktif')->default(true);
            $table->json('rekaman')->nullable();
            $table->timestamps();
            
            $table->index(['guru_id', 'kelas']);
            $table->index('nis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
