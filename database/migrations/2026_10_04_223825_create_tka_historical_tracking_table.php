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
        Schema::create('tka_historical_tracking', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->foreignId('dimension_id')->constrained('tka_dimensions_enhanced')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->float('skor');
            $table->float('perubahan_skor')->nullable()->comment('Perubahan dari semester sebelumnya');
            $table->enum('trend', ['meningkat', 'stabil', 'menurun'])->nullable();
            $table->timestamps();

            $table->index('siswa_id');
            $table->index('dimension_id');
            $table->index('semester_id');
            $table->index('trend');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tka_historical_tracking');
    }
};
