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
        'pjok',
        'pendidikan jasmani',
        'penjas',
    ],

    // Absensi dibuka sejak beberapa menit sebelum jam mulai hingga tenggang setelah jam selesai.
    'menit_sebelum_jadwal' => (int) env('PRESENSI_MENIT_SEBELUM_JADWAL', 15),
    'menit_setelah_jadwal' => (int) env('PRESENSI_MENIT_SETELAH_JADWAL', 60),

    // Batas aman umum apabila sekolah belum mengatur akurasi per lokasi.
    'akurasi_gps_maksimal_default' => (int) env('PRESENSI_AKURASI_GPS_MAKSIMAL', 150),
];
