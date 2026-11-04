<?php

namespace App\Modules\Tenant\Contexts;

/**
 * Konteks tenant (sekolah) aktif pada siklus request/job saat ini.
 *
 * Menyimpan ID skalar integer di container Laravel (`current_tenant_id`)
 * sehingga tidak pernah meng-cache atau menahan objek Eloquent Model.
 */
class TenantContext
{
    public const CONTAINER_KEY = 'current_tenant_id';

    public static function set(?int $tenantId): void
    {
        if ($tenantId === null) {
            self::clear();

            return;
        }

        app()->instance(self::CONTAINER_KEY, $tenantId);
    }

    public static function id(): ?int
    {
        if (! app()->bound(self::CONTAINER_KEY)) {
            return null;
        }

        $value = app(self::CONTAINER_KEY);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function clear(): void
    {
        app()->forgetInstance(self::CONTAINER_KEY);
    }

    /**
     * Jalankan callback di dalam konteks tenant tertentu lalu pulihkan konteks sebelumnya.
     */
    public static function run(?int $tenantId, callable $callback): mixed
    {
        $previous = self::id();
        self::set($tenantId);

        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }
}
