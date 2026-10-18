<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tryout_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->constrained('tryout_categories')->onDelete('cascade');
            $table->string('kode_paket')->unique();
            $table->string('nama_paket');
            $table->text('deskripsi')->nullable();
            $table->enum('jenjang', ['SD', 'SMP', 'SMA', 'SMK', 'UMUM'])->default('SMA');
            $table->year('tahun_paket');
            $table->integer('jumlah_mapel')->default(0);
            $table->integer('durasi_total_menit');
            $table->float('passing_grade')->default(60.0);
            $table->json('standar_nilai')->nullable()->comment('Standar nilai per mapel');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('jenjang');
            $table->index('tahun_paket');
            $table->index('aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tryout_packages');
    }
};
