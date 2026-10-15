# Pre-Investigation Findings (Manual Scan)

## Status: IN PROGRESS
Workflow paralel sedang berjalan. Dokumen ini adalah findings dari quick manual scan.

---

## 1. CBT (Computer-Based Test) - BACKEND COMPLETE ✅

### Status Keseluruhan
- **Backend:** ✅ SELESAI (7 model, 5 controller, semua routes, middleware)
- **Frontend:** ⏳ PENDING (views untuk guru, siswa, JavaScript)
- **Testing:** ⏳ PENDING

### Model yang Ada
```
app/Models/Cbt/
├── QuestionBank.php      ✅
├── Question.php           ✅
├── QuestionOption.php     ✅
├── TestSession.php        ✅
├── StudentAnswer.php      ✅
├── TestResult.php         ✅
└── IntegrityLog.php       ✅
```

### Controller yang Ada
```
app/Http/Controllers/Cbt/
├── CbtQuestionBankController.php      ✅ index, create, store, show, edit, update, destroy
├── CbtQuestionController.php          ✅ store, update, destroy
├── CbtTestSessionController.php       ✅ index, create, store, show, publish, start, complete, monitoring
├── CbtStudentController.php           ✅ index, start, submitAnswer, submit, result
└── CbtIntegrityController.php         ✅ logViolation, getViolations
```

### Admin CBT Controller
```
app/Http/Controllers/Admin/Cbt/
├── CbtBankController.php             ✅
└── CbtSessionController.php           ✅
```

### Routes Registered
- Guru CBT Routes: `/cbt/*` dengan `role:guru`
- Siswa CBT Routes: `/cbt/student/*` dengan `role:siswa`, `cbt.session.lock`, `cbt.security`
- Admin CBT Routes: `/admin/cbt/*` dengan `role:admin`

### Database Tables
- ✅ `cbt_question_banks` - Migration done
- ✅ `cbt_questions` - Migration done
- ✅ `cbt_question_options` - Migration done
- ✅ `cbt_test_sessions` - Migration done
- ✅ `cbt_student_answers` - Migration done
- ✅ `cbt_test_results` - Migration done
- ✅ `cbt_integrity_logs` - Migration done

### Missing / Pending
1. **Blade Views** - Need to create:
   - `resources/views/cbt/layouts/` - Layout
   - `resources/views/cbt/question-banks/` - List, create, edit, show views
   - `resources/views/cbt/sessions/` - Session management views
   - `resources/views/cbt/student/` - Student exam interface
   
2. **JavaScript** - Need to create:
   - `resources/js/cbt/integrity-checker.js` - Anti-cheat logic
   - `resources/js/cbt/timer.js` - Countdown timer
   - `resources/js/cbt/answer-saver.js` - Auto-save answers
   - `resources/js/cbt/question-navigator.js` - Question navigation

3. **Tests** - Need to create:
   - Feature tests untuk CBT controllers
   - Unit tests untuk CBT models

---

## 2. E-Rapor Alignment - PHASE 1 COMPLETE ✅

### Status Keseluruhan
- **Phase 1:** ✅ AUDIT & DESIGN SELESAI (2026-10-04)
- **Phase 2-7:** ⏳ PENDING (Database migration, implementation, testing)

### Documentation That Exists
- ✅ `docs/09-ERAPOR-ALIGNMENT-PLAN.md` - Rencana lengkap
- ✅ `docs/10-ALIGNMENT-DESIGN.md` - Design dengan aturan matching yang sudah dikoreksi
- ⏳ `docs/11-ALIGNMENT-GUIDE.md` - Belum ada
- ⏳ `docs/12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md` - Belum ada

### Key Findings from Design
1. **Foreign Key Relationship**: `nilai_erapor.jadwal_pelajaran_id` → `jadwal_pelajaran.id`
2. **FK Constraint**: `nullOnDelete` (bukan restrict) untuk handle Dapodik re-import
3. **Matching Rules** (corrected in Phase 1):
   - Match semester dari text → `semesters` table
   - Normalize text: `lower(trim())`
   - Handle ambiguous matches (multiple days same guru+mapel+kelas)
   - Track unmatched reasons: `guru_tak_punya_jadwal`, `mapel_tak_cocok`, `kelas_tak_cocok`, `semester_tak_cocok`

### Models Status
- ✅ `NilaiErapot` model sudah ada
- ✅ `JadwalPelajaran` model sudah ada
- ⏳ Relasi `jadwalPelajaran()` dan `nilaiErapot()` belum ditambah

### Next Phase Actions
1. Add migration untuk FK column di `nilai_erapot`
2. Update models dengan relationships
3. Create `JadwalPelajaranResolver` service
4. Update `NilaiErapotController@simpan` untuk resolve jadwal
5. Create views untuk alignment check
6. Create comprehensive tests

### Controllers Involved
- `app/Http/Controllers/Guru/NilaiErapotController.php` - Existing, needs update
- `app/Http/Controllers/Admin/DataReferensiController.php` - Existing, needs alignment check method

---

## 3. Dashboard untuk Peran - STATUS FRAGMENTED ⚠️

### Dashboard Yang Sudah Ada

#### A. Guru Dashboard ✅
- **File**: `app/Http/Controllers/Guru/DashboardController.php`
- **Status**: Complete dan comprehensive
- **Features**:
  - Ringkasan: jumlah siswa, rata-rata nilai, tertinggi, tuntas
  - Siswa yang perlu pendampingan
  - Siswa yang menonjol
  - Materi terbaru
  - Quiz aktif
  - Chart: predikat distribution
  - Chart: trend nilai per jenis assessment
  - Agenda: KKM status, jadwal hari ini, deadline rapor, progress e-Rapor
  - Esei menunggu koreksi
  - Google Sheets sync status

#### B. Siswa Dashboard ✅
- **File**: `app/Http/Controllers/Siswa/DashboardController.php`
- **Status**: Complete
- **Features**:
  - Kehadiran percentage
  - Rata-rata nilai
  - Jumlah nilai yang diterima
  - Materi terbaru
  - Quiz aktif dengan hasil
  - Nilai per mata pelajaran
  - Kehadiran bulanan breakdown (H/S/I/A)
  - Saran personalized berdasarkan performance & kehadiran

#### C. Kepsek Dashboard ✅
- **File**: `app/Http/Controllers/Kepsek/DashboardController.php`
- **Status**: Complete dan comprehensive
- **Features**:
  - Stats: total siswa, guru, kelas, orang tua
  - Rata-rata nilai sekolah & predikat
  - Persentase kehadiran sekolah
  - Partisipasi orang tua
  - Activity timeline: nilai, materi, kuis (7 hari)
  - Top 5 guru ranking
  - Distribusi predikat (A/B/C/D)
  - Performance per kelas: jumlah siswa, rata nilai, kehadiran, partisipasi ortu
  - Monitoring page: aktivitas guru per hari dengan filter

#### D. Admin Dashboard ✅
- **File**: `app/Http/Controllers/DashboardController.php::admin()`
- **Status**: Complete dan comprehensive
- **Features**:
  - Stats: total users, guru, siswa, siswa aktif, PTK, rombel
  - Dapodik sync: last sync, synced siswa/guru
  - Aktivitas: materi, kuis, kehadiran total
  - Online count
  - Setup checklist: 15 items (Dapodik, admin, guru/siswa, kepala, gelar, logo, rapor dates, tema, backup, e-rapor kirim)
  - Progress kerja (persentase setup completion)
  - Aktivitas terbaru (6 items)
  - Breakdown per peran (admin/guru/siswa/ortu/kepsek - total & aktif)
  - Backup info: jumlah, ukuran, terakhir, antri, gagal
  - Trend pendaftaran (6 minggu)
  - System health: environment, debug, queue, failed jobs, cache, database, backup folder
  - Rekap guru aktif: data siswa, materi, kuis, nilai, e-Rapor

#### E. Ortu Dashboard ✅
- **File**: `app/Http/Controllers/DashboardController.php::ortu()`
- **Status**: Complete (basic, per-child view)
- **Features**:
  - Multi-anak support (bisa lihat multiple children)
  - Ringkasan per anak: nilai akhir, predikat, kehadiran %, kebiasaan minggu, catatan terbaru
  - Dropdown selector untuk switch antar anak
- **Related Methods**:
  - `ortuNilai()` - Detail nilai per anak

### Struktur Ortu Controllers
```
app/Http/Controllers/Ortu/
├── CatatanController.php       - Catatan untuk ortu
├── HasPredikatKaih.php         - Helper trait?
├── KebiasaanController.php     - 7 Kebiasaan tracking
├── KehadiranController.php     - Kehadiran siswa
├── LaporanController.php       - Laporan untuk ortu
└── RekapController.php         - Rekap data
```

### Gap Analysis
1. **No Ortu Dashboard**: Ortu doesn't have aggregated view
2. **No Admin Dashboard**: Admin may not have complete dashboard (or root-level controller serves it)
3. **TKA & Tryout**: Likely no dedicated dashboards untuk kedua fitur ini

---

## 4. Middleware & Security

### CBT-Specific Middleware
- ✅ `CbtSecurityMiddleware` - Validasi status, expiry, integrity score
- ✅ `CbtSessionLock` - IP & User Agent validation

### Registered Middleware
- Registered di `bootstrap/app.php`
- Applied to CBT student routes

---

## Summary of Findings

| Area | Status | Complete % | Notes |
|------|--------|-----------|-------|
| CBT Backend | ✅ COMPLETE | 100% | Models, controllers, routes, migrations done |
| CBT Frontend | ⏳ PENDING | 0% | Need views & JS |
| E-Rapor Alignment Design | ✅ COMPLETE | 100% | Phase 1 audit & design done, rules corrected |
| E-Rapor Alignment Impl | ⏳ PENDING | 0% | Phase 2-7 pending |
| Dashboard Guru | ✅ COMPLETE | 100% | Full featured |
| Dashboard Siswa | ✅ COMPLETE | 100% | Full featured |
| Dashboard Kepsek | ✅ COMPLETE | 100% | Full featured |
| Dashboard Admin | ✅ COMPLETE | 100% | Full featured |
| Dashboard Ortu | ✅ COMPLETE | 100% | Basic (per-child), needs enhancement |

---

## Waiting For
- Workflow parallel investigation to complete
- Consolidation report from workflow agents
