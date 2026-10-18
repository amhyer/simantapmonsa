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
        Schema::create('tryout_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('tryout_questions')->onDelete('cascade');
            $table->enum('huruf', ['A', 'B', 'C', 'D', 'E']);
            $table->text('isi_opsi');
            $table->boolean('adalah_benar')->default(false);
            $table->integer('urutan')->default(0);
            $table->timestamps();

            $table->unique(['question_id', 'huruf'], 'unique_huruf_per_question');
            $table->index('question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tryout_question_options');
    }
};
