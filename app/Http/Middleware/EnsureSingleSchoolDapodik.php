<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

/** Melindungi integrasi lama yang konfigurasi dan data PTK-nya masih global. */
class EnsureSingleSchoolDapodik
{
    public function handle(Request $request, Closure $next)
    {
        if (TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
            $message = 'Integrasi Dapodik lama dinonaktifkan untuk multi-sekolah sampai konfigurasi dan data PTK terisolasi per sekolah.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 503)
                : response($message, 503);
        }

        return $next($request);
    }
}
