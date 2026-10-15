<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modul CBT (Computer Based Test)
    |--------------------------------------------------------------------------
    |
    | Modul CBT belum lengkap: controller (App\Http\Controllers\Cbt\*), view
    | (resources/views/cbt/*), dan tabel basis datanya belum ada, padahal
    | route-nya sudah terdaftar — sehingga 27 URL /cbt* menghasilkan HTTP 500
    | ("Class ... not found"). Route tersebut kini dimatikan secara default.
    |
    | Aktifkan kembali (SIMANTAP_CBT_ENABLED=true) SETELAH controller, view,
    | dan migrasi tabel CBT tersedia.
    |
    */
    'cbt_enabled' => env('SIMANTAP_CBT_ENABLED', false),

];
