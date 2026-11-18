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

    'bridge_download_url' => env('DAPODIK_BRIDGE_DOWNLOAD_URL'),

    'bridge_version' => env('DAPODIK_BRIDGE_VERSION'),

    'bridge_sha256' => env('DAPODIK_BRIDGE_SHA256'),

    'bridge_storage_path' => storage_path('app/private/bridge/SIMANTAP-Bridge.exe'),

];
