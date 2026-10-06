<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Desain: docs/10-ALIGNMENT-DESIGN.md §4 (nullOnDelete, bukan restrict).
     */
    public function up(): void
    {
        Schema::table('nilai_erapor', function (Blueprint $table) {
            $table->foreignId('jadwal_pelajaran_id')
                ->nullable()
                ->after('guru_id')
                ->constrained('jadwal_pelajaran')
                ->nullOnDelete();

            $table->index(['jadwal_pelajaran_id', 'semester', 'tahun_ajaran']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nilai_erapor', function (Blueprint $table) {
            $table->dropForeign(['jadwal_pelajaran_id']);
            $table->dropIndex(['jadwal_pelajaran_id', 'semester', 'tahun_ajaran']);
            $table->dropColumn('jadwal_pelajaran_id');
        });
    }
};
