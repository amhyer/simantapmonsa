<?php

namespace App\Jobs;

use App\Support\BackupTables;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportBackupJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Tulis backup JSON secara streaming (per chunk 200 baris) agar
     * memori tetap kecil walau data besar. Tanpa payload: job query sendiri.
     */
    public function handle(): void
    {
        set_time_limit(0);

        $filename = 'backup-simantap-' . now()->format('Y-m-d-His') . '.json';
        $path = 'backups/' . $filename;

        // Daftar tabel terpusat (App\Support\BackupTables) supaya cakupan
        // backup selalu seimbang dengan tabel yang dikosongkan wipeAll().
        $tables = BackupTables::semua();

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Gagal membuka stream sementara untuk backup.');
        }

        try {
            fwrite($stream, "{\n");

            $firstTable = true;
            foreach ($tables as $key => $konfigurasi) {
                // CBT belum tentu sudah dimigrasikan saat flag masih nonaktif.
                if (str_starts_with($key, 'cbt_') && !Schema::hasTable($key)) {
                    continue;
                }
                if (!$firstTable) {
                    fwrite($stream, ",\n");
                }
                $firstTable = false;

                fwrite($stream, json_encode($key) . ": [\n");

                $except = $konfigurasi['except'];
                $firstRow = true;

                $tulis = function ($rows) use ($stream, $except, $key, &$firstRow) {
                    foreach ($rows as $row) {
                        if (!$firstRow) {
                            fwrite($stream, ",\n");
                        }
                        $firstRow = false;

                        // Backup privat harus menyertakan kunci/snapshot CBT;
                        // hidden hanya untuk serialisasi HTTP, bukan pemulihan data.
                        if (str_starts_with($key, 'cbt_') && $row instanceof \Illuminate\Database\Eloquent\Model) {
                            $row->makeVisible($row->getHidden());
                        }
                        $record = is_array($row) ? $row : $row->toArray();
                        if ($except !== []) {
                            $record = array_diff_key($record, array_flip($except));
                        }
                        fwrite($stream, json_encode($record));
                    }
                };

                $class = $konfigurasi['class'];

                if ($class) {
                    // Eloquent chunk() hemat memori untuk tabel besar.
                    $class::chunk(200, $tulis);
                } else {
                    // Tabel tanpa model (pivot) → ambil langsung dari query builder.
                    // Kedua tabel pivot punya kolom `id` (lihat migrasinya).
                    DB::table($key)->orderBy('id')->chunk(200, $tulis);
                }

                fwrite($stream, "\n]");
            }

            fwrite($stream, "\n}\n");
            rewind($stream);

            Storage::disk('local')->put($path, stream_get_contents($stream));

            Log::info("Backup exported successfully: {$filename}");
        } finally {
            fclose($stream);
        }
    }
}
