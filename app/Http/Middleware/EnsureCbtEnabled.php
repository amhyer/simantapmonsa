<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengunci seluruh route manajemen CBT (/admin/cbt/*) ketika fitur CBT
 * belum diaktifkan via config('simantap.cbt_enabled').
 *
 * Berbeda dengan EnsureModuleEnabled yang meloloskan admin, middleware ini
 * memblokir SEMUA peran (termasuk admin dan super_admin) dengan HTTP 404.
 */
class EnsureCbtEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('simantap.cbt_enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
