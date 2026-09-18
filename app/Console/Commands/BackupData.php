<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupData extends Command
{
    protected $signature = 'simantap:backup';
    protected $description = 'Backup data SIMANTAP ke file JSON';

    public function handle()
    {
        $this->info('Membuat backup...');

        $data = [
            'metadata' => [
                'version' => '1.0',
                'created_at' => now()->toIso8601String(),
                'app' => config('app.name'),
            ],
            'users' => \App\Models\User::all()->toArray(),
            'siswa' => \App\Models\Siswa::all()->toArray(),
            'nilai_erapor' => \App\Models\NilaiErapot::all()->toArray(),
            'kehadiran' => \App\Models\Kehadiran::all()->toArray(),
            'kebiasaan' => \App\Models\Kebiasaan::all()->toArray(),
        ];

        $filename = 'backup-simantap-' . date('Y-m-d-His') . '.json';
        Storage::disk('local')->put("backups/{$filename}", json_encode($data, JSON_PRETTY_PRINT));

        $this->info("✅ Backup tersimpan: storage/app/backups/{$filename}");
        $this->info("📊 Total: " . count($data['siswa']) . " siswa");
    }
}
