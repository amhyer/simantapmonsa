<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capaian_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('mata_pelajaran', 100);
            $table->char('fase', 2);
            $table->string('elemen', 100);
            $table->text('deskripsi');
            $table->string('kode_cp', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capaian_pembelajaran');
    }
};
