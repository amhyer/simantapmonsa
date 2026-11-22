<?php

namespace App\Jobs;

use App\Services\AktivitasService;
use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\MesinPresensi\PresensiMesinPullService;
use App\Services\PresensiMesinService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TarikSemuaPresensiJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?int $userId,
    ) {}

    /**
     * Tarik seluruh riwayat scan mesin di background. Dulu dijalankan sinkron
     * dalam request web dengan set_time_limit(600) — pasti menabrak timeout
     * Nginx/PHP-FPM dan bisa jalan ganda. Sekarang lewat queue worker.
     */
    public function handle(
        AttendanceDatabaseReader $reader,
        PresensiMesinService $presensi,
        PresensiMesinPullService $pull,
    ): void {
        set_time_limit(0);

        try {
            $rentang = $reader->rentang();
        } catch (\Throwable $e) {
            report($e);
            Log::error('Tarik semua presensi gagal membaca rentang: '.$e->getMessage());

            return;
        }

        $dari = $rentang['min'];
        $sampai = $rentang['maks'];

        if ($dari === null || $sampai === null) {
            $pull->tandaiTerakhirTarik();

            return;
        }

        try {
            $hasil = $pull->tarikSemuaRentang($reader, $presensi, $dari, $sampai, $this->userId);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Tarik semua presensi gagal: '.$e->getMessage());

            return;
        }

        $pull->tandaiTerakhirTarik();

        AktivitasService::log(
            'tambah',
            'Tarik presensi dari database mesin',
            "Tarik data mesin {$dari->toDateString()} s/d {$sampai->toDateString()}: {$hasil['scans']} scan, {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, {$hasil['tetap']} tetap, {$hasil['dilindungi']} dilindungi, {$hasil['nik_tidak_dikenal']} NIK tidak dikenal.",
            'presensi_pegawai',
            null,
            null,
            $hasil,
        );
    }
}
