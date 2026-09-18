<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('kode', 20)->nullable();
            $table->enum('jenjang', ['SD', 'MI', 'SMP', 'MTs', 'SMA', 'MA', 'SMK', 'SLB'])->default('SD');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['nama', 'jenjang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran');
    }
};
