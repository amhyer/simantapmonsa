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
        Schema::create('cbt_integrity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_result_id')->constrained('cbt_test_results')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('siswa')->onDelete('cascade');
            $table->enum('jenis_pelanggaran', ['tab_switch', 'copy_paste', 'screen_capture', 'focus_lost', 'multiple_tabs', 'suspicious_activity']);
            $table->dateTime('timestamp');
            $table->text('deskripsi')->nullable();
            $table->json('data_tambahan')->nullable();
            $table->timestamps();

            $table->index('test_result_id');
            $table->index('siswa_id');
            $table->index('jenis_pelanggaran');
            $table->index('timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_integrity_logs');
    }
};
