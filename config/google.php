<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Sheets Configuration
    |--------------------------------------------------------------------------
    | Sesuai dengan skrip GAS SIMANTAP v4.2
    */

    'service_account_path' => env('GOOGLE_SERVICE_ACCOUNT_PATH'),

    /*
    | Tab names in spreadsheet - EXACTLY as GAS creates them
    */
    'sheet_tabs' => [
        'pengguna'      => 'PENGGUNA',
        'siswa'         => 'SISWA',
        'nilai'         => 'NILAI',
        'kehadiran'     => 'KEHADIRAN',
        'materi'        => 'MATERI',
        'kuis'          => 'KUIS',
        'hasil_kuis'    => 'HASIL KUIS',
        'catatan'       => 'CATATAN',
        'dimensi'       => 'DIMENSI',
        'kebiasaan'     => '7 KEBIASAAN',
        'pengaturan'    => 'PENGATURAN GURU',
        'sekolah'       => 'SEKOLAH',
        'ringkasan'     => 'RINGKASAN GURU',
        'aktivitas'     => 'AKTIVITAS',
    ],

    /*
    | Column headers - EXACTLY as GAS defines them (KOL_xxx)
    */
    'headers' => [
        'pengguna' => [
            'ID', 'Nama Lengkap', 'Nama Pengguna', 'Kata Sandi', 'Peran',
            'Terhubung Dengan', 'Kelas / Mata Pelajaran', 'Aktif', 'Terakhir Masuk',
        ],
        'siswa' => [
            'ID', 'Guru ID', 'Nama Guru', 'NIS', 'Nama Peserta Didik', 'Kelas',
            'Jenis Kelamin', 'Nama Orang Tua', 'Aktif', 'Rekaman (JSON)',
        ],
        'nilai' => [
            'ID', 'Guru ID', 'Nama Guru', 'Siswa ID', 'NIS', 'Nama Peserta Didik',
            'Tanggal', 'Jenis', 'Judul Penilaian', 'Mata Pelajaran', 'Nilai', 'Rekaman (JSON)',
        ],
        'kehadiran' => [
            'ID', 'Guru ID', 'Nama Guru', 'Siswa ID', 'NIS', 'Nama Peserta Didik',
            'Tanggal', 'Kode', 'Status', 'Keterangan', 'Rekaman (JSON)',
        ],
        'materi' => [
            'ID', 'Guru ID', 'Nama Guru', 'Tanggal', 'Judul', 'Mata Pelajaran', 'Kelas',
            'Tujuan Pembelajaran', 'Materi Pokok', 'Kegiatan', 'Tautan Sumber', 'Rekaman (JSON)',
        ],
        'kuis' => [
            'ID', 'Guru ID', 'Nama Guru', 'Tanggal', 'Judul', 'Mata Pelajaran', 'Kelas',
            'Mode', 'Sumber Soal', 'Tautan Soal', 'Materi ID', 'KKM',
            'Batas Waktu (menit)', 'Aktif', 'Jumlah Soal', 'Rekaman (JSON)',
        ],
        'hasil_kuis' => [
            'ID', 'Guru ID', 'Nama Guru', 'Kuis ID', 'Judul Kuis', 'Siswa ID', 'NIS',
            'Nama Peserta Didik', 'Waktu', 'Benar', 'Total', 'Skor', 'Tuntas',
            'Durasi (detik)', 'Sumber Soal', 'Diisi Oleh', 'Catatan Siswa', 'Rekaman (JSON)',
        ],
        'catatan' => [
            'ID', 'Guru ID', 'Nama Guru', 'Siswa ID', 'Nama Peserta Didik',
            'Tanggal', 'Jenis', 'Catatan', 'Rekaman (JSON)',
        ],
        'dimensi' => [
            'ID', 'Guru ID', 'Nama Guru', 'Siswa ID', 'Nama Peserta Didik',
            'No Dimensi', 'Dimensi', 'Skor', 'Predikat', 'Catatan', 'Rekaman (JSON)',
        ],
        'kebiasaan' => [
            'ID', 'Siswa ID', 'Tanggal', 'Bangun Pagi', 'Beribadah', 'Berolahraga',
            'Makan Sehat dan Bergizi', 'Gemar Belajar', 'Bermasyarakat', 'Tidur Cepat',
            'Catatan Orang Tua', 'Diisi Oleh', 'Waktu Simpan',
        ],
        'pengaturan' => [
            'Guru ID', 'Nama Guru', 'Mata Pelajaran', 'Kelas', 'KKM', 'Semester',
            'Tahun Pelajaran', 'Fase', 'Pembaruan', 'Pengaturan (JSON)', 'Sistem (JSON)',
        ],
        'sekolah' => [
            'Bagian', 'Kunci', 'Nilai',
        ],
        'ringkasan' => [
            'Guru ID', 'Nama Guru', 'Mata Pelajaran', 'Kelas', 'KKM', 'Semester',
            'Tahun Pelajaran', 'Jumlah Siswa', 'Rata-rata', 'Tuntas', 'Belum Tuntas',
            'Ketuntasan (%)', 'Kehadiran (%)',
            'Rata Tugas', 'Rata Ulangan Harian', 'Rata Praktik', 'Rata PTS', 'Rata PAS',
            'Predikat A', 'Predikat B', 'Predikat C', 'Predikat D', 'Belum Dinilai',
            'Hadir', 'Sakit', 'Izin', 'Alpa',
            'Dim 1 Keimanan dan Ketakwaan', 'Dim 2 Kewargaan', 'Dim 3 Penalaran Kritis',
            'Dim 4 Kreativitas', 'Dim 5 Kolaborasi', 'Dim 6 Kemandirian',
            'Dim 7 Kesehatan', 'Dim 8 Komunikasi',
            'KAIH Bangun Pagi', 'KAIH Beribadah', 'KAIH Berolahraga',
            'KAIH Makan Sehat dan Bergizi', 'KAIH Gemar Belajar', 'KAIH Bermasyarakat',
            'KAIH Tidur Cepat',
            'KAIH Capaian', 'KAIH Predikat', 'KAIH Hari Laporan', 'KAIH Keluarga Melapor',
            'Jumlah Materi', 'Jumlah Kuis', 'Kuis Terbuka', 'Kuis Tautan Luar',
            'Pengerjaan Kuis', 'Catatan Sikap', 'Butir Nilai',
            'Pembaruan',
        ],
        'aktivitas' => [
            'Waktu', 'Nama Pengguna', 'Peran', 'Kegiatan', 'Rincian', 'Jumlah Data',
        ],
    ],

    /*
    | Status hadir mapping
    */
    'status_hadir' => [
        'H' => 'Hadir',
        'S' => 'Sakit',
        'I' => 'Izin',
        'A' => 'Tanpa keterangan',
    ],

    /*
    | Dimensi Profil Pelajar
    */
    'dimensi' => [
        'Keimanan dan Ketakwaan',
        'Kewargaan',
        'Penalaran Kritis',
        'Kreativitas',
        'Kolaborasi',
        'Kemandirian',
        'Kesehatan',
        'Komunikasi',
    ],

    /*
    | SKALA KAIH (7 Kebiasaan)
    */
    'skala_kaih' => [
        1 => 'Mulai Berkembang',
        2 => 'Berkembang',
        3 => 'Berkembang Sesuai Harapan',
        4 => 'Sangat Berkembang',
    ],

];
