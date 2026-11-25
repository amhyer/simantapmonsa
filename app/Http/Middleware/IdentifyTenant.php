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

        $tenantInfo = null;
        $customDomain = $this->extractCustomDomain($request);
        if ($customDomain !== null) {
            $tenantInfo = $this->resolveByCustomDomain($customDomain);
        }

        $requestedSubdomain = null;
        if ($tenantInfo === null) {
            $requestedSubdomain = $this->extractSubdomain($request);
            if ($requestedSubdomain !== null) {
                $tenantInfo = $this->resolveBySubdomain($requestedSubdomain);
            }
        }

        $isLogoutRequest = $request->is('logout') || $request->routeIs('logout');

        if ($tenantInfo !== null) {
            if (! $isLogoutRequest) {
                if (! $tenantInfo['aktif']) {
                    abort(404, 'Sekolah (tenant) tidak ditemukan atau sedang dinonaktifkan.');
                }

                if (! empty($tenantInfo['tgl_kedaluwarsa']) && strtotime($tenantInfo['tgl_kedaluwarsa']) < strtotime('today')) {
                    abort(402, 'Masa aktif langganan sekolah telah berakhir. Silakan hubungi Super Administrator untuk perpanjangan layanan.');
                }
            }

            $resolvedTenantId = $tenantInfo['id'];
        } elseif (($requestedSubdomain !== null || $customDomain !== null) && ! $isLogoutRequest) {
            abort(404, 'Sekolah (tenant) tidak ditemukan atau sedang dinonaktifkan.');
        }

        $user = $request->user();
        $userTenantId = ($user && $user->tenant_id !== null) ? (int) $user->tenant_id : null;
        $allowedPengawasIds = ($user && $user->peran === 'pengawas')
            ? $this->allowedPengawasTenantIds($user)
            : [];

        if ($user && $user->peran === 'pengawas' && $request->hasSession()) {
            $activeBinaanId = $request->session()->get('pengawas_active_tenant_id');
            if ($activeBinaanId !== null && is_numeric($activeBinaanId) && in_array((int) $activeBinaanId, $allowedPengawasIds, true)) {
                $userTenantId = (int) $activeBinaanId;
            }
        }

        // Super Admin dan rute portal /superadmin tidak dibatasi oleh tenant tunggal
        if ($request->is('superadmin*') || ($user && in_array($user->peran, ['super_admin'], true) && $requestedSubdomain === null && $customDomain === null)) {
            $resolvedTenantId = null;
        } elseif ($userTenantId !== null) {
            if ($resolvedTenantId !== null && $resolvedTenantId !== $userTenantId) {
                if ($user && $user->peran === 'pengawas' && in_array((int) $resolvedTenantId, $allowedPengawasIds, true)) {
                    $userTenantId = (int) $resolvedTenantId;
                } else {
                    abort(403, 'Akses lintas sekolah (cross-tenant) tidak diizinkan.');
                }
            }
            $resolvedTenantId = $userTenantId;

            // Periksa kedaluwarsa langganan akun pengguna / sekolah binaan aktif (kecuali saat proses logout)
            if (! $isLogoutRequest) {
                $userTenantInfo = $this->resolveById($userTenantId);
                if (! $userTenantInfo || ! $userTenantInfo['aktif']) {
                    abort(403, 'Sekolah akun Anda tidak aktif atau tidak ditemukan.');
                }
                if (! empty($userTenantInfo['tgl_kedaluwarsa']) && strtotime($userTenantInfo['tgl_kedaluwarsa']) < strtotime('today')) {
                    abort(402, 'Masa aktif langganan sekolah akun Anda telah berakhir. Silakan hubungi Super Administrator.');
                }
            }
        } elseif ($user && $resolvedTenantId !== null) {
            abort(403, 'Akun belum terhubung ke sekolah tersebut.');
        }

        TenantContext::set($resolvedTenantId);

        try {
            return $next($request);
        } finally {
            TenantContext::set($previousTenantId);
        }
    }

    /**
     * @return array<int, int>
     */
    private function allowedPengawasTenantIds(object $pengawas): array
    {
        $ids = [];
        if (isset($pengawas->tenant_id) && $pengawas->tenant_id !== null) {
            $ids[] = (int) $pengawas->tenant_id;
        }
        if (isset($pengawas->terhubung_dengan) && is_array($pengawas->terhubung_dengan)) {
            foreach ($pengawas->terhubung_dengan as $id) {
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizedAppHost(): string
    {
        $raw = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));

        return preg_replace('/^(www|app)\./', '', $raw) ?: '';
    }

    private function isCentralOrLocalHost(string $host, string $appHost): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        if ($appHost !== '' && ($host === $appHost || $host === 'www.'.$appHost || $host === 'app.'.$appHost)) {
            return true;
        }

        return false;
    }

    private function extractCustomDomain(Request $request): ?string
    {
        $header = trim((string) $request->header('X-Tenant-Domain', ''));
        if ($header !== '') {
            return strtolower($header);
        }

        if (trim((string) $request->header('X-Tenant-Subdomain', '')) !== '') {
            return null;
        }

        if (! config('simantap.multi_tenant_enabled', false)) {
            return null;
        }

        $host = strtolower($request->getHost());
        $appHost = $this->normalizedAppHost();

        if ($this->isCentralOrLocalHost($host, $appHost)) {
            return null;
        }

        if ($appHost !== '' && ! in_array($appHost, ['localhost', '127.0.0.1', '::1'], true)) {
            if (str_ends_with($host, '.'.$appHost)) {
                return null;
            }

            return $host;
        }

        return null;
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
        $appHost = $this->normalizedAppHost();

        if ($this->isCentralOrLocalHost($host, $appHost)) {
            return null;
        }

        if ($appHost !== '' && ! in_array($appHost, ['localhost', '127.0.0.1', '::1'], true)) {
            $suffix = '.'.$appHost;
            if (str_ends_with($host, $suffix)) {
                $sub = substr($host, 0, -strlen($suffix));
                if ($sub !== '' && ! str_contains($sub, '.') && ! in_array($sub, ['www', 'app'], true)) {
                    return $sub;
                }
            }

            return null;
        }

        $parts = explode('.', $host);
        $twoLevelTlds = ['sch.id', 'go.id', 'ac.id', 'or.id', 'co.id', 'my.id', 'web.id', 'desa.id', 'ponpes.id'];
        $minParts = 3;
        foreach ($twoLevelTlds as $tld) {
            if ($host === $tld || str_ends_with($host, '.'.$tld)) {
                $minParts = 4;
                break;
            }
        }

        if (count($parts) >= $minParts && ! in_array($parts[0], ['www', 'app'], true)) {
            return $parts[0];
        }

        return null;
    }

    /**
     * @return array{id: int, aktif: bool, tgl_kedaluwarsa: ?string, paket: string, kuota_siswa: int}|null
     */
    private function resolveBySubdomain(string $subdomain): ?array
    {
        // ATURAN PROYEK: Cache HANYA menyimpan array skalar, bukan Model Eloquent.
        return Cache::remember("tenant:subdomain:{$subdomain}", 60, function () use ($subdomain): ?array {
            $row = Tenant::query()
                ->where('subdomain', $subdomain)
                ->first(['id', 'aktif', 'tgl_kedaluwarsa', 'paket', 'kuota_siswa']);

            if (! $row) {
                return null;
            }

            return [
                'id' => (int) $row->id,
                'aktif' => (bool) $row->aktif,
                'tgl_kedaluwarsa' => $row->tgl_kedaluwarsa ? (is_string($row->tgl_kedaluwarsa) ? $row->tgl_kedaluwarsa : $row->tgl_kedaluwarsa->toDateString()) : null,
                'paket' => (string) ($row->paket ?? 'standar'),
                'kuota_siswa' => (int) ($row->kuota_siswa ?? 500),
            ];
        });
    }

    /**
     * @return array{id: int, aktif: bool, tgl_kedaluwarsa: ?string, paket: string, kuota_siswa: int}|null
     */
    private function resolveByCustomDomain(string $domain): ?array
    {
        return Cache::remember("tenant:domain:{$domain}", 60, function () use ($domain): ?array {
            $row = Tenant::query()
                ->where('domain_kustom', $domain)
                ->first(['id', 'aktif', 'tgl_kedaluwarsa', 'paket', 'kuota_siswa']);

            if (! $row) {
                return null;
            }

            return [
                'id' => (int) $row->id,
                'aktif' => (bool) $row->aktif,
                'tgl_kedaluwarsa' => $row->tgl_kedaluwarsa ? (is_string($row->tgl_kedaluwarsa) ? $row->tgl_kedaluwarsa : $row->tgl_kedaluwarsa->toDateString()) : null,
                'paket' => (string) ($row->paket ?? 'standar'),
                'kuota_siswa' => (int) ($row->kuota_siswa ?? 500),
            ];
        });
    }

    /**
     * @return array{id: int, aktif: bool, tgl_kedaluwarsa: ?string, paket: string, kuota_siswa: int}|null
     */
    private function resolveById(int $id): ?array
    {
        // Status aktif tidak boleh tertunda oleh cache ketika sekolah dinonaktifkan.
        $row = Tenant::query()->whereKey($id)
            ->first(['id', 'aktif', 'tgl_kedaluwarsa', 'paket', 'kuota_siswa']);
        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'aktif' => (bool) $row->aktif,
            'tgl_kedaluwarsa' => $row->tgl_kedaluwarsa ? (is_string($row->tgl_kedaluwarsa) ? $row->tgl_kedaluwarsa : $row->tgl_kedaluwarsa->toDateString()) : null,
            'paket' => (string) ($row->paket ?? 'standar'),
            'kuota_siswa' => (int) ($row->kuota_siswa ?? 500),
        ];
    }
}
