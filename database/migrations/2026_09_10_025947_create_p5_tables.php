<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p5_projek', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255);
            $table->string('tema', 100);
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('kelas', 50);
            $table->foreignId('koordinator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('p5_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projek_id')->constrained('p5_projek')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->integer('nilai')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
            $table->unique(['projek_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p5_siswa');
        Schema::dropIfExists('p5_projek');
    }
};
