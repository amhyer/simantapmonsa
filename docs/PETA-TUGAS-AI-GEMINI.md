# Peta Tugas & Orkestrasi Multi-Agen AI Gemini — SIMANTAP

**Dokumen koordinasi kerja** untuk memetakan kondisi proyek, pembagian tugas antar-model AI Gemini (Swarm & Multi-Agen), gelombang eksekusi, serta batas kepemilikan berkas agar kolaborasi paralel berjalan aman tanpa konflik.

- **Basis Commit Awal:** `a85b10b` (`feat(tka): add evidence-based TKA readiness monitoring module`, 16 November 2026) · **Pembaruan Terakhir:** 17 November 2026 (pasca-merge PR `#27` `cf2a8fa` + Gelombang 2A, sinkronisasi 3A–3C, 4A, & refaktor dokumentasi `.md`)
- **Branch Aktif:** `main` (perubahan terbaru dikerjakan melalui PR dari branch kerja)
- **Acuan Arsitektur & Koordinasi:** `AGENTS.md`, `CLAUDE.md`, [`RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md`](RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md), [`PROMPT-ELEARNING-PERANGKAT-MENU.md`](PROMPT-ELEARNING-PERANGKAT-MENU.md), dan [`DESAIN-ELEARNING-DAN-WALI-KELAS.md`](DESAIN-ELEARNING-DAN-WALI-KELAS.md)

---

## 1. Ringkasan Status Proyek Aktual (Kroscek GitHub `main` @ `83dac21`)

### Hasil Verifikasi Baseline (`a85b10b` / `83dac21`)
- **Pemeriksaan Statis (`tools/verifikasi-perbaikan.php`):** `63/63 lulus (0 gagal)`.
- **Smoke Test Rute SQLite (`RouteSweepTest`):** `2.392` kombinasi route GET × akun fixture = `0` endpoint `5xx` di SQLite.
- **Modul Pemantauan Kesiapan TKA (`TkaKesiapanServiceTest`, `TkaKesiapanHttpTest`):** `25/25 lulus`.
- **Kroscek GitHub Actions CI (`Tests` run `#43889950905` di `main` & PR `#26`):** 3 dari 4 job gagal:
  - **PHPUnit (PHP 8.4 / SQLite):** gagal di step `Audit menu dan hasilkan matriks` (`MenuInventoryTest` — 4 rute duplikat pada sidebar Guru). Pada suite lokal SQLite sebelumnya `603/607` dengan **4 kegagalan terpetakan**:
    1. `Tests\Feature\AdminMenuTest::test_sidebar_tanpa_label_ganda_dan_submenu_tertutup_default` (`tests/Feature/AdminMenuTest.php:95`) — sejak commit `e066cbb`, `admin.dashboard` berada di bawah tab `setup`, sedangkan test masih memeriksa label grup tab `erapor` (`Pengaturan Cetak & Rapor`) pada respons `/admin/dashboard`.
    2. `Tests\Feature\AuditRemediation2026Test::test_p2_seluruh_tabel_web_ui_memakai_table_wrapper_dan_katex_self_hosted` (`tests/Feature/AuditRemediation2026Test.php:309`) — 4 tabel (termasuk template HTML cetak pada `<script>`) belum dibungkus `.table-wrapper`:
       - `resources/views/admin/referensi/foto-siswa.blade.php:246`
       - `resources/views/admin/referensi/guru.blade.php:454`
       - `resources/views/admin/users/index.blade.php:769`
       - `resources/views/admin/users/siswa.blade.php:477`
    3. `Tests\Feature\LoginViewTest::test_halaman_login_menampilkan_tombol_kembali_dan_toggle_mata_password` (`tests/Feature/LoginViewTest.php:18`) — test mengharapkan `route('home')` (`/dashboard`), sedangkan tombol kembali di `resources/views/auth/login.blade.php` mengarah ke beranda publik `route('landing')` (`/`).
    4. `Tests\Feature\MenuInventoryTest::test_inventaris_menu_konsisten_dan_laporannya_dibuat` (`tests/Feature/MenuInventoryTest.php:107`) — grup "Menu Wali Kelas" pada `resources/views/guru/partials/sidebar.blade.php` menduplikasi 4 rute (`guru.siswa.index`, `guru.kehadiran.index`, `guru.catatan.index`, `guru.erapor.index`) dalam satu peran.
  - **PHPUnit (PHP 8.4, PostgreSQL 16):** `596 passed / 11 failed` — selain 4 kegagalan di atas, kroscek GitHub CI menemukan **5 akar masalah tambahan khusus PostgreSQL/CI**:
    5. `TemaKokurikuler` di `WaliKelasController::p5Index` dan `Admin\RaporMerdekaController::p5Index` memanggil kolom `nama_tema` padahal kolom tabel `tema_kokurikuler` adalah `nama` (`SQLSTATE[42703]`), memicu transaksi PostgreSQL aborted (`25P02`) yang meruntuhkan `MenuAuditTest` (52 rute) dan `RouteSweepTest`.
    6. `SuperAdmin\TenantController` (`destroyTenant`, `destroyUser`, `bulkDestroyUsers`) menghapus `aktivitas` dengan kolom `user_id`, padahal kolom tabel `aktivitas` adalah `guru_id` (`SQLSTATE[42703]`, mematahkan `SuperAdminAccessTest`).
    7. `DapodikBridgeSyncTest::test_export_nilai_rapor_returns_data_for_bridge` memasukkan `'semester' => 'ganjil'` (huruf kecil) yang melanggar check constraint `nilai_erapor_semester_check` (`'Ganjil'`/`'Genap'`).
    8. `DapodikResetTest::test_admin_sekolah_dapat_melihat_halaman_dan_mengunduh_bridge` tidak menyiapkan berkas dummy `SIMANTAP-Bridge.exe` di lingkungan CI sehingga respons menjadi `302` alih-alih `200`.
  - **Authenticated Playwright E2E (`tests/e2e/global-setup.js:41`):** gagal timeout karena mencari tombol `name: 'Masuk', exact: true`, sementara tombol di `resources/views/auth/login.blade.php` berlabel `Masuk ke Sistem`.
  - **Browser smoke (Chromium):** LULUS.

### Modul Utama yang Sudah Tersedia (Terverifikasi Commit Historis)
- **Pemantauan Kesiapan TKA (`a85b10b`):** Agregasi bukti read-only lintas sumber (`nilai`, `kuis`, `nilai_erapor`, `exam_*` / fallback legacy `cbt_results` & `tryout_results`, `tka_student_assessments`, `tka_adaptive_simulations`), validasi histori kelas 4–6, pelabelan regulasi BSKAP/Pusmendik, privasi agregat Kepsek/Admin, feature flag `TKA_KESIAPAN_AKTIF`, serta backup/restore (`docs/TKA-KESIAPAN.md`).
- **Wali Kelas, TP/P5, Rapor Siswa & ZKTeco TCP (`7db9356`):** Fitur wali kelas, pencatatan TP/P5, akses rapor siswa, dan sinkronisasi mesin presensi TCP.
- **Modifikasi Tampilan Rapor & Rapor Merdeka Parity (`3553d8b`, `dedd47c`, `2dc9462`):** 5 modul styling rapor + live preview, kenaikan kelas, PTS/Buku Induk/P5BK print, grid TTD wali, forum, matriks kelengkapan nilai, kunci rombel, dan impersonasi guru.
- **Navigasi Admin & Engine CBT/Tryout (`e066cbb`, `d243220`, `c41dc06`):** Relokasi tab `Pengaturan` & `Dasbor`, tampilan ujian CBT ala ANBK/Pusmendik, mode wajib layar penuh, pintasan admin `Ctrl+K`, dan reset jawaban saat pelanggaran.

---

## 2. Arsitektur & Pemetaan Peran Masing-Masing AI Gemini

Pembagian tugas Swarm menggunakan spesialisasi model Gemini dengan kepemilikan ruang kerja dan batas berkas yang jelas:

| Roster / Agen | Model ID (`swarm`) | Peran Utama | Area & Tanggung Jawab | Mode Workspace Disarankan |
| :--- | :--- | :--- | :--- | :--- |
| **Coordinator** (`coordinator`) | `gemini-pro-orchestrator-g1-pro` (*Orchestrator Argon*) | Koordinator Utama, Penjaga Arsitektur & *Shared-File Gatekeeper* | Membagi paket kerja, menegakkan aturan `AGENTS.md`, meninjau & menggabungkan diff pada file bersama (`routes/web.php`, `resources/views/*/partials/sidebar.blade.php`, `resources/views/layouts/app.blade.php`, `tests/Feature/MenuAuditTest.php`), serta mengelola commit/merge. | `inherit` |
| `worker-backend-security` | `gemini-4-hydrogen-xhigh` (*Gemini 4 Hydrogen XHigh*) | Principal Backend, Security & Multi-Tenant Architect | Isolasi `tenant_id` (`BelongsToTenant`, middleware `IdentifyTenant`), pembukaan bertahap fitur yang dibatasi pada multi-sekolah (Backup/Restore per tenant, konfigurasi Dapodik & PTK per sekolah, tarik mesin presensi per sekolah), audit Policy/IDOR, dan rekonsiliasi data historis. | `share` |
| `worker-academic-engine` | `gemini-4-hydrogen-high` (*Gemini 4 Hydrogen High*) | Academic Engine, Reporting & Data Validation Specialist | Pengembangan modul akademik & pelaporan (e-Rapor, Transkrip, Cetak, KKM & Peta Kelas Kepala Sekolah per sekolah), modul **Validasi & Kelengkapan Data** Admin, serta **Pusat Notifikasi & Tugas** lintas peran. Menjaga kueri bebas N+1 dan tanpa cache model Eloquent. | `share` |
| `worker-ui-nav` | `gemini-4-hydrogen-medium` (*Gemini 4 Hydrogen Medium*) | Frontend Blade/Alpine, Sidebar & Mobile UX Engineer | Kepatuhan `.table-wrapper` pada tabel Blade (`resources/css/layout.css`), perapian navigasi sidebar tanpa duplikasi rute, pencarian menu (`Ctrl+K` lintas peran), bantuan singkat kontekstual, dan pengerasan UX mobile. | `share` |
| `worker-qa-verifier` | `gemini-3.8-flash-high` (*Gemini 3.8 Flash High*) | QA Automation, Regression Suite & E2E Verification Runner | Penyelarasan test regresi PHPUnit hingga `607/607` hijau, eksekusi `tools/verifikasi-perbaikan.php` & `RouteSweepTest`, serta penyiapan & eksekusi Playwright E2E (`playwright.config.js` dengan Herd PHP + seeder E2E). | `inherit` / `share` saat menyunting tes |

### Sinergi dengan Pembagian Area `AGENTS.md` (Opencode & Kiro AI)
Untuk mencegah konflik lintas agen saat sesi paralel lain aktif:
- **Wilayah Kiro AI (`AGENTS.md`):** CBT (`app/**/Cbt/`, `Cbt*.php`, migrasi `*cbt*`), alignment e-Rapor (`docs/09..12`, `alignment*`), serta dasbor/fitur peran guru–siswa–ortu. Worker Gemini (`worker-academic-engine` / `worker-ui-nav`) **tidak menyentuh** area CBT/alignment aktif Kiro kecuali atas instruksi eksplisit Koordinator.
- **Wilayah Opencode (`AGENTS.md`):** Transkrip, Cetak, struktur sidebar, security, serta `TranskripTest`/`CetakTest`. Perubahan `sidebar.blade.php` atau `routes/web.php` oleh worker Gemini harus masuk melalui review **Coordinator** sebagai *Shared-File Gatekeeper*.
- **File Bersama Wajib Baca-Dulu:** `routes/web.php`, `resources/views/*/partials/sidebar.blade.php`, `tests/Feature/MenuAuditTest.php`, dan `resources/views/layouts/app.blade.php` — wajib `phpunit` hijau sebelum commit.

---

## 3. Matriks Gelombang Pekerjaan (Work Waves) & Kepemilikan File

### Gelombang 1 — Stabilitas Test Suite & CI 100% Hijau (SQLite, PostgreSQL 16 & E2E)
*Tujuan:* Menutup seluruh kegagalan suite PHPUnit (SQLite + PostgreSQL 16) dan Playwright E2E di `main` tanpa melemahkan assertion.

| Paket | Status | Pemilik (`Roster`) | Target Berkas & Rincian Tugas | Kriteria Selesai |
| :--- | :--- | :--- | :--- | :--- |
| **1A** | **Selesai (CI Hijau)** | `worker-ui-nav` | Bungkus 4 tabel Web UI / cetak HTML dengan `<div class="table-wrapper">...</div>` pada `resources/views/admin/referensi/foto-siswa.blade.php`, `resources/views/admin/referensi/guru.blade.php`, `resources/views/admin/users/index.blade.php`, dan `resources/views/admin/users/siswa.blade.php`. | `AuditRemediation2026Test::test_p2_seluruh_tabel_web_ui_memakai_table_wrapper_dan_katex_self_hosted` **PASS**. |
| **1B** | **Selesai (CI Hijau)** | `worker-ui-nav` + `coordinator` | Hilangkan duplikasi 4 rute grup Wali Kelas (`guru.siswa.index`, `guru.kehadiran.index`, `guru.catatan.index`, `guru.erapor.index`) pada `resources/views/guru/partials/sidebar.blade.php`, hilangkan duplikasi lintas-tab `admin.users.index` pada `resources/views/admin/partials/sidebar.blade.php`, dan gerakkan visibilitas grup Wali Kelas memakai flag wali kelas (`guruAdalahWaliKelas()`). | `MenuInventoryTest::test_inventaris_menu_konsisten_dan_laporannya_dibuat` **PASS**. |
| **1C** | **Selesai (CI Hijau)** | `worker-qa-verifier` | Selaraskan `tests/Feature/AdminMenuTest.php`, `tests/Feature/LoginViewTest.php`, `tests/Feature/DapodikBridgeSyncTest.php`, `tests/Feature/DapodikResetTest.php`, `tests/e2e/global-setup.js`, `tests/e2e/cbt.spec.js`, serta perbaiki bug kolom PostgreSQL di `WaliKelasController::p5Index`, `Admin/RaporMerdekaController::p5Index` (`tema_kokurikuler.nama`), dan `SuperAdmin/TenantController` (`aktivitas.guru_id`). | Seluruh 4 job CI (`PHPUnit SQLite`, `PHPUnit PostgreSQL 16`, `Browser smoke`, `Authenticated E2E`) **PASS** pada run `#43981289068` (PR #27). |

### Gelombang 2 — Hardening Multi-Tenant & Rekonsiliasi Sekolah *(Status: 2A Selesai; 2B Sebagian; 2C Belum)*
*Tujuan:* Membuka secara aman fitur yang sebelumnya dibatasi pada instalasi ber-tenant ([`RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md`](RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md)).

| Paket | Status | Pemilik (`Roster`) | Cakupan Teknis | Kriteria Selesai |
| :--- | :--- | :--- | :--- | :--- |
| **2A** | **Selesai** | `worker-academic-engine` | `getKKM()` & cache KKM terscope per `tenant_id` (`forgetKKMCache`), `PengaturanGuru::scopeForSchool()`, penutupan IDOR lintas sekolah pada `Admin\BobotController`, `Admin\KodeAksesController`, & `Admin\PetaKelasController`, serta pembukaan `Kepsek\PetaKelasController` & `Kepsek\HasilBelajarController` (dengan data tabel/grafik nyata dan bebas N+1) di multi-tenant. | `TenantKkmAndPetaKelasIsolationTest` & `SchoolReferenceIsolationTest` (2 sekolah + sekolah tunggal) **PASS**. |
| **2B** | Sebagian (`b68536a` selesai untuk `DapodikConfig` & `Ptk::HasSchoolOwnership`) | `worker-backend-security` | Audit & perancangan koneksi **tarik langsung database mesin presensi** per `tenant_id` (saat ini masih diblokir aman via `data.single-school:presensi-mesin` kecuali opt-in single-tenant), beserta uji worker/antrean & API key jembatan presensi lintas 2 sekolah. | Koneksi mesin per sekolah terisolasi penuh & lolos uji 2 tenant. |
| **2C** | Belum | `worker-backend-security` | Rancangan & implementasi ekspor serta Backup/Restore terisolasi per sekolah (`tenant_id`), tanpa merusak fungsi sekolah tunggal (saat ini dibatasi aman via `data.single-school`). | `BackupCoverageTest`, `BackupRestoreDrillTest`, dan uji isolasi restore per tenant **PASS**. |

### Gelombang 3 — Kemudahan Operasional Harian Sekolah *(Status: Selesai — 3A, 3B, 3C Terimplementasi & Diperkuat)*
*Tujuan:* Mempercepat alur kerja harian admin, guru, kepala sekolah, siswa, dan orang tua tanpa menumpuk item sidebar.

| Paket | Status | Pemilik (`Roster`) | Cakupan Teknis | Kriteria Selesai |
| :--- | :--- | :--- | :--- | :--- |
| **3A** | **Selesai** | `worker-academic-engine` | Menu **Validasi & Kelengkapan Data** Admin (`admin.validasi-data.index`, `ValidasiDataSekolahService`): siswa tanpa rombel, rombel tanpa wali, jadwal tanpa guru/rombel, guru tanpa mapel, selisih akun siswa, **PIN/NIK pegawai ambigu/duplikat**, dan kelengkapan profil kop sekolah per `tenant_id`. | Laporan kelengkapan data menampilkan tautan perbaikan read-only per tenant (`Gelombang7MenuAndValidationTest` & `TenantKkmAndPetaKelasIsolationTest`). |
| **3B** | **Selesai** | `worker-academic-engine` + `worker-ui-nav` | **Pusat Notifikasi & Tugas** pada topbar tiap peran (`RoleTaskSummaryService`): sinkron Dapodik, temuan validasi data, tugas Kelas Digital menunggu dinilai/dikumpulkan, **pengajuan izin orang tua menunggu verifikasi** (hanya jumlah tanpa alasan sensitif), jurnal mengajar harian, dan perangkat ajar siap supervisi Kepsek. | Ringkasan tugas tampil per peran & tenant tanpa membocorkan alasan sensitif pada pratinjau. |
| **3C** | **Selesai** | `worker-ui-nav` | Pencarian menu lintas peran (`Ctrl+K` / `CommandPaletteService`) terfilter hak akses, status wali kelas, feature flag CBT/TKA, & mode multi-sekolah + bantuan singkat kontekstual per halaman. | Hanya menu berotorisasi yang muncul di hasil pencarian; bebas route 403/500 pada `MenuAuditTest`. |

### Gelombang 4 — Verifikasi End-to-End Browser & Kesiapan Go-Live *(Status: 4A Selesai; 4B Menunggu Eksekusi Staging)*
*Tujuan:* Membuktikan alur penuh dari login hingga aksi UI lintas peran di browser nyata.

| Paket | Status | Pemilik (`Roster`) | Cakupan Teknis | Kriteria Selesai |
| :--- | :--- | :--- | :--- | :--- |
| **4A** | **Selesai** | `worker-qa-verifier` | Konfigurasi `playwright.config.js` lintas platform (deteksi otomatis Herd PHP pada Windows + override `E2E_PHP_BIN`/`E2E_HOST`/`E2E_PORT` + default `php` pada Linux CI) serta fixture & `global-setup.js` (`E2E_*_PASSWORD`). | `npm run test:e2e` (`tka.spec.js`, `aplikasi.spec.js`, `cbt.spec.js`, `tryout.spec.js`) hijau di CI & siap dijalankan lokal Windows Herd. |
| **4B** | Belum (Staging / Operasional) | `worker-qa-verifier` + `worker-backend-security` | Audit manual & otomatis skenario 2 sekolah + skenario sekolah tunggal pada 7 peran + drill restore pada lingkungan staging PostgreSQL produksi. | Checklist [`CHECKLIST-STAGING-INTEGRASI.md`](CHECKLIST-STAGING-INTEGRASI.md) & [`RUNBOOK-GO-LIVE.md`](RUNBOOK-GO-LIVE.md) tercentang bukti eksekusi. |

### Gelombang 5 — E-Learning / Kelas Digital *(Evolusi Materi Ajar, Tanpa LMS Terpisah)*
*Tujuan:* Mengubah Materi Ajar menjadi ekosistem **Kelas Digital** terstruktur (`Kelas → Modul/Bab → Pertemuan → Isi teks/video/file/link → Tugas → Kuis terhubung → Progress siswa`) yang memakai ulang `Materi`, `Kuis`, dan `Nilai` eksisting, dengan isolasi tenant & anti-IDOR ketat. Desain lengkap tercatat di [`DESAIN-ELEARNING-DAN-WALI-KELAS.md`](DESAIN-ELEARNING-DAN-WALI-KELAS.md).

| Paket | Status | Pemilik (`Roster`) | Kepemilikan Berkas & Rincian Tugas | Kriteria Selesai |
| :--- | :--- | :--- | :--- | :--- |
| **5A** | **Selesai (CI Hijau)** | `worker-backend-security` | Fase 0 Fondasi + Backend E-Learning: (1) kolom `users.is_wali_kelas` + `User::isWaliKelas()` + `guruAdalahWaliKelas()` tanpa backfill agresif; (2) perbaikan filter `Siswa/MateriController` berbasis rombel + mapel + `JadwalPelajaran` (`SiswaMateriVisibilityService`); (3) migrasi `create_kelas_digital_tables`, 7 model `App\Models\Elearning\*` (`BelongsToTenant`, `SoftDeletes`, `uuid`), `ElearningFileService` (disk `local` privat + validasi MIME server-side), dan registrasi `BackupTables`. | Desain tertulis di `docs/`; test flag wali kelas, IDOR materi siswa, & isolasi dua tenant **PASS** (SQLite & PG 16). |
| **5B** | **Selesai (CI Hijau)** | `worker-academic-engine` | `KelasDigitalAccessService`, `Guru\KelasDigitalController`, `Siswa\KelasDigitalController`: CRUD Kelas/Modul (dengan field Pembelajaran Mendalam: `pm`, `dimensi`, `pengalaman`)/Pertemuan/Materi Item (`teks|video|file|link`), tautan ke `Materi` & `Kuis` eksisting, Tugas & Pengumpulan siswa, penilaian guru yang mengalir ke `Nilai` (`jenis = 'Tugas'`), dan pelacakan progress bebas N+1 tanpa cache model Eloquent. | Alur end-to-end guru→siswa→tugas→nilai masuk Daftar Nilai **PASS** di `KelasDigitalTest`. |
| **5C** | **Selesai (CI Hijau)** | `worker-ui-nav` + `coordinator` | View Blade `resources/views/guru/kelas-digital/*` dan `resources/views/siswa/kelas-digital/*` (mobile-first, `.table-wrapper`, Lucide icons, tanpa CDN) serta pendaftaran rute di `routes/web.php` dan integrasi menu `Kelas Digital` pada `guru/partials/sidebar.blade.php` & `siswa/partials/sidebar.blade.php`. | `MenuInventoryTest`, `MenuAuditTest`, dan `AuditRemediation2026Test` **PASS**. |
| **5D** | **Selesai (CI Hijau)** | `worker-qa-verifier` | Suite `WaliKelasFlagTest`, `SiswaMateriFilterTest`, `KelasDigitalTest`, pembaruan `MenuAuditTest`, verifikasi `tools/verifikasi-perbaikan.php` (63/63), build Vite, serta validasi CI penuh (SQLite, PostgreSQL 16, E2E). | 4/4 job CI **PASS** pada run `#43981289068` (PR #27); 0 endpoint `5xx` dari 2.512 kombinasi `RouteSweepTest`. |

### Gelombang 6 — Perangkat Administrasi Guru *(CP, TP, ATP, Prota, Promes, Modul Ajar/RPP, KKM/KKTP & Jurnal Mengajar)*
*Tujuan:* Menyediakan perangkat pembelajaran terstruktur bagi guru (CP, TP, ATP, Prota, Promes, Modul Ajar Pembelajaran Mendalam, KKM/KKTP, dan Jurnal Mengajar Harian) dengan template & cetak PDF via DomPDF, hak baca read-only wali kelas & supervisi Kepsek, serta pengaturan eksplisit flag wali kelas oleh admin. Desain lengkap di [`DESAIN-PERANGKAT-AJAR-DAN-MENU.md`](DESAIN-PERANGKAT-AJAR-DAN-MENU.md).

| Paket | Status | Pemilik (`Roster`) | Kepemilikan Berkas & Rincian Tugas | Kriteria Selesai |
| :--- | :--- | :--- | :--- | :--- |
| **6A** | **Selesai** | `worker-academic-engine` | Migrasi & model `perangkat_pembelajaran` (mendukung `cp`, `tp`, `atp`, `prota`, `promes`, `modul_ajar`, `kkm`) + `jurnal_mengajar` (`BelongsToTenant`, `uuid`, `SoftDeletes`), `PerangkatAccessService` (template siap pakai + kop sekolah), `Guru\PerangkatController`, ekspor PDF DomPDF, dan pengaturan `is_wali_kelas` di `Admin\UserController`. | Guru dapat mengisi, menyimpan, dan mencetak PDF perangkat & jurnal per tenant tanpa error. |
| **6B** | **Selesai** | `worker-backend-security` | Kebijakan scope `guru_id + tenant_id`, hak baca read-only wali kelas terhadap perangkat guru pengajar rombelnya, supervisi read-only `Kepsek\PerangkatSupervisiController`, lampiran privat `nosniff`, audit `AktivitasService`, dan registrasi `BackupTables`. | Uji dua tenant, supervisi Kepsek, & isolasi hak baca wali kelas **PASS**. |
| **6C** | **Selesai** | `worker-ui-nav` + `worker-qa-verifier` | UI Blade `resources/views/guru/perangkat/*` & `resources/views/kepsek/perangkat/index.blade.php` (`.table-wrapper`), integrasi menu sidebar, serta `tests/Feature/PerangkatPembelajaranTest.php`. | Suite penuh + audit menu & cetak PDF hijau. |

### Gelombang 7 — Penataan Menu Komplit & Terarah *(IA 7 Peran + Pencarian `Ctrl+K` Terotorisasi + Validasi Data & Pusat Tugas)*
*Tujuan:* Menyempurnakan IA menu 7 peran (1 menu = 1 tugas, active-state detail akurat, pencarian menu `Ctrl+K` lintas peran terfilter hak akses + feature flag + wali kelas + badge tugas + bantuan kontekstual, serta Pusat Validasi Kelengkapan Data Admin).

| Paket | Status | Pemilik (`Roster`) | Kepemilikan Berkas & Rincian Tugas | Kriteria Selesai |
| :--- | :--- | :--- | :--- | :--- |
| **7A** | **Selesai** | `worker-ui-nav` + `coordinator` | Penataan sidebar Guru (`Perangkat Ajar` + `Jurnal Mengajar`), Kepsek (`Supervisi Perangkat Ajar`), Admin (`Validasi Kelengkapan Data`) tanpa duplikasi, `CommandPaletteService` satu sumber kebenaran `Ctrl+K` terfilter peran/wali-kelas/feature-flag/multi-school/`Route::has`, dan panduan singkat kontekstual. | `MenuInventoryTest`, `MenuAuditTest`, dan `Gelombang7MenuAndValidationTest` **PASS**; 0 rute duplikat dan 0 rute tak berotorisasi di hasil pencarian. |
| **7B** | **Selesai** | `worker-academic-engine` | `ValidasiDataSekolahService` + `Admin\ValidasiDataController` (`admin.validasi-data.index`) dan `RoleTaskSummaryService` untuk `$layoutNotifs` lintas peran (hanya array skalar, tanpa cache model Eloquent, tanpa bocoran alasan sensitif) + badge notifikasi topbar. | Ringkasan tugas & validasi kelengkapan data tampil akurat per peran & tenant. |
| **7C** | **Selesai** | `worker-qa-verifier` | `tests/Feature/Gelombang7MenuAndValidationTest.php`, pembaruan `MenuAuditTest`, `docs/DESAIN-PERANGKAT-AJAR-DAN-MENU.md`, dan verifikasi penuh CI GitHub Actions (SQLite, PG 16, Chromium Smoke, Playwright E2E). | Seluruh gerbang kualitas & 4/4 job CI **PASS**. |

### Batas Kepemilikan File Lintas Agen (Gelombang 5–7)
- **Area Kiro AI (JANGAN disentuh tanpa instruksi eksplisit Coordinator):** `app/**/Cbt/*`, `app/Models/Cbt*`, `database/migrations/*cbt*`, `docs/09..12`, `alignment*`.
- **Area Opencode (JANGAN disentuh tanpa instruksi eksplisit Coordinator):** `Admin\TranskripController`, `Admin\CetakController`, `TranskripTest`, `CetakTest`.
- **File Bersama (melalui Coordinator):** `routes/web.php`, `resources/views/*/partials/sidebar.blade.php`, `resources/views/layouts/app.blade.php`, `app/helpers.php`, `app/Support/BackupTables.php`, `tests/Feature/MenuAuditTest.php`.

---

## 4. Aturan Koordinasi & Gerbang Kualitas Wajib (Quality Gates)

Setiap agen Gemini wajib mematuhi aturan berikut sebelum menyerahkan pekerjaan kembali ke Coordinator:
1. **Binary PHP Wajib:** Selalu gunakan `C:\Users\USER\.config\herd\bin\php84\php.exe` (jangan `php` polos pada Windows).
2. **Kompatibilitas Test SQLite:** PHPUnit berjalan dengan `DB_CONNECTION=sqlite` in-memory; hindari sintaks SQL khusus PostgreSQL (`ilike`, `to_char`) pada query yang tercakup test.
3. **Larangan Cache Model Eloquent:** Jangan pernah men-cache instance model Eloquent (hanya simpan skalar/array) untuk mencegah error unserialize `500`.
4. **Standar Tabel Blade:** Semua elemen `<table>` di UI web wajib dibungkus `<div class="table-wrapper">` (`resources/css/layout.css`).
5. **Gerbang Verifikasi Sebelum Merge:**
   - Lulus lint PHP (`php -l`) pada seluruh berkas tersentuh.
   - Lulus pemeriksaan statis: `C:\Users\USER\.config\herd\bin\php84\php.exe tools/verifikasi-perbaikan.php` (`63/63 lulus`).
   - Lulus PHPUnit pada test terkait dan suite penuh (`vendor/bin/phpunit --no-progress`) tanpa melaporkan klaim kelulusan palsu bila masih ada test gagal/terlewati.
