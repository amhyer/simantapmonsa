<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

/** Jangan melayani data sekolah tanpa identitas sekolah yang tepercaya. */
class EnsureSchoolContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        abort_unless($user->aktif && $user->archived_at === null, 403, 'Akun tidak aktif.');
        if ($user->tenant_id !== null) {
            abort_unless(TenantContext::id() === (int) $user->tenant_id, 403);
        } elseif ($user->tenant_owned || TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
            abort(403, 'Akun belum terhubung ke sekolah aktif.');
        }

        return $next($request);
    }
}
