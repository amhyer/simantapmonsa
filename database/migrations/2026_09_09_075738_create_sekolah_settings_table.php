<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sekolah_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_sekolah', 255);
            $table->string('alamat', 500)->nullable();
            $table->string('kota', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->string('telepon', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('kepala_sekolah', 255)->nullable();
            $table->string('npsn', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('jenjang', 50)->nullable();
            $table->json('pengaturan')->nullable();
            $table->timestamps();
            
            $table->unique('guru_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sekolah_settings');
    }
};
