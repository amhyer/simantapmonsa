<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->enum('jenis', ['materi', 'kuis', 'nilai', 'kehadiran', 'catatan', 'dimensi', 'kebiasaan', 'pengaturan', 'login', 'tambah', 'ubah', 'hapus', 'lihat']);
            $table->string('judul', 255);
            $table->text('deskripsi')->nullable();
            $table->string('tabel_terkait', 100)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
            $table->timestamps();
            
            $table->index(['guru_id', 'jenis']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas');
    }
};
