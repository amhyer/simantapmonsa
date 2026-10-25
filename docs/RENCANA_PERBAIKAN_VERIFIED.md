# RENCANA PERBAIKAN VERIFIED — SIMANTAP

### Acuan pembenahan gabungan, diverifikasi langsung terhadap kode

**Basis verifikasi:** working tree pada commit `56f7922` (15 Okt 2026) + perubahan belum di-commit.
**Tanggal verifikasi awal:** 17 Oktober 2026.
**Metode awal:** pembacaan kode + pemeriksaan file/folder satu per satu (bukan klaim dari laporan lain).
**Status mutakhir:** §1b dan matriks §2 adalah snapshot awal; §§1c–1e mencatat fase perbaikan setelahnya. §1f berisi eksekusi terbaru dan mengalahkan status historis yang belum direkonsiliasi.
**Sumber digabung:**
- `docs/Audit_Mendalam_SIMANTAPMONSA.md` (audit statis, snapshot `56f7922`)
- `docs/AUDIT_SIMANTAP_MENDALAM_2026-10-17.md` (audit + eksekusi, klaim 19 temuan)

Dokumen ini adalah **satu-satunya acuan kerja**. Laporan lain yang mengklaim "100% FIXED" atau
"Production-Ready" dinyatakan **kedaluwarsa** dan tidak boleh dipakai sebagai dasar keputusan.

---

## 1. RINGKASAN EKSEKUTIF

| Metrik | Angka (terverifikasi 17 Okt) |
|---|---|
| Temuan CBT/Tryout kritis yang **masih rusak** | **18 dari 19** (semua kecuali BUG-12 yang sebagian) |
| Endpoint berpotensi HTTP 500 | **±20** (13 view hilang + jalur 500 lain) → terverifikasi GET: **2** (§1b); sisanya butuh data uji |
| Kerentanan IDOR yang **masih terbuka** | **11 endpoint** (0 Policy di aplikasi) |
| View yang hilang | **13** (dicek satu per satu: semua `False`) |
| `app/Policies/` | **tidak ada** (0 file) |
| CI (`.github/workflows`) | **ADA** (`.github/workflows/test.yml`, 17 Okt) |
| Artefak regression test dari audit 17 Okt | **hilang dari tree** (`AuditCbtProofTest`, `RouteHealthAuditTest`, `authz_sweep.py` tidak ada) |
| Test suite CBT/Tryout/TKA/Backup | **0 file test** |

### Tiga kesimpulan

1. **Modul CBT, Tryout, dan TKA belum pernah bisa dijalankan end-to-end.** Alur siswa mati di
   3 titik 500 berurutan (start → autosave → anti-cheat), plus penilaian salah, plus IDOR lintas akun.
2. **Verifikasi sebelumnya tidak pernah mengeksekusi kode.** Klaim "127/127 hijau", "95% confidence",
   "CLEARED FOR DEPLOYMENT" tidak terbukti; test suite sendiri punya 1 ERROR dan bergantung
   `npm run build` yang tidak pernah dijalankan di clone segar.
3. **CBT dan Tryout adalah dua implementasi duplikat** dengan nama tabel/kolom berbeda dan dua set
   bug berbeda. Memperbaiki terpisah = dua kali kerja. Disatukan setelah P0 tertutup.

**Aturan mutlak:** setiap klaim "sudah diperbaiki" WAJIB dibuktikan dengan test/eksekusi yang
menyala hijau — bukan pembacaan kode, bukan laporan Markdown.

---

## 1b. STATUS EKSEKUSI FASE 0 (17 Okt 2026)

| # | Status | Bukti eksekusi |
|---|---|---|
| 0.1 | ✅ SELESAI | `tests/TestCase.php` memanggil `withoutVite()`; suite jalan tanpa `npm run build` |
| 0.2 | ✅ SELESAI (baseline merah) | `tests/Feature/Cbt/AuditCbtProofTest.php` — **16 dari 18 GAGAL**, 2 lulus (perlu ditelaah, lihat di bawah) |
| 0.3 | ✅ SELESAI | `tests/Feature/RouteHealthAuditTest.php` — 1035 kombinasi (207 GET × 5 peran) → **2 endpoint 5xx** (`storage/logs/route-health-report.txt`) |
| 0.4 | ✅ SELESAI | `.github/workflows/test.yml`; langkah wajib `php artisan test --exclude-group baseline` → lokal **127 test, 0 error, 0 gagal (61,5 s)** |
| 0.5 | ✅ SELESAI (ubah pendekatan) | flag `cbt_enabled=false` + middleware `EnsureModuleEnabled` (alias `module.asesmen`) → 404. **Route tetap terdaftar** (bukan dihapus) agar `route()` di view tidak melempar `RouteNotFoundException` |
| 0.6 | ✅ SELESAI | `git rm routes/web.php.remote all_routes.txt api.json`; `git mv setup_api_key.php tools/`; path di `tools/verifikasi-perbaikan.php` diperbaiki |
| 0.7 | ✅ SELESAI | Header "⚠️ KEDALUWARSA (17 Okt 2026)" di 6 dokumen. `00_START_HERE.txt` dilewati (penulisan ulang merusak encoding; sudah direvert) |

### Temuan baru dari eksekusi Fase 0

1. **Klaim "±20 endpoint 500" TIDAK TERBUKTI untuk GET.** Smoke test menemukan **hanya 2**:
   `guru GET /guru/laporan/pdf` (`guru.laporan.pdf`) dan `guru GET /tka/assessments/1/edit`
   (`tka.assessments.edit`). **Caveat penting:** parameter diisi `1`, sehingga banyak route
   `show/edit` berhenti di **404** (model tidak ada) sebelum merender view → angka 2 adalah **batas
   bawah**. View hilang (§2.4) tetap wajib ditutup; verifikasinya butuh data uji nyata.
2. **Docblock `@group` tidak lagi didukung di PHPUnit 12.5** (proyek memakai `phpunit/phpunit ^12.5`).
   Tag eksklusi baseline memakai atribut `#[Group('baseline')]`.
3. **2 test baseline yang LULUS — jangan dianggap "tidak ada bug":**
   `test_api_bank_soal_tidak_mengirim_kunci_jawaban` (kemungkinan route menolak lebih dulu, bukan
   bukti anti-bocor) dan `test_percobaan_kedua_tidak_menghitung_jawaban_percobaan_pertama`
   (skenario multi-attempt belum benar-benar terbentuk). Wajib dikaji ulang di Fase 2.
4. **Lingkungan lokal (SUDAH DIPERBAIKI 17 Okt).** `php.ini` Herd menyetel `sys_temp_dir` /
   `upload_tmp_dir` ke `D:\Project\simantap\storage\app\phptmp` (drive D: basi; proyek pindah ke E:).
   Akibatnya `tmpfile()` gagal → 3 error `TranskripTest` dan `php artisan test` gagal di Windows
   (`WindowsPipes`: "temporary file could not be opened"). Sudah diarahkan ke
   `E:\Project\simantap\storage\app\phptmp` (mempertahankan maksud asli: `artisan serve` child `-S`
   mewarisi TEMP). **CI (Linux) tidak pernah terpengaruh.**
5. **Perintah verifikasi lokal resmi:** `php artisan test --exclude-group baseline`
   (lulus 127 test, 390 assertion, ~51 s). Baseline merah: `php artisan test --group baseline`.

---

## 1c. REKONSILIASI 19–20 OKT 2026 — CBT DITULIS ULANG

Antara Fase 0 dan Fase 1, implementasi CBT lama dihapus dan diganti (commit `68a2eea`, `6912c9c`,
`4fcbef1`, `20115de`, `3e98367`). Konsekuensi:

| Item lama | Status baru |
|---|---|
| K-01..K-12 (CBT lama), `CbtStudentController`, `CbtSessionLock`, `CbtSecurityMiddleware`, model `App\Models\Cbt\*`, migrasi `2026_10_04_*cbt*` | **OBSOLETE / DIHAPUS** — diganti `CbtExamService` + `CbtScoring`, model `CbtQuestionBank/CbtQuestion/CbtTestSession/CbtResult/CbtViolation`, migrasi `2026_10_18_000001_create_cbt_tables` |
| `AuditCbtProofTest` (baseline merah Fase 0.2) | **DIHAPUS** (commit `6912c9c`) karena menguji skema lama; digantikan `tests/Feature/CbtExamTest.php` (28 test) + `CbtDisabledTest.php` |
| 13 view hilang (termasuk CBT) | Sebagian dibuat (commit `20115de`); sisa **17 view Tryout/TKA** (Fase 1.5) |
| `cbt.security` / `cbt.session.lock` | Dihapus; akses CBT via `CbtAccessMiddleware` (aktif + force_password_change) |

**Bukti:** `php artisan test --exclude-group baseline` → **177 lulus, 0 gagal** (20 Okt 2026).
`CbtExamTest` mencakup autosave, snapshot, IDOR siswa/guru, integritas, finalisasi, dan backup/restore.

**Yang benar-benar tersisa dari Fase 1:** modul **Tryout** (masih implementasi lama) dan **17 view**
Tryout/TKA. Itulah yang dikerjakan pada 20 Okt.

### Status Fase 1 (20 Okt 2026)

| # | Status | Bukti |
|---|---|---|
| 1.1 | ✅ CBT / ✅ Tryout | CBT baru tidak memakai `waktu_selesai` NOT NULL; migrasi `2026_10_20_000001_fix_tryout_schema` membuat `tryout_results.waktu_selesai` nullable |
| 1.2 | ✅ OBSOLETE (CBT) | skema CBT baru memakai `cbt_results` + snapshot; tidak ada `test_result_id` |
| 1.3 | ✅ OBSOLETE | middleware lama dihapus |
| 1.4 | ✅ | CBT baru tidak lagi memakai `asset('js/cbt')` |
| 1.5 | ✅ | 17 view Tryout/TKA dibuat (admin/guru TKA & Tryout); `view:cache` bersih; `RouteHealthAuditTest` hijau |
| 1.6 | ✅ | route TKA assessments eksplisit (commit `4fcbef1`) |
| 1.7 | ✅ | null-guard laporan PDF (commit `3e98367`) |
| 1.8 | ✅ | `Session::participants()` ditambahkan; `forceCompleteSession` tidak lagi menulis kolom `status` yang tidak ada |
| 1.9 | ✅ | `TryoutStudentController::submitAnswer` hanya mengembalikan `{success: true}` |
| 1.10 | ✅ OBSOLETE | CBT baru meng-escape soal (CbtExamTest: `test_html_soal_di_escape...`) |
| 1.11 | ✅ OBSOLETE | route baru `cbt.student.integrity` + `cbt.integrity.violations` |

Test regresi Tryout baru: `tests/Feature/TryoutTest.php` — **9 test lulus** (mulai tanpa 500, tanpa
bocor kunci, skor benar, 409 setelah selesai, IDOR 403, percobaan ulang terisolasi, soal asing 422,
siswa tanpa data, admin tanpa 500). Perbaikan tambahan: `tryout_sessions.durasi_menit`/`instruksi`
(kolom yang dipakai form tapi belum ada) dan `semester_id` nullable.

Bug 500 lain yang ditemukan saat membuat view dan sudah diperbaiki:
- `TryoutPackageAdminController::showResult()` memuat relasi `answers` yang tidak ada → ditambahkan
  `Result::answers()` (difilter sesi+siswa+attempt).
- `TkaAdminController::earlyWarnings()/showWarning()` memuat relasi `element`/`assessment` yang tidak
  ada → dihapus.
- Validasi status early warning memakai `notified/in_progress/resolved` padahal enum DB
  `pending/sedang_diproses/selesai` → diselaraskan (controller + 2 view).

**Verifikasi akhir 20 Okt 2026:** `php artisan test` → **191 test lulus, 0 gagal, 636 assertion,
~104 s**. `RouteHealthAuditTest` (flag CBT aktif) → **1050 kombinasi, 0 endpoint 5xx**.
`phpunit.xml` diberi `memory_limit=512M` (sebelumnya fatal 128 MB di dompdf/MenuAudit).
CI kini menjalankan **seluruh** suite sebagai langkah wajib (bukan lagi dua tahap).

---

## 1d. STATUS FASE 2 — IDOR & PENILAIAN (20 Okt 2026)

| # | Status | Bukti |
|---|---|---|
| 2.1 | ✅ | 6 policy dibuat: `CbtQuestionBankPolicy`, `CbtQuestionPolicy`, `CbtTestSessionPolicy`, `CbtResultPolicy`, `TryoutSessionPolicy`, `TryoutResultPolicy`; didaftarkan eksplisit lewat `Gate::policy()` di `AppServiceProvider` |
| 2.2 | ✅ | `AuthorizesRequests` ditambahkan ke `App\Http\Controllers\Controller`. CBT guru (bank/soal/sesi/integritas), Tryout guru (sesi), dan Tryout siswa memakai `$this->authorize()`. `CbtAuthorizationTest` (6 test) + 2 test IDOR baru di `TryoutTest`: semua **403**, status/jumlah baris tidak berubah |
| 2.3 | ✅ | `CbtQuestion::$hidden = ['correct_answer']`; test `test_api_soal_guru_pemilik_tidak_memuat_kunci_jawaban`. `TryoutStudentController::submitAnswer` hanya `{success:true}` |
| 2.4 | ✅ | CBT: jawaban menempel pada `cbt_results.answers` (unique `session_id+siswa_id`), bukan tabel global. Tryout: filter `attempt_number` (test isolasi attempt 1 vs 2) |
| 2.5 | ✅ CBT / ⏳ Tryout | CBT membekukan `question_snapshot` saat publish dan menilai dari snapshot (`CbtExamTest::test_snapshot_tidak_berubah_saat_bank_diedit`). Tryout belum punya snapshot → Fase 3 |
| 2.6 | ✅ | `App\Services\TryoutScoring` menilai `pilihan_ganda`, `benar_salah`, `isian_singkat` (normalisasi spasi/huruf); `isian_singkat` kini punya input di `take.blade.php`. Kunci tipe non-PG = opsi `adalah_benar`; tanpa kunci → 0, bukan benar. Unit test Tryout 4; unit test `CbtScoring` lama (6) dipertahankan. CBT hanya pilihan ganda A–D sesuai desain |
| 2.7 | ✅ OBSOLETE | `CbtSecurityMiddleware` sudah dihapus; route `GET .../result` dapat diakses (CbtExamTest) |
| 2.8 | ✅ | `Session::canStudentTake()` mengecek `kelas`/`rombel` dan siswa null; test `test_siswa_kelas_lain_tidak_bisa_memulai` |

Tambahan: grup route `admin/cbt` kini juga memakai middleware `module.asesmen` (sebelumnya lolos
kill-switch). Tidak ada migrasi baru di Fase 2.

**Verifikasi Fase 2:** `php artisan test` → **203 test lulus, 0 gagal, 674 assertion, ~179 s**
(termasuk `RouteHealthAuditTest`: 0 endpoint 5xx).

---

## 1e. STATUS FASE 3 — FONDASI ENGINE TERPADU (20 Okt 2026)

Lingkup sesi ini: **langkah 1–5 tuntas untuk Tryout dan CBT**. Memindahkan
pembaca lama (ekspor/laporan) ke `exam_*` dan menghapus tabel lama (langkah 6)
sengaja **belum** dikerjakan (butuh keputusan produk + rikonsiliasi data produksi).

| Langkah dokumen | Status | Bukti |
|---|---|---|
| (1) Tabel baru `exam_*` | ✅ | migrasi `2026_10_20_000002_create_exam_engine_tables.php`: `exam_engines`, `exam_banks`, `exam_sections`, `exam_questions`, `exam_question_options`, `exam_sessions`, `exam_attempts`, `exam_answers`, `exam_results` (+ model & relasi di `App\Models\Exam`) |
| (2) Backfill dari `cbt_*` + `tryout_*` | ✅ | `php artisan exam:backfill [--only=cbt|tryout]`, idempoten lewat jejak `sumber_tabel`+`sumber_id`; **tidak pernah menulis/menghapus tabel lama** |
| (3) Satu `ExamGradingService` | ✅ | `app/Services/ExamGradingService.php` menyatukan aturan `CbtScoring` (pilihan ganda) & `TryoutScoring` (benar_salah/isian_singkat + normalisasi); `essay` = menunggu penilaian manual; kunci eksplisit (`exam_questions.kunci_jawaban`) diutamakan di atas opsi `adalah_benar` |
| (4) Parity test | ✅ | `tests/Unit/ExamGradingParityTest.php` (5 test: parity vs kedua penilai lama, prioritas kunci, essay, normalisasi) + `tests/Feature/ExamEngineFoundationTest.php` (4 test: skema, relasi, kunci tak ikut serialisasi, backfill idempoten) |
| (5) Arahkan rute/tampilan — **Tryout** | ✅ | alur siswa Tryout (`start`→`answer`→`resume`→`complete`) kini menulis ke `exam_*` lewat `TryoutEngineBridge`; tabel `tryout_*` tetap ditulis sebagai **cermin** agar laporan/ekspor/admin yang belum dipindahkan tetap benar. Bukti: `tests/Feature/TryoutEngineParityTest.php` (4 test) + 12 test `TryoutTest` lama tetap hijau tanpa perubahan assertion |
| (5) Arahkan rute/tampilan — **CBT** | ✅ | `CbtExamService` tetap pemilik siklus (kunci, kedaluwarsa, deadline, idempotensi) lalu menulis lewat `CbtEngineBridge` ke `exam_*`; tabel `cbt_*` tetap dicerminkan. Kunci dibekukan dari `question_snapshot` ke `exam_questions.kunci_jawaban` agar mengedit bank setelah publikasi tidak menggeser nilai. Bukti: `tests/Feature/CbtEngineParityTest.php` (5 test) + 28 test `CbtExamTest` lama tetap hijau |
| (5b) Sisa tampilan lain (ekspor/laporan) | ✅ | pembaca lama dikunci lebih dulu dengan `tests/Feature/ExamReaderTest.php` (ekspor CSV CBT, halaman hasil Tryout admin, dasbor ortu) — sebelumnya **tanpa test sama sekali**. Disusul pembaca pengganti murni `exam_*` (`ExamReportService` + rute `admin.cbt.sessions.export-engine`) yang keluarannya **identik** dengan jalur lama (diuji parity). Jalur lama tetap dipakai di produksi sebagai pembanding. |
| (5c) Pelanggaran CBT ke engine | ✅ | tabel `exam_violations` baru (migrasi aditif, jejak `sumber_tabel`/`sumber_id`), ditulis lewat `CbtEngineBridge::catatPelanggaran` di alur langsung dan dipetakan ulang oleh `exam:backfill`. Kolom **Pelanggaran** pada ekspor engine kini terisi dan ikut diuji parity penuh (seluruh kolom identik). |
| (5d) Cakupan backup `exam_*` | ✅ | seluruh 10 tabel `exam_*` didaftarkan di `BackupTables` **dan** daftar wipe `$tabelData`, sehingga backup/restore tidak lagi kehilangan data engine (sebelumnya tidak tercadang maupun terhapus → keadaan setengah saat wipe). Diperiksa rule cakupan: 46 tabel di-wipe, semuanya tercakup. |
| (6) Drop tabel lama | ⏳ belum | pembaca pengganti sudah terbukti setara & `exam_*` sudah tercadang; menunggu rikonsiliasi data produksi |

Catatan desain: `exam_sections` ditambahkan sebagai tingkat yang hilang pada skema awal
dokumen, karena Tryout berjenjang paket → mapel; CBT hanya memakai satu tingkat bank → soal.

Catatan langkah 5: `config('simantap.exam_engine_tryout')` dan
`config('simantap.exam_engine_cbt')` (keduanya default `true`, dapat dimatikan
lewat `SIMANTAP_EXAM_ENGINE_TRYOUT=false` / `SIMANTAP_EXAM_ENGINE_CBT=false`)
memilih engine. Pemetaan bank/section/soal dipusatkan di
`App\Services\Exam\LegacyExamMapper` dan dipakai baik oleh `exam:backfill`
maupun alur ujian langsung, sehingga aturan keduanya tidak dapat berbeda. Soal
yang dibuat **setelah** ujian dimulai tetap dipetakan saat dijawab/dinilai (uji
cakupan di `TryoutEngineParityTest`).

Catatan CBT: `CbtExamService` sengaja **tidak** ditulis ulang — ia tetap pemilik
semantik siklus yang rumit (kunci baris, deadline dipotong `closes_at`,
finalisasi kedaluwarsa, idempotensi submit) dan hanya menambah tiga titik tulis
lewat `CbtEngineBridge`. Kunci penilaian dibekukan dari `question_snapshot` ke
`exam_questions.kunci_jawaban`, dan `ExamGradingService` kini mengutamakan
`kunci_jawaban` di atas opsi `adalah_benar` untuk soal pilihan ganda — sesuai
desain Fase 3. Akibatnya mengedit bank setelah publikasi tidak menggeser nilai
(diuji `CbtEngineParityTest::test_snapshot_menang_atas_bank_yang_diedit`).

**Verifikasi Fase 3:** `php artisan test` → **233 test lulus, 0 gagal, 898 assertion, ~220 s**.

Pelanggaran CBT kini juga bermukim di engine (`exam_violations`, ditulis di alur
langsung lewat `CbtEngineBridge::catatPelanggaran` dan dipetakan ulang oleh
`exam:backfill`), sehingga ekspor pengganti murni `exam_*` identik dengan jalur
lama **pada seluruh kolom**, termasuk kolom Pelanggaran.

Seluruh 10 tabel `exam_*` juga didaftarkan di `App\Support\BackupTables` **dan**
daftar wipe `$tabelData`. Sebelumnya keduanya luput: backup tidak memuat data
engine, dan "Hapus Semua Data" tidak mengosongkannya — sehingga wipe + restore
kehilangan hasil ujian. Aturan cakupan (`tools/verifikasi-perbaikan.php` §8)
memverifikasi 46 tabel di-wipe semuanya tercadang atau dikecualikan.

Dua bug nyata yang ketangkap saat mengunci pembaca (langkah 5b):

1. **Ekspor CSV CBT 500** — `CbtSessionController::exportResults` memanggil
   `$session->load('guru')`, padahal `CbtTestSession` tidak punya relasi `guru()`.
   Tombol ekspor di UI admin benar-benar rusak; diperbaiki dengan menambahkan
   relasi tersebut.
2. **Halaman Hasil Tryout menampilkan 0.0** — `admin.tryout.results.index`
   membaca `skor_total`, `jumlah_benar/salah/kosong`, dan `waktu_pengerjaan_detik`
   yang **tidak ada** di `Tryout\Result`, sehingga tabel & kartu statistik selalu
   tampil 0.0/kosong. Disajikan lewat accessor (`skor_total` = persentase, agar
   sebanding dengan ambang kelulusan 60) sehingga nilai sebenarnya tampil.

---

## 1f. VERIFIKASI ULANG KEADAAN TERKINI (25 OKT 2026)

**Basis:** commit `6088244edbc3ff986f3ba3aceaca62dc23bc5321` pada PR #9.
Bukti CI: [run 40171614678](https://github.com/amhyer/simantapmonsa/actions/runs/40171614678).
Hasil: PHPUnit PHP 8.4 (SQLite) **lulus**, PHPUnit PostgreSQL 16 **lulus**, smoke Chromium
landing/FAQ **lulus**, dan `php artisan view:cache` **lulus**. Ketiga job CI selesai sukses.

### Temuan yang diperiksa ulang

| Temuan | Status aktual | Bukti yang dijalankan |
|---|---|---|
| `GET /ortu/dashboard` tanpa anak terhubung | **200; pesan aman** | `CriticalRoutesRegressionTest::test_dashboard_ortu_tanpa_anak_tetap_merender_pesan_yang_aman` |
| `GET /guru/laporan/pdf` tanpa pengaturan, dengan 12 siswa | **200; PDF `%PDF-` valid** | `CriticalRoutesRegressionTest::test_pdf_laporan_guru_berhasil_tanpa_pengaturan_dan_tanpa_query_kehadiran_n_plus_satu` |
| `GET /guru/erapor/excel` tanpa pengaturan, dengan siswa | **200; stream XLSX `PK` valid**; semester/tahun ajaran fallback ditetapkan bila pengaturan guru belum dibuat | `CriticalRoutesRegressionTest::test_export_erapor_excel_berhasil_tanpa_pengaturan_dan_dengan_siswa` |
| N+1 kehadiran pada laporan guru | **Diperbaiki**: persentase dihitung dari agregat yang sudah diambil sekaligus; filter semester berulang per baris nilai dihapus karena siswa sudah difilter pada query awal | Dua test laporan memeriksa tidak ada query `COUNT` kehadiran per siswa; index juga mempertahankan hasil 50% pada fixture H/S |
| `GET /tka/assessments/1/edit` | **404 terencana, bukan 500** | Route edit tidak didaftarkan karena controller tidak mendukung edit; `CriticalRoutesRegressionTest::test_edit_assessment_yang_tidak_didukung_tidak_melempar_500` |
| Alur TKA: input skor berbobot → hasil → laporan orang tua | **Berjalan; skor terverifikasi** (13/16 = 81,25%, rerata berbobot 3,25 → `siap`) | `TkaAssessmentFlowTest::test_alur_tka_menilai_menghitung_membuat_dan_membaca_laporan_orang_tua` |
| Rekomendasi orang tua TKA untuk dimensi lemah | **Berjalan**; filter closure yang sebelumnya tak dievaluasi diperbaiki | `TkaAssessmentFlowTest::test_laporan_tka_menyertakan_saran_untuk_dimensi_yang_lemah` |
| IDOR asesmen TKA guru | **Ditutup untuk catatan siswa**: form hanya menampilkan siswa guru yang login; create/show/riwayat/generate report memeriksa kepemilikan via `TkaAssessmentPolicy` | `TkaAssessmentFlowTest::test_guru_tidak_dapat_melihat_membuat_atau_menerbitkan_laporan_untuk_siswa_guru_lain` |
| ID item lintas-dimensi TKA | **Ditolak 404** saat ID item tidak termasuk dimensi parent pada route nested; tidak ada penghapusan | `TkaAssessmentFlowTest::test_item_dimensi_lain_tidak_dapat_dihapus_melalui_route_nested` |
| Akses orang tua ke laporan TKA | **Hanya akun yang terhubung dapat membaca**; ID siswa string/integer dinormalisasi; akses orang tua lain 403 | Test alur TKA di atas menguji akses benar, penandaan dibaca, dan penolakan parent lain |
| 13 view yang pernah dilaporkan hilang | **Semua tersedia** | `CriticalRoutesRegressionTest::test_view_yang_pernah_dilaporkan_hilang_semuanya_tersedia` + kompilasi seluruh Blade di CI |
| Ekspor CSV CBT | **Berjalan** | `ExamReaderTest::test_ekspor_csv_cbt_memuat_nilai_dan_pelanggaran` membuat ujian, memanggil ekspor, dan memeriksa isi CSV |
| Start → autosave → anti-cheat → submit → hasil CBT | **Berjalan** | `CbtExamTest::test_alur_siswa_end_to_end_mulai_autosave_anti_cheat_submit_hasil` |
| Start → autosave → complete → hasil Tryout | **Berjalan** | `TryoutTest::test_alur_siswa_tryout_end_to_end_mulai_autosave_complete_hasil` |
| IDOR lintas akun CBT, Tryout, dan asesmen TKA | **Policy terdaftar; aksi baca/tulis/telemetri diperiksa** | 7 Policy di `app/Policies` (4 CBT, 2 Tryout, 1 TKA); `CbtAuthorizationTest`, `CbtExamTest`, `TryoutTest`, dan `TkaAssessmentFlowTest` menguji akses ditolak/data tetap utuh |
| Smoke route GET | **Semua route GET terdaftar, >1.000 kombinasi route × peran, tanpa 5xx** | `RouteHealthAuditTest` menjalankan enam peran (admin, guru, siswa, ortu, kepsek, tendik) dan gagal jika menemukan HTTP 5xx |

### Batas cakupan yang tetap harus dinyatakan

- Route-health mengisi parameter dinamis dengan nilai contoh `1`; binding yang datanya tidak
  ada dapat berakhir 404 sebelum view dijalankan. Untuk itu, 13 view lama dan endpoint kritis
  di atas diberi pemeriksaan khusus dengan fixture yang benar. Angka 0 5xx tidak berarti setiap
  kombinasi ID/data produksi telah diuji.
- Perbaikan laporan guru membuktikan tidak adanya query kehadiran per siswa pada dua halaman
  yang diuji; belum ada benchmark waktu nyata atau pengukuran ulang skor performa 60%/cold-start.
- PHPUnit PostgreSQL CI memakai database sementara, bukan PostgreSQL produksi. Layanan langsung
  Dapodik/Google Sheets dan staging sekolah tetap belum diuji; Chromium hanya menguji landing/FAQ,
  bukan keseluruhan UI CBT/Tryout/TKA.
- `AuditCbtProofTest` lama memang tidak dikembalikan karena menargetkan skema CBT yang sudah
  dihapus. Penjaga aktifnya adalah `CbtExamTest`, `CbtAuthorizationTest`, `TryoutTest`, test parity
  engine, dan `RouteHealthAuditTest`.
- Engine CBT/Tryout sudah memiliki bridge ke `exam_*` (lihat §1e); tabel legacy tetap dicerminkan
  dan belum boleh dihapus sebelum rekonsiliasi data produksi.

## 2. MATRIKS VERIFIKASI — SNAPSHOT 17 OKTOBER (lihat status terkini §1f)

> Matriks berikut merekam temuan awal, sebelum rekonsiliasi dan perbaikan pada §§1c–1f. Status
> **RUSAK** di snapshot lama tidak mengalahkan bukti eksekusi lebih baru di §1f.

Kode historis: **RUSAK** = masih belum diperbaiki pada 17 Okt · **SEBAGIAN** = ada perbaikan parsial · **HILANG** = artefak yang diklaim ada ternyata tidak ada.

### 2.1 CBT (dari AUDIT_SIMANTAP_MENDALAM, §1)

| ID | Temuan | Status | Bukti verifikasi (file:baris) |
|---|---|---|---|
| K-01 | `waktu_selesai` NOT NULL saat insert null → 500 saat Mulai Ujian | **RUSAK** | `database/migrations/2026_10_04_210653_create_cbt_test_results_table.php:28` (`dateTime` tanpa `nullable`); `Cbt/CbtStudentController.php:67` insert `'waktu_selesai' => null`. Belum ada migrasi perbaikan |
| K-02 | Middleware `find(route param)` menerima model → Collection → 500 | **RUSAK** | `app/Http/Middleware/CbtSessionLock.php:17` masih `TestResult::find($request->route('result'))` |
| K-03 | `submitAnswer` pakai `test_result_id` yang tidak ada di skema → 500 | **RUSAK** | `CbtStudentController.php:106,122` memakai `test_result_id`; migrasi `cbt_student_answers` (2026_10_04_210643) hanya punya `test_session_id` (baris 17). Kolom `test_result_id` tidak ada |
| K-04 | JS CBT 404 → timer/auto-save/anti-cheat mati total | **RUSAK** | `vite.config.js:7` hanya 3 entry (app.css, app.js, guru-dashboard.js); `take.blade.php:387-389` masih `asset("js/cbt/...")`; `public/js/` **tidak ada**; `resources/js/cbt/{timer,auto-save,integrity-checker}.js` ada tapi tak pernah dibangun |
| K-05 | IDOR siswa: siswa A bisa tulis/tutup/buka hasil siswa B | **RUSAK** | `CbtStudentController::submitAnswer/submit/result` nol cek `siswa_id` vs login |
| K-06 | IDOR guru: guru B ubah/hapus bank soal & soal milik guru A | **RUSAK** | `CbtQuestionBankController` hanya `index()` yang filter `guru_id` (baris ±); `show/edit/update/destroy/apiGetQuestions` tanpa guard. `app/Policies/` tidak ada |
| K-07 | Kebocoran kunci jawaban lintas guru via `api/questions` (`adalah_benar: true`) | **RUSAK** | `CbtQuestionBankController::apiGetQuestions` tanpa filter guru + mengembalikan field `adalah_benar` |
| K-08 | IDOR guru: ubah status sesi ujian guru lain (publish/start/complete/monitoring) | **RUSAK** | `CbtTestSessionController::{show,publish,start,complete,monitoring,apiMonitoring}` tanpa cek `guru_id` |
| K-09 | `nilai_maksimal` dihitung dari SELURUH bank, bukan soal tampil → status lulus salah | **RUSAK** | `CbtStudentController.php:178` `$result->testSession->questionBank->questions->sum('poin')` |
| K-10 | Attempt ke-2 menghitung jawaban attempt ke-1 | **RUSAK** | `app/Models/Cbt/TestResult.php:55-59` relasi `studentAnswers()` hanya join `test_session_id`+`siswa_id`, tanpa `attempt_number`/`test_result_id` |
| K-11 | Halaman nilai terkunci `CbtSecurityMiddleware` setelah submit | **RUSAK** | Perlu dipastikan di `CbtSecurityMiddleware`; route `GET /cbt/student/{result}/result` masih lewat middleware `cbt.security` (routes/web.php:454) |
| K-12a | Endpoint pelaporan anti-cheat di grup `role:guru` → siswa selalu 403 | **RUSAK** | `routes/web.php:445-448` route `cbt.integrity.log` di grup `role:guru`; pemanggil: siswa via `integrity-checker.js` |
| K-12b | Enum `jenis_pelanggaran` JS ≠ validasi controller → 422 senyap | **RUSAK** | JS kirim `context_menu`, `screenshot` (`integrity-checker.js`); controller `in:tab_switch,copy_paste,screen_capture,focus_lost,multiple_tabs,suspicious_activity` |
| K-12c | `data_tambahan` dikirim string JSON, divalidasi `array` → 422 senyap | **RUSAK** | JS: `payload.append('data_tambahan', JSON.stringify(...))`; controller: `'nullable|array'` |
| — | Grading `benar_salah` keliru: selalu cek `'benar'`, bukan kunci soal | **RUSAK** | `CbtStudentController.php:159-160` `strtolower($answer->jawaban) === 'benar'` |
| — | `isian_singkat`/`menjodohkan`/`essay` tidak ditangani grader → jatuh "salah" | **RUSAK** | `CbtStudentController.php:156-161` hanya 2 tipe soal |
| — | Randomisasi setelah `limit()` → soal teratas selalu terpilih | **RUSAK** | `CbtStudentController.php:76-83` |
| — | XSS sinkrip soal: `json_encode($questions)` tanpa `JSON_HEX_TAG` | **RUSAK** | `take.blade.php:300` `{!! json_encode($questions) !!}` |

### 2.2 Tryout (AUDIT §2)

| ID | Temuan | Status | Bukti |
|---|---|---|---|
| T-01 | `start` 500: `tryout_results.waktu_selesai` NOT NULL | **RUSAK** | Migrasi `2026_10_04_222414:30` tanpa nullable; `TryoutStudentController.php:65` insert null |
| T-02 | IDOR: submit/complete/result tanpa cek kepemilikan | **RUSAK** | `TryoutStudentController` tanpa guard `siswa_id` |
| T-03 | `submitAnswer` bocorkan `is_correct` + `poin` ke siswa saat tes berjalan | **RUSAK** | `TryoutStudentController.php:120` `return ... ['is_correct' => $isCorrect, 'poin' => $poin]` |
| T-04 | `start()` tidak cek `$siswa` null | **RUSAK** | Berbeda dari CBT yang sudah cek |
| T-05 | `updateOrCreate` paksa `attempt_number => 1` → percobaan ulang menimpa | **RUSAK** | `TryoutStudentController.php:58,106` |
| T-06 | `admin/tryout/sessions/{id}` 500: relasi `participants` tidak ada | **RUSAK** | `TryoutPackageAdminController.php:272`; model `Tryout\Session` tanpa `participants()` |
| T-07 | `admin/tryout/sessions` index 500: `count() on null` | **RUSAK** | View `admin/tryout/sessions/index.blade.php` |

### 2.3 TKA & e-Rapor (AUDIT §3)

| ID | Temuan | Status | Catatan |
|---|---|---|---|
| TKA-01 | Route `resource` `tka/assessments` daftarkan 7 method, controller punya 5 (`edit` hilang) → 500 | **RUSAK** | `routes/web.php:601` `Route::resource('assessments', ...)` |
| TKA-02 | View `tka.assessments.by-siswa` hilang | **RUSAK** | Cek: file tidak ada |
| TKA-03 | View `admin.tka.students.history` hilang | **RUSAK** | Cek: file tidak ada |
| TKA-04 | `guru/erapor/excel` 500: `SekolahSettings` null tak dicek | **RUSAK** | `ErapotGeneratorController.php:110` |
| TKA-05 | `cbt/question-banks/{id}` 500: `$tingkat_kesulitan` undefined (relasi semester → stdClass) | **RUSAK** | View `cbt/question-banks/show.blade.php` |

### 2.4 13 View yang hilang (semua terverifikasi `False`)

| Controller | View hilang |
|---|---|
| `Admin\Cbt\CbtBankController` | `admin.cbt.banks.show`, `admin.cbt.banks.edit` |
| `Admin\Cbt\CbtSessionController` | `admin.cbt.sessions.show` |
| `Cbt\CbtTestSessionController` | `cbt.sessions.show` |
| `Admin\Tryout\TryoutPackageAdminController` | `admin.tryout.packages.show`, `admin.tryout.packages.edit`, `admin.tryout.sessions.edit` |
| `Tryout\TryoutPackageController` | `tryout.packages.show`, `tryout.packages.edit` |
| `Tryout\TryoutSessionController` | `tryout.sessions.show`, `tryout.sessions.monitoring` |
| `Tka\TkaAssessmentController` | `tka.assessments.by-siswa` |
| `Admin\Tka\TkaAdminController` | `admin.tka.students.history` |

### 2.5 Infrastruktur & mutu (gabungan M-01..M-16 + P1/P2 dokumen statis)

| Temuan | Status | Bukti |
|---|---|---|
| Tidak ada CI | **RUSAK** | `.github/` tidak berisi workflows |
| 0 Policy | **RUSAK** | `app/Policies/` tidak ada |
| `TestCase` tanpa `withoutVite()` | **RUSAK** | `tests/TestCase.php` kosong |
| Artefak regression test audit hilang | **HILANG** | `tests/Feature/Cbt/`, `AuditCbtProofTest`, `RouteHealthAuditTest`, `tools/authz_sweep.py` tidak ada di tree. Hanya `tools/verifikasi-perbaikan.php` yang ada |
| `routes/web.php.remote` (71 KB sisa merge) masih ada | **RUSAK** | Ada di root `routes/` |
| `all_routes.txt` berisi pesan error | **RUSAK** | Ada |
| `api.json` OpenAPI generic belum dikustom | **RUSAK** | Ada |
| `setup_api_key.php` di root repo | **RUSAK** | Ada (seharusnya di `tools/`) |
| `tools/verify.sh` & `diagnose.sh` | **TIDAK ADA** | Audit lama klaim ada — ternyata tidak (koreksi terhadap M-15) |
| `composer.json` PHP `^8.3` vs lock `>=8.4.1` | **TIDAK TERKONFIRMASI** | Lock `platform.php` = `^8.3` sama; perlu cek ulang saat install di lingkungan bersih |
| Feature flag `cbt_enabled` default `true` + route admin/tryout/tka DI LUAR flag | **RUSAK** | `config/simantap.php:16` default `true`; `routes/web.php:464,492,532,563,585,594` di luar blok `if (config('simantap.cbt_enabled'))` |
| Backup terjadwal ≠ backup panel (5 tabel vs registry luas) | **RUSAK** | `app/Console/Commands/BackupData.php` vs `BackupController::export` |
| Registry backup belum termasuk tabel CBT/tryout/TKA; wipe juga tidak | **RUSAK** | `app/Support/BackupTables.php` |
| Google Sheets sync: `clearSheet()` tab pusat + subset 1 guru | **RUSAK** | `Guru/IntegrasiController.php::fullSync` |
| Hanya 1 FormRequest di 355 route | **RUSAK** | `app/Http/Requests/` = `ProfileUpdateRequest.php` saja |
| `nama_guru` NOT NULL denormalisasi di `kuis`/`nilai`/`nilai_erapor` | **RUSAK** | Sumber bug insert |
| Dokumen "100% FIXED"/"Completion rate 100%" | **KEDALUWARSA** | `docs/03-REMAINING-ISSUES.md`, `docs/IMPROVEMENT_BACKLOG.md` |
| README masih boilerplate Laravel | **RUSAK** | `README.md` |
| Commit `56f7922` klaim "127/127 hijau" | **SALAH** | Suite bergantung `public/build/manifest.json`; `MenuAuditTest` pernah ERROR memory |

---

## 3. BACKLOG PERBAIKAN BERURUTAN

Setiap item punya **kriteria lulus** berbasis eksekusi. Urutan wajib dijaga.

### Fase 0 — Pagar pengaman (½ hari) — SEBELUM PERBAIKAN APAPUN

> ✅ **SELESAI 17 Okt 2026** — bukti eksekusi di §1b.

| # | Langkah | Kriteria lulus |
|---|---|---|
| 0.1 | `tests/TestCase.php`: tambah `withoutVite()` | `php artisan test` bisa jalan di clone segar tanpa `npm run build` |
| 0.2 | Tulis ulang `tests/Feature/Cbt/AuditCbtProofTest.php` (19 test pembuktian dari audit) — saat ini HARUS gagal merah | Test gagal sesuai prediksi bug (bukti baseline) |
| 0.3 | Tulis ulang `tests/Feature/RouteHealthAuditTest.php` (smoke rute × peran, deteksi 5xx) | Report daftar endpoint 500 tersimpan |
| 0.4 | CI `.github/workflows/test.yml`: PHP 8.4, `composer install`, `npm ci && npm run build`, `php artisan test` (SQLite) | Workflow hijau di push/PR |
| 0.5 | Set `SIMANTAP_CBT_ENABLED` default **`false`**; bungkus route `admin/cbt`, `admin/tryout`, `admin/tka`, `tryout.*`, `tka.*` dalam blok flag yang sama | Dengan flag=false, seluruh rute CBT/tryout/TKA 404 (bukan 500) |
| 0.6 | Hapus artefak: `routes/web.php.remote`, `all_routes.txt`, `api.json`; pindah `setup_api_key.php` ke `tools/` | File tidak ada; `php artisan route:list` tetap jalan |
| 0.7 | Tandai dokumen kedaluwarsa (`03-REMAINING-ISSUES.md`, `IMPROVEMENT_BACKLOG.md`, laporan audit lama) dengan header "KEDALUWARSA — lihat RENCANA_PERBAIKAN_VERIFIED.md" | Header ada |

### Fase 1 — Nyalakan CBT & Tryout (2–3 hari)

| # | Langkah | Kriteria lulus |
|---|---|---|
| 1.1 | Migrasi baru: `cbt_test_results.waktu_selesai` + `durasi_detik` nullable; `tryout_results.waktu_selesai` nullable (JANGAN edit migrasi lama) | Test `start` CBT & tryout → HTTP 200, record tercipta |
| 1.2 | Migrasi: tambah `test_result_id` (FK → `cbt_test_results`) + unique constraint ke `cbt_student_answers`; controller isi `test_result_id` + `test_session_id` | Autosave sukses; jawaban tercatat benar |
| 1.3 | Fix `CbtSessionLock`/`CbtSecurityMiddleware`: pakai route param yang sudah di-bound (`instanceof TestResult`), jangan `find()` ulang | Semua request `/cbt/student/{result}/*` tidak 500 |
| 1.4 | Vite: daftarkan entry `resources/js/cbt/index.js` (bundle timer+autosave+integrity); ubah `take.blade.php` ke `@vite` | `npm run build` menghasilkan aset; timer jalan di browser |
| 1.5 | Tulis 13 view yang hilang (acu: view `index`/`create` yang sudah ada) | Route-health test: endpoint terkait bukan 500 |
| 1.6 | `TkaAssessmentController`: tambah `edit`/`update`/`destroy` ATAU ubah route jadi non-resource | `tka/assessments/{id}/edit` bukan 500 |
| 1.7 | `ErapotGeneratorController`: null-safe `SekolahSettings` | `guru/erapor/excel` tanpa settings tetap aman |
| 1.8 | `Tryout\Session`: tambah relasi `participants()`; fix view admin sessions | Halaman admin tryout sessions bukan 500 |
| 1.9 | Hapus `is_correct`/`poin` dari respons `submitAnswer` Tryout (hanya `{success:true}`) | Respons tidak berisi kunci |
| 1.10 | `take.blade.php:300`: `json_encode(..., JSON_HEX_TAG\|JSON_HEX_APOS\|JSON_HEX_QUOT\|JSON_HEX_AMP)` | Soal berisi `</script>` tidak menembus |
| 1.11 | Pindahkan endpoint `cbt.integrity.log` ke grup siswa + samakan enum `jenis_pelanggaran` JS↔controller + terima `data_tambahan` sebagai string-JSON yang di-decode | Siswa bisa laporkan pelanggaran; log masuk DB |

### Fase 2 — Tutup semua IDOR & benarkan penilaian (2 hari)

| # | Langkah | Kriteria lulus |
|---|---|---|
| 2.1 | Buat `app/Policies/`: `CbtQuestionBankPolicy`, `CbtQuestionPolicy`, `CbtTestSessionPolicy`, `CbtTestResultPolicy`, `TryoutSessionPolicy`, `TryoutResultPolicy`; registrasikan | File ada; `Gate::resolve` tidak error |
| 2.2 | `Gate::authorize()`/`$this->authorize()` di SETIAP action guru (bank, soal, sesi) & siswa (result/answer) | Tes negatif 2-guru & 2-siswa: semua dapat **403**, DB tidak berubah |
| 2.3 | **Hapus `adalah_benar` dari semua respons API** (`api/questions` dsb.) — skor dihitung server saja | Respons API guru tidak memuat kunci jawaban |
| 2.4 | Relasi jawaban per attempt: `studentAnswers()` filter `test_result_id` + `attempt_number` | Attempt 2 tidak menghitung jawaban attempt 1 (test numerik) |
| 2.5 | `nilai_maksimal` dihitung dari snapshot soal yang ditampilkan (simpan `AttemptQuestion`/snapshot saat `start`) | Bank 10 soal, tampil 2 → `nilai_maksimal` = 20 |
| 2.6 | Grader: kunci `benar_salah` eksplisit dari soal; `isian_singkat` dengan normalisasi; `essay` status "menunggu penilaian" | Unit test per tipe soal |
| 2.7 | `CbtSecurityMiddleware`: izinkan route `GET .../result` (kunci read-only, jangan tolak) | Siswa lihat nilai setelah submit |
| 2.8 | `canStudentTake()`: cek kelas/rombel + cek `$siswa` null di tryout juga | Siswa kelas lain ditolak |

### Fase 3 — Satukan engine asesmen (3–5 hari) — setelah Fase 1–2 hijau

Skema target:

```
exam_engines  (id, nama, jenis: cbt|tryout)
exam_banks    (id, engine_id, ...)
exam_questions / exam_question_options
exam_sessions (id, bank_id, ...)
exam_attempts (id, session_id, siswa_id, attempt_number, ...)
exam_answers  (id, attempt_id, question_id, jawaban, ...)
exam_results  (id, attempt_id, ...)
```

Langkah: (1) tabel baru → (2) backfill dari `cbt_*` + `tryout_*` → (3) satu `ExamGradingService` →
(4) parity test lulus → (5) arahkan route/view ke engine baru → (6) drop tabel lama (rikonsiliasi data).
Kriteria lulus: satu attempt bisa dibuat → dijawab → dilanjutkan → dikumpulkan sekali → dinilai benar
→ direview → hanya terlihat pihak berwenang; skor konsisten di UI, laporan, ekspor.

### Fase 4 — Kebersihan & mutu (2 hari)

| # | Langkah |
|---|---|
| 4.1 | Pindahkan validasi ke FormRequest (mulai CBT/Tryout) |
| 4.2 | `laravel/pint` di CI |
| 4.3 | Satu registry backup (`BackupTables`) dipakai export panel, `BackupData` terjadwal, restore, DAN wipe — termasuk tabel CBT/tryout/TKA. nama file dibedakan penuh vs parsial |
| 4.4 | Streaming export benar (tulis ke disk, jangan `stream_get_contents` seluruh JSON ke memori) |
| 4.5 | README proper (ganti boilerplate Laravel) |
| 4.6 | Tulis ulang laporan status dengan bukti eksekusi (jumlah test, jumlah lulus, timestamp) |

### Fase 5 — Fitur baru (hanya setelah Fase 0–4 stabil)

Lihat §5. Rilis per modul dengan feature flag + acceptance criteria.

---

## 4. MATRIKS TES MINIMUM SEBELUM RILIS

| Modul/alur | Skenario sukses | Skenario negatif wajib |
|---|---|---|
| Login/role | Setiap role masuk ke dashboardnya | Akun nonaktif, gagal login berulang, role salah |
| CBT | Mulai → autosave → resume → submit → nilai → review | ID result siswa lain, ID sesi/bank guru lain, waktu habis, submit ganda, soal di luar sesi |
| Tryout | Mulai → simpan semua jawaban → selesai → rekap | Result orang lain, paket lain, bocor nilai sebelum selesai, akun tanpa record siswa |
| TKA | Guru menilai siswa cakupannya; ortu lihat laporan anak | Guru kirim siswa/asesmen di luar cakupan; ortu ganti ID anak |
| e-Rapor | Import → hitung → cetak → ekspor konsisten | Jenis nilai tidak valid, jadwal tak terpetakan, duplikasi import |
| Google Sheets | Sync sesuai model personal/pusat | Guru A hapus data guru B; kegagalan API di tengah sync |
| Dapodik | Preview → sinkron → log hasil | Duplikat, referensi invalid, key tak valid, timeout/retry, partial failure |
| Backup/restore | Backup lengkap → restore staging → row counts + relasi terverifikasi | Backup parsial, checksum salah, skema beda, restore gagal di tengah |
| Wipe | Dry-run + konfirmasi kuat setelah backup valid | Pembatalan, password salah, tabel gagal, cascade tak diharapkan |
| Route health | 0 endpoint 5xx pada semua kombinasi rute × peran | Regresi view/route hilang terdeteksi CI |

Tes negatif wajib memeriksa: status HTTP **dan** DB tidak berubah **dan** tidak ada info rahasia di respons/log.

---

## 5. FITUR INOVATIF (Fase 5) — Diprioritaskan

Urut berdasarkan nilai ÷ risiko untuk sekolah dasar.

| Prioritas | Fitur | Ringkasan | Prasyarat |
|---|---|---|---|
| 1 | **Satu mesin asesmen** | Konsolidasi CBT+Tryout+kuis; menghapus duplikasi & dua set bug | Fase 1–2 lulus |
| 2 | **Audit trail akademik** | Log hash-chain perubahan nilai rapor (`prev_hash→hash`), status draft→disahkan→terbit, snapshot saat publish | Fase 4 |
| 3 | **Item analysis otomatis** | Difficulty index (P), discrimination index (D), deteksi distraktor lemah, laporan per bank soal | Mesin asesmen |
| 4 | **Portal orang tua (PWA-first)** | Ringkasan mingguan kehadiran/tugas/nilai, push notification, mode data hemat | Mesin asesmen + data lengkap |
| 5 | **Notifikasi WhatsApp gateway** | Info ujian, pengumuman, rekap kehadiran ke WA ortu; two-way ack | Portal ortu |
| 6 | **AI deskripsi rapor (berpagar)** | LLM bantu narasi rapor Kurikulum Merdeka dari nilai+CP; guru edit sebelum terbit; **tanpa data pribadi ke cloud tanpa izin** | Audit trail |
| 7 | **Early-warning akademik transparan** | Aturan terbuka (penurunan rata-rata 2 periode, absen berulang) + alasan + tindakan; hindari label otomatis menghakimi | Data 2+ semester |
| 8 | **Adiwiyata / Eco-School tracker** | Pencatatan kebersihan, bank sampah, efisiensi energi, indikator KLHK, rapor Adiwiyata | Modul mandiri (risiko rendah) |
| 9 | **Dapodik sync center** | Panel status koneksi, preview/dry-run, retry+checkpoint, laporan konflik, idempotency key | Fase 4 |
| 10 | **Mode ujian offline** | Service Worker + IndexedDB, sync queue, token HMAC per soal, konflik server-side | Mesin asesmen |
| 11 | **Multi-sekolah (SaaS)** | `schools` + `school_id`, plan/limit, billing Midtrans/Xendit, isolasi policy global | Semua fase stabil |

---

## 6. CATATAN KOORDINASI & METODOLOGI

1. **Pembagian kerja (diperbarui 17 Okt):** sesuai keputusan pemilik project, **Opencode
   mengerjakan perbaikan CBT/Tryout juga** (melonggarkan pembagian lama AGENTS.md di mana CBT
   = Kiro). Pembagian lain tetap: Kiro = alignment e-Rapor (docs 09-12); Opencode = Transkrip,
   Cetak, sidebar, security, test `TranskripTest`/`CetakTest`. File bersama (`routes/web.php`,
   `sidebar.blade.php`, `MenuAuditTest.php`, `layouts/app.blade.php`): baca dulu, edit seperlunya,
   wajib phpunit hijau sebelum commit.
2. **PHP wajib** `C:\Users\USER\.config\herd\bin\php84\php.exe` (jangan `php` polos). Test =
   sqlite in-memory → hindari `ilike`/`to_char` di query yang kena test.
3. **Jangan cache model Eloquent** (korup saat unserialize). Cache skalar/array saja.
4. **Tabel di Blade** pakai `.table-wrapper`.
5. **Commit kecil + pesan jelas.** Jangan commit kecuali diminta. Setiap commit perbaikan
   menyertakan test yang membuktikannya.
6. **Metodologi verifikasi (pelajaran dari laporan palsu):** test yang assertion-nya merepresentasikan
   perilaku BENAR sementara kode masih salah → saat ini merah (bukti baseline) → setelah diperbaiki
   hijau (regression guard). Dilarang menandai item "selesai" tanpa eksekusi.
7. **Jangan jalankan `wipeAll` pada database produksi** selama cakupan backup belum terbukti lengkap
   (tabel CBT/tryout/TKA belum masuk registry).
8. **Kredensial Dapodik lama** yang pernah ada di riwayat git: cabut/rotasi sebelum fitur sync
   diaktifkan kembali; hapus dari HEAD ≠ hilang dari riwayat.

---

*Dokumen ini dibuat 17 Okt 2026 dari verifikasi langsung kode. Perbarui tabel §2 setiap kali satu
item selesai diperbaiki — ganti status RUSAK → DIPERBAKI + tambahkan baris bukti test.*
