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
    'wajib_gps_scan_qr_pegawai' => (bool) env('PRESENSI_WAJIB_GPS_SCAN_QR_PEGAWAI', false),
];
