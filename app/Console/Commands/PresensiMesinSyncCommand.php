<?php

namespace App\Console\Commands;

use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\PresensiMesinService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sinkron otomatis presensi dari database mesin fingerprint (Attendance Management).
 *
 * Dipakai oleh scheduler (`schedule:run`) dan bisa dijalankan manual:
 *   php artisan presensi:mesin-sync --dari=2026-11-08 --sampai=2026-11-08
 *
 * Rentang default: kemarin s/d hari ini (cocok jadwal harian).
 * Tidak pernah menghapus data: hanya membuat/perbarui presensi sesuai
 * aturan service (izin, sakit, cuti dan koreksi admin dilindungi).
 */
class PresensiMesinSyncCommand extends Command
{
    protected $signature = 'presensi:mesin-sync
        {--dari= : Rentang awal tarik data (format Y-m-d). Default: kemarin}
        {--sampai= : Rentang akhir tarik data (format Y-m-d). Default: hari ini}';

    protected $description = 'Sinkron otomatis presensi dari database mesin fingerprint Attendance Management';

    public function handle(AttendanceDatabaseReader $reader, PresensiMesinService $presensi): int
    {
        $dari = $this->option('dari')
            ? Carbon::parse((string) $this->option('dari'), config('app.timezone'))->startOfDay()
            : Carbon::yesterday(config('app.timezone'));
        $sampai = $this->option('sampai')
            ? Carbon::parse((string) $this->option('sampai'), config('app.timezone'))->startOfDay()
            : Carbon::today(config('app.timezone'));

        if ((int) $dari->diffInDays($sampai) > 30) {
            $this->error('Rentang tarik data maksimal 30 hari agar proses tetap cepat.');

            return self::FAILURE;
        }

        try {
            $scans = $reader->baca($dari, $sampai);
        } catch (\Throwable $e) {
            report($e);

            $this->error('Gagal membaca database mesin: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($scans === []) {
            $this->info('Database mesin berhasil dibaca, tetapi tidak ada scan pada '.$dari->toDateString().' s/d '.$sampai->toDateString().'.');

            return self::SUCCESS;
        }

        $hasil = $presensi->importScans($scans, null);
        $hasil['baris_diabaikan'] = 0;

        return self::SUCCESS;
    }
}
