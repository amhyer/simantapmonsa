<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanggal_rapor', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran', 20);
            $table->string('semester', 10);
            $table->date('tanggal')->nullable();
            $table->string('tempat', 255)->nullable();
            $table->string('keterangan', 500)->nullable();
            $table->timestamps();
            $table->unique(['tahun_ajaran', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanggal_rapor');
    }
};
