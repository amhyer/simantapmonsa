# Audit statis menu dan navigasi — 8 November 2026

## Ringkasan eksekutif

Audit awal menemukan **113 entri sidebar** dari tujuh peran. Admin mempunyai 57 entri dan tiga tujuan berulang lintas tab. Ada pula aksi lama yang tampil pada mode multi-sekolah meski endpoint-nya ditolak middleware. Perubahan renovasi tahap pertama sudah merapikan duplikasi, menyelaraskan indikator mode tenant, serta menyembunyikan Bridge lama yang belum tenant-safe tanpa menutup opsi impor manual.

Audit kode tidak bisa membuktikan menu benar-benar dipakai atau tidak. Karena itu sekarang aplikasi merekam **agregat harian kunjungan halaman HTML sukses** berdasarkan route, role, dan cakupan sekolah—tanpa menyimpan user ID, IP, user-agent, session, query string, atau isi request. Gunakan data minimal 30 hari sebelum memutuskan penghapusan/penambahan menu.

## Cara menjalankan dan membaca matriks

```bash
php artisan test --filter=MenuInventoryTest
```

Tes memvalidasi bahwa rute menu terdaftar dan tidak ada rute ganda dalam tab atau lintas tab Admin. Tes membuat matriks peran, tab, label, named route, URI, `Controller@method`, middleware, dan flag modul di `storage/logs/menu-inventory.md`. GitHub Actions mengunggah hasil sebagai artifact **`menu-inventory`**.

## Snapshot hasil renovasi

| Peran | Entri | Rute unik per peran |
|---|---:|---:|
| Admin | 55 | 55 |
| Guru | 25 | 25 |
| Siswa | 9 | 9 |
| Orang tua | 8 | 8 |
| Kepala sekolah | 8 | 8 |
| Superadmin | 3 | 3 |
| Pegawai | 3 | 3 |
| **Total** | **111** | **111 dijumlahkan per peran** |

Empat tab Admin sekarang: **e-Rapor 19**, **CBT 6**, **TKA 4**, **Setup 26**. Bobot & Ketuntasan dipindahkan ke pengaturan e-Rapor agar tidak tercampur dengan keamanan/sistem. Jumlah unik lintas peran akan lebih kecil karena beberapa halaman memang dibagikan antarperan.

## Perubahan yang sudah dilakukan

### 1. Duplikasi lintas tab Admin dibersihkan

- `admin.dashboard` hanya ada di e-Rapor dan labelnya disederhanakan menjadi **Dasbor**.
- `admin.modul.index` dan `admin.log.index` hanya ada di Setup, bukan lagi pintasan kedua di tab CBT.
- Tes inventaris kini gagal bila nama route Admin muncul ulang lintas tab.

### 2. Aksi tenant yang tidak tersedia diberi status jelas

- Predicate tunggal `TenantMode::isMultiSchool()` sekarang dipakai middleware single-school/Dapodik, sidebar Admin/Kepsek, command palette, dan halaman impor presensi mesin. Kondisinya konsisten: tenant context aktif, flag multi-tenant aktif, atau data tenant sudah ada.
- Di halaman Dapodik, Bridge lama tidak ditampilkan pada mode multi-sekolah. Banner menjelaskan batasannya dan tab **Kelola Langsung** tetap tersedia untuk impor berkas per sekolah. Data PTK global tidak dipaparkan.
- Halaman impor presensi mesin tetap bisa dipakai untuk unggah file. Panel tarik langsung dan tombol yang akan ditolak middleware tidak ditampilkan saat mode tenant aktif.
- Proteksi backend tetap dipertahankan; perubahan ini tidak membuka endpoint single-school.

### 3. Setup dibuat lebih mudah dipindai tanpa menambah tab

Pemetaan Setup dirapikan menjadi kelompok dengan label yang lebih jelas: Integrasi & Impor Data, Data Sekolah & Periode, Pendidik & Peserta Didik, Kelas & Pembelajaran, Akun & Kode Akses, Presensi, Kokurikuler, dan Sistem & Keamanan. Modul dan Log hanya tampil di kelompok Sistem & Keamanan.

### 4. Pengukuran penggunaan 30–60 hari tersedia

Middleware `menu.telemetry` mencatat hanya permintaan GET bernama yang menghasilkan HTTP 200 dan `text/html` pada grup route role terautentikasi. Satu baris diagregasikan per tanggal, route, role, dan tenant; data identitas pribadi tidak disimpan. Laporan dan retensi:

```bash
php artisan menu:usage-report --days=30
php artisan menu:usage-report --days=60
php artisan menu:usage-prune
```

Scheduler menjalankan prune harian dan menyimpan maksimal 60 hari. Detail cakupan dan konfigurasi ada di [MENU-TELEMETRY.md](MENU-TELEMETRY.md). Jangan mengambil keputusan hanya dari angka klik: periksa siklus akademik, tugas role, dan status empty-state bersama pemilik proses.

## Batasan

- Named route terdaftar bukan bukti bahwa role benar-benar memakai fitur; kunjungan HTML bukan bukti penyelesaian tugas bisnis.
- Pengumpulan telemetri dapat dimatikan melalui `SIMANTAP_MENU_USAGE_TELEMETRY=false`.
- Artifact matriks berasal dari konfigurasi test/CI; flag CBT/Tryout diaktifkan oleh test agar rute opsional ikut tercatat.
- Daftar route tanpa pintu masuk sidebar tetap hanya kandidat review: halaman cetak/unduh, detail, dan alur internal dapat sah tanpa menu tersendiri.
