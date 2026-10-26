<?php

namespace App\Console\Commands;

use App\Services\NilaiErapotAuditService;
use Illuminate\Console\Command;

class VerifikasiAuditNilai extends Command
{
    protected $signature = 'nilai:audit:verifikasi
                            {--nilai= : Hanya periksa satu ID nilai e-Rapor}
                            {--json : Keluarkan hasil sebagai JSON}';

    protected $description = 'Verifikasi rantai hash jejak audit nilai e-Rapor (read-only)';

    public function handle(NilaiErapotAuditService $audit): int
    {
        $nilai = $this->option('nilai');

        if ($nilai !== null && (! ctype_digit((string) $nilai) || (int) $nilai < 1)) {
            $this->error('Opsi --nilai harus berupa ID numerik.');

            return self::FAILURE;
        }

        $hasil = $audit->verifikasi($nilai !== null ? (int) $nilai : null);

        if ($this->option('json')) {
            $this->line(json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return $hasil['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Catatan diperiksa: {$hasil['diperiksa']}");

        if ($hasil['ok']) {
            $this->info('Rantai audit utuh.');

            return self::SUCCESS;
        }

        $this->error('Rantai audit TIDAK utuh: '.count($hasil['masalah']).' masalah ditemukan.');

        $this->table(
            ['Audit ID', 'Nilai ID', 'Jenis', 'Tersimpan', 'Diharapkan'],
            array_map(fn (array $masalah): array => [
                $masalah['audit_id'],
                $masalah['nilai_erapor_id'] ?? '-',
                $masalah['jenis'],
                $masalah['tersimpan'] ?? '-',
                $masalah['diharapkan'] ?? '-',
            ], $hasil['masalah'])
        );

        return self::FAILURE;
    }
}
