<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceIndexesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_new_performance_indexes_exist_and_migration_is_idempotent(): void
    {
        $expected = [
            'aktivitas' => 'idx_aktivitas_tenant_id',
            'jadwal_pelajaran' => ['idx_jadwal_pelajaran_guru_id', 'idx_jadwal_pelajaran_rombel_id', 'idx_jadwal_pelajaran_kelas'],
            'siswa' => 'idx_siswa_rombel_id',
            'rombel' => 'idx_rombel_guru_id',
            'nilai_erapor' => ['idx_nilai_erapor_guru_id', 'idx_nilai_erapor_kelas'],
            'kehadiran_mapel' => 'idx_kehadiran_mapel_kelas',
            'cbt_results' => 'idx_cbt_results_user_id',
            'cbt_test_sessions' => 'idx_cbt_test_sessions_kelas',
        ];

        foreach ($expected as $table => $names) {
            foreach ((array) $names as $name) {
                $this->assertTrue(
                    Schema::hasIndex($table, $name),
                    "Indeks {$name} pada tabel {$table} tidak ditemukan."
                );
            }
        }

        // Jalankan ulang up() secara langsung -> harus idempoten tanpa error
        $migration = require database_path('migrations/2026_11_19_000002_add_performance_indexes_to_core_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex('aktivitas', 'idx_aktivitas_tenant_id'));
    }
}
