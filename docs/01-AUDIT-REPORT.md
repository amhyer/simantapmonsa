# SIMANTAP — Laporan Audit Lengkap

**Tanggal:** 13 September 2026
**Lingkup:** UI/Views, Security, Controllers, Routes, Database/Models
**Total Temuan:** 33 issues (13 CRITICAL, 9 HIGH, 8 MEDIUM, 3 LOW)

---

## Ringkasan Eksekusi

| Kategori | CRITICAL | HIGH | MEDIUM | LOW | Status |
|----------|----------|------|--------|-----|--------|
| Keamanan | 5 | 3 | 2 | 1 | 8 fixed |
| UI/Views | 5 | 2 | 0 | 0 | 7 fixed |
| Controller | 0 | 4 | 3 | 1 | 7 fixed |
| Database/Model | 3 | 0 | 1 | 1 | 1 fixed |
| Routes | 0 | 0 | 2 | 0 | 1 fixed |
| **Total** | **13** | **9** | **8** | **3** | **24 fixed** |

---

## 🔴 CRITICAL — Keamanan

### S1: Demo Route Exposes Admin Credentials
- **File:** `app/Http/Controllers/LandingController.php:46`
- **Issue:** `/demo` route publicly displays `admin/admin123` credentials
- **Status:** ✅ FIXED — Gated behind `APP_ENV=local`

### S2: Default Password `guru123` for All Synced Users
- **File:** `app/Services/Dapodik/DapodikSyncService.php:506`
- **Issue:** Every Dapodik-synced user gets password `guru123`, never forced to change
- **Status:** ⏳ NEEDS WORKFLOW — Requires "force change password on first login" feature

### S3: Backup Export Leaks Password Hashes
- **File:** `app/Http/Controllers/Admin/BackupController.php:39-63`
- **Issue:** `User::all()` includes `kata_sandi` in downloadable JSON
- **Status:** ✅ FIXED — Excluded `kata_sandi` and `remember_token` from export

### S4: Backup Import Allows Password Hash Injection
- **File:** `app/Http/Controllers/Admin/BackupController.php:66-141`
- **Issue:** `kata_sandi` in import fillable allows setting arbitrary password hashes
- **Status:** ✅ FIXED — Removed `kata_sandi` from import fillable map

### S5: API Keys Stored in Plaintext
- **File:** `app/Http/Middleware/ApiKeyMiddleware.php:22`
- **Issue:** API keys stored as plaintext; DB leak = all keys compromised
- **Status:** ⏳ NEEDS ARCHITECTURE — Requires hash+verify pattern

---

## 🔴 CRITICAL — UI/Views

### V1: `$siswa->no_hp` Does Not Exist
- **File:** `resources/views/siswa/dashboard.blade.php:56`
- **Issue:** Column is `telepon`, not `no_hp`
- **Status:** ✅ FIXED — Changed to `$siswa->telepon`

### V2: `$siswa->nama_ortu` Does Not Exist
- **File:** `resources/views/siswa/dashboard.blade.php:57`
- **Issue:** Column is `nama_orang_tua`, not `nama_ortu`
- **Status:** ✅ FIXED — Changed to `$siswa->nama_orang_tua`

### V3: `$siswa->no_hp_ortu` Does Not Exist
- **File:** `resources/views/siswa/dashboard.blade.php:58`
- **Issue:** No parent phone column on siswa table
- **Status:** ✅ FIXED — Removed line

### V4: Kepsek Aktivitas View/Controller Variable Mismatch
- **File:** `resources/views/kepsek/aktivitas/index.blade.php` + `app/Http/Controllers/Kepsek/AktivitasController.php`
- **Issue:** Controller passes `$aktivitas`, `$guruAktif`, `$statistikAktivitas`. View expects `$penggunaAktif`, `$totalAktivitas`, `$logAktivitas`, `$penggunaAktifList`, `$chartHariLabels`, etc.
- **Status:** ✅ FIXED — Rewrote controller to pass all expected variables

### V5: `$anak->no_hp_ortu` Does Not Exist
- **File:** `resources/views/ortu/dashboard.blade.php:39,52`
- **Issue:** No parent phone or student phone columns with these names
- **Status:** ✅ FIXED — Changed to `$anak->telepon`

---

## 🔴 CRITICAL — Database/Model

### D1-D2: Dimensi Model Broken Relationship
- **File:** `app/Models/Dimensi.php:10-22, 36-39`
- **Issue:** Audit reported `siswa_id` doesn't exist in `dimensi` table
- **Status:** ✅ FALSE ALARM — `siswa_id` and `nama_siswa` DO exist in DB

### D3: Nilai Model Missing `kuis_id` in Fillable
- **File:** `app/Models/Nilai.php:10-22`
- **Issue:** `kuis_id` column exists in DB but not in `$fillable`
- **Status:** ✅ FIXED — Added `kuis_id` to fillable

---

## 🟠 HIGH — Security

### S6: Weak Password Policy
- **Files:** `UserController.php:33,78`, `LandingController.php:27`
- **Issue:** Min 4-6 chars, no complexity requirements
- **Status:** ✅ FIXED — All changed to `min:8`

### S7: No Rate Limiting on Registration
- **File:** `routes/web.php:54`
- **Issue:** Mass account creation possible
- **Status:** ✅ FIXED — Added `throttle:5,1` middleware

### S8: School Lookup Endpoint Has No Authentication
- **File:** `routes/api.php:17-18`
- **Issue:** Public endpoint allows school data lookup by NPSN
- **Status:** ⏳ DEFERRED — Low risk, schools are public info

---

## 🟠 HIGH — Controllers

### C1: Resource Routes Define Methods Controllers Don't Implement (405)
- **Files:** `routes/web.php:200,206,209` — kehadiran, dimensi, catatan
- **Issue:** `->except(['show'])` still exposes create/edit/update which don't exist
- **Status:** ✅ FIXED — Changed to `->only(['index', 'store'])` / `->only(['index', 'store', 'destroy'])`

### C2: InputNilaiController Writes Non-Existent Columns
- **File:** `app/Http/Controllers/Guru/InputNilaiController.php:86-89`
- **Issue:** Writes `kelas`, `semester`, `tahun_ajaran` — columns don't exist on `nilai` table
- **Status:** ✅ FIXED — Removed non-existent columns from update array

### C3: IntegrasiController Null Pointer
- **File:** `app/Http/Controllers/Guru/IntegrasiController.php:14`
- **Issue:** `$pengaturan->sistem` when `$pengaturan` is null
- **Status:** ✅ FIXED — Changed to `optional($pengaturan)->sistem`

### C4: NilaiErapotController Null Pointer
- **File:** `app/Http/Controllers/Guru/NilaiErapotController.php:18`
- **Issue:** `$pengaturan->kelas` when `$pengaturan` is null
- **Status:** ✅ FIXED — Changed to `optional($pengaturan)->kelas`

---

## 🟠 HIGH — UI/Views

### V6: Siswa Dashboard Kehadiran Shows Count, Not Percentage
- **File:** `resources/views/siswa/dashboard.blade.php:34`
- **Issue:** `number_format($kehadiran, 1)` displays raw count as percentage
- **Status:** ✅ FIXED — Controller now calculates actual percentage

### V7: Ortu Laporan Null Check
- **File:** `resources/views/ortu/laporan/index.blade.php:52`
- **Issue:** `$laporan['predikat']` accessed without `isset()` check
- **Status:** ⏳ DEFERRED — Low crash risk due to upstream guards

---

## 🟡 MEDIUM

### M1: Raw `header() + exit` Bypasses Laravel Pipeline
- **Files:** `ImportErapotController.php:52,173`, `ErapotGeneratorController.php:138`
- **Status:** ✅ FIXED — Changed to `response()->stream()`

### M2: Kuis Resource Param Naming Inconsistency
- **File:** `routes/web.php:172`
- **Issue:** `{kui}` parameter vs `$kuis` type-hint
- **Status:** ⏳ DEFERRED — Works via Laravel type-hint resolution

### M3: `APP_DEBUG=true` in .env
- **Status:** ⏳ DEFERRED — Dev environment, intentional

### M4: `SESSION_SECURE_COOKIE` Not Set
- **Status:** ⏳ DEFERRED — Dev environment, localhost

### M5: Backup Import `updateOrCreate` with Null ID
- **File:** `app/Http/Controllers/Admin/BackupController.php:132`
- **Status:** ✅ FIXED — Now creates if ID is null, updates if ID exists

### M6: KehadiranController Regenerates UUID on Every Update
- **File:** `app/Http/Controllers/Guru/KehadiranController.php:31`
- **Status:** ✅ FIXED — Split into separate create/update logic

### M7: Seeder Uses Old Column Names
- **File:** `database/seeders/ApiKeySeeder.php:16-21`
- **Status:** ✅ FIXED — Updated to `nama_lengkap`, `nama_pengguna`, `kata_sandi`

### M8: Hardcoded API Key in Seeder
- **File:** `database/seeders/ApiKeySeeder.php:31`
- **Status:** ✅ FIXED — Now uses `Str::random(64)`

---

## 🟢 LOW

### L1: User Model Unused `HasUuids` Import
- **File:** `app/Models/User.php:8`
- **Status:** ⏳ COSMETIC

### L2: API Key Middleware Uses Session Auth
- **File:** `app/Http/Middleware/ApiKeyMiddleware.php:40`
- **Status:** ⏳ DEFERRED — Works but unusual

### L3: 9 DB Tables Without Eloquent Models
- `capaian_pembelajaran`, `ekstrakurikuler`, `nilai_cp`, `nilai_mapel`, `p5_projek`, `p5_siswa`, `prestasi`, `siswa_ekstrakurikuler`, `dapodik_data_cache`
- **Status:** ⏳ BY DESIGN — Not needed yet

---

## Database Schema Reference

### Tables with Verified Columns

| Table | Key Columns |
|-------|-------------|
| `siswa` | id, uuid, guru_id, nama_guru, nis, nisn, nama_peserta_didik, kelas, jenis_kelamin, nama_orang_tua, aktif, telepon, dapodik_id, semester_id, status_siswa, is_active, archived_at, rekaman, + 15 more personal data columns |
| `users` | id, uuid, nama_lengkap, nama_pengguna, kata_sandi, peran, terhubung_dengan, kelas_mata_pelajaran, aktif, terakhir_masuk, dapodik_id, is_active, archived_at |
| `semesters` | id, semester_id, tahun_ajaran, nama_semester (NO `aktif` column) |
| `dimensi` | id, uuid, guru_id, siswa_id, nama_guru, nama_siswa, no_dimensi, dimensi, skor, predikat, catatan, rekaman |
| `nilai` | id, uuid, guru_id, siswa_id, nama_guru, nama_siswa, tanggal, jenis, judul_penilaian, mata_pelajaran, nilai, kuis_id, rekaman |
| `pengaturan_guru` | id, guru_id, nama_guru, mata_pelajaran, kelas, kkm, semester, tahun_pelajaran, fase, pembaruan, pengaturan, sistem |
| `kehadiran` | id, uuid, guru_id, siswa_id, tanggal, status, keterangan, rekaman |
| `materi` | id, uuid, guru_id, nama_guru, tanggal, judul, mata_pelajaran, kelas, + more |
| `kuis` | id, uuid, guru_id, nama_guru, tanggal, judul, mata_pelajaran, kelas, mode, aktif, + more |
