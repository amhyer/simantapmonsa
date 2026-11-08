# Perbaikan isolasi dashboard dan log Dapodik

## Yang diubah

- Dashboard admin untuk akun ber-tenant memakai cache per tenant dan hanya menampilkan jumlah pengguna (difilter eksplisit), siswa dan rombel yang memiliki scope tenant, serta log tenant tersebut. Dashboard dengan data global tetap dipakai untuk instalasi sekolah tunggal tanpa tenant. Admin tanpa sekolah ditolak jika mode multi-tenant aktif atau ada tenant di database.
- Migrasi `2026_11_08_000001_add_tenant_id_to_dapodik_sync_logs` menambahkan `tenant_id` dan penanda `tenant_owned`. Model membatasi query/detail/hapus log ke tenant aktif. Log yatim yang pernah dimiliki tenant tidak berubah menjadi log sekolah tunggal setelah tenant dihapus.
- Kepemilikan log baru diambil dari konteks server, bukan NPSN yang dikirim. Status sidebar menggunakan log terisolasi dan cache per tenant. Pada API Bridge, API key harus milik pengguna aktif, konteks sekolah diambil dari pemilik key, dan header sekolah yang bertentangan ditolak.
- Impor siswa via antrean menyimpan dan memulihkan konteks tenant, menolak job lama yang tidak memiliki konteks dalam instalasi ber-tenant, dan tidak mengubah akun siswa dari sekolah lain. Akun baru memperoleh sandi acak dan diwajibkan ganti sandi; admin harus mereset/mendistribusikan sandi melalui alur resmi. Bentrok nama pengguna tidak diselesaikan dengan mengambil alih akun lain: akun baru dilewati dan dicatat di log aplikasi.
- Pengelolaan akun admin difilter per tenant, tindakan edit/update/hapus dibatasi pada akun sekolah sendiri, dan relasi siswa divalidasi per sekolah. Pembuatan akun massal dan hapus massal sementara ditolak pada tenant; job massal lama juga ditolak karena belum membawa konteks tenant.
- API key hanya bisa dilihat/diubah oleh admin sekolah pemilik akun key. Log impor API lama tidak ditampilkan pada tenant karena tidak menyimpan pemilik tenant yang dapat dipercaya.
- **Pembatasan data global:** backup/restore/wipe dan log aktivitas admin dinonaktifkan untuk instalasi ber-tenant. Job backup global dan job pembuatan akun massal lama juga ditolak ketika ada tenant. Fitur backup per sekolah belum diimplementasikan; operator tetap perlu menjalankan backup platform yang aman melalui prosedur operasional yang terkontrol.
- **Pembatasan sementara:** Bridge lama (penulisan `/api/dapodik/sync/*`), konfigurasi/push Dapodik lama, dan auto-sync lama tidak dapat dijalankan bila ada tenant atau mode multi-tenant aktif. Konfigurasi Dapodik dan tabel PTK masih global; membiarkannya aktif dapat mencampur data sekolah. API status tetap tersedia untuk key ber-tenant dan hanya menampilkan data tenant yang sudah di-scope; data PTK dan semester global disembunyikan. Endpoint impor manual siswa dan riwayat log tetap tersedia; halaman saat ini belum menyediakan alur unggah manual yang lengkap. Job tenant tidak mencatat ke tabel aktivitas lama karena tabel itu belum terisolasi; log impor tetap tersedia.

## Migrasi dan log lama

1. Buat backup database dan pastikan prosedur restore telah diuji. Coba perubahan dahulu di salinan/staging, bukan langsung di produksi.
2. Jalankan `php artisan migrate` dengan PHP proyek (Windows Herd: gunakan path PHP 8.4 yang dipakai proyek). Migrasi diperlukan **sebelum** kode baru melayani request.
3. **Tidak ada backfill otomatis:** NPSN pada log bisa berasal dari payload dan akun bisa berpindah sekolah. Log lama `tenant_id = NULL` tidak ditampilkan pada instalasi dengan tenant; datanya tidak dihapus. Penetapan pemilik log lama perlu diverifikasi satu per satu berdasarkan bukti yang dapat dipercaya. Jika dikerjakan lewat prosedur administratif terpisah, isi `tenant_id` yang benar **dan** set `tenant_owned = true`; simpan audit dan backup. Jangan mengaitkan semua log hanya berdasarkan NPSN/akun saat ini.
4. Job impor lama yang sudah ada di antrean dan tidak memiliki konteks tenant akan gagal secara aman. Tinjau antrean/failed jobs dan jalankan ulang impor dari akun sekolah yang benar. Restart worker agar memuat kode baru.
5. Jalankan `php artisan optimize:clear` bila deployment menggunakan cache konfigurasi/rute/view, lalu verifikasi ulang. Cache hasil dashboard dan badge memiliki TTL 120 detik.

## Pengujian yang perlu dijalankan di lokal/staging

```sh
php artisan test --filter='SchoolReferenceIsolationTest|SuperAdminTenantSubscriptionTest|DapodikTenantIsolationTest|MenuNavigationRegressionTest|DapodikBridgeSyncTest|DapodikSyncTest|AdminMenuTest|MenuAuditTest'
php artisan test
```

Uji juga secara manual dua akun admin dua sekolah: dashboard, daftar/edit/hapus akun, API keys, daftar/detail/hapus log, penolakan backup/wipe, API status dengan masing-masing key, header tenant yang salah, impor siswa di antrean, dan perilaku setelah tenant dihapus. Pastikan Bridge/push yang tidak aman diblokir, serta instalasi sekolah tunggal tanpa tenant tetap berfungsi. Gunakan database uji, jangan memakai data produksi.

## Batas dan risiko tersisa

Perubahan ini tidak menjamin isolasi seluruh aplikasi. `dapodik_configs`, `pengaturan_guru`, `semesters` dan tabel lain masih belum seluruhnya mempunyai kepemilikan tenant; fitur/halaman lain yang memakai tabel global, backup, dan cache global memerlukan audit tersendiri. NIS dan nama pengguna masih unik secara global, sehingga impor dengan identitas yang sama pada dua sekolah dapat gagal atau melewati pembuatan akun. Akun lama yang sudah memakai sandi default tidak otomatis direset. Sebelum mengaktifkan layanan SaaS produksi, lakukan migrasi dan audit isolasi untuk semua jalur baca/tulis, hubungan data, queue dan backup; lakukan tes otomatis dan uji penetrasi dua tenant.

Status verifikasi pada lingkungan kerja: lint sintaks PHP dengan PHP WebAssembly dan `git diff --check` berhasil. PHPUnit, migrasi, database, dan uji browser belum dijalankan karena PHP/Composer native dan dependensi Laravel tidak tersedia.


## Pembaruan prioritas 1–5 (belum selesai seluruhnya)

### Kode yang ditambahkan

- PTK, aktivitas, ringkasan guru, dan ekstrakurikuler sekarang diberi `tenant_id` dan penanda `tenant_owned`, dengan scope Eloquent; data baru mengikuti konteks sekolah. Login mencatat tenant dari akun yang diautentikasi, bukan dari header. Relasi akun pada aktivitas/ringkasan dibatasi ke sekolah saat ini. Guru dan orang tua dalam query Kepala Sekolah dibatasi ke sekolah, termasuk rekap dan halaman pemantauan.
- Referensi guru/PTK dan pilihan guru/pembina dibatasi; validasi penugasan jadwal/ekskul memeriksa sekolah dan peran guru, perubahan gelar menolak seluruh permintaan jika ada ID di luar sekolah, penghapusan jadwal/ekskul mencari ulang dalam konteks. Middleware identifikasi tenant ditempatkan sebelum route model binding web. Akun tanpa sekolah tidak dapat memakai header untuk mengambil konteks; admin dan kepsek tanpa konteks yang sah ditolak pada instalasi bertenant.
- Migration baru `2026_11_08_000002_isolate_school_reference_and_activity.php` menambahkan penanda akun yang pernah memiliki tenant. Migrasi mencatat **kepemilikan akun saat ini**, bukan menebak pemilik data historis. Baris PTK/aktivitas/ringkasan/ekskul lama tetap belum dipetakan; di sekolah tunggal tanpa tenant baris lama tetap terbaca. Baris yang ditandai milik tenant tidak berubah menjadi data sekolah tunggal saat tenant dihapus.
- Ringkasan guru lama yang tidak terlihat dapat bertabrakan dengan indeks unik `guru_id`. Penulisan ringkasan tidak akan menimpa atau mengklaim baris tersebut; sistem mencatat peringatan dan meminta rekonsiliasi. Perubahan data siswa tetap disimpan, tetapi angka rekap guru itu belum diperbarui sampai data lama ditangani.

### Mitigasi sementara dan dampaknya

- Peta Kelas dan Hasil Belajar Kepala Sekolah dinonaktifkan pada instalasi bertenant: `pengaturan_guru` masih global dan pemilik lamanya tidak dapat disimpulkan dari akun guru yang mungkin sudah berpindah. Dashboard menggunakan ambang KKM sementara 70 dan menampilkannya sebagai peringatan; jangan gunakan predikat tersebut sebagai keputusan akademik final tanpa validasi KKM. Mode sekolah tunggal tetap menggunakan konfigurasi lama.
- Penghapusan tenant melalui Super Admin ditolak agar baris tabel lama dengan FK `nullOnDelete` tidak berubah menjadi data sekolah tunggal; nonaktifkan sekolah sebagai gantinya. Pemindahan langsung akun antar-tenant juga ditolak. Menautkan akun yang belum punya tenant tidak lagi memindahkan siswa hanya berdasarkan kesamaan NISN; identitas dan tautan siswa harus direkonsiliasi secara terpisah. Jangan menghapus tenant langsung melalui SQL: banyak tabel lain belum memiliki penanda kepemilikan.
- Bridge/push/autosync dan backup/restore/wipe per tenant **belum diimplementasikan**; guard lama tetap berlaku. Ekspor mentah dan restore global tidak aman dijalankan per sekolah, sehingga prioritas 3 belum selesai.
- Username dan NIS masih memiliki keunikan global, dan akun serta log lama belum direkonsiliasi otomatis. Penggantian constraint memerlukan perubahan identitas login, impor, integrasi, penanganan konflik, dan migrasi/rollback yang teruji; prioritas 4 belum selesai. Reset sandi lama harus melalui proses yang terverifikasi, bukan dikaitkan massal berdasarkan NISN.

### Langkah lokal tambahan

1. Backup dan uji restore dahulu. Migrasikan pada staging dengan `php artisan migrate`; kedua migrasi tanggal 2026-11-08 wajib berjalan sebelum kode baru menerima trafik. Restart worker dan jalankan `php artisan optimize:clear`.
2. Periksa dan cocokkan baris lama secara manual menggunakan sumber tepercaya, bukan NISN/NPSN atau pemilik akun saat ini saja. Jika data terbukti milik sekolah, tetapkan `tenant_id` dan `tenant_owned = true` dalam transaksi yang tercatat/auditable; cek batasan unik PTK dan ringkasan lebih dahulu. Jangan mengubah penanda `tenant_owned` pada data yatim tanpa verifikasi.
3. Jalankan tes di atas, seluruh suite, migrasi/rollback pada salinan data, dan uji dua tenant (termasuk IDOR via route binding, login dengan header palsu, akun dipindah/tenant dinonaktifkan, data lama, pekerjaan antrean, serta mode sekolah tunggal). Tambahkan uji browser dan backup/restore di lingkungan terisolasi.

`SchoolReferenceIsolationTest` dan tes lain disiapkan tetapi **belum dijalankan** di lingkungan ini. Hanya lint sintaks PHP WebAssembly dan `git diff --check` yang telah dijalankan. Laravel, PHPUnit, migrasi, database, concurrency, dan browser belum diverifikasi; prioritas 5 belum selesai. Jangan anggap perubahan siap produksi sebelum pengujian tersebut lulus dan seluruh permukaan aplikasi diaudit.
