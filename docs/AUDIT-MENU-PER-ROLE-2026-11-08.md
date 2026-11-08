# Audit menu per role dan pengelompokan

> **Snapshot historis sebelum renovasi menu tahap pertama.** Untuk kondisi terbaru, lihat [audit matriks menu 8 November 2026](AUDIT-MENU-MATRIKS-2026-11-08.md); matriks route/controller/middleware dihasilkan ulang lewat `php artisan test --filter=MenuInventoryTest`.

Tanggal pemeriksaan: 8 November 2026. Acuan kode: commit `f70523d`.

## Cakupan dan batasan

Inventaris ini dibuat dengan membaca sidebar tiap role, tab admin, helper pemilihan tab, dan `routes/web.php`. Ini adalah pemeriksaan **statis**, bukan hasil membuka semua menu di browser atau menjalankan tes. PHP, Composer dan direktori `vendor` tidak tersedia pada lingkungan pemeriksaan, sehingga status HTTP, tampilan, tombol, hak akses nyata, dan operasi database belum diverifikasi secara runtime. Nama menu menunjukkan yang didefinisikan pada kode; isi aktual dapat bergantung pada konfigurasi dan data.

Terdapat tujuh role: `super_admin`, `admin`, `guru`, `siswa`, `ortu`, `kepsek`, dan `tendik`. Tombol Keluar ada di layout utama. Sebagian halaman seperti halaman cetak menggunakan layout tersendiri, bukan sidebar biasa.

## Pembaruan isolasi dashboard dan Dapodik

Perbaikan lanjutan dan langkah migrasi tercatat di [PERBAIKAN-ISOLASI-DAPODIK-2026-11-08.md](PERBAIKAN-ISOLASI-DAPODIK-2026-11-08.md). Dashboard tenant sekarang hanya menampilkan metrik yang dapat dibatasi per sekolah; log baru menggunakan `tenant_id`, bukan NPSN. Integrasi Dapodik lama yang menggunakan konfigurasi/PTK global dibatasi sementara di instalasi ber-tenant. Isolasi seluruh aplikasi belum selesai dan semua tes runtime masih perlu dijalankan di lokal/staging.

## Status perbaikan menu awal (sebelum perbaikan isolasi lanjutan)

- Status sinkron di sidebar dan notifikasi memakai cache per tenant dan pemilihan NPSN; log tanpa NPSN tidak akan terlihat pada tenant. Instalasi sekolah tunggal tanpa konteks tenant tetap menggunakan log lama.
- Penanda aktif untuk halaman detail, edit, dan buat diperbaiki; shortcut lintas tab CBT diberi keterangan tujuan.
- TKA guru dan simulasi mandiri siswa tetap ditampilkan saat flag CBT mati karena route-nya memang tidak berada di balik flag; CBT/Tryout yang terkait flag tetap tersembunyi.
- Tautan tambah sekolah membuka formulir; label grup UTAMA pada admin ditampilkan, dan grup Super Admin dinamai PENGGUNA sesuai isinya. Cakupan tes audit diperluas dan tes regresi baru ditambahkan.
- **Belum terverifikasi runtime:** PHP/Composer dan dependensi Laravel belum tersedia di lingkungan kerja. Lint sintaks PHP dengan PHP WebAssembly bukan pengganti PHPUnit atau uji browser.
- **Perlu audit keamanan lanjutan sebelum digunakan sebagai SaaS:** perbaikan di atas hanya membatasi badge/notifikasi, bukan seluruh akses log. `DashboardController::admin` menggunakan cache `admin-dashboard:v2` global dan membaca log tanpa filter; `DapodikImportController` dan lokasi lain juga masih membaca/mengubah `DapodikSyncLog` tanpa scope tenant. Beberapa log lama tidak mempunyai NPSN, dan NPSN log dapat berasal dari payload. Karena itu filter NPSN pada badge bukan jaminan isolasi data yang menyeluruh; dibutuhkan desain kepemilikan tenant, migrasi/backfill aman, pembatasan semua jalur baca/tulis, dan tes dua sekolah sebelum produksi multi-tenant.

Bagian inventaris dan temuan berikut mendokumentasikan keadaan **sebelum** perbaikan; perubahan yang disebut di atas menggantikannya untuk item terkait.

## 1. Super Admin (`super_admin`)

Sumber: `resources/views/superadmin/partials/sidebar.blade.php`.

- **Pusat Kontrol:** Dasbor Platform.
- **Multi-Tenant & Sekolah:** Katalog Sekolah; Daftarkan Sekolah Baru (tautan ke bagian tambah di halaman katalog).
- **Pengguna & Lisensi:** Semua Akun Pengguna. Pengaturan lisensi/langganan ada pada pengelolaan sekolah, bukan item menu tersendiri.

## 2. Admin sekolah (`admin`)

Sumber: `resources/views/admin/partials/sidebar.blade.php`, `resources/views/components/admin-tabs.blade.php`, dan `activeAdminTab()` di `app/helpers.php`. Sidebar berubah sesuai tab aktif.

### Tab E-Rapor

- **Utama:** Dasbor Sistem; Profile.
- **Status Penilaian:** Status per Guru; Statistik Nilai Rapor.
- **Perkembangan Nilai:** Perkembangan Nilai; Grafik Nilai Rapor.
- **Pengaturan Cetak & Rapor:** Mapping Rapor; Tanggal Rapor; Logo dan TTD; Cek Alignment.
- **Transkrip Ijazah:** Setting Transkrip; Nomor Ijazah; Input Nilai Transkrip; Import Nilai Transkrip; Cetak Transkrip Nilai.
- **Cetak Nilai:** Leger Rapor; Pelengkap Rapor; Nilai Rapor.

### Tab CBT/Ujian — hanya terlihat saat modul asesmen aktif

- **Utama:** Dasbor Utama; Statistik Ujian.
- **Manajemen Ujian CBT:** Sesi Ujian CBT; Bank Soal CBT.
- **Tryout Sekolah:** Paket Tryout; Sesi Tryout; Hasil Tryout.
- **Sistem:** Modul & Tampilan; Log Aktivitas.

### Tab TKA — hanya terlihat saat modul asesmen aktif

- **Tes Kemampuan Akademik (TKA):** Dashboard Kesiapan TKA; Dimensi & Kisi-kisi TKA; Data Penilaian Peserta; Early Warning Kesiapan.

### Tab Setup

- **Dapodik (Integrasi 1-Pintu):** Tarik Data Dapodik; Kirim Nilai Ke Dapodik.
- **Data Sekolah & Semester:** Profil Sekolah; Data Semester & Tahun Ajaran.
- **Data Guru & Siswa:** Data Guru & PTK; Data Siswa; Foto Siswa.
- **Rombel & Pembelajaran:** Rombel & Peta Kelas; Mata Pelajaran; Data Pembelajaran; Data Ekstrakurikuler; Data Kelompok Mapel.
- **Akun & Kode Akses:** Manajemen Akun; Cetak Kode Akses Login.
- **Presensi:** Presensi Pegawai & Lokasi; Import Presensi Mesin; Kartu QR Siswa & Pegawai.
- **Data Kokurikuler (P5):** Daftar Tema; Kegiatan Kokurikuler; Kelompok Kokurikuler.
- **Sistem & Modul:** Modul & Tampilan; Bobot & Ketuntasan; Penyimpanan Data; Backup & Restore; Kunci API Bridge; Log Aktivitas.

## 3. Guru (`guru`)

Sumber: `resources/views/guru/partials/sidebar.blade.php`.

- **Menu Utama:** Dasbor.
- **Pembelajaran:** Materi Ajar; Profil Lulusan.
- **Asesmen → Asesmen & Nilai:** Input Nilai Cepat; Input Nilai e-Rapor; Kuis & Soal; Daftar Nilai; Analisis Belajar.
- **CBT, Tryout & TKA** (keadaan sebelum perbaikan: hanya terlihat saat modul asesmen aktif):
  - **Ujian CBT:** Sesi Ujian CBT; Bank Soal CBT.
  - **Tryout:** Paket Tryout; Sesi Tryout.
  - **Asesmen & Simulasi TKA:** Monitoring Kesiapan TKA; Analisis Kognitif Kelas; Dimensi & Kisi-kisi TKA; Penilaian Diagnostik TKA.
- **Pemantauan:** Presensi Saya; Kehadiran Siswa; Catatan Siswa; 7 Kebiasaan.
- **Pelaporan:** Laporan & Rapor; Generate e-Rapor; Data Siswa.
- **Pengaturan:** Google Sheet; Pengaturan.

## 4. Siswa (`siswa`)

Sumber: `resources/views/siswa/partials/sidebar.blade.php`.

- **Menu Siswa:** Beranda; Kartu Pelajarku; Materi; Kuis; Nilaiku; Profil Saya.
- **Ujian & Asesmen TKA** (keadaan sebelum perbaikan: hanya terlihat saat modul asesmen aktif): Ujian CBT; Tryout Sekolah; Simulasi TKA Pusmendik.

Kartu Pelajarku menuju halaman cetak tersendiri yang menyediakan tombol kembali, bukan halaman dengan sidebar biasa.

## 5. Orang tua (`ortu`)

Sumber: `resources/views/ortu/partials/sidebar.blade.php`.

- **Menu Orang Tua:** Ringkasan.
- **Kemitraan:** Isi 7 Kebiasaan; Rekap Kebiasaan.
- **Pemantauan → Pemantauan Anak:** Perkembangan Nilai; Kehadiran Anak; Catatan Guru; Laporan / Rapor; Laporan TKA (terakhir hanya muncul saat modul asesmen aktif).

## 6. Kepala sekolah (`kepsek`)

Sumber: `resources/views/kepsek/partials/sidebar.blade.php`.

- **Menu Kepsek:** Dashboard; Rekap Sekolah; Presensi Saya.
- **Pemetaan:** Peta Kelas; Hasil Belajar.
- **Pemantauan:** Pantau Aktivitas; Rekap Kebiasaan; Log Aktivitas.

## 7. Tenaga kependidikan (`tendik`)

Sumber: `resources/views/pegawai/partials/sidebar.blade.php`; role `tendik` diarahkan ke presensi pegawai oleh route `home`.

- **Menu Pegawai:** Presensi Saya; Kartu Identitas Saya.
- **Akun & Keamanan:** Ganti Kata Sandi.

Guru dan kepala sekolah juga memiliki Presensi Saya, tetapi halaman presensi memilih sidebar sesuai role masing-masing.

## Temuan dan prioritas

### 1. [Tinggi] Status sinkronisasi Dapodik sidebar admin berpotensi tertukar antar sekolah

`resources/views/admin/partials/sidebar.blade.php` menggunakan cache tetap `admin-sidebar-sync:v2` untuk semua tenant. Query `DapodikSyncLog::orderByDesc('created_at')->first()` juga tidak tampak dibatasi sekolah dan model `DapodikSyncLog` tidak menggunakan trait scope tenant. Badge waktu sinkron pada menu **Tarik Data Dapodik** bisa mencerminkan sekolah lain dalam instalasi multi-tenant. `$syncedCount` juga disimpan dengan cache global, walaupun tidak terlihat digunakan oleh menu saat ini. Perlu verifikasi dengan dua tenant, pembatasan query menurut identitas sekolah/tenant yang benar, cache per tenant, dan tes isolasi. Jangan langsung menambah filter `tenant_id` tanpa memeriksa skema log: model saat ini memiliki `sekolah_npsn`.

### 2. [Sedang] Status menu aktif pada halaman detail sering tidak mengikuti menu induk

Sidebar memeriksa nama route yang persis sama atau `$route . '.*'`. Misalnya menu `siswa.materi.index` tidak cocok dengan route detail `siswa.materi.show`; `kepsek.rekap.index` tidak cocok dengan `kepsek.rekap.detail`; `superadmin.tenants.index` tidak cocok dengan `superadmin.tenants.show`. Akibatnya penanda aktif/submenu induk dapat hilang saat membuka detail. Gunakan pola eksplisit per menu (misalnya `siswa.materi.*`) dan uji agar tidak menandai beberapa menu sekaligus.

### 3. [Sedang] Penjagaan menu dan route TKA tidak konsisten

Seluruh grup CBT/Tryout/TKA guru dan siswa disembunyikan saat `SIMANTAP_CBT_ENABLED` mati, tetapi `guru.tka.index`, `guru.tka.analysis`, dan `siswa.tka-simulasi.*` berada di grup route guru/siswa biasa tanpa `module.asesmen`. Dengan demikian menu disembunyikan sementara URL tersebut tetap terdaftar tanpa penjagaan modul yang sama. Tentukan apakah fitur-fitur itu memang bagian modul opsional: selaraskan menu dan middleware sesuai keputusan produk. Tidak menyimpulkan bahwa pengguna tanpa hak bisa mengaksesnya; route masih memiliki middleware auth dan role.

### 4. [Sedang] Perpindahan tab admin dapat terasa tidak konsisten

Di tab CBT, **Dasbor Utama** mengarah ke `admin.dashboard` sehingga tab berubah ke E-Rapor. **Modul & Tampilan** dan **Log Aktivitas** juga tersedia pada CBT, tetapi ketika diklik helper mengklasifikasikannya sebagai Setup. Ini tidak otomatis berarti link rusak, namun pengguna berpindah konteks dan menu tersebut berada di dua tab. Putuskan apakah pengulangan memang shortcut lintas tab dan beri petunjuk, atau tetapkan satu lokasi.

### 5. [Rendah] Menu tambah sekolah menuju bagian yang masih terlipat

**Daftarkan Sekolah Baru** menuju `superadmin.tenants.index#tambah`; sasaran adalah elemen `<details id="tambah">` yang hanya diberi `open` ketika ada kesalahan validasi. Tidak terlihat logika khusus yang membuka formulir ketika tautan fragment diklik. Pengguna mungkin masih harus membuka bagian tambah secara manual; perlu konfirmasi lintas browser.

### 6. [Rendah] Penamaan dan pengelompokan dapat diperjelas

- Grup **Pengguna & Lisensi** pada Super Admin hanya menampilkan item **Semua Akun Pengguna**, sedangkan pengaturan langganan ada dalam detail sekolah. Bisa ganti nama grup atau sediakan navigasi yang jelas menuju pengelolaan lisensi.
- Pada Guru, **Data Siswa** diletakkan dalam **Pelaporan**, padahal dapat juga dianggap data/pemantauan. Ini usulan organisasi, bukan bug teknis.
- `UTAMA` pada admin didefinisikan dalam data, tetapi renderer `components/admin-menu.blade.php` tidak merender label untuk grup yang memakai `items`; yang terlihat adalah itemnya saja. Tentukan apakah label memang sengaja disembunyikan.

### 7. [Sedang] Cakupan tes menu belum membuktikan semua menu berfungsi

`tests/Feature/MenuAuditTest.php` mencakup enam role, belum memasukkan `super_admin`, dan daftar rutenya belum mengikuti seluruh menu terbaru seperti impor mesin maupun seluruh grup asesmen. Pengujiannya hanya menganggap status **500 ke atas** sebagai error; 403, 404 dan redirect yang tidak diharapkan tidak otomatis gagal. `AdminMenuTest.php` menguji sebagian navigasi dan akses, bukan seluruh menu. Kedua tes belum dijalankan di lingkungan ini. Tambahkan pemeriksaan status yang diharapkan, role positif/negatif, grup modul hidup/mati, menu aktif detail, dan tenant berbeda.

## Urutan pemeriksaan lanjutan yang disarankan

1. Siapkan lingkungan PHP/Composer dan dependensi sesuai kebutuhan proyek; jalankan tes yang tersedia dan catat hasilnya.
2. Uji setiap role memakai akun uji dan data yang sesuai; klik seluruh item menu, termasuk detail, kembali, formulir dan submenu. Periksa layar desktop dan seluler.
3. Ulangi dengan modul asesmen hidup dan mati, termasuk restart/clear konfigurasi serta route cache sesuai prosedur deployment.
4. Uji dua tenant terpisah untuk badge dan isi data; pastikan tidak ada data sekolah lain yang tampil.
5. Konfirmasi perubahan pengelompokan yang diinginkan sebelum mengubah navigasi. Audit ini tidak mengubah kode aplikasi.
