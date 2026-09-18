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
        Schema::create('dapodik_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('sekolah_id')->nullable();
            $table->string('nama_sekolah')->nullable();
            $table->integer('total_siswa')->default(0);
            $table->integer('berhasil')->default(0);
            $table->integer('gagal')->default(0);
            $table->integer('diperbarui')->default(0);
            $table->json('errors')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dapodik_import_logs');
    }
};
