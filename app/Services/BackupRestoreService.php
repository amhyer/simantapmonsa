<?php

namespace App\Services;

use App\Support\BackupTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Satu implementasi pemulihan backup, dipakai oleh:
 *
 * - `BackupController::import` (restore ke database aplikasi), dan
 * - `simantap:backup:drill` (restore ke database SQLite sementara).
 *
 * Sebelumnya logika restore hanya hidup di controller sehingga tidak mungkin
 * diuji tanpa menimpa database nyata. Pemisahan ini membuat drill dapat
 * membuktikan prosedur yang sama persis dengan restore produksi.
 */
class BackupRestoreService
{
    /**
     * @return array{tidak_dikenal: array<int, string>, total: int, tabel: array<int, string>}
     */
    public function validasi(array $data): array
    {
        $valid = BackupTables::tabel();
        $tidakDikenal = [];
        $total = 0;
        $tabel = [];

        foreach ($data as $table => $records) {
            if ($table === 'metadata') {
                continue;
            }

            if (! in_array($table, $valid, true)) {
                $tidakDikenal[] = (string) $table;

                continue;
            }

            $tabel[] = $table;
            $total += is_array($records) ? count($records) : 0;
        }

        return [
            'tidak_dikenal' => $tidakDikenal,
            'total' => $total,
            'tabel' => $tabel,
        ];
    }

    /**
     * Restore seluruh tabel yang ada di backup. Urutan mengikuti registry
     * (induk → anak). Mengembalikan jumlah baris yang diproses per tabel.
     *
     * @return array<string, int>
     */
    public function restore(array $data, ?string $connection = null): array
    {
        $sebelum = DB::getDefaultConnection();

        if ($connection !== null) {
            DB::setDefaultConnection($connection);
        }

        try {
            $hasil = [];

            foreach (BackupTables::tabel() as $table) {
                if (! array_key_exists($table, $data)) {
                    continue;
                }

                $hasil[$table] = $this->restoreTable($table, $data[$table], $connection);
            }

            return $hasil;
        } finally {
            if ($connection !== null) {
                DB::setDefaultConnection($sebelum);
            }
        }
    }

    /**
     * Restore satu tabel. Kolom difilter terhadap schema agar backup lama tetap
     * dapat dipulihkan. Baris users tanpa sandi diberi sandi acak + wajib ganti.
     */
    private function restoreTable(string $table, mixed $records, ?string $connection = null): int
    {
        if (! is_array($records) || $records === []) {
            return 0;
        }

        if (! in_array($table, BackupTables::tabel(), true) || ! Schema::hasTable($table)) {
            return 0;
        }

        $kolom = Schema::getColumnListing($table);
        $modelClass = BackupTables::model($table);
        $diproses = 0;

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $row = array_intersect_key($record, array_flip($kolom));

            if ($row === []) {
                continue;
            }

            $id = $row['id'] ?? null;

            if ($table === 'users' && empty($row['kata_sandi'])) {
                $row['kata_sandi'] = Hash::make(bin2hex(random_bytes(8)));
                $row['force_password_change'] = true;
            }

            if ($modelClass) {
                \Illuminate\Database\Eloquent\Model::unguarded(function () use ($modelClass, $id, $row) {
                    $query = method_exists($modelClass, 'withTrashed') ? $modelClass::withTrashed() : $modelClass::query();

                    if ($id !== null) {
                        $query->updateOrCreate(['id' => $id], $row);
                    } else {
                        $modelClass::create($row);
                    }
                });

                $diproses++;

                continue;
            }

            // Tabel tanpa model Eloquent (pivot).
            if ($id !== null) {
                DB::table($table)->updateOrInsert(['id' => $id], $row);
            } else {
                DB::table($table)->insert($row);
            }

            $diproses++;
        }

        $this->sinkronkanSequence($table, $kolom, $connection);

        return $diproses;
    }

    /**
     * @param  array<int, string>  $kolom
     */
    private function sinkronkanSequence(string $table, array $kolom, ?string $connection = null): void
    {
        if (! in_array('id', $kolom, true)) {
            return;
        }

        $db = $connection !== null ? DB::connection($connection) : DB::connection();

        if ($db->getDriverName() !== 'pgsql') {
            return;
        }

        $seq = $db->selectOne('SELECT pg_get_serial_sequence(?, ?) AS seq', [$table, 'id'])?->seq;

        if (! $seq) {
            return;
        }

        $maxId = (int) $db->table($table)->max('id');
        $db->statement('SELECT setval(?, ?, ?)', [$seq, max($maxId, 1), $maxId > 0]);
    }
}
