<?php

namespace App\Services\Dapodik;

use App\Models\DapodikSyncLog;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class SidebarSyncStatus
{
    /** @return array{created_at: ?string, berhasil: int, gagal: int}|null */
    public static function latest(): ?array
    {
        $tenantId = TenantContext::id();
        if ($tenantId === null && (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists())) {
            return null;
        }

        $cacheKey = $tenantId === null
            ? 'dapodik-menu-sync:v2:single'
            : 'dapodik-menu-sync:v2:tenant:'.$tenantId;

        return Cache::remember($cacheKey, 120, function (): ?array {
            $last = DapodikSyncLog::query()->orderByDesc('created_at')->orderByDesc('id')->first();

            return $last ? [
                'created_at' => $last->created_at?->toIso8601String(),
                'berhasil' => (int) $last->berhasil,
                'gagal' => (int) $last->gagal,
            ] : null;
        });
    }
}
