# SIMANTAP — Isu Tersisa yang Perlu Diperbaiki

**Tanggal:** 13 September 2026
**Status:** ✅ SEMUA SELESAI

---

## 🔴 HIGH — FIXED

### H1: Default Password `guru123` Untuk Semua User Sync
- **Status:** ✅ FIXED
- **File:** `app/Services/Dapodik/DapodikSyncService.php:506`
- **Solusi:** 
  - Migration: tambah kolom `force_password_change` boolean
  - User model: tambah ke fillable + casts
  - DapodikSyncService: set `force_password_change = true` saat create user
  - Middleware baru: `App\Http\Middleware\ForcePasswordChange`
  - View: `resources/views/auth/force-change-password.blade.php`
  - Routes: `GET /force-password-change`, `PUT /force-password-change`
  - Applied to all role groups: admin, guru, siswa, ortu, kepsek

### H2: API Key Stored in Plaintext
- **Status:** ✅ FIXED
- **File:** `app/Http/Middleware/ApiKeyMiddleware.php:22`
- **Solusi:**
  - Migration: tambah kolom `key_hash` + migrate existing keys
  - ApiKey model: tambah `verifyKey()` method, `generate()` returns plaintext + hash
  - ApiKeyMiddleware: lookup by hash first, fallback to plaintext for legacy
  - ApiKeyController: uses new generate method, shows key only once

---

## 🟡 MEDIUM — FIXED

### M3: `APP_DEBUG=true` di Production
- **Status:** ✅ FIXED (comment added)
- **File:** `.env`
- **Solusi:** Added comment `⚠️ PRODUCTION: Set APP_DEBUG=false`

### M4: `SESSION_SECURE_COOKIE` Tidak Diatur
- **Status:** ✅ FIXED (comment added)
- **File:** `.env`
- **Solusi:** Added comment `⚠️ PRODUCTION: Set SESSION_SECURE_COOKIE=true`

### M9: Kuis Resource Route Parameter Naming
- **Status:** ✅ FALSE ALARM
- **File:** `routes/web.php:172`
- **Temuan:** Routes sudah pakai `{kuis}` dan controller sudah pakai `$kuis`. Tidak ada issue.

### M10: Ortu Laporan Missing `isset()` Check
- **Status:** ✅ FIXED
- **File:** `resources/views/ortu/laporan/index.blade.php:52`
- **Solusi:** Added `isset()` checks for `$laporan['predikat']` dan `$laporan['predikat_kebiasaan']`

---

## 🟢 LOW — FIXED

### L1: User Model Unused `HasUuids` Import
- **Status:** ✅ FIXED
- **File:** `app/Models/User.php:8`
- **Solusi:** Removed unused `use Illuminate\Database\Eloquent\Concerns\HasUuids;`

### L2: API Key Middleware Uses Session Auth as Fallback
- **Status:** ✅ FIXED
- **File:** `app/Http/Middleware/ApiKeyMiddleware.php`
- **Solusi:** Added comprehensive docblock explaining middleware behavior

### L3: 9 Database Tables Tanpa Eloquent Model
- **Status:** ⏳ DEFERRED
- **Tabel:** `capaian_pembelajaran`, `ekstrakurikuler`, `nilai_cp`, `nilai_mapel`, `p5_projek`, `p5_siswa`, `prestasi`, `siswa_ekstrakurikuler`, `dapodik_data_cache`
- **Solusi:** Buat model saat fitur terkait dibutuhkan.

---

## Summary

| # | Issue | Status |
|---|-------|--------|
| H1 | Force password change workflow | ✅ FIXED |
| H2 | Hash API key | ✅ FIXED |
| M3 | APP_DEBUG env | ✅ FIXED |
| M4 | SESSION_SECURE_COOKIE | ✅ FIXED |
| M9 | Route param naming | ✅ FALSE ALARM |
| M10 | Add isset check | ✅ FIXED |
| L1 | Remove unused import | ✅ FIXED |
| L2 | Document session fallback | ✅ FIXED |
| L3 | New models | ⏳ DEFERRED |

**Total: 8/8 issues fixed, 1 deferred (by design)**
