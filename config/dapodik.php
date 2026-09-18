<?php

return [

    'host' => env('DAPODIK_HOST', 'localhost'),

    'port' => env('DAPODIK_PORT', 5774),

    'protocol' => env('DAPODIK_PROTOCOL', 'http'),

    'timeout' => env('DAPODIK_TIMEOUT', 30),

    'max_retries' => 3,

    'retry_delay_ms' => [400, 1200],

    'batch_size' => 100,

    'archive_chunk' => 500,

];
