<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

/** Fitur yang membaca/mengubah data global tidak aman untuk admin tenant. */
class EnsureSingleSchoolData
{
    public function handle(Request $request, Closure $next)
    {
        if (TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
            $message = 'Fitur data global ini dinonaktifkan untuk admin sekolah sampai tersedia isolasi per tenant.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 503)
                : response($message, 503);
        }

        return $next($request);
    }
}
