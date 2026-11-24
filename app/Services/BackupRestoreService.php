<?php

namespace App\Services;

use App\Support\BackupTables;
use Illuminate\Database\Eloquent\Model;
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
    /** @var array<string, array<int, string>> */
    private array $restoreColumns = [];

    /** @var array<string, ?string> */
    private array $restoreModels = [];

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

            return NilaiErapotAuditService::tanpaAudit(function () use ($data, $connection, &$hasil): array {
                foreach (BackupTables::tabel() as $table) {
                    if (! array_key_exists($table, $data)) {
                        continue;
                    }

                    $hasil[$table] = $this->restoreTable($table, $data[$table], $connection);
                }

                return $hasil;
            });
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

        $diproses = 0;

        foreach ($records as $record) {
            if ($this->restoreRecord($table, $record)) {
                $diproses++;
            }
        }

        $this->sinkronkanSequence($table, null, $connection);

        return $diproses;
    }

    /**
     * Restore satu baris record (dipakai baik oleh streaming import di BackupController
     * maupun restore array penuh di BackupRestoreService::restoreTable).
     */
    public function restoreRecord(string $table, mixed $record): bool
    {
        if (! is_array($record) || ! in_array($table, BackupTables::tabel(), true)) {
            return false;
        }

        if (! array_key_exists($table, $this->restoreColumns)) {
            if (! Schema::hasTable($table)) {
                $this->restoreColumns[$table] = [];
                $this->restoreModels[$table] = null;
            } else {
                $this->restoreColumns[$table] = Schema::getColumnListing($table);
                $this->restoreModels[$table] = BackupTables::model($table);
            }
        }

        $kolom = $this->restoreColumns[$table];
        if ($kolom === []) {
            return false;
        }

        $modelClass = $this->restoreModels[$table];
        $row = array_intersect_key($record, array_flip($kolom));

        if ($row === []) {
            return false;
        }

        $id = $row['id'] ?? null;

        if ($table === 'users' && empty($row['kata_sandi'])) {
            $row['kata_sandi'] = Hash::make(bin2hex(random_bytes(8)));
            $row['force_password_change'] = true;
        }

        if ($modelClass) {
            Model::unguarded(function () use ($modelClass, $id, $row): void {
                $modelClass::withoutEvents(function () use ($modelClass, $id, $row): void {
                    $query = method_exists($modelClass, 'withTrashed') ? $modelClass::withTrashed() : $modelClass::query();

                    if ($id !== null) {
                        $query->updateOrCreate(['id' => $id], $row);
                    } else {
                        $modelClass::create($row);
                    }
                });
            });

            return true;
        }

        // Tabel tanpa model Eloquent (pivot).
        if ($id !== null) {
            DB::table($table)->updateOrInsert(['id' => $id], $row);
        } else {
            DB::table($table)->insert($row);
        }

        return true;
    }

    /**
     * @param  array<int, string>|null  $kolom
     */
    public function sinkronkanSequence(string $table, ?array $kolom = null, ?string $connection = null): void
    {
        $db = $connection !== null ? DB::connection($connection) : DB::connection();

        if ($db->getDriverName() !== 'pgsql') {
            return;
        }

        if ($kolom === null) {
            if (! Schema::hasTable($table)) {
                return;
            }
            $kolom = $this->restoreColumns[$table] ?? Schema::getColumnListing($table);
        }

        if (! in_array('id', $kolom, true)) {
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
