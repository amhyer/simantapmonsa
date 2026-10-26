<?php

namespace Database\Seeders\Support;

/**
 * Penjaga bersama untuk seeder yang menulis kredensial contoh.
 *
 * Beberapa seeder SIMANTAP membuat akun dengan sandi yang tercantum di
 * repositori (`admin123`, `guru123`, …). Menjalankannya di server live sama
 * artinya dengan membuka kredensial publik, jadi seeder semacam itu harus
 * menolak berjalan di production kecuali operator sengaja mengizinkannya
 * lewat `SIMANTAP_ALLOW_SEED=true` di `.env`.
 *
 * Penjaga ini dibuat terpusat agar `DatabaseSeeder`, `DemoDataSeeder`, dan
 * seeder contoh lain tidak menyalin logika yang sama tiga kali.
 */
final class SeedGuard
{
    /**
     * True bila seeder boleh melanjutkan.
     */
    public static function bolehJalan(?object $command = null): bool
    {
        if (! app()->environment('production')) {
            return true;
        }

        if (filter_var(env('SIMANTAP_ALLOW_SEED', false), FILTER_VALIDATE_BOOL) === true) {
            $command?->warn('SIMANTAP_ALLOW_SEED=true — seeder contoh dijalankan di production.');

            return true;
        }

        $command?->error('Seeder ditolak: APP_ENV=production.');
        $command?->line(
            'Seeder ini membuat akun dengan sandi yang ada di repositori. '
            . 'Buat akun admin pertama secara manual (lihat DEPLOYMENT.md §6), '
            . 'atau set SIMANTAP_ALLOW_SEED=true bila Anda benar-benar yakin.'
        );

        return false;
    }

    /**
     * Akun contoh di luar lingkungan local wajib mengganti sandi pada login
     * pertama; middleware `force.password.change` yang menegakkannya.
     */
    public static function paksaGantiSandi(): bool
    {
        return ! app()->environment('local');
    }
}
