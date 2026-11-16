# Peta Tugas & Orkestrasi Multi-Agen AI Gemini — SIMANTAP

**Dokumen koordinasi kerja** untuk memetakan kondisi proyek, pembagian tugas antar-model AI Gemini (Swarm & Multi-Agen), gelombang eksekusi, serta batas kepemilikan berkas agar kolaborasi paralel berjalan aman tanpa konflik.

- **Basis Commit:** `a85b10b` (`feat(tka): add evidence-based TKA readiness monitoring module`, 16 November 2026)
- **Branch Aktif:** `main`
- **Acuan Arsitektur & Koordinasi:** `AGENTS.md`, `CLAUDE.md`, dan [`RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md`](RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md)

---

## 1. Ringkasan Status Proyek Aktual (Baseline `a85b10b`)

### Hasil Verifikasi Terakhir
- **Pemeriksaan Statis (`tools/verifikasi-perbaikan.php`):** `63/63 lulus (0 gagal)`.
- **Smoke Test Rute (`RouteSweepTest`):** `2.392` kombinasi route GET × akun fixture = `0` endpoint `5xx`.
- **Modul Pemantauan Kesiapan TKA (`TkaKesiapanServiceTest`, `TkaKesiapanHttpTest`):** `25/25 lulus` (berjalan di SQLite in-memory; migrasi telah diterapkan ke database lokal batch `[26]`).
- **Test Suite PHPUnit Penuh:** `603/607 lulus (3.315 assertions)`, menyisakan **4 kegagalan terpetakan**:
  1. `Tests\Feature\AdminMenuTest::test_sidebar_tanpa_label_ganda_dan_submenu_tertutup_default` (`tests/Feature/AdminMenuTest.php:95`) — sejak commit `e066cbb`, `admin.dashboard` berada di bawah tab `pengaturan`, sedangkan test masih memeriksa label grup tab `erapor` (`Pengaturan Cetak & Rapor`) pada respons `/admin/dashboard`.
  2. `Tests\Feature\AuditRemediation2026Test::test_p2_seluruh_tabel_web_ui_memakai_table_wrapper_dan_katex_self_hosted` (`tests/Feature/AuditRemediation2026Test.php:309`) — 4 tabel belum dibungkus `.table-wrapper`:
     - `resources/views/admin/referensi/foto-siswa.blade.php:246`
     - `resources/views/admin/referensi/guru.blade.php:454`
     - `resources/views/admin/users/index.blade.php:769`
     - `resources/views/admin/users/siswa.blade.php:477`
  3. `Tests\Feature\LoginViewTest::test_halaman_login_menampilkan_tombol_kembali_dan_toggle_mata_password` (`tests/Feature/LoginViewTest.php:18`) — test mengharapkan `route('home')` (`/dashboard`), sedangkan tombol kembali di `resources/views/auth/login.blade.php` mengarah ke beranda publik (`/`).
  4. `Tests\Feature\MenuInventoryTest::test_inventaris_menu_konsisten_dan_laporannya_dibuat` (`tests/Feature/MenuInventoryTest.php:107`) — grup "Wali Kelas" pada `resources/views/guru/partials/sidebar.blade.php` menduplikasi 4 rute (`guru.siswa.index`, `guru.kehadiran.index`, `guru.catatan.index`, `guru.erapor.index`) dalam satu peran.

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

### Gelombang 1 — Stabilitas Test Suite 100% Hijau (`607/607`)
*Tujuan:* Menutup seluruh 4 kegagalan tersisa dari suite PHPUnit saat ini tanpa regresi.

| Paket | Pemilik (`Roster`) | Target Berkas & Rincian Tugas | Kriteria Selesai |
| :--- | :--- | :--- | :--- |
| **1A** | `worker-ui-nav` | Bungkus 4 tabel Web UI dengan `<div class="table-wrapper">...</div>` pada `resources/views/admin/referensi/foto-siswa.blade.php:246`, `resources/views/admin/referensi/guru.blade.php:454`, `resources/views/admin/users/index.blade.php:769`, dan `resources/views/admin/users/siswa.blade.php:477`. | `AuditRemediation2026Test::test_p2_seluruh_tabel_web_ui_memakai_table_wrapper_dan_katex_self_hosted` **PASS**. |
| **1B** | `worker-ui-nav` + `coordinator` | Hilangkan duplikasi 4 rute grup Wali Kelas (`guru.siswa.index`, `guru.kehadiran.index`, `guru.catatan.index`, `guru.erapor.index`) pada `resources/views/guru/partials/sidebar.blade.php` (atau selaraskan konvensi pengecekan di `tests/Feature/MenuInventoryTest.php` bila rute berparameter berbeda dimaksudkan). | `MenuInventoryTest::test_inventaris_menu_konsisten_dan_laporannya_dibuat` **PASS**. |
| **1C** | `worker-qa-verifier` | Selaraskan `tests/Feature/AdminMenuTest.php:95` (uji sidebar tab `erapor` selain `/admin/dashboard` atau tab `pengaturan` sesuai struktur commit `e066cbb`) serta `tests/Feature/LoginViewTest.php:18` (konsistensi URL beranda `/` vs `route('home')`). | `AdminMenuTest` & `LoginViewTest` **PASS**, total suite **607/607 hijau**. |

### Gelombang 2 — Hardening Multi-Tenant & Rekonsiliasi Sekolah
*Tujuan:* Membuka secara aman fitur yang sebelumnya dibatasi pada instalasi ber-tenant ([`RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md`](RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md)).

| Paket | Pemilik (`Roster`) | Cakupan Teknis | Kriteria Selesai |
| :--- | :--- | :--- | :--- |
| **2A** | `worker-academic-engine` | Pengaturan KKM dan sumber Peta Kelas / Hasil Belajar Kepala Sekolah tersimpan dan di-scope per `tenant_id`. | Kepsek dua sekolah melihat KKM dan peta kelas masing-masing tanpa bocor lintas tenant. |
| **2B** | `worker-backend-security` | Isolasi konfigurasi Dapodik, PTK, serta koneksi mesin presensi per `tenant_id` (beserta audit IDOR, API key, dan antrean job). | Uji dua tenant di `DapodikTenantIsolationTest` & `SchoolReferenceIsolationTest` lolos penuh. |
| **2C** | `worker-backend-security` | Rancangan & implementasi ekspor serta Backup/Restore terisolasi per sekolah (`tenant_id`), tanpa merusak fungsi sekolah tunggal. | `BackupCoverageTest`, `BackupRestoreDrillTest`, dan uji isolasi restore per tenant **PASS**. |

### Gelombang 3 — Kemudahan Operasional Harian Sekolah
*Tujuan:* Mempercepat alur kerja harian admin, guru, kepala sekolah, siswa, dan orang tua tanpa menumpuk item sidebar.

| Paket | Pemilik (`Roster`) | Cakupan Teknis | Kriteria Selesai |
| :--- | :--- | :--- | :--- |
| **3A** | `worker-academic-engine` | Menu **Validasi & Kelengkapan Data** Admin (siswa tanpa rombel, rombel tanpa wali, jadwal tanpa guru/kelas, akun tanpa sekolah, PIN/NIK ambigu). | Laporan kelengkapan data menampilkan tautan perbaikan read-only per tenant. |
| **3B** | `worker-academic-engine` + `worker-ui-nav` | **Pusat Notifikasi & Tugas** pada dasbor tiap peran (izin orang tua menunggu verifikasi, presensi belum dicatat, nilai belum lengkap), dengan privasi ketat. | Ringkasan tugas tampil per peran tanpa membocorkan alasan sensitif pada pratinjau. |
| **3C** | `worker-ui-nav` | Pencarian menu lintas peran (`Ctrl+K` / modal cepat) terfilter hak akses + bantuan singkat kontekstual per halaman. | Hanya menu berotorisasi yang muncul di hasil pencarian; bebas route 403/500 pada `MenuAuditTest`. |

### Gelombang 4 — Verifikasi End-to-End Browser & Kesiapan Go-Live
*Tujuan:* Membuktikan alur penuh dari login hingga aksi UI lintas peran di browser nyata.

| Paket | Pemilik (`Roster`) | Cakupan Teknis | Kriteria Selesai |
| :--- | :--- | :--- | :--- |
| **4A** | `worker-qa-verifier` | Konfigurasi `playwright.config.js` / skrip E2E agar memakai Herd PHP (`C:\Users\USER\.config\herd\bin\php84\php.exe`) serta dokumentasi/env fixture untuk `tests/e2e/global-setup.js`. | `npm run test:e2e` (`tka.spec.js`, `aplikasi.spec.js`, `cbt.spec.js`, `tryout.spec.js`) dapat dijalankan lokal. |
| **4B** | `worker-qa-verifier` + `worker-backend-security` | Audit manual & otomatis skenario 2 sekolah + skenario sekolah tunggal pada 7 peran + drill restore pada lingkungan staging PostgreSQL. | Checklist [`CHECKLIST-STAGING-INTEGRASI.md`](CHECKLIST-STAGING-INTEGRASI.md) & [`RUNBOOK-GO-LIVE.md`](RUNBOOK-GO-LIVE.md) tercentang bukti eksekusi. |

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
