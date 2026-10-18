# Bukti Verifikasi CBT — 18 Oktober 2026

## Ringkasan Eksekutif & Klasifikasi Pengujian

Dokumen ini memisahkan secara tegas hasil verifikasi berdasarkan jenis pengujian yang benar-benar dijalankan pada 18 Oktober 2026:

| Kategori Pengujian | Lingkungan / Alat | Status | Hasil Terukur |
|---|---|---|---|
| **1. Pemeriksaan Kontrak Statis** | Python 3.11 (`tools/verifikasi-cbt.py`, `tools/verifikasi-perbaikan.php`) | **DIEKSEKUSI** | **16/16 LULUS** (CBT) & **39/39 LULUS** (Regresi) |
| **2. Lint Sintaks PHP Native** | PHP 8.4.11 CLI (`php -l`) | **DIEKSEKUSI** | **18/18 berkas CBT & integrasi LULUS** (`No syntax errors`) |
| **3. Logika Murni & JS DOM Tiruan** | Node v22.22.3 (`tools/cbt-browser-check.mjs`) | **DIEKSEKUSI** | **8/8 LULUS** |
| **4. PHPUnit (Unit & Feature CBT)** | PHP 8.4.11 + Laravel 13.31.0 (SQLite & PostgreSQL 18.4 `simantap_test`) | **DIEKSEKUSI** | **35/35 LULUS** (`118 assertions`) di SQLite & PostgreSQL |
| **5. Konkurensi Paralel & Presisi Deadline** | PostgreSQL 18.4 (`simantap_test`) + pekerja paralel simultan (`proc_open`) | **DIEKSEKUSI** | **5/5 skenario LULUS** (`0 lost update`, `0 deadlock`) |
| **6. Browser End-to-End & Isolasi** | Playwright + Headless Chromium 153.0.0 + PostgreSQL 18.4 | **DIEKSEKUSI** | **10/10 skenario E2E & isolasi LULUS** |
| **7. Export–Wipe–Restore Staging** | PostgreSQL 18.4 (`simantap_staging`) | **DIEKSEKUSI** | **LULUS 100%** (ID, FK, snapshot, kunci, nilai, timestamp, sequence utuh) |

> **Catatan Kejujuran Operasional:** Seluruh pengujian di atas dieksekusi di lingkungan non-produksi terisolasi (`simantap_test` dan `simantap_staging`). Feature flag `SIMANTAP_CBT_ENABLED` tetap **`false` secara default** di `config/simantap.php` dan `.env.example` sampai operator menjalankan migrasi dan mengaktifkannya secara sadar di server target.

---

## 1. Temuan Audit & Bukti Sebelum → Sesudah Perbaikan

Selama verifikasi integrasi nyata dengan PHP 8.4, PostgreSQL 18.4, dan Headless Chromium, ditemukan **5 bug nyata** yang telah diperbaiki dan dibuktikan sebelum–sesudah:

### Temuan 1: Isolasi Environment Flag antara `CbtDisabledTest` dan `CbtExamTest`
- **Akar Masalah:** `CbtDisabledTest::refreshApplication()` memicu `LoadEnvironmentVariables` (`Dotenv::createImmutable()->safeLoad()`) yang mengisi `Illuminate\Support\Env::$repository` (`ImmutableWriter`) dengan nilai dari `.env` (`SIMANTAP_CBT_ENABLED=false`). Saat `CbtExamTest::setUp()` memanggil `putenv('SIMANTAP_CBT_ENABLED=true')` tanpa me-reset repository `Env`, `Dotenv` pada test berikutnya menimpa kembali `$_ENV`/`$_SERVER` menjadi `'false'`.
- **SEBELUM:**
  ```text
  $ php artisan test --filter=Cbt
  Tests: 24 failed, 8 passed (RouteNotFoundException: Route [cbt.student.start] not defined)
  ```
- **Perbaikan:** Memanggil `\Illuminate\Support\Env::enablePutenv()` saat menyetel dan memulihkan `SIMANTAP_CBT_ENABLED` di `tests/Feature/CbtDisabledTest.php` dan `tests/Feature/CbtExamTest.php`.
- **SESUDAH:**
  ```text
  $ php artisan test --filter=Cbt
  Tests: 35 passed (118 assertions)
  ```

### Temuan 2: Pergeseran Jadwal/Timestamp 7 Jam (UTC vs WIB) & Desinkronisasi Sequence PostgreSQL saat Backup–Restore
- **Akar Masalah:**
  1. Model CBT (`CbtQuestionBank`, `CbtQuestion`, `CbtTestSession`, `CbtResult`, `CbtViolation`) mengekspor kolom `datetime` lewat `toArray()` dalam format ISO-8601 UTC (`2026-10-18T01:00:00.000000Z` untuk pukul `08:00:00` WIB). Saat `BackupController::restoreTable()` memulihkan data melalui `updateOrCreate()`, Eloquent `DateTime::format('Y-m-d H:i:s')` mengambil jam UTC mentah (`01:00:00`) tanpa mengonversi ke `config('app.timezone')` (`Asia/Jakarta`), sehingga jadwal `opens_at`, `closes_at`, `started_at`, `deadline_at`, dan `submitted_at` bergeser mundur **7 jam**.
  2. Di PostgreSQL, pemulihan dengan ID eksplisit setelah `TRUNCATE ... RESTART IDENTITY CASCADE` tidak memajukan sequence serial (`*_id_seq`), sehingga insert record baru pasca-restore gagal dengan `Unique violation`.
- **SEBELUM (PostgreSQL `simantap_staging`):**
  ```text
  Before export opens_at: 2026-10-18 08:00:00
  Serialized opens_at:    2026-10-18T01:00:00.000000Z
  After restore opens_at: 2026-10-18 01:00:00
  INSERT Error after restore: SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "cbt_question_banks_pkey" DETAIL: Key (id)=(1) already exists.
  ```
- **Perbaikan:** Menambahkan `serializeDate(\DateTimeInterface $date)` pada kelima model CBT agar mengekspor tanggal dalam timezone aplikasi (`Y-m-d H:i:s`), serta menyinkronkan sequence PostgreSQL (`pg_get_serial_sequence` + `setval`) di akhir `BackupController::restoreTable()`.
- **SESUDAH (PostgreSQL `simantap_staging`):**
  ```text
  Before export opens_at: 2026-10-18 08:00:00
  After restore opens_at: 2026-10-18 08:00:00
  New bank created after restore with ID: 2
  ```

### Temuan 3: Kegagalan Restore FK `cbt_results_siswa_id_foreign` pada ID Non-Urut Pasca-Wipe
- **Akar Masalah:** `BackupController::restoreTable()` memanggil `$modelClass::updateOrCreate(['id' => $id], $row)` dalam keadaan guarded. Untuk model yang tidak mencantumkan `'id'` di `$fillable` (seperti `Siswa`), Eloquent membuang `'id'` saat membuat baris baru pasca-wipe (`RESTART IDENTITY`), sehingga siswa yang di backup ber-ID `2` di-insert menjadi ID `1` dan mematahkan FK `cbt_results.siswa_id = 2`.
- **SEBELUM (PostgreSQL `simantap_staging`):**
  ```text
  SEBELUM export - Siswa ID: 2
  SEBELUM fix - Flash error: Restore gagal, tidak ada data yang diubah. Periksa format file backup.
  (Log: SQLSTATE[23503]: Foreign key violation: Key (siswa_id)=(2) is not present in table "siswa")
  SEBELUM fix - CbtResult count after restore: 0
  ```
- **Perbaikan:** Membungkus pemulihan model di `BackupController::restoreTable()` dengan `Model::unguarded(...)` (setelah `$row` difilter ketat terhadap `Schema::getColumnListing($table)`).
- **SESUDAH (PostgreSQL `simantap_staging`):**
  ```text
  SESUDAH export - Siswa ID: 2
  SESUDAH fix - Flash success: Data berhasil dipulihkan dari backup.
  SESUDAH fix - Restored Siswa ID: 2
  SESUDAH fix - CbtResult count after restore: 1 (siswa_id=2)
  ```

### Temuan 4: Penundaan Autosave saat Perubahan Opsi Cepat Terjadi Ketika `fetch` Sebelumnya Masih Berjalan
- **Akar Masalah:** Di `resources/views/cbt/student/exam.blade.php`, `savePending()` hanya mengiterasi `Array.from(pending)` satu kali per pemanggilan. Jika siswa mengubah jawaban soal yang sama dari `'A'` ke `'D'` saat `fetch` untuk `'A'` masih in-flight, panggilan `savePending()` kedua langsung keluar karena `saving === true`, dan setelah `fetch` pertama selesai loop `for` berhenti tanpa mengirim `'D'` sampai `setInterval` 8 detik berikutnya. Jika siswa me-refresh halaman dalam jeda tersebut, jawaban terakhir hilang.
- **SEBELUM:**
  ```text
  SEBELUM - Jumlah fetch setelah request 1 selesai (tanpa menunggu 8 detik): 1
  Status: Masih ada jawaban menunggu simpan.
  ```
- **Perbaikan:** Mengubah loop di `savePending()` menjadi `while (pending.size && !expired && !submitting)` sehingga perubahan baru yang masuk selama `fetch` sebelumnya berjalan langsung dikirim begitu `fetch` sebelumnya selesai.
- **SESUDAH:**
  ```text
  SESUDAH - Jumlah fetch setelah request 1 selesai (tanpa menunggu 8 detik): 2
  Jawaban terakhir: D
  Status: Semua perubahan jawaban tersimpan di server.
  ```

### Temuan 5: Akses `GET /cbt/student/{session}/start` Setelah Deadline & Validasi Kelayakan/Urutan Lock pada `logViolation`
- **Akar Masalah:**
  1. `CbtStudentController::start()` hanya memeriksa `if ($result->submitted_at)` tanpa menyinkronkan percobaan yang sudah melewati `deadline_at` atau sesi yang sudah `completed`, sehingga siswa masih disuguhi halaman ujian yang sudah kedaluwarsa.
  2. `CbtIntegrityController::logViolation()` belum memeriksa `CbtExamService::eligible($session, $student)` (misal siswa sudah dipindahkan ke guru/kelas lain), belum memeriksa `$session->status === 'running'`, dan mengunci `CbtResult` tanpa mengunci `CbtTestSession` terlebih dahulu.
  3. `resources/views/cbt/student/start.blade.php` memakai `now()->between($session->opens_at, $session->closes_at)` (inklusif pada `closes_at`), sedangkan `CbtExamService::begin()` memakai batas eksklusif `now()->greaterThanOrEqualTo($session->closes_at)`.
- **SEBELUM:**
  ```text
  SEBELUM - GET start setelah deadline mengembalikan: View cbt.student.exam
  SEBELUM - logViolation saat siswa sudah pindah guru: HTTP 200
  ```
- **Perbaikan:** Menambahkan `CbtExamService::syncResult()` pada `CbtStudentController::start()` dan `result()`, memindahkan pencatatan integritas ke `CbtExamService::recordViolation()` dengan pengecekan `eligible()` dan urutan lock `sesi -> hasil`, serta menyamakan batas waktu di `start.blade.php`.
- **SESUDAH:**
  ```text
  SESUDAH - GET start setelah deadline mengembalikan: Illuminate\Http\RedirectResponse -> submitted_at: FINALIZED
  SESUDAH - logViolation saat siswa sudah pindah guru: HTTP 403
  ```

---

## 2. Hasil PHPUnit (SQLite & PostgreSQL 18.4)

Perintah yang dijalankan:

```bash
# SQLite in-memory
php artisan test --filter=Cbt

# PostgreSQL 18.4 (database khusus pengujian: simantap_test)
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=simantap_test DB_USERNAME=postgres DB_PASSWORD="" php artisan test --filter=Cbt
```

Hasil pada kedua mesin database:
- `Tests\Unit\CbtScoringTest`: **6 passed**
- `Tests\Feature\CbtDisabledTest`: **1 passed**
- `Tests\Feature\CbtExamTest`: **28 passed**
- **Total CBT Suite:** **35 passed (118 assertions)**, **0 failed**.

Suite penuh (`php artisan test`):
- **149 passed (475 assertions)**, termasuk seluruh 35 tes CBT, `AlignmentCheckTest`, `BackupTablesTest`, `AuthSecurityTest`, `PredikatConsistencyTest`, dan `ProfileTest`.
- Terdapat **2 kegagalan pre-existing di luar modul CBT** (warisan kode lama sebelum CBT):
  1. `Tests\Feature\InputNilaiJenisTest`: `InputNilaiController::simpanNilai()` belum menyertakan kolom `nama_guru` yang wajib `NOT NULL` pada tabel `nilai`.
  2. `Tests\Feature\MenuAuditTest`: menulis laporan ke path lokal Windows hardcoded `C:/Users/USER/AppData/Local/Temp/opencode/menu_audit.txt` (meskipun array `$errors` dari audit seluruh rute menu bernilai kosong `[]`).

---

## 3. Hasil Uji Konkurensi Paralel & Presisi Deadline di PostgreSQL 18.4

Pengujian dijalankan terhadap PostgreSQL 18.4 (`simantap_test`) menggunakan pekerja proses PHP paralel simultan (`proc_open`):

1. **4 Request Mulai (`begin`) Paralel Simultan:** Menghasilkan tepat **1 baris `cbt_results`** (`id=1`) dan `deadline_at` identik di keempat proses berkat row lock `SELECT ... FOR UPDATE` pada `cbt_test_sessions` dan constraint unik `(session_id, siswa_id)`.
2. **10 Request Autosave (`answer`) Paralel Simultan (Soal 1–10):** Seluruh **10/10 jawaban tersimpan utuh** di JSON `cbt_results.answers` tanpa satu pun jawaban hilang (*0 lost update*) berkat serialisasi lock `sesi -> hasil`.
3. **4 Request Submit Paralel Simultan:** Idempoten; skor akhir tetap **`80.0`** dan `submitted_at` tidak berubah pada submit kedua–keempat.
4. **Balapan (`Race`) Penutupan Sesi Guru (`complete`) vs Autosave/Submit Siswa:** **0 deadlock** karena keduanya mengunci `cbt_test_sessions` terlebih dahulu sebelum `cbt_results`; sesi berakhir `completed`, hasil dinilai satu kali, dan autosave pasca-tutup ditolak (`false` / HTTP `409`).
5. **Presisi Deadline Server (`min(started_at + duration, closes_at)`):**
   - Pada `deadline_at - 1 detik`: jawaban diterima (`true`).
   - Tepat pada `deadline_at` (`now() == deadline_at`): jawaban baru ditolak (`false`), hasil langsung difinalisasi dari jawaban sebelum deadline.
   - Pada `deadline_at + 1 detik`: jawaban baru dan payload submit terlambat tetap ditolak/diabaikan.

---

## 4. Hasil Uji Browser Nyata (Playwright + Headless Chromium 153 + PostgreSQL 18.4)

Pengujian dijalankan menggunakan browser **Headless Chromium 153.0.0** terhadap server aplikasi Laravel dengan database PostgreSQL `simantap_test`:

1. **Login Guru A & Siklus Penuh Pembuatan Ujian:** Guru A login via `/login`, membuat bank soal, menambahkan 2 soal pilihan ganda (termasuk soal dengan tag `<script>window.__xss=1</script>` dan `<img src=x onerror="window.__xss=2">`), membuat sesi kelas `6A`, mempublikasikan snapshot, dan membuka sesi. (**LULUS**)
2. **Isolasi Guru B di Browser:** Guru B login di konteks browser terpisah dan mencoba membuka `/cbt/question-banks/{id}` serta `/cbt/sessions/{id}/monitoring` milik Guru A → keduanya ditolak **HTTP 403**. (**LULUS**)
3. **Login Siswa, Halaman Petunjuk (`GET`), Mulai (`POST`), Anti-XSS, & Anti-Leak Kunci:**
   - Siswa ditolak **HTTP 403** saat mencoba membuka `/cbt/question-banks`.
   - Membuka halaman petunjuk `GET /cbt/student/{session}/start` tidak membuat baris `CbtResult` (`count = 0`).
   - Setelah klik `"Mulai ujian sekarang"` (`POST`), `window.__xss` tetap `undefined`, tag `<script>` tampil ter-escape (`&lt;script&gt;`), dan DOM/HTML sama sekali tidak memuat `correct_answer` maupun `question_snapshot`. (**LULUS**)
4. **Viewport Mobile (`375x667`):** Elemen `#timer` dan tombol `#submit-exam` tampil proporsional di dalam viewport `375px` tanpa terpotong. (**LULUS**)
5. **Perubahan Opsi Cepat Saat Fetch Masih In-Flight:** Saat request simpan pilihan `'C'` ditahan 250ms, siswa mengubah pilihan ke `'A'` → request kedua (`'A'`) langsung dikirim setelah request pertama selesai tanpa menunggu interval 8 detik, dan status akhir menjadi `"Semua perubahan jawaban tersimpan di server."`. (**LULUS**)
6. **Putus/Sambung Koneksi (Indikator Gagal Simpan & Retry Otomatis):** Saat koneksi diputus (`internetdisconnected`), memilih opsi `'B'` menampilkan `"BELUM tersimpan. Periksa koneksi/login; sistem akan mencoba lagi. Jangan tutup halaman."`; saat event `online` dipicu kembali, jawaban otomatis terkirim ulang hingga berstatus `"Semua perubahan jawaban tersimpan di server."`. (**LULUS**)
7. **Refresh Halaman:** Reload penuh halaman ujian (`page.reload()`) mempertahankan pilihan radio `['A', 'B']` dari state server. (**LULUS**)
8. **Klik Submit Ganda & Halaman Hasil:** Klik ganda simultan pada `#submit-exam` hanya memproses satu pengumpulan dan mengarahkan ke `/cbt/student/{result}/result` dengan nilai **`100.00`**. (**LULUS**)
9. **Timer & Auto-Submit Saat Waktu Habis:** Pada percobaan Siswa 2 dengan sisa waktu 2 detik, countdown mencapai `00:00` dan browser secara otomatis mengumpulkan ujian serta berpindah ke halaman hasil dengan nilai **`50.00`**. (**LULUS**)
10. **Isolasi Antar Siswa & Penolakan Akun Nonaktif Saat Sesi Login Masih Aktif:**
    - Siswa 2 ditolak **HTTP 403** saat mengakses hasil Siswa 1.
    - Ketika status `Siswa` dinonaktifkan (`aktif = false`) saat Siswa 2 masih memiliki sesi login aktif di browser, akses berikutnya ke `/cbt/student` langsung ditolak oleh middleware `cbt.access`. (**LULUS**)

---

## 5. Hasil Uji Export–Wipe–Restore Penuh di Database Staging (`simantap_staging`)

Diuji pada database PostgreSQL terpisah `simantap_staging`:
1. Dibuat data lengkap: `CbtQuestionBank`, 2 `CbtQuestion`, `CbtTestSession` (terpublikasi & berjalan), `CbtResult` (dengan jawaban & skor `100.0`), dan `CbtViolation` (`tab_hidden`).
2. Diekspor melalui `ExportBackupJob` dan diverifikasi pula pada `php artisan simantap:backup`.
3. Dijalankan `BackupController::wipeAll` pada `simantap_staging` → kelima tabel CBT (`cbt_violations`, `cbt_results`, `cbt_test_sessions`, `cbt_questions`, `cbt_question_banks`) bersih (`0` baris), sedangkan tabel `users` tetap utuh.
4. Dipulihkan melalui `BackupController::import` → seluruh ID, relasi FK, `correct_answer`, `question_snapshot`, `participant_ids`, `answers`, `score`, `correct_count`, dan timestamp kembali **100% identik** tanpa pergeseran waktu, serialisasi HTTP tetap menyembunyikan `correct_answer` dan `question_snapshot`, serta sequence PostgreSQL berlanjut normal pada insert berikutnya.
