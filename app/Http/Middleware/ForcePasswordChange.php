<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Only check authenticated users
        if (!$user) {
            return $next($request);
        }

        if ($user->force_password_change) {
            // Allow access to password change routes and logout
            if ($request->routeIs('force-password-change.*') || $request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('force-password-change.show')
                ->with('warning', 'Anda harus mengubah password terlebih dahulu.');
        }

        return $next($request);
    }
}
