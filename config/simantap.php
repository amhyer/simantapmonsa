<?php

return [
    // CBT pilihan ganda: aktifkan SETELAH migrate, pengujian PostgreSQL,
    // scheduler (schedule:run setiap menit), dan uji penerimaan sekolah.
    // Petunjuk: docs/CBT.md. Flag off tidak menghapus tabel/data.
    'cbt_enabled' => env('SIMANTAP_CBT_ENABLED', false),
];
