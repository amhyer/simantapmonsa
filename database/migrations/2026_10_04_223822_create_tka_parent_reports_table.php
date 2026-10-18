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
        Schema::create('tka_parent_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('assessment_id')->constrained('tka_student_assessments')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->foreignId('ortu_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('dibaca_oleh_ortu')->default(false);
            $table->datetime('tanggal_dibaca')->nullable();
            $table->text('rekomendasi_rumah')->nullable();
            $table->json('aktivitas_suggested')->nullable()->comment('Aktivitas yang disarankan di rumah');
            $table->timestamps();

            $table->unique('assessment_id', 'unique_assessment');
            $table->index('siswa_id');
            $table->index('ortu_user_id');
            $table->index('dibaca_oleh_ortu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tka_parent_reports');
    }
};
