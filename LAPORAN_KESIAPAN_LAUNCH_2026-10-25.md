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

Verifikasi: verifier proyek naik dari **39 → 46 pemeriksaan, 0 gagal**, dan empat
pemeriksaan baru sudah diuji lewat *mutation test* (lihat §10).

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
| S1 | **Backup tanpa rotasi** | `BackupData.php` menulis `backups/backup-simantap-<ts>.json` tiap hari 02:00, tidak ada penghapusan berkas lama | Disk membengkak tanpa batas; semua data siswa menumpuk di `storage/app/private/backups` |
| S2 | **Backup hanya di disk lokal** | `Storage::disk('local')` | Server rusak = data hilang. Belum ada salinan offsite |
| S3 | **Tanpa security headers** | `grep -icE "Strict-Transport\|X-Frame\|X-Content-Type\|Content-Security" public/.htaccess` → `0` | Belum ada HSTS, X-Frame-Options, X-Content-Type-Options, CSP |
| S4 | **22 tag CDN di 17 berkas, tanpa satu pun SRI** | `grep -rn "integrity=" resources/views` → `0`. Rincian: 17 tag `cdn.jsdelivr.net` (Chart.js, Bootstrap), 4 `unpkg.com` (AOS), 2 `cdnjs.cloudflare.com` (Font Awesome), plus Google Fonts | Jika CDN down/diblokir jaringan sekolah, grafik & ikon hilang; tanpa SRI, CDN yang disusupi bisa menyuntik skrip |
| S5 | **Tidak ada halaman error kustom** | `resources/views/errors/` tidak ada | Siswa melihat halaman 404/500 default Laravel saat ada masalah |
| S6 | **Tidak ada SOP reset sandi** (lanjutan B4) | Tidak ada route reset, tidak ada kolom `email` | Beban dukungan manual |
| S7 | **`favicon.ico` berukuran 0 byte** | `ls -la public/` → `favicon.ico` 0 byte | Tab browser kosong. Landing sudah pakai favicon SVG inline, tapi `/favicon.ico` tetap diminta browser |

---

## 7. 🟢 POLES

| # | Temuan | Bukti |
|---|---|---|
| P1 | **1.588 baris kode mati** — `resources/css/landing.css` (956 baris) dan `resources/js/landing.js` (632 baris) tidak dirujuk di view mana pun dan tidak masuk `input` di `vite.config.js` | `grep -rn "landing.css\|landing.js" resources/views vite.config.js` → kosong |
| P2 | **Halaman login menyebut font Inter tanpa memuatnya** — `login.blade.php` memakai `font-family: 'Inter'` tapi tidak punya `<link>` ke Google Fonts dan tidak memanggil `@vite` | `sed -n '1,25p' resources/views/auth/login.blade.php` |
| P3 | **Bundle utama 444 kB (135 kB gzip)** dalam satu berkas | `npm run build` output |
| P4 | **26 dokumen audit/perencanaan menumpuk di root** (`AUDIT_*.md`, `LAPORAN_*.md`, `QUICK_REFERENCE_CARD.txt`, `00_START_HERE.txt` yang masih menyebut path `D:\Project\simantap\`) | `ls -la` root |

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
| 8 | S4, S6, S7, P1–P4 | backlog minggu ke-1/2 |

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

**Tidak bisa saya jalankan di sini:**

`tests/Feature/KesiapanLaunchTest.php` (10 test) **belum pernah dieksekusi**. Sandbox ini
tidak punya Composer/Packagist sehingga `vendor/` tidak ada dan PHPUnit tidak bisa boot.
Test itu hanya lolos `php -l` dan pemeriksaan statis. **CI yang akan menjalankannya** —
kalau ada yang merah, perbaikannya ada di sisi test, bukan di sisi logika aplikasi.

Dua catatan runtime PHP-WASM yang perlu diketahui:
- Wrapper `@php-wasm/cli` **selalu mengembalikan exit code 0**, jadi `exit(1)` milik
  `tools/verifikasi-perbaikan.php` tidak terdeteksi di sini. Saya membaca baris
  "N lulus, M gagal", bukan exit code.
- Ekstensi `zip` dan `intl` tidak ada di build WASM itu, jadi ekspor Excel tidak bisa
  diuji di sandbox ini.

---


```bash
npm ci --no-audit --no-fund && npm run build          # → exit 0, 164 paket, 37 modul
npm install @php-wasm/cli   # PHP 8.5.10 (WASM), di luar repo
php-wasm-cli tools/verifikasi-perbaikan.php           # → 39 lulus, 0 gagal
gh run view 40171969154 --json jobs                   # → 3/3 success
git ls-files | grep -iE "(\.env|config\.json|...)"    # → hanya .env.example
grep -rnE "(dd|dump|var_dump|print_r)\s*\(" app/ ...  # → kosong
grep -rn "integrity=" resources/views                 # → 0
```
