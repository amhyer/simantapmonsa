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
- **Privasi Foto Pegawai (`User.foto`)**: foto pegawai di `IdentityCardService.php:383` masih memakai `asset('storage/...')` (disk `public`); logo sekolah/pemda tetap aset publik. Pindahkan foto pegawai ke disk `local` + route terotorisasi pada batch berikutnya.
- **Opsi (b) Queue Job untuk Reset & Generate Massal**: bila sekolah memiliki >500 akun sekaligus dan batas batch `SIMANTAP_RESET_MASSAL_MAX=100` terasa menyulitkan, pindahkan eksekusi hash ke queue job dengan hasil terenkripsi ber-TTL pendek di cache.
- **Hashing di luar transaksi `MassPasswordResetService`**: pindahkan proses `Hash::make()` (bcrypt ±270 ms/akun) ke luar `DB::transaction` agar kunci baris tabel `users` selama transaksi tetap singkat.
- **Prasyarat mode satu sekolah (`SIMANTAP_MULTI_TENANT=false`)**: tabel `tenants` harus kosong dan seluruh `users.tenant_id` harus `NULL`; jika tabel `tenants` berisi baris, akun dengan `tenant_id = NULL` akan terkena `403` global oleh `EnsureSchoolContext`. Jalur publik `GET/POST /daftar-sekolah` sudah ditutup (404) saat `multi_tenant_enabled=false`; penjagaan serupa untuk `/superadmin/tenants` (`TenantController::store`) serta perintah CLI `tenant:create` / `tenant:backfill` (opsi `--force` atau konfirmasi) dicatat untuk evaluasi lanjutan.
- Evaluasi `set_time_limit(120)` pada `GET /admin/dapodik/api/semesters` (`DapodikController.php:224`) dan pastikan HTTP timeout eksplisit bila memanggil API Dapodik.
- Ubah cetak kartu QR massal (`PresensiKartuController`, 180s) menjadi queue job + unduhan asinkron.
- **Tahap 3 — Performa `cetakSiswaMassal`**: pengukuran 100 siswa menghasilkan `406 query` (pola N+1) dan `12,3 detik` di SQLite, masih memakai `GET` dengan `set_time_limit(180)` serta 1 request gambar per foto siswa; optimasi eager loading & mekanisme cetak batch dijadwalkan di Tahap 3.
- **Pengerasan CSP (menuju Enforce tanpa `unsafe-inline`/`unsafe-eval`)**: inventaris saat ini pada `resources/views/` mencatat `89` blok `<script>` inline, `51` blok `<style>` inline, `7.093` atribut `style="..."`, dan pemakaian Alpine terbatas (`3` file Blade dengan `3` kemunculan `x-data`, `4` `@click`/`x-on:`, `0` `x-init` — aplikasi mayoritas memakai vanilla JS; Alpine tetap memerlukan `'unsafe-eval'` atau `@alpinejs/csp`). Ekstraksi bertahap dan *nonce* dijadwalkan setelah fase observasi Report-Only selesai.
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
