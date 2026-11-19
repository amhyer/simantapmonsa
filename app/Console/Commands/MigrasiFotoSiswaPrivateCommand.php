<?php

namespace App\Console\Commands;

use App\Http\Controllers\Media\SiswaFotoController;
use App\Models\Siswa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrasiFotoSiswaPrivateCommand extends Command
{
    protected $signature = 'siswa:migrasi-foto-private
        {--apply : Salin berkas dari disk public ke disk local (private) dengan verifikasi SHA-256}
        {--delete-public : Hapus salinan di disk public untuk berkas yang terverifikasi identik (wajib --apply dan --yes-backup-done)}
        {--yes-backup-done : Konfirmasi bahwa backup storage/app/public/foto-siswa dan storage/app/private sudah dilakukan}
        {--tenant= : Batasi migrasi pada satu tenant_id}
        {--report= : Tulis laporan JSON ID siswa per kategori ke path di luar repositori}';

    protected $description = 'Migrasi foto siswa dari disk public ke disk local (private) secara aman (default dry-run).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $deletePublic = (bool) $this->option('delete-public');
        $yesBackup = (bool) $this->option('yes-backup-done');
        $tenantOpt = $this->option('tenant');
        $reportPath = $this->option('report');

        if ($deletePublic && (! $apply || ! $yesBackup)) {
            $this->error('Opsi --delete-public hanya diizinkan bersama --apply DAN --yes-backup-done.');

            return self::FAILURE;
        }

        if ($reportPath !== null && trim((string) $reportPath) !== '') {
            $resolvedReport = $this->resolveSafeReportPath((string) $reportPath);
            if ($resolvedReport === null) {
                $this->error('Path --report harus berada di luar direktori repositori.');

                return self::FAILURE;
            }
            $reportPath = $resolvedReport;
        } else {
            $reportPath = null;
        }

        $startedAt = microtime(true);

        $categories = [
            'hanya_public' => [],
            'ada_di_keduanya_identik' => [],
            'ada_di_keduanya_berbeda' => [],
            'hanya_private' => [],
            'hilang' => [],
            'path_tidak_valid' => [],
        ];
        $stats = [
            'disalin_terverifikasi' => 0,
            'gagal_verifikasi' => 0,
            'dihapus_public' => 0,
        ];
        $referencedPaths = [];

        $query = Siswa::withoutGlobalScopes()
            ->whereNotNull('foto')
            ->where('foto', '!=', '')
            ->orderBy('id');

        if ($tenantOpt !== null && trim((string) $tenantOpt) !== '') {
            $query->where('tenant_id', (int) $tenantOpt);
        }

        $query->chunkById(200, function ($students) use (
            $apply,
            $deletePublic,
            &$categories,
            &$stats,
            &$referencedPaths
        ): void {
            foreach ($students as $siswa) {
                $id = (int) $siswa->id;
                $path = trim((string) ($siswa->foto ?? ''));

                if ($path === '' || preg_match(SiswaFotoController::PATH_REGEX, $path) !== 1) {
                    $categories['path_tidak_valid'][] = $id;

                    continue;
                }

                $referencedPaths[$path] = true;

                $inLocal = Storage::disk('local')->exists($path);
                $inPublic = Storage::disk('public')->exists($path);

                if ($inLocal && ! $inPublic) {
                    $categories['hanya_private'][] = $id;

                    continue;
                }

                if (! $inLocal && ! $inPublic) {
                    $categories['hilang'][] = $id;

                    continue;
                }

                if ($inLocal && $inPublic) {
                    $localHash = $this->hashDiskFile('local', $path);
                    $publicHash = $this->hashDiskFile('public', $path);

                    if ($localHash !== null && $publicHash !== null && hash_equals($localHash, $publicHash)) {
                        $categories['ada_di_keduanya_identik'][] = $id;
                        if ($apply && $deletePublic) {
                            Storage::disk('public')->delete($path);
                            $stats['dihapus_public']++;
                        }
                    } else {
                        // KONFLIK: jangan sentuh berkas mana pun
                        $categories['ada_di_keduanya_berbeda'][] = $id;
                    }

                    continue;
                }

                // Hanya ada di public
                $categories['hanya_public'][] = $id;
                if (! $apply) {
                    continue;
                }

                $sourceHash = $this->hashDiskFile('public', $path);
                if ($sourceHash === null) {
                    $stats['gagal_verifikasi']++;

                    continue;
                }

                $copied = $this->copyPublicToLocalStream($path);
                if (! $copied) {
                    $stats['gagal_verifikasi']++;

                    continue;
                }

                $destHash = $this->hashDiskFile('local', $path);
                if ($destHash === null || ! hash_equals($sourceHash, $destHash)) {
                    $stats['gagal_verifikasi']++;

                    continue;
                }

                $stats['disalin_terverifikasi']++;

                if ($deletePublic) {
                    Storage::disk('public')->delete($path);
                    $stats['dihapus_public']++;
                }
            }
        });

        // Report-only: berkas yatim di public/foto-siswa yang tidak dirujuk DB (jangan dihapus)
        $yatimPublic = 0;
        if (Storage::disk('public')->exists('foto-siswa')) {
            foreach (Storage::disk('public')->files('foto-siswa') as $publicFile) {
                if (! isset($referencedPaths[$publicFile])) {
                    $yatimPublic++;
                }
            }
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $mode = $apply ? ($deletePublic ? 'APPLY+DELETE-PUBLIC' : 'APPLY') : 'DRY-RUN';

        $counts = [
            'hanya_public' => count($categories['hanya_public']),
            'ada_di_keduanya_identik' => count($categories['ada_di_keduanya_identik']),
            'ada_di_keduanya_berbeda' => count($categories['ada_di_keduanya_berbeda']),
            'hanya_private' => count($categories['hanya_private']),
            'hilang' => count($categories['hilang']),
            'path_tidak_valid' => count($categories['path_tidak_valid']),
            'yatim_public' => $yatimPublic,
            'disalin_terverifikasi' => $stats['disalin_terverifikasi'],
            'gagal_verifikasi' => $stats['gagal_verifikasi'],
            'dihapus_public' => $stats['dihapus_public'],
        ];

        $this->info("Mode: {$mode} | Durasi: {$durationMs} ms");
        foreach ($counts as $label => $count) {
            $this->line("{$label}: {$count}");
        }

        if ($reportPath !== null) {
            $payload = [
                'mode' => $mode,
                'duration_ms' => $durationMs,
                'counts' => $counts,
                'student_ids_by_category' => $categories,
            ];
            $dir = dirname($reportPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            file_put_contents($reportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Laporan ID siswa per kategori ditulis ke berkas target.');
        }

        return $stats['gagal_verifikasi'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function copyPublicToLocalStream(string $path): bool
    {
        $stream = Storage::disk('public')->readStream($path);
        if (is_resource($stream)) {
            try {
                return (bool) Storage::disk('local')->writeStream($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        $bytes = Storage::disk('public')->get($path);
        if ($bytes === null) {
            return false;
        }

        return (bool) Storage::disk('local')->put($path, $bytes);
    }

    protected function hashDiskFile(string $disk, string $path): ?string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (is_resource($stream)) {
            try {
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);

                return hash_final($ctx);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        $bytes = Storage::disk($disk)->get($path);

        return is_string($bytes) ? hash('sha256', $bytes) : null;
    }

    private function resolveSafeReportPath(string $rawPath): ?string
    {
        $rawPath = trim($rawPath);
        if ($rawPath === '') {
            return null;
        }

        // Tolak segmen '..' eksplisit (path traversal)
        if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $rawPath) === 1) {
            return null;
        }

        $normalized = str_replace('\\', '/', $rawPath);

        // Tolak path relatif — wajib path absolut
        if (preg_match('/^([A-Za-z]:\/|\/)/', $normalized) !== 1) {
            return null;
        }

        $baseReal = realpath(base_path());
        if ($baseReal === false) {
            return null;
        }
        $base = str_replace('\\', '/', rtrim($baseReal, '/\\'));

        // Telusuri direktori induk yang ada lalu jalankan realpath() untuk mendeteksi symlink/junction
        $probe = $rawPath;
        $tail = [];
        while (! file_exists($probe)) {
            $tail[] = basename($probe);
            $parent = dirname($probe);
            if ($parent === $probe) {
                return null;
            }
            $probe = $parent;
        }

        $resolvedExisting = realpath($probe);
        if ($resolvedExisting === false) {
            return null;
        }

        $resolved = str_replace('\\', '/', rtrim($resolvedExisting, '/\\'));
        if ($tail !== []) {
            $resolved .= '/'.implode('/', array_reverse($tail));
        }

        if (stripos($resolved, $base.'/') === 0 || strcasecmp($resolved, $base) === 0) {
            return null;
        }

        return $rawPath;
    }
}
