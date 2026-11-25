<?php

use Illuminate\Http\Request;

return [

    /*
    |--------------------------------------------------------------------------
    | Header keamanan
    |--------------------------------------------------------------------------
    |
    | Dipasang oleh App\Http\Middleware\SecurityHeaders pada SETIAP respons.
    | Matikan sementara dengan SECURITY_HEADERS_ENABLED=false bila ada
    | integrasi (mis. iframe pihak ketiga) yang benar-benar membutuhkannya.
    |
    */

    'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | X-Frame-Options
    |--------------------------------------------------------------------------
    |
    | SAMEORIGIN menolak situs ini dibingkai (iframe) oleh domain lain,
    | sehingga memblokir clickjacking. Ganti ke DENY bila tidak pernah
    | membingkai halaman sendiri.
    |
    */

    'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),

    /*
    |--------------------------------------------------------------------------
    | Referrer-Policy
    |--------------------------------------------------------------------------
    */

    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    /*
    |--------------------------------------------------------------------------
    | Permissions-Policy
    |--------------------------------------------------------------------------
    |
    | SIMANTAP MEMANG memakai geolokasi (presensi geofence) dan kamera
    | (pemindaian QR kartu siswa), jadi keduanya dibatasi ke same-origin
    | — BUKAN dimatikan. Mikrofon tidak dipakai aplikasi.
    |
    */

    'permissions_policy' => env(
        'SECURITY_PERMISSIONS_POLICY',
        'geolocation=(self), camera=(self), microphone=(), interest-cohort=()'
    ),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Hanya dikirim pada permintaan HTTPS — mengirim HSTS lewat HTTP tidak
    | berguna dan bisa menyesatkan. Default 1 tahun (31536000 detik).
    | Set 0 untuk menonaktifkan.
    |
    | includeSubDomains sengaja TIDAK dinyalakan secara default: bila ada
    | subdomain yang belum ber-HTTPS, subdomain itu akan ikut terkunci.
    |
    */

    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies (Nginx / Cloudflare / Load Balancer)
    |--------------------------------------------------------------------------
    |
    | Daftar IP/CIDR proxy tepercaya dipisah koma (env SIMANTAP_TRUSTED_PROXIES).
    | Default KOSONG = tidak mempercayai proxy mana pun. Isi '*' HANYA jika
    | aplikasi diisolasi di belakang proxy dan tidak bisa diakses langsung.
    |
    */

    'trusted_proxies' => env('SIMANTAP_TRUSTED_PROXIES', ''),
    'trusted_proxy_headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO,

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | SENGAJA KOSONG secara default.
    |
    | Seluruh aset frontend utama sudah di-self-host melalui Vite (tanpa CDN
    | eksternal di Blade aplikasi), namun beberapa blok <script> inline masih
    | dipakai di template Blade. Urutan penerapan yang aman:
    |   1. Aktifkan SIMANTAP_CSP_REPORT_ONLY=true dan pantau /csp-report.
    |   2. Setelah bersih dari pelanggaran, tegakkan lewat SECURITY_CSP.
    |
    */

    'content_security_policy' => env('SECURITY_CSP', ''),

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy (Report-Only)
    |--------------------------------------------------------------------------
    |
    | Mode observasi (TIDAK memblokir browser). Aktifkan lewat
    | SIMANTAP_CSP_REPORT_ONLY=true untuk mengumpulkan laporan pelanggaran
    | ke POST /csp-report (channel log 'csp', retensi 14 hari).
    |
    */

    'csp' => [
        'enabled' => (bool) env('SIMANTAP_CSP_REPORT_ONLY', false),
        'report_uri' => '/csp-report',
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://cdn.jsdelivr.net', 'https://unpkg.com'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://unpkg.com'],
            'font-src' => ["'self'", 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'"],
            'frame-src' => ["'self'", 'https://www.youtube-nocookie.com'],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ],
    ],

];
