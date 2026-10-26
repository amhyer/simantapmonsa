<?php

namespace App\Console\Commands;

use App\Models\CbtTestSession;
use App\Models\Exam\Session as ExamSession;
use App\Services\Exam\ExamItemAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AnalisisButir extends Command
{
    protected $signature = 'exam:analisis-butir
                            {--cbt= : ID sesi cbt_test_sessions}
                            {--engine= : ID sesi exam_sessions}
                            {--json : Keluarkan hasil sebagai JSON}';

    protected $description = 'Analisis tingkat kesukaran, daya beda, dan distraktor butir soal (read-only)';

    public function handle(ExamItemAnalysisService $analysis): int
    {
        $cbt = $this->option('cbt');
        $engine = $this->option('engine');

        if ($cbt !== null && $engine !== null) {
            $this->error('Pilih salah satu: --cbt atau --engine.');

            return self::FAILURE;
        }

        if ($cbt === null && $engine === null) {
            $this->info('Pilih sesi dengan --cbt=ID atau --engine=ID.');
            $this->table(
                ['ID CBT', 'Nama', 'Kelas', 'Status', 'Hasil'],
                CbtTestSession::withCount('results')->latest('id')->limit(20)->get()->map(fn ($s): array => [
                    $s->id, $s->title, $s->kelas, $s->status, $s->results_count,
                ])->all()
            );

            return self::SUCCESS;
        }

        try {
            if ($cbt !== null) {
                $session = CbtTestSession::findOrFail((int) $cbt);
                $hasil = $analysis->untukSesiCbt($session);
                $nama = $session->title;
                $id = $session->id;
            } else {
                $session = ExamSession::findOrFail((int) $engine);
                $hasil = $analysis->analisisEngine($session);
                $nama = $session->nama;
                $id = $session->id;
            }
        } catch (ModelNotFoundException) {
            $this->error('Sesi tidak ditemukan.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'sesi' => ['id' => $id, 'nama' => $nama],
                ...$hasil,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info("Sesi: {$nama} (ID {$id})");
        $this->line("Sumber: {$hasil['sumber']} | Status data: {$hasil['status_data']} | Peserta: {$hasil['jumlah_peserta']}");
        $this->newLine();

        $this->table(
            ['No', 'Butir', 'Kunci', 'Benar', 'P', 'Kategori P', 'D', 'Kategori D', 'Kosong', 'Catatan'],
            array_map(fn (array $b): array => [
                $b['nomor'],
                mb_strimwidth($b['teks'], 0, 60, '...'),
                $b['kunci'] ?? '?',
                "{$b['jumlah_benar']}/{$b['jumlah_peserta']}",
                $b['p'] !== null ? number_format($b['p'] * 100, 1).'%' : '-',
                $b['kategori_p'],
                $b['d'] !== null ? number_format($b['d'], 2) : '-',
                $b['kategori_d'],
                $b['kosong'],
                implode('; ', $b['catatan']) ?: 'Baik',
            ], $hasil['butir'])
        );

        return self::SUCCESS;
    }
}
