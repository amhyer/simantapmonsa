<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kebiasaan', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->date('tanggal');
            $table->integer('bangun_pagi')->unsigned()->nullable();
            $table->integer('beribadah')->unsigned()->nullable();
            $table->integer('berolahraga')->unsigned()->nullable();
            $table->integer('makan_sehat')->unsigned()->nullable();
            $table->integer('gemar_belajar')->unsigned()->nullable();
            $table->integer('bermasyarakat')->unsigned()->nullable();
            $table->integer('tidur_cepat')->unsigned()->nullable();
            $table->text('catatan_orang_tua')->nullable();
            $table->string('diisi_oleh', 255)->nullable();
            $table->timestamp('waktu_simpan')->nullable();
            $table->json('rekaman')->nullable();
            $table->timestamps();
            
            $table->unique(['siswa_id', 'tanggal']);
            $table->index(['siswa_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebiasaan');
    }
};
