<?php

return [

    'host' => env('DAPODIK_HOST', 'localhost'),

    'port' => env('DAPODIK_PORT', 5774),

    'protocol' => env('DAPODIK_PROTOCOL', 'http'),

    'timeout' => env('DAPODIK_TIMEOUT', 30),

    'token' => env('DAPODIK_TOKEN'),

    'max_retries' => 3,

    'retry_delay_ms' => [400, 1200],

    'batch_size' => 100,

    'archive_chunk' => 500,

    // URL unduhan installer Bridge WAJIB memakai HTTPS yang valid.
    'bridge_download_url' => (static function (?string $raw): ?string {
        $url = trim((string) $raw);
        if ($url === '') {
            return null;
        }

        return str_starts_with(strtolower($url), 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false
            ? $url
            : null;
    })(env('DAPODIK_BRIDGE_DOWNLOAD_URL')),

    'bridge_version' => env('DAPODIK_BRIDGE_VERSION'),

    'bridge_sha256' => env('DAPODIK_BRIDGE_SHA256'),

    'bridge_storage_path' => storage_path('app/private/bridge/SIMANTAP-Bridge.exe'),

];
