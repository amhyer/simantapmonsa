<?php

return [

    'host' => env('DAPODIK_HOST', 'localhost'),

    'port' => env('DAPODIK_PORT', 5774),

    'protocol' => env('DAPODIK_PROTOCOL', 'http'),

    'timeout' => env('DAPODIK_TIMEOUT', 30),

    'token' => env('DAPODIK_TOKEN'),

    // Pada mode multi-sekolah, host loopback/localhost ditolak secara bawaan
    // agar server cloud SIMANTAP tidak diarahkan ke dirinya sendiri oleh tenant.
    'allow_loopback_in_multi_school' => (bool) env('DAPODIK_ALLOW_LOOPBACK_IN_MULTI_SCHOOL', false),

    // Alias kompatibilitas konfigurasi lama untuk pengecualian loopback di atas.
    'allow_private_host' => (bool) env('DAPODIK_ALLOW_PRIVATE_HOST', false),

    'max_retries' => 3,

    'retry_delay_ms' => [400, 1200],

    'batch_size' => 100,

    'archive_chunk' => 500,

    'bridge_download_url' => env('DAPODIK_BRIDGE_DOWNLOAD_URL'),

    'bridge_version' => env('DAPODIK_BRIDGE_VERSION'),

    'bridge_sha256' => env('DAPODIK_BRIDGE_SHA256'),

    'bridge_storage_path' => storage_path('app/private/bridge/SIMANTAP-Bridge.exe'),

];
