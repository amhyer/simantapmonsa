<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dimensi', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->string('nama_siswa', 255);
            $table->integer('no_dimensi');
            $table->string('dimensi', 100);
            $table->integer('skor')->unsigned();
            $table->string('predikat', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->json('rekaman')->nullable();
            $table->timestamps();
            
            $table->unique(['siswa_id', 'no_dimensi']);
            $table->index(['siswa_id', 'no_dimensi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dimensi');
    }
};
