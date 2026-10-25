<?php

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
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | SENGAJA KOSONG.
    |
    | Ada 22 tag CDN (jsdelivr, cdnjs, unpkg, Google Fonts) dan banyak blok
    | <script> inline di template Blade. Memasang CSP sekarang akan
    | mematahkan grafik, ikon, dan interaksi di hampir seluruh halaman.
    |
    | Urutan yang benar bila ingin memakainya:
    |   1. Self-host aset CDN lewat Vite (lihat LAPORAN_KESIAPAN_LAUNCH §6 S4).
    |   2. Pasang dulu sebagai Content-Security-Policy-Report-Only dan pantau.
    |   3. Baru tegakkan sebagai header wajib.
    |
    */

    'content_security_policy' => env('SECURITY_CSP', ''),

];
