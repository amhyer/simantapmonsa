# SIMANTAP — Panduan Keamanan

**Tanggal:** 13 September 2026

---

## Status Keamanan Saat Ini

| Area | Status | Catatan |
|------|--------|---------|
| Password Hashing | ✅ Aman | Menggunakan `Hash::make()` (bcrypt) |
| CSRF Protection | ✅ Aman | Laravel default |
| SQL Injection | ✅ Aman | Eloquent ORM |
| XSS | ⚠️ Partial | Blade `{{ }}` auto-escape, tapi `{!! !!}` di beberapa tempat |
| Rate Limiting | ✅ Fixed | Registration `throttle:5,1` |
| Password Policy | ✅ Fixed | Minimum 8 karakter |
| Demo Route | ✅ Fixed | Hanya di `APP_ENV!=production` |
| Backup Export | ✅ Fixed | Password hash excluded |
| Backup Import | ✅ Fixed | Password hash injection prevented |
| API Key | ⚠️ Belum Fix | Plaintext storage |
| Default Password | ⚠️ Belum Fix | `guru123` untuk semua sync user |

---

## Yang Sudah Diperbaiki

### 1. Demo Route Exposed Password
- **Status:** ✅ FIXED
- **Lokasi:** `LandingController.php`
- **Perubahan:** Route hanya aktif di non-production

### 2. Backup Export Leaks Passwords
- **Status:** ✅ FIXED
- **Lokasi:** `BackupController.php`
- **Perubahan:** `kata_sandi` dan `remember_token` di-exclude dari export

### 3. Backup Import Allows Hash Injection
- **Status:** ✅ FIXED
- **Lokasi:** `BackupController.php`
- **Perubahan:** `kata_sandi` dihapus dari fillable import

### 4. Weak Password Policy
- **Status:** ✅ FIXED
- **Lokasi:** `UserController.php`, `LandingController.php`
- **Perubahan:** Semua password field minimum 8 karakter

### 5. No Rate Limiting
- **Status:** ✅ FIXED
- **Lokasi:** `routes/web.php`
- **Perubahan:** Registration rate limited 5 attempts per minute

---

## Yang Perlu Diperbaiki

### 1. Default Password `guru123`
- **Status:** ⏳ NEEDS WORKFLOW
- **Risiko:** Semua sync user share password yang sama
- **Solusi:**
  ```
  Tambah kolom: users.force_password_change (boolean)
  Workflow:
  1. Sync dari Dapodik → set force_password_change = true
  2. Login pertama → redirect ke /ganti-password
  3. Password baru harus != guru123
  4. Set force_password_change = false
  ```

### 2. API Key Plaintext
- **Status:** ⏳ NEEDS ARCHITECTURE
- **Risiko:** DB leak = semua key compromised
- **Solusi:**
  ```
  Kolom baru: api_keys.key_hash (bcrypt)
  Flow:
  1. Generate: hash($key) → simpan ke key_hash, tampilkan $key sekali saja
  2. Verify: hash_equals(hash($providedKey), $storedHash)
  3. Hapus kolom key plaintext setelah ditampilkan
  ```

---

## Production Checklist

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://simantap.yourdomain.com

SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

LOG_CHANNEL=stack
LOG_LEVEL=warning
```

### Before Deploy
- [ ] Set `APP_DEBUG=false`
- [ ] Set `SESSION_SECURE_COOKIE=true`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Verify `.env` not in webroot
- [ ] Verify `storage/` not publicly writable
- [ ] Set proper file permissions (755 dirs, 644 files)
- [ ] Configure database user with minimum required privileges

### Database Permissions (PostgreSQL)
```sql
-- User for Laravel app
CREATE USER simantap_app WITH PASSWORD 'secure_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO simantap_app;
GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO simantap_app;

-- User for backup/restore operations
CREATE USER simantap_admin WITH PASSWORD 'another_secure_password';
GRANT ALL PRIVILEGES ON DATABASE simantap_db TO simantap_admin;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO simantap_admin;
```

---

## Monitoring

### Log Files to Watch
- `storage/logs/laravel.log` — Application errors
- PostgreSQL logs — Slow queries, connection issues
- Nginx/Apache access logs — Suspicious requests

### Suspicious Patterns to Alert
- Multiple failed login attempts (>5 per minute)
- API key usage from new IP addresses
- Mass registration attempts
- Large data export requests
- Database backup downloads
