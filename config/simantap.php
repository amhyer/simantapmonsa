<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modul CBT (Computer Based Test)
    |--------------------------------------------------------------------------
    |
    | Modul CBT kini sudah lengkap: controller (App\Http\Controllers\Cbt\*),
    | view (resources/views/cbt/*), dan tabel basis datanya sudah tersedia,
    | sehingga route diaktifkan secara default. Setel SIMANTAP_CBT_ENABLED=false
    | bila perlu menonaktifkan route CBT sementara.
    |
    */
    'cbt_enabled' => env('SIMANTAP_CBT_ENABLED', true),

];
