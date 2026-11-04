# Peta Proyek SIMANTAP

**Identifikasi menyeluruh: struktur, alur, halaman, dan fungsi**

| | |
|---|---|
| Tanggal pemetaan | 4 November 2026 |
| Commit terakhir | `62b9e15 fix(landing): sederhanakan kolom footer Untuk menjadi satu tautan login` |
| Cabang | `main` (working tree **kotor** — 58 file berubah + 8 file baru, termasuk dokumen ini) |
| Sumber kebenaran | kode di repo + `php artisan route:list` |
| Dokumen ini menggantikan | klaim jumlah di `docs/01-AUDIT-REPORT.md`, `docs/03-REMAINING-ISSUES.md`, `docs/07-MENU-ROADMAP.md`, `docs/arsip/DATABASE_MODELS_AND_RELATIONSHIPS.md` |

> Angka di dokumen ini dihitung langsung dari repo pada tanggal di atas, bukan disalin dari dokumen lama.

---

## 0. Cara membaca

- **Bagian 1–4** = identitas, stack, dan struktur.
- **Bagian 5** = peta rute lengkap per awalan dan per peran.
- **Bagian 6** = peta basis data per domain.
- **Bagian 7** = alur bisnis tiap modul (yang paling berguna untuk memahami "aplikasi ini bekerja bagaimana").
- **Bagian 8–9** = inventaris halaman dan fungsi (untuk mencari "fitur X ada di file mana").
- **Bagian 13–15** = status, risiko, dan dokumen mana yang sudah basi.

---

## 1. Identitas proyek

**SIMANTAP** (Sistem Informasi Manajemen Terpadu Sekolah) adalah aplikasi web monolit Laravel untuk sekolah dasar/menengah. Cakupannya:

1. **e-Rapor** — input nilai, audit perubahan nilai, cetak rapor, leger, transkrip ijazah.
2. **Dapodik** — tarik data (pull) dan kirim nilai (push) ke Dapodik, baik lewat API maupun bridge lokal.
3. **Ujian & asesmen** — **CBT** dan **Tryout** memakai mesin terpadu `exam_*`; **TKA** (diagnostik + simulasi Pusmendik) berjalan di jalur terpisah.
4. **Presensi** — presensi pegawai berbasis geofence + presensi siswa via QR kartu dan presensi per mata pelajaran.
5. **Kokurikuler P5** — tema, kegiatan, kelompok, anggota.
6. **Monitoring** — dasbor kepsek, wali kelas/guru, orang tua, dan siswa.
7. **Administrasi** — akun, rombel/peta kelas, semester, mapel, backup/restore, Google Sheets, API key, log aktivitas.

### Stack

| Lapisan | Teknologi |
|---|---|
| Bahasa / framework | PHP 8.3+ (dev memakai 8.4), Laravel 13 |
| Frontend | Blade + Tailwind 3/Vite 8, Bootstrap 5.3, Alpine.js, Chart.js, Lucide icons, AOS (landing) |
| Basis data | PostgreSQL (produksi), SQLite in-memory (test) |
| Auth | Session guard bawaan Laravel, login manual (`nama_pengguna` + `kata_sandi`) |
| Antrean | Database queue (`jobs`), 4 Job class |
| Ekspor | DomPDF (rapor/leger/transkrip), PhpSpreadsheet/CSV |
| Integrasi | Dapodik (API + bridge Node), Google Sheets API |
| Observability | Laravel Telescope, log aktivitas sendiri (`aktivitas`) |
| Test | PHPUnit 12 + Playwright (e2e) |
| CI | GitHub Actions (`.github/workflows/test.yml`) |

### Perintah dev yang benar (Windows/Herd)

```powershell
# PHP wajib memakai path Herd; `php` polos rusak ekstensinya
C:\Users\USER\.config\herd\bin\php84\php.exe artisan serve --host=127.0.0.1 --port=8000
C:\Users\USER\.config\herd\bin\php84\php.exe artisan migrate --seed
C:\Users\USER\.config\herd\bin\php84\php.exe artisan test
```

---

## 2. Angka kunci (terverifikasi)

| Metrik | Jumlah | Cara hitung |
|---|---:|---|
| Total rute (semua, termasuk Telescope/docs) | **448** | `route:list --json` |
| Rute aplikasi (tanpa Telescope/docs/storage/health) | **399** | filter `uri` |
| Rute GET | 243 | |
| Rute tulis (POST/PUT/PATCH/DELETE) | 205 | |
| Controller | **90** | `app/Http/Controllers` |
| Method publik controller | **~412** | regex `public function` |
| Model Eloquent | **40** | `app/Models` |
| Service | **39** | `app/Services` |
| Migrasi | **85** | `database/migrations` |
| Tabel (union `Schema::create`) | **81** | termasuk tabel framework |
| Blade view | **234** | termasuk komponen/error/vendor |
| File test PHPUnit | **65** | `tests/**/*Test.php` |
| Job | 4 | |
| Artisan command | 10 | |
| Policy | 7 | |
| Observer | 1 | |
| Middleware kustom | 6 | |

### Sebaran rute per peran (middleware `role:*`)

| Peran | Rute |
|---|---:|
| `admin` | 196 |
| `guru` | 130 |
| `siswa` | 30 |
| `kepsek` | 10 |
| `ortu` | 10 |
| `guru,kepsek,tendik` (presensi) | 3 |

> Satu rute bisa muncul di beberapa peran, jadi totalnya tidak dijumlahkan.

### Sebaran view per area

`admin` 89 · `guru` 37 · `siswa` 14 · `cbt` 13 · `tryout` 11 · `tka` 10 · `kepsek` 10 · `ortu` 9 · `pegawai` 2 · `auth` 4 · `components` 18 · `layouts` 4 · `errors` 7 · `vendor` 3 · landing 1 · partials 1

---

## 3. Struktur direktori

```
simantap/
├─ app/
│  ├─ Console/Commands/          10 perintah artisan (backup, exam, dapodik, tka, sheets)
│  ├─ Http/
│  │  ├─ Controllers/            90 controller, dibagi per peran
│  │  │  ├─ Admin/               e-Rapor, referensi, CBT, Tryout, TKA, backup, transkrip, cetak
│  │  │  ├─ Api/                 endpoint Dapodik + lookup sekolah (sync pakai API key)
│  │  │  ├─ Auth/                login, lupa/reset sandi, paksa ganti sandi
│  │  │  ├─ Cbt/                 sisi guru & sisi siswa CBT
│  │  │  ├─ Guru/                pembelajaran, nilai, e-Rapor, TKA, laporan
│  │  │  ├─ Kepsek/              rekap, peta kelas, hasil belajar, aktivitas
│  │  │  ├─ Ortu/                pemantauan anak
│  │  │  ├─ Pegawai/             presensi pegawai
│  │  │  ├─ Siswa/               materi, kuis, nilai, profil, simulasi TKA
│  │  │  ├─ Tka/                 asesmen, dimensi, laporan orang tua
│  │  │  └─ Tryout/              paket, sesi, siswa
│  │  └─ Middleware/             role, apikey, force.password.change, module.asesmen,
│  │                             CbtAccessMiddleware, SecurityHeaders
│  ├─ Jobs/                      4 job (Dapodik sync/import, export backup, akun massal)
│  ├─ Models/                    40 model
│  ├─ Observers/                 NilaiErapotObserver (audit nilai)
│  ├─ Policies/                  7 policy (CBT, Tryout, TKA)
│  ├─ Services/                  39 service, lihat §10
│  ├─ Support/BackupTables.php   daftar tabel untuk backup/wipe
│  ├─ Traits/SyncableToSheet.php sinkronisasi data → Google Sheets (dipakai controller)
│  └─ helpers.php                 setting(), getKKM(), activeAdminTab()
├─ bootstrap/app.php             registrasi rute + alias middleware
├─ config/
│  ├─ simantap.php               4 flag fitur (CBT + engine ujian)
│  └─ security.php               header keamanan
├─ database/
│  ├─ migrations/                85 migrasi
│  └─ seeders/                   Database, Demo, E2E, LoadTest, ApiKey
├─ dapodik-bridge/               bridge Node.js untuk Dapodik lokal
├─ docs/                         dokumentasi (lihat §15)
├─ resources/
│  ├─ css/, js/                  aset Vite
│  └─ views/                     234 Blade
├─ routes/
│  ├─ web.php                    671 baris — peta utama
│  ├─ api.php                    endpoint Dapodik (bridge) + lookup sekolah
│  └─ console.php                penjadwalan
├─ tests/
│  ├─ Feature/                   56 test
│  ├─ Unit/                      9 test
│  └─ e2e/                       Playwright
└─ tools/                        skrip bantu (verifikasi, audit)
```

---

## 4. Peran, autentikasi, dan otorisasi

### 4.1 Enam peran

Nilai kolom `users.peran`:

| Peran | Label | Cakupan |
|---|---|---|
| `admin` | Administrator | Semua modul, semua data sekolah |
| `guru` | Guru | Kelas/mapel yang diampu + CBT/Tryout/TKA |
| `siswa` | Siswa | Data sendiri: materi, kuis, nilai, ujian, simulasi TKA |
| `ortu` | Orang tua/wali | Data anak yang terhubung (`orang_tua_siswa`) |
| `kepsek` | Kepala sekolah | Rekap read-only + presensi sendiri |
| `tendik` | Tenaga kependidikan | Presensi pegawai |

### 4.2 Alur login

```
GET /            → landing (publik)
GET /login       → form (guest)
POST /login      → LoginController@login
                   ├─ validasi nama_pengguna + kata_sandi
                   ├─ Auth::attempt(nama_pengguna + password)
                   ├─ cek users.aktif = 1 DAN archived_at = null
                   ├─ throttle 5 percobaan/menit (key: login:{ip}) + throttle:10,1 (rute)
                   ├─ regenerate session
                   └─ redirect sesuai peran
POST /logout     → invalidate session (throttle:30,1)

Jika users.force_password_change = 1 → semua rute memaksa ke /force-password-change
```

Pendaftaran mandiri **ditutup**: `GET /register` hanya redirect ke login dengan pesan bahwa akun dibuat admin. Reset sandi mandiri tersedia lewat email (`/lupa-sandi`, `/reset-sandi`) tetapi pengguna tanpa email diarahkan ke admin.

### 4.3 Middleware

| Alias / kelas | Fungsi |
|---|---|
| `auth` | Wajib login |
| `role:a,b,c` | `RoleMiddleware` — cocokkan `users.peran`, else 403 |
| `force.password.change` | `ForcePasswordChange` — alihkan ke form ganti sandi bila `users.force_password_change` |
| `module.asesmen` | `EnsureModuleEnabled` — blokir CBT/Tryout/TKA dengan **404** bila modul dimatikan; **admin selalu lolos** |
| `apikey` | `ApiKeyMiddleware` — header `X-API-Key`, dicocokkan ke `api_keys.key_hash` (SHA-256), cek aktif/expired + ability |
| `CbtAccessMiddleware` | Gerbang tambahan rute CBT siswa/guru (dipakai langsung di `routes/web.php`) |
| `SecurityHeaders` | Ditambahkan global: CSP, X-Frame-Options, Referrer-Policy, dll. dari `config/security.php` |

### 4.4 Flag fitur (`config/simantap.php`)

| Flag | Default | Arti |
|---|---|---|
| `SIMANTAP_CBT_ENABLED` | `false` | Rute & menu CBT/Tryout/TKA aktif. Off ≠ hapus data |
| `SIMANTAP_EXAM_ENGINE_TRYOUT` | `true` | Tulis Tryout ke `exam_*` lalu cermin ke `tryout_*` |
| `SIMANTAP_EXAM_ENGINE_CBT` | `true` | Tulis CBT ke `exam_*` lalu cermin ke `cbt_*` |
| `SIMANTAP_EXAM_ENGINE_READERS` | `false` | Pembaca laporan pakai `exam_*` (belum default karena perlu backfill) |

---

## 5. Peta rute

### 5.1 Publik & autentikasi

| Method | URI | Name | Keterangan |
|---|---|---|---|
| GET | `/` | `landing` | Landing page |
| GET | `/demo` | `demo` | Mode demo |
| GET | `/tka-statistik` | `tka.statistik` | Statistik TKA publik |
| GET | `/api/tka-statistik` | `tka.api` | JSON statistik |
| POST | `/tka-statistik/refresh` | `tka.refresh` | Auth |
| GET/POST | `/login` | `login` | |
| GET | `/register` | `register` | Redirect (ditutup) |
| GET/POST | `/lupa-sandi` | `password.request/email` | |
| GET/POST | `/reset-sandi/{token}` | `password.reset/update` | |
| POST | `/logout` | `logout` | |
| GET/POST | `/force-password-change` | — | Wajib sebelum mengakses apa pun |

### 5.2 Rute aplikasi per awalan

| Awalan | Jumlah | Isi |
|---|---:|---|
| `admin/*` | 196 | Lihat rincian di bawah |
| `guru/*` | 77 | Dasbor, materi, dimensi, input nilai, e-Rapor, kuis, nilai, analisis, CBT, Tryout, TKA, kehadiran, catatan, kebiasaan, laporan, siswa, integrasi, pengaturan |
| `cbt/*` | 28 | Sisi guru (sesi, bank soal, monitoring, integritas) + `cbt/student/*` |
| `tryout/*` | 21 | Sisi guru (paket, sesi, monitoring) + `tryout/student/*` |
| `siswa/*` | 18 | Dasbor, materi, kuis, nilai, profil, `tka-simulasi` |
| `tka/*` | 18 | Dimensi, asesmen, laporan orang tua |
| `kepsek/*` | 10 | Dasbor, pantau, rekap, peta kelas, hasil belajar, kebiasaan, aktivitas |
| `ortu/*` | 8 | Dasbor, kebiasaan, rekap, nilai, kehadiran, catatan, laporan |
| `presensi/*` | 3 | Presensi pegawai (masuk, pulang, index) |
| `api/*` | 5 | `dapodik/sync/*` (bridge, API key), `dapodik/ping`, `sekolah/lookup`, `tka-statistik` |
| `dashboard` | 1 | Router dasbor per peran |
| `tka-statistik` | 2 | Statistik publik |

### 5.3 Rincian `admin/*` (196 rute)

| Sub-awalan | Rute | Controller |
|---|---:|---|
| `admin/referensi` | 22 | `DataReferensiController` (guru, pembelajaran, alignment, tanggal rapor, kelompok mapel, mapping, ekstrakurikuler, logo/TTD, foto siswa) |
| `admin/tryout` | 22 | `Tryout\TryoutPackageAdminController` (paket, sesi, hasil, ekspor, duplikat) |
| `admin/dapodik` | 22 | `DapodikImportController`, `Api\DapodikController` (pull/sync), `Api\DapodikPushController` |
| `admin/cbt` | 18 | `Cbt\CbtSessionController`, `Cbt\CbtBankController` |
| `admin/tka` | 15 | `Tka\TkaAdminController` (dimensi, asesmen, early warning, riwayat, ekspor) |
| `admin/transkrip` | 13 | `TranskripController` (setting, nomor, input, import, cetak) |
| `admin/mapel` | 11 | `MapelController` |
| `admin/kokurikuler` | 11 | `KokurikulerController` |
| `admin/users` | 10 | `UserController` (CRUD, hapus massal, akun massal, daftar siswa) |
| `admin/peta-kelas` | 8 | `PetaKelasController` |
| `admin/presensi` | 8 | `PresensiController` + `PresensiKartuController` |
| `admin/semester` | 4 | `SemesterController` |
| `admin/api-keys` | 4 | `ApiKeyController` |
| `admin/cetak` | 4 | `CetakController` (leger, pelengkap, nilai) |
| `admin/backup` | 4 | `BackupController` (export, import, wipe) |
| `admin/sheet` | 3 | `SheetController` (Google Sheets) |
| `admin/sekolah` | 3 | `SekolahController` |
| `admin/penilaian` | 2 | `PenilaianController` (status, statistik) |
| `admin/bobot` | 2 | `BobotController` |
| `admin/modul` | 2 | `ModulController` |
| `admin/profile` | 2 | `ProfileController` |
| `admin/kode-akses` | 2 | `KodeAksesController` |
| `admin/perkembangan` | 2 | `PerkembanganController` |
| `admin/log` | 1 | `LogController` |
| `admin/dashboard` | 1 | `DashboardController@admin` |

### 5.4 Grup rute bersyarat (CBT)

```
if (config('simantap.cbt_enabled')) {
    /cbt/*            role:guru   + CbtAccessMiddleware
    /cbt/student/*    role:siswa  + CbtAccessMiddleware
    /admin/cbt/*      role:admin  + module.asesmen
}

/admin/tryout/*       role:admin  + module.asesmen
/admin/tka/*          role:admin  + module.asesmen
/tryout/*             role:guru   + module.asesmen
/tryout/student/*     role:siswa  + module.asesmen
/tka/*                role:guru   + module.asesmen
/tka/parent/*         role:ortu   + module.asesmen
/siswa/tka-simulasi/* role:siswa  (modul baru, belum di-commit)
```

---

## 6. Peta basis data (81 tabel)

### 6.1 Domain akademik & e-Rapor

`users` · `siswa` · `rombel` · `mata_pelajaran` · `semesters` · `jadwal_pelajaran` · `nilai` · `nilai_mapel` · `nilai_cp` · `nilai_erapor` · `nilai_erapor_audit_logs` · `tanggal_rapor` · `transkrip_nilai` · `capaian_pembelajaran` · `dimensi` · `ringkasan_guru` · `pengaturan_guru` · `sekolah_settings` · `catatan`

### 6.2 Kesiswaan & kokurikuler

`siswa_ekstrakurikuler` · `ekstrakurikuler` · `prestasi` · `kehadiran` · `kehadiran_mapel` · `kebiasaan` · `tema_kokurikuler` · `kegiatan_kokurikuler` · `kelompok_kokurikuler` · `anggota_kelompok` · `p5_projek` · `p5_siswa` · `orang_tua_siswa` · `ptk`

### 6.3 Pembelajaran

`materi` · `kuis` · `hasil_kuis` · `aktivitas`

### 6.4 Mesin ujian terpadu (`exam_*`)

`exam_engines` · `exam_banks` · `exam_sections` · `exam_questions` · `exam_question_options` · `exam_answers` · `exam_attempts` · `exam_sessions` · `exam_results` · `exam_violations`

### 6.5 Mesin lama (masih dicerminkan)

`cbt_question_banks` · `cbt_questions` · `cbt_test_sessions` · `cbt_results` · `cbt_violations`
`tryout_categories` · `tryout_subjects` · `tryout_packages` · `tryout_questions` · `tryout_question_options` · `tryout_sessions` · `tryout_student_answers` · `tryout_results`
`tka_assessment_items` · `tka_student_assessments` · `tka_assessment_responses` · `tka_dimensions_enhanced` · `tka_early_warnings` · `tka_historical_tracking` · `tka_parent_reports`

### 6.6 Presensi

`presensi_lokasi` · `presensi_pegawai`

### 6.7 Dapodik & integrasi

`dapodik_configs` · `dapodik_sync_logs` · `dapodik_import_logs` · `dapodik_data_cache` · `api_keys`

### 6.8 Infrastruktur Laravel

`cache` · `cache_locks` · `jobs` · `job_batches` · `failed_jobs` · `sessions` · `password_reset_tokens`

### 6.9 Model ↔ tabel

40 model mencakup seluruh tabel domain di atas. Relasi inti:

```
User (siswa) ↔ Siswa      via users.terhubung_dengan (JSON array id), fallback cocokkan NIS
                          dengan nama_pengguna / nama_pengguna tanpa awalan "siswa"
User (ortu)  ↔ Siswa      via users.terhubung_dengan (JSON array id)
User (guru)  1─n Siswa    via siswa.guru_id (wali kelas)
OrangTuaSiswa             tabel pivot tambahan untuk relasi ortu–siswa
Siswa n─1 Rombel
Rombel n─1 Semester
JadwalPelajaran n─1 Guru(Ptk), n─1 MataPelajaran, n─1 Rombel
Nilai n─1 Siswa, n─1 MataPelajaran, n─1 Semester
NilaiErapot 1─n NilaiErapotAudit  (setiap perubahan dicatat observer)
ExamAttempt 1─n ExamAnswer, 1─1 ExamResult, 1─n ExamViolation
CbtTestSession 1─n CbtResult  (cermin dari exam_*)
```

---

## 7. Alur bisnis per modul

### 7.1 Dapodik — tarik data (pull)

```
Admin → Setup → "Tarik Data Dapodik"  (admin.dapodik.index)
  │
  ├─ [Bridge lokal]  dapodik-bridge/ (Node.js) membaca database Dapodik sekolah
  │     ├─ POST /api/dapodik/sync/{modul}   (header X-API-Key, throttle 120/menit)
  │     └─ GET  /api/dapodik/sync/status
  │
  ├─ [WebService resmi]  /admin/dapodik/api/*  (session admin, bukan API key)
  │     ├─ GET  /config            POST /config
  │     ├─ POST /test-connection   POST /preview-data
  │     ├─ POST /pull              GET  /semesters
  │     └─ POST /sync
  │
  ├─ DapodikClient → DapodikSyncService
  │     ├─ upsert sekolah, guru/PTK, siswa, rombel, semester, pembelajaran
  │     ├─ tulis DapodikSyncLog (status, jumlah, error)
  │     └─ simpan cache di dapodik_data_cache
  │
  └─ Hasil: data referensi terisi → dipakai modul akademik
```

Tersedia juga `DapodikAutoSyncJob` dan `ImportDapodikJob` untuk eksekusi antrean, serta `integrasi:dapodik:cek` (`IntegrasiDapodikCheck`) untuk diagnosa koneksi.

Bridge punya sumber tunggal kebenaran tersendiri; test `DapodikBridgeSyncTest` dan `DapodikSingleSourceOfTruthTest` menjaga agar pull bridge dan pull API tidak menghasilkan data ganda.

### 7.2 Dapodik — kirim nilai (push)

```
Admin → Setup → "Kirim Nilai Ke Dapodik"
  └─ DapodikPushController / DapodikPushService
       ├─ pushSekolah, pushPesertaDidik, pushGtk, pushRombel, pushJadwal
       ├─ pushNilaiRapor  (nilai per mapel/semester)
       └─ pushKehadiran
     Setiap endpoint mengembalikan status; hasil dicatat di log.
```

### 7.3 e-Rapor — input nilai sampai cetak

```
Guru → Input Nilai e-Rapor (Guru\NilaiErapotController)
  ├─ pilih rombel + mapel
  ├─ simpan / autoSave / isi deskripsi
  └─ NilaiErapotObserver mencatat perubahan ke nilai_erapor_audit_logs
        (nilai lama, nilai baru, user, waktu)

Admin → Status Penilaian     : guru mana yang sudah/belum mengisi
Admin → Statistik Nilai      : distribusi nilai rapor
Admin → Perkembangan Nilai   : tren antar semester + grafik

Guru → Generate e-Rapor (Guru\ErapotGeneratorController)
  ├─ generatePdf    (satu siswa)
  ├─ generateBulkPdf(satu PDF gabungan untuk banyak siswa)
  └─ exportExcel

Admin → Cetak Nilai (Admin\CetakController)
  ├─ leger      : matriks siswa × mapel
  ├─ pelengkap  : data pelengkap rapor
  └─ nilai      : rekap nilai rapor
```

Audit nilai bisa diverifikasi ulang lewat `nilai:audit:verifikasi` (`VerifikasiAuditNilai`) dan service `NilaiErapotAuditService`.

### 7.4 Transkrip ijazah

```
Admin → Setting Transkrip   : konfigurasi header/format/nomor
Admin → Nomor Ijazah        : input manual atau import
Admin → Mapping Mapel       : samakan nama mapel rapor ↔ transkrip
Admin → Input Nilai         : per siswa, atau Import Nilai (Excel/CSV)
Admin → Cetak Transkrip     : PDF per siswa / massal
```

Model: `TranskripNilai`. Test: `TranskripTest`, `CetakTest`.

### 7.5 Mesin ujian (CBT / Tryout) dan TKA

Arsitektur "strangler": mesin baru `exam_*` menjadi tempat tulis, tabel lama dicerminkan agar laporan lama tetap benar. **TKA diagnostik** dan **simulasi TKA** berjalan di jalur terpisah.

```
┌──────────────┐   ┌──────────────────┐
│  CBT (guru)  │   │ Tryout (guru)    │
└──────┬───────┘   └────────┬─────────┘
       │                    │
       └────────► ExamAttemptService
                         │
                  ┌──────┴───────┐
                  │ ExamGrading  │  (ExamGradingService)
                  └──────┬───────┘
                         │
              ┌──────────┴──────────┐
              ▼                     ▼
        exam_attempts          exam_results
        exam_answers           exam_violations
              │
     CbtEngineBridge / TryoutEngineBridge
              │ (mirror, selama migrasi)
              ▼
        cbt_* / tryout_*

Jalur terpisah:
  TKA diagnostik (guru/admin)  → TkaAssessmentController → TKAService → tka_* (tka_assessment_items,
                                  tka_student_assessments, tka_assessment_responses, tka_early_warnings)
  Simulasi TKA (siswa)         → TkaSimulasiController → session (paket hardcoded, belum ke DB)
```

**Alur siswa mengerjakan ujian:**

```
Siswa → daftar ujian → start
  ├─ buat/ambil exam_attempt (status in_progress, durasi dari sesi)
  ├─ soal & opsi dimuat sesuai urutan bank soal
  ├─ jawaban disimpan per soal (submitAnswer) dan dihitung saat submit
  ├─ pelanggaran dicatat (CbtIntegrityController → exam_violations)
  ├─ submit → ExamGradingService menghitung skor
  │           ├─ pilihan ganda: exact match
  │           ├─ bobot per soal
  │           ├─ KKM/predikat dari setting()
  │           └─ tulis exam_results + mirror
  └─ hasil ditampilkan (result)
```

**Alur guru/admin:**

```
Guru  → Sesi Ujian CBT      : index, create, store, show, publish, start, complete, monitoring
      → Bank Soal CBT       : CRUD bank & soal, statistik, apiGetQuestions
      → Analisis Butir      : tingkat kesukaran, daya pembeda (ExamItemAnalysisService)
      → Ekspor Hasil        : exportResults / exportResultsEngine

Admin → Sesi & Bank Soal    : sama + forceComplete, destroy
      → Tryout              : paket → sesi → hasil, duplikat paket
      → TKA                 : dimensi, asesmen, early warning, riwayat, ekspor
```

**Finalisasi otomatis:** `cbt:finalize-expired` berjalan tiap menit (saat CBT aktif) untuk menutup attempt yang melewati waktu. `exam:backfill` mengisi `exam_*` dari data lama, `exam:rekonsiliasi` membandingkan keduanya.

**Simulasi TKA Pusmendik (modul baru, belum di-commit):**
`Siswa\TkaSimulasiController` — alur `index → confirm → start → exam → answer → submit → result`. Tiga subtes (literasi, numerasi, sains) dengan 5 soal per paket yang **di-hardcode di controller** (belum dari bank soal DB). Token ujian diturunkan dari `md5(id + user + tanggal)`; jawaban, riwayat, dan hasil disimpan di **session**, bukan di tabel. Skor → level Mahir/Cakap/Dasar/Perlu Intervensi. Test: `TkaSimulasiMandiriTest`.
> Konsekuensi: hasil simulasi hilang saat session berakhir dan tidak bisa dilaporkan ke wali/ortu. Jika modul ini akan dipakai produksi, perlu tabel + penyimpanan permanen.

### 7.6 Presensi

**Pegawai (guru/kepsek/tendik):**

```
Pegawai → Presensi Saya
  ├─ masuk  : ambil koordinat browser → GeofenceService cocokkan ke presensi_lokasi
  ├─ pulang : idem + hitung durasi
  └─ simpan ke presensi_pegawai
```

`GeofenceService` menghitung jarak (haversine) terhadap radius lokasi; `MapelAttendancePolicy` menentukan apakah presensi mapel wajib.

**Siswa:**

```
Guru → Kehadiran Siswa
  ├─ scan QR kartu siswa (StudentQrHasher memvalidasi hash)
  ├─ setStatus (hadir/sakit/izin/alpa)
  └─ simpan ke kehadiran / kehadiran_mapel

Admin → Pemetaan QR Kartu Siswa : bind / reset kartu
```

### 7.7 Kuis & materi

```
Guru  → Kuis & Soal : CRUD kuis, toggle aktif, lihat hasil
      → Materi Ajar: CRUD materi (judul, isi, lampiran)
Siswa → Materi      : daftar & detail
      → Kuis        : daftar → kerjakan → submit (batas waktu) → hasil
```

Batas waktu kuis dijaga `KuisBatasWaktuTest`.

### 7.8 Kokurikuler P5

```
Admin → Tema        : CRUD tema
      → Kegiatan    : CRUD kegiatan per tema
      → Kelompok    : CRUD kelompok + anggota (anggota_kelompok)
Guru  → Profil Lulusan / dimensi : store & hapus dimensi
Ortu  → Isi 7 Kebiasaan  : store kebiasaan anak
      → Rekap Kebiasaan  : ringkasan
Kepsek→ Rekap Kebiasaan
```

### 7.9 Monitoring kepsek

```
Kepsek → Dashboard        : ringkasan sekolah
       → Rekap Sekolah    : rekap + unduh
       → Peta Kelas       : sebaran siswa per rombel
       → Hasil Belajar    : agregat nilai
       → Pantau Aktivitas : aktivitas guru/siswa
       → Log Aktivitas    : tabel aktivitas
       → Rekap Kebiasaan
```

### 7.10 Orang tua

```
Ortu → Ringkasan          : kartu ringkas anak
     → Perkembangan Nilai : tren nilai
     → Kehadiran Anak     : rekap kehadiran
     → Catatan Guru       : catatan wali/guru
     → Laporan / Rapor    : unduh rapor
     → Laporan TKA        : TkaParentController (laporan kesiapan anak)
```

Semua akses anak dibatasi trait `HasAnakLookup` (ortu) dan `HasSiswaLookup` (siswa) agar tidak bisa membaca data siswa lain.

### 7.11 Backup & restore

```
Admin → Backup & Restore
  ├─ export     : BackupJsonStreamWriter menulis JSON per tabel (streaming)
  │               daftar tabel = App\Support\BackupTables (sumber tunggal)
  ├─ import     : BackupJsonStreamReader membaca & restore
  └─ wipeAll    : kosongkan tabel sesuai BackupTables
```

Job `ExportBackupJob` untuk ekspor besar; `simantap:backup:drill` (`BackupRestoreDrill`) menguji restore ke DB sementara. Jadwal harian 02:00 lewat `simantap:backup`.

### 7.12 Google Sheets

```
Guru  → Google Sheet  : connect, disconnect, updateSettings, fullSync
Admin → Penyimpanan Data : connect / disconnect
Trait SyncableToSheet dipakai controller Guru\NilaiController, Guru\KehadiranController,
Guru\DimensiController, dan Ortu\KebiasaanController untuk mendorong baris ke sheet
setelah data disimpan; command sheets:full-sync untuk sinkronisasi penuh.
```

### 7.13 Landing page & statistik TKA publik

Landing (`resources/views/landing.blade.php`) berisi section:
`hero` · `demo` · `features` · `stats` · `quiz` (kesiapan sekolah) · `how-it-works` · `modules` · `testimonials` · `blog` · `cta` · `tka-statistik` (chart harian & kumulatif, tab peserta/daftar) · `updates` · `faq`.

Data statistik diambil dari `TkaStatistikService`, di-refresh `tka:refresh` setiap 6 jam dan bisa dipaksa lewat tombol refresh (auth).

---

## 8. Inventaris halaman & menu per peran

### 8.1 Admin — 4 tab (shell `layouts/admin-with-tabs.blade.php`)

Tab ditentukan `activeAdminTab()` di `app/helpers.php`; setiap tab punya sidebar sendiri.

**Tab E-Rapor** (`sidebar-erapor.blade.php`)

| Grup | Halaman |
|---|---|
| Utama | Dasbor Sistem · Profile |
| Status Penilaian | Status per Guru · Statistik Nilai Rapor |
| Perkembangan Nilai | Perkembangan Nilai · Grafik Nilai Rapor |
| Transkrip Ijazah | Setting Transkrip · Nomor Ijazah · Mapping Mapel · Input Nilai Transkrip · Import Nilai Transkrip · Cetak Transkrip Nilai |
| Cetak Nilai | Leger Rapor · Pelengkap Rapor · Nilai Rapor |
| Pengaturan Cetak & Rapor | Logo dan TTD · Tanggal Rapor · Mapping Rapor · Kelompok Mapel · Data Ekstrakurikuler · Cek Alignment |

**Tab CBT/Ujian** (`sidebar-cbt.blade.php`)

| Grup | Halaman |
|---|---|
| Utama | Dasbor Utama · Statistik Ujian |
| Manajemen Ujian CBT | Sesi Ujian CBT · Bank Soal CBT |
| Tryout Sekolah | Paket Tryout · Sesi Tryout · Hasil Tryout |
| Sistem | Modul & Tampilan · Log Aktivitas |

**Tab TKA** (`sidebar-tka.blade.php`)

Dashboard Kesiapan TKA · Dimensi & Kisi-kisi TKA · Data Penilaian Peserta · Early Warning Kesiapan

**Tab Setup** (`sidebar-setup.blade.php`)

| Grup | Halaman |
|---|---|
| Dapodik | Tarik Data Dapodik (badge waktu sync) · Kirim Nilai Ke Dapodik |
| Data Sekolah & Semester | Profil Sekolah · Data Semester & Tahun Ajaran |
| Data Guru & Siswa | Data Guru & PTK · Data Siswa · Foto Siswa |
| Rombel & Pembelajaran | Rombel & Peta Kelas · Mata Pelajaran · Data Pembelajaran |
| Akun & Kode Akses | Manajemen Akun · Cetak Kode Akses Login |
| Presensi | Presensi Pegawai & Lokasi · Pemetaan QR Kartu Siswa |
| Data Kokurikuler (P5) | Daftar Tema · Kegiatan Kokurikuler · Kelompok Kokurikuler |
| Sistem & Modul | Modul & Tampilan · Bobot & Ketuntasan · Penyimpanan Data · Backup & Restore · Log Aktivitas |

**Sidebar default** (`admin/partials/sidebar.blade.php`) menggabungkan semuanya + grup CBT & E-LEARNING, TKA, dan SYSTEM — dipakai sebagai fallback/legacy. Ini berarti menu admin **terduplikasi di 5 partial**; setiap penambahan menu harus menyentuh tab yang relevan.

### 8.2 Guru (37 view)

Dasbor · Materi Ajar · Profil Lulusan · Asesmen & Nilai (Input Nilai Cepat, Input Nilai e-Rapor, Kuis & Soal, Daftar Nilai, Analisis Belajar) · CBT (Sesi, Bank Soal) · Tryout (Paket, Sesi) · TKA (Monitoring Kesiapan, Analisis Kognitif, Dimensi & Kisi-kisi, Penilaian Diagnostik) · Presensi Saya · Kehadiran Siswa · Catatan Siswa · 7 Kebiasaan · Laporan & Rapor · Generate e-Rapor · Data Siswa · Google Sheet · Pengaturan

### 8.3 Siswa (14 view)

Beranda · Materi · Kuis · Nilaiku · Profil Saya · Ujian CBT · Tryout Sekolah · **Simulasi TKA Pusmendik**

### 8.4 Orang tua (9 view)

Ringkasan · Isi 7 Kebiasaan · Rekap Kebiasaan · Perkembangan Nilai · Kehadiran Anak · Catatan Guru · Laporan/Rapor · Laporan TKA

### 8.5 Kepala sekolah (10 view)

Dashboard · Rekap Sekolah · Presensi Saya · Peta Kelas · Hasil Belajar · Pantau Aktivitas · Rekap Kebiasaan · Log Aktivitas

### 8.6 Tendik (2 view)

Presensi Saya (index, masuk, pulang)

---

## 9. Inventaris fungsi per controller

Format: `Controller` → daftar method publik.

### 9.1 Admin

```
ApiKeyController          index, store, destroy, toggle
BackupController          index, export, import, wipeAll
BobotController           index, update
CetakController           leger, pelengkap, nilai, showNilai
DapodikImportController   index, import, preview, logDetail, logDestroy, downloadBridge
DapodikPushController     index
DataReferensiController   guru, pembelajaran, storePembelajaran, destroyPembelajaran,
                          alignmentCheck, refreshAlignment, exportUnmatchedAlignment,
                          tanggalRapor, storeTanggalRapor, destroyTanggalRapor,
                          kelompokMapel, mappingRapor, updateMapelMeta,
                          ekstrakurikuler, storeEkstrakurikuler, updateGelarPtk,
                          destroyEkstrakurikuler, logoTtd, storeLogoTtd,
                          fotoSiswa, storeFotoSiswaSatuan, storeFotoSiswa
KodeAksesController       index, update
KokurikulerController     tema, storeTema, destroyTema, kegiatan, storeKegiatan,
                          destroyKegiatan, kelompok, showKelompok, storeKelompok,
                          destroyKelompok, updateAnggota
LogController             index
MapelController           index, create, store, edit, update, destroy, destroyAll,
                          destroySelected, importDapodik, importLokal, seedDefault
ModulController           index, update
PenilaianController       status, statistik
PerkembanganController    index, grafik
PetaKelasController       daftar, detail, index, store, destroy, pindahSiswa,
                          keluarkanSiswa, getKelasOptions
PresensiController        index, storeLocation, updateLocation, destroyLocation, koreksi
PresensiKartuController   index, bind, reset
ProfileController         show, updatePassword
SekolahController         index, search, update
SemesterController        index, store, destroy, activate
SheetController           index, connect, disconnect
TranskripController       setting, updateSetting, nomor, updateNomor, importNomor,
                          input, formInput, storeInput, importNilai, templateNilai,
                          storeImportNilai, cetak, showCetak
UserController            index, create, store, edit, update, destroy, destroyAll,
                          akunMassal, prosesAkunMassal, siswaIndex
Admin\Cbt\CbtBankController    index, create, store, show, edit, update, destroy, statistics
Admin\Cbt\CbtSessionController index, create, store, show, updateStatus, forceComplete,
                               destroy, analisisButir, exportResults, exportResultsEngine
Admin\Tryout\TryoutPackageAdminController
                          index, create, store, show, edit, update, destroy, statistics,
                          allSessions, createSession, storeSession, showSession,
                          editSession, updateSession, destroySession, forceCompleteSession,
                          allResults, showResult, showEngineResult, exportResults,
                          toggleStatus, duplicate
Admin\Tka\TkaAdminController   index, dimensions, createDimension, storeDimension,
                          showDimension, editDimension, updateDimension,
                          toggleDimensionStatus, assessments, earlyWarnings,
                          showWarning, updateWarningStatus, generateReport,
                          studentHistory, export
```

### 9.2 Auth

```
LoginController              showLoginForm, login, logout
ForcePasswordChangeController show, update
ForgotPasswordController     showLinkRequestForm, sendResetLinkEmail
ResetPasswordController      showResetForm, reset
```

### 9.3 Guru

```
DashboardController      index
AnalisisController       index, detail
CatatanController        index, store, destroy
DimensiController        index, store, destroy
ErapotGeneratorController index, generatePdf, generateBulkPdf, exportExcel
GuruKebiasaanController  index, show
ImportErapotController   downloadTemplate, import, export
InputNilaiController     index, getNilai, simpanNilai, pasteFromExcel
IntegrasiController      index, connect, disconnect, updateSettings, fullSync
KehadiranController      index, jadwal, scan, setStatus, store, destroy
KuisController           index, create, store, edit, update, destroy, toggle, hasil
LaporanController        index, pdf
MateriController         index, create, store, edit, update, destroy, show
NilaiController          index, create, store, edit, update, destroy
NilaiErapotController    index, simpan, autoSave, deskripsi
PengaturanController     index, update
SiswaController          index, create, store, edit, update, destroy, unduh,
                         impor, pungut, getKelasSiswa, simpanPungut
TKAController            index, show, store, analysis
```

### 9.4 Siswa

```
DashboardController     index
KuisController          index, show, submit
MateriController        index, show
NilaiController         index, detail
ProfilController        show, edit, update
TkaSimulasiController   index, confirm, start, exam, answer, submit, result   ← baru/untracked
```

### 9.5 CBT (guru & siswa)

```
Cbt\CbtQuestionBankController  index, create, store, show, edit, update, destroy, apiGetQuestions
Cbt\CbtQuestionController      store, update, destroy
Cbt\CbtTestSessionController   index, create, store, show, publish, start, complete,
                               monitoring, apiMonitoring
Cbt\CbtStudentController       index, start, begin, submitAnswer, submit, result
Cbt\CbtIntegrityController     logViolation, getViolations
```

### 9.6 Tryout

```
Tryout\TryoutPackageController index, create, store, show, edit, update, destroy
Tryout\TryoutSessionController index, create, store, show, publish, start, complete,
                               monitoring, apiMonitoring
Tryout\TryoutStudentController index, start, submitAnswer, complete, result
```

### 9.7 TKA

```
Tka\TkaAssessmentController index, create, store, show, bySiswa
Tka\TkaDimensionController  index, create, store, show, edit, update, destroy,
                            addItem, destroyItem, getItems
Tka\TkaParentController     index, show, generateReport
TkaController               index, api, refresh          (statistik publik)
```

### 9.8 Kepsek

```
DashboardController    index, pantau
AktivitasController    index
HasilBelajarController index
KebiasaanController    index
PetaKelasController    index, detail
RekapController        index, detail, unduh
```

### 9.9 Ortu

```
DashboardController  index
KebiasaanController  index, store
KehadiranController  index
CatatanController    index
LaporanController    index
RekapController      index
```

### 9.10 API & pegawai

```
Api\DapodikController      config, saveConfig, testConnection, preview, pull,
                           semesters, sync, ping
Api\DapodikPushController  pushSekolah, pushPesertaDidik, pushGtk, pushRombel,
                           pushJadwal, pushNilaiRapor, pushKehadiran, status
Api\DapodikSyncController  store, status
Api\SchoolLookupController lookup
Pegawai\PresensiController index, masuk, pulang
LandingController          index, demo
DashboardController        admin, ortu, ortuNilai
```

---

## 10. Service, job, command

### 10.1 Service (39)

| Kelompok | Service |
|---|---|
| Ujian | `ExamAttemptService`, `ExamGradingService`, `ExamDashboardService`, `CbtExamService`, `CbtScoring`, `TryoutScoring`, `CbtEngineBridge`, `TryoutEngineBridge`, `Exam\LegacyExamMapper`, `Exam\ExamReconciliationService`, `Exam\ExamReportService`, `Exam\ExamItemAnalysisService`, `Exam\CbtDashboardProvider`, `Exam\CbtStudentReader`, `Exam\TryoutStudentReader`, `Exam\TryoutResultProvider` |
| Dapodik | `DapodikService`, `Dapodik\DapodikClient`, `Dapodik\DapodikSyncService`, `Dapodik\DapodikPushService`, `Dapodik\helpers` |
| e-Rapor | `NilaiService`, `NilaiErapotAuditService`, `ErapotKemdikbudService`, `LaporanService`, `ExcelExportService` |
| TKA | `TKAService`, `TkaStatistikService`, `TryoutResultDetailService` |
| Presensi | `Presensi\GeofenceService`, `Presensi\MapelAttendancePolicy`, `Presensi\StudentQrHasher` |
| Lain | `AktivitasService`, `BackupRestoreService`, `Backup\BackupJsonStreamReader`, `Backup\BackupStreamWriter`, `GoogleSheetsService`, `JadwalPelajaranResolver`, `SchoolDataService` |

### 10.2 Job (4)

`DapodikAutoSyncJob` · `ImportDapodikJob` · `ExportBackupJob` · `ProsesAkunMassalJob`

### 10.3 Command (10)

| Command | Class | Fungsi |
|---|---|---|
| `simantap:backup` | `BackupData` | Backup harian (02:00) |
| `simantap:backup:drill` | `BackupRestoreDrill` | Uji restore ke DB sementara |
| `exam:backfill` | `ExamBackfill` | Isi `exam_*` dari tabel lama |
| `exam:rekonsiliasi` | `ExamReconciliation` | Bandingkan `exam_*` vs `cbt_*`/`tryout_*` |
| `exam:analisis-butir` | `AnalisisButir` | Analisis butir soal |
| `nilai:audit:verifikasi` | `VerifikasiAuditNilai` | Verifikasi konsistensi audit nilai |
| `integrasi:dapodik:cek` | `IntegrasiDapodikCheck` | Diagnosa koneksi Dapodik |
| `integrasi:sheets:cek` | `IntegrasiSheetsCheck` | Diagnosa koneksi Google Sheets |
| `tka:refresh` | `RefreshTkaStatistik` | Segarkan statistik TKA (tiap 6 jam) |
| `sheets:full-sync` | `FullSyncSheet` | Sinkronisasi penuh Google Sheets |

> `cbt:finalize-expired` bukan class di `app/Console/Commands`; ia didefinisikan langsung di `routes/console.php` dan dijadwalkan tiap menit saat CBT aktif.

### 10.4 Policy (7)

`CbtQuestionBankPolicy` · `CbtQuestionPolicy` · `CbtResultPolicy` · `CbtTestSessionPolicy` · `TkaAssessmentPolicy` · `TryoutResultPolicy` · `TryoutSessionPolicy`

---

## 11. Integrasi eksternal

| Integrasi | Arah | Titik masuk |
|---|---|---|
| Dapodik API resmi | masuk & keluar | `/admin/dapodik/api/*` (session), `Api\DapodikController`, `DapodikPushService` |
| Dapodik bridge (Node) | masuk | `dapodik-bridge/`, `POST /api/dapodik/sync/{modul}` (API key), `POST /api/dapodik/ping` |
| Google Sheets | keluar | `GoogleSheetsService`, trait `SyncableToSheet` |
| e-Rapor Kemdikbud | keluar | `ErapotKemdikbudService` |
| Pusmendik / TKA | masuk (statistik) | `TkaStatistikService`, `TkaController` |
| Lookup sekolah | masuk | `POST /api/sekolah/lookup` (throttle 30/menit) |

---

## 12. Keamanan

| Kontrol | Implementasi |
|---|---|
| Password hashing | bcrypt (default Laravel; tidak ada `config/hashing.php` khusus) |
| Session | regenerate saat login, invalidate saat logout |
| Brute force | throttle 5/menit (controller) + 10/menit (rute), reset-sandi 5/menit |
| Otorisasi | `role` middleware + 7 policy + scope trait per peran |
| Paksa ganti sandi | `force.password.change` |
| Header | `SecurityHeaders`: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS (hanya HTTPS). **CSP kosong secara default** (diaktifkan lewat `config/security.php`) |
| API | `apikey` middleware untuk `/api/dapodik/sync/*`; kunci disimpan sebagai SHA-256 di `api_keys.key_hash`, plaintext hanya ditampilkan sekali |
| Audit nilai | observer mencatat setiap perubahan ke `nilai_erapor_audit_logs` |
| Backup | daftar tabel terpusat, drill restore |
| Modul | `module.asesmen` mematikan modul ujian tanpa menghapus data (balas 404) |

Dokumentasi terkait: `docs/04-SECURITY-GUIDE.md`, `docs/ROTASI-KREDENSIAL.md`.

---

## 13. Pengujian & CI

- **65 file test PHPUnit**, terbagi 56 Feature + 9 Unit.
- Test penting: `RouteSweepTest` (semua rute tidak 500), `CriticalRoutesRegressionTest`, `AuditHalamanRegressionTest`, `MenuAuditTest`, `SidebarCoverageAuditTest`, `BackupCoverageTest`, `ExamEngineParityTest`, `CbtEngineParityTest`, `TryoutEngineParityTest`, `NilaiErapotAuditTest`, `DapodikBridgeSyncTest`, `DapodikSingleSourceOfTruthTest`, `KesiapanLaunchTest`.
- **E2E Playwright** di `tests/e2e/` (panduan: `docs/PANDUAN_UJI_ROUTE_PLAYWRIGHT.md`).
- **CI** `.github/workflows/test.yml` menjalankan test pada push/PR.
- Database test: **SQLite in-memory** → hindari SQL khusus PostgreSQL (`ilike`, `to_char`) di kode yang tersentuh test.

Perintah:

```powershell
C:\Users\USER\.config\herd\bin\php84\php.exe artisan test
C:\Users\USER\.config\herd\bin\php84\php.exe artisan test --filter=RouteSweepTest
npx playwright test
```

---

## 14. Menjalankan & operasional

### 14.1 Setup awal

```powershell
composer install
copy .env.example .env
C:\Users\USER\.config\herd\bin\php84\php.exe artisan key:generate
C:\Users\USER\.config\herd\bin\php84\php.exe artisan migrate --seed
npm install && npm run build
```

### 14.2 Seeder

| Seeder | Isi |
|---|---|
| `DatabaseSeeder` | Orkestrator + data minimal |
| `DemoDataSeeder` | Data demo lengkap (sekolah, siswa, nilai) |
| `E2eSeeder` / `E2eFixtureSeeder` | Fixture untuk Playwright |
| `LoadTestSeeder` | Data volume besar untuk uji beban |
| `ApiKeySeeder` | API key awal |

### 14.3 Scheduler

Wajib ada satu cron `* * * * * php artisan schedule:run` agar:

- backup harian 02:00,
- `tka:refresh` tiap 6 jam,
- `cbt:finalize-expired` tiap menit (bila CBT aktif).

### 14.4 Go-live

Checklist ada di `docs/RUNBOOK-GO-LIVE.md`, `docs/CHECKLIST-STAGING-INTEGRASI.md`, dan `LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md`.

---

## 15. Status, risiko, dan dokumen

### 15.1 Kondisi working tree

`git status` menunjukkan **58 file tracked berubah + 8 file baru** di `main` (±5.092 baris ditambah, 2.631 dihapus), mencakup:

- redesain UI CBT/TKA dan sidebar,
- `Siswa\TkaSimulasiController` + `resources/views/siswa/tka/` (**untracked**),
- test baru: `DapodikBridgeSyncTest`, `DapodikSingleSourceOfTruthTest`, `SidebarCoverageAuditTest`, `TkaSimulasiMandiriTest`,
- perubahan `docs/05-ARCHITECTURE.md`, `app/helpers.php`, `routes/web.php`.

**Risiko:** fitur simulasi TKA dan redesain belum masuk riwayat git; jika perlu rollback, perubahan ini akan hilang. Disarankan commit bertahap (per modul) sebelum pekerjaan lain.

### 15.2 Utang teknis yang terlihat

| Area | Catatan |
|---|---|
| Duplikasi sidebar admin | 5 partial (`sidebar`, `-erapor`, `-cbt`, `-tka`, `-setup`) harus disunting bersamaan |
| Dua mesin ujian | `exam_*` dan `cbt_*`/`tryout_*` hidup bersama; `exam_engine_readers` masih `false` → laporan masih membaca tabel lama |
| Menu "planned" | Sidebar admin masih punya mekanisme `planned` (item tanpa `route` tampil nonaktif). Saat ini tidak ada item planned; jangan beri `route` pada item yang ditandai planned |
| Dokumen basi | Lihat 15.3 |
| Graphify | `graphify-out/graph.json` tanggal 19 Okt 2026, tidak punya `GRAPH_REPORT.md`/`graph.html`, dan belum memuat modul TKA simulasi |

### 15.3 Status dokumen di `docs/`

| Dokumen | Status |
|---|---|
| `13-PETA-PROYEK.md` (ini) | **Baru — acuan struktur & alur** |
| `05-ARCHITECTURE.md` | Relevan (sedang diubah di working tree) |
| `AUDIT-FUNGSI-HALAMAN-2026-10-25.md` | Relevan — audit halaman/tombol terbaru |
| `LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md` | Relevan — kesiapan rilis |
| `CBT_TRYOUT_TKA_DESIGN.md` | Relevan — desain mesin ujian (besar) |
| `CBT.md`, `CBT_IMPLEMENTATION_PROGRESS.md` | Relevan untuk modul CBT |
| `04-SECURITY-GUIDE.md`, `ROTASI-KREDENSIAL.md` | Relevan |
| `09`–`12` (alignment e-Rapor) | Relevan, area Kiro |
| `01-AUDIT-REPORT.md`, `03-REMAINING-ISSUES.md`, `07-MENU-ROADMAP.md` | **Basi** — angka model/tabel/menu tidak lagi cocok |
| `arsip/DATABASE_MODELS_AND_RELATIONSHIPS.md` | **Basi** (menyebut 31 model; aktual 40) |

### 15.4 Pembagian kerja tim (dari `AGENTS.md`)

- **Kiro**: CBT (`app/**/Cbt/`, `Cbt*.php`, migrasi `*cbt*`), alignment e-Rapor (docs 09–12), dasbor per peran.
- **Opencode**: Transkrip, Cetak, struktur sidebar, security, `TranskripTest`/`CetakTest`.
- **File bersama**: `routes/web.php`, `sidebar.blade.php`, `MenuAuditTest.php`, `layouts/app.blade.php` — baca dulu, edit seperlunya, `phpunit` wajib hijau sebelum commit.

---

## Lampiran A — Peta cepat "saya mau ubah X, buka di mana?"

| Kebutuhan | File |
|---|---|
| Tambah menu sidebar | `resources/views/{peran}/partials/sidebar*.blade.php` |
| Tambah rute | `routes/web.php` (perhatikan grup role + flag CBT) |
| Ubah tampilan admin (tab) | `resources/views/layouts/admin-with-tabs.blade.php`, `components/admin-tabs.blade.php` |
| Ubah nilai/KKM | `app/helpers.php` (`getKKM`, `setting`), `Admin\BobotController` |
| Ubah perhitungan ujian | `app/Services/ExamGradingService.php`, `CbtScoring`, `TryoutScoring` |
| Ubah geofence | `app/Services/Presensi/GeofenceService.php` |
| Tambah tabel ke backup | `app/Support/BackupTables.php` |
| Ubah landing | `resources/views/landing.blade.php` + `LandingController` |
| Ubah header keamanan | `app/Http/Middleware/SecurityHeaders.php`, `config/security.php` |
| Ubah flag fitur | `config/simantap.php` + `.env` |
| Audit nilai | `app/Observers/NilaiErapotObserver.php`, `NilaiErapotAuditService` |

---

*Dokumen ini dihasilkan dari pembacaan kode dan `artisan route:list` pada 4 November 2026. Perbarui angka pada §2 jika struktur berubah.*
