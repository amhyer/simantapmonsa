<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orang_tua_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->enum('tipe', ['ayah', 'ibu', 'wali']);
            $table->string('nama', 255);
            $table->string('nik', 20)->nullable();
            $table->integer('tahun_lahir')->nullable();
            $table->enum('pendidikan', ['Tidak Sekolah', 'SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'S1', 'S2', 'S3'])->nullable();
            $table->string('pekerjaan', 100)->nullable();
            $table->enum('penghasilan', ['< 1jt', '1-2jt', '2-5jt', '5-10jt', '> 10jt'])->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('alamat')->nullable();
            $table->boolean('status_hidup')->default(true);
            $table->timestamps();
            $table->unique(['siswa_id', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orang_tua_siswa');
    }
};
