<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Memasang header Content-Security-Policy-Report-Only pada respons HTML web
 * saat `config('security.csp.enabled')` aktif. Tidak pernah memasang header
 * enforce `Content-Security-Policy`.
 */
class CspReportOnlyHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('security.csp.enabled', false)) {
            return $response;
        }

        // Lewati respons biner/unduhan/gambar/JSON — CSP hanya relevan untuk dokumen HTML
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml+xml')) {
            return $response;
        }

        $policy = $this->buildPolicy();
        if ($policy !== '') {
            $response->headers->set('Content-Security-Policy-Report-Only', $policy);
        }

        return $response;
    }

    private function buildPolicy(): string
    {
        $directives = (array) config('security.csp.directives', []);

        // Di lingkungan lokal saat Vite dev server aktif (public/hot ada), izinkan origin Vite
        if (app()->environment('local') && file_exists(public_path('hot'))) {
            $viteHttp = ['http://localhost:5173', 'http://127.0.0.1:5173'];
            $viteWs = ['ws://localhost:5173', 'ws://127.0.0.1:5173'];

            $directives['script-src'] = array_values(array_unique(array_merge((array) ($directives['script-src'] ?? []), $viteHttp)));
            $directives['style-src'] = array_values(array_unique(array_merge((array) ($directives['style-src'] ?? []), $viteHttp)));
            $directives['connect-src'] = array_values(array_unique(array_merge((array) ($directives['connect-src'] ?? []), $viteHttp, $viteWs)));
        }

        $parts = [];
        foreach ($directives as $name => $sources) {
            $list = array_values(array_filter(array_map('trim', (array) $sources), fn (string $v): bool => $v !== ''));
            if ($list !== []) {
                $parts[] = $name.' '.implode(' ', $list);
            }
        }

        $reportUri = trim((string) config('security.csp.report_uri', '/csp-report'));
        if ($reportUri !== '') {
            $parts[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $parts);
    }
}
