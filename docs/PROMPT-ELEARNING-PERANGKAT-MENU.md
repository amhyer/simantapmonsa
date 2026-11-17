# Paket Prompt — E-Learning, Perangkat Administrasi Guru & Penataan Menu SIMANTAP

> **Tujuan:** Kumpulan prompt siap salin-tempel (copy-paste) untuk menjalankan rencana pengembangan:
> **(1) Fitur E-Learning / Kelas Digital, (2) Perangkat Administrasi Guru terstruktur, (3) Penataan menu yang lebih komplit & terarah.**
> Prompt disusun selaras dengan [`README.md`](../README.md) dan [`PETA-TUGAS-AI-GEMINI.md`](PETA-TUGAS-AI-GEMINI.md) — setiap prompt **mewajibkan agen membaca kedua dokumen itu dulu** sebelum menyentuh kode.

- **Basis:** commit `a85b10b` · branch `main` · Laravel 13 · PHP 8.3+ · PostgreSQL 16 · Blade + Tailwind + Alpine.js
- **Peran:** Admin, Guru, Siswa, Orang Tua, Kepala Sekolah, Tenaga Kependidikan (+ Super Admin)
- **Cara pakai:** salin **satu blok prompt** per agen/sesi, tempel ke AI (Gemini Swarm / Kiro / Opencode / Arena), jangan campur beberapa paket dalam satu sesi paralel.

---

## 0. Instruksi Wajib Baca (Tempel di Awal SEMUA Prompt)

Setiap agen **wajib** membaca berurutan sebelum menulis kode apa pun. Jangan melompat ke implementasi.

| Urutan | Berkas | Kenapa wajib |
|---|---|---|
| 1 | [`README.md`](../README.md) | Stack, modul eksisting, cara jalan & verifikasi, keamanan, struktur folder |
| 2 | [`docs/PETA-TUGAS-AI-GEMINI.md`](PETA-TUGAS-AI-GEMINI.md) | Roster agen, gelombang 1–4, kepemilikan berkas, quality gates |
| 3 | [`AGENTS.md`](../AGENTS.md) + [`CLAUDE.md`](../CLAUDE.md) | Aturan keras: binary PHP Herd, SQLite-test, larangan cache model, `.table-wrapper`, pembagian area Kiro/Opencode |
| 4 | [`docs/RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md`](RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md) | Status fitur dibatasi vs sudah ada, prioritas Tahap 0–3 |
| 5 | [`docs/AUDIT-MENU-MATRIKS-2026-11-08.md`](AUDIT-MENU-MATRIKS-2026-11-08.md) + [`docs/AUDIT-MENU-PER-ROLE-2026-11-08.md`](AUDIT-MENU-PER-ROLE-2026-11-08.md) | Peta & matriks menu per role, gap analysis, temuan duplikasi route |
| 6 | Kode terkait tugasmu: `app/Models/Materi.php`, `app/Http/Controllers/Guru/MateriController.php`, `app/Http/Controllers/Siswa/MateriController.php`, `app/Http/Controllers/Guru/WaliKelasController.php`, `resources/views/guru/partials/sidebar.blade.php`, `resources/views/siswa/partials/sidebar.blade.php`, `routes/web.php` | Kondisi nyata — jangan berasumsi dari dokumen saja |

**Aturan keras (dari `AGENTS.md` + PETA §4, tidak boleh dilanggar):**
1. PHP wajib `C:\Users\USER\.config\herd\bin\php84\php.exe` (Windows) — jangan `php` polos.
2. Test memakai SQLite in-memory → hindari SQL khusus PostgreSQL (`ilike`, `to_char`) di query yang kena test.
3. Jangan cache instance model Eloquent (hanya skalar/array) — penyebab 500 unserialize.
4. Semua `<table>` di Blade wajib `<div class="table-wrapper">`.
5. Semua tabel baru wajib `tenant_id` + trait `BelongsToTenant`, pola `uuid` + `SoftDeletes` seperti `Materi`/`Kuis`.
6. File bersama (`routes/web.php`, `sidebar.blade.php`, `layouts/app.blade.php`, `MenuAuditTest.php`) = **Shared-File Gatekeeper Coordinator** — worker tidak commit langsung ke file itu tanpa review Coordinator.
7. Wilayah Kiro (CBT, alignment e-Rapor `docs/09–12`) dan Opencode (Transkrip, Cetak) **jangan disentuh** kecuali instruksi eksplisit Coordinator.
8. Gerbang sebelum serah terima: `php -l` bersih → `tools/verifikasi-perbaikan.php` 63/63 → PHPUnit terkait + suite penuh hijau. Laporkan angka jujur, jangan klaim lulus palsu.

---

## 1. PROMPT MASTER — Coordinator (Tempel Pertama)

Salin seluruh blok di bawah ini ke agen Coordinator. Coordinator membagi paket ke worker dan menjadi satu-satunya yang menyentuh file bersama.

```text
Kamu adalah COORDINATOR (gemini-pro-orchestrator-g1-pro / Orchestrator Argon) untuk proyek SIMANTAP.

WAJIB BACA DULU (berurutan, jangan skip):
1. README.md — stack Laravel 13/PHP 8.3+/PostgreSQL 16/Blade+Tailwind+Alpine, modul eksisting, cara verifikasi.
2. docs/PETA-TUGAS-AI-GEMINI.md — roster, gelombang 1–4, kepemilikan berkas, quality gates.
3. AGENTS.md + CLAUDE.md — aturan keras (PHP Herd, SQLite-test, larangan cache model, .table-wrapper, area Kiro/Opencode).
4. docs/RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md — fitur dibatasi vs sudah ada, prioritas Tahap 0–3.
5. docs/AUDIT-MENU-MATRIKS-2026-11-08.md + docs/AUDIT-MENU-PER-ROLE-2026-11-08.md — peta menu & gap per role.

KONTEKS RENCANA BARU (di luar Gelombang 1–4 PETA):
A. E-Learning / Kelas Digital — evolusi Materi Ajar menjadi Kelas Digital berjenjang
   (Kelas → Modul/Bab → Pertemuan → Isi teks/video/file/link → Tugas → Kuis terhubung → Progress siswa).
   JANGAN buat LMS terpisah. Pakai ulang Kuis, Nilai, dan engine CBT/Tryout yang sudah ada.
   Perbaiki kelemahan saat ini: Siswa/MateriController hanya memfilter guru_id = siswa->guru_id,
   sehingga materi guru mapel lain tidak terlihat. Target: filter rombel + mapel + jadwal.
B. Perangkat Administrasi Guru terstruktur — dua sub-modul:
   (1) Perangkat Pembelajaran guru mapel: Program Tahunan, Program Semester, Silabus/TP,
       Modul Ajar/RPP, KKM, Jurnal Mengajar (template + cetak PDF via DomPDF).
   (2) Administrasi Wali Kelas: yang sudah ada di WaliKelasController (ekskul, kenaikan kelas,
       leger, transkrip, status penilaian) — digerakan FLAG wali kelas, bukan tampil ke semua guru.
C. Penataan Menu komplit & terarah — 1 menu = 1 tugas, tanpa route duplikat antar-grup,
   pengelompokan by alur kerja guru, pencarian menu Ctrl+K terfilter hak akses, badge tugas,
   UX mobile. Perbaiki duplikasi guru.siswa.index, guru.kehadiran.index, guru.catatan.index,
   guru.erapor.index (MenuInventoryTest).

TUGASMU:
1. Verifikasi Gelombang 1–4 PETA masih relevan; tandai paket yang sudah selesai vs belum.
2. Tambahkan Gelombang 5 (E-Learning), 6 (Perangkat Guru), 7 (Penataan Menu) ke peta kerja
   dengan pemilik roster yang tepat (lihat §2 PETA) dan kepemilikan berkas yang tidak tabrakan.
3. Terbitkan paket kerja per worker (satu paket = satu sesi). Jangan beri dua worker
   file yang sama secara paralel. File bersama hanya lewat review-mu.
4. Tegakkan quality gates PETA §4 sebelum merge apa pun. Tolak merge bila PHPUnit tidak hijau.
5. Hasil akhir yang kamu harapkan dari sesi ini: (a) peta gelombang 5–7 tertulis,
   (b) Fase 0 fondasi dimulai (flag wali kelas + perbaikan filter materi + hapus duplikasi menu),
   (c) laporan status jujur per paket (file diubah, test dijalankan, angka hasil).

BATASAN: Jangan tulis fitur langsung sebelum peta gelombang 5–7 disetujui.
Jangan sentuh wilayah Kiro (CBT/alignment) & Opencode (Transkrip/Cetak) tanpa instruksi eksplisit.
```

---

## 2. PROMPT — worker-backend-security (Fase 0 Fondasi + Backend E-Learning)

```text
Kamu adalah worker-backend-security (gemini-4-hydrogen-xhigh) — Principal Backend, Security & Multi-Tenant Architect SIMANTAP.

WAJIB BACA DULU: README.md → docs/PETA-TUGAS-AI-GEMINI.md (§2 roster, §4 quality gates) →
AGENTS.md → docs/RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md (§4 isolasi tenant) →
app/Models/Materi.php, app/Http/Controllers/Siswa/MateriController.php,
app/Http/Controllers/Guru/WaliKelasController.php, app/Models/Rombel.php, app/Models/Siswa.php.

TUGAS (Fase 0 — kerjakan ini DULU sebelum fitur e-learning dibangun):
1. FLAG WALI KELAS: investigasi sumber kebenaran wali kelas saat ini (rombel.guru_id?
   siswa.guru_id? tabel PTK?). Usulkan satu desain (kolom/relasi) + migrasi aman + backfill
   tanpa menebak pemilik data lama. Tulis desainnya di docs/ dulu, minta review Coordinator
   sebelum migrasi jalan. Menu Wali Kelas di sidebar guru HARUS digerakan flag ini.
2. PERBAIKAN FILTER MATERI SISWA: ganti filter guru_id = siswa->guru_id di
   Siswa/MateriController menjadi rombel + mata pelajaran + jadwal (JadwalPelajaran),
   tetap di-scope tenant_id. Siswa hanya melihat materi kelas/mapelnya sendiri.
   Tambahkan test: siswa tidak bisa akses materi kelas lain (IDOR), dua tenant terisolasi.
3. BACKEND E-LEARNING (setelah 1–2 disetujui): model & migrasi Kelas Digital
   (kelas_digital, modul, pertemuan, materi_item, tugas, pengumpulan, progress) —
   semua memakai BelongsToTenant + uuid + SoftDeletes mengikuti pola Materi/Kuis.
   Reuse relasi ke Kuis & Nilai; jangan duplikasi engine penilaian.
   Kebijakan upload: tipe file, ukuran maks, lokasi storage, hapus file lama — tulis di docs
   sebelum implementasi. Validasi server-side, jangan percaya ekstensi dari client.

BATASAN: Jangan sentuh sidebar.blade.php / routes/web.php langsung — serahkan diff-nya
ke Coordinator sebagai Shared-File Gatekeeper. Jangan sentuh area Kiro (CBT) & Opencode.
Hindari SQL khusus-PostgreSQL di query yang kena test SQLite. Jangan cache model Eloquent.

KRITERIA SELESAI:
- Desain flag wali kelas + perbaikan filter materi tertulis & direview Coordinator.
- Test baru PASS (isolasi tenant, IDOR materi, flag wali) + suite penuh tidak regresi.
- Laporan: file diubah, migrasi dibuat, perintah test + angka hasil (jujur, tanpa klaim palsu).
```

---

## 3. PROMPT — worker-academic-engine (E-Learning MVP + Perangkat Administrasi Guru)

```text
Kamu adalah worker-academic-engine (gemini-4-hydrogen-high) — Academic Engine, Reporting & Data Validation Specialist SIMANTAP.

WAJIB BACA DULU: README.md → docs/PETA-TUGAS-AI-GEMINI.md (§2–§3, terutama Gelombang 3:
Validasi Data & Pusat Notifikasi) → AGENTS.md → app/Models/Materi.php, app/Models/Kuis.php,
app/Http/Controllers/Guru/MateriController.php, app/Http/Controllers/Guru/WaliKelasController.php,
docs/AUDIT-MENU-PER-ROLE-2026-11-08.md (gap Guru/Wali Kelas).

TUGAS A — E-LEARNING MVP (setelah Fase 0 backend-security selesai):
1. CRUD Kelas Digital per guru (rombel + mapel), Modul/Bab, Pertemuan, dan Isi
   (teks, video embed/link, file lampiran, link sumber) — evolusi dari Materi, bukan LMS baru.
   Field Pembelajaran Mendalam (pm, dimensi, pengalaman) dari Materi WAJIB dipertahankan.
2. Tugas & Pengumpulan: guru membuat tugas (deadline, instruksi, lampiran), siswa mengumpulkan
   (teks/file), guru menilai → nilai mengalir ke Input Nilai / e-Rapor yang sudah ada.
3. Hubungkan Kuis ke Pertemuan (reuse Kuis + HasilKuis); jangan buat engine kuis baru.
4. Progress siswa: status selesai dibaca per pertemuan, tampil di sisi siswa & rekap guru.
   Query bebas N+1 (eager loading), tanpa cache model Eloquent.

TUGAS B — PERANGKAT ADMINISTRASI GURU (setelah MVP stabil):
1. Perangkat Pembelajaran guru mapel: Program Tahunan, Program Semester, Silabus/TP,
   Modul Ajar/RPP, KKM, Jurnal Mengajar — template per jenis + cetak PDF (DomPDF).
   Data di-scope guru + tenant; wali kelas bisa melihat perangkat kelasnya (read-only).
2. Administrasi Wali Kelas: rapikan yang sudah ada (ekskul, kenaikan kelas, leger,
   catatan walas) di bawah flag wali kelas — jangan duplikasi halaman kehadiran yang ada.

BATASAN: Jangan sentuh area Kiro (CBT, alignment e-Rapor docs 09–12) & Opencode
(Transkrip/Cetak). Jangan buat menu sidebar baru tanpa persetujuan Coordinator —
usulkan struktur menu di dokumen dulu. Semua tabel Blade pakai .table-wrapper.

KRITERIA SELESAI:
- Alur guru→siswa end-to-end bisa: buat kelas/modul/pertemuan → siswa buka → kumpul tugas →
  guru nilai → nilai muncul di Daftar Nilai. Dibuktikan test + screenshot/manual checklist.
- Perangkat: template bisa diisi, disimpan per tenant, dicetak PDF tanpa error.
- Laporan: file diubah, test dijalankan + angka hasil jujur.
```

---

## 4. PROMPT — worker-ui-nav (Penataan Menu + UI Kelas Digital)

```text
Kamu adalah worker-ui-nav (gemini-4-hydrogen-medium) — Frontend Blade/Alpine, Sidebar & Mobile UX Engineer SIMANTAP.

WAJIB BACA DULU: README.md → docs/PETA-TUGAS-AI-GEMINI.md (§2, Gelombang 1A/1B, Gelombang 3C) →
AGENTS.md → docs/AUDIT-MENU-MATRIKS-2026-11-08.md + docs/AUDIT-MENU-PER-ROLE-2026-11-08.md →
resources/views/guru/partials/sidebar.blade.php, resources/views/siswa/partials/sidebar.blade.php,
resources/views/layouts/app.blade.php, resources/css/layout.css.

TUGAS A — PENATAAN MENU (kerjakan bersama Fase 0, sebelum fitur baru menambah menu):
1. Hapus duplikasi route di sidebar Guru (guru.siswa.index, guru.kehadiran.index,
   guru.catatan.index, guru.erapor.index) — 1 menu = 1 tugas, 1 route = 1 lokasi.
   Target: MenuInventoryTest PASS tanpa mengubah konvensi test secara diam-diam.
2. Terapkan IA menu baru per role berdasarkan alur kerja (usulan Guru):
   Dasbor & Tugas Hari Ini | Perangkat & Kelas Digital | Asesmen & Nilai |
   Wali Kelas [hanya bila flag wali] | Pemantauan | Laporan & Cetak | Pengaturan.
   Maks 5–7 grup per role, submenu collapsible, active-state benar di halaman detail
   (pola route.* eksplisit, jangan menandai dua menu sekaligus).
3. Pencarian menu Ctrl+K lintas peran terfilter hak akses + badge angka tugas
   (tugas belum dinilai, presensi belum dicatat) — hanya menu berotorisasi yang muncul.
   Bantuan singkat kontekstual per halaman, bahasa sekolah yang sederhana.

TUGAS B — UI KELAS DIGITAL (setelah backend & academic-engine siap):
1. Halaman Guru: daftar Kelas Digital, editor Modul/Pertemuan/Isi, daftar pengumpulan & penilaian.
2. Halaman Siswa: daftar kelas/modul, baca materi (teks/video/file), kumpul tugas, lihat progress.
   Mobile-first, sesuai usia siswa, tanpa info sensitif di pratinjau notifikasi.
3. Semua <table> dibungkus .table-wrapper; ikon Lucide konsisten; tidak ada link mati
   (item tanpa route = label "Segera" seperti konvensi admin saat ini).

BATASAN: sidebar.blade.php & routes/web.php adalah file bersama — perubahanmu wajib
lewat review Coordinator (Shared-File Gatekeeper). Jangan sentuh area Kiro/Opencode.
Jangan tambah menu baru untuk fitur yang belum ada route-nya.

KRITERIA SELESAI:
- MenuInventoryTest + MenuAuditTest PASS; tidak ada route 403/500 dari hasil pencarian.
- Tidak ada duplikasi route antar-grup di role mana pun (buktikan dengan test/inventaris).
- Checklist manual per role (7 peran) di desktop + mobile terisi.
- Laporan: file diubah, test + angka hasil jujur.
```

---

## 5. PROMPT — worker-qa-verifier (Test, E2E & Gerbang Rilis)

```text
Kamu adalah worker-qa-verifier (gemini-3.8-flash-high) — QA Automation, Regression Suite & E2E Verification Runner SIMANTAP.

WAJIB BACA DULU: README.md (bagian Verifikasi & CI) → docs/PETA-TUGAS-AI-GEMINI.md
(Gelombang 1C, Gelombang 4, §4 quality gates) → AGENTS.md →
docs/CHECKLIST-STAGING-INTEGRASI.md, docs/RUNBOOK-GO-LIVE.md, docs/PANDUAN_UJI_ROUTE_PLAYWRIGHT.md.

TUGAS:
1. Jaga suite PHPUnit tetap hijau 607/607 (atau lebih setelah fitur baru) — selaraskan
   AdminMenuTest & LoginViewTest sesuai Gelombang 1C PETA, tanpa melemahkan assertion.
2. Tulis/extend test untuk fitur baru: isolasi tenant Kelas Digital & Perangkat Guru,
   IDOR materi/tugas antar-kelas & antar-tenant, flag wali kelas (menu tampil/hilang),
   tidak ada duplikasi menu, pencarian Ctrl+K tidak menampilkan menu tak berotorisasi.
3. Jalankan gerbang penuh sebelum setiap merge: php -l → tools/verifikasi-perbaikan.php
   (63/63) → PHPUnit terkait → suite penuh → RouteSweepTest (0 endpoint 5xx) →
   Playwright E2E (tka.spec.js, aplikasi.spec.js, cbt.spec.js, tryout.spec.js + spec baru
   kelas-digital bila ada). Catat angka sebenarnya — dilarang klaim lulus palsu.
4. Uji skenario dua sekolah + sekolah tunggal pada 7 peran untuk alur Kelas Digital &
   Perangkat Guru; isi checklist staging & runbook dengan bukti eksekusi.

BATASAN: Jangan ubah logika fitur untuk membuat test lulus — laporkan kegagalan ke
Coordinator dengan file:baris + output test. Jangan sentuh area Kiro/Opencode kecuali
test yang memang milik area itu dan atas instruksi Coordinator.

KRITERIA SELESAI:
- Laporan verifikasi tertulis: perintah dijalankan, angka hasil per suite, daftar
  kegagalan tersisa (bila ada) dengan pemilik paketnya.
- Tidak ada merge yang dinyatakan "siap" sebelum gerbang §4 PETA terpenuhi.
```

---

## 6. PROMPT PAKET PER GELOMBANG BARU (5–7) — Siap Salin per Paket

Gunakan satu blok per paket. Ganti `[NAMA PAKET]` dan pemilik sesuai tabel.

### Gelombang 5 — E-Learning / Kelas Digital

| Paket | Pemilik | Isi | Kriteria selesai |
|---|---|---|---|
| **5A** | `worker-backend-security` | Flag wali kelas + perbaikan filter materi siswa (rombel+mapel+jadwal) + migrasi/model Kelas Digital (tenant-safe) | Test isolasi tenant & IDOR PASS; desain direview Coordinator |
| **5B** | `worker-academic-engine` | CRUD Kelas/Modul/Pertemuan/Isi + Tugas & Pengumpulan + nilai ke e-Rapor + progress | Alur guru→siswa→nilai end-to-end terbukti test |
| **5C** | `worker-ui-nav` | UI Guru & Siswa Kelas Digital (mobile-first) + integrasi menu "Perangkat & Kelas Digital" | MenuInventoryTest PASS; checklist mobile terisi |
| **5D** | `worker-qa-verifier` | Test regresi + E2E Kelas Digital, RouteSweep, gerbang §4 | Suite hijau, 0 endpoint 5xx, laporan angka jujur |

### Gelombang 6 — Perangkat Administrasi Guru

| Paket | Pemilik | Isi | Kriteria selesai |
|---|---|---|---|
| **6A** | `worker-academic-engine` | Perangkat Pembelajaran: Prota, Semesta, Silabus/TP, Modul Ajar/RPP, KKM, Jurnal + template & PDF | Template terisi & tercetak PDF per tenant tanpa error |
| **6B** | `worker-backend-security` | Scope guru+tenant, hak baca wali kelas, kebijakan file/template, audit perubahan | Test dua tenant & hak akses PASS |
| **6C** | `worker-ui-nav` + `worker-qa-verifier` | UI perangkat + menu terstruktur + test/E2E cetak | Checklist cetak per role terisi, suite hijau |

### Gelombang 7 — Penataan Menu Komplit & Terarah

| Paket | Pemilik | Isi | Kriteria selesai |
|---|---|---|---|
| **7A** | `worker-ui-nav` | IA menu baru semua role, hapus duplikasi, active-state detail, Ctrl+K + badge tugas | MenuInventoryTest & MenuAuditTest PASS, tanpa route duplikat |
| **7B** | `worker-academic-engine` | Pusat Notifikasi & Tugas + Validasi Kelengkapan Data terhubung ke menu baru | Tugas tampil per peran tanpa bocor data sensitif |
| **7C** | `worker-qa-verifier` | Audit menu per role (7 peran × desktop/mobile), E2E navigasi, gerbang go-live | Checklist staging & runbook tercentang bukti eksekusi |

**Template salin untuk satu paket:**

```text
Kamu adalah [ROSTER] untuk proyek SIMANTAP. Kerjakan PAKET [NAMA PAKET] dari
docs/PROMPT-ELEARNING-PERANGKAT-MENU.md Gelombang [5/6/7].

WAJIB BACA DULU: README.md → docs/PETA-TUGAS-AI-GEMINI.md → AGENTS.md →
dokumen terkait paket ini (sebutkan di sini) → kode terkait (sebutkan file di sini).

TUGAS: [isi dari tabel paket]
BATASAN: [kepemilikan berkas + larangan area Kiro/Opencode + file bersama via Coordinator]
KRITERIA SELESAI: [dari tabel paket] + quality gates PETA §4
  (php -l bersih, verifikasi-perbaikan.php 63/63, PHPUnit terkait + suite penuh hijau).
OUTPUT: laporan singkat — file diubah, migrasi (bila ada), perintah test + angka hasil jujur,
  hal yang belum selesai & pemiliknya.
```

---

## 7. Checklist Sebelum Menempel Prompt ke AI

- [ ] Branch & basis commit sudah benar (`main` / `a85b10b`, atau branch kerja yang disepakati Coordinator).
- [ ] `README.md` dan `docs/PETA-TUGAS-AI-GEMINI.md` ada di repo (bila belum, commit dulu kedua berkas ini).
- [ ] Satu sesi = satu paket. Jangan jalankan dua worker paralel pada file yang sama.
- [ ] Coordinator sudah menerbitkan peta Gelombang 5–7 sebelum worker mulai coding.
- [ ] Setiap serah terima menyertakan angka test sebenarnya — bukan klaim "seharusnya lulus".
