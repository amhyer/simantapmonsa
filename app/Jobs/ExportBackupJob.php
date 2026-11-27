<?php

namespace App\Jobs;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Backup\BackupDirectory;
use App\Services\Backup\BackupStreamWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExportBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public ?int $tenantId = null;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Tulis backup registry secara bertahap ke temporary stream, lalu unggah
     * sebagai resource agar JSON utuh tidak pernah dimaterialisasi di memori.
     */
    public function handle(BackupStreamWriter $writer): void
    {
        $tenantId = $this->tenantId;

        if ($tenantId === null && (TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant())) {
            throw new RuntimeException('Job backup global tidak boleh dijalankan oleh admin sekolah dalam instalasi multi-tenant.');
        }

        set_time_limit(0);

        $prefix = $tenantId !== null ? 'backup-simantap-sekolah-'.$tenantId.'-' : 'backup-simantap-';
        $filename = $prefix.now()->format('Y-m-d-His').'.json';
        $directory = BackupDirectory::forTenant($tenantId);
        $path = $directory.'/'.$filename;
        $diskName = BackupDirectory::diskName();
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Gagal membuka temporary stream untuk backup.');
        }

        try {
            $summary = $writer->writeTo($stream, $tenantId);
            rewind($stream);

            if (! Storage::disk($diskName)->put($path, $stream)) {
                throw new RuntimeException("Gagal menyimpan backup ke disk '{$diskName}'.");
            }

            Log::info('Backup exported successfully.', [
                'tenant_id' => $tenantId,
                'filename' => $filename,
                'tables' => $summary['tables'],
                'rows' => $summary['rows'],
            ]);
        } finally {
            fclose($stream);
        }
    }
}
