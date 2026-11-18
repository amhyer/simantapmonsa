# Backlog Pengembangan SIMANTAP MONSA

Dokumen ini mencatat item pengembangan lanjutan yang ditunda dari rilis awal agar peluncuran tetap fokus dan aman.

## Fase 2 — Modul CBT (Sebelum `SIMANTAP_CBT_ENABLED=true`)
1. **Pengelolaan Butir Soal CBT oleh Admin (Opsi a)**
   - Ekstrak logika mutasi soal (transaksi `lockForUpdate`, batas 200 soal per bank, sinkronisasi `LegacyExamMapper`) dari `App\Http\Controllers\Cbt\CbtQuestionController` ke `App\Services\Cbt\CbtQuestionService`.
   - Tambahkan halaman/endpoint admin di `/admin/cbt/banks/{bank}/questions/*` menggunakan `CbtQuestionService`, `StoreCbtQuestionRequest` (atau varian admin), dan pencatatan audit via `AktivitasService` agar admin dapat membantu guru menyusun butir soal saat mode persiapan (`cbt_enabled=false`) tanpa membuka route `/cbt/*` milik guru.
2. **Impor Soal Massal (Word / Excel)**
   - Tambahkan fitur impor butir soal pilihan ganda dari format Word/Excel pada jalur Bank Soal CBT (belum tersedia baik di sisi guru maupun admin).
3. **Uji Beban & Redis CBT**
   - Aktifkan Redis untuk `SIMANTAP_CBT_BUFFER_ASYNC=true` dan lakukan uji beban (k6) sebelum menyalakan `SIMANTAP_CBT_ENABLED=true`.

## Backlog Teknis & Keamanan Lainnya
- **Rotasi kredensial manual (Wajib oleh pemilik)**: cabut/ganti token Dapodik yang sempat ada di `SchoolDataService` & riwayat Git, ganti password akun yang dulu ter-hardcode di `dapodik_auto_pull.py`, dan reset akun yang masih memakai password default.
- **Pemindai rahasia di CI**: usulkan integrasi `gitleaks` pada `.github/workflows/` setelah rotasi selesai (belum diubah di workflow saat ini).
- **Pembersihan riwayat Git**: pertimbangkan `git filter-repo` **hanya** jika repositori bersifat publik dan **setelah** seluruh kredensial selesai dirotasi.
- **Validasi URL unduhan Bridge**: validasi `dapodik.bridge_download_url` wajib diawali `https://` pada konfigurasi produksi.
- Evaluasi `set_time_limit(120)` pada `GET /admin/dapodik/api/semesters` (`DapodikController.php:224`) dan pastikan HTTP timeout eksplisit bila memanggil API Dapodik.
- Ubah cetak kartu QR massal (`PresensiKartuController`, 180s) menjadi queue job + unduhan asinkron.
- Evaluasi `php artisan test --parallel` untuk mempercepat `composer test`.

## Usulan Pemeriksaan Akun Password Default (Read-Only)
> **PERINGATAN**: Backup database terlebih dahulu sebelum menjalankan opsi `--apply`. Jangan simpan file ekspor CSV di dalam folder repositori.

1. **Dry-run (audit read-only, tidak mengubah data)**:
   ```bash
   php artisan akun:audit-password-bawaan
   ```
   (Masukkan kata sandi bawaan yang ingin diperiksa saat diminta prompt tersembunyi; gunakan `--tenant=<id>` untuk membatasi satu sekolah.)

2. **Terapkan reset acak + ekspor sekali-pakai** (setelah backup database):
   ```bash
   php artisan akun:audit-password-bawaan --apply --export=/tmp/reset-sandi-awal.csv
   ```
