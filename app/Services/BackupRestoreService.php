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
     *
     * Bila `$tenantId` diberikan (mode multi-sekolah), setiap baris divalidasi
     * agar tidak pernah menimpa atau menyisipkan data milik sekolah lain.
     */
    public function restoreRecord(string $table, mixed $record, ?int $tenantId = null): bool
    {
        if (! is_array($record) || ! in_array($table, BackupTables::tabel(), true)) {
            return false;
        }

        if ($tenantId !== null && $table === 'tenants') {
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

        if ($tenantId !== null) {
            if (in_array('tenant_id', $kolom, true)) {
                if (array_key_exists('tenant_id', $row) && $row['tenant_id'] !== null && (int) $row['tenant_id'] !== $tenantId) {
                    throw new \UnexpectedValueException('File backup ini milik sekolah lain dan tidak dapat dipulihkan ke sekolah Anda.');
                }

                $row['tenant_id'] = $tenantId;

                if ($id !== null) {
                    $existingOwner = DB::table($table)->where('id', $id)->value('tenant_id');
                    if ($existingOwner !== null && (int) $existingOwner !== $tenantId) {
                        throw new \UnexpectedValueException("ID data pada tabel '{$table}' bentrok dengan data sekolah lain.");
                    }
                }
            } elseif (in_array('siswa_id', $kolom, true)) {
                $siswaId = $row['siswa_id'] ?? null;
                if ($siswaId === null || ! Schema::hasTable('siswa') || ! Schema::hasColumn('siswa', 'tenant_id')) {
                    return false;
                }
                $siswaOwner = DB::table('siswa')->where('id', $siswaId)->value('tenant_id');
                if ($siswaOwner !== null && (int) $siswaOwner !== $tenantId) {
                    throw new \UnexpectedValueException('File backup ini berisi data siswa milik sekolah lain.');
                }
                if ($siswaOwner === null) {
                    return false;
                }
            } elseif (in_array('guru_id', $kolom, true) || in_array('user_id', $kolom, true)) {
                $ownerCol = in_array('guru_id', $kolom, true) ? 'guru_id' : 'user_id';
                $ownerUserId = $row[$ownerCol] ?? null;
                if ($ownerUserId === null || ! Schema::hasTable('users') || ! Schema::hasColumn('users', 'tenant_id')) {
                    return false;
                }
                $userOwner = DB::table('users')->where('id', $ownerUserId)->value('tenant_id');
                if ($userOwner !== null && (int) $userOwner !== $tenantId) {
                    throw new \UnexpectedValueException('File backup ini berisi data pengguna milik sekolah lain.');
                }
                if ($userOwner === null) {
                    return false;
                }
            } else {
                // Tabel tanpa kolom kepemilikan sekolah dilewati agar data
                // referensi global / sekolah lain tidak pernah berubah.
                return false;
            }
        }

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
