# Panduan Deployment SIMANTAP

Dokumen ini adalah **satu-satunya acuan** untuk menaikkan SIMANTAP ke server produksi.
Latar belakang temuan yang melahirkannya: `LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md`.

> **Ringkas:** clone → `composer install` → `.env` produksi → `migrate --force`
> (tanpa `--seed`) → `npm run build` → cache → cron + queue → `./verify.sh`.

---

## 1. Prasyarat server

| Komponen | Versi minimum | Catatan |
|---|---|---|
| PHP | **8.3** (CI memakai 8.4) | Ekstensi: `mbstring`, `pdo_pgsql`, `sqlite3`, `bcmath`, `gd`, `zip`, `xml`, `intl`, `gmp`, `dom`, `fileinfo`, `iconv`, `curl` |
| PostgreSQL | 16 | SQLite hanya untuk test suite |
| Node.js | 22 | Hanya dibutuhkan saat build aset |
| Composer | 2 | |
| Web server | Nginx atau Apache | Apache: `mod_rewrite` aktif |

Cek cepat:

```bash
php -v && php -m | grep -E "pdo_pgsql|zip|intl|gd" && node -v && composer --version
```

---

## 2. Ambil kode & dependensi

```bash
git clone <url-repo> /var/www/simantap
cd /var/www/simantap

composer install --no-dev --optimize-autoloader
```

`--no-dev` penting: Telescope, Pail, dan Pint tidak perlu ada di produksi.

---

## 3. Konfigurasi `.env`

```bash
cp .env.production.example .env
nano .env          # isi semua nilai <...>
```

Tiga baris yang **tidak boleh salah**:

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

Lalu buat kunci aplikasi:

```bash
php artisan key:generate
```

Daftar lengkap variabel beserta penjelasannya ada di `.env.production.example`.

> ⚠️ `.env.example` (tanpa `.production`) adalah konfigurasi **pengembangan**:
> `APP_ENV=local` dan `APP_DEBUG=true`. Jangan menyalinnya ke server live.

---

## 4. Basis data PostgreSQL

Buat database dan **user aplikasi terpisah** — jangan pakai superuser `postgres`:

```sql
CREATE DATABASE simantap_db ENCODING 'UTF8';

CREATE USER simantap_app WITH PASSWORD 'ganti-dengan-yang-kuat';
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO simantap_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO simantap_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO simantap_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO simantap_app;
```

Migrasi:

```bash
php artisan migrate --force
```

> 🚫 **Jangan** menambahkan `--seed`. `DatabaseSeeder` membuat akun `admin/admin123`,
> `guru/guru123`, dan `kepsek/kepsek123` — sandi-sandi itu tercantum di repositori.
> Sejak perbaikan launching, seeder juga **menolak** berjalan saat `APP_ENV=production`
> kecuali `SIMANTAP_ALLOW_SEED=true`.

---

## 5. Aset frontend & cache

`public/build/` **di-gitignore**, jadi tanpa langkah ini semua halaman tampil tanpa
CSS/JavaScript.

```bash
npm ci
npm run build

php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 6. Akun admin pertama

Karena seeder dilarang di produksi, buat admin secara manual:

```bash
php artisan tinker --execute="
\App\Models\User::create([
    'nama_lengkap' => 'Administrator Sekolah',
    'nama_pengguna' => 'admin',
    'kata_sandi' => \Illuminate\Support\Facades\Hash::make('GANTI-SEGERA'),
    'peran' => 'admin',
    'terhubung_dengan' => [],
    'aktif' => true,
    'force_password_change' => true,
]);
"
```

`force_password_change => true` membuat admin diarahkan ke halaman ganti sandi begitu
login pertama kali. **Jangan** dihapus.

### Prosedur reset sandi pengguna

SIMANTAP **tidak punya fitur "lupa password"** — tabel `users` tidak memiliki kolom
`email`. Semua reset dilakukan admin lewat **Admin → Pengguna → Edit → Kata Sandi**.

Sejak perbaikan launching, sandi yang direset admin otomatis menandai akun tersebut
`force_password_change = true`, sehingga pengguna dipaksa mengganti sandi sementara itu
pada login berikutnya. Sampaikan ke pengguna: *"sandi ini sementara, Anda akan diminta
menggantinya saat masuk."*

---

## 7. Cron & queue worker — WAJIB

Dua hal ini **tidak jalan sendiri**. Tanpa keduanya fitur inti mati diam-diam.

### 7.1 Cron (scheduler)

`routes/console.php` mendaftarkan:

| Jadwal | Perintah | Akibat bila cron mati |
|---|---|---|
| Tiap hari 02:00 | `simantap:backup` | **Tidak ada backup sama sekali** |
| Tiap 6 jam | `tka:refresh` | Statistik TKA basi |
| Tiap menit (bila CBT aktif) | `cbt:finalize-expired` | **Ujian siswa yang terputus tidak pernah difinalisasi/dinilai** |

Tambahkan ke crontab user web (`crontab -e`):

```cron
* * * * * cd /var/www/simantap && php artisan schedule:run >> /dev/null 2>&1
```

### 7.2 Queue worker

`QUEUE_CONNECTION=database`, jadi job (export backup, proses akun massal) menunggu
worker. Jalankan permanen lewat systemd:

```ini
# /etc/systemd/system/simantap-queue.service
[Unit]
Description=SIMANTAP Queue Worker
After=network.target postgresql.service

[Service]
User=www-data
WorkingDirectory=/var/www/simantap
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now simantap-queue
```

> Setelah setiap deploy, jalankan `php artisan queue:restart` agar worker memuat kode baru.

---

## 8. Web server & HTTPS

**Document root harus menunjuk ke `public/`**, bukan root proyek. Kalau tidak,
`.env` dapat diunduh publik.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name simantap.sekolah.sch.id;

    root /var/www/simantap/public;
    index index.php;

    # Header keamanan (X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
    # Permissions-Policy, HSTS) dipasang oleh aplikasi lewat
    # App\Http\Middleware\SecurityHeaders — lihat config/security.php.
    # Jangan menduplikasinya di sini agar browser tidak menerima dua nilai.

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}

server {
    listen 80;
    server_name simantap.sekolah.sch.id;
    return 301 https://$host$request_uri;
}
```

### Apache

`public/.htaccess` sudah tersedia; cukup pastikan `AllowOverride All` dan `mod_rewrite`
aktif, lalu arahkan `DocumentRoot` ke `/var/www/simantap/public`.

### Izin berkas

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find /var/www/simantap -type d -exec chmod 755 {} \;
sudo find /var/www/simantap -type f -exec chmod 644 {} \;
```

---

## 9. Verifikasi pra-launch

```bash
./verify.sh
```

Skrip ini gerbang resmi proyek: memeriksa sintaks PHP, jumlah route, timezone,
seluruh PHPUnit, dan cache. **Bila ada satu pun ✗, jangan deploy.**

Verifikasi statis tambahan (tidak butuh database):

```bash
php tools/verifikasi-perbaikan.php    # target: "N lulus, 0 gagal"
```

Cek manual:

- [ ] `curl -I https://domain/` → `200`, dan `curl -I http://domain/` → `301` ke HTTPS
- [ ] Buka `/login`, `/`, `/tka-statistik` tanpa login
- [ ] Buka `/up` → health check Laravel
- [ ] `/docs/api` dan `/telescope` **tidak** bisa diakses dari luar
- [ ] Login untuk 6 peran: admin, guru, siswa, ortu, kepsek, tendik
- [ ] Alur penuh: guru input nilai → e-Rapor → cetak PDF
- [ ] `php artisan simantap:backup` lalu buka file hasilnya di `storage/app/private/backups/`
- [ ] Cron benar-benar jalan: tunggu 1 menit, cek `storage/logs/laravel.log`

---

## 10. Deploy ulang (release berikutnya)

```bash
cd /var/www/simantap
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

---

## 11. Backup & pemulihan

Backup otomatis menulis JSON ke `<disk>/backups/backup-simantap-<timestamp>.json`
setiap hari pukul 02:00. Kolom `kata_sandi` dan `remember_token` **tidak** ikut
(`User::$hidden`).

### Rotasi (sudah otomatis)

`simantap:backup` menghapus sendiri berkas `backup-simantap-*.json` yang lebih tua dari
`BACKUP_RETENTION_DAYS` (default **30 hari**). Berkas lain di folder itu tidak disentuh.

```env
BACKUP_RETENTION_DAYS=30     # 0 = rotasi dimatikan
```

Lewati rotasi sekali jalan bila perlu: `php artisan simantap:backup --tanpa-rotasi`.

### Salinan offsite

Cadangan satu disk **tidak** melindungi dari server yang rusak. Ada dua cara:

**a) Lewat aplikasi** — set disk kedua; setiap cadangan disalin ke sana. Kegagalan
penyalinan tidak membatalkan cadangan utama, hanya dicatat ke log.

```env
BACKUP_DISK=local
BACKUP_COPY_DISK=s3          # disk lain di config/filesystems.php
```

**b) Di luar aplikasi** (sering lebih tangguh) — rclone / rsync / snapshot volume:

```cron
30 2 * * * rclone copy /var/www/simantap/storage/app/private/backups remote:simantap/
```

### Backup level database

Untuk pemulihan cepat dan utuh, cadangan JSON saja tidak cukup — pakai `pg_dump` juga:

```bash
pg_dump -U simantap_admin -F c -f backup-$(date +%F).dump simantap_db
pg_restore --list backup-<tanggal>.dump   # verifikasi file bisa dibaca
```

### Uji pemulihan

Cadangan yang belum pernah dipulihkan bukan cadangan. Sekali sebulan, pulihkan ke
**staging** (bukan produksi) dan pastikan aplikasi bisa dipakai.

Prosedur rollback lengkap untuk perubahan skema: `docs/12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md`.

---

## 12. Mode pemeliharaan

```bash
php artisan down --retry=60 --refresh=60   # situs mati, pengunjung diminta coba lagi
php artisan up
```

---

## 13. Keputusan yang harus diambil sebelum launching

- [ ] **CBT/Tryout ikut launching atau tidak?** Default `SIMANTAP_CBT_ENABLED=false`.
      Bila dinyalakan, syarat `docs/CBT.md` wajib terpenuhi lebih dulu (migrate, uji
      PostgreSQL, cron tiap menit, uji penerimaan sekolah).
- [ ] **Siapa pemegang akun admin** dan bagaimana SOP reset sandi disosialisasikan.
- [ ] **Jadwal backup offsite** dan siapa yang memantau disk.
