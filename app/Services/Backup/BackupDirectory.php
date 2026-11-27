<?php

namespace App\Services\Backup;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Resolusi direktori penyimpanan backup agar setiap sekolah (tenant) memiliki
 * folder cadangan terisolasi (`backups/tenant-{id}`), sementara instalasi
 * single-school tetap memakai folder root (`backups`).
 */
class BackupDirectory
{
    public static function diskName(): string
    {
        return (string) config('backup.disk', 'local');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function base(): string
    {
        return trim((string) config('backup.direktori', 'backups'), '/');
    }

    public static function forTenant(?int $tenantId = null): string
    {
        $base = self::base();

        if ($tenantId !== null) {
            return $base.'/tenant-'.$tenantId;
        }

        return $base;
    }
}
