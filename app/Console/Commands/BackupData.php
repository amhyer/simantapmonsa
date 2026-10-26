<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupStreamWriter;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupData extends Command
{
    protected $signature = 'simantap:backup
                            {--tanpa-rotasi : Lewati penghapusan cadangan kedaluwarsa}';

    protected $description = 'Backup seluruh tabel terdaftar ke JSON secara streaming (dengan rotasi & salinan offsite)';

    /** Pola berkas yang dikelola rotasi. Sama dengan yang ditulis ExportBackupJob. */
    private const POLA_BERKAS = 'backup-simantap-';

    public function handle(BackupStreamWriter $writer): int
    {
        $this->info('Membuat backup dari registry...');

        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Gagal membuka temporary stream untuk backup.');
        }

        try {
            $summary = $writer->writeTo($stream);
            $direktori = trim((string) config('backup.direktori', 'backups'), '/');
            $filename = self::POLA_BERKAS.date('Y-m-d-His').'.json';
            $relatif = "{$direktori}/{$filename}";
            $namaDiskUtama = (string) config('backup.disk', 'local');
            $utama = Storage::disk($namaDiskUtama);

            rewind($stream);
            if (! $utama->put($relatif, $stream)) {
                throw new RuntimeException("Gagal menyimpan backup ke disk '{$namaDiskUtama}'.");
            }

            $this->info('✅ Backup tersimpan: '.$this->lokasi($utama, $relatif, $namaDiskUtama));
            $this->info("📊 {$summary['tables']} tabel / {$summary['rows']} baris; siswa: ".($summary['rows_by_table']['siswa'] ?? 0));

            $this->salinKeOffsite($relatif, $stream, $namaDiskUtama);

            if ($this->option('tanpa-rotasi')) {
                $this->warn('Rotasi dilewati (--tanpa-rotasi).');

                return self::SUCCESS;
            }

            $this->rotasi($utama, $namaDiskUtama, $direktori);

            $salinan = $this->diskSalinan($namaDiskUtama);
            if ($salinan !== null) {
                $this->rotasi($salinan[1], $salinan[0], $direktori);
            }

            return self::SUCCESS;
        } finally {
            fclose($stream);
        }
    }

    /**
     * Salinan offsite. Kegagalan di sini TIDAK boleh membatalkan cadangan utama:
     * jaringan ke penyimpanan awan bisa putus, sementara disk lokal sudah aman.
     *
     * @return array{0: string, 1: Disk}|null  [nama disk, instance]
     */
    private function diskSalinan(string $diskUtama): ?array
    {
        $nama = (string) config('backup.salinan_disk', '');

        if ($nama === '' || $nama === $diskUtama) {
            return null;
        }

        return [$nama, Storage::disk($nama)];
    }

    /** @param resource $stream */
    private function salinKeOffsite(string $relatif, $stream, string $diskUtama): void
    {
        $salinan = $this->diskSalinan($diskUtama);
        if ($salinan === null) {
            return;
        }

        [$nama, $disk] = $salinan;

        try {
            rewind($stream);
            if (! $disk->put($relatif, $stream)) {
                throw new RuntimeException('Filesystem mengembalikan false saat menulis stream.');
            }
            $this->info("☁️  Salinan offsite tersimpan di disk '{$nama}': ".$this->lokasi($disk, $relatif, $nama));
        } catch (Throwable $e) {
            Log::error("Gagal menyalin cadangan ke disk '{$nama}': ".$e->getMessage());
            $this->error("⚠️  Cadangan utama AMAN, tetapi salinan ke disk '{$nama}' gagal: ".$e->getMessage());
        }
    }

    /**
     * Hapus berkas cadangan yang lebih tua dari `backup.retensi_hari`.
     * Hanya berkas berprefiks POLA_BERKAS — berkas lain di folder itu tidak disentuh.
     */
    private function rotasi(Disk $disk, string $namaDisk, string $direktori): void
    {
        $retensi = (int) config('backup.retensi_hari', 30);

        if ($retensi <= 0) {
            $this->warn("Rotasi nonaktif (backup.retensi_hari = {$retensi}) di disk '{$namaDisk}'.");

            return;
        }

        $batas = now()->subDays($retensi)->getTimestamp();
        $dihapus = 0;
        $gagal = 0;

        foreach ($disk->files($direktori) as $berkas) {
            if (! str_starts_with(basename($berkas), self::POLA_BERKAS)) {
                continue;
            }

            try {
                if ($disk->lastModified($berkas) > $batas) {
                    continue;
                }

                $disk->delete($berkas);
                $dihapus++;
            } catch (Throwable $e) {
                $gagal++;
                Log::warning("Gagal menghapus cadangan kedaluwarsa {$berkas}: ".$e->getMessage());
            }
        }

        if ($gagal > 0) {
            $this->warn("♻️  Disk '{$namaDisk}': {$dihapus} dihapus, {$gagal} gagal dihapus.");
        } else {
            $this->info("♻️  Disk '{$namaDisk}': {$dihapus} cadangan lebih tua dari {$retensi} hari dihapus.");
        }
    }

    /** Deskripsi lokasi yang aman untuk disk lokal maupun awan. */
    private function lokasi(Disk $disk, string $relatif, string $namaDisk): string
    {
        try {
            return $disk->path($relatif);
        } catch (Throwable) {
            return "disk:{$namaDisk}/{$relatif}";
        }
    }
}
