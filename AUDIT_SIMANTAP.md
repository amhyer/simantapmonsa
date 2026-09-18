# AUDIT SIMANTAP — Laporan Komprehensif
> Tanggal: 17 September 2026
> Versi: v2.0 (audit menyeluruh kedua)
> Auditor: opencode CLI

---

## 1. Ringkasan Kondisi Project

| Item | Detail |
|------|--------|
| **Framework** | Laravel 13.31.0 (PHP ^8.3) |
| **Frontend** | Blade + Alpine.js + Tailwind CSS + Vite 8 |
| **Database** | PostgreSQL 14+ (`simantap_db` pada port 5432) |
| **Auth** | Custom (Breeze scaffolding, field `nama_pengguna` / `kata_sandi`, peran via `peran`) |
| **Integrasi** | Google Sheets API, e-Rapor Kemdikdasmen, Dapodik WebService |
| **Total Routes** | 187 (web + API) |
| **Total Models** | 25 |
| **Total Controllers** | 58 (8 namespace) |
| **Total Blade Views** | ~100 file |
| **Total Migrations** | 42 (semua sudah jalan) |
| **Status Aplikasi** | **Bisa dijalankan** — Server dev start tanpa error, semua halaman utama bisa diakses |
| **Total Users** | 889 (1 admin, 17 guru, 871 siswa, 0 ortu asli, 0 kepsek asli) |

### Kredensial Login
| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `admin123` |
| Guru | `198603012009011003` | *(terenkripsi, perlu reset atau seed)* |
| Siswa | `siswa2026010622` | *(terenkripsi, perlu reset atau seed)* |

---

## 2. Daftar Halaman Error

### 2.1 ERROR 500 — Ortu Dashboard

| Item | Detail |
|------|--------|
| **URL** | `GET /ortu/dashboard` |
| **Status** | HTTP 500 |
| **Penyebab** | `@return` directive di Blade tidak bekerja di Laravel 13 |
| **Log** | `Attempt to read property "nama_peserta_didik" on null` |
| **File** | `resources/views/ortu/dashboard.blade.php:17` |
| **Detail** | Controller mengirim `$anak = null` saat ortu belum terhubung siswa. Blade template menggunakan `@return` pada baris 17 untuk stop rendering, tetapi `@return` bukan valid Blade directive di Laravel 13. View tetap render dan mengakses `$anak->nama_peserta_didik` (baris 24) yang menyebabkan NPE. |
| **Status** | **BUG AKTIF** |

### 2.2 Guru Routes — Timeout pada Request Pertama

| Item | Detail |
|------|--------|
| **URL** | Semua route `/guru/*` |
| **Status** | HTTP 200 (akhirnya) |
| **Penyebab** | Cold start PHP dev server + query N+1 |
| **Detail** | Request pertama ke route guru membutuhkan 5-13 detik. Bukan error, tetapi UX buruk di production. |
| **Status** | **INFO** |

---

## 3. Daftar Tombol/Fitur Tidak Berfungsi

### 3.1 Fitur dengan Bug Kritis

#### A. Ortu Dashboard (`@return` broken)
- **Lokasi**: `resources/views/ortu/dashboard.blade.php:17`
- **Masalah**: `@return` di Blade tidak bekerja di Laravel 13. Saat ortu belum terhubung siswa, halaman crash 500.
- **Saran**: Ganti `@return` dengan `@if/@else/@endif` pattern atau `@php return; @endphp`.

#### B. Google Sheets Sync — Password Hash Tersinkron
- **Lokasi**: `app/Services/GoogleSheetsService.php:198`
- **Masalah**: Tipe `pengguna` menyertakan `$model->kata_sandi` (password hash) ke Google Sheet.
- **Saran**: Hapus field `kata_sandi` dari buildRow() untuk type `pengguna`.

#### C. Backup Import — Password Hilang
- **Lokasi**: `app/Http/Controllers/Admin/BackupController.php:88`
- **Masalah**: `$fillableMap['users']` tidak menyertakan `kata_sandi`. Saat import backup, semua user kehilangan password.
- **Saran**: Sertakan `kata_sandi` di whitelist atau hash ulang secara terpisah.

#### D. Backup Export — Siswa PII Ter-expose
- **Lokasi**: `app/Http/Controllers/Admin/BackupController.php:39-45`
- **Masalah**: Export menyertakan NIK, No KK, Alamat siswa tanpa filtering.
- **Saran**: Tambah field sensitif ke daftar `except` atau encrypt sebelum export.

#### E. Quiz Submit — Race Condition
- **Lokasi**: `app/Http/Controllers/Siswa/KuisController.php:54-121`
- **Masalah**: Pengecekan `$sudahMengerjakan` dan `HasilKuis::create()` tidak atomic. Dual submit bisa menyebabkan exception 500 atau duplikat.
- **Saran**: Gunakan `DB::transaction()` + unique constraint handling.

#### F. Input Nilai — Tidak Ada Transaction
- **Lokasi**: `app/Http/Controllers/Guru/InputNilaiController.php:62-98`
- **Masalah**: `updateOrCreate` dipanggil di loop tanpa transaction. Gagal di tengah menyebabkan data inkonsisten.
- **Saran**: Bungkus dalam `DB::transaction()`.

#### G. NilaiErapot AutoSave — Missing guru_id
- **Lokasi**: `app/Http/Controllers/Guru/NilaiErapotController.php:101-141`
- **Masalah**: `firstOrNew` lookup tidak menyertakan `guru_id`. Dua guru bisa overwrite data satu sama lain.
- **Saran**: Tambahkan `guru_id` ke kriteria pencarian.

---

### 3.2 Fitur dengan Bug Medium

| # | Fitur | Lokasi | Masalah |
|---|-------|--------|---------|
| 1 | **XSS di Quiz** | `siswa/kuis/show.blade.php:109,111` | `{!! $item['pertanyaan'] !!}` menampilkan HTML tanpa sanitasi |
| 2 | **Deskripsi stale** | `NilaiErapotController::autoSave()` | `generateDeskripsi()` dipanggil sebelum `predikat` di-update ke model |
| 3 | **Missing tahun_ajaran** | `NilaiErapotController::deskripsi()` | Query tidak filter `tahun_ajaran`, bisa ambil data tahun salah |
| 4 | **TKA overwrite** | `TKAController::store()` | `updateOrCreate` uniqueness tidak include `guru_id` — guru lain bisa overwrite |
| 5 | **Filename injection** | `ErapotGeneratorController::exportExcel()` | `$kelas` langsung di-Content-Disposition tanpa sanitasi |
| 6 | **Import numerik** | `ImportErapotController::import()` | Cell Excel tidak divalidasi numerik — string "Belum" jadi value |
| 7 | **Export null siswa** | `ImportErapotController::export()` | `$n->siswa->nisn` bisa NPE jika siswa sudah dihapus |
| 8 | **User delete partial** | `UserController::deleteUserRelations()` | Tidak hapus `nilai_erapor` dan `dimensi` untuk non-guru |
| 9 | **Race condition bulk create** | `UserController::prosesAkunMassal()` | `where()->exists()` + `insert()` tidak atomic |
| 10 | **getKKM inconsistent** | `InputNilaiController::getPredikat()` | Panggil `getKKM()` tanpa guru_id — bisa ambil KKM yang salah |
| 11 | **Paste Excel incomplete** | `InputNilaiController::pasteFromExcel()` | Data parsed tapi tidak digunakan untuk buat Nilai |
| 12 | **NIS=NISN swap** | `DapodikImportController::import():121` | Fallback `nis` pakai `nisn` — field berbeda jadi sama |
| 13 | **Download bridge** | `DapodikImportController::downloadBridge()` | File .exe di public/ bisa diakses langsung |

---

## 4. Daftar Kekurangan Lain

### 4.1 Keamanan

| # | Kategori | Lokasi | Detail | Severity |
|---|----------|--------|--------|----------|
| 1 | **Password lemah** | `.env:29` | `DB_PASSWORD=admin123` — password database sangat lemah | CRITICAL |
| 2 | **Password hash export** | `GoogleSheetsService.php:198` | Hash password tersinkron ke Google Sheets | HIGH |
| 3 | **XSS** | `siswa/kuis/show.blade.php:109,111` | `{!! !!}` pada konten quiz | MEDIUM |
| 4 | **Rate limiting tidak ada** | 8+ routes write | `full-sync`, `backup/import`, `users/proses-massal`, `hapus-semua/{peran}`, `siswa/impor`, `nilai-erapor/simpan`, `mapel/*`, `semester/` | MEDIUM |
| 5 | **set_time_limit(0)** | `UserController::prosesAkunMassal():214` | Non-aktifkan timeout — potensi DoS | MEDIUM |
| 6 | **Logout tidak throttle** | `POST /logout` | CSRF logout flooding | LOW |

### 4.2 Kualitas Kode

| # | Kategori | Detail |
|---|----------|--------|
| 1 | **N+1 queries** | `TKAController::index()`, `TKAController::analysis()`, `ErapotGeneratorController::exportExcel()`, `ErapotGeneratorController::prepareData()`, `Kepsek\PetaKelasController::detail()` |
| 2 | **Missing transactions** | `InputNilaiController::simpanNilai()`, `NilaiErapotController::autoSave()` |
| 3 | **Missing error handling** | `GoogleSheetsService::getClient()`, `BackupController::export()`, `ErapotGeneratorController::exportExcel()` |
| 4 | **Unused code** | `app/Http/Requests/Auth/LoginRequest.php` tidak dipakai |
| 5 | **Route-model binding** | 6 routes pakai raw `{id}` tanpa type hint |
| 6 | **Inconsistent casting** | `UserController::prosesAkunMassal()` pakai `DB::table()->insert()` bypass Eloquent |

### 4.3 UX

| # | Detail |
|---|--------|
| 1 | Cold start timeout 5-13 detik untuk route guru |
| 2 | Backup file tidak cleanup setelah download |
| 3 | `@return` di Blade menyebabkan 500 error pada ortu dashboard |

---

## 5. Rekomendasi Prioritas Perbaikan

### PRIORITAS 1 — CRITICAL (Perlu diperbaiki sekarang)

| # | Issue | File | Fix |
|---|-------|------|-----|
| 1 | `@return` broken di 5 blade views | `ortu/dashboard.blade.php`, `siswa/dashboard.blade.php`, `siswa/nilai/index.blade.php`, `siswa/materi/index.blade.php`, `siswa/kuis/index.blade.php` | Ganti `@return` dengan `@php return; @endphp` atau restructuring `@if/@else/@endif` |
| 2 | Password hash tersync ke Google Sheets | `GoogleSheetsService.php:198` | Hapus `$model->kata_sandi` dari `buildRow()` type `pengguna` |
| 3 | Backup import kehilangan passwords | `BackupController.php:88` | Tambah `kata_sandi` ke `$fillableMap['users']` atau handle password hash secara terpisah |
| 4 | Backup export expose PII siswa | `BackupController.php:39-45` | Tambah `nik`, `no_kk`, `alamat` ke daftar `except` |
| 5 | DB password `admin123` | `.env:29` | Ganti dengan password kuat dan tambah ke `.env.example` sebagai placeholder |

### PRIORITAS 2 — HIGH (Perlu diperbaiki minggu ini)

| # | Issue | File | Fix |
|---|-------|------|-----|
| 6 | Race condition quiz submit | `KuisController.php:54-121` | Tambah `DB::transaction()` + handle `QueryException` |
| 7 | Input nilai tanpa transaction | `InputNilaiController.php:62-98` | Bungkus loop dalam `DB::transaction()` |
| 8 | NilaiErapot autoSave missing guru_id | `NilaiErapotController.php:101-141` | Tambah `guru_id` ke pencarian `firstOrNew` |
| 9 | Deskripsi stale predikat | `NilaiErapotController.php:134-138` | Set `$nilai->predikat` SEBELUM panggil `generateDeskripsi()` |
| 10 | Missing tahun_ajaran di deskripsi | `NilaiErapotController.php:149-153` | Tambah filter `tahun_ajaran` ke query |
| 11 | User delete tidak hapus semua relasi | `UserController.php:183-203` | Tambah `nilaiErapot()->delete()` dan `dimensi()->delete()` |

### PRIORITAS 3 — MEDIUM (Perlu diperbaiki bulan ini)

| # | Issue | File | Fix |
|---|-------|------|-----|
| 12 | XSS di quiz | `siswa/kuis/show.blade.php:109,111` | Ganti `{!! !!}` dengan `{{ }}` atau HTMLPurifier |
| 13 | Rate limiting tidak ada di 8+ routes | `routes/web.php` | Tambah `throttle:60,1` ke semua write routes |
| 14 | N+1 queries | 5 controllers | Tambah `with()` eager loading |
| 15 | Import numerik tidak divalidasi | `ImportErapotController.php:115-117` | Tambah `is_numeric()` check |
| 16 | Filename injection | `ErapotGeneratorController.php:138` | Sanitasi `$kelas` di Content-Disposition |
| 17 | TKA overwrite | `TKAController.php:70` | Tambah `guru_id` ke uniqueness criteria |
| 18 | Paste Excel incomplete | `InputNilaiController.php:100-118` | Implementasi matching siswa dan auto-create Nilai |
| 19 | Missing error handling | 3 services/controllers | Tambah try-catch |
| 20 | set_time_limit(0) | `UserController.php:214` | Hapus atau batasi |

### PRIORITAS 4 — LOW (Nice to have)

| # | Issue | Fix |
|---|-------|-----|
| 21 | Unused `LoginRequest.php` | Hapus file |
| 22 | Route-model binding raw `{id}` | Ganti dengan type-hinted model |
| 23 | Backup file cleanup | Hapus file setelah download |
| 24 | Cold start optimization | Pertimbangkan queue/warming untuk production |
| 25 | Logout throttle | Tambah throttle ke `POST /logout` |

---

## 6. Status Pencapaian vs Audit Sebelumnya

| Issue dari Audit v1 | Status | Catatan |
|---------------------|--------|---------|
| Controller rename (Ortu/Kepsek) | ✅ Fixed | File sudah di-rename, routes di-update |
| APP_DEBUG=false | ✅ Fixed | Sudah di `.env` |
| Demo credentials di local only | ✅ Fixed | `LandingController::demo()` |
| helpers.php auth check | ✅ Fixed | Cek `auth()->check()` sebelum `file_put_contents` |
| Force password change | ✅ Fixed | Middleware + controller bekerja |
| Ownership check guru | ✅ Fixed | Semua controller guru sudah ada ownership check |
| Rate limiting login/register | ✅ Fixed | `throttle:10,1` dan `throttle:5,1` |
| Input validation integrasi | ✅ Fixed | `IntegrasiController::updateSettings()` |
| SESSION_SECURE_COOKIE | ✅ Fixed | `false` di `.env` |
| DapodikImportLog removed | ✅ N/A | Ternyata masih dipakai di `ApiKeyController` |

---

## 7. Kesimpulan

Aplikasi SIMANTAP dalam kondisi **functional** — bisa dijalankan, login, dan navigasi ke semua halaman utama. Namun terdapat **5 critical bugs**, **6 high bugs**, dan **13+ medium issues** yang perlu diperbaikan sebelum production deployment.

**Prioritas utama:**
1. Perbaiki `@return` di 5 Blade views (causes 500 error)
2. Hapus password hash dari Google Sheets sync
3. Perbaiki backup export/import (PII + password loss)
4. Tambah transactions di critical write operations
5. Ganti password database default

**Estimasi waktu perbaikan critical + high:** ~8-12 jam developer time
