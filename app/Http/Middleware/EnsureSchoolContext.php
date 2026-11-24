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
        if ($user->peran === 'pengawas') {
            $allowedIds = [];
            if ($user->tenant_id !== null) {
                $allowedIds[] = (int) $user->tenant_id;
            }
            if (is_array($user->terhubung_dengan)) {
                foreach ($user->terhubung_dengan as $id) {
                    if (is_numeric($id)) {
                        $allowedIds[] = (int) $id;
                    }
                }
            }
            $allowedIds = array_values(array_unique($allowedIds));
            if ($allowedIds !== []) {
                abort_unless(TenantContext::id() !== null && in_array((int) TenantContext::id(), $allowedIds, true), 403);
            } elseif ($user->tenant_owned || TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()) {
                abort(403, 'Akun belum terhubung ke sekolah aktif.');
            }
        } elseif ($user->tenant_id !== null) {
            abort_unless(TenantContext::id() === (int) $user->tenant_id, 403);
        } elseif ($user->tenant_owned || TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()) {
            abort(403, 'Akun belum terhubung ke sekolah aktif.');
        }

        return $next($request);
    }
}
