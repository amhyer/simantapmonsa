<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CbtAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->aktif && !$request->user()->archived_at, 403);
        if ($request->user()->force_password_change) {
            return redirect()->route('force-password-change.show');
        }
        return $next($request);
    }
}
