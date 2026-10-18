<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds jadwal_pelajaran_id foreign key to nilai_erapor table
     * for E-Rapor alignment with Dapodik schedule data.
     */
    public function up(): void
    {
        // Guard: kolom sudah ditambahkan migrasi lokal 2026_10_14_000002.
        if (Schema::hasColumn('nilai_erapor', 'jadwal_pelajaran_id')) {
            return;
        }

        Schema::table('nilai_erapor', function (Blueprint $table) {
            // Add foreign key column (nullable untuk backward compatibility)
            $table->foreignId('jadwal_pelajaran_id')
                ->nullable()
                ->after('guru_id')
                ->constrained('jadwal_pelajaran')
                ->nullOnDelete(); // SET NULL saat jadwal dihapus (Dapodik re-import safe)

            // Add composite index untuk query alignment
            $table->index(['jadwal_pelajaran_id', 'semester', 'tahun_ajaran'], 'nilai_erapor_alignment_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kolom ditangani migrasi lain (2026_10_14) — jangan drop di sini.
        if (Schema::hasColumn('nilai_erapor', 'jadwal_pelajaran_id')) {
            return;
        }

        Schema::table('nilai_erapor', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['jadwal_pelajaran_id']);
            
            // Drop index
            $table->dropIndex('nilai_erapor_alignment_index');
            
            // Drop column
            $table->dropColumn('jadwal_pelajaran_id');
        });
    }
};
