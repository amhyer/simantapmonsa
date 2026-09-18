<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materi', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->date('tanggal');
            $table->string('judul', 255);
            $table->string('mata_pelajaran', 100);
            $table->string('kelas', 50);
            $table->text('tujuan_pembelajaran');
            $table->text('materi_pokok');
            $table->text('kegiatan')->nullable();
            $table->string('tautan_sumber')->nullable();
            $table->json('pm')->nullable();
            $table->json('dimensi')->nullable();
            $table->json('pengalaman')->nullable();
            $table->boolean('sematkan')->default(false);
            $table->json('rekaman')->nullable();
            $table->timestamps();
            
            $table->index(['guru_id', 'kelas']);
            $table->index('judul');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materi');
    }
};
