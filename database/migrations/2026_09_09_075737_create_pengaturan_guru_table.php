<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->string('mata_pelajaran', 100)->nullable();
            $table->string('kelas', 50)->nullable();
            $table->integer('kkm')->default(70);
            $table->string('semester', 10)->nullable();
            $table->string('tahun_pelajaran', 20)->nullable();
            $table->string('fase', 20)->nullable();
            $table->timestamp('pembaruan')->nullable();
            $table->json('pengaturan')->nullable();
            $table->json('sistem')->nullable();
            $table->timestamps();
            
            $table->unique('guru_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_guru');
    }
};
