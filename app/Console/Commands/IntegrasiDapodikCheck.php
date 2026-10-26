<?php

namespace App\Console\Commands;

use App\Models\DapodikConfig;
use App\Services\Dapodik\DapodikClient;
use Illuminate\Console\Command;

/**
 * Pemeriksaan integrasi Dapodik untuk staging/produksi.
 *
 * Tanpa `--live` perintah hanya memeriksa konfigurasi (tidak menyentuh
 * jaringan). Dengan `--live` perintah memanggil endpoint baca `getSekolah`
 * sekali; tidak ada data yang ditulis ke Dapodik maupun database lokal.
 */
class IntegrasiDapodikCheck extends Command
{
    protected $signature = 'integrasi:dapodik:cek
                            {--live : Panggil getSekolah read-only ke WebService Dapodik}
                            {--json : Keluarkan laporan JSON}';

    protected $description = 'Periksa konfigurasi dan koneksi Dapodik (read-only)';

    public function handle(): int
    {
        $config = DapodikConfig::getInstance();

        $masalah = [];
        $peringatan = [];
        $data = [
            'npsn' => $config->npsn,
            'host' => $config->host,
            'port' => $config->port,
            'protocol' => $config->protocol,
            'token' => $config->masked_token,
            'auto_sync_enabled' => (bool) $config->auto_sync_enabled,
            'last_sync_at' => $config->last_sync_at?->toIso8601String(),
            'live' => false,
            'nama_sekolah' => null,
        ];

        if (blank($config->npsn)) {
            $masalah[] = 'NPSN belum diisi.';
        }

        if (blank($config->token)) {
            $masalah[] = 'Token WebService Dapodik belum diisi.';
        }

        if (blank($config->host)) {
            $masalah[] = 'Host Dapodik belum diisi.';
        }

        if (! is_numeric($config->port) || (int) $config->port < 1 || (int) $config->port > 65535) {
            $masalah[] = 'Port Dapodik tidak valid (1-65535).';
        }

        if (! in_array($config->protocol, ['http', 'https'], true)) {
            $masalah[] = "Protocol harus 'http' atau 'https'.";
        }

        if ($config->protocol === 'http'
            && app()->environment('production')
            && ! $config->allow_insecure_in_production) {
            $masalah[] = 'Produksi memakai HTTP tanpa allow_insecure_in_production. Gunakan HTTPS atau aktifkan pengecualian secara sadar.';
        }

        if ($config->auto_sync_enabled) {
            if (! $config->auto_sync_last_run_at) {
                $peringatan[] = 'Auto-sync aktif tetapi belum pernah berjalan.';
            } elseif ($config->auto_sync_last_run_at->lt(now()->subDays(7))) {
                $peringatan[] = 'Auto-sync terakhir lebih dari 7 hari lalu: ' . $config->auto_sync_last_run_at->diffForHumans();
            }

            if ($config->auto_sync_run_status === 'gagal') {
                $peringatan[] = 'Auto-sync terakhir berstatus gagal: ' . ($config->auto_sync_run_error ?: 'tanpa pesan');
            }
        }

        if ($this->option('live') && $masalah === []) {
            $data['live'] = true;

            try {
                $client = new DapodikClient(
                    (string) $config->npsn,
                    (string) $config->token,
                    (string) $config->host,
                    (int) $config->port,
                    (string) $config->protocol,
                    is_array($config->cf_access) ? $config->cf_access : [],
                );

                $sekolah = $client->getSekolah();
                $nama = $sekolah[0]['nama'] ?? $sekolah[0]['nama_sekolah'] ?? null;
                $data['nama_sekolah'] = $nama;

                if (! $nama) {
                    $peringatan[] = 'Koneksi berhasil tetapi respons getSekolah tidak memuat nama sekolah.';
                }
            } catch (\Throwable $e) {
                $masalah[] = 'Koneksi live gagal: ' . $e->getMessage();
            }
        }

        $data['ok'] = $masalah === [];
        $data['masalah'] = $masalah;
        $data['peringatan'] = $peringatan;

        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $data['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(['Pemeriksaan', 'Nilai'], [
            ['NPSN', $config->npsn ?: '—'],
            ['Host:Port', ($config->host ?: '—') . ':' . ($config->port ?: '—')],
            ['Protocol', $config->protocol ?: '—'],
            ['Token', $config->masked_token ?: '—'],
            ['Auto-sync', $config->auto_sync_enabled ? 'aktif' : 'nonaktif'],
            ['Auto-sync terakhir', $config->auto_sync_last_run_at?->toDateTimeString() ?: '—'],
            ['Live check', $data['live'] ? 'ya' : 'tidak (pakai --live)'],
            ['Nama sekolah', $data['nama_sekolah'] ?: '—'],
        ]);

        foreach ($peringatan as $pesan) {
            $this->warn('⚠ ' . $pesan);
        }

        foreach ($masalah as $pesan) {
            $this->error('✗ ' . $pesan);
        }

        if ($data['ok']) {
            $this->info('✅ Konfigurasi Dapodik siap.' . ($data['live'] ? ' Koneksi live berhasil.' : ' Jalankan --live untuk menguji koneksi.'));

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
