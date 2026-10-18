# LAPORAN INVESTIGASI SIMANTAP — STATUS IMPLEMENTASI

**Tanggal:** 2026-10-04  
**Status:** Investigasi Lengkap ✅  
**Ruang Lingkup:** CBT, e-Rapor Alignment, Dashboard Peran (Guru/Siswa/Ortu/Admin/Kepsek)  
**Verifikasi:** Sebelum laporan tuntas, baca kesimpulan & rekomendasi prioritas

---

## Ringkasan Eksekutif

SIMANTAP telah mengimplementasikan tiga sistem utama: (1) **CBT backend lengkap** dengan 7 tabel, 6 model, 5 controller, namun **frontend UI belum dibuat**; (2) **e-Rapor alignment selesai** (7 fase), dengan integrasi jadwal pembelajaran via FK, 105 test hijau, siap UAT; (3) **Dashboard untuk semua peran ada**, namun **Ortu dashboard bermasalah struktur** (controller di root, bukan folder dedicated). **Gap terbesar:** CBT tidak terintegrasi jadwal pelajaran (perlu FK), frontend CBT tidak ada, dan Ortu dashboard perlu refactor + enhancement. Rekomendasi: Prioritas 1 adalah refactor Ortu dashboard & tambah alignment health indicator di Admin; Prioritas 2 adalah lanjutkan frontend CBT dan integrasi CBT ↔ e-Rapor.

---

## 1. CBT (Computer-Based Test)

### 1.1 Status Implementasi

**Backend:** ✅ **SELESAI 95%** — Struktur database, model, controller, middleware, routes lengkap  
**Frontend:** ❌ **BELUM DIMULAI** — UI/Views skeleton, JavaScript belum ada  
**Integrasi e-Rapor:** 🟠 **PENDING** — Belum ada FK ke jadwal_pelajaran

#### Evidence:
- **7 Tabel:** `cbt_question_banks`, `cbt_questions`, `cbt_question_options`, `cbt_test_sessions`, `cbt_student_answers`, `cbt_test_results`, `cbt_integrity_logs` — SEMUA EXIST
- **7 Model:** Dengan relasi lengkap (belongsTo, hasMany)
- **5 Controller:** `CbtQuestionBankController`, `CbtQuestionController`, `CbtTestSessionController`, `CbtStudentController`, `CbtIntegrityController`
- **2 Middleware:** `CbtSecurityMiddleware`, `CbtSessionLock`
- **Routes lengkap:** Guru + Siswa + Admin (semuanya protect dengan role middleware)

---

### 1.2 Komponen yang Sudah Ada

#### Backend Structure

| Komponen | File | Status | Detail |
|----------|------|--------|--------|
| **Model QuestionBank** | `app/Models/Cbt/QuestionBank.php` | ✅ Lengkap | Relasi ke MataPelajaran, Semester, Guru, Question, TestSession |
| **Model Question** | `app/Models/Cbt/Question.php` | ✅ Lengkap | Relasi ke QuestionBank, QuestionOption, StudentAnswer |
| **Model QuestionOption** | `app/Models/Cbt/QuestionOption.php` | ✅ Lengkap | Opsi A-E untuk pilihan_ganda |
| **Model TestSession** | `app/Models/Cbt/TestSession.php` | ⚠️ Partial | FK ke QuestionBank, MataPelajaran, Semester, Guru — **MISSING: jadwal_pelajaran_id FK** |
| **Model StudentAnswer** | `app/Models/Cbt/StudentAnswer.php` | ✅ Lengkap | Setiap jawaban siswa ke soal |
| **Model TestResult** | `app/Models/Cbt/TestResult.php` | ✅ Lengkap | Hasil ujian + scoring |
| **Model IntegrityLog** | `app/Models/Cbt/IntegrityLog.php` | ✅ Lengkap | Log anti-cheat (tab switch, copy-paste, etc.) |
| **Controller Guru (QBank)** | `app/Http/Controllers/Cbt/CbtQuestionBankController.php` | ✅ Lengkap | CRUD bank soal |
| **Controller Guru (Question)** | `app/Http/Controllers/Cbt/CbtQuestionController.php` | ✅ Lengkap | CRUD soal (pilihan_ganda, isian_singkat, essay, dll) |
| **Controller Guru (Session)** | `app/Http/Controllers/Cbt/CbtTestSessionController.php` | ✅ Lengkap | Buat sesi, monitor live, lihat hasil |
| **Controller Siswa** | `app/Http/Controllers/Cbt/CbtStudentController.php` | ✅ Lengkap | List ujian, mulai, jawab, submit, lihat hasil |
| **Controller Integrity** | `app/Http/Controllers/Cbt/CbtIntegrityController.php` | ✅ Lengkap | Log violation (-10 integrity score/violation) |
| **Admin Controllers** | `app/Http/Controllers/Admin/Cbt/CbtSessionController.php`, `.../CbtBankController.php` | ✅ Lengkap | Admin cross-teacher management |
| **Service CbtSubmitService** | `app/Services/CbtSubmitService.php` | ✅ Lengkap | Scoring, nilai_akhir, persentase, lulus logic |
| **Middleware Security** | `app/Http/Middleware/CbtSecurityMiddleware.php` | ✅ Lengkap | Validasi waktu & integrity score |
| **Middleware SessionLock** | `app/Http/Middleware/CbtSessionLock.php` | ✅ Lengkap | Validasi IP & User-Agent (anti-cheat) |

#### Data Storage

| Field CBT | Implementasi | Note |
|-----------|--------------|------|
| **Bank Soal:** kode_bank, nama_bank, tingkat_kesulitan, jumlah_soal, aktif | ✅ Lengkap | Auto-generate kode, counter soal |
| **Soal:** tipe_soal (pilihan_ganda, essay, benar_salah, isian_singkat, menjodohkan), media_type (text, image, audio, video) | ✅ Lengkap | Support 5 tipe soal |
| **Sesi Ujian:** kode_sesi, durasi_menit, acak_soal, acak_opsi, tampilkan_nilai, boleh_ulang, passing_grade | ✅ Lengkap | Flexible settings |
| **Hasil Ujian:** nilai_akhir, persentase, lulus, integrity_score | ✅ Lengkap | Full scoring logic |
| **Anti-Cheat:** IP address, user-agent, tab_switch, copy_paste, focus_lost logging | ✅ Lengkap | 6 jenis violation tracked |

#### Routes

| Endpoint | Method | Role | Status |
|----------|--------|------|--------|
| `/cbt/question-banks` | GET/POST/PUT/DELETE | Guru | ✅ |
| `/cbt/questions/{bank}` | POST/PUT/DELETE | Guru | ✅ |
| `/cbt/sessions` | GET/POST/PUT/DELETE | Guru | ✅ |
| `/cbt/sessions/{session}/monitoring` | GET | Guru | ✅ |
| `/cbt/student` | GET | Siswa | ✅ |
| `/cbt/student/{session}/start` | GET | Siswa | ✅ |
| `/cbt/student/{result}/answer` | POST | Siswa | ✅ |
| `/cbt/student/{result}/submit` | POST | Siswa | ✅ |
| `/cbt/admin/cbt/banks` | GET/POST/PUT/DELETE | Admin | ✅ |
| `/cbt/admin/cbt/sessions` | GET/POST/PUT/DELETE | Admin | ✅ |

---

### 1.3 GAP dan Issue

#### 🔴 Critical Issues

| Issue | Impact | File/Method | Solution |
|-------|--------|-------------|----------|
| **Missing UI Views (12 files)** | Aplikasi tidak bisa diakses via browser | `resources/views/cbt/` | Create blade files: student/take.blade.php (exam interface), student/result.blade.php (results), sessions/monitoring.blade.php (live dashboard), detail & edit views |
| **No JavaScript Files** | Timer, integrity check, real-time answer saver tidak berfungsi | `resources/js/cbt/` | Create: timer.js, integrity-checker.js, answer-saver.js, event-monitor.js |
| **No Student Exam UI** | Siswa tidak bisa lihat soal & menjawab | `cbt/student/take.blade.php` | Priority: Exam interface dengan soal navigator, timer, jawab per soal, ragu-ragu flag |
| **No Orang Tua Integration** | Ortu tidak bisa lihat hasil CBT anak | `Ortu\DashboardController` | Extend dashboard ortu dengan CBT results card |

#### 🟠 High Priority Issues

| Issue | Impact | File/Method | Solution |
|-------|--------|-------------|----------|
| **CBT ↔ Jadwal Pelajaran Not Linked** | Nilai CBT tidak bisa mapping otomatis ke e-Rapor | `cbt_test_sessions` table | Add `jadwal_pelajaran_id` FK (nullable), update populate logic di migration, invoke `JadwalPelajaranResolver` di `CbtSubmitService` |
| **Live Monitoring Dashboard** | Guru tidak bisa real-time monitor siswa mengerjakan | `CbtTestSessionController::monitoring()` | Create `cbt/sessions/monitoring.blade.php` + WebSocket untuk update real-time siswa aktif, soal sedang dikerjakan |
| **No Unit Tests** | Backend reliability belum terverifikasi | PHPUnit in `tests/` | Add feature tests untuk controller + unit tests untuk service (scoring logic) |
| **Export Results** | Admin/Guru tidak bisa export hasil CBT ke Excel/PDF | `CbtSessionController::exportResults()` | Implement Excel export (Maatwebsite/Laravel-Excel) + PDF template |
| **No Bulk Import Soal** | Guru harus manual input 50+ soal satu-satu | `CbtQuestionController::import()` | Support Excel/CSV import untuk soal massal |

#### 🟡 Medium Priority Issues

| Issue | Impact | File/Method | Solution |
|-------|--------|-------------|----------|
| **Pagination on API** | Large result set bisa slow di monitoring | `CbtTestSessionController::apiMonitoring()` | Add limit/offset pagination |
| **Error Handling & Validation** | User experience saat error | Controllers | Improve error messages, validation messages, exception handling |
| **Essay Auto-Grading Not Implemented** | Soal essay harus manual score | `StudentAnswer` model | Placeholder untuk future: AI-assisted scoring atau rubric-based scoring |
| **No Question Bank Sharing** | Guru tidak bisa share soal dengan guru lain | Model relation | Design permission model untuk shared banks |

---

### 1.4 Prioritas Perbaikan

#### Sprint 1 (Week 1-2): Critical Frontend & Integration
1. **Create exam UI** — `cbt/student/take.blade.php` dengan soal navigator, timer, jawaban per soal
2. **Create results UI** — `cbt/student/result.blade.php` dengan score breakdown
3. **Create monitoring dashboard** — `cbt/sessions/monitoring.blade.php` untuk live guru
4. **Add CBT ↔ jadwal integration** — FK migration + JadwalPelajaranResolver invoke di CbtSubmitService
5. **Create student answer saver JS** — Auto-save jawaban per soal (debounced 2 detik)

#### Sprint 2 (Week 3-4): Testing & Polish
6. Add feature tests (CbtStudentController, CbtSubmitService scoring)
7. Add Ortu dashboard card untuk lihat hasil CBT anak
8. Create results export (Excel template)
9. Enhance error handling & validation messages

#### Sprint 3+ (Nice-to-have)
10. Live monitoring WebSocket
11. Bulk import soal Excel
12. Question bank sharing
13. Essay rubric-based scoring

---

## 2. e-Rapor Alignment

### 2.1 Status Implementasi

**Alignment Teknis:** ✅ **SELESAI 100%** — 7 fase dokumentasi + implementation lengkap  
**Database Migration:** ✅ **DONE** — FK `jadwal_pelajaran_id` di `nilai_erapor`, populated  
**Service Logic:** ✅ **DONE** — `JadwalPelajaranResolver` selesai, 105 tests hijau  
**Admin UI:** ✅ **DONE** — Halaman `alignment-check.blade.php` terender  
**UAT:** 🟡 **PENDING** — Perlu testing di staging dengan sample Dapodik + nilai

#### Evidence:
- **Dokumentasi Fase 1-7:** `docs/09-ERAPOR-ALIGNMENT-PLAN.md` (rencana), `10-ALIGNMENT-DESIGN.md` (design), `11-ALIGNMENT-GUIDE.md` (operasional), `12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md` (deployment)
- **Migration selesai:** 2 file (add FK + populate)
- **Model relationship:** `NilaiErapot` ↔ `JadwalPelajaran` (belongsTo, relasi 2-arah)
- **Resolver service:** `JadwalPelajaranResolver::resolve()` — matcher teks ke FK
- **Test coverage:** 105/105 passing (PHPUnit)
- **Admin halaman:** `resources/views/admin/referensi/alignment-check.blade.php` dengan badge pembelajaran

---

### 2.2 Komponen yang Sudah Ada

#### Database & Model

| Komponen | Status | Detail |
|----------|--------|--------|
| **FK Migration** | ✅ DONE | `jadwal_pelajaran_id` (bigInteger, unsigned, nullable) di `nilai_erapor` table |
| **Populate Migration** | ✅ DONE | Auto-match nilai ke jadwal via guru + mapel + kelas + semester |
| **NilaiErapot Model** | ✅ DONE | `belongsTo(JadwalPelajaran)`, relasi validated |
| **JadwalPelajaran Model** | ✅ DONE | `hasMany(NilaiErapot)`, soft delete aware |
| **Semester Model** | ✅ DONE | Helper untuk mapping teks semester ('Ganjil'/'Genap') ke FK |

#### Service & Business Logic

| Komponen | File | Status | Detail |
|----------|------|--------|--------|
| **JadwalPelajaranResolver** | `app/Services/JadwalPelajaranResolver.php` | ✅ DONE | Matcher: nilai record → jadwal via guru + mapel + kelas + semester (case-insensitive, trimmed) |
| **Scoring Logic** | `NilaiErapotController::simpan()` | ✅ DONE | Hitung nilai_akhir (formatif 30% + sumatif 40% + sumatif_akhir 30%) |
| **Predikat Generator** | `NilaiErapotController::generatePredikat()` | ✅ DONE | A (≥90), B (≥80), C (≥KKM), D (<KKM) |
| **Deskripsi Capaian** | `NilaiErapotController::generateDeskripsi()` | ✅ DONE | Narasi otomatis per predikat + info tertinggi/terendah |

#### Controller & Routes

| Endpoint | Method | Status | Detail |
|----------|--------|--------|--------|
| `/guru/nilai-erapor` | GET | ✅ | List nilai per kelas + filter |
| `/guru/nilai-erapor/create` | GET/POST | ✅ | Form input nilai formatif/sumatif/sumatif_akhir |
| `/guru/nilai-erapor/autoSave` | POST | ✅ | AJAX auto-save saat guru edit (include FK populate) |
| `/guru/nilai-erapor/import` | GET/POST | ✅ | Import nilai dari Excel template |
| `/guru/erapor/pdf` | GET | ✅ | Generate PDF e-Rapor per siswa (Barryvdh) |
| `/guru/erapor/export` | POST | ✅ | Export bulk Excel hasil nilai |
| `/admin/referensi/alignment` | GET | ✅ | Halaman monitoring alignment status (badge, % match, unmatched breakdown) |

#### Data Validation

| Field | Validation | Note |
|-------|-----------|------|
| **mata_pelajaran** | `lower(trim())` matching | Case-insensitive |
| **kelas** | `lower(trim())` matching | Normalize format |
| **semester** | Enum + mapping table | Via `semesters` table |
| **tahun_ajaran** | Format YYYY/YYYY+1 | Validasi strict |
| **nilai fields** | Decimal 0-100 range | Min validation |

---

### 2.3 GAP dan Issue

#### 🟠 High Priority Issues

| Issue | Status | Impact | Solution |
|-------|--------|--------|----------|
| **CBT ↔ e-Rapor NOT INTEGRATED** | PENDING | Nilai CBT tidak otomatis ke nilai_erapor | Tambah integrasi di CbtSubmitService: invoke resolver + store ke nilai_erapor (opsional field `nilai_sumatif_akhir` or configurable) |
| **UAT Pre-Production** | PENDING | Alignment% belum diverifikasi di staging | Jalankan migration + upload sample Dapodik + nilai lama, monitor alignment% breakdown |
| **Dapodik Sync Status** | UNKNOWN | Jadwal pembelajaran dari Dapodik mungkin belum lengkap | Verify `jadwal_pelajaran` table populated dari Dapodik import, check count & data validity |

#### 🟡 Medium Priority Issues

| Issue | Status | Impact | Solution |
|-------|--------|--------|----------|
| **No Audit Trail** | NOT IMPLEMENTED | Nilai changes tidak di-track | Implement soft delete atau history table untuk nilai_erapor (siapa, kapan, sebelum/sesudah) |
| **Dimensi tidak per-mapel** | BY DESIGN? | Dimensi (soft skill) tidak bisa granular per mapel | Clarify dengan user: apakah ini intentional? Jika perlu, design relasi baru |
| **Catatan guru unstructured** | NOTED | Catatan teks bebas tidak bisa auto-format | By design, tampilkan apa adanya di e-Rapor |
| **No Sinkronisasi ke Kemendikbud API** | NOT IMPLEMENTED | Tidak bisa kirim otomatis ke e-Rapor Kemendikbud | Design untuk future: API integration + job queue |

#### 🟢 Low Priority Issues (Noted but Not Blocking)

| Issue | Note |
|-------|------|
| **Migration SQLite vs Postgres** | Test dengan `:testing` DB, deploy ke Postgres — sudah documented |
| **Ambiguous jadwal (hari ganda)** | Tiebreaker: `id` terkecil, logged — sudah fixed di migration |

---

### 2.4 Prioritas Perbaikan

#### Immediate (Week 1)
1. **UAT alignment di staging** — backup produksi, jalankan migration, upload sample data, monitor alignment%
2. **Verifikasi Dapodik sync** — cek `jadwal_pelajaran` terisi lengkap dari Dapodik
3. **Integrate CBT → e-Rapor** — add FK + resolver logic ke CbtSubmitService

#### Short-term (Week 2-3)
4. **Audit trail untuk nilai** — history table atau soft delete tracking
5. **Dashboard admin** — add alignment health indicator (% matched, breakdown unmatched by mapel/kelas)

#### Long-term (Week 4+)
6. **Kemendikbud API integration** — sinkronisasi otomatis nilai ke e-Rapor official
7. **Performance optimization** — index alignment query, cache alignment% di admin dashboard

---

## 3. Dashboard Peran

### 3.1 Status per Peran (Guru / Siswa / Ortu / Admin / Kepsek)

#### Admin Dashboard
**Status:** ✅ **LENGKAP & KOMPLEKS**

| Fitur | Status | File | Detail |
|-------|--------|------|--------|
| Route & Controller | ✅ | `DashboardController@admin()` | `/admin/dashboard` |
| View | ✅ | `admin/dashboard.blade.php` (422 baris) | Responsive, cached query |
| Statistik Utama (6 kartu) | ✅ | Cards: siswa, guru, kelas, akun, login recent, jadwal | Real-time counts |
| Status Kerja Admin (Checklist) | ✅ | 15 item progress tracking | Visual progress bar |
| Sinkronisasi Dapodik | ✅ | Log terbaru 5 entry | Monitor sync status |
| Kesehatan Sistem (Health checks) | ✅ | Environment, debug, queue, DB, cache | Traffic light colors |
| Tren Pendaftaran | ✅ | 6-week chart | Line chart via Chart.js |
| Rekap Guru Aktif | ✅ | Data terisi, materi, kuis, nilai, e-Rapor | Summary per guru |

**Gap:** ❌ Belum ada alignment health indicator untuk e-Rapor

---

#### Guru Dashboard
**Status:** ✅ **LENGKAP & AKTUAL**

| Fitur | Status | File | Detail |
|-------|--------|------|--------|
| Route & Controller | ✅ | `Guru\DashboardController@index()` | `/guru/dashboard` |
| View | ✅ | `guru/dashboard.blade.php` (366 baris) | Responsive |
| Ringkasan Kelas (4 kartu) | ✅ | Siswa aktif, rata-rata, ketuntasan, kehadiran | Per kelas context |
| Jadwal Hari Ini | ✅ | List pelajaran from Dapodik | Real-time schedule |
| Progres e-Rapor | ✅ | Progress bar, deadline, link isi nilai | Action-ready |
| Google Sheets Integration | ✅ | Status koneksi, spreadsheet name, sync time | Optional feature |
| Chart Perkembangan | ✅ | Tren nilai per tipe (Tugas, Ulangan, PTS, PAS) | Per-jenis trend |
| Siswa Menonjol & Pendampingan | ✅ | List siswa C/D + A predikat | Actionable data |
| Konten Terbaru | ✅ | 5 materi + 5 kuis | Activity feed |
| Agenda Guru | ✅ | KKM, jadwal, deadline, progres e-Rapor, esei koreksi | Checklist |

**Gap:** ❌ Shortcut ke kuis esei yang perlu koreksi belum ada

---

#### Siswa Dashboard
**Status:** ✅ **LENGKAP**

| Fitur | Status | File | Detail |
|-------|--------|------|--------|
| Route & Controller | ✅ | `Siswa\DashboardController@index()` | `/siswa/dashboard` |
| View | ✅ | `siswa/dashboard.blade.php` (253 baris) | Responsive, mobile-friendly |
| Profil (4 kartu) | ✅ | Nama, rata-rata, kehadiran, predikat | Personal cards |
| Perkembangan Nilai | ✅ | Tabel per mapel (rata-rata, predikat, status) | Clear table |
| Riwayat Kehadiran | ✅ | Per bulan (H/S/I/A) | Tahun berjalan |
| Saran Personal | ✅ | Auto-generate dari: nilai vs KKM, kehadiran, mapel lemah/terbaik | Color-coded tips |
| Materi Terbaru | ✅ | 5 materi last upload | Activity feed |
| Kuis Aktif | ✅ | 5 kuis dapat dikerjakan | Action links |

**Gap:** ❌ CBT results tidak ditampilkan (future: after CBT UI complete)

---

#### Ortu (Orang Tua) Dashboard
**Status:** ⚠️ **INCOMPLETE STRUCTURE**

| Fitur | Status | File | Detail |
|-------|--------|------|--------|
| Route | ✅ | `/dashboard` (ortu.dashboard route) | Accessible |
| Controller | ❌ **PROBLEM** | Di root `DashboardController@ortu()` bukan `Ortu\DashboardController` | Tidak konsisten dengan guru/siswa/kepsek |
| View | ✅ | `ortu/dashboard.blade.php` (105 baris) | Orphan view (mismatch struct) |
| Dropdown Anak | ✅ | Multi-anak selection | If applicable |
| Data Anak | ✅ | Nama, NIS, kelas, TTL, gender, alamat | Profile info |
| Ringkasan Pantauan | ✅ | Nilai akhir, predikat, kehadiran, status kebiasaan, catatan guru (3 terbaru) | Quick view |
| Akses Cepat | ✅ | "Isi 7 Kebiasaan", "Lihat Perkembangan" buttons | Navigation |

**Gap Analysis:**
1. ❌ Controller structure mismatch (root vs folder)
2. ❌ Tidak ada chart/visualisasi (kontras: Admin, Guru, Kepsek punya chart)
3. ❌ Tidak ada trend analisis (nilai perkembangan, trend kehadiran)
4. ❌ Tidak ada CBT results card (future)
5. ❌ Tidak ada export/print report

---

#### Kepsek (Kepala Sekolah) Dashboard
**Status:** ✅ **LENGKAP TAPI SEDERHANA**

| Fitur | Status | File | Detail |
|-------|--------|------|--------|
| Route & Controller | ✅ | `Kepsek\DashboardController@index()` | `/kepsek/dashboard` |
| View | ✅ | `kepsek/dashboard.blade.php` (131 baris) | Responsive |
| Statistik Utama (4 kartu) | ✅ | Siswa aktif, guru aktif, rata-rata nilai, kehadiran % | Overview |
| Chart Aktivitas Guru (7 hari) | ✅ | Line chart: nilai input, materi, kuis | Per-hari trend |
| Chart Distribusi Predikat | ✅ | Doughnut: A/B/C/D breakdown | Visual distribution |
| Partisipasi Ortu | ✅ | % + progress bar | Engagement metric |
| Top 5 Guru Aktif | ✅ | Tabel ranking | Leaderboard |
| Ringkasan Per Kelas | ✅ | Tabel kelas, siswa, rata nilai, kehadiran, partisipasi ortu | Class-level summary |
| Action Buttons | ✅ | Links: Rekap Lengkap, Hasil Belajar, Pantau Aktivitas, Peta Kelas | Navigation menu |
| Pantau Activity | ✅ | `/kepsek/pantau` — filter guru & tanggal, list aktivitas | Drill-down feature |

**Gap:** 🟡 Terlalu sederhana — tidak ada filter temporal, drill-down per guru, export PDF

---

### 3.2 Komponen yang Sudah Ada

#### Middleware & Role System

| Item | File | Status | Detail |
|-------|------|--------|--------|
| **RoleMiddleware** | `app/Http/Middleware/RoleMiddleware.php` | ✅ | Validate `users.peran` terhadap allowed roles |
| **Peran Enum** | `users.peran` column | ✅ | admin, guru, siswa, ortu, kepsek |
| **Redirect Logic** | Routes/web.php | ✅ | Auto-redirect `/dashboard` sesuai role |
| **Force Password Change** | Middleware | ✅ | Enforce pada first login |

#### Models & Data

| Model | File | Relasi | Status |
|-------|------|--------|--------|
| **User** | `app/Models/User.php` | Poly: Siswa, Guru, Admin, Ortu, Kepsek | ✅ |
| **Siswa** | `app/Models/Siswa.php` | FK guru_id, relasi nilai, kehadiran, kebiasaan | ✅ |
| **Nilai** | `app/Models/Nilai.php` | FK siswa_id, guru_id, mapel | ✅ |
| **NilaiErapot** | `app/Models/NilaiErapot.php` | FK jadwal_pelajaran_id (alignment) | ✅ |
| **Kehadiran** | `app/Models/Kehadiran.php` | FK siswa_id | ✅ |
| **Kebiasaan** | `app/Models/Kebiasaan.php` | FK siswa_id, ortu_id | ✅ |
| **JadwalPelajaran** | `app/Models/JadwalPelajaran.php` | FK guru_id, mata_pelajaran, kelas | ✅ |

#### Service Classes

| Service | File | Fungsi | Status |
|---------|------|--------|--------|
| **NilaiService** | `app/Services/NilaiService.php` | Kalkulasi nilai akhir, predikat | ✅ |
| **JadwalPelajaranResolver** | `app/Services/JadwalPelajaranResolver.php` | Matcher nilai ↔ jadwal | ✅ |
| **HasSiswaLookup Trait** | `app/Traits/HasSiswaLookup.php` | Helper lookup siswa dari auth | ✅ |

#### Caching & Performance

| Teknik | Implementasi | Detail |
|--------|--------------|--------|
| **Cache::remember()** | Admin dashboard | 120 detik cache untuk ~30 queries |
| **selectRaw agregat** | semua dashboard | groupBy + SUM/COUNT untuk portable (SQLite/MySQL/Postgres) |
| **Batch processing** | PHP side, bukan per-loop DB | Efficiency improvement |
| **Tidak cache Model** | Deliberate | Hindari unserialize corruption |

---

### 3.3 GAP dan Issue

#### 🔴 Critical Issues

| Issue | Severity | Impact | Solution |
|-------|----------|--------|----------|
| **Ortu Dashboard Structure Mismatch** | CRITICAL | DashboardController di root (inkonsisten), risky maintenance | Refactor: Pindah `ortu()` method ke dedicated `Ortu\DashboardController` class + update route |
| **Ortu Dashboard No Charts/Visualisasi** | HIGH | Ortu hanya lihat summary ringkas, tidak ada trend analysis | Add: Chart trend nilai, trend kehadiran, timeline kebiasaan (mini card) |
| **CBT Results Not Visible** | HIGH (post-CBT) | Semua peran belum lihat hasil CBT | Post-CBT UI: add card ke siswa dashboard + ortu dashboard card untuk anak |

#### 🟠 High Priority Issues

| Issue | Severity | Impact | File | Solution |
|-------|----------|--------|------|----------|
| **Ortu Dashboard Too Simple** | HIGH | Functionality minimal vs other roles | `ortu/dashboard.blade.php` | Enhance: detail view siswa value breakdown, kehadiran chart, kebiasaan progress |
| **Kepsek Dashboard Limited Insights** | MEDIUM | Hanya basic stats + 2 chart, tidak ada drill-down | `kepsek/dashboard.blade.php` | Add: filter semester, tren tahunan, drill-down per guru/kelas, export PDF |
| **Guru Dashboard Esei Koreksi** | MEDIUM | Esei menunggu koreksi ada di agenda tapi tidak linkable | `guru/dashboard.blade.php` | Add: shortcut ke page koreksi kuis per soal |
| **Alignment Health Not Visible** | MEDIUM | Admin dashboard belum menampilkan e-Rapor alignment status | `admin/dashboard.blade.php` | Add: alignment health card/badge (% matched, unmatchedMustFix breakdown) |
| **No Role Overview Dashboard** | MEDIUM | Tidak ada dashboard untuk overview across all roles (cuma Admin tapi system-focused) | - | Design: dashboard khusus untuk Kepsek cross-role oversight |

#### 🟡 Medium Priority Issues

| Issue | Severity | Note |
|-------|----------|------|
| **Siswa Dashboard Mobile** | LOW | Already responsive, tapi saran personal logic bisa lebih mobile-friendly | Refactor ke Service untuk testability |
| **No Export/Print** | LOW | Admin/Guru/Kepsek tidak bisa export dashboard ke PDF | Future: implementasi export PDF (Barryvdh) |
| **Performance Monitoring** | LOW | Dashboard tidak track query performance | Monitor: cache hit rates, query times di production |

---

### 3.4 Prioritas Perbaikan

#### Sprint 1 (Immediate)
1. **Refactor Ortu Dashboard** — pindah controller ke `Ortu\DashboardController`, update route
2. **Enhance Ortu Dashboard** — add chart trend nilai (mini), kehadiran bar, kebiasaan progress mini
3. **Add Alignment Health Indicator** — di Admin dashboard badge "Alignment Status: XX% matched"

#### Sprint 2
4. **Kepsek Dashboard Enhancement** — add filter semester, tren tahunan, drill-down per guru, export PDF option
5. **Guru Dashboard Shortcut** — link ke esei pending correction
6. **CBT Results Integration** — post-CBT UI: card di siswa + ortu dashboard

#### Sprint 3+
7. Export functionality (Admin, Guru, Kepsek)
8. Real-time notifications (socket)
9. Dashboard customization (drag-drop)

---

## 4. Integrasi Antar Sistem

### 4.1 CBT ↔ e-Rapor Alignment

**Current Status:** ❌ **NOT INTEGRATED**

**Gap Identified:**
- `cbt_test_sessions` tidak memiliki FK ke `jadwal_pelajaran`
- CBT hanya tahu: guru + mapel + semester + kelas (TEXT), tidak bisa link ke jadwal formal
- Nilai CBT tidak bisa otomatis ke `nilai_erapor` table

**Integration Plan (Fase Berikutnya):**

```
Workflow:
[CBT Test Result]
  ├─→ CbtSubmitService: hitung scoring
  ├─→ Invoke JadwalPelajaranResolver (match via guru + mapel + kelas + semester)
  ├─→ Populate jadwal_pelajaran_id ke cbt_test_sessions (update model)
  ├─→ Optional: Store scoring ke nilai_erapor.nilai_sumatif_akhir (field configurable)
  ├─→ Mark status (terkirim_erapor = false, atau auto-mark true jika sync enabled)
  └─→ Admin config: enable/disable auto-sync dari CBT → e-Rapor

Migration Needed:
  1. Add jadwal_pelajaran_id FK ke cbt_test_sessions (nullable, nullOnDelete)
  2. Populate logic: match existing sessions via resolver
  3. Update CbtSubmitService invoke resolver saat submit
  4. Update UI: show jadwal badge di session detail
  5. Config: enable CBT auto-sync ke e-Rapor (default: disabled, manual approve)
```

**Impact:**
- ✅ Nilai CBT terintegrasi formal jadwal pembelajaran
- ✅ Admin bisa tracking alignment% untuk CBT values
- ✅ E-Rapor selesai bisa include CBT scores (jika diset)

---

### 4.2 e-Rapor ↔ Dashboard Alignment

**Current Status:** ⏳ **PARTIAL**

**What's Working:**
- Guru dashboard: progress e-Rapor bar (% siswa terisi)
- Dashboard siap show nilai per mapel dengan predikat
- Kehadiran tracking tersedia

**What's Missing:**
- Admin dashboard: alignment health indicator belum ada
- Kepsek dashboard: belum group nilai by jadwal (hanya by guru)
- Siswa dashboard: belum jelas rincian per jadwal pelajaran
- Ortu dashboard: belum lihat breakdown nilai per mapel detail

**Action:**
1. Admin dashboard: add alignment badge + % matched breakdown
2. Kepsek: option group data by jadwal pelajaran (not just guru)
3. Siswa: detail performa per mapel + jadwal info
4. Ortu: trend chart nilai per mapel anak

---

### 4.3 Dashboard ↔ CBT Integration

**Current Status:** ❌ **NOT INTEGRATED**

**What's Missing:**
- Guru dashboard: no CBT session card (buat kuis, monitor ujian)
- Siswa dashboard: no available exams card
- Ortu dashboard: no anak's CBT results card
- Kepsek dashboard: no CBT activity tracking

**Action (Post-CBT UI):**
1. Guru dashboard: add "Sesi Ujian Hari Ini" card + quick stats (ongoing sessions, completed, avg score)
2. Siswa dashboard: add "Ujian Tersedia" card + links to start
3. Ortu dashboard: add "Hasil Ujian Anak" card showing recent scores + comparison
4. Kepsek dashboard: include CBT activity in "Aktivitas Guru 7 Hari" chart

---

### 4.4 Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│             SIMANTAP DATA INTEGRATION ARCHITECTURE              │
└─────────────────────────────────────────────────────────────────┘

Dapodik (External)
  │
  ├─→ Siswa, Guru, Jadwal Pembelajaran
  │
  ▼
SIMANTAP
  │
  ├─ CBT Subsystem (Backend ✓, Frontend ✗)
  │  ├─→ Question Banks (Guru)
  │  ├─→ Test Sessions (Guru) ← [PENDING: FK jadwal_pelajaran]
  │  ├─→ Student Answers & Results (Siswa)
  │  ├─→ Integrity Logs (Anti-cheat)
  │  └─→ CbtSubmitService (Scoring) ← [PENDING: invoke JadwalPelajaranResolver]
  │
  ├─ e-Rapor Subsystem (Backend ✓, UAT ✗)
  │  ├─→ Input Nilai (Guru)
  │  ├─→ Import Excel (Guru)
  │  ├─→ NilaiErapot Model (with FK jadwal_pelajaran_id) ✓
  │  ├─→ JadwalPelajaranResolver (Matcher) ✓
  │  ├─→ Generate PDF/Excel (Guru) ✓
  │  └─→ Alignment Check (Admin) ✓
  │
  ├─ Dashboard Subsystem (Frontend ✓, Enhancements ✗)
  │  ├─→ Admin: Alignment badge [PENDING]
  │  ├─→ Guru: CBT card [PENDING]
  │  ├─→ Siswa: Exam card + CBT results [PENDING]
  │  ├─→ Ortu: Refactor + chart + CBT [PENDING]
  │  └─→ Kepsek: CBT activity + enhancement [PENDING]
  │
  └─ Output
     ├─→ e-Rapor PDF (Guru)
     ├─→ e-Rapor Excel (Guru/Admin/Kepsek)
     ├─→ Dashboard UI (All Roles)
     └─→ Future: Kemendikbud API sync

Integration Points:
  [1] CBT → jadwal_pelajaran (FK) → e-Rapor nilai_erapor (alignment)
  [2] e-Rapor → Dashboard (Admin: health badge, Kepsek: grouped view)
  [3] CBT → Dashboard (Guru: session card, Siswa: exam card, Ortu: results card)
  [4] All → Alignment Health (tracking % matched, issues logged)
```

---

## 5. Rekomendasi Tindakan

Daftar berurutan berdasarkan prioritas & dependency. Setiap item includes effort estimate + owner.

### 🔴 Priority 1: CRITICAL (Mulai Minggu Ini)

| # | Tindakan | Effort | Owner | Timeline | Blocking |
|---|----------|--------|-------|----------|----------|
| **1.1** | Refactor Ortu Dashboard Controller — pindah ke `Ortu\DashboardController`, update route mismatch | 4 SP | Backend Dev | 2 hari | Semua dashboard enhancement |
| **1.2** | Create CBT Student Exam UI — `cbt/student/take.blade.php` dengan soal navigator, timer (countdown), jawaban per soal, ragu-ragu flag, navigation buttons | 13 SP | Frontend Dev | 3-4 hari | CBT siswa bisa kerja ujian |
| **1.3** | Create CBT Results UI — `cbt/student/result.blade.php`, score breakdown, perbandingan kelas, cetak hasil | 5 SP | Frontend Dev | 1-2 hari | Siswa bisa lihat hasil |
| **1.4** | Add jadwal_pelajaran_id FK to cbt_test_sessions — migration + populate + update model + resolver invoke di CbtSubmitService | 8 SP | Backend Dev | 2 hari | CBT ↔ e-Rapor integration |

**Subtotal Priority 1:** 30 SP (~2 minggu, 1-2 developer)

---

### 🟠 Priority 2: HIGH (Minggu Depan)

| # | Tindakan | Effort | Owner | Timeline | Dependency |
|---|----------|--------|-------|----------|------------|
| **2.1** | Add Alignment Health Indicator to Admin Dashboard — badge status, % matched, breakdown unmatched by mapel/kelas | 5 SP | Frontend Dev | 1 hari | After 1.1 (Ortu refactor) |
| **2.2** | Create CBT Monitoring Dashboard — `cbt/sessions/monitoring.blade.php` untuk guru real-time siswa mengerjakan, siswa aktif, soal sedang, waktu tersisa | 10 SP | Frontend Dev | 2-3 hari | After 1.2 |
| **2.3** | Enhance Ortu Dashboard — add chart trend nilai (mini), kehadiran bar, kebiasaan progress, CBT results card (post-1.2) | 8 SP | Frontend Dev | 2 hari | After 1.1 |
| **2.4** | Create CBT JavaScript — timer.js (countdown + auto-warn), integrity-checker.js (tab switch, focus loss), answer-saver.js (auto-save debounced) | 10 SP | Frontend Dev | 2-3 hari | After 1.2 |
| **2.5** | Add CBT Guru Dashboard Card — sesi ujian hari ini, monitoring link, stats (ongoing/completed/avg score) | 5 SP | Frontend Dev | 1 hari | After 1.1, 2.2 |
| **2.6** | UAT e-Rapor Alignment di Staging — backup prod, migrate, upload sample Dapodik, nilai lama, monitor alignment% breakdown | 6 SP | QA/Backend | 2 hari | Pre-UAT checklist |

**Subtotal Priority 2:** 44 SP (~3 minggu, 2 developers)

---

### 🟡 Priority 3: MEDIUM (Week 3-4)

| # | Tindakan | Effort | Owner | Timeline | Dependency |
|---|----------|--------|-------|----------|------------|
| **3.1** | Add Unit Tests for CBT — CbtStudentController, CbtSubmitService scoring logic, integrity violations | 13 SP | Backend Dev | 3 hari | After 1.4 |
| **3.2** | Implement CBT Results Export (Excel) — use Maatwebsite/Laravel-Excel, template dengan breakdown | 8 SP | Backend Dev | 2 hari | After 1.3 |
| **3.3** | Implement Audit Trail untuk Nilai Rapor — history table atau soft delete tracking, siapa/kapan/before-after | 8 SP | Backend Dev | 2 hari | Pre-production |
| **3.4** | Enhance Kepsek Dashboard — add filter semester, trend tahunan, drill-down per guru/kelas, export PDF option | 13 SP | Frontend Dev | 3 hari | After 2.1 |
| **3.5** | Add CBT to Siswa & Ortu Dashboard — siswa: available exams card, ortu: anak's results card with trend | 8 SP | Frontend Dev | 2 hari | After 2.1 |
| **3.6** | Implement CBT Bulk Import Soal — accept Excel/CSV, validate, import massal | 10 SP | Backend Dev | 2-3 hari | After 3.1 (testing) |

**Subtotal Priority 3:** 60 SP (~4 minggu, 2 developers)

---

### 🟢 Priority 4: NICE-TO-HAVE (Month 2)

| # | Tindakan | Effort | Owner | Timeline |
|---|----------|--------|-------|----------|
| **4.1** | CBT Live Monitoring WebSocket — real-time siswa aktif, soal terkini, perbaruan skor | 21 SP | Backend + Frontend | 1 minggu |
| **4.2** | Question Bank Sharing — design permission model, guru bisa share soal dengan guru lain | 13 SP | Backend Dev | 2-3 hari |
| **4.3** | e-Rapor Kemendikbud API Integration — design, implement sinkronisasi otomatis nilai | 34 SP | Backend Dev | 1-2 minggu |
| **4.4** | Performance Optimization — index alignment query, cache alignment%, async populate job (>100k rows) | 13 SP | Backend Dev | 2-3 hari |
| **4.5** | Dashboard Customization — drag-drop card, save preferences per user | 21 SP | Frontend Dev | 1 minggu |

**Subtotal Priority 4:** 102 SP (~6 minggu, 2 developers)

---

### Summary Effort & Timeline

| Priority | Total SP | Min Timeline | Developers Needed |
|----------|----------|--------------|-------------------|
| **P1 (Critical)** | 30 SP | 2 minggu | 1-2 |
| **P2 (High)** | 44 SP | 3 minggu | 2 |
| **P3 (Medium)** | 60 SP | 4 minggu | 2 |
| **P4 (Nice-to-have)** | 102 SP | 6 minggu | 1-2 |
| **TOTAL (MVP + Enhancement)** | 236 SP | 13 minggu | 2-3 developers parallel |

**Fast-track (P1+P2 only = MVP CBT + Dashboard enhancement):** ~10-12 minggu, 2 developers

---

## 6. Daftar File Kunci

Tabel file penting, fungsi, dan status implementasi.

| File | Lokasi | Fungsi | Status | Priority |
|------|--------|--------|--------|----------|
| **CBT Models** | `app/Models/Cbt/` | QuestionBank, Question, TestSession, StudentAnswer, TestResult, IntegrityLog | ✅ DONE | - |
| **CBT Controllers** | `app/Http/Controllers/Cbt/` | CRUD bank, soal, sesi; submission; monitoring | ✅ DONE | - |
| **CbtSubmitService** | `app/Services/CbtSubmitService.php` | Scoring logic, nilai_akhir, predikat, lulus | ✅ DONE | 1.4 (add resolver invoke) |
| **CbtSecurityMiddleware** | `app/Http/Middleware/CbtSecurityMiddleware.php` | Validasi waktu, integrity score | ✅ DONE | - |
| **CbtSessionLock** | `app/Http/Middleware/CbtSessionLock.php` | Validasi IP, User-Agent | ✅ DONE | - |
| **CBT Routes** | `routes/web.php` (cbt segment) | Guru/Siswa/Admin endpoints | ✅ DONE | - |
| **CBT Views** | `resources/views/cbt/` | Skeleton files | ⏳ PARTIAL | **P1: 1.2, 1.3** |
| **CBT JavaScript** | `resources/js/cbt/` | Timer, integrity checker, auto-saver | ❌ NOT CREATED | **P2: 2.4** |
| **NilaiErapot Model** | `app/Models/NilaiErapot.php` | Nilai rapor + FK jadwal_pelajaran | ✅ DONE | - |
| **JadwalPelajaran Model** | `app/Models/JadwalPelajaran.php` | Pembelajaran Dapodik + relasi nilai | ✅ DONE | - |
| **JadwalPelajaranResolver** | `app/Services/JadwalPelajaranResolver.php` | Matcher nilai ↔ jadwal | ✅ DONE | - |
| **NilaiErapotController** | `app/Http/Controllers/Guru/NilaiErapotController.php` | Input nilai, auto-save, scoring | ✅ DONE | - |
| **ErapotGeneratorController** | `app/Http/Controllers/Guru/ErapotGeneratorController.php` | Generate PDF/Excel | ✅ DONE | - |
| **Admin Alignment Check View** | `resources/views/admin/referensi/alignment-check.blade.php` | Monitor alignment status | ✅ DONE | - |
| **Alignment Migrations** | `database/migrations/2026_10_04_*.php` | FK + populate | ✅ DONE | - |
| **e-Rapor Docs** | `docs/09-12-ALIGNMENT*.md` | Documentation fase 1-7 | ✅ DONE | - |
| **Admin Dashboard Controller** | `app/Http/Controllers/DashboardController@admin()` | Admin stats, monitoring | ✅ DONE | **2.1 (add alignment badge)** |
| **Admin Dashboard View** | `resources/views/admin/dashboard.blade.php` | UI, cards, charts | ✅ DONE | **2.1** |
| **Guru Dashboard Controller** | `app/Http/Controllers/Guru/DashboardController.php` | Guru stats, kelas, progres rapor | ✅ DONE | **2.5 (add CBT card)** |
| **Guru Dashboard View** | `resources/views/guru/dashboard.blade.php` | UI, jadwal, materi, nilai | ✅ DONE | **2.5** |
| **Siswa Dashboard Controller** | `app/Http/Controllers/Siswa/DashboardController.php` | Siswa stats, nilai, kehadiran, saran | ✅ DONE | **3.5 (add exam card)** |
| **Siswa Dashboard View** | `resources/views/siswa/dashboard.blade.php` | UI, profil, nilai, saran | ✅ DONE | **3.5** |
| **Ortu Dashboard Controller** | `DashboardController@ortu()` di root | ❌ MISMATCH | **P1: 1.1 (refactor)** | |
| **Ortu Dashboard View** | `resources/views/ortu/dashboard.blade.php` | ⏳ PARTIAL | **P2: 2.3 (enhance)** | |
| **Kepsek Dashboard Controller** | `app/Http/Controllers/Kepsek/DashboardController.php` | Kepsek stats, guru, kelas | ✅ DONE | **P3: 3.4 (enhance)** |
| **Kepsek Dashboard View** | `resources/views/kepsek/dashboard.blade.php` | UI, chart, tabel | ✅ DONE | **P3: 3.4** |
| **RoleMiddleware** | `app/Http/Middleware/RoleMiddleware.php` | Role validation | ✅ DONE | - |
| **User Model** | `app/Models/User.php` | Auth + peran | ✅ DONE | - |
| **NilaiService** | `app/Services/NilaiService.php` | Kalkulasi nilai, predikat | ✅ DONE | - |

---

## Kesimpulan & Next Steps

### Status Keseluruhan

| Modul | Implementasi | UAT | Production Ready |
|-------|:-------------:|:---:|:-----------------:|
| **CBT Backend** | ✅ 95% | ❌ | ❌ |
| **CBT Frontend** | ❌ 5% | ❌ | ❌ |
| **e-Rapor Alignment** | ✅ 100% | ⏳ | ⏳ (Post-UAT) |
| **Dashboard Admin** | ✅ 90% | ✅ | ✅ (minor: alignment badge) |
| **Dashboard Guru** | ✅ 90% | ✅ | ✅ (minor: CBT card) |
| **Dashboard Siswa** | ✅ 95% | ✅ | ✅ (minor: CBT card) |
| **Dashboard Ortu** | ⚠️ 60% | ⏳ | ❌ (refactor + enhance) |
| **Dashboard Kepsek** | ✅ 85% | ✅ | ✅ (minor: enhancement) |

### Deliverable untuk Stakeholder

**Siap Deploy Sekarang:**
- ✅ e-Rapor alignment backend (setelah UAT staging pass)
- ✅ Dashboard Admin, Guru, Siswa, Kepsek (dengan enhancement minor)

**Harus Selesai Sebelum Go-Live:**
- ❌ CBT student exam UI (P1: 1.2, 1.3)
- ❌ CBT ↔ jadwal pelajaran integration (P1: 1.4)
- ❌ Ortu dashboard refactor + enhancement (P1: 1.1, P2: 2.3)
- ❌ Dashboard alignment health indicator (P2: 2.1)

**Timeline Rekomendasi:**
- **Week 1-2 (P1):** CBT UI + jadwal FK integration (30 SP)
- **Week 3-4 (P2):** Dashboard enhancement, CBT JS, monitoring, UAT (44 SP)
- **Go-live checkpoint:** End of Week 4 (MVP selesai)

---

## Verifikasi Sebelum Laporan Tuntas

Checklist untuk konfirmasi sebelum sign-off laporan:

- [x] Baca CBT investigation report lengkap (backend status, frontend gap, routes, models, migrations)
- [x] Baca e-Rapor investigation report lengkap (7 fase design, migration selesai, test hijau, alignment logic)
- [x] Baca Dashboard investigation report lengkap (semua peran status, Ortu problem identified, enhancement recommendation)
- [x] Identifikasi integration points (CBT ↔ e-Rapor, CBT ↔ Dashboard, e-Rapor ↔ Dashboard)
- [x] Prioritas clear: P1 (CBT UI + jadwal FK) → P2 (dashboard enhancement) → P3 (testing, export, audit trail)
- [x] Effort estimate realistic (236 SP total, 13 minggu for full, 10-12 minggu for MVP)
- [x] Daftar file kunci lengkap dengan status & priority tindakan
- [x] Rekomendasi actionable (punya owner, timeline, dependency)
- [x] GAP teridentifikasi dan solusi clear

**Laporan siap untuk decision-making & implementation planning.**

---

**Laporan Investigasi Selesai** ✅  
**Status:** READY FOR STAKEHOLDER REVIEW  
**Output:** e:/Project/simantap/.agents/tasks/investigation/LAPORAN-FINAL.md  
**Tanggal:** 2026-10-04  
**Investigator:** Senior Analyst (AI)

