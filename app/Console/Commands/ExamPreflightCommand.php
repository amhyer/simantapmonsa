<?php

namespace App\Console\Commands;

use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\Exam\Answer as ExamAnswer;
use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Exam\Result as ExamResult;
use App\Models\Exam\Session as ExamSession;
use App\Models\Tka\StudentAssessment;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Services\Exam\ExamReconciliationService;
use App\Services\NilaiErapotAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gerbang preflight terpadu (read-only) untuk aktivasi bertahap modul ujian:
 *   - tka     : Fase 1 (TKA / Kesiapan Asesmen)
 *   - cbt     : Fase 2 (CBT & Tryout Dual-Write)
 *   - readers : Fase 3 (Cutover pembaca dasbor/laporan ke engine `exam_*`)
 */
class ExamPreflightCommand extends Command
{
    protected $signature = 'exam:preflight
                            {--target=cbt : Target fase aktivasi (tka|cbt|readers)}
                            {--json : Keluarkan laporan dalam format JSON}';

    protected $description = 'Pemeriksaan preflight terpadu (GO/NO-GO) sebelum aktivasi bertahap modul ujian TKA/CBT/Tryout';

    public function handle(
        ExamReconciliationService $reconciliation,
        NilaiErapotAuditService $auditNilai
    ): int {
        $target = strtolower(trim((string) $this->option('target')));
        if (! in_array($target, ['tka', 'cbt', 'readers'], true)) {
            $this->error("Nilai --target harus salah satu dari: tka, cbt, readers (diterima: '{$target}').");

            return self::INVALID;
        }

        $checks = [];

        // 1. Ketersediaan skema tabel ujian sesuai target fase
        $requiredTables = $this->requiredTablesFor($target);
        $missingTables = array_values(array_filter(
            $requiredTables,
            fn (string $table): bool => ! Schema::hasTable($table)
        ));
        $checks[] = [
            'kode' => 'schema',
            'nama' => 'Skema tabel modul ujian',
            'lulus' => $missingTables === [],
            'detail' => $missingTables === []
                ? count($requiredTables).' tabel inti tersedia'
                : 'Tabel hilang: '.implode(', ', $missingTables),
        ];

        // 2. Konsistensi feature flag & driver cache/queue
        $flagIssues = $this->checkFeatureFlags($target);
        $checks[] = [
            'kode' => 'flags',
            'nama' => 'Konsistensi feature flag & cache/queue',
            'lulus' => $flagIssues === [],
            'detail' => $flagIssues === [] ? 'Konfigurasi selaras' : implode('; ', $flagIssues),
        ];

        // 3. Integritas rantai audit nilai e-Rapor
        $audit = $auditNilai->verifikasi();
        $checks[] = [
            'kode' => 'audit_nilai',
            'nama' => 'Integritas rantai hash audit nilai',
            'lulus' => (bool) $audit['ok'],
            'detail' => $audit['ok']
                ? "{$audit['diperiksa']} catatan diperiksa, rantai utuh"
                : count($audit['masalah']).' anomali ditemukan pada rantai audit nilai',
        ];

        // 4. Rekonsiliasi isi legacy vs engine (wajib untuk fase cbt & readers)
        if (in_array($target, ['cbt', 'readers'], true)) {
            $rekonsiliasi = $reconciliation->laporan(null, 10);
            $checks[] = [
                'kode' => 'rekonsiliasi',
                'nama' => 'Rekonsiliasi legacy (cbt/tryout) vs engine (exam_*)',
                'lulus' => (bool) $rekonsiliasi['ok'],
                'detail' => $rekonsiliasi['ok']
                    ? "{$rekonsiliasi['diperiksa']} baris diperiksa, 0 selisih"
                    : "{$rekonsiliasi['total_masalah']} selisih dari {$rekonsiliasi['diperiksa']} baris",
            ];
        }

        // 5. Kebersihan antrean failed_jobs
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $checks[] = [
            'kode' => 'failed_jobs',
            'nama' => 'Antrean job gagal (failed_jobs)',
            'lulus' => $failedJobs === 0,
            'detail' => $failedJobs === 0
                ? '0 job gagal'
                : "{$failedJobs} job gagal belum ditangani (cek `php artisan queue:failed`)",
        ];

        $allPassed = ! in_array(false, array_column($checks, 'lulus'), true);

        $payload = [
            'target' => $target,
            'keputusan' => $allPassed ? 'GO' : 'NO-GO',
            'ok' => $allPassed,
            'diperiksa_pada' => now()->toIso8601String(),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $allPassed ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Preflight Aktivasi Modul Ujian — Target Fase: '.strtoupper($target));
        $this->table(
            ['Kode', 'Pemeriksaan', 'Status', 'Detail'],
            array_map(fn (array $c): array => [
                $c['kode'],
                $c['nama'],
                $c['lulus'] ? 'LULUS' : 'GAGAL',
                $c['detail'],
            ], $checks)
        );

        if ($allPassed) {
            $this->info('✅ KEPUTUSAN: GO — Prasyarat aktivasi fase '.strtoupper($target).' terpenuhi.');

            return self::SUCCESS;
        }

        $this->error('❌ KEPUTUSAN: NO-GO — Selesaikan seluruh pemeriksaan yang gagal sebelum mengaktifkan fase '.strtoupper($target).'.');

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function requiredTablesFor(string $target): array
    {
        $tkaTables = [
            (new StudentAssessment)->getTable(),
        ];

        if ($target === 'tka') {
            return $tkaTables;
        }

        return array_values(array_unique(array_merge($tkaTables, [
            (new CbtTestSession)->getTable(),
            (new CbtResult)->getTable(),
            (new TryoutSession)->getTable(),
            (new TryoutResult)->getTable(),
            (new ExamSession)->getTable(),
            (new ExamAttempt)->getTable(),
            (new ExamAnswer)->getTable(),
            (new ExamResult)->getTable(),
        ])));
    }

    /**
     * @return list<string>
     */
    private function checkFeatureFlags(string $target): array
    {
        $issues = [];

        if (config('simantap.cbt_buffer_async', false) && config('cache.default') !== 'redis') {
            $issues[] = 'SIMANTAP_CBT_BUFFER_ASYNC=true membutuhkan CACHE_STORE=redis (saat ini: '.config('cache.default').')';
        }

        if ($target === 'readers') {
            if (! config('simantap.exam_engine_cbt', true) || ! config('simantap.exam_engine_tryout', true)) {
                $issues[] = 'SIMANTAP_EXAM_ENGINE_CBT dan SIMANTAP_EXAM_ENGINE_TRYOUT harus true sebelum cutover readers';
            }
        }

        if (config('queue.default') === 'database') {
            $retryAfter = (int) config('queue.connections.database.retry_after', 90);
            if ($retryAfter < 360) {
                $issues[] = "DB_QUEUE_RETRY_AFTER ({$retryAfter}s) terlalu rendah untuk beban ujian/ekspor (minimal 360s, disarankan 600s)";
            }
        }

        return $issues;
    }
}
