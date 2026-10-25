<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk setiap respons.
 *
 * Dipasang global di bootstrap/app.php. Nilai nilainya berasal dari
 * config/security.php agar bisa disesuaikan lewat .env tanpa menyentuh kode.
 *
 * Catatan penting: Content-Security-Policy SENGAJA tidak dipasang secara
 * default — lihat penjelasan di config/security.php.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('security.enabled', true)) {
            return $response;
        }

        $headers = $response->headers;

        // Cegah browser "menebak" tipe konten (MIME sniffing → XSS).
        $headers->set('X-Content-Type-Options', 'nosniff');

        // Blokir clickjacking.
        if ($frame = config('security.frame_options')) {
            $headers->set('X-Frame-Options', $frame);
        }

        if ($referrer = config('security.referrer_policy')) {
            $headers->set('Referrer-Policy', $referrer);
        }

        if ($permissions = config('security.permissions_policy')) {
            $headers->set('Permissions-Policy', $permissions);
        }

        // HSTS hanya berarti (dan hanya boleh dikirim) lewat HTTPS.
        $maxAge = (int) config('security.hsts_max_age', 0);
        if ($maxAge > 0 && $request->secure()) {
            $headers->set('Strict-Transport-Security', "max-age={$maxAge}");
        }

        // Opsional, kosong secara default — lihat config/security.php.
        if ($csp = config('security.content_security_policy')) {
            $headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
