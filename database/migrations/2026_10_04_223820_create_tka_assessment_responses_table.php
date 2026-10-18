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
        Schema::create('tka_assessment_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('assessment_id')->constrained('tka_student_assessments')->onDelete('cascade');
            $table->foreignId('assessment_item_id')->constrained('tka_assessment_items')->onDelete('cascade');
            $table->float('skor');
            $table->text('catatan_guru')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'assessment_item_id'], 'unique_assessment_item');
            $table->index('assessment_id');
            $table->index('assessment_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tka_assessment_responses');
    }
};
