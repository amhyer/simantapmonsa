<?php

namespace App\Services\Backup;

use App\Support\BackupTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Serializes the canonical backup registry to a seekable temporary stream.
 * The stream spills to disk after 2 MiB, so callers can upload it with
 * FilesystemAdapter::put($path, $resource) without materializing the complete
 * backup JSON string in application memory.
 */
class BackupStreamWriter
{
    /**
     * @param  resource  $stream
     * @return array{tables: int, rows: int, rows_by_table: array<string, int>}
     */
    public function writeTo($stream): array
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('Backup output must be a writable stream.');
        }

        $flags = JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;
        $counts = [];
        $this->writeAll($stream, "{\n");

        $this->writeMember($stream, 'metadata', [
            'version' => '1.0',
            'created_at' => now()->toIso8601String(),
            'app' => config('app.name'),
        ], $flags);

        $registry = BackupTables::semua();
        foreach ($registry as $table => $definition) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            if ($columns === []) {
                continue;
            }

            $this->writeAll($stream, ",\n" . json_encode($table, $flags) . ": [\n");
            $firstRow = true;
            $counts[$table] = 0;
            $except = array_flip($definition['except']);

            $query = DB::table($table);
            $orderColumn = in_array('id', $columns, true) ? 'id' : $columns[0];
            $query->orderBy($orderColumn)->chunk(200, function ($rows) use ($stream, $flags, &$firstRow, &$counts, $table, $except) {
                foreach ($rows as $row) {
                    $record = array_diff_key((array) $row, $except);

                    if (! $firstRow) {
                        $this->writeAll($stream, ",\n");
                    }
                    $this->writeAll($stream, json_encode($record, $flags));
                    $firstRow = false;
                    $counts[$table]++;
                }
            });

            $this->writeAll($stream, "\n]");
        }

        $this->writeAll($stream, "\n}\n");

        return [
            'tables' => count($counts),
            'rows' => array_sum($counts),
            'rows_by_table' => $counts,
        ];
    }

    /** @param resource $stream */
    private function writeMember($stream, string $key, array $value, int $flags): void
    {
        $this->writeAll($stream, json_encode($key, $flags) . ': ' . json_encode($value, $flags));
    }

    /** @param resource $stream */
    private function writeAll($stream, string $value): void
    {
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            $written = fwrite($stream, substr($value, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Failed while writing the backup stream.');
            }
            $offset += $written;
        }
    }
}
