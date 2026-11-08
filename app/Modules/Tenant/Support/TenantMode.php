<?php

namespace App\Modules\Tenant\Support;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;

/** Predicate tunggal agar navigasi, UI, dan middleware menyamakan mode multi-sekolah. */
class TenantMode
{
    public static function isMultiSchool(): bool
    {
        return TenantContext::check()
            || (bool) config('simantap.multi_tenant_enabled', false)
            || Tenant::query()->exists();
    }
}
