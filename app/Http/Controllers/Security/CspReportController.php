<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CspReportController extends Controller
{
    public const MAX_BODY_BYTES = 10240;

    private const ALLOWED_CONTENT_TYPES = [
        'application/csp-report',
        'application/reports+json',
    ];

    public function store(Request $request): Response
    {
        $rawContentType = strtolower(trim(explode(';', (string) $request->header('Content-Type', ''))[0]));
        if (! in_array($rawContentType, self::ALLOWED_CONTENT_TYPES, true)) {
            return response()->noContent(415);
        }

        $rawBody = (string) $request->getContent();
        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            return response()->noContent(413);
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            return response()->noContent(400);
        }

        $reports = $this->extractReports($decoded, $rawContentType);
        foreach ($reports as $entry) {
            Log::channel('csp')->warning('CSP violation report', $entry);
        }

        return response()->noContent(204);
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<int, array<string, string>>
     */
    private function extractReports(array $decoded, string $contentType): array
    {
        $items = [];

        if ($contentType === 'application/reports+json' && array_is_list($decoded)) {
            foreach (array_slice($decoded, 0, 20) as $report) {
                if (! is_array($report)) {
                    continue;
                }
                $body = is_array($report['body'] ?? null) ? $report['body'] : [];
                $items[] = $this->sanitizeEntry([
                    'violated-directive' => $body['violatedDirective'] ?? $body['effectiveDirective'] ?? '',
                    'effective-directive' => $body['effectiveDirective'] ?? '',
                    'blocked-uri' => $body['blockedURL'] ?? '',
                    'document-uri' => $body['documentURL'] ?? $report['url'] ?? '',
                    'disposition' => $body['disposition'] ?? 'report',
                ]);
            }

            return $items;
        }

        $body = is_array($decoded['csp-report'] ?? null) ? $decoded['csp-report'] : $decoded;
        $items[] = $this->sanitizeEntry([
            'violated-directive' => $body['violated-directive'] ?? '',
            'effective-directive' => $body['effective-directive'] ?? $body['violated-directive'] ?? '',
            'blocked-uri' => $body['blocked-uri'] ?? '',
            'document-uri' => $body['document-uri'] ?? '',
            'disposition' => $body['disposition'] ?? 'report',
        ]);

        return $items;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, string>
     */
    private function sanitizeEntry(array $entry): array
    {
        return [
            'violated-directive' => mb_substr(trim((string) ($entry['violated-directive'] ?? '')), 0, 120),
            'effective-directive' => mb_substr(trim((string) ($entry['effective-directive'] ?? '')), 0, 120),
            'blocked-uri' => $this->stripQueryAndFragment((string) ($entry['blocked-uri'] ?? '')),
            'document-uri' => $this->stripQueryAndFragment((string) ($entry['document-uri'] ?? '')),
            'disposition' => mb_substr(trim((string) ($entry['disposition'] ?? 'report')), 0, 32),
        ];
    }

    private function stripQueryAndFragment(string $uri): string
    {
        $clean = trim($uri);
        if ($clean === '') {
            return '';
        }

        // Pertahankan kata kunci CSP seperti 'inline', 'eval', 'data', 'blob'
        if (! str_contains($clean, '://') && ! str_starts_with($clean, '/')) {
            return mb_substr(explode('?', explode('#', $clean)[0])[0], 0, 255);
        }

        $withoutFragment = explode('#', $clean, 2)[0];
        $withoutQuery = explode('?', $withoutFragment, 2)[0];

        return mb_substr($withoutQuery, 0, 512);
    }
}
