# Backlog Pengembangan SIMANTAP MONSA

Dokumen ini mengelompokkan seluruh item backlog dan tugas lanjutan ke dalam tiga prioritas agar peluncuran tetap fokus, aman, dan terukur.

## 1. Wajib Sebelum Rilis Publik
- **Rotasi kredensial manual (pemilik)**: cabut/ganti token Dapodik yang pernah ada di `app/Services/SchoolDataService.php` & riwayat Git, ganti password akun yang pernah ter-hardcode di `dapodik_auto_pull.py`, serta audit & reset akun ber-password bawaan via `php artisan akun:audit-password-bawaan` (dry-run dulu, lalu `--apply --export=/tmp/reset-sandi-awal.csv` setelah backup DB).
- **Verifikasi prasyarat mode satu sekolah (`SIMANTAP_MULTI_TENANT=false`)**: pastikan pada database target `SELECT count(*) FROM tenants` = `0` dan `SELECT count(*) FROM users WHERE tenant_id IS NOT NULL` = `0` (jika tidak, akun `tenant_id = null` terkena `403` global oleh `app/Http/Middleware/EnsureSchoolContext.php`).
- **Cutover privasi foto siswa (`docs/CUTOVER-FOTO.md`)**: migrasi foto lama dari disk `public` ke `local` (`php artisan siswa:migrasi-foto-private`), pastikan `hanya_public = 0`, set `SIMANTAP_FOTO_FALLBACK_PUBLIC=false`, dan blokir `/storage/foto-siswa/` (`404`) di Nginx/CDN.
- **Bangun ulang Bridge & validasi URL HTTPS**: bangun ulang `SIMANTAP-Bridge.exe`, isi `DAPODIK_BRIDGE_SHA256`, dan validasi `dapodik.bridge_download_url` wajib diawali `https://` (`config/dapodik.php`).
- **Pemindaian rahasia & pembersihan riwayat Git**: jalankan `gitleaks` sebelum membuka repositori publik, dan pertimbangkan `git filter-repo` **hanya** jika repositori bersifat publik serta **setelah** seluruh kredensial dirotasi.

## 2. Segera Setelah Rilis
- **Privasi foto pegawai (`User.foto`)**: pindahkan foto pegawai di `app/Services/Presensi/IdentityCardService.php:383` dari `asset('storage/...')` (disk `public`) ke disk `local` + route terotorisasi (logo sekolah/pemda tetap aset publik).
- **Pemindai rahasia di CI**: integrasikan `gitleaks` ke `.github/workflows/ci.yml` setelah rotasi kredensial selesai.
- **S6 — Uji TTL nyata & `settings.json` multi-tenant**: tambahkan uji TTL nyata via manipulasi waktu `Carbon` untuk `IdentityCardService::svgQr` (`app/Services/Presensi/IdentityCardService.php:325`), dan pindahkan sumber data `setting()` (`app/helpers.php:19`, `storage/app/settings.json`) ke penyimpanan per-tenant sebelum mengaktifkan multi-tenant.
- **Evaluasi `set_time_limit(120)` Dapodik**: tinjau `app/Http/Controllers/Api/DapodikController.php:224` (`GET /admin/dapodik/api/semesters`) dan pastikan HTTP timeout eksplisit saat memanggil API Dapodik.
- **Pindahkan mutasi & render berat cetak kartu massal ke POST/Queue**: ubah `GET /admin/presensi/kartu-siswa/cetak-massal` (`app/Http/Controllers/Admin/PresensiKartuController.php:215`, `set_time_limit(180)`) agar tidak melakukan efek samping (penerbitan QR lazy & update `printed_at`) di request `GET`, dan jadikan unduhan asinkron berbasis queue.
- **Hashing di luar transaksi `MassPasswordResetService`**: pindahkan `Hash::make()` (bcrypt ±270 ms/akun) di `app/Services/Admin/MassPasswordResetService.php` ke luar `DB::transaction` agar kunci baris tabel `users` tetap singkat.
- **Opsi Queue Job untuk reset & generate massal >500 akun**: jika batas `SIMANTAP_RESET_MASSAL_MAX=100` terasa menyulitkan untuk sekolah besar, pindahkan eksekusi hash ke queue job dengan hasil terenkripsi ber-TTL pendek di cache.

## 3. Nanti
- **Fase 2 — Modul CBT (`SIMANTAP_CBT_ENABLED=true`) — Ekstraksi `CbtQuestionService` untuk Admin**: ekstrak logika mutasi soal (`lockForUpdate`, batas 200 soal/bank, sinkronisasi `LegacyExamMapper`) dari `app/Http/Controllers/Cbt/CbtQuestionController.php` ke `App\Services\Cbt\CbtQuestionService` dan tambahkan endpoint `/admin/cbt/banks/{bank}/questions/*` + audit `AktivitasService`.
- **Fase 2 — Modul CBT — Impor soal massal Word/Excel**: tambahkan fitur impor butir soal pilihan ganda dari Word/Excel pada Bank Soal CBT.
- **Fase 2 — Modul CBT — Uji beban & Redis**: aktifkan Redis untuk `SIMANTAP_CBT_BUFFER_ASYNC=true` dan lakukan uji beban (k6) sebelum menyalakan `SIMANTAP_CBT_ENABLED=true`.
- **Pengerasan CSP menuju Enforce tanpa `unsafe-inline`/`unsafe-eval`**: refactor bertahap `resources/views/` (tercatat `89` blok `<script>` inline, `51` `<style>` inline, `7.093` atribut `style="..."`, `381` handler event inline `onclick`/`onchange`/dll., `1` `javascript:` di `resources/views/errors/layout.blade.php:1`, serta migrasi Alpine di 3 view `x-data` ke `@alpinejs/csp` atau vanilla JS).
- **Penjagaan pembuatan tenant saat mode satu sekolah**: tambahkan penjagaan serupa `/daftar-sekolah` pada `/superadmin/tenants` (`app/Http/Controllers/SuperAdmin/TenantController.php`) serta opsi `--force`/konfirmasi pada CLI `tenant:create` dan `tenant:backfill`.
- **Optimasi kecepatan suite tes**: evaluasi `php artisan test --parallel` untuk mempercepat `composer test`.
