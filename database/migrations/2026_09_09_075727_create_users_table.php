<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nama_lengkap', 255);
            $table->string('nama_pengguna', 100)->unique();
            $table->string('kata_sandi');
            $table->enum('peran', ['admin', 'guru', 'siswa', 'ortu', 'kepsek'])->default('guru');
            $table->json('terhubung_dengan')->nullable();
            $table->string('kelas_mata_pelajaran', 255)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamp('terakhir_masuk')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index('peran');
            $table->index('aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
