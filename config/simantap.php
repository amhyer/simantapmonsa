<?php

return [
    // CBT pilihan ganda: aktifkan SETELAH migrate, pengujian PostgreSQL,
    // scheduler (schedule:run setiap menit), dan uji penerimaan sekolah.
    // Petunjuk: docs/CBT.md. Flag off tidak menghapus tabel/data.
    'cbt_enabled' => env('SIMANTAP_CBT_ENABLED', false),

    // Fase 3 (strangler): alur ujian Tryout ditulis ke engine terpadu `exam_*`,
    // sementara tabel `tryout_*` tetap dicerminkan agar laporan/ekspor/admin
    // yang belum dipindahkan tetap benar. Set false untuk kembali ke alur lama.
    'exam_engine_tryout' => env('SIMANTAP_EXAM_ENGINE_TRYOUT', true),
];
