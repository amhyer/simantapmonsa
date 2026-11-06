<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pengidentifikasi sekolah (tenant) aktif.
 *
 * Urutan resolusi:
 * 1. Header eksplisit `X-Tenant-Subdomain` atau subdomain host (saat mode SaaS aktif).
 * 2. Kolom `tenant_id` pada pengguna yang sedang login (`$request->user()->tenant_id`).
 *
 * Jika keduanya ada namun berbeda, akses ditolak (HTTP 403) untuk mencegah
 * akun Sekolah A mengakses endpoint di bawah konteks Sekolah B.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $previousTenantId = TenantContext::id();
        $resolvedTenantId = $previousTenantId;

        $requestedSubdomain = $this->extractSubdomain($request);
        if ($requestedSubdomain !== null) {
            $tenantInfo = $this->resolveBySubdomain($requestedSubdomain);
            if ($tenantInfo === null || ! $tenantInfo['aktif']) {
                abort(404, 'Sekolah (tenant) tidak ditemukan atau sedang dinonaktifkan.');
            }
            $resolvedTenantId = $tenantInfo['id'];
        }

        $user = $request->user();
        $userTenantId = ($user && $user->tenant_id !== null) ? (int) $user->tenant_id : null;

        // Super Admin dan rute portal /superadmin tidak dibatasi oleh tenant tunggal
        if ($request->is('superadmin*') || ($user && in_array($user->peran, ['super_admin'], true) && $requestedSubdomain === null)) {
            $resolvedTenantId = null;
        } elseif ($userTenantId !== null) {
            if ($resolvedTenantId !== null && $resolvedTenantId !== $userTenantId) {
                abort(403, 'Akses lintas sekolah (cross-tenant) tidak diizinkan.');
            }
            $resolvedTenantId = $userTenantId;
        }

        TenantContext::set($resolvedTenantId);

        try {
            return $next($request);
        } finally {
            TenantContext::set($previousTenantId);
        }
    }

    private function extractSubdomain(Request $request): ?string
    {
        $header = trim((string) $request->header('X-Tenant-Subdomain', ''));
        if ($header !== '') {
            return strtolower($header);
        }

        if (! config('simantap.multi_tenant_enabled', false)) {
            return null;
        }

        $host = strtolower($request->getHost());
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) >= 3 && ! in_array($parts[0], ['www', 'app'], true)) {
            return $parts[0];
        }

        return null;
    }

    /**
     * @return array{id: int, aktif: bool}|null
     */
    private function resolveBySubdomain(string $subdomain): ?array
    {
        // ATURAN PROYEK: Cache HANYA menyimpan array skalar, bukan Model Eloquent.
        return Cache::remember("tenant:subdomain:{$subdomain}", 60, function () use ($subdomain): ?array {
            $row = Tenant::query()
                ->where('subdomain', $subdomain)
                ->first(['id', 'aktif']);

            if (! $row) {
                return null;
            }

            return [
                'id' => (int) $row->id,
                'aktif' => (bool) $row->aktif,
            ];
        });
    }
}
