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

        // Sertakan seluruh CBT dalam backup terjadwal, termasuk snapshot privat.
        foreach (\App\Support\BackupTables::semua() as $table => $definition) {
            if (str_starts_with($table, 'cbt_') && \Illuminate\Support\Facades\Schema::hasTable($table)) {
                $data[$table] = $definition['class']::all()->map(function ($row) {
                    return $row->makeVisible($row->getHidden())->toArray();
                })->all();
            }
        }

        $filename = 'backup-simantap-' . date('Y-m-d-His') . '.json';
        Storage::disk('local')->put("backups/{$filename}", json_encode($data, JSON_PRETTY_PRINT));

        // Disk 'local' berakar di storage/app/private (config/filesystems.php),
        // jadi tampilkan path sebenarnya agar operator tidak salah cari berkas.
        $this->info("✅ Backup tersimpan: " . Storage::disk('local')->path("backups/{$filename}"));
        $this->info("📊 Total: " . count($data['siswa']) . " siswa");
    }
}
