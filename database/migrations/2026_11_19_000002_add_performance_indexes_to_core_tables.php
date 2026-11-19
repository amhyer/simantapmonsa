<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar indeks baru untuk tabel query-tinggi hasil audit Tahap 3.
     * Hanya MENAMBAH indeks; tidak menghapus indeks yang sudah ada.
     *
     * @var array<int, array{table: string, columns: array<int, string>, name: string}>
     */
    private const INDEXES = [
        ['table' => 'aktivitas', 'columns' => ['tenant_id'], 'name' => 'idx_aktivitas_tenant_id'],
        ['table' => 'jadwal_pelajaran', 'columns' => ['guru_id'], 'name' => 'idx_jadwal_pelajaran_guru_id'],
        ['table' => 'jadwal_pelajaran', 'columns' => ['rombel_id'], 'name' => 'idx_jadwal_pelajaran_rombel_id'],
        ['table' => 'jadwal_pelajaran', 'columns' => ['kelas'], 'name' => 'idx_jadwal_pelajaran_kelas'],
        ['table' => 'siswa', 'columns' => ['rombel_id'], 'name' => 'idx_siswa_rombel_id'],
        ['table' => 'rombel', 'columns' => ['guru_id'], 'name' => 'idx_rombel_guru_id'],
        ['table' => 'nilai_erapor', 'columns' => ['guru_id'], 'name' => 'idx_nilai_erapor_guru_id'],
        ['table' => 'nilai_erapor', 'columns' => ['kelas'], 'name' => 'idx_nilai_erapor_kelas'],
        ['table' => 'kehadiran_mapel', 'columns' => ['kelas'], 'name' => 'idx_kehadiran_mapel_kelas'],
        ['table' => 'cbt_results', 'columns' => ['user_id'], 'name' => 'idx_cbt_results_user_id'],
        ['table' => 'cbt_test_sessions', 'columns' => ['kelas'], 'name' => 'idx_cbt_test_sessions_kelas'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $def) {
            if (! Schema::hasTable($def['table']) || ! Schema::hasColumns($def['table'], $def['columns'])) {
                continue;
            }
            if (Schema::hasIndex($def['table'], $def['name'])) {
                continue;
            }

            Schema::table($def['table'], function (Blueprint $table) use ($def): void {
                $table->index($def['columns'], $def['name']);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES) as $def) {
            if (! Schema::hasTable($def['table']) || ! Schema::hasIndex($def['table'], $def['name'])) {
                continue;
            }

            Schema::table($def['table'], function (Blueprint $table) use ($def): void {
                $table->dropIndex($def['name']);
            });
        }
    }
};
