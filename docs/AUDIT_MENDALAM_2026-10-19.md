# 🔍 AUDIT MENDALAM SIMANTAP — Pasca-Sinkronisasi GitHub
**Tanggal:** 19 Oktober 2026
**Cakupan:** Seluruh sisi (arsitektur, integritas data, keamanan, pengujian, performa, operasi)
**Basis:** Sinkronisasi `origin/main` (merge `ffb86bc`) + perbaikan (push `4fcbef1`) + knowledge graph (3.138 node / 5.723 edge / 400 komunitas / **0 siklus import**)
**Status suite:** ✅ **178 test, 580 assertion, semua hijau**

> Dokumen ini fokus pada temuan **baru** yang terungkap saat sinkronisasi & audit teknis.
> Rekomendasi umum lintas aspek yang sudah terdokumentasi di `AUDIT_MENDALAM_SIMANTAP.md`
> (CI/CD, monitoring, backup, dokumentasi API, dst.) tetap berlaku dan tidak diulang di sini.

---

## 1. RINGKASAN EKSEKUTIF

Sinkronisasi mengungkap bahwa proyek membawa **dua implementasi CBT yang bertabrakan**:
versi lama (`App\Models\Cbt\*` → tabel yang sudah tidak ada) dan versi baru dari GitHub
(`App\Models\Cbt*` → skema `2026_10_18`). Perbedaan inilah sumber utama 500-an yang muncul
saat suite dijalankan. **Semua telah diperbaiki dan di-push.**

| Sisi | Temuan kunci | Status |
|------|--------------|--------|
| **Integritas & CBT** | Dual implementasi CBT; kode mati menunjuk tabel hilang | ✅ Diperbaiki (cruft dihapus) |
| **Data (date-cast)** | `date` cast → `Y-m-d H:i:s` di SQLite; pencocokan persis meleset | ✅ Diperbaiki |
| **Rute/View** | Rute ke method tak ada; view akses null & rute tak terdaftar | ✅ Diperbaiki |
| **Arsitektur** | Bersih, 0 siklus import; god-node wajar | ✅ Sehat |
| **Keamanan** | `User.peran` di `$fillable` (latensi), tanpa mass-assign aktif | 🟡 Perlu hardening |
| **Migrasi** | `2026_10_18` tanpa guard `hasTable` | ✅ Diperbaiki (guard ditambahkan) |
| **Pengujian** | 178 test hijau; cakupan masih sebagian besar rute/CRUD | 🟡 Perlu luas |

**Skor kesehatan pasca-sync: 🟢 88%** (naik dari kondisi merge yang merah 196 test / 24 gagal).

---

## 2. APA YANG SUDAH DIPERBAIKI (ter-push `4fcbef1`)

Enam commit logis, suite hijau sebelum push:

1. **`6912c9c` — Hapus sisa CBT lama:** `AuditCbtProofTest` (18 test skema terhapus),
   `cbt/student/take.blade.php`, `resources/js/cbt/*.js` (tak dirender route mana pun).
2. **`5bfce15` — Dashboard & sidebar:** guard rute CBT (`config` + `Route::has`) dan port
   query ke model CBT baru; perbaiki 500 guru/siswa/kepsek/ortu saat modul CBT mati.
3. **`041006b` — Presensi:** pencocokan tanggal via `whereDate` (lihat §4) + assertion
   test DB-agnostik.
4. **`20115de` — Admin CBT:** port controller & view ke skema baru; buat view
   `banks/show`, `banks/edit`, `sessions/show` yang hilang; transisi status via
   `CbtExamService`; ekspor CSV hasil.
5. **`4fcbef1` — Sisa 5xx route-health:** rute TKA eksplisit (lihat §3.3), tautan TKA admin
   ke rute `history` yang ada, null-guard `laporan/pdf`, hapus rute `toggle-status` admin CBT.

**Baseline → selesai:** 196 test (172 lulus / 7 gagal / 17 error) → **178 test hijau**
(selisih 18 = test CBT lama yang sengaja dihapus).

---

## 3. TEMUAN BARU YANG PERLU DIPERBAIKI

### 3.1 🔴 Kode CBT mati menunjuk tabel yang tidak ada (CRUFT)
Tiga file masih ter-registrasi tetapi menunjuk skema lama yang **tabelnya tidak pernah
diciptakan migrasi mana pun** → 500 bila dipanggil:

| File | Menunjuk | Nasib tabel |
|------|----------|-------------|
| `app/Http/Middleware/CbtSecurityMiddleware.php` | `cbt_test_results`, `cbt_integrity_logs` | ❌ tidak ada |
| `app/Http/Middleware/CbtSessionLock.php` | `cbt_test_results`, `cbt_integrity_logs` | ❌ tidak ada |
| `app/Services/CbtSubmitService.php` | `cbt_test_results`, `cbt_student_answers` | ❌ tidak ada |

- `bootstrap/app.php:20-21` masih me-**alias** `cbt.security` & `cbt.session.lock`
  (tapi **tidak dipakai route mana pun** → latensi, bukan bug aktif).
- **7 model lama** `app/Models/Cbt/{QuestionBank,Question,TestSession,QuestionOption,StudentAnswer,TestResult,IntegrityLog}.php`
  masih ada. Tiga di antaranya (`QuestionBank`,`Question`,`TestSession`) **berbagi nama tabel**
  dengan model baru tetapi mengharapkan skema lama → akan error kolom jika kode lama dijalankan.
- ✅ **Verifikasi:** tidak ada kode aktif di luar file mati yang memakai namespace `App\Models\Cbt\`.

**Rekomendasi (aman, jalur struktur):** hapus 3 file mati + 7 model lama + 2 alias di
`bootstrap/app.php`. Jalankan suite (hijau) → commit.

> ✅ **SUDAH DIEKSEKUSI (19 Okt 2026):** `CbtSecurityMiddleware.php`, `CbtSessionLock.php`,
> `CbtSubmitService.php`, seluruh `app/Models/Cbt/*` (7 model), dan alias
> `cbt.security`/`cbt.session.lock` di `bootstrap/app.php` **dihapus**. Tidak ada referensi
> menggantung; suite **178/178 hijau**.

### 3.2 🔴 Migrasi CBT tanpa guard `hasTable`
`database/migrations/2026_10_18_000001_create_cbt_tables.php` memanggil `Schema::create(...)`
**tanpa pemeriksaan `Schema::hasTable()`** di `up()` (hanya `dropIfExists` saat rollback).
- **Risiko:** pada DB produksi yang sudah punya tabel `cbt_question_banks`/`cbt_questions`/
  `cbt_test_sessions` dari instalasi CBT lama, migrasi **gagal** ("table already exists").
- **Rekomendasi:** bungkus tiap `Schema::create` dengan `if (!Schema::hasTable(...))`, atau
  pastikan migrasi lama sudah men-drop tabel lama terlebih dulu.

> ✅ **SUDAH DIEKSEKUSI (19 Okt 2026):** kelima `Schema::create` kini dibungkus guard
> `if (!Schema::hasTable(...))`. Catatan: bila DB lama berisi tabel CBT **berskema lama**
> (bukan baru), guard melewati pembuatan → kolom baru bisa belum ada; tabel lama semacam ini
> perlu migrasi manual sekali (data CBT lama sudah terdeprekasi).

### 3.3 🟠 Rute/resource over-registered (pola yang sama bisa berulang)
- `Route::resource('assessments', TkaAssessmentController)` mendaftarkan `edit/update/destroy`
  padahal controller hanya punya `index/create/store/show` → `BadMethodCallException` (500)
  saat dipanggil. **Sudah diganti rute eksplisit** di `4fcbef1`, tetapi **audit sisa `Route::resource`**
  yang method-nya tidak lengkap di seluruh `routes/web.php` perlu dilakukan (pola ini rawan berulang).
- `admin.tka.assessments.index` view sempat memanggil `route('admin.tka.assessments.show'/'edit')`
  yang tidak ada → 500 **hanya saat ada data** (lolos di test karena tabel kosong).
  **Pelajaran:** endpoint yang hanya me-render link di dalam `@forelse` lolos saat kosong —
  perlu test dengan data terisi.

### 3.4 🟠 Bug yang tertangkap `RouteHealthAuditTest` (bukti nilai test rute)
- `guru/laporan/pdf`: `collect($dataLaporan)->first()['nilai_per_mapel']` → **akses offset array
  pada `null`** saat tidak ada siswa → 500. Sudah di-null-guard. Kelas bug serupa (`first()[...]`)
  bisa masih tersebar — **grep pola `first()[` / `->first()?[`** disarankan.

---

## 4. INTEGRITAS DATA: JEBAKAN DATE-CAST (akar masalah presensi)

- Model dengan `casts: ['tanggal' => 'date']` menyerialkan `Y-m-d H:i:s` di **SQLite**
  (uji), bukan `Y-m-d`. Pencocokan string persis (`firstOrNew(['tanggal' => $today])`)
  meleset → **INSERT ganda / pelanggaran UNIQUE**. Di PostgreSQL produksi hal ini tersamar
  karena koersi DATE.
- **Pola perbaikan (konsisten):** `Model::whereDate('tanggal', $today)->first() ?? new Model(...)`
  — dipakai pada `KehadiranController`, `Admin\PresensiController`, `Pegawai\PresensiController`.
- **Test kini DB-agnostik** (lolos SQLite & PostgreSQL).
- ⚠️ **Waspada berulang:** grep `firstOrNew([` / `updateOrCreate([` dengan nilai tanggal
  yang di-cast `'date'`/`'datetime'` di seluruh model — bisa masih ada di modul lain.

---

## 5. KEAMANAN (jalur Opencode)

**Posisi kuat (dari audit umum, tetap berlaku):** hashing bcrypt, RBAC + middleware peran,
force-password-change, rate limiting login, CSRF, API key auth, hidden field pada User.

**Temuan baru / hardening:**
1. 🟡 **`User.peran` ada di `$fillable`** (`app/Models/User.php:22`). **Telah diverifikasi dan
   ternyata AMAN — bukan lubang:**
   - Tidak ada `User::create/update($request->all())` / mass-assign dari input mentah (dicek).
   - Registrasi publik `LandingController` memvalidasi `'peran' => 'required|in:guru,siswa,ortu'`
     (**tidak bisa** memilih admin) dan membuat akun `aktif => false` (nonaktif sampai aktivasi admin).
   - `Admin\UserController` (di belakang `role:admin`) & `DapodikSyncController` mengoper
     `'peran'` dari data tervalidasi/sistem.
   - Update profil hanya `auth()->user()->update(['kelas_mata_pelajaran' => ...])` (whitelist).
   - ⚠️ **`peran` TIDAK boleh dihapus dari `$fillable`** — tiga `User::create` sah (registrasi,
     buat-user admin, sync Dapodik) mengoper `peran` via mass-assignment; menghapusnya akan
     **merusak** ketiganya. Tidak ada perubahan kode yang dilakukan di sini (sudah tepat).
   **Rekomendasi lanjutan (opsional, defense-in-depth):** bila suatu saat ada endpoint baru yang
   menerima input user, pastikan selalu pakai whitelist validasi — jangan pass `$request->all()`.
2. 🟡 Pastikan tidak ada `Model::unguard()` global / `Model::preventSilentlyDiscardingAttributes`
   yang dimatikan tanpa syarat.
3. 🟡 (lanjutan audit umum) pen tes OWASP, scan dependensi, kebijakan data siswa.

---

## 6. PENGUJIAN

- ✅ **178 test / 580 assertion hijau.** `RouteHealthAuditTest` memukul 985 kombinasi endpoint
  GET dan terbukti menangkap 500 nyata (laporan pdf, TKA) — **aset berharga, pertahankan.**
- 🟡 Cakupan masih condong ke rute/CRUD; **logika bisnis kritis** (kalkulasi `Nilai`,
  `NilaiErapot`, penilaian `Kuis`/CBT, rekap Kehadiran) perlu unit test eksplisit.
- 🟡 **Test rute dengan data terisi** untuk view yang me-render link di `@forelse`
  (lihat §3.3) — mode kosong menyembunyikan error route-name.
- 🟡 Test integrasi Dapodik sync, otorisasi antar-peran (negative test), error handling API.

---

## 7. PERFORMA

- 🟡 **God-node `Controller` (deg 164) & `Siswa` (deg 123)** wajar untuk Laravel, tapi
  menandakan banyak titik sentuh pusat → pastikan query relasi di dashboard/list **eager-load**
  (`withCount`/`with`) untuk hindari N+1.
- 🟡 Kontrol `forelse` besar (nilai, laporan) perlu pagination + index pada FK & kolom
  yang sering difilter (`tanggal`, `guru_id`, `siswa_id`, `status`).
- 🟡 PDF e-Rapor/laporan berat → pertimbangkan queue bila memblokir request.
- 🟡 Strategi cache (Redis) untuk data akademik yang jarang berubah (referensi, rombel, mapel).

---

## 8. PETA JALAN PRIORITAS (output perbaikan)

### 🔴 KRITIS — struktur & keamanan (aman, segera)
1. ✅ **Bersihkan cruft CBT lama:** hapus `CbtSecurityMiddleware`, `CbtSessionLock`,
   `CbtSubmitService`, 7 model `app/Models/Cbt\*`, 2 alias `bootstrap/app.php`
   → suite hijau → commit. *(Selesai — 19 Okt 2026)*
2. ✅ **Guard migrasi CBT:** tambah `if (!Schema::hasTable(...))` pada `2026_10_18_000001`.
   *(Selesai — 19 Okt 2026)*
3. ✅ **Verifikasi `User.peran`:** ternyata aman & **wajib tetap di `$fillable`**
   (3 `User::create` sah mengopernya; menghapus malah merusak). Tanpa perubahan kode. *(Selesai)*

### 🟠 TINGGI — keandalan data & rute
4. **Audit massal pola date-cast** (`firstOrNew`/`updateOrCreate` + kolom tanggal) → pakai `whereDate`.
5. **Audit `Route::resource`** yang method controller-nya tidak lengkap (pola §3.3).
6. **Grep `first()[`** untuk akses offset pada `null` di seluruh view/controller.
7. **Test dengan data terisi** untuk view `@forelse` yang me-render `route()`.

### 🟡 SEDANG — mutu
8. Unit test logika bisnis (Nilai, Erapor, Kuis, penilaian CBT, rekap kehadiran).
9. Eager-loading + pagination + index untuk query dashboard/list berat.
10. Perluas test RouteHealth ke endpoint POST/PUT penting (transisi status CBT, submit ujian).

### 🟢 RENDAH — siklus lanjut (dari audit umum)
11. CI/CD (GitHub Actions: phpunit + php -l), Docker, monitoring, backup terjadwal,
    dokumentasi API (OpenAPI), hardening OWASP menyeluruh.

---

## 9. LAMPIRAN: META-ARsitektUR (knowledge graph)

- **3.138 node, 5.723 edge, 400 komunitas, 0 siklus import** → struktur modul bersih,
  tidak ada circular dependency antar-namespace.
- **God nodes:** `Controller`(164), `Siswa`(123), `User`(76), `TestCase`(55),
  `DapodikSyncService`(46) — pusat wajar; kandidat untuk eager-load audit.
- **Surprising connections:** hampir semua `*Test` → model (jembatan antar-komunitas
  yang **diharapkan**) — tidak ada ketergaitan arsitektur aneh yang mencurigakan.
- **Komunitas teratas** didominasi base-class framework (`Blueprint`, `Model`, `TestCase`,
  `Controller`) + hub domain (`JadwalPelajaranResolver`, `getKKM`, `Kuis`) → modul akademik
  (Jadwal, Nilai, Kuis) adalah inti kohesif, sesuai ekspektasi.
- Graph tersimpan di `graphify-out/graph.json` + analisis `.graphify_analysis.json`.

---

**Audit selesai:** 19 Oktober 2026 · **Suite:** 178/178 hijau · **Ter-push:** `4fcbef1` (origin/main)
