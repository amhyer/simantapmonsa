<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteksi Host Header Injection untuk instalasi single-school maupun multi-tenant.
 *
 * Memvalidasi `$request->getHost()` terhadap:
 *  1. Host utama `APP_URL` beserta subdomainnya (`*.appHost`).
 *  2. Host tambahan pada `SIMANTAP_TRUSTED_HOSTS` (exact atau wildcard `*.domain`).
 *  3. `tenants.domain_kustom` aktif pada mode multi-tenant (di-cache sebagai array string).
 *  4. Host lokal (`localhost`, `127.0.0.1`, `::1`, `*.localhost`, `*.test`) khusus lingkungan non-produksi.
 */
class TrustHosts
{
    public const CUSTOM_DOMAINS_CACHE_KEY = 'security:trusted_custom_domains';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.trust_hosts_enabled', true)) {
            return $next($request);
        }

        $host = strtolower(trim($request->getHost()));
        if ($host === '' || ! $this->isTrustedHost($host)) {
            Log::warning('Permintaan dengan Host header tidak tepercaya ditolak.', [
                'host' => $host,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Host tidak diizinkan.',
                ], 400);
            }

            abort(400, 'Host tidak diizinkan.');
        }

        return $next($request);
    }

    private function isTrustedHost(string $host): bool
    {
        // 1. Host utama APP_URL dan seluruh subdomainnya
        $appHost = $this->normalizedAppHost();
        if ($appHost !== '') {
            if ($host === $appHost || $host === 'www.'.$appHost || str_ends_with($host, '.'.$appHost)) {
                return true;
            }
        }

        // 2. Host lokal pada lingkungan pengembangan & pengujian otomatis
        if (! app()->isProduction()) {
            if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                || str_ends_with($host, '.localhost')
                || str_ends_with($host, '.test')) {
                return true;
            }
        }

        // 3. Daftar host tambahan dari SIMANTAP_TRUSTED_HOSTS (dipisah koma)
        $configured = trim((string) config('security.trusted_hosts', ''));
        if ($configured !== '') {
            foreach (explode(',', $configured) as $pattern) {
                $pattern = strtolower(trim($pattern));
                if ($pattern === '') {
                    continue;
                }
                if ($pattern === $host) {
                    return true;
                }
                if (str_starts_with($pattern, '*.')) {
                    $suffix = substr($pattern, 1); // ".domain.tld"
                    if ($suffix !== '' && str_ends_with($host, $suffix) && strlen($host) > strlen($suffix)) {
                        return true;
                    }
                }
            }
        }

        // 4. Domain kustom tenant yang terdaftar dan aktif
        if (in_array($host, $this->activeCustomDomains(), true)) {
            return true;
        }

        return false;
    }

    private function normalizedAppHost(): string
    {
        $raw = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));

        return preg_replace('/^www\./', '', $raw) ?: '';
    }

    /**
     * @return list<string>
     */
    private function activeCustomDomains(): array
    {
        try {
            // Hanya menyimpan array string skalar (bukan model Eloquent).
            return Cache::remember(self::CUSTOM_DOMAINS_CACHE_KEY, 60, function (): array {
                if (! Schema::hasTable('tenants')) {
                    return [];
                }

                return Tenant::query()
                    ->where('aktif', true)
                    ->whereNotNull('domain_kustom')
                    ->where('domain_kustom', '!=', '')
                    ->pluck('domain_kustom')
                    ->map(fn ($domain) => strtolower(trim((string) $domain)))
                    ->filter(fn (string $domain) => $domain !== '')
                    ->unique()
                    ->values()
                    ->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }
}
