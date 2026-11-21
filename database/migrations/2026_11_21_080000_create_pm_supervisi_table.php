<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_supervisi', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->index();
            $table->unsignedBigInteger('kepsek_id')->nullable()->index();
            $table->string('tahun_ajaran', 20)->index();
            $table->unsignedTinyInteger('semester')->default(1);
            $table->string('status', 30)->default('menunggu'); // menunggu, direview, disetujui, revisi
            $table->unsignedTinyInteger('skor_kelengkapan')->default(0); // 0..100
            $table->text('catatan_kepsek')->nullable();
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'guru_id', 'tahun_ajaran', 'semester'], 'pm_supervisi_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_supervisi');
    }
};
