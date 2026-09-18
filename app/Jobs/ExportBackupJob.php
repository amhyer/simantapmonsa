<?php

namespace App\Jobs;

use App\Models\Aktivitas;
use App\Models\Catatan;
use App\Models\Dimensi;
use App\Models\HasilKuis;
use App\Models\Kebiasaan;
use App\Models\Kehadiran;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\PengaturanGuru;
use App\Models\RingkasanGuru;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
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

        $tables = [
            'users' => [User::class, function ($m) {
                return collect($m->toArray())->except(['kata_sandi', 'remember_token', 'dapodik_id', 'rekaman'])->toArray();
            }],
            'siswa' => [Siswa::class, function ($m) {
                return collect($m->toArray())->except(['rekaman', 'foto', 'nik', 'no_kk'])->toArray();
            }],
            'materi' => [Materi::class, null],
            'kuis' => [Kuis::class, null],
            'nilai' => [Nilai::class, null],
            'kehadiran' => [Kehadiran::class, null],
            'hasil_kuis' => [HasilKuis::class, null],
            'catatan' => [Catatan::class, null],
            'dimensi' => [Dimensi::class, null],
            'kebiasaan' => [Kebiasaan::class, null],
            'pengaturan_guru' => [PengaturanGuru::class, null],
            'ringkasan_guru' => [RingkasanGuru::class, null],
            'sekolah_settings' => [SekolahSettings::class, null],
            'aktivitas' => [Aktivitas::class, null],
        ];

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Gagal membuka stream sementara untuk backup.');
        }

        try {
            fwrite($stream, "{\n");

            $firstTable = true;
            foreach ($tables as $key => [$class, $map]) {
                if (!$firstTable) {
                    fwrite($stream, ",\n");
                }
                $firstTable = false;

                fwrite($stream, json_encode($key) . ": [\n");

                $firstRow = true;
                $class::chunk(200, function ($rows) use ($stream, $map, &$firstRow) {
                    foreach ($rows as $row) {
                        if (!$firstRow) {
                            fwrite($stream, ",\n");
                        }
                        $firstRow = false;
                        $record = $map ? $map($row) : $row->toArray();
                        fwrite($stream, json_encode($record));
                    }
                });

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
