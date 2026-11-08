<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Agregasi kunjungan halaman sukses tanpa menyimpan identitas atau detail request. */
class RecordMenuUsage
{
    public function handle(Request $request, \Closure $next)
    {
        $response = $next($request);

        if (! config('simantap.menu_usage_telemetry', true)
            || ! $request->isMethod('GET')
            || $request->expectsJson()
            || $response->getStatusCode() !== 200) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        $user = $request->user();
        $role = $user?->peran;
        if (! is_string($routeName) || $routeName === '' || ! is_string($role) || $role === '' || strlen($routeName) > 120) {
            return $response;
        }

        $tenantId = TenantContext::id() ?? (is_numeric($user->tenant_id ?? null) ? (int) $user->tenant_id : null);
        $scopeKey = $tenantId === null ? 'single' : 'tenant:'.$tenantId;
        $now = now();

        try {
            // A savepoint keeps a telemetry write failure from poisoning a surrounding PostgreSQL test/request transaction.
            DB::transaction(function () use ($now, $scopeKey, $tenantId, $role, $routeName): void {
                DB::table('menu_usage_daily')->upsert(
                    [[
                        'visit_date' => $now->toDateString(),
                        'scope_key' => $scopeKey,
                        'tenant_id' => $tenantId,
                        'role' => substr($role, 0, 24),
                        'route_name' => $routeName,
                        'visits' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['visit_date', 'scope_key', 'role', 'route_name'],
                    [
                        'visits' => DB::raw('menu_usage_daily.visits + 1'),
                        'updated_at' => $now,
                    ]
                );
            });
        } catch (Throwable $exception) {
            // Telemetry tidak boleh menggagalkan halaman yang sedang dibuka.
            Log::warning('Pencatatan penggunaan menu gagal.', [
                'route_name' => $routeName,
                'exception' => $exception::class,
            ]);
        }

        return $response;
    }
}
