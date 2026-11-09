<?php

namespace App\Console\Commands;

use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\PresensiMesinService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Sinkron otomatis presensi dari database mesin fingerprint (Attendance Management).
 *
 * Dipakai oleh scheduler (`schedule:run`) dan bisa dijalankan manual:
 *   php artisan presensi:mesin-sync --dari=2026-11-08 --sampai=2026-11-08
 *
 * Rentang default: sejak sinkron terakhir (maksimal 30 hari ke belakang) s/d
 * hari ini. Cara ini membuat scan yang tertunda beberapa hari (mis. mesin atau
 * ZKTime sempat mati) tetap ikut terbaca, tanpa menarik seluruh isi database.
 * Tidak pernah menghapus data: hanya membuat/perbarui presensi sesuai
 * aturan service (izin, sakit, cuti dan koreksi admin dilindungi).
 */
class PresensiMesinSyncCommand extends Command
{
    private const KUNCI_SINKRON_TERAKHIR = 'presensi.mesin.terakhir_tarik';

    private const MAKS_HARI_TARIK = 30;

    protected $signature = 'presensi:mesin-sync
        {--dari= : Rentang awal tarik data (format Y-m-d). Default: sejak sinkron terakhir}
        {--sampai= : Rentang akhir tarik data (format Y-m-d). Default: hari ini}';

    protected $description = 'Sinkron otomatis presensi dari database mesin fingerprint Attendance Management';

    public function handle(AttendanceDatabaseReader $reader, PresensiMesinService $presensi): int
    {
        $sampai = $this->option('sampai')
            ? Carbon::parse((string) $this->option('sampai'), config('app.timezone'))->startOfDay()
            : Carbon::today(config('app.timezone'));

        $dari = $this->option('dari')
            ? Carbon::parse((string) $this->option('dari'), config('app.timezone'))->startOfDay()
            : $this->mulaiDefault($sampai);

        if ($dari->gt($sampai)) {
            $dari = $sampai->copy();
        }

        if ((int) $dari->diffInDays($sampai) > self::MAKS_HARI_TARIK) {
            $this->error('Rentang tarik data maksimal '.self::MAKS_HARI_TARIK.' hari agar proses tetap cepat.');

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
            Cache::forever(self::KUNCI_SINKRON_TERAKHIR, now()->toDateTimeString());

            $this->info('Database mesin berhasil dibaca, tetapi tidak ada scan pada '.$dari->toDateString().' s/d '.$sampai->toDateString().'.');

            return self::SUCCESS;
        }

        $hasil = $presensi->importScans($scans, null);

        Cache::forever(self::KUNCI_SINKRON_TERAKHIR, now()->toDateTimeString());

        $this->info(
            'Sinkron presensi mesin '.$dari->toDateString().' s/d '.$sampai->toDateString().' selesai: '
            .count($scans).' scan, '
            ."{$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, {$hasil['tetap']} tidak berubah, "
            ."{$hasil['dilindungi']} dilindungi, {$hasil['nik_tidak_dikenal']} NIK tidak dikenal."
        );

        if (! empty($hasil['contoh_nik_tidak_dikenal'])) {
            $this->warn('Contoh NIK tidak dikenal: '.implode(', ', $hasil['contoh_nik_tidak_dikenal']).'.');
        }

        return self::SUCCESS;
    }

    /**
     * Tanggal awal tarik default: satu hari sebelum sinkron terakhir agar scan
     * yang masuk terlambat tetap tertangkap, dibatasi 30 hari ke belakang.
     */
    private function mulaiDefault(Carbon $sampai): Carbon
    {
        $batasMundur = $sampai->copy()->subDays(self::MAKS_HARI_TARIK);
        $terakhir = Cache::get(self::KUNCI_SINKRON_TERAKHIR);

        if (is_string($terakhir) && trim($terakhir) !== '') {
            try {
                $mulai = Carbon::parse($terakhir, config('app.timezone'))->startOfDay()->subDay();

                return $mulai->lt($batasMundur) ? $batasMundur : $mulai;
            } catch (\Throwable) {
                // Abaikan nilai cache yang rusak dan pakai default di bawah.
            }
        }

        return $sampai->copy()->subDay();
    }
}
