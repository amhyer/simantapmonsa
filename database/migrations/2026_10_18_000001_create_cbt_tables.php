<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('subject', 100);
            $table->timestamps();
        });
        Schema::create('cbt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('cbt_question_banks')->cascadeOnDelete();
            $table->text('prompt');
            $table->json('options');
            $table->string('correct_answer', 1);
            $table->timestamps();
        });
        Schema::create('cbt_test_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('bank_id')->constrained('cbt_question_banks')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('kelas', 50);
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 20)->default('draft');
            $table->json('question_snapshot')->nullable();
            $table->json('participant_ids')->nullable();
            $table->timestamps();
            $table->index(['guru_id', 'status']);
        });
        Schema::create('cbt_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('cbt_test_sessions')->restrictOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('deadline_at');
            $table->timestamp('submitted_at')->nullable();
            $table->json('answers');
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'siswa_id']);
            $table->index(['submitted_at', 'deadline_at']);
        });
        Schema::create('cbt_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('cbt_results')->cascadeOnDelete();
            $table->string('type', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['cbt_violations', 'cbt_results', 'cbt_test_sessions', 'cbt_questions', 'cbt_question_banks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
