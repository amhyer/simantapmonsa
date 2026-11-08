<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Support\TenantMode;
use Closure;
use Illuminate\Http\Request;

/** Melindungi integrasi lama yang konfigurasi dan data PTK-nya masih global. */
class EnsureSingleSchoolDapodik
{
    public function handle(Request $request, Closure $next)
    {
        if (TenantMode::isMultiSchool()) {
            $message = 'Integrasi Dapodik lama dinonaktifkan untuk multi-sekolah sampai konfigurasi dan data PTK terisolasi per sekolah.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 503)
                : response($message, 503);
        }

        return $next($request);
    }
}
