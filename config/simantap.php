<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modul CBT (Computer Based Test)
    |--------------------------------------------------------------------------
    |
    | Kill switch modul asesmen: CBT, Tryout, dan TKA.
    |
    | DEFAULT FALSE. Modul ini BELUM bisa dijalankan end-to-end (lihat
    | docs/RENCANA_PERBAIKAN_VERIFIED.md, Fase 1). Selama flag false, seluruh
    | route CBT/Tryout/TKA diblokir middleware `module.asesmen` dan membalas
    | HTTP 404 (bukan 500), baik untuk peran guru, siswa, maupun admin.
    |
    | Setel SIMANTAP_CBT_ENABLED=true HANYA setelah Fase 0-2 lulus dan test
    | CBT/Tryout hijau.
    |
    */
    'cbt_enabled' => env('SIMANTAP_CBT_ENABLED', false),

];
