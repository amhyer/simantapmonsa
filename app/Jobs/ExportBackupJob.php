<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExportBackupJob implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        $filename = 'backup-simantap-' . now()->format('Y-m-d-His') . '.json';
        $path = 'backups/' . $filename;

        // Gabungkan data per chunk
        $allData = [];
        foreach ($this->data as $table => $chunk) {
            foreach ($chunk as $records) {
                $allData[$table] = array_merge($allData[$table] ?? [], $records);
            }
        }

        try {
            Storage::disk('local')->put($path, json_encode($allData, JSON_PRETTY_PRINT));
            Log::info("Backup exported successfully: {$filename}");
        } catch (\Exception $e) {
            Log::error("Gagal export backup: " . $e->getMessage());
            throw $e; // Re-throw agar queue bisa retry
        }
    }
}
