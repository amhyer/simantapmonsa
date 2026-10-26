# Laporan Kesiapan Launching — SIMANTAP

**Tanggal:** 25 Oktober 2026
**Branch:** `arena/fdf55ef7-simantapmonsa` (identik dengan `main` — 0 commit di depan, working tree bersih)
**Basis commit:** `a571aedd0` — *"Merge pull request #9 from amhyer/arena/00baf979-simantapmonsa"*

---

## 1. Kesimpulan

**Status: 80% siap. Kode aplikasi sehat; yang belum siap adalah sisi operasional/deployment.**

Tidak ada satu pun temuan keamanan kritis yang tersisa di kode. Yang menahan launching
adalah **5 blocker operasional** di §5 — semuanya soal konfigurasi server dan prosedur,
bukan soal kode. Estimasi penyelesaian blocker: **0,5–1 hari kerja**.

---

### ✅ PEMBARUAN 25 Okt 2026 — Blocker B1–B4 sudah dikerjakan

| Blocker | Status | Bukti |
|---|---|---|
| B1 `.env` produksi | ✅ **Selesai** | `.env.production.example` (baru) |
| B2 Panduan deployment | ✅ **Selesai** | `DEPLOYMENT.md` (baru) + `README.md` ditulis ulang |
| B3 Kredensial seeder | ✅ **Selesai** | `Database\Seeders\Support\SeedGuard` + 3 seeder dijaga |
| B4 Reset sandi admin | ✅ **Selesai** | `UserController::store()` & `update()` menandai `force_password_change` |
| B5 Keputusan CBT | ⏳ **Menunggu keputusan Anda** | Celah middleware-nya sudah ditutup di `routes/web.php` |
| — | 🔎 **Temuan baru** | 5 grup route ber-auth ternyata tanpa `force.password.change` — semuanya sudah ditutup |

Verifikasi: verifier proyek naik dari **39 → 53 pemeriksaan, 0 gagal**, dan pemeriksaan
baru sudah diuji lewat *mutation test* (lihat §10 dan §11).

### ✅ PEMBARUAN 25 Okt 2026 (tahap 2) — S1, S2, S3, S5, S7 sudah dikerjakan

| Temuan | Status | Bukti |
|---|---|---|
| S1 Rotasi backup | ✅ **Selesai** | `BackupData` merotasi otomatis; `config/backup.php` |
| S2 Salinan offsite | ✅ **Selesai** | `BACKUP_COPY_DISK`; kegagalan salinan tidak membatalkan cadangan utama |
| S3 Security headers | ✅ **Selesai** | `App\Http\Middleware\SecurityHeaders` (global) + `config/security.php` |
| S5 Halaman error | ✅ **Selesai** | `resources/views/errors/` 403, 404, 419, 429, 500, 503 |
| S7 Favicon | ✅ **Selesai** | `public/favicon.ico` 0 byte → 4.286 byte (32×32, glyph "SM") |
| S4 Aset pihak ketiga | ✅ **Selesai (26 Okt)** | Ditangani lebih baik dari SRI: **seluruh CDN dibuang**, semua aset dibundel sendiri. Lihat §13 (§11.4 mencatat alasan penundaan awalnya) |
| S6 SOP reset sandi | 🟡 **Sebagian** | SOP tertulis sudah ada di `DEPLOYMENT.md` §6; reset mandiri tetap tidak ada |

---

### ✅ PEMBARUAN 25 Okt 2026 (tahap 3) — P1, P2, P3, P4 sudah dikerjakan

| Temuan | Status | Bukti |
|---|---|---|
| P1 Kode mati | ✅ **Selesai** | 1.588 baris dihapus (`landing.css` + `landing.js`) |
| P2 Font Inter | ✅ **Selesai** | `register.blade.php` tidak lagi memanggil Google Fonts |
| P3 Bundle 444 kB | ✅ **Selesai** | 444,73 kB → **76,98 kB** (gzip 134,95 → 27,08 kB); QR dimuat malas |
| P4 Dokumen menumpuk | ✅ **Selesai** | Root 20 → 5 dokumen; 15 diarsipkan ke `docs/arsip/` |

Detail, keputusan desain, dan batas verifikasinya ada di **§12**. Verifier naik lagi
menjadi **58 pemeriksaan, 0 gagal** (56 saat pertama dikerjakan, lalu +2 check
integritas tautan setelah koreksi di §12.5).

---

| Dimensi | Nilai | Catatan |
|---|---|---|
| Kualitas kode & stabilitas | 🟢 Baik | Verifier proyek 39/39 lulus, CI hijau 3/3 job |
| Keamanan aplikasi | 🟢 Baik | 0 kredensial di repo, login throttled, API key ter-hash |
| Konfigurasi produksi | 🔴 Belum siap | Tidak ada `.env` produksi, `.env.example` masih mode lokal+debug |
| Prosedur deployment | 🔴 Belum siap | README masih template Laravel bawaan, tidak ada panduan |
| Operasi pasca-launch | 🟡 Perlu perhatian | Tidak ada jalur "lupa password", backup tanpa rotasi |

---

## 2. Yang Benar-Benar Saya Verifikasi (bukan asumsi)

Semua angka di bawah berasal dari perintah yang saya jalankan pada sesi ini.

### 2.1 Build frontend — **LULUS**

```
npm ci    → added 164 packages in 6s            (exit 0)
npm run build → vite v8.3.5, ✓ 37 modules transformed, ✓ built in 1.45s   (exit 0)
```

Output yang dihasilkan:

| Berkas | Ukuran | gzip |
|---|---|---|
| `assets/app-Dznj_hH3.js` | 444.73 kB | 134.95 kB |
| `assets/guru-dashboard-DM6IsfOk.js` | 202.10 kB | 69.24 kB |
| `assets/app-DabbrMnI.css` | 70.28 kB | 21.71 kB |
| `assets/app-CwcQUycu.css` | 46.88 kB | 9.63 kB |

### 2.2 Verifier statis resmi proyek — **39 LULUS, 0 GAGAL**

```
php tools/verifikasi-perbaikan.php
=== Ringkasan: 39 lulus, 0 gagal ===
Semua pemeriksaan lulus.
```

Skrip ini dirancang berjalan tanpa Composer/vendor/database, sehingga bisa saya jalankan
penuh. Cakupannya termasuk: sintaks seluruh berkas PHP (`token_get_all(TOKEN_PARSE)`),
keberadaan semua controller & view yang dirujuk, pemindaian kredensial, konsistensi enum
nilai, alur e-Rapor, cakupan backup vs wipe, dan 11 pemeriksaan autentikasi/kunci API.

### 2.3 CI di GitHub — **3/3 JOB HIJAU** pada commit basis branch ini

Run `40171969154`, commit `a571aedd0` (= HEAD branch ini):

| Job | Kesimpulan |
|---|---|
| PHPUnit (PHP 8.4) | ✅ success |
| PHPUnit (PHP 8.4, PostgreSQL 16) | ✅ success |
| Browser smoke (Chromium) | ✅ success |

### 2.4 Kebersihan repositori — **BERSIH**

```
git ls-files | grep -iE "(\.env|config.json|service-account|credential|\.pem|\.key|\.log)"
→ hanya .env.example
```

- Tidak ada `.env`, `dapodik-bridge/config.json`, service account, atau file kunci yang ter-commit.
- `dapodik-bridge/csv_files/*.csv` berisi **data contoh** (Ahmad Rizki, SDN Ungmongisadi 1) — bukan data siswa nyata.
- `dapodik-bridge/dapodik_response.html` hanya markup halaman login Dapodik — tidak memuat kredensial.
- Tidak ada `dd(`, `dump(`, `var_dump(` di `app/`, `routes/`, `database/`, `resources/`. Tidak ada `@dd`/`@dump` di Blade.
- `public/` hanya berisi `index.php`, `.htaccess`, `robots.txt`, `favicon.ico` — tidak ada berkas nyasar.

### 2.5 Kontrol keamanan yang sudah benar (saya baca kodenya, bukan dari dokumen lama)

| Kontrol | Bukti |
|---|---|
| Rate limit login | `LoginController.php` — 5 percobaan/IP, decay 300 dtk; `routes/web.php:71` menambah `throttle:10,1` |
| Akun nonaktif tidak bisa login | `LoginController.php` — cek `aktif` + `archived_at`, lalu logout & invalidate sesi |
| Pendaftaran publik tidak auto-aktif | `LandingController.php` — akun dibuat `aktif => false`, `throttle:5,1` |
| API key tidak disimpan polos | `ApiKeyMiddleware.php` — lookup **hanya** via `sha256`, fallback plaintext sudah dihapus |
| Wajib ganti sandi awal | `force.password.change` kini terpasang di **15** grup route ber-auth (sebelum perbaikan: 10 dari 15 — lihat koreksi di §10) |
| Telescope terkunci | `TelescopeServiceProvider.php` — wajib `peran === 'admin'`, `aktif`, dan masuk `TELESCOPE_ALLOWED_USERS` |
| API docs Scramble terkunci | `config/scramble.php` — middleware memuat `RestrictedDocsAccess` (hanya lingkungan lokal) |
| Route demo terkunci | `LandingController::demo()` — redirect ke login bila `APP_ENV != local` |
| Hash sandi tidak ikut ter-backup | `User::$hidden` memuat `kata_sandi` & `remember_token`, dipakai `BackupData` |
| CSRF & auto-escape | Laravel default; tidak ada `{!! !!}` berbahaya yang terdeteksi verifier |

### 2.6 Skala aplikasi

362 berkas PHP · 220 template Blade · 83 migrasi · 64 model · 87 controller · 46 berkas test · 377 pemanggilan `Route::` di `routes/web.php`.

---

## 3. Yang TIDAK Bisa Saya Verifikasi di Sini (dan alasannya)

Saya sampaikan apa adanya supaya Anda tidak menganggap ini sudah teruji:

1. **`php artisan test` / PHPUnit tidak saya jalankan.** Sandbox ini tidak punya `php`,
   dan Composer/Packagist tidak terjangkau dari sini — `getcomposer.org`,
   `repo.packagist.org`, dan `packagist.org` semuanya mengembalikan HTTP `000`, sehingga
   `vendor/` tidak bisa dipasang. Saya mengakalinya dengan memasang **PHP 8.5.10 via
   `@php-wasm/cli` dari npm**, yang cukup untuk menjalankan verifier proyek (§2.2) tetapi
   tidak cukup untuk memuat Laravel/PHPUnit.
   → **Bukti pengganti:** tiga job CI hijau pada commit yang sama persis (§2.3).
2. **Jumlah test & assertion persis tidak terbaca.** GitHub mengalihkan unduhan log Actions
   ke blob storage yang berada di luar host yang diizinkan di sandbox ini (HTTP `302`, 0 byte).
   Dokumen `docs/AUDIT-FUNGSI-HALAMAN-2026-10-25.md` menyebut *244 test / 1.069 assertions*
   pada basis commit yang lebih lama — angka itu **belum saya konfirmasi ulang**.
3. **Perilaku runtime (halaman benar-benar dibuka, database sungguhan) tidak saya latih.**
   Tidak ada PostgreSQL maupun SQLite terisi di sini.
4. **Kesiapan server target** (Nginx/Apache, HTTPS, PostgreSQL, izin berkas) berada di luar
   repositori — tidak bisa saya periksa dari kode.

**Rekomendasi:** jalankan `./verify.sh` di server staging sebelum go-live. Skrip itu adalah
gerbang resmi proyek ("Jika ada ✗, JANGAN deploy") dan mencakup lint, jumlah route,
timezone, PHPUnit penuh, serta `config:cache`/`route:cache`/`view:cache`.

---

## 4. Ringkasan Temuan

```
🔴 Blocker (wajib sebelum live)     : 5
🟡 Sebaiknya diperbaiki (1–2 minggu): 7
🟢 Poles (kapan saja)               : 4
```

---

## 5. 🔴 BLOCKER — Harus Selesai Sebelum Launching

### B1. Belum ada `.env` produksi

> ✅ **SELESAI 25 Okt 2026** — `.env.production.example` ditambahkan.
`.env.example` masih konfigurasi pengembangan:

```env
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug
# SESSION_SECURE_COOKIE=true     ← masih dikomentari
APP_URL=http://localhost:8000
```

`config/app.php` memang default `debug => false` dan `env => 'production'` bila variabel
kosong, tetapi **`.env.example` inilah yang akan disalin operator**. Jika disalin apa adanya,
aplikasi live akan membocorkan stack trace ke layar siswa.
**Tindakan:** siapkan `.env.production` — lihat checklist §8.

### B2. Tidak ada panduan deployment sama sekali

> ✅ **SELESAI 25 Okt 2026** — `DEPLOYMENT.md` ditambahkan, `README.md` ditulis ulang.
`README.md` masih **template Laravel bawaan** (logo Laravel, "About Laravel", "Learning
Laravel", kontak Taylor Otwell). Tidak satu pun dokumen yang menjelaskan cara deploy.

Yang **wajib** dilakukan saat deploy tetapi tidak terdokumentasi di mana pun:
- `npm run build` — `public/build` **di-gitignore**, jadi tanpa ini semua halaman tanpa CSS/JS.
- Cron `* * * * * php artisan schedule:run` — tanpa ini **backup harian 02:00 tidak jalan**,
  `tka:refresh` tidak jalan, dan `cbt:finalize-expired` tidak jalan (ujian siswa tidak difinalisasi).
- Queue worker — `QUEUE_CONNECTION=database`, jadi `php artisan queue:work` harus hidup
  (supervisor/systemd) atau export & job antrean menumpuk di tabel `jobs`.
- `php artisan config:cache && route:cache && view:cache`
- `php artisan migrate --force`

**Tindakan:** tulis `DEPLOYMENT.md` + ganti README dengan identitas SIMANTAP.

### B3. Kredensial default dari seeder tidak dipaksa ganti

> ✅ **SELESAI 25 Okt 2026** — ketiga seeder dijaga `SeedGuard`: menolak berjalan di
> production dan memaksa ganti sandi di luar `local`.
`database/seeders/DatabaseSeeder.php` membuat:

| Akun | Sandi | `force_password_change` |
|---|---|---|
| `admin` | `admin123` | ❌ tidak di-set (default DB `false`) |
| `guru` | `guru123` | ❌ tidak di-set |
| `kepsek` | `kepsek123` | ❌ tidak di-set |
| `siswa<NIS>` / `ortu<NIS>` | = NIS | ❌ tidak di-set |

Migrasi `2026_09_13_044206` memberi default `false`, jadi akun hasil seed **tidak** diarahkan
ke halaman ganti sandi. Bila seeder dijalankan di server produksi, `admin/admin123` adalah
kredensial publik yang tercantum di repositori.
**Tindakan:** jangan pernah `db:seed` di produksi; buat akun admin pertama lewat tinker
dengan `force_password_change => true`, atau ubah seeder agar menandai akunnya.

### B4. Admin yang mereset sandi tidak memicu ganti sandi wajib

> ✅ **SELESAI 25 Okt 2026** — `store()` dan `update()` di `UserController` kini menandai
> `force_password_change`. Catatan: fitur "lupa password" mandiri tetap belum ada
> (lihat S6) — yang diperbaiki adalah sandi sementara tidak lagi berlaku selamanya.
`app/Http/Controllers/Admin/UserController.php` baris 105–106:

```php
if (!empty($validated['kata_sandi'])) {
    $data['kata_sandi'] = Hash::make($validated['kata_sandi']);
}
```

`force_password_change` **tidak** ikut di-set `true`. Artinya sandi sementara yang diketik
admin berlaku selamanya. Ditambah **tidak ada fitur "lupa password" sama sekali** —
tabel `users` tidak punya kolom `email` dan tidak ada route reset — maka setiap siswa/orang
tua yang lupa sandi harus ditangani manual oleh admin, dan sandi pengganti itu tidak
pernah diganti lagi.

Untuk aplikasi dengan ratusan orang tua, ini akan jadi beban dukungan nomor satu di minggu
pertama.
**Tindakan (minimal):** set `force_password_change = true` saat admin mengganti sandi orang
lain. **Lebih baik:** tambah tombol "Reset Sandi" + SOP tertulis untuk admin.

### B5. Keputusan fitur CBT/Tryout belum diambil
`config/simantap.php` dan `.env.example`:

```php
'cbt_enabled' => env('SIMANTAP_CBT_ENABLED', false),
```

CBT **mati secara default**, dan komentarnya mensyaratkan: migrate + uji PostgreSQL +
scheduler tiap menit + uji penerimaan sekolah (lihat `docs/CBT.md`). Kalau launching
dimaksudkan termasuk ujian online, syarat-syarat itu harus dipenuhi lebih dulu. Kalau tidak,
launching bisa jalan sekarang dengan CBT tetap mati — flag off **tidak** menghapus tabel/data.

> ✅ **Celah middleware sudah ditutup 25 Okt 2026.** Total **5** grup route ber-auth
> ternyata melewatkannya — bukan 2 seperti dugaan awal (lihat koreksi di §10):
> CBT guru (439), CBT siswa (480), Tryout guru (590), TKA guru (621), TKA orang tua (643).
> Sekarang ke-15 grup ber-auth memakainya, dan `tools/verifikasi-perbaikan.php`
> menguncinya. Sisa keputusan di B5 hanya soal **kapan** CBT dinyalakan:
> - `routes/web.php:437` → `['auth', 'role:guru', CbtAccessMiddleware]`
> - `routes/web.php:477` → `['auth', 'role:siswa', CbtAccessMiddleware]`
>
> Akibatnya guru/siswa yang masih memakai sandi awal tetap bisa masuk alur ujian CBT.
> Tambahkan `'force.password.change'` ke kedua grup itu sebelum menyalakan
> `SIMANTAP_CBT_ENABLED`.

**Tindakan:** putuskan eksplisit, jangan biarkan ambigu.

---

## 6. 🟡 SEBAIKNYA DIPERBAIKI (1–2 minggu pertama)

| # | Temuan | Bukti | Dampak |
|---|---|---|---|
| S1 | ✅ **Selesai** — **Backup tanpa rotasi** | `BackupData.php` menulis `backups/backup-simantap-<ts>.json` tiap hari 02:00, tidak ada penghapusan berkas lama | Disk membengkak tanpa batas; semua data siswa menumpuk di `storage/app/private/backups` |
| S2 | ✅ **Selesai** — **Backup hanya di disk lokal** | `Storage::disk('local')` | Server rusak = data hilang. Belum ada salinan offsite |
| S3 | ✅ **Selesai** — **Tanpa security headers** | `grep -icE "Strict-Transport\|X-Frame\|X-Content-Type\|Content-Security" public/.htaccess` → `0` | Belum ada HSTS, X-Frame-Options, X-Content-Type-Options, CSP |
| S4 | ⏳ **Ditunda** — **22 tag CDN di 17 berkas, tanpa satu pun SRI** | `grep -rn "integrity=" resources/views` → `0`. Rincian: 17 tag `cdn.jsdelivr.net` (Chart.js, Bootstrap), 4 `unpkg.com` (AOS), 2 `cdnjs.cloudflare.com` (Font Awesome), plus Google Fonts | Jika CDN down/diblokir jaringan sekolah, grafik & ikon hilang; tanpa SRI, CDN yang disusupi bisa menyuntik skrip |
| S5 | ✅ **Selesai** — **Tidak ada halaman error kustom** | `resources/views/errors/` tidak ada | Siswa melihat halaman 404/500 default Laravel saat ada masalah |
| S6 | 🟡 **Sebagian** — **Tidak ada SOP reset sandi** (lanjutan B4) | Tidak ada route reset, tidak ada kolom `email` | Beban dukungan manual |
| S7 | ✅ **Selesai** — **`favicon.ico` berukuran 0 byte** | `ls -la public/` → `favicon.ico` 0 byte | Tab browser kosong. Landing sudah pakai favicon SVG inline, tapi `/favicon.ico` tetap diminta browser |

---

## 7. 🟢 POLES

| # | Temuan | Bukti |
|---|---|---|
| P1 ✅ | **1.588 baris kode mati** — `resources/css/landing.css` (956 baris) dan `resources/js/landing.js` (632 baris) tidak dirujuk di view mana pun dan tidak masuk `input` di `vite.config.js` | `grep -rn "landing.css\|landing.js" resources/views vite.config.js` → kosong |
| P2 ✅ | **Halaman login menyebut font Inter tanpa memuatnya** — `login.blade.php` memakai `font-family: 'Inter'` tapi tidak punya `<link>` ke Google Fonts dan tidak memanggil `@vite` | `sed -n '1,25p' resources/views/auth/login.blade.php` |
| P3 ✅ | **Bundle utama 444 kB (135 kB gzip)** dalam satu berkas | `npm run build` output |
| P4 ✅ | **26 dokumen audit/perencanaan menumpuk di root** (`AUDIT_*.md`, `LAPORAN_*.md`, `docs/arsip/QUICK_REFERENCE_CARD.txt`, `docs/arsip/00_START_HERE.txt` yang masih menyebut path `D:\Project\simantap\`) | `ls -la` root |

> Catatan koreksi: saya sempat menduga `guru-dashboard.js` dan `layout.css` tidak terpakai.
> Keduanya **terpakai** — `guru-dashboard.js` dimuat di `resources/views/guru/dashboard.blade.php:358`,
> dan `layout.css` di-`@import` dari `resources/css/app.css:1`. Hanya `landing.css`/`landing.js` yang benar-benar mati.

---

## 8. Checklist Go-Live (siap dicentang)

### Konfigurasi `.env` produksi
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-sekolah-anda.sch.id
APP_KEY=            # isi: php artisan key:generate
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
DB_CONNECTION=pgsql
DB_DATABASE=simantap_db
DB_USERNAME=simantap_app     # JANGAN pakai superuser postgres
DB_PASSWORD=<kuat>
TELESCOPE_ENABLED=false
SIMANTAP_CBT_ENABLED=false   # atau true bila B5 sudah diputuskan
```

### Urutan perintah di server
```bash
git clone <repo> && cd simantap
composer install --no-dev --optimize-autoloader
cp .env.example .env && nano .env          # isi sesuai blok di atas
php artisan key:generate
php artisan migrate --force                # JANGAN --seed
npm ci && npm run build                    # WAJIB — public/build tidak ada di git
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
./verify.sh                                # harus 0 gagal
php artisan down && php artisan up         # uji maintenance mode
```

### Infrastruktur (di luar aplikasi)
- [ ] Cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Queue worker hidup permanen (supervisor/systemd): `php artisan queue:work --tries=3`
- [ ] HTTPS aktif + redirect HTTP→HTTPS
- [ ] Izin berkas: direktori `755`, berkas `644`, `storage/` & `bootstrap/cache/` dapat ditulis user web
- [ ] Document root menunjuk ke `public/` (bukan root proyek)
- [ ] PostgreSQL: user aplikasi hanya `SELECT, INSERT, UPDATE, DELETE` (lihat `docs/04-SECURITY-GUIDE.md`)
- [ ] Akun admin pertama dibuat manual + `force_password_change = true`

### Hari-H
- [ ] Uji login untuk 6 peran: admin, guru, siswa, ortu, kepsek, tendik
- [ ] Uji satu alur penuh: guru input nilai → e-Rapor → cetak PDF
- [ ] Uji backup: `php artisan simantap:backup` lalu buka file hasilnya
- [ ] Uji restore di server staging (bukan produksi)
- [ ] Pantau `storage/logs/laravel.log` selama 30 menit pertama
- [ ] Siapkan SOP reset sandi untuk admin (B4/S6)

---

## 9. Rekomendasi Urutan Pengerjaan

| Prioritas | Aksi | Estimasi |
|---|---|---|
| 1 | B1 + B2 — `.env.production` + `DEPLOYMENT.md` + README baru | 2–3 jam |
| 2 | B3 + B4 — akun admin pertama & `force_password_change` saat admin reset sandi | 1–2 jam |
| 3 | B5 — putuskan status CBT/Tryout untuk launching | keputusan, 0 jam |
| 4 | Deploy ke **staging**, jalankan `./verify.sh`, uji 6 peran | 2–4 jam |
| 5 | S1 + S2 — rotasi backup + salinan offsite | 2 jam |
| 6 | S3 + S5 — security headers + halaman 404/500 kustom | 1–2 jam |
| 7 | **LAUNCH** | — |
| 8 | ~~S7, P1–P4~~ → **sudah dikerjakan**; tersisa S4 (SRI) dan S6 (reset sandi mandiri) | backlog minggu ke-1/2 |

**Perkiraan total sampai layak launching: 1–1,5 hari kerja**, dengan asumsi server target
sudah tersedia.

---

## 10. Perubahan yang Sudah Dikerjakan (25 Okt 2026)

### Berkas baru

| Berkas | Isi |
|---|---|
| `.env.production.example` | Template `.env` produksi beranotasi — `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`, `TELESCOPE_ENABLED=false`, `APP_KEY` kosong |
| `DEPLOYMENT.md` | 13 bagian: prasyarat, `.env`, PostgreSQL, build aset, akun admin pertama, cron & queue, Nginx/Apache, verifikasi, backup |
| `database/seeders/Support/SeedGuard.php` | Penjaga bersama: tolak seeder di production + penanda ganti sandi |
| `tests/Feature/KesiapanLaunchTest.php` | 10 test regresi PHPUnit |
| `LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md` | Laporan ini |

### Berkas diubah

| Berkas | Perubahan |
|---|---|
| `app/Http/Controllers/Admin/UserController.php` | `store()` membuat akun dengan `force_password_change => true`. `update()` menandainya saat admin mereset sandi orang lain (tidak untuk sandi sendiri), mencatat aktivitas "Reset Sandi" **tanpa** nilai sandi, dan menyesuaikan pesan flash |
| `database/seeders/DatabaseSeeder.php` | Pakai `SeedGuard`; menolak di production; semua akun contoh dipaksa ganti sandi di luar `local` |
| `database/seeders/DemoDataSeeder.php` | Sama |
| `database/seeders/ApiKeySeeder.php` | Pembuatan kunci API tetap boleh di production, tetapi akun admin cadangan dijaga |
| `routes/web.php` | `'force.password.change'` ditambahkan ke **5** grup route yang melewatkannya: CBT guru (439), CBT siswa (480), Tryout guru (590), TKA guru (621), TKA orang tua (643) |
| `tools/verifikasi-perbaikan.php` | Bagian 13 "Kesiapan launching": 7 pemeriksaan statis baru |
| `README.md` | Template Laravel bawaan diganti identitas SIMANTAP + tautan `DEPLOYMENT.md` |

### Cara saya memverifikasinya (dan batasnya)

**Bisa saya jalankan di sini:**

```
php -l <8 berkas PHP yang berubah>        → semuanya "No syntax errors detected"
php tools/verifikasi-perbaikan.php        → 46 lulus, 0 gagal
npm run build                             → ✓ built in 1.89s
```

> ⚠️ **Koreksi atas laporan ini sendiri.** Pada §2.5 versi awal saya menulis
> `force.password.change` terpasang di "7 grup route". Angka itu salah: saya mencarinya
> dengan `grep -nE "Route::middleware\(\[?['\"]?auth"`, yang hanya menangkap grup dengan
> `auth` sebagai elemen **pertama**. Grup seperti `['module.asesmen', 'auth', 'role:guru']`
> tidak ikut terdeteksi. Setelah pemeriksaan statis ke-5 (yang memindai **semua**
> `Route::middleware([...])` berisi `auth`) ditambahkan, terungkap total **15** grup —
> 10 sudah benar, 5 belum. Kelimanya kini diperbaiki, dan pemeriksaannya dikunci.

**Mutation test** — untuk membuktikan pemeriksaan baru benar-benar menangkap regresi
(bukan sekadar lulus karena polanya salah), saya merusak kodenya sementara lalu
menjalankan verifier:

| Mutasi | Hasil verifier |
|---|---|
| Buang `$data['force_password_change'] = true;` dari `update()` | ❌ FAIL "Admin yang mereset sandi memicu force_password_change" |
| Buang `'force.password.change'` dari grup CBT guru | ❌ FAIL "Semua grup route CBT memasang force.password.change" |
| Buang `'force.password.change'` dari grup `tka/parent` (ortu) | ❌ FAIL "Setiap grup route ber-auth memasang force.password.change" |
| Buang `'force.password.change'` dari grup `siswa` | ❌ FAIL "Setiap grup route ber-auth memasang force.password.change" |
| Buang `SeedGuard::bolehJalan()` dari `DemoDataSeeder` | ❌ FAIL "Seeder kredensial contoh dijaga SeedGuard" |
| Hapus `.env.production.example` | ❌ FAIL "Template .env produksi mematikan debug & mengamankan sesi" |
| Kembalikan semuanya | ✅ 46 lulus, 0 gagal |

Pemeriksaan pertama sempat **lulus palsu** pada percobaan awal — pola yang mencari teks
`force_password_change` dalam jendela 900 karakter ikut cocok dengan pesan flash di akhir
method. Polanya diperketat menjadi "penetapan `$data['force_password_change'] = true` harus
mengikuti `Hash::make(...)`", lalu mutation test di atas baru benar-benar gagal.

**Dijalankan CI (PR #11) — dan dibuktikan benar-benar dieksekusi:**

```
PHPUnit (PHP 8.4)                  → pass  (52s)
PHPUnit (PHP 8.4, PostgreSQL 16)   → pass  (1m21s)
Browser smoke (Chromium)           → pass  (49s)
Sourcery review                    → pass
```

`tests/Feature/KesiapanLaunchTest.php` (10 test) tidak bisa dijalankan di sandbox ini
(tidak ada `vendor/`). Karena itu hijau saja tidak cukup — file itu bisa saja terlewat
PHPUnit. Untuk membuktikannya saya menyisipkan **canary** yang sengaja gagal:

| Kondisi | PHPUnit (SQLite) | PHPUnit (PostgreSQL) | Browser smoke |
|---|---|---|---|
| Canary `$this->fail(...)` disisipkan | ❌ **fail** | ❌ **fail** | ✅ pass |
| Canary dicabut | ✅ pass | ✅ pass | ✅ pass |

Kegagalan canary membuktikan berkas itu benar-benar dimuat dan dijalankan, sehingga
hasil hijau setelahnya berarti **10 test baru itu lulus di SQLite maupun PostgreSQL**.
Canary sudah dicabut di commit berikutnya.

---


## 11. Hardening Operasional — Tahap 2 (25 Okt 2026)

### 11.1 Yang berubah

| Berkas | Perubahan |
|---|---|
| `config/backup.php` *(baru)* | `BACKUP_DISK`, `BACKUP_COPY_DISK`, `BACKUP_DIRECTORY`, `BACKUP_RETENTION_DAYS` (default 30) |
| `app/Console/Commands/BackupData.php` | Rotasi otomatis (hanya berkas `backup-simantap-*`), salinan ke disk offsite, opsi `--tanpa-rotasi`. Kegagalan salinan **tidak** membatalkan cadangan utama |
| `app/Jobs/ExportBackupJob.php` | Disk & folder mengikuti `config/backup.php`, bukan `local` hardcoded |
| `app/Http/Controllers/Admin/BackupController.php` | Daftar cadangan membaca disk yang sama dengan yang ditulis perintah |
| `config/security.php` *(baru)* | Nilai header keamanan, dapat diubah lewat `.env` |
| `app/Http/Middleware/SecurityHeaders.php` *(baru)* | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS (hanya lewat HTTPS), CSP opsional |
| `bootstrap/app.php` | Middleware didaftarkan global lewat `$middleware->append(...)` |
| `resources/views/errors/` *(baru)* | `layout` + 403, 404, 419, 429, 500, 503 — mandiri, tanpa Vite/CDN |
| `public/favicon.ico` | 0 byte → 4.286 byte, ICO 32×32 32-bit, glyph "SM" emas di atas navy |
| 6 layout/halaman auth | `<link rel="icon" href="{{ asset('favicon.ico') }}">` |
| `.env.production.example` | Variabel cadangan & keamanan |
| `DEPLOYMENT.md` | §11 ditulis ulang (rotasi kini otomatis), §8 tidak lagi menduplikasi header di Nginx |
| `tools/verifikasi-perbaikan.php` | Bagian 14: 7 pemeriksaan statis baru |
| `tests/Feature/HardeningOperasionalTest.php` *(baru)* | 18 test regresi |

### 11.2 Keputusan desain yang perlu Anda ketahui

**Permissions-Policy tidak mematikan geolokasi dan kamera.** Presensi pegawai memakai
`navigator.geolocation` (`resources/js/presensi-pegawai.js`) dan pemindaian QR memakai
`Html5Qrcode` (`resources/js/presensi-qr.js`). Memakai `geolocation=()` atau `camera=()`
akan mematikan dua fitur itu. Nilainya `(self)`.

**Content-Security-Policy sengaja TIDAK dipasang.** Alasannya di §11.4. Keputusan ini
dikunci test (`test_csp_tidak_dipasang_secara_bawaan`) supaya tidak ada yang memasangnya
tanpa memindahkan aset CDN lebih dulu.

**HSTS hanya dikirim lewat HTTPS**, dan `includeSubDomains` sengaja tidak dinyalakan —
bila ada subdomain yang belum ber-HTTPS, subdomain itu akan ikut terkunci selama setahun.

**Halaman error mandiri.** Tidak memakai Vite maupun CDN: halaman ini bisa diminta
sebelum aset frontend selesai dibangun, dan justru saat server sedang bermasalah.

### 11.3 Verifikasi

**Dijalankan lokal:**

```
npm ci + npm run build              → 164 paket, ✓ built in 1.32s
php -l 9 berkas PHP yang berubah    → semuanya "No syntax errors detected"
php tools/verifikasi-perbaikan.php  → 53 lulus, 0 gagal   (naik dari 46)
public/favicon.ico                  → struktur ICO valid (reserved=0, type=1, count=1, 32x32, 32bpp)
```

**Mutation test** — 9 mutasi, semuanya tertangkap:

| Mutasi | Hasil |
|---|---|
| Lepas `SecurityHeaders` dari `bootstrap/app.php` | ❌ FAIL |
| Ganti `geolocation=(self)` → `geolocation=()` | ❌ FAIL |
| Hapus `errors/500.blade.php` | ❌ FAIL |
| Sisipkan `@vite(` ke `errors/404.blade.php` | ❌ FAIL |
| Kosongkan `public/favicon.ico` | ❌ FAIL |
| Buang `config('backup.retensi_hari')` | ❌ FAIL |
| Buang `$disk->delete($berkas)` | ❌ FAIL |
| Buang `config('backup.salinan_disk')` | ❌ FAIL |
| Kembalikan disk hardcoded di `ExportBackupJob` | ❌ FAIL |
| Restore semua | ✅ 53 lulus, 0 gagal |

**CI menangkap satu test yang salah tulis.** Pada run pertama PR #12, dari 300 test
hanya satu yang gagal: `test_hsts_dikirim_lewat_https` —
`Failed asserting that null is identical to 'max-age=31536000'`. Penyebabnya bukan
middleware-nya, melainkan harness-nya: `$this->withServerVariables(['HTTPS' => 'on'])`
tidak sampai ke `$request->secure()`. Test-nya diganti memanggil `SecurityHeaders`
langsung dengan request yang server bag-nya dibangun eksplisit, plus assertion prekondisi
agar kondisi HTTPS benar-benar terpasang sebelum diuji.

**Dua pemeriksaan statis baru juga sempat salah dan sudah diperbaiki:**

1. Pemeriksaan "halaman error mandiri" mencari teks bebas `@vite`, sehingga ikut cocok
   dengan **kata `@vite` di dalam komentar** `errors/layout.blade.php` → FAIL palsu.
   Polanya diubah menjadi pemanggilan `@vite(`.
2. Pemeriksaan rotasi mencari teks bebas `retensi_hari`, yang masih muncul di komentar dan
   pesan konsol setelah kodenya dibuang → **lulus palsu** (mutasi M6 lolos pada percobaan
   pertama). Polanya diperketat menjadi pembacaan konfigurasi nyata
   (`config('backup.retensi_hari'`), lalu ketiga lengan mutasinya baru benar-benar gagal.

### 11.4 S4 (SRI pada CDN) — ditunda, dan mengapa

Menambahkan `integrity="sha384-..."` **tidak bisa** saya lakukan dengan jujur dari sini:
hash SRI harus dihitung dari isi berkas CDN yang sebenarnya, sedangkan `cdn.jsdelivr.net`,
`cdnjs.cloudflare.com`, dan `unpkg.com` berada di luar host yang dapat diakses sandbox ini.
Hash karangan akan membuat browser **memblokir** skripnya — grafik dan ikon hilang di
seluruh aplikasi, jauh lebih parah daripada kondisi sekarang.

Jalur yang benar (butuh jaringan penuh, bukan sandbox):

1. **Utama — self-host.** `chart.js` dan `@fortawesome/fontawesome-free` **sudah** menjadi
   dependensi npm dan sudah ter-bundle Vite (`resources/js/guru-dashboard.js` sudah
   `import Chart from 'chart.js/auto'`). Pindahkan 17 pemakaian CDN Chart.js dan 2 Font
   Awesome ke aset ter-bundle. Setelah itu SRI tidak lagi relevan karena asetnya lokal.
2. **Alternatif — kunci versi + SRI.** Hitung hash dari berkas yang benar-benar diunduh,
   lalu pasang bersamaan dengan versi yang dipatok (saat ini `unpkg.com/aos@2.3.1` sudah
   dipatok, tetapi `cdn.jsdelivr.net/npm/chart.js` **tidak**).
3. Baru setelah itu CSP (§11.2) layak dipasang.

### 11.5 S6 — sebagian

SOP reset sandi tertulis sudah ada di `DEPLOYMENT.md` §6, dan sejak tahap 1 sandi hasil
reset admin memicu ganti sandi wajib. Yang **belum** ada: reset mandiri oleh pengguna
(tabel `users` tidak punya kolom `email`, jadi reset lewat email tidak mungkin tanpa
perubahan skema). Untuk aplikasi dengan ratusan orang tua ini tetap beban dukungan nyata.

---

## 12. Poles P1–P4 — Tahap 3 (25 Okt 2026)

Keempat item 🟢 POLES sudah dikerjakan. Ringkasannya, lalu bukti dan batasnya.

### 12.1 Yang berubah

| # | Yang dilakukan | Hasil terukur |
|---|---|---|
| P1 | `git rm resources/css/landing.css resources/js/landing.js` | **1.588 baris kode mati dihapus** (956 + 632). `grep -rn "landing.css\|landing.js"` seluruh repositori → kosong. |
| P2 | Hapus `<link>` Google Fonts + 2 baris `preconnect` dari `resources/views/auth/register.blade.php`, diganti komentar penjelas berbahasa Indonesia | Ketiga halaman gerbang auth (`login`, `register`, `force-change-password`) kini konsisten: **nol permintaan font eksternal**, `'Inter'` tetap ada di awal tumpukan `font-family` sebagai *progressive enhancement*. |
| P3 | Impor statis `Html5Qrcode` di `resources/js/presensi-qr.js` diganti pemuatan malas `await import('html5-qrcode')` di dalam handler tombol "Mulai Kamera" | Bundle utama **444,73 kB → 76,98 kB** (gzip **134,95 → 27,08 kB**), turun **82,7 %**. `html5-qrcode` kini terpisah ke `esm-k8a5Y8M6.js` (369,11 kB / 108,72 kB gzip) dan **hanya diunduh saat pemindaian QR benar-benar dibuka**. |
| P4 | 15 dokumen warisan dipindah `git mv` ke `docs/arsip/` + indeks `docs/arsip/README.md`; 8 referensi silang diperbarui | Root turun dari **20 dokumen → 5** (`README.md`, `DEPLOYMENT.md`, `LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md`, `AGENTS.md`, `CLAUDE.md`). 17 tautan Markdown relatif + 16 URL absolut GitHub diperiksa, **semuanya menunjuk berkas yang ada**. *(Klaim awal "nol referensi menggantung" di sini ternyata salah — lihat §12.5.)* |

### 12.2 Keputusan desain

- **P2 — font tidak dibundel, melainkan dilepas.** Ada tiga pilihan: memuat Inter
  via Google Fonts (menambah permintaan eksternal di halaman login), membundelnya ke
  Vite (menambah ~100 kB ke aset yang diunduh semua orang), atau membiarkan
  `font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif` sebagai
  progressive enhancement. Pilihan ketiga yang dipakai — **sudah menjadi perilaku
  `login.blade.php` dan `force-change-password.blade.php`**, jadi register sekadar
  disamakan. Kalau Anda memang ingin Inter tampil persis, ini keputusan visual yang
  perlu Anda ambil, bukan perbaikan bug.
- **P3 — pemuatan malas, bukan penghapusan fitur.** `html5-qrcode` tetap dipakai;
  yang berubah hanya *kapan* ia diunduh. Konsekuensinya: saat operator menekan
  "Mulai Kamera" ada jeda pendek (~109 kB gzip) untuk mengambil chunk QR. Ini
  terjadi sekali per sesi dan di-cache browser setelahnya.
- **P4 — arsip, bukan hapus.** Dokumen-dokumen itu punya nilai sejarah dan masih
  dirujuk silang oleh beberapa audit, jadi diarsipkan beserta indeks yang
  menegaskan statusnya sebagai rekaman historis. `docs/arsip/README.md` mengarahkan
  pembaca ke dokumen yang masih berlaku.

### 12.3 Verifikasi

- **Verifier dinaikkan ke 58 pemeriksaan** (dari 53) — bagian 15 baru mengunci
  ketiganya: tidak ada aset mati di `resources/`, `html5-qrcode` wajib dimuat
  dinamis, dan root hanya boleh berisi 5 dokumen aktif + indeks arsip wajib ada.
- **Mutation test — setiap check baru dibuktikan tidak lolos kosong.** Mutasi M2
  pertama saya *gagal mendeteksi apa pun* karena perintah `sed` saya tidak cocok
  dengan isi berkas sehingga impor statisnya tidak pernah tersisip; setelah disisipkan
  lewat Python, check-nya baru terbukti bekerja. Dicatat agar jujur.

| Mutasi | Check yang diharapkan gagal | Hasil |
|---|---|---|
| M1 · kembalikan `resources/css/landing.css` | Tidak ada aset frontend mati | ❌ gagal (terdeteksi) |
| M2 · sisipkan `import { Html5Qrcode } from 'html5-qrcode'` | html5-qrcode dimuat secara malas | ❌ gagal (terdeteksi) |
| M3 · ganti `import('html5-qrcode')` jadi pemanggilan biasa | html5-qrcode dimuat secara malas | ❌ gagal (terdeteksi) |
| M4 · tambah `CATATAN_LAMA.md` di root | Root hanya berisi dokumen aktif | ❌ gagal (terdeteksi) |
| M5 · hapus `docs/arsip/README.md` | Root hanya berisi dokumen aktif | ❌ gagal (terdeteksi) |
| restore | — | ✅ 56 lulus, 0 gagal |

- `npm run build` → exit 0, `✓ built in 2.74s`. `php -l tools/verifikasi-perbaikan.php`
  → tidak ada galat sintaks.

### 12.4 Yang TIDAK saya verifikasi

- **Perilaku pemindai QR setelah refactor impor malas.** Saya tidak bisa membuka
  kamera di lingkungan ini, dan Playwright di CI hanya menyentuh halaman landing.
  Impor dinamisnya benar secara statis dan build-nya sukses, tetapi **klik tombol
  "Mulai Kamera" sekali di staging sebelum Hari-H** adalah pemeriksaan 30 detik
  yang tidak bisa saya gantikan.
- **Tampilan halaman register tanpa webfont Inter.** Perubahan ini sengaja, tapi
  efek visualnya perlu mata Anda.
- `docs/AUDIT-FUNGSI-HALAMAN-2026-10-25.md` menyebut "244 tests / 1.069 assertions".
  Angka itu tidak saya jalankan ulang — tidak ada PHPUnit lokal di lingkungan ini.

### 12.5 Koreksi: klaim "nol referensi menggantung" itu salah

Pada §12.1 di atas saya menulis bahwa setelah pemindahan P4 tidak ada referensi yang
menggantung. **Klaim itu tidak benar**, dan baru ketahuan saat verifikasi ulang
26 Oktober 2026.

**Akar masalahnya ada dua.**

1. **Regex penulis-ulang saya menolak awalan `/`.** Pola `(?<![\w/.-])NAMA_BERKAS`
   sengaja dibuat agar tidak menulis ulang referensi yang sudah berprefiks `arsip/`,
   tetapi efek sampingnya ia juga **melewati URL absolut** seperti
   `https://github.com/.../blob/main/LAPORAN_AUDIT_BUG_2026-10-14.md` — karena karakter
   sebelum nama berkas adalah `/`. Dua tautan di `docs/Audit_Mendalam_SIMANTAPMONSA.md`
   tetap menunjuk lokasi lama di root.
2. **Pemeriksaan verifikasi saya memakai substring polos.** `grep -rlF "NAMA_BERKAS"`
   mencocokkan `arsip/NAMA_BERKAS` juga, sehingga ia melaporkan 7 "referensi menggantung"
   palsu sekaligus menyembunyikan 2 yang asli. Pemeriksaan yang menghasilkan positif
   palsu tidak bisa dipercaya untuk menemukan negatif palsu.

**Yang ditemukan dan diperbaiki.**

| Temuan | Sumber | Tindakan |
|---|---|---|
| 2 URL absolut GitHub → `blob/main/LAPORAN_AUDIT_BUG_2026-10-14.md` dan `.../LAPORAN_PERBAIKAN_2026-10-14.md` | Akibat pemindahan P4 | Diarahkan ke `blob/main/docs/arsip/…` |
| 2 URL absolut GitHub → `database/migrations/2026_10_04_210653_create_cbt_test_results_table.php` dan `…_210643_create_cbt_student_answers_table.php` | **Sudah rusak sebelum P4** — kedua migrasi itu dikonsolidasi menjadi `2026_10_18_000001_create_cbt_tables.php` pada 18 Okt 2026 | Tautan diarahkan ke berkas pengganti, dengan komentar penjelas |

**Pencegahan.** Dua pemeriksaan baru ditambahkan ke verifier (bagian 15), sehingga
kasus ini tidak bisa terulang diam-diam:

- *Tautan Markdown relatif menunjuk berkas yang ada* — 17 tautan diperiksa.
- *URL GitHub absolut menunjuk berkas yang ada* — 16 URL diperiksa.

Keduanya menolak lolos bila tidak menemukan apa pun untuk diperiksa, dan keduanya
sudah diuji: menyisipkan tautan relatif rusak (M6) dan mengembalikan URL GitHub rusak
(M7) masing-masing membuat check-nya **gagal**. Verifier kini **58 lulus, 0 gagal**.

## 13. S4 — Aset Pihak Ketiga Di-self-host (26 Okt 2026)

S4 semula saya tunda karena **menghitung hash SRI membutuhkan byte asli dari CDN**, dan
host CDN tidak terjangkau dari sandbox ini (lihat §11.4). Pada 26 Okt 2026 saya
menanganinya dengan cara yang lebih baik: **membuang CDN-nya sama sekali**, sehingga
SRI tidak lagi diperlukan.

### 13.1 Temuan yang lebih buruk dari dugaan semula

Audit ulang menemukan **22 tag aset dari 5 penyedia CDN berbeda**, bukan hanya
`chart.js` dan `aos` yang saya catat di §11.4. Yang paling mengkhawatirkan:

| Aset | Dari | Jumlah | Kondisi |
|---|---|---|---|
| Chart.js | `cdn.jsdelivr.net/npm/chart.js` | **15 view** | **12 tanpa pin versi** — halaman bisa berubah perilaku kapan pun paket merilis versi baru. 3 sisanya pin `@4.4.0`, padahal `package.json` menyatakan `^4.5.1` (tidak cocok) |
| Font Awesome | `cdnjs.cloudflare.com` | 2 | pin 6.5.0 dan 6.4.0 — dua versi berbeda |
| AOS | `unpkg.com` | 2 (CSS + JS) | pin 2.3.1 |
| Bootstrap | `cdn.jsdelivr.net` | 1 | pin 5.3.0 |
| Inter | `fonts.googleapis.com` | 3 (2 preconnect + CSS) | halaman landing |
| Figtree | `fonts.bunny.net` | 2 | `layouts/guest.blade.php` |

Dua temuan tambahan yang muncul saat audit:

- **`chart.js` sudah jadi dependency npm** (`^4.5.1`) dan `guru-dashboard.js` sudah
  membundelnya untuk satu halaman — jadi 15 view lain memuat pustaka yang sama dari
  CDN tanpa alasan.
- **`tailwind.config.js` mendeklarasikan `fontFamily.sans = ['Figtree', …]`, tetapi
  `layouts/app.blade.php` tidak pernah memuat Figtree.** Hanya `layouts/guest.blade.php`
  yang memuatnya. Artinya seluruh area dalam aplikasi selama ini jatuh ke font sistem —
  bug yang satu kelas dengan P2.

### 13.2 Yang dikerjakan

| Berkas baru | Isi |
|---|---|
| `resources/js/charts.js` | `import Chart from 'chart.js/auto'; window.Chart = Chart;` |
| `resources/css/halaman-publik.css` | Inter (subset latin, bobot 400–900) + Font Awesome + AOS CSS |
| `resources/js/halaman-publik.js` | `import AOS from 'aos'` + `AOS.init(...)` |
| `resources/css/tka-statistik.css` | Bootstrap + Font Awesome |

- **15 tag `<script>` Chart.js** → `@vite('resources/js/charts.js')`.
- `resources/js/guru-dashboard.js` **dihapus** dan `guru/dashboard.blade.php` diarahkan
  ke `charts.js`. Bukti konsolidasinya persis: hash chunk-nya identik
  (`guru-dashboard-DM6IsfOk.js` → `charts-DM6IsfOk.js`, sama-sama 202,10 kB).
- Halaman landing: 3 `<link>` font + 2 AOS + skrip AOS inline → satu `@vite([...])`.
- `layouts/guest.blade.php`: 2 `<link>` bunny.net → Figtree diimpor di `app.css`,
  sehingga kini **semua** halaman (bukan hanya guest) benar-benar memuat font yang
  dideklarasikan Tailwind.
- Dependency baru di `package.json`: `aos@^2.3.1`, `bootstrap@^5.3.0`,
  `@fontsource/inter@^5.3.0`, `@fontsource/figtree@^5.3.0`. CI memakai **`npm ci`**,
  jadi `package-lock.json` yang mengunci versi pastinya.

**Hasil: nol tag aset eksternal di seluruh view aplikasi.** Yang tersisa hanyalah
tautan navigasi `<a href="https://tka.kemendikdasmen.go.id">` dan
`<a href="https://github.com/amhyer/simantapmonsa">` — itu memang seharusnya keluar.
`resources/views/vendor/scramble/*` sengaja tidak disentuh: milik paket, akan tertimpa
saat pembaruan, dan hanya aktif di lingkungan lokal.

### 13.3 Jebakan yang hampir lolos: modul Vite bersifat *deferred*

Ini bagian yang paling mudah rusak secara senyap, jadi dicatat terang-terangan.

Tag CDN lama adalah `<script src>` biasa yang **blocking** dan diletakkan di akhir
body, sehingga `window.Chart` tersedia seketika untuk skrip inline berikutnya.
`@vite()` menghasilkan `<script type="module">` yang **deferred** — dieksekusi *setelah*
dokumen selesai diparsing. Skrip inline yang langsung memanggil `new Chart(...)`
tanpa menunggu akan gagal dengan `ReferenceError: Chart is not defined`.

Saya audit ke-15 situs, dan **14 aman** (sudah dibungkus `DOMContentLoaded`), tetapi
**`resources/views/siswa/nilai/index.blade.php` tidak** — ia memanggil `new Chart(...)`
langsung. Sudah dibungkus `DOMContentLoaded`, dan sintaksnya diverifikasi dengan
`node --check` setelah ekspresi Blade dinetralkan.

Kasus AOS lebih licik lagi. Skrip inline lamanya berbunyi:

```js
if (window.AOS && typeof window.AOS.init === 'function') { window.AOS.init({ ... }); }
```

Kalau `AOS.init()` dibiarkan inline, penjaga itu membuatnya **gagal tanpa suara** —
halaman tetap 200, CI tetap hijau, animasinya saja yang mati. Karena itu `AOS.init()`
dipindahkan ke dalam `resources/js/halaman-publik.js` bersama impornya.

### 13.4 Verifikasi

- **Verifier 58 → 61 pemeriksaan, 0 gagal.** Bagian 16 baru: tidak ada aset CDN di
  view aplikasi; pustaka self-host terdaftar & terkunci di `package.json`; semua
  `input` Vite benar-benar ada di disk.
- **Mutation test M8–M11**, semuanya terdeteksi:

  | Mutasi | Hasil |
  |---|---|
  | M8 · kembalikan satu `<script src="https://cdn.jsdelivr.net/npm/chart.js">` | ❌ terdeteksi |
  | M9 · hapus `chart.js` dari `package.json` | ❌ terdeteksi |
  | M10 · set `aos` ke `"*"` | ❌ terdeteksi (versi tidak terkunci) |
  | M11 · tambah `resources/js/tidak-ada.js` ke `input` Vite | ❌ terdeteksi |
  | restore | ✅ 61 lulus, 0 gagal |

- `npm run build` → exit 0. Aset hasil build: `app-*.js` 77,14 kB (gzip 27,17) ·
  `charts-*.js` 202,10 kB (gzip 69,24) · `halaman-publik-*.css` 97,35 kB (gzip 24,43) ·
  `tka-statistik-*.css` 297,93 kB (gzip 52,45) · `halaman-publik-*.js` 14,02 kB.
  Font `figtree-latin-*.woff2` dan `inter-latin-*.woff2` ikut terbundel.
- **Test Playwright baru** (`tests/e2e/landing.spec.js`) membuktikan di Chromium
  sungguhan: nol permintaan lintas-asal; `window.Chart` berupa fungsi **dan** ada
  instance grafik yang benar-benar terbentuk; setiap elemen `[data-aos]` mendapat
  kelas `aos-init` (bukti `AOS.init()` tidak jalan terlalu dini); ikon `.fas`
  memakai font "Font Awesome" dan lebarnya > 0; ada `@font-face` Inter berstatus
  `loaded`.

  Test smoke yang lama hanya **memblokir** CDN (`route.abort()`), jadi ia membuktikan
  halaman tahan tanpa CDN — tetapi tidak membuktikan penggantinya bekerja. Test baru
  ini yang menutup celah tersebut.

  **Test ini gagal pada percobaan pertama di CI, dan itu berguna.** Assertion saya
  `expect(grafikDibuat.totalKanvas).toBeGreaterThan(0)` menerima **0** — bukan karena
  Chart.js rusak (`window.Chart` terbukti berupa fungsi, assertion sebelumnya lolos),
  melainkan karena kanvas statistik di landing berada di dalam `@if($lastUpdate)`, dan
  CI menjalankan basis data kosong tanpa data TKA. **Asumsi test saya yang salah.**

  Diperbaiki dengan menguji Chart.js lewat **kanvas sintetis** — membuat `<canvas>`
  sendiri, menggambar, memverifikasi `Chart.getChart()` mengenalinya, lalu membersihkannya.
  Cara ini justru lebih kuat: membuktikan pustakanya benar-benar berfungsi tanpa
  bergantung pada ada-tidaknya data di halaman. Assertion Inter juga dibuat
  deterministik dengan `document.fonts.load()`; tanpa itu bobot yang tidak terpakai di
  halaman tetap berstatus `unloaded` dan hasilnya bisa berubah-ubah.

### 13.5 Yang tetap tidak saya verifikasi

- **Test Playwright baru belum pernah saya jalankan sendiri** — tidak ada PHP/vendor
  di sandbox ini, jadi `php artisan serve` tidak bisa dinyalakan. Test itu baru
  terbukti saat CI menjalankannya.
- **Tampilan halaman setelah Figtree benar-benar dimuat di area dalam aplikasi.**
  Sebelumnya font itu dideklarasikan tapi tidak pernah dimuat, jadi sekarang area
  dalam aplikasi akan terlihat sedikit berbeda — memang itu tujuannya, tetapi
  perlu dilihat mata.
- Halaman `/tka-statistik` sengaja tidak saya masukkan ke test e2e agar tidak
  bergantung pada sumber data TKA eksternal.

## Lampiran: Perintah yang Saya Jalankan

```bash
npm ci --no-audit --no-fund && npm run build          # → exit 0, 164 paket, 37 modul
npm install @php-wasm/cli   # PHP 8.5.10 (WASM), di luar repo
php-wasm-cli tools/verifikasi-perbaikan.php           # → 39 lulus, 0 gagal
gh run view 40171969154 --json jobs                   # → 3/3 success
git ls-files | grep -iE "(\.env|config\.json|...)"    # → hanya .env.example
grep -rnE "(dd|dump|var_dump|print_r)\s*\(" app/ ...  # → kosong
grep -rn "integrity=" resources/views                 # → 0
```
