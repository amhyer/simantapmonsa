# Desain Teknis: MFA / 2FA (TOTP) untuk SIMANTAP MONSA

**Status**: Disetujui (Siap Implementasi — Tahap 1)  
**Tanggal**: 2026-11-26  
**Target Rilis**: Peluncuran bertahap dimulai dari Super Admin (wajib) & Admin Sekolah (opsional)

---

## 1. Ringkasan Pemahaman (Understanding Summary)

- **Apa yang dibangun**: Sistem autentikasi dua faktor berbasis **TOTP (RFC 6238, HMAC-SHA1, 6 digit, interval 30 detik)** lengkap dengan enrollment QR Code SVG (`bacon/bacon-qr-code`), challenge OTP pasca-verifikasi sandi, **8 Recovery Codes** sekali pakai, serta reset darurat (CLI artisan & panel Super Admin).
- **Mengapa dibutuhkan**: Melindungi akun berprivilese tinggi (`super_admin` dan `admin` sekolah) dari *account takeover* akibat kebocoran sandi atau *credential stuffing*.
- **Target pengguna & perluasan bertahap**:
  - **Fase 1**: Wajib (*enforced*) untuk `super_admin`, opsional (*opt-in*) untuk `admin` sekolah.
  - **Fase 2–4**: Dapat diperluas ke seluruh Admin Sekolah, Kepala Sekolah, hingga Guru cukup dengan mengubah variabel lingkungan (`SIMANTAP_MFA_ENFORCED_ROLES`, `SIMANTAP_MFA_ALLOWED_ROLES`) tanpa perubahan arsitektur.
- **Batasan utama**:
  - 100% *self-hosted* tanpa CDN dan tanpa dependency Composer tambahan (memanfaatkan `bacon/bacon-qr-code` yang sudah terpasang).
  - Kompatibel dengan PostgreSQL 16 (produksi) dan SQLite in-memory (PHPUnit).
  - Tidak menghalangi jalur impersonasi sah (`super_admin -> admin` dan `admin -> guru`).
- **Non-goals eksplisit**:
  - Tidak mencakup SMS OTP, WhatsApp OTP, atau WebAuthn/Passkey pada Tahap 1.
  - Tidak mengubah mekanisme autentikasi API Key stateless (`ApiKeyMiddleware`).

---

## 2. Persyaratan Non-Fungsional & Asumsi

| Kategori | Persyaratan / Asumsi |
|---|---|
| **Keamanan & Privasi** | `mfa_secret` dan `mfa_recovery_codes` disimpan terenkripsi (`encrypted` / `encrypted:array`) dan terdaftar pada `User::$hidden`. Recovery codes disimpan dalam bentuk hash satu arah (`sha256`), hanya ditampilkan sekali saat pembuatan. |
| **Anti-Brute-Force & Anti-Replay** | Endpoint `POST /mfa/challenge` dibatasi `throttle:5,1` per IP + pending user. `mfa_last_used_window` mencegah pemakaian ulang kode OTP yang sama dalam window 30 detik. |
| **Pre-Auth Isolation** | Sebelum challenge MFA lulus, pengguna **tidak** masuk `Auth::login()`. Sesi pra-MFA hanya menyimpan `mfa_pending_user_id` dengan masa berlaku 5 menit. |
| **Performa & Skala** | Verifikasi TOTP lokal (< 1 ms, tanpa panggilan jaringan eksternal). Toleransi *clock drift* ±1 window (30 detik sebelum/sesudah). |
| **Kill-Switch** | `SIMANTAP_MFA_ENABLED` (default `true`) memungkinkan penonaktifan cepat bila terjadi insiden darurat. |

---

## 3. Decision Log

| # | Keputusan | Alternatif yang Dipertimbangkan | Alasan Pemilihan |
|---|---|---|---|
| D1 | **Native `TotpService` (RFC 6238)** + `bacon/bacon-qr-code` yang sudah terpasang | `laravel/fortify`, `pragmarx/google2fa-laravel` | Fortify bentrok dengan custom auth multi-tenant SIMANTAP (`nama_pengguna`, `kata_sandi`). TOTP RFC 6238 ~80 baris PHP murni, zero dependency baru, mudah diaudit & dites di Laravel 13. |
| D2 | **4 kolom di tabel `users`** (`mfa_secret`, `mfa_recovery_codes`, `mfa_confirmed_at`, `mfa_last_used_window`) | Tabel terpisah `user_mfa_methods` (polimorfik) | Menerapkan YAGNI: menghindari join tambahan di setiap login serta tidak perlu mengubah registry `BackupTables` untuk Tahap 1. |
| D3 | **Pre-Auth Challenge** (belum `Auth::login()` sebelum OTP valid) | Login dulu lalu blokir dengan middleware `EnsureMfaVerified` | Menghilangkan kelas kerentanan MFA bypass lewat rute yang lupa memasang middleware. |
| D4 | **Role policy berbasis env** (`SIMANTAP_MFA_ENFORCED_ROLES`, `SIMANTAP_MFA_ALLOWED_ROLES`) | Hardcode peran di controller | Mendukung peluncuran bertahap dari Super Admin -> Admin -> Kepsek -> Guru tanpa rombak kode. |
| D5 | **Recovery Codes (8 kode hash) + CLI `mfa:reset` + Reset oleh Super Admin** | Hanya recovery codes atau reset lewat email saja | Menjamin akun tidak terkunci permanen sekaligus menghindari downgrade keamanan ke email-only recovery. |

---

## 4. Desain Final

### 4.1 Skema Database (`users`)
- `mfa_secret` (`text`, nullable) — cast `encrypted`, masuk `$hidden`.
- `mfa_recovery_codes` (`text`, nullable) — cast `encrypted:array` (berisi array hash `sha256`), masuk `$hidden`.
- `mfa_confirmed_at` (`timestamp`, nullable) — penanda MFA aktif dan telah diverifikasi.
- `mfa_last_used_window` (`bigInteger`, nullable) — *time-slice* (`floor(time() / 30)`) terakhir yang valid.

### 4.2 Konfigurasi (`config/simantap.php`)
```php
'mfa_enabled' => env('SIMANTAP_MFA_ENABLED', true),
'mfa_enforced_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIMANTAP_MFA_ENFORCED_ROLES', 'super_admin'))))),
'mfa_allowed_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIMANTAP_MFA_ALLOWED_ROLES', 'super_admin,admin'))))),
```

### 4.3 Komponen & Rute
1. **`App\Services\Auth\TotpService`**:
   - `generateSecret(int $bytes = 20): string` (Base32)
   - `provisioningUri(string $issuer, string $account, string $secret): string`
   - `qrCodeSvg(string $uri): string` (memakai `BaconQrCode`)
   - `verifyCode(string $secret, string $code, ?int $lastUsedWindow = null, int $discrepancy = 1): ?int` (mengembalikan nomor window valid atau `null`)
   - `generateRecoveryCodes(int $count = 8): array{plain: list<string>, hashes: list<string>}`
   - `consumeRecoveryCode(array $hashes, string $input): ?array` (constant-time `hash_equals`, mengembalikan sisa hash bila cocok)
2. **`App\Http\Controllers\Auth\MfaChallengeController`** (guest/pre-auth):
   - `GET /mfa/challenge` (`mfa.challenge`)
   - `POST /mfa/challenge` (`mfa.challenge.verify`, `throttle:5,1`)
3. **`App\Http\Controllers\Auth\MfaSettingsController`** (`auth`, `force.password.change`):
   - `GET /akun/keamanan/mfa` (`account.mfa.show`)
   - `POST /akun/keamanan/mfa/aktifkan` (`account.mfa.enable`, `throttle:10,1`) — wajib sandi saat ini + OTP 6 digit pertama
   - `POST /akun/keamanan/mfa/recovery` (`account.mfa.recovery`, `throttle:5,1`) — regenerasi 8 kode, wajib sandi saat ini
   - `DELETE /akun/keamanan/mfa` (`account.mfa.disable`, `throttle:5,1`) — nonaktifkan MFA (ditolak bila peran termasuk `enforced`), wajib sandi saat ini + invalidasi sesi lain via `SessionSecurity::invalidateUserSessions()`
4. **`App\Http\Middleware\EnsureMfaSetup`**:
   - Aktif pada grup rute terautentikasi: bila `mfa_enabled` dan peran masuk `mfa_enforced_roles` dan `mfa_confirmed_at === null` serta bukan sesi impersonasi (`impersonator_id` / `admin_impersonator_id`), arahkan ke `account.mfa.show` (kecuali rute `account.mfa.*`, `force-password-change.*`, `logout`).
5. **Reset Darurat**:
   - Artisan CLI: `php artisan mfa:reset {username}` (`App\Console\Commands\MfaResetCommand`).
   - Super Admin: `POST /superadmin/tenants/{tenant}/admin-mfa/reset` untuk mereset MFA admin sekolah terkait (tercatat di log audit + invalidasi sesi admin).

### 4.4 Strategi Verifikasi (PHPUnit)
- **`tests/Unit/TotpServiceTest.php`**: uji encoding Base32, kesesuaian vektor RFC 6238, toleransi *clock drift* ±1 window, penolakan *replay* pada window yang sama/lebih lama, serta hashing & konsumsi sekali pakai recovery code.
- **`tests/Feature/MfaAuthenticationTest.php`**:
  - Login user ber-MFA tidak langsung terautentikasi (`assertGuest()`) sebelum challenge lulus dan tidak bisa bypass langsung ke `/superadmin` atau `/admin`.
  - Challenge OTP valid menyelesaikan login + regenerasi sesi + anti-replay.
  - Challenge recovery code valid hanya berlaku sekali pakai.
  - Rate limiting pada `POST /mfa/challenge`.
  - `super_admin` tanpa MFA dipaksa ke `/akun/keamanan/mfa` oleh `EnsureMfaSetup`, sementara sesi impersonasi tetap berjalan lancar.
  - Perintah `php artisan mfa:reset` dan reset MFA oleh Super Admin berfungsi & menghapus sesi aktif target.
