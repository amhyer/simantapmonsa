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
    // Agregat kunjungan route HTML internal; tidak menyimpan identitas pengguna.
    'menu_usage_telemetry' => env('SIMANTAP_MENU_USAGE_TELEMETRY', true),

    // Strategi autosave Redis-first murni (tanpa write DB per klik jawaban).
    // Default false = dual-write (buffer Redis/Cache + baris CbtResult tanpa
    // mengunci CbtTestSession). Set true di produksi ber-Redis untuk beban tinggi
    // 300+ siswa serentak; jawaban di-flush penuh saat submit/complete/expired.
    'cbt_buffer_async' => env('SIMANTAP_CBT_BUFFER_ASYNC', false),

    // Mode SaaS Multi-Tenant (satu database banyak sekolah dengan isolasi ketat
    // kolom `tenant_id`). Default false untuk instalasi sekolah tunggal.
    'multi_tenant_enabled' => env('SIMANTAP_MULTI_TENANT', false),

    // Pendaftaran mandiri Akun Sekolah Baru (/daftar-sekolah).
    // Pendaftaran mandiri guru, siswa, dan orang tua tidak disediakan;
    // publik hanya dapat mendaftarkan Akun Sekolah (Operator/Administrator).
    'school_registration_enabled' => (bool) env('SIMANTAP_SCHOOL_REGISTRATION', true),

    // Wajib ganti kata sandi pada login pertama untuk akun siswa baru
    // (hasil sinkronisasi Dapodik / generate massal). Guru/PTK selalu wajib.
    'force_change_siswa' => env('SIMANTAP_FORCE_CHANGE_SISWA', true),

    // Batas maksimal akun yang diproses per permintaan pada reset/generate massal
    // (menghindari timeout Nginx/PHP-FPM akibat bcrypt 12 rounds).
    'reset_massal_max' => (int) env('SIMANTAP_RESET_MASSAL_MAX', 100),

    // Jumlah baris per halaman pada halaman referensi Foto Siswa.
    'foto_siswa_per_page' => max(1, (int) env('SIMANTAP_FOTO_SISWA_PER_PAGE', 50)),

    // Fallback baca foto siswa lama dari disk `public` (hanya lewat route terautentikasi)
    // selama jendela migrasi ke disk `local` (private). Default false (private-by-default):
    // file di disk `public` bisa diakses langsung tanpa auth via /storage/.
    // Set true hanya sementara selama migrasi, lalu kembalikan ke false.
    'foto_fallback_public' => (bool) env('SIMANTAP_FOTO_FALLBACK_PUBLIC', false),

    // Batas keamanan arsip ZIP pada unggah massal foto siswa.
    'foto_zip_max_entries' => max(1, (int) env('SIMANTAP_FOTO_ZIP_MAX_ENTRIES', 200)),
    'foto_zip_max_uncompressed_bytes' => max(1048576, (int) env('SIMANTAP_FOTO_ZIP_MAX_UNCOMPRESSED_BYTES', 100 * 1024 * 1024)),

    // Pengaturan visibilitas bagian opsional di halaman landing publik.
    'landing' => [
        'show_demo_video' => (bool) env('SIMANTAP_LANDING_DEMO_VIDEO', false),
        'show_newsletter' => (bool) env('SIMANTAP_LANDING_NEWSLETTER', false),
    ],
];
