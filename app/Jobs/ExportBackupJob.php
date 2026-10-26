<?php

namespace App\Jobs;

use App\Services\Backup\BackupStreamWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExportBackupJob implements ShouldQueue
{
    use Queueable;

    /**
     * Tulis backup registry secara bertahap ke temporary stream, lalu unggah
     * sebagai resource agar JSON utuh tidak pernah dimaterialisasi di memori.
     */
    public function handle(BackupStreamWriter $writer): void
    {
        set_time_limit(0);

        $filename = 'backup-simantap-'.now()->format('Y-m-d-His').'.json';
        $directory = trim((string) config('backup.direktori', 'backups'), '/');
        $path = $directory.'/'.$filename;
        $diskName = (string) config('backup.disk', 'local');
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Gagal membuka temporary stream untuk backup.');
        }

        try {
            $summary = $writer->writeTo($stream);
            rewind($stream);

            if (! Storage::disk($diskName)->put($path, $stream)) {
                throw new RuntimeException("Gagal menyimpan backup ke disk '{$diskName}'.");
            }

            Log::info('Backup exported successfully.', [
                'filename' => $filename,
                'tables' => $summary['tables'],
                'rows' => $summary['rows'],
            ]);
        } finally {
            fclose($stream);
        }
    }
}
