<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk utama cadangan
    |--------------------------------------------------------------------------
    |
    | Nama disk (config/filesystems.php) tempat berkas cadangan JSON ditulis.
    | Default 'local' = storage/app/private. Ganti ke 's3' (atau disk lain)
    | bila cadangan ingin langsung mendarat di penyimpanan awan.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disk salinan (offsite)
    |--------------------------------------------------------------------------
    |
    | Bila diisi dengan nama disk yang BERBEDA dari 'disk', setiap cadangan
    | juga disalin ke sana. Kegagalan penyalinan TIDAK membatalkan cadangan
    | utama — hanya dicatat ke log dan dilaporkan di keluaran perintah.
    |
    | Kosongkan bila salinan offsite ditangani di luar aplikasi
    | (rclone / rsync / snapshot volume).
    |
    */

    'salinan_disk' => env('BACKUP_COPY_DISK'),

    /*
    |--------------------------------------------------------------------------
    | Direktori cadangan
    |--------------------------------------------------------------------------
    |
    | Folder di dalam disk. Dipakai bersama oleh `simantap:backup`,
    | ExportBackupJob (tombol Export di Admin › Cadangan), dan daftar
    | cadangan pada BackupController::index().
    |
    */

    'direktori' => env('BACKUP_DIRECTORY', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Retensi
    |--------------------------------------------------------------------------
    |
    | Umur maksimum (hari) berkas `backup-simantap-*.json` sebelum dihapus
    | otomatis oleh `simantap:backup` (terjadwal tiap hari 02:00).
    | Tanpa rotasi, cadangan harian menumpuk tanpa batas dan menghabiskan disk.
    |
    | Set 0 untuk menonaktifkan rotasi.
    |
    */

    'retensi_hari' => (int) env('BACKUP_RETENTION_DAYS', 30),

];
