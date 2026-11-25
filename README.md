# SIMANTAP

**Situs Mandiri Terintegrasi Aplikasi Pembelajaran** — satu ekosistem untuk tiga agenda
sekolah: **TKA**, **Pembelajaran Mendalam**, dan **Manajemen Kelas**.

Dipakai oleh enam peran: **Admin**, **Guru**, **Siswa**, **Orang Tua**, **Kepala Sekolah**,
dan **Tenaga Kependidikan**.

| | |
|---|---|
| **Stack** | Laravel 13 · PHP 8.4+ · PostgreSQL 16 · Blade + Tailwind CSS · Alpine.js |
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

## 🏫 Mode Multi-Sekolah (SaaS) & Lintas Jenjang

Saat `SIMANTAP_MULTI_TENANT=true`, satu instalasi melayani banyak sekolah secara terisolasi per `tenant_id` (`SuperAdmin` mengelola lintas sekolah di `/superadmin/tenants`):

- **Lintas jenjang penuh (`SD`, `MI`, `SMP`, `MTs`, `SMA`, `MA`, `SMK`, `SLB`):** Tingkat `1–12` dan Fase Kurikulum Merdeka `A–F` menyesuaikan otomatis dengan jenjang masing-masing sekolah.
- **Pendaftaran mandiri sekolah (`/daftar-sekolah`):** Mengatur masa uji coba dan kuota awal bawaan melalui `SIMANTAP_SCHOOL_TRIAL_DAYS`, `SIMANTAP_SCHOOL_DEFAULT_PAKET`, `SIMANTAP_SCHOOL_DEFAULT_KUOTA_SISWA`, dan `SIMANTAP_SCHOOL_DEFAULT_KUOTA_STORAGE_MB`.
- **Penegakan kuota & masa aktif langganan:** Kuota siswa (`kuota_siswa`), penyimpanan berkas (`kuota_storage_mb`), dan kuota AI harian per paket ditegakkan otomatis; pengingat jatuh tempo dijalankan harian oleh scheduler melalui `php artisan tenants:send-expiry-reminders`.

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

CI terdiri dari dua workflow:

- `.github/workflows/test.yml` — **kanonis**. Empat job pada setiap push/PR ke `main`:
  PHPUnit di SQLite, PHPUnit di PostgreSQL 16, browser smoke test Chromium (landing/FAQ),
  dan E2E Playwright terautentikasi (CBT/Tryout/TKA).
- `.github/workflows/ci.yml` — smoke test produksi: `composer install --no-dev`,
  generasi `config/route/view/event` cache (memastikan `php artisan route:cache`
  tetap bisa dijalankan), plus audit Composer/NPM yang bersifat *advisory*.
  Dependabot (`.github/dependabot.yml`) membuka PR pembaruan mingguan/bulanan.

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
| Indeks dokumentasi aktif | [`docs/README.md`](docs/README.md) |
| Arsitektur & peta proyek | [`docs/05-ARCHITECTURE.md`](docs/05-ARCHITECTURE.md) · [`docs/13-PETA-PROYEK.md`](docs/13-PETA-PROYEK.md) |
| Referensi kolom database | [`docs/06-DATABASE-COLUMNS.md`](docs/06-DATABASE-COLUMNS.md) |
| Pemantauan Kesiapan TKA | [`docs/TKA-KESIAPAN.md`](docs/TKA-KESIAPAN.md) |
| Modul CBT | [`docs/CBT.md`](docs/CBT.md) |
| Matriks menu & peran | [`docs/AUDIT-MENU-MATRIKS-2026-11-08.md`](docs/AUDIT-MENU-MATRIKS-2026-11-08.md) |
| Rencana perbaikan | [`docs/RENCANA_PERBAIKAN_VERIFIED.md`](docs/RENCANA_PERBAIKAN_VERIFIED.md) |
| Peta tugas & orkestrasi AI Gemini | [`docs/PETA-TUGAS-AI-GEMINI.md`](docs/PETA-TUGAS-AI-GEMINI.md) |
| Prompt E-Learning, perangkat guru & penataan menu | [`docs/PROMPT-ELEARNING-PERANGKAT-MENU.md`](docs/PROMPT-ELEARNING-PERANGKAT-MENU.md) |
| Desain E-Learning / Kelas Digital & flag wali kelas | [`docs/DESAIN-ELEARNING-DAN-WALI-KELAS.md`](docs/DESAIN-ELEARNING-DAN-WALI-KELAS.md) |
| Desain Perangkat Ajar (CP/TP/ATP/Prota/Promes/Modul Ajar/KKM/Jurnal) & Penataan Menu | [`docs/DESAIN-PERANGKAT-AJAR-DAN-MENU.md`](docs/DESAIN-PERANGKAT-AJAR-DAN-MENU.md) |
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
  Http/Controllers/   128 controller (Admin, Guru, Siswa, Ortu, Kepsek, Pegawai, Pengawas, Cbt, Tka, Tryout, Api, Auth)
  Models/             102 model Eloquent (termasuk Exam/Tryout/Tka/Elearning/Perangkat)
  Services/           Dapodik, Nilai, Google Sheets, CBT/Exam engine, Presensi
  Support/            BackupTables (satu sumber kebenaran cakupan backup/restore)
database/
  migrations/         119 migrasi
  seeders/            DatabaseSeeder, DemoDataSeeder, ApiKeySeeder (+ Support/SeedGuard)
dapodik-bridge/       Bridge Python yang berjalan di komputer operator sekolah
docs/                 Audit, rencana perbaikan, desain CBT/Tryout/TKA
routes/web.php        649 definisi route (700 panggilan Route:: termasuk grup) + 7 di routes/api.php
tools/                Skrip verifikasi statis & pemeriksaan runtime
```

---

## Lisensi

Proprietary — hak cipta milik pengelola SIMANTAP. Kerangka Laravel yang mendasarinya
berlisensi MIT.
