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

        // Only check authenticated users (and skip when Admin/Super Admin is impersonating).
        // Keduanya harus dicek: alur admin->guru memakai `admin_impersonator_id`,
        // alur super_admin->admin memakai `impersonator_id` (TenantController).
        if (! $user || $request->session()->has('admin_impersonator_id') || $request->session()->has('impersonator_id')) {
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
