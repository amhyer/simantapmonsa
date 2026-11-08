<?php

return [
    /*
    | Mata pelajaran yang punya catatan hadir terpisah pada setiap jadwal.
    | Nama jadwal dicocokkan secara longgar agar variasi nama Dapodik seperti
    | "Pendidikan Agama Islam dan Budi Pekerti" tetap dikenali.
    */
    'mapel_terpisah' => [
        'bahasa inggris',
        'english',
        'agama islam',
        'pai',
        'agama kristen',
        'pak',
        'agama katolik',
        'agama hindu',
        'agama buddha',
        'agama khonghucu',
        'pjok',
        'pendidikan jasmani',
        'penjas',
        'muatan lokal',
        'mulok',
        'bahasa daerah',
        'informatika',
        'tik',
        'btq',
        'baca tulis quran',
    ],

    // Absensi dibuka sejak beberapa menit sebelum jam mulai hingga tenggang setelah jam selesai.
    'menit_sebelum_jadwal' => (int) env('PRESENSI_MENIT_SEBELUM_JADWAL', 15),
    'menit_setelah_jadwal' => (int) env('PRESENSI_MENIT_SETELAH_JADWAL', 60),

    // Batas aman umum apabila sekolah belum mengatur akurasi per lokasi.
    'akurasi_gps_maksimal_default' => (int) env('PRESENSI_AKURASI_GPS_MAKSIMAL', 150),

    // Jika true, scan mandiri kartu QR pegawai tetap wajib mengirim koordinat GPS di dalam area sekolah.
    'wajib_gps_scan_qr_pegawai' => (bool) env('PRESENSI_WAJIB_GPS_SCAN_QR_PEGAWAI', true),

    // Batas jam masuk untuk presensi hasil impor mesin fingerprint.
    // Scan masuk setelah jam ini ditandai sebagai "terlambat".
    'mesin_jam_masuk' => env('PRESENSI_MESIN_JAM_MASUK', '07:15'),

    // Batas jam masuk mesin per jenjang (PAUD/SD/MI/SMP/MTs/SMA/MA/SMK/SLB).
    // Nilai default mengikuti `PRESENSI_MESIN_JAM_MASUK`; tiap jenjang bisa
    // di-override lewat env `PRESENSI_MESIN_JAM_MASUK_JENJANG_<JENJANG>`.
    'mesin_jam_masuk_jenjang' => [
        'SD' => env('PRESENSI_MESIN_JAM_MASUK_JENJANG_SD', '07:15'),
        'MI' => env('PRESENSI_MESIN_JAM_MASUK_JENJANG_MI', '07:15'),
        'SMP' => env('PRESENSI_MESIN_JAM_MASUK_JENJANG_SMP', '07:15'),
        'MTs' => env('PRESENSI_MESIN_JAM_MASUK_JENJANG_MTS', '07:15'),
        'SMA' => env('PRESENSI_MESIN_JAM_MASUK_JENJANG_SMA', '07:15'),
        'MA' => env('PRESENSI_MESIN_JAM_MASUK_JENJANG_MA', '07:15'),
        'SMK' => env('PRESENSI_MESIN_JAM_MASUK_JENJANG_SMK', '07:15'),
        'SLB' => env('PRESENSI_MESIN_JAM_MASUK_JENJANG_SLB', '07:15'),
    ],

    /*
    | Tarik langsung dari database Attendance Management (ZKTeco).
    | PRESENSI_MESIN_DB_PATH diarahkan ke file att2000.mdb / .accdb.
    | Bila dikosongkan, sistem mencari di lokasi pemasangan yang umum.
    */
    'mesin_db_path' => env('PRESENSI_MESIN_DB_PATH'),
    'mesin_db_engine' => env('PRESENSI_MESIN_DB_ENGINE', 'auto'),
    'mesin_db_timeout' => (int) env('PRESENSI_MESIN_DB_TIMEOUT', 120),
    'mesin_db_script' => env('PRESENSI_MESIN_DB_SCRIPT', base_path('tools/attendance-mdb-export.ps1')),
    'mesin_db_powershell' => env('PRESENSI_MESIN_DB_POWERSHELL'),
    'mesin_db_kandidat' => [
        'D:\\ZKTime\\bin\\att2000.mdb',
        'E:\\ZKTime\\bin\\att2000.mdb',
        'C:\\ZKTime\\bin\\att2000.mdb',
        'C:\\Program Files (x86)\\Solution\\ATT2000.MDB',
        'C:\\Program Files (x86)\\ZKTime\\bin\\att2000.mdb',
        'C:\\Program Files\\ZKTime\\bin\\att2000.mdb',
    ],

    /*
    | Komunikasi langsung ke mesin fingerprint via TCP (port default ZKTeco: 4370).
    | Digunakan bila mesin dapat dijangkau di jaringan lokal yang sama.
    */
    'mesin_db_tcp_host' => env('PRESENSI_MESIN_DB_TCP_HOST', '192.168.1.7'),
    'mesin_db_tcp_port' => env('PRESENSI_MESIN_DB_TCP_PORT', 4370),
];
