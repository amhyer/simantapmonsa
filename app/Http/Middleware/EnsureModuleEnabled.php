<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Kill switch modul asesmen (CBT / Tryout / TKA).
 *
 * Selama `config('simantap.cbt_enabled')` bernilai false, seluruh route modul
 * asesmen diblokir dan membalas HTTP 404 (bukan 500). Route tetap terdaftar
 * agar pemanggilan `route()` di view tidak melempar RouteNotFoundException.
 *
 * Lihat docs/RENCANA_PERBAIKAN_VERIFIED.md (Fase 0.5).
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        // Admin super selalu memiliki akses ke pengelolaan modul asesmen & CBT/TKA
        if (auth()->check() && auth()->user()->peran === 'admin') {
            return $next($request);
        }

        if (!config('simantap.cbt_enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
