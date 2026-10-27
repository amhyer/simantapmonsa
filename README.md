# SIMANTAP

**Situs Mandiri Terintegrasi Aplikasi Pembelajaran** — satu ekosistem untuk tiga agenda
sekolah: **TKA**, **Pembelajaran Mendalam**, dan **Manajemen Kelas**.

Dipakai oleh enam peran: **Admin**, **Guru**, **Siswa**, **Orang Tua**, **Kepala Sekolah**,
dan **Tenaga Kependidikan**.

| | |
|---|---|
| **Stack** | Laravel 13 · PHP 8.3+ · PostgreSQL 16 · Blade + Tailwind CSS · Alpine.js |
| **Integrasi** | Dapodik (sinkron dua arah), Google Sheets, e-Rapor Kemdikbud, DomPDF, PhpSpreadsheet |
| **Modul** | Penilaian & e-Rapor, Presensi (geofence + QR), 7 Kebiasaan, Kokurikuler, Kuis, Tryout/TKA, CBT (opsional) |

---

## 🚀 Mulai cepat (pengembangan)

```bash
composer install
cp .env.example .env
php artisan key:generate

# PostgreSQL lokal, atau pakai SQLite untuk coba cepat:
#   DB_CONNECTION=sqlite
php artisan migrate --seed

npm install
npm run build          # atau: npm run dev

php artisan serve
```

Akun hasil seed (hanya untuk pengembangan): `admin/admin123`, `guru/guru123`,
`kepsek/kepsek123`.

> Di luar lingkungan `local`, akun hasil seed dipaksa mengganti sandi pada login pertama,
> dan `DatabaseSeeder` menolak berjalan saat `APP_ENV=production`.

---

## 📦 Deploy ke produksi

Lihat **[`DEPLOYMENT.md`](DEPLOYMENT.md)** — berisi prasyarat server, konfigurasi `.env`
produksi, hak akses PostgreSQL, cron & queue worker, sampai prosedur backup.

Ringkasan langkah yang paling sering terlewat:

1. **`npm run build`** — `public/build/` di-gitignore; tanpa ini halaman tampil tanpa CSS/JS.
2. **Cron `schedule:run` tiap menit** — tanpa ini backup harian dan finalisasi CBT tidak jalan.
3. **Queue worker** — `QUEUE_CONNECTION=database`, butuh `php artisan queue:work` permanen.
4. **`php artisan migrate --force`** — **tanpa** `--seed`.

---

## ✅ Verifikasi

```bash
php tools/verifikasi-perbaikan.php   # pemeriksaan statis, tanpa Composer/vendor/DB
./verify.sh                          # gerbang pra-deploy: lint + route + PHPUnit + cache
composer test                        # PHPUnit (SQLite)
npm run test:e2e                     # Playwright (Chromium)
```

CI (`.github/workflows/test.yml`) menjalankan empat job pada setiap push/PR ke `main`:
PHPUnit di SQLite, PHPUnit di PostgreSQL 16, browser smoke test Chromium (landing/FAQ),
dan E2E Playwright terautentikasi (CBT/Tryout/TKA).

---

## 📚 Dokumentasi

| Topik | Berkas |
|---|---|
| Deployment produksi | [`DEPLOYMENT.md`](DEPLOYMENT.md) |
| Runbook go-live & rollback | [`docs/RUNBOOK-GO-LIVE.md`](docs/RUNBOOK-GO-LIVE.md) |
| Kesiapan launching | [`LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md`](LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md) |
| Keamanan | [`docs/04-SECURITY-GUIDE.md`](docs/04-SECURITY-GUIDE.md) |
| Reset sandi mandiri | [`docs/RESET-SANDI-MANDIRI.md`](docs/RESET-SANDI-MANDIRI.md) |
| Audit nilai e-Rapor | [`docs/AUDIT-NILAI.md`](docs/AUDIT-NILAI.md) |
| Analisis butir CBT | [`docs/ANALISIS-BUTIR.md`](docs/ANALISIS-BUTIR.md) |
| Antrean jawaban offline | [`docs/ANTREAN-OFFLINE-UJIAN.md`](docs/ANTREAN-OFFLINE-UJIAN.md) |
| Checklist staging integrasi | [`docs/CHECKLIST-STAGING-INTEGRASI.md`](docs/CHECKLIST-STAGING-INTEGRASI.md) |
| Uji beban (k6) | [`docs/LOAD-TEST.md`](docs/LOAD-TEST.md) |
| Drill restore backup | [`docs/DRILL-RESTORE-BACKUP.md`](docs/DRILL-RESTORE-BACKUP.md) |
| Rekonsiliasi CBT/Tryout | [`docs/REKONSILIASI-FASE-3.md`](docs/REKONSILIASI-FASE-3.md) |
| Arsitektur | [`docs/05-ARCHITECTURE.md`](docs/05-ARCHITECTURE.md) |
| Modul CBT | [`docs/CBT.md`](docs/CBT.md) |
| Menu & peran | [`docs/arsip/MENU_AND_ROLES_DOCUMENTATION.md`](docs/arsip/MENU_AND_ROLES_DOCUMENTATION.md) |
| Model & relasi database | [`docs/arsip/DATABASE_MODELS_AND_RELATIONSHIPS.md`](docs/arsip/DATABASE_MODELS_AND_RELATIONSHIPS.md) |
| Rencana perbaikan | [`docs/RENCANA_PERBAIKAN_VERIFIED.md`](docs/RENCANA_PERBAIKAN_VERIFIED.md) |
| Uji route/halaman dengan Playwright | [`docs/PANDUAN_UJI_ROUTE_PLAYWRIGHT.md`](docs/PANDUAN_UJI_ROUTE_PLAYWRIGHT.md) |
| Arsip dokumentasi lama | [`docs/arsip/`](docs/arsip/README.md) |

---

## 🔐 Keamanan

- Sandi di-hash dengan bcrypt (`BCRYPT_ROUNDS=12`); kolom `kata_sandi` ada di `$hidden`.
- Login dibatasi 5 percobaan per IP (decay 300 detik); akun nonaktif/terarsip ditolak.
- Pendaftaran publik membuat akun **nonaktif** — admin yang mengaktifkan.
- Sandi yang dibuat/direset admin memicu `force_password_change` pada pemilik akun.
- Reset sandi mandiri memakai email opsional; akun tanpa email tetap direset admin.
  Permintaan dibatasi 5/menit, token sekali pakai dan kedaluwarsa 60 menit.
- Perubahan nilai e-Rapor dicatat dalam rantai hash (`prev_hash -> hash`), ikut
  backup/restore, dan dapat diverifikasi dengan `php artisan nilai:audit:verifikasi`.
  Ini mendeteksi perubahan langsung pada snapshot, bukan pengganti backup/WORM.
- API key disimpan sebagai hash SHA-256; kunci polos hanya tampil sekali saat dibuat.
- Telescope dikunci ke admin yang namanya ada di `TELESCOPE_ALLOWED_USERS`.
- API docs Scramble (`/docs/api`) hanya dapat diakses di lingkungan `local`.

Laporkan celah keamanan secara pribadi ke pengelola proyek — jangan lewat issue publik.

---

## 🗂 Struktur ringkas

```
app/
  Http/Controllers/   89 controller (Admin, Guru, Siswa, Ortu, Kepsek, Pegawai, Cbt, Tka)
  Models/             65 model Eloquent
  Services/           Dapodik, Nilai, Google Sheets, CBT/Exam engine, Presensi
  Support/            BackupTables (satu sumber kebenaran cakupan backup/restore)
database/
  migrations/         85 migrasi
  seeders/            DatabaseSeeder, DemoDataSeeder, ApiKeySeeder (+ Support/SeedGuard)
dapodik-bridge/       Bridge Python yang berjalan di komputer operator sekolah
docs/                 Audit, rencana perbaikan, desain CBT/Tryout/TKA
routes/web.php        414 pendaftaran route
tools/                Skrip verifikasi statis & pemeriksaan runtime
```

---

## Lisensi

Proprietary — hak cipta milik pengelola SIMANTAP. Kerangka Laravel yang mendasarinya
berlisensi MIT.
