<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureMfaSetup
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('simantap.mfa_enabled', true)) {
            return $next($request);
        }

        $user = Auth::user();

        // Lewati bila belum login atau sedang dalam sesi impersonasi sah.
        if (! $user || $request->session()->has('admin_impersonator_id') || $request->session()->has('impersonator_id')) {
            return $next($request);
        }

        if ($user->isMfaEnforced() && ! $user->hasMfaEnabled()) {
            if ($request->routeIs('account.mfa.*', 'force-password-change.*', 'logout')) {
                return $next($request);
            }

            return redirect()->route('account.mfa.show')
                ->with('warning', 'Akun dengan peran Anda wajib mengaktifkan Autentikasi Dua Faktor (MFA) terlebih dahulu.');
        }

        return $next($request);
    }
}
