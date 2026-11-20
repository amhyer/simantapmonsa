# Panduan Deploy Produksi — SIMANTAP MONSA

Panduan berurutan untuk operator server (Ubuntu/Debian Linux) dalam menyiapkan, mendeploy, memantau, dan melakukan rollback aplikasi **SIMANTAP MONSA** secara aman.

---

## 1. Prasyarat Server

Versi PHP produksi **harus sama** dengan versi yang diuji di CI GitHub Actions (`.github/workflows/ci.yml`: **PHP 8.4**).

| Komponen | Versi Disarankan | Keterangan |
|---|---|---|
| **OS** | Ubuntu 22.04 / 24.04 LTS | Disarankan server Linux khusus |
| **PHP-FPM & CLI** | **8.4** (`composer.json`: `^8.4`) | Ekstensi wajib (`php8.4-*`): `pdo_pgsql`, `pgsql`, `mbstring`, `xml`, `ctype`, `json`, `bcmath`, `gd`, `zip`, `fileinfo`, `intl`, `openssl`, `curl` |
| **PostgreSQL** | **16** | Database utama aplikasi |
| **Nginx** | Stable terbaru | Reverse proxy + TLS termination |
| **Node.js & npm** | **20 LTS** | Hanya dibutuhkan saat build aset (`npm run build`), atau build di CI/mesin lain lalu unggah folder `public/build` |
| **Supervisor** | Paket OS `supervisor` | Menjalankan worker antrean (`queue:work`) |
| **Cron** | Paket OS `cron` | Menjalankan penjadwal Laravel (`schedule:run`) tiap menit |
| **Redis** *(opsional)* | 7.x | Direkomendasikan bila trafik tinggi untuk `CACHE_STORE` dan `SESSION_DRIVER` |

Contoh pengaturan `php.ini` (`/etc/php/8.4/fpm/conf.d/99-simantap.ini`):
```ini
upload_max_filesize = 105M
post_max_size = 110M
memory_limit = 512M
max_execution_time = 120
```

---

## 2. Persiapan `.env` Produksi

Salin `.env.example` menjadi `.env`, buat kunci aplikasi (`php artisan key:generate`), dan pastikan izin file `.env` terbatas (`chmod 600 .env`). Seluruh kunci di bawah diturunkan dari `config/*.php` dan tersedia di `.env.example`.

| Kunci ENV | Default Kode | Wajib / Opsional | Nilai Rilis Disarankan | Keterangan |
|---|---|---|---|---|
| `APP_NAME` | `Laravel` | Wajib | `SIMANTAP_MONSA` | Nama aplikasi |
| `APP_ENV` | `production` | **Wajib** | `production` | Mode produksi |
| `APP_DEBUG` | `false` | **Wajib** | **`false`** | **Jangan pernah `true` di produksi** |
| `APP_KEY` | *(kosong)* | **Wajib** | `base64:...` | Dihasilkan via `php artisan key:generate` |
| `APP_URL` | `http://localhost` | **Wajib** | `https://domain-sekolah.sch.id` | Wajib HTTPS |
| `APP_TIMEZONE` | `Asia/Makassar` | Wajib | `Asia/Makassar` | Sesuaikan zona waktu sekolah |
| `APP_LOCALE` | `id` | Opsional | `id` | Bahasa antarmuka |
| `LOG_CHANNEL` | `stack` | Wajib | `daily` | Rotasi log harian (`LOG_DAILY_DAYS=14`) |
| `LOG_LEVEL` | `debug` | Wajib | `warning` atau `info` | Hindari `debug` di produksi |
| `DB_CONNECTION` | `pgsql` | **Wajib** | `pgsql` | PostgreSQL 16 |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` | **Wajib** | Host & port DB | — |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | — | **Wajib** | Kredensial kuat | Jangan pakai user `postgres` superuser bila memungkinkan |
| `SESSION_DRIVER` | `database` | Wajib | `database` atau `redis` | Lihat catatan Database vs Redis di bawah |
| `SESSION_SECURE_COOKIE` | `null` | **Wajib (HTTPS)** | **`true`** | Cookie sesi hanya dikirim lewat HTTPS |
| `CACHE_STORE` | `database` | Wajib | `database` atau `redis` | Lihat catatan Database vs Redis di bawah |
| `QUEUE_CONNECTION` | `database` | Wajib | `database` atau `redis` | Wajib dijalankan oleh Supervisor |
| `SIMANTAP_MULTI_TENANT` | `false` | **Wajib** | **`false`** (rilis 1 sekolah) | Prasyarat: tabel `tenants` kosong & `users.tenant_id` semua `NULL` |
| `SIMANTAP_CBT_ENABLED` | `false` | Wajib | **`false`** (Fase 1) | Aktifkan hanya setelah uji beban & Redis siap |
| `SIMANTAP_TRUSTED_PROXIES` | `''` | **Wajib di belakang proxy** | `127.0.0.1` (atau CIDR proxy) | **JANGAN** pakai `*` bila server bisa diakses langsung |
| `SIMANTAP_CSP_REPORT_ONLY` | `false` | Opsional | `true` (fase observasi awal) | Mengirim laporan pelanggaran ke `/csp-report` tanpa memblokir UI |
| `SECURITY_HEADERS_ENABLED` | `true` | Wajib | `true` | Aktifkan header keamanan & HSTS |
| `SECURITY_HSTS_MAX_AGE` | `31536000` | Opsional | `31536000` | 1 tahun (aktif otomatis pada request HTTPS) |
| `TELESCOPE_ENABLED` | `true` (jika terpasang) | **Wajib** | **`false`** | Paket `laravel/telescope` ada di `require-dev`, aman `--no-dev` |
| `SIMANTAP_FOTO_FALLBACK_PUBLIC` | `true` | Wajib | `false` setelah cutover | Lihat `docs/CUTOVER-FOTO.md` |
| `SIMANTAP_FOTO_SISWA_PER_PAGE` | `50` | Opsional | `50` | Paginasi galeri foto siswa |
| `SIMANTAP_FOTO_ZIP_MAX_ENTRIES` | `200` | Opsional | `200` | Batas file per unggah ZIP foto |
| `SIMANTAP_FOTO_ZIP_MAX_UNCOMPRESSED_BYTES` | `104857600` | Opsional | `104857600` (100 MB) | Proteksi zip bomb |
| `SIMANTAP_FORCE_CHANGE_SISWA` | `true` | Opsional | `true` | Paksa ganti sandi awal siswa |
| `SIMANTAP_RESET_MASSAL_MAX` | `100` | Opsional | `100` | Batas akun per permintaan reset/generate massal |
| `SIMANTAP_MESIN_HOST` | `''` | Opsional | **Kosong** (hosting internet) | Gunakan jalur Bridge; isi hanya bila server on-premise satu LAN dengan ZKTeco |
| `SIMANTAP_MESIN_PORT` / `SIMANTAP_MESIN_LIVE_TIMEOUT` | `4370` / `3` | Opsional | `4370` / `3` | Connect timeout `/mesin/live` maks 3 detik |
| `DAPODIK_TOKEN` / `DAPODIK_HOST` / `DAPODIK_PORT` | — | Opsional | Token baru hasil rotasi | Untuk integrasi Dapodik |
| `DAPODIK_BRIDGE_DOWNLOAD_URL` / `DAPODIK_BRIDGE_SHA256` | — | Opsional | URL HTTPS + SHA-256 `.exe` baru | Untuk unduhan SIMANTAP-Bridge |
| `GOOGLE_CREDENTIALS_JSON` | — | Opsional | JSON service account (1 baris) | Opsional bila sinkron Google Sheets dipakai |
| `BACKUP_DISK` / `BACKUP_DIRECTORY` / `BACKUP_RETENTION_DAYS` | `local` / `backups` / `14` | Opsional | `local` / `backups` / `14` | Retensi backup JSON otomatis |
| `TKA_KESIAPAN_AKTIF` / `SIMANTAP_MENU_USAGE_TELEMETRY` | `true` / `true` | Opsional | `true` / `true` | Modul Kesiapan TKA & telemetri menu anonim |
| `SIMANTAP_EXAM_ENGINE_READERS` / `SIMANTAP_LANDING_*` | `false` / `false` | Opsional | `false` / `false` | Fitur bertahap (tetap `false` di Fase 1) |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_FROM_*` | `log` | Opsional | SMTP sekolah (atau `log`) | Diperlukan bila fitur reset sandi via email dipakai |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | `127.0.0.1` / `6379` / `null` | Opsional | Diisi bila pakai Redis | Untuk `SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION=redis` |

**Catatan `database` vs `redis` untuk `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`:**
- Mode `database` siap pakai tanpa layanan tambahan, cocok untuk 1 sekolah bertrafik ringan–menengah. Konsekuensinya: tiap request web menambah ≥ 2 query (`sessions`) + 1 query per cache lookup + polling berkala worker ke tabel `jobs`.
- Mode `redis` menghilangkan beban query sesi/cache/queue dari PostgreSQL dan disarankan bila pengguna aktif serentak tinggi atau sebelum mengaktifkan CBT (`SIMANTAP_CBT_ENABLED=true`).

---

## 3. Urutan Deploy

> **PENTING:** Selalu buat backup database terlebih dahulu sebelum menjalankan `php artisan migrate --force`.

```bash
# 1. Masuk ke direktori rilis
cd /var/www/simantap

# 2. Pasang dependensi PHP produksi (tanpa dev packages)
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Bangun aset front-end (atau unggah folder public/build hasil CI)
npm ci && npm run build

# 4. Backup database PostgreSQL sebelum migrasi
pg_dump -U simantap_user -h 127.0.0.1 -Fc simantap_db > /var/backups/simantap/pre-deploy-$(date +%Y%m%d-%H%M%S).dump

# 5. Jalankan migrasi database
php artisan migrate --force

# 6. Pastikan symlink storage publik tersedia (untuk aset non-pribadi seperti logo/kop)
php artisan storage:link

# 7. Bangun seluruh cache produksi (WAJIB termasuk view:cache & icons:cache)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache

# 8. Restart PHP-FPM dan queue worker agar memuat kode & konfigurasi baru
sudo systemctl reload php8.4-fpm
sudo supervisorctl restart simantap-worker:*
```

**Warm-up pasca-deploy:**
Setelah reload PHP-FPM, buka beberapa halaman utama (`/`, `/login`, `/admin/dashboard`, `/admin/profile`, `/guru/dashboard`) dari server/browser operator agar OPcache dan cache aplikasi hangat sebelum pengguna pertama masuk (mencegah jeda hit dingin).

---

## 4. Contoh Konfigurasi Nginx Lengkap

Batas timeout dan ukuran unggah di bawah disesuaikan dengan kode aplikasi:
- **Batas unggah terbesar di kode:** `100 MB` (`BackupController.php:64` `max:102400`) dan `50 MB` (`DataReferensiController.php:619` ZIP foto `max:51200`) → `client_max_body_size 110m;`.
- **Rute dengan `set_time_limit` panjang di kode:**
  - `600 dtk`: `POST /admin/presensi/mesin/tarik-semua` (`PresensiMesinImportController.php:272`)
  - `300 dtk`: `POST /admin/presensi/mesin/tarik`, `POST /admin/presensi/mesin/karyawan/tarik` (`PresensiMesinImportController.php:132,198`), dan endpoint sinkron Dapodik (`Api/DapodikController.php:138,181,262`)
  - `180 dtk`: cetak kartu massal (`PresensiKartuController.php:215,459`)
  - `120 dtk`: `GET /admin/dapodik/api/semesters` (`Api/DapodikController.php:224`)

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name domain-sekolah.sch.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name domain-sekolah.sch.id;

    root /var/www/simantap/public;
    index index.php;

    # Sertifikat TLS (mis. Let's Encrypt / Certbot)
    ssl_certificate     /etc/letsencrypt/live/domain-sekolah.sch.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/domain-sekolah.sch.id/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

    # HSTS (juga dikirim oleh Laravel saat HTTPS aktif)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Batas unggah (backup JSON 100 MB + overhead)
    client_max_body_size 110m;

    # Kompresi teks & aset
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
    # Aktifkan brotli bila modul ngx_brotli terpasang:
    # brotli on; brotli_types text/plain text/css application/json application/javascript image/svg+xml;

    # WAJIB: blokir akses publik langsung ke foto siswa lama di disk public
    location ^~ /storage/foto-siswa/ {
        return 404;
    }

    # Blokir dotfile (.env, .git, .htaccess, dll.)
    location ~ /\.(?!well-known).* {
        deny all;
        return 404;
    }

    # Cache immutable untuk aset hasil Vite build (ber-hash)
    location ^~ /build/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
        try_files $uri =404;
    }

    # Timeout panjang khusus rute tarik mesin, Dapodik, dan cetak kartu massal
    location ~ ^/(admin/presensi/(mesin/(tarik|tarik-semua|karyawan/tarik)|kartu-(siswa|pegawai)/cetak-massal)|admin/dapodik) {
        try_files $uri /index.php?$query_string;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_read_timeout 600;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
        fastcgi_param HTTP_X_FORWARDED_HOST $host;
        fastcgi_param HTTP_X_FORWARDED_PORT $server_port;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_read_timeout 120;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
        fastcgi_param HTTP_X_FORWARDED_HOST $host;
        fastcgi_param HTTP_X_FORWARDED_PORT $server_port;
        include fastcgi_params;
    }
}
```

> Bila memakai Cloudflare di depan Nginx, atur `SIMANTAP_TRUSTED_PROXIES` di `.env` ke `127.0.0.1` (dan/atau CIDR Cloudflare sesuai `docs/OPERASIONAL.md`) serta pastikan `set_real_ip_from` dikonfigurasi di Nginx.

---

## 5. Supervisor (Queue Worker) & Cron Scheduler

### Job yang Berjalan di Antrean (`QUEUE_CONNECTION=database`)
Terdapat 4 kelas Job di `app/Jobs/`:
1. `DapodikAutoSyncJob` — sinkronisasi otomatis Dapodik.
2. `ImportDapodikJob` — impor data Dapodik berukuran besar (`set_time_limit(0)`).
3. `ExportBackupJob` — ekspor backup data asinkron (`set_time_limit(0)`).
4. `ProsesAkunMassalJob` — pembuatan akun siswa/orang tua secara massal.

### Contoh Konfigurasi Supervisor (`/etc/supervisor/conf.d/simantap-worker.conf`)
```ini
[program:simantap-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/simantap/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/simantap/storage/logs/worker.log
stopwaitsecs=60
```

Aktifkan:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start simantap-worker:*
```

### Cron Scheduler (`crontab -u www-data -e`)
```cron
* * * * * cd /var/www/simantap && php artisan schedule:run >> /dev/null 2>&1
```

### Daftar Command Terjadwal (Hasil `php artisan schedule:list`)
```text
35 7 * * *  php artisan presensi:mesin-sync
35 15 * * * php artisan presensi:mesin-sync
0 2 * * *   php artisan simantap:backup
0 */6 * * * php artisan tka:refresh
30 3 * * *  php artisan menu:usage-prune
* * * * *   php artisan cbt:finalize-expired
```

---

## 6. Backup, Restore & Rotasi Log

1. **Backup PostgreSQL terjadwal** (mis. cron harian pukul 01:30 sebelum `simantap:backup` 02:00):
   ```bash
   pg_dump -U simantap_user -h 127.0.0.1 -Fc simantap_db > /var/backups/simantap/db-$(date +%Y%m%d).dump
   ```
   Simpan salinan offsite (object storage / server terpisah) dan pertahankan minimal 14–30 hari.
2. **Backup file lokal di luar DB** (WAJIB karena foto siswa tidak ikut di dump tabel):
   - `storage/app/private/foto-siswa/`
   - `storage/app/private/backups/`
   - File `.env` (tersimpan aman terenkripsi di pengelola rahasia, **bukan** di Git).
3. **Uji restore berkala:** jalankan `php artisan simantap:backup:drill` pada lingkungan staging/uji serta uji `pg_restore` ke database sementara minimal 1× per bulan.
4. **Rotasi log & retensi channel `csp`:**
   - Set `LOG_CHANNEL=daily` dan `LOG_DAILY_DAYS=14` di `.env` agar `storage/logs/laravel-*.log` terotasi otomatis.
   - Channel `csp` di `config/logging.php` memakai driver `daily` (`storage/logs/csp-*.log`, retensi 14 hari). Pantau ukuran disk secara berkala bila `SIMANTAP_CSP_REPORT_ONLY=true`.

---

## 7. Pemeriksaan Pasca-Deploy (Smoke Test) & Rollback

### Smoke Test Wajib
1. **Healthcheck:** `curl -fsS https://domain-sekolah.sch.id/up` → HTTP `200`.
2. **Prasyarat satu sekolah (jika `SIMANTAP_MULTI_TENANT=false`):** pastikan `GET /daftar-sekolah` mengembalikan `404` dan tombol daftar sekolah tersembunyi di halaman login.
3. **Privasi foto:** `curl -I https://domain-sekolah.sch.id/storage/foto-siswa/uji.jpg` → HTTP `404`.
4. **Login & alur inti per peran:**
   - **Admin:** `/admin/dashboard`, `/admin/profile`, `/admin/data_pengguna`, `/admin/referensi/foto-siswa`, `/admin/presensi/kartu-siswa/cetak-massal`.
   - **Guru:** `/guru/dashboard`, `/guru/kehadiran`, `/guru/perangkat/buat`.
   - **Kepala Sekolah / Siswa / Orang Tua:** login dan buka dashboard masing-masing (`/kepsek/dashboard`, `/siswa/dashboard`, `/ortu/dashboard`).
5. **Header keamanan:** periksa respons HTTPS menyertakan `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options`, dan (bila diaktifkan) `Content-Security-Policy-Report-Only`.

### Prosedur Rollback
1. Aktifkan mode perawatan: `php artisan down`.
2. Kembalikan kode ke tag/rilis sebelumnya (`git checkout <tag-lama>` atau arahkan symlink rilis), lalu jalankan `composer install --no-dev --optimize-autoloader` dan ulangi cache (`config:cache`, `route:cache`, `view:cache`, `event:cache`, `icons:cache`).
3. **Database:**
   - **Cara paling aman:** pulihkan dump PostgreSQL pra-deploy (`pg_restore --clean --if-exists -U simantap_user -d simantap_db /var/backups/simantap/pre-deploy-*.dump`).
   - **Rollback migrasi selektif:** hanya migrasi aditif `2026_11_19_*` (indeks performa & struktur tambahan tanpa penghapusan data) yang aman di-rollback dengan `php artisan migrate:rollback --step=N`; untuk migrasi yang mengubah data/kolom, **selalu gunakan restore dump DB**.
4. Reload PHP-FPM, restart Supervisor, lalu jalankan `php artisan up`.

---

## 8. Tautan & Checklist Tugas Manual Pemilik (Sebelum Publikasi)

- Panduan cutover foto siswa ke penyimpanan privat: [docs/CUTOVER-FOTO.md](CUTOVER-FOTO.md)
- Panduan operasional (proxy, indeks DB, mesin presensi): [docs/OPERASIONAL.md](OPERASIONAL.md)
- Daftar backlog teknis: [docs/BACKLOG.md](BACKLOG.md)

**Checklist tugas manual yang wajib diselesaikan pemilik sebelum membuka akses publik:**
- [ ] Rotasi seluruh kredensial lama (token Dapodik, password akun bawaan, dan kunci lain yang pernah ada di riwayat Git atau skrip lama).
- [ ] Bangun ulang `SIMANTAP-Bridge.exe` dan isi `DAPODIK_BRIDGE_DOWNLOAD_URL` (HTTPS) serta `DAPODIK_BRIDGE_SHA256` di `.env` produksi.
- [ ] Jalankan pemindaian rahasia (`gitleaks`) sebelum menjadikan repositori publik.
- [ ] Verifikasi prasyarat mode satu sekolah pada DB produksi: `SELECT count(*) FROM tenants;` (= `0`) dan `SELECT count(*) FROM users WHERE tenant_id IS NOT NULL;` (= `0`).
- [ ] Jalankan cutover foto siswa sesuai `docs/CUTOVER-FOTO.md`, pastikan `hanya_public = 0`, set `SIMANTAP_FOTO_FALLBACK_PUBLIC=false`, dan pastikan Nginx memblokir `/storage/foto-siswa/`.
