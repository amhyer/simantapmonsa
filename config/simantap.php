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

    // Sama untuk CBT: attempt/jawaban ditulis ke `exam_*` lalu dicerminkan ke
    // `cbt_*`. Set false untuk kembali ke penulisan langsung engine lama.
    'exam_engine_cbt' => env('SIMANTAP_EXAM_ENGINE_CBT', true),

    // Fase 3 langkah 6b: pembaca dasbor/laporan memakai `exam_*` alih-alih
    // `cbt_*`/`tryout_*`. Default false sampai data produksi direkonsiliasi
    // (jalankan `exam:backfill` lalu bandingkan), karena instalasi lama bisa
    // punya data yang belum punya padanan engine.
    'exam_engine_readers' => env('SIMANTAP_EXAM_ENGINE_READERS', false),
];
