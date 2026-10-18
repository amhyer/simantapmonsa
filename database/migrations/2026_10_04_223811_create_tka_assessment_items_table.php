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
        Schema::create('tka_assessment_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dimension_id')->constrained('tka_dimensions_enhanced')->onDelete('cascade');
            $table->integer('urutan')->default(0);
            $table->string('indikator');
            $table->text('pertanyaan_penilaian');
            $table->enum('tipe_jawaban', ['skala_1_4', 'skala_1_5', 'ya_tidak', 'checklist'])->default('skala_1_4');
            $table->float('bobot_item')->default(1.0);
            $table->timestamps();

            $table->index('dimension_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tka_assessment_items');
    }
};
