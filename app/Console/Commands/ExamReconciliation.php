<?php

namespace App\Console\Commands;

use App\Models\Exam\Engine;
use App\Services\Exam\ExamReconciliationService;
use Illuminate\Console\Command;

/**
 * Membuktikan isi data lama dan engine baru benar-benar sama.
 *
 * Berbeda dari `exam:backfill` yang hanya menyalin dan menghitung baris,
 * perintah ini membandingkan kolom, opsi, jawaban, nilai, dan relasi untuk
 * setiap baris. Read-only dan aman dijalankan di produksi sebelum go-live.
 */
class ExamReconciliation extends Command
{
    protected $signature = 'exam:rekonsiliasi
                            {--only= : Batasi ke cbt atau tryout}
                            {--batas=25 : Jumlah masalah yang ditampilkan}
                            {--json : Keluarkan laporan JSON}';

    protected $description = 'Bandingkan isi cbt_*/tryout_* dengan exam_* (read-only)';

    public function handle(ExamReconciliationService $service): int
    {
        $only = $this->option('only');

        if ($only !== null && ! in_array($only, [Engine::CBT, Engine::TRYOUT], true)) {
            $this->error("Nilai --only harus 'cbt' atau 'tryout'.");

            return self::INVALID;
        }

        $laporan = $service->laporan($only, (int) $this->option('batas'));

        if ($this->option('json')) {
            $this->line((string) json_encode($laporan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $laporan['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Jumlah baris (legacy → engine):');

        $baris = [];
        foreach ($laporan['jumlah']['legacy'] ?? [] as $tabel => $jumlah) {
            if (str_contains($tabel, '.')) {
                continue;
            }

            $baris[] = [$tabel, $jumlah];
        }

        $this->table(['Tabel legacy', 'Jumlah'], $baris);

        $engine = [];
        foreach ($laporan['jumlah']['engine'] ?? [] as $tabel => $jumlah) {
            $engine[] = [$tabel, $jumlah];
        }

        $this->table(['Padanan engine', 'Jumlah'], $engine);

        if ($laporan['ok']) {
            $this->info("✅ Rekonsiliasi cocok: {$laporan['diperiksa']} baris diperiksa, 0 masalah.");

            return self::SUCCESS;
        }

        $this->error("❌ {$laporan['total_masalah']} masalah ditemukan dari {$laporan['diperiksa']} baris yang diperiksa.");

        $contoh = array_map(fn (array $m) => [
            $m['area'],
            $m['tabel'],
            $m['id'],
            $m['masalah'],
            $m['detail'] === [] ? '-' : (string) json_encode($m['detail'], JSON_UNESCAPED_UNICODE),
        ], $laporan['masalah']);

        $this->table(['Area', 'Tabel', 'ID', 'Masalah', 'Detail'], $contoh);

        $this->line('Jalankan ulang dengan --json untuk laporan lengkap (field `masalah` dibatasi oleh --batas).');

        return self::FAILURE;
    }
}
