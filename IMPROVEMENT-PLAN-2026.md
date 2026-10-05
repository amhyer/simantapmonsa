# 📋 SIMANTAP — Rencana Perbaikan & Peningkatan Komprehensif

**Tanggal Pembuatan:** 5 Oktober 2026  
**Status:** Active Planning & Execution  
**Target:** Production Readiness Q4 2026

---

## 📊 Executive Summary

**Project:** SIMANTAP v4.2 — Learning Management & Assessment System  
**Stack:** Laravel 11 + PHP 8.3 + PostgreSQL 16 + Blade + Tailwind CSS  
**Language Composition:** 52.7% Blade, 37% PHP, 5.1% Python (Bridge), 5.2% Other  

**Current State:**
- ✅ 22/33 issues FIXED
- ⏳ 9/33 issues DEFERRED or IN-PROGRESS
- 🎯 Core functionality STABLE
- 🔐 Security foundation SOLID but incomplete
- 📦 Database schema COMPREHENSIVE (35 tables)

---

## 🔍 DEEP DIVE ANALYSIS

### 1. PROJECT PURPOSE & USERS

| Role | Purpose | Features | Access Level |
|------|---------|----------|--------------|
| **Admin** | System configuration & governance | User CRUD, Dapodik sync, API keys, backups | Full |
| **Guru (Teacher)** | Assessment & grading | Input nilai, e-Rapor generation, quiz, materials | Role-based |
| **Siswa (Student)** | Learning & assessment participation | View grades, take quiz, materials | Limited |
| **Ortu (Parent)** | Child monitoring | View child's grades, attendance, reports | View-only |
| **Kepsek (Principal)** | School oversight | Monitor teachers, view reports, activity logs | Reporting |

**Key Integration Points:**
- Dapodik (Indonesian Education Database) — two-way sync
- Google Sheets — automatic export
- PDF/Excel export for compliance

---

### 2. CURRENT ARCHITECTURE LAYERS

```
┌─────────────────────────────────────────────────────┐
│         Frontend (Blade + Tailwind CSS)              │
│  - 107 Blade templates                              │
│  - Role-based dashboards (5 roles)                  │
│  - Chart.js integration (analytics)                 │
└─────────────────────────────────────────────────────┘
                          ↕
┌─────────────────────────────────────────────────────┐
│    Application Layer (Laravel 11 + Controllers)      │
│  - 53 Controllers                                    │
│  - 159 Routes                                        │
│  - 5 Services (Dapodik, Nilai, etc.)                │
│  - 8 Middleware (Auth, Role, API Key, etc.)         │
└─────────────────────────────────────────────────────┘
                          ↕
┌─────────────────────────────────────────────────────┐
│   Data Layer (26 Eloquent Models + 35 Tables)       │
│  - Core: siswa, users, guru, rombel, semester       │
│  - Academic: nilai, kuis, materi, kehadiran         │
│  - Assessment: nilai_erapor, capaian_pembelajaran   │
│  - Support: api_keys, dapodik_sync_logs             │
└─────────────────────────────────────────────────────┘
                          ↕
┌─────────────────────────────────────────────────────┐
│   External Integrations                              │
│  - Dapodik WebService (sync)                        │
│  - Google Sheets API (export)                       │
│  - DomPDF (PDF generation)                          │
│  - PhpSpreadsheet (Excel export)                    │
└─────────────────────────────────────────────────────┘
```

---

### 3. DATABASE SCHEMA OVERVIEW

**Core Tables (8):**
- `users` — Authentication (13 cols)
- `siswa` — Students (35 cols) **— HEAVY SCHEMA**
- `guru` — Teachers (via users + ptk)
- `rombel` — Class assignments
- `semester` — Academic terms
- `mata_pelajaran` — Subjects
- `jadwal_pelajaran` — Learning schedules
- `pengaturan_guru` — Teacher settings per subject

**Academic/Grading (9):**
- `nilai` — Individual grades (14 cols)
- `nilai_erapor` — e-Rapor grades (18 cols) **— DUPLICATES nilai?**
- `nilai_mapel` — Subject aggregates
- `nilai_cp` — Learning outcome grades
- `capaian_pembelajaran` — Learning outcomes
- `kuis` — Quizzes (18 cols)
- `hasil_kuis` — Quiz results (11 cols)
- `dimensi` — Competency dimensions (12 cols)
- `kehadiran` — Attendance (8 cols)

**Support/Reference (6):**
- `ekstrakurikuler` — Extra-curriculars
- `p5_projek`, `p5_siswa` — P5 projects
- `prestasi` — Achievements
- `catatan` — Notes/remarks
- `kebiasaan` — Behavioral habits (7 habits scale)

**Integration/Logging (5):**
- `api_keys` — Bridge authentication
- `dapodik_sync_logs` — Sync history
- `dapodik_config` — Connection settings
- `aktivitas` — Activity logs
- `backups` — Backup metadata

**Kokurikuler/Co-curricular (3):**
- `tema_kokurikuler` — Themes
- `kegiatan_kokurikuler` — Activities
- `kelompok_kokurikuler` — Groups

**Total: 35 tables, 26 Eloquent models**

---

### 4. CRITICAL FINDINGS

#### A. Data Model Issues

| Issue | Severity | Impact | Status |
|-------|----------|--------|--------|
| **nilai vs nilai_erapor duplication** | 🔴 HIGH | Unclear which to use when; sync conflicts | NEEDS CLARITY |
| **Missing FK references** | 🔴 HIGH | nilai has no jadwal_pelajaran_id; subject matching via text | NEEDS REFACTOR |
| **Soft deletes inconsistency** | 🟠 MEDIUM | Some tables use soft deletes, others don't; affects reporting | NEEDS STANDARDIZATION |
| **9 tables without models** | 🟡 LOW | capaian_pembelajaran, ekstrakurikuler, etc. — raw queries only | DEFERRED |
| **No composite indexes** | 🟡 LOW | Queries on (guru_id, siswa_id, mata_pelajaran) may be slow | PERFORMANCE TUNING |

#### B. Security Gaps (RESIDUAL)

| Gap | Current State | Target |
|-----|---------------|--------|
| **Default password** | ✅ FIXED — Force change workflow active | Verify in production |
| **API keys** | ✅ FIXED — Now using hash+verify pattern | Migrate legacy keys |
| **Backup security** | ✅ FIXED — Password excluded | Audit completed |
| **XSS in views** | ⚠️ Some `{!! !!}` remain | Audit legacy views |
| **Rate limiting** | ✅ FIXED on registration | Needs: login (5/min), API (100/hour) |

#### C. Controller Logic Issues

| File | Issue | Status |
|------|-------|--------|
| `NilaiErapotController` | Null pointer → now using `optional()` | ✅ FIXED |
| `InputNilaiController` | Writing non-existent columns → fixed | ✅ FIXED |
| `KehadiranController` | UUID regeneration on update → now split logic | ✅ FIXED |
| `IntegrasiController` | Null pointer on pengaturan → fixed | ✅ FIXED |
| `ErapotGeneratorController` | Raw header() bypass → now response()->stream() | ✅ FIXED |

#### D. View Template Issues

| Issue | Count | Status |
|-------|-------|--------|
| Wrong attribute names | 5 | ✅ FIXED |
| Missing variables | 3 | ✅ FIXED |
| Null pointer access | 2 | ✅ FIXED (M10) |
| Chart/script placement | 2 | ✅ FIXED |

---

## 🎯 IMPROVEMENT PLAN PHASE 1: Foundation (Q4 2026)

### Phase 1A: Data Model Clarity & Integrity

**Objective:** Eliminate ambiguity in grade storage; establish referential integrity

#### 1A-1: Resolve `nilai` vs `nilai_erapor` Confusion
```
Current State:
- nilai: General grades, supports kuis_id (quiz grades)
- nilai_erapor: e-Rapor specific, formatted per standard

Issue: Both exist; unclear when to use which

Action Required:
□ Audit: Which is system of record?
  - Option A: nilai is primary → nilai_erapor derived view
  - Option B: nilai_erapor is primary → nilai deprecated
  
□ Implement: If A → create computed/materialized view
          If B → migrate nilai → archive

□ Document: Usage policy in Architecture guide

□ Test: Verify e-Rapor generation consistency
```

**Owner:** @amhyer  
**Timeline:** 2 days  
**Files:** docs/05-ARCHITECTURE.md, NilaiErapotController.php, migrations/

---

#### 1A-2: Add Foreign Key References
```sql
-- Migration: alter_nilai_add_references.php
ALTER TABLE nilai ADD COLUMN jadwal_pelajaran_id BIGINT UNSIGNED NULLABLE;
ALTER TABLE nilai ADD FOREIGN KEY (jadwal_pelajaran_id) 
  REFERENCES jadwal_pelajaran(id) ON DELETE RESTRICT;

ALTER TABLE nilai_erapor ADD COLUMN jadwal_pelajaran_id BIGINT UNSIGNED NULLABLE;
ALTER TABLE nilai_erapor ADD FOREIGN KEY (jadwal_pelajaran_id) 
  REFERENCES jadwal_pelajaran(id) ON DELETE RESTRICT;

-- Create indexes for performance
CREATE INDEX idx_nilai_jadwal ON nilai(jadwal_pelajaran_id, guru_id, siswa_id);
CREATE INDEX idx_nilai_erapor_jadwal ON nilai_erapor(jadwal_pelajaran_id, siswa_id);
```

**Owner:** @amhyer  
**Timeline:** 1 day  
**Validation:** Run migration, verify no data loss  

---

#### 1A-3: Standardize Soft Deletes
```php
// Current: Inconsistent
// nilai: soft deletes
// kehadiran: NO soft deletes
// kuis: soft deletes
// materi: soft deletes

// Action: Add SoftDeletes to ALL academic tables
// Reason: Compliance, audit trail, accidental deletion recovery

// Tables to update:
// - kehadiran, catatan, kebiasaan, dimensi
// - kokurikuler tables
// - dapodik_sync_logs
```

**Owner:** @amhyer  
**Timeline:** 1 day  
**Migration:** `add_soft_deletes_to_all_academic_tables.php`

---

### Phase 1B: Security Hardening

#### 1B-1: API Key Migration & Verification
```
Current: API keys now hashed, but legacy plaintext may exist

Action:
□ Audit api_keys table for unhashed entries
□ Migrate plaintext → hash using new verifyKey() method
□ Force regeneration of old keys
□ Log migration with audit trail

Code Location: app/Http/Middleware/ApiKeyMiddleware.php
```

**Owner:** @amhyer  
**Timeline:** 1 day  

---

#### 1B-2: XSS Audit & Remediation
```
Scan all Blade files for unescaped output:

Files to Review:
- resources/views/admin/** (20 files)
- resources/views/guru/** (25 files)
- resources/views/kepsek/** (8 files)
- resources/views/ortu/** (12 files)

Pattern to find: {!! $variable !!}
Action: Use {{ $variable }} unless HTML is explicitly needed

High-Risk Areas:
- Laporan views (may render user HTML)
- Integrasi views (Google Sheets data)
- Kepsek/pantau (aggregated data)
```

**Owner:** @amhyer  
**Timeline:** 3 days  

---

#### 1B-3: Rate Limiting Expansion
```php
// Current: Only registration throttled

// Add to routes/web.php:
Route::post('/login', [...])
    ->middleware('throttle:5,1');  // 5 attempts per minute

Route::post('/logout', [...])
    ->middleware('throttle:30,1'); // 30 per minute (already done ✅)

// Add to routes/api.php:
Route::prefix('api')->middleware('throttle:100,1')->group(function() {
    // All API calls: 100 per minute
    Route::post('/dapodik/sync/{modul}', ...)
        ->middleware('throttle:20,1');  // Stricter for heavy ops
});
```

**Owner:** @amhyer  
**Timeline:** 1 day  

---

### Phase 1C: Route & Resource Definition Cleanup

#### 1C-1: Standardize Resource Routes
```php
// Current issues: Inconsistent ->only() / ->except() usage

// Current:
Route::resource('kehadiran', KehadiranController::class)
    ->except(['show']);  // This still allows create, edit, update

// Should be:
Route::resource('kehadiran', KehadiranController::class)
    ->only(['index', 'store', 'destroy']);

// Apply to: kehadiran, catatan, dimensi, kuis, materi
```

**Owner:** @amhyer  
**Timeline:** 1 day  
**Validation:** Run `php artisan route:list` and verify 405 errors gone  

---

## 🚀 IMPROVEMENT PLAN PHASE 2: Features & Enhancement (Q1 2027)

### Phase 2A: Data Quality & Validation

#### 2A-1: Implement Input Validation Layer
```php
// Create: app/Services/ValidationService.php

class ValidationService {
    public function validateNilaiInput(array $data): void {
        // Nilai must be 0-100
        // mata_pelajaran must exist in mata_pelajaran table
        // siswa_id must exist and be active
        // guru_id must be current user or admin
        // semester_id must be active
    }
    
    public function validateErapotInput(array $data): void {
        // nilai_formatif + nilai_sumatif + nilai_sumatif_akhir valid
        // nilai_akhir calculated correctly
        // predikat matches calculated grade
    }
}
```

**Owner:** @amhyer  
**Timeline:** 3 days  

---

#### 2A-2: Create Missing Models (L3 Deferred Items)
```php
// Generate models for 9 tables without models
php artisan make:model CapaianPembelajaran
php artisan make:model Ekstrakurikuler // Already exists?
php artisan make:model NilaiCp
php artisan make:model NilaiMapel
php artisan make:model P5Projek
php artisan make:model P5Siswa
php artisan make:model Prestasi
php artisan make:model SiswaEkstrakurikuler
php artisan make:model DapodikDataCache

// Define relationships and scopes
```

**Owner:** @amhyer  
**Timeline:** 2 days  

---

### Phase 2B: Reporting & Analytics

#### 2B-1: Create Reporting Service Layer
```php
// app/Services/ReportingService.php

class ReportingService {
    public function getNilaiErapotPerSiswa(Siswa $siswa, Semester $semester);
    public function getKehadiranSummary(Guru $guru, Semester $semester);
    public function getPrestasiRanking(Rombel $rombel);
    public function getAnalysisBelajar(Siswa $siswa, Mata_Pelajaran $mapel);
}
```

**Owner:** @amhyer  
**Timeline:** 5 days  

---

### Phase 2C: Integration & Automation

#### 2C-1: Enhance Dapodik Sync
```
Current: Manual trigger

Planned:
□ Scheduled sync (via task scheduler)
□ Partial sync (only changed data)
□ Conflict resolution (local edits vs Dapodik)
□ Rollback capability
□ Change notification
```

**Owner:** @amhyer  
**Timeline:** 7 days  

---

#### 2C-2: Google Sheets Automation
```
Current: Manual export to Sheets

Planned:
□ Real-time sync on grade entry
□ Formula preservation (KKM, predicate calculation)
□ Sheet versioning
□ Automatic column mapping
□ Error recovery
```

**Owner:** @amhyer  
**Timeline:** 5 days  

---

## 🛡️ IMPROVEMENT PLAN PHASE 3: Production Hardening (Q2 2027)

### Phase 3A: Performance & Scalability

#### 3A-1: Database Query Optimization
```
Audit slow queries:
□ Kehadiran aggregation (O(n²) risk)
□ Nilai calculation with multiple JOINs
□ Erapor generation with 1000+ students
□ Report generation with large datasets

Action:
□ Add composite indexes
□ Use DB::select() for complex reports
□ Implement caching (Redis) for hot data
□ Pagination for large result sets
```

**Owner:** @amhyer  
**Timeline:** 5 days  

---

#### 3A-2: Implement Caching Strategy
```php
// Cache ttl = 1 hour for:
Cache::remember('guru-students-' . $guru_id, 3600, fn() => {
    return Siswa::where('guru_id', $guru_id)->get();
});

// Cache ttl = 1 day for:
Cache::remember('semesters', 86400, fn() => {
    return Semester::all();
});

// Cache ttl = on-demand for:
// - Nilai calculations
// - Report aggregations
// - Erapor PDF (regenerate weekly)
```

**Owner:** @amhyer  
**Timeline:** 3 days  

---

### Phase 3B: Monitoring & Observability

#### 3B-1: Setup Application Monitoring
```
Implement:
□ Laravel Telescope (already in composer.json) — enable in production
□ Sentry (error tracking)
□ New Relic (APM)
□ Custom activity logging

Track:
□ Login attempts (success/failure)
□ Data exports (who, what, when)
□ Dapodik syncs (success/failure/details)
□ Report generation time
□ Database query performance
```

**Owner:** @amhyer  
**Timeline:** 3 days  

---

#### 3B-2: Setup Alerting
```
Alert on:
□ Login failures > 5/minute → possible brute force
□ API key used from new IP → security check
□ Dapodik sync failure → escalate to admin
□ Database backup failure → critical
□ Disk space > 80% → maintenance
□ Response time > 5s → performance issue
```

**Owner:** @amhyer  
**Timeline:** 2 days  

---

### Phase 3C: Documentation & Training

#### 3C-1: Complete System Documentation
```
Update/Create:
□ User Manuals (Guru, Admin, Kepsek, Ortu)
□ Admin Setup Guide (fresh installation)
□ Dapodik Integration Guide
□ API Documentation (Bridge usage)
□ Troubleshooting Guide
□ Data Backup & Recovery Procedures
```

**Owner:** @amhyer  
**Timeline:** 10 days  

---

#### 3C-2: Developer Documentation
```
Create:
□ Architecture Deep-Dive (models, relations)
□ Service Layer Reference
□ Middleware Guide
□ Adding New Feature Workflow
□ Testing Strategy (unit, integration, feature)
□ Deployment Procedure
```

**Owner:** @amhyer  
**Timeline:** 5 days  

---

## 📈 METRICS & SUCCESS CRITERIA

### Quality Metrics

| Metric | Current | Target | Timeline |
|--------|---------|--------|----------|
| Test Coverage | ? | 70% | Q1 2027 |
| Critical Issues | 0 | 0 | Maintain |
| Response Time (p95) | ? | <2s | Q2 2027 |
| Uptime | ? | 99.5% | Q2 2027 |
| API Error Rate | ? | <0.1% | Q2 2027 |

### Security Metrics

| Check | Status | Target |
|-------|--------|--------|
| SQL Injection | ✅ Safe (Eloquent) | Maintain |
| XSS | ⚠️ Partial | 100% scanned & fixed by Q1 2027 |
| CSRF | ✅ Safe (Laravel) | Maintain |
| Password Policy | ✅ 8+ chars | Maintain |
| Rate Limiting | ⚠️ Partial | Expanded by Q1 2027 |
| API Key Security | ✅ Hashed | Migrate legacy by Dec 2026 |

### Performance Metrics

| Operation | Current | Target |
|-----------|---------|--------|
| Login | ? | <500ms |
| Load Erapor (100 students) | ? | <3s |
| Generate PDF | ? | <5s per student |
| Dapodik Sync (1000 students) | ? | <60s |

---

## 📅 IMPLEMENTATION ROADMAP

```
OCT 2026 (Now)
├─ Phase 1A: Data Model Clarity (2-3 days)
├─ Phase 1B: Security Hardening (2-3 days)
├─ Phase 1C: Route Cleanup (1 day)
└─ Buffer for issues (2-3 days)

NOV 2026
├─ Phase 2A: Data Quality & Missing Models (5-7 days)
├─ Phase 2B: Reporting Service (5 days)
├─ Testing & Bug Fixes (3 days)
└─ Documentation (5 days)

DEC 2026
├─ Phase 2C: Integration & Automation (7-10 days)
├─ UAT & Client Testing (5 days)
├─ Final Security Audit (2 days)
└─ Production Readiness Review (1 day)

JAN-FEB 2027
├─ Phase 3A: Performance Optimization (8 days)
├─ Load Testing & Tuning (5 days)
└─ Capacity Planning

MAR-APR 2027
├─ Phase 3B: Monitoring & Alerting (5 days)
├─ Phase 3C: Documentation & Training (15 days)
└─ Go-Live Preparation
```

---

## 🎯 CRITICAL PATH ITEMS (Must Complete Before Production)

1. ✅ Resolve nilai vs nilai_erapor (Phase 1A-1)
2. ✅ Add FK references (Phase 1A-2)
3. ✅ API Key migration (Phase 1B-1)
4. ✅ XSS audit complete (Phase 1B-2)
5. ✅ Route cleanup (Phase 1C-1)
6. ✅ Load testing results (Phase 3A)
7. ✅ Production checklist signed off

---

## 💾 TESTING STRATEGY

### Unit Tests (Target: 70% coverage)
```php
// Test each Service method
TestNilaiService, TestReportingService, TestDapodikSyncService

// Test Model relationships & scopes
TestNilaiModel, TestSiswaModel, TestGaruModel
```

### Feature Tests
```php
// Test complete workflows
TestNilaiInputWorkflow, TestErapotGenerationWorkflow
TestDapodikSyncWorkflow, TestUserLoginWorkflow
```

### Integration Tests
```php
// Test external integrations
TestDapodikWebServiceIntegration
TestGoogleSheetsIntegration
```

### Performance Tests
```php
// Load test e-Rapor generation with 1000+ students
// Dapodik sync with large datasets
// Concurrent user access (100+ simultaneous)
```

---

## 📋 DEPENDENCIES & BLOCKERS

| Blocker | Status | Resolution |
|---------|--------|-----------|
| nilai vs nilai_erapor clarity | 🟠 BLOCKING | Audit & document by Oct 15 |
| Client UAT schedule | ⏳ PENDING | Confirm timeline |
| Production server specs | ⏳ PENDING | Capacity planning needed |
| Data migration plan | ⏳ PENDING | Legacy system data? |
| Go-live date | ⏳ PENDING | Affects all timelines |

---

## 👥 TEAM ASSIGNMENTS

| Phase | Owner | Contributors | Hours |
|-------|-------|--------------|-------|
| 1A-1B-C | @amhyer | — | 20 |
| 2A-B-C | @amhyer | QA team | 40 |
| 3A-B-C | @amhyer | DevOps + QA | 50 |
| **TOTAL** | | | **110 hours** |

---

## 📞 DECISION POINTS

**Decision 1:** nilai vs nilai_erapor Strategy  
**Required by:** Oct 10, 2026  
**Impact:** Database schema, all grading features  

**Decision 2:** Production Deployment Timeline  
**Required by:** Oct 15, 2026  
**Impact:** Phase 3 scheduling  

**Decision 3:** Monitoring & Logging Infrastructure  
**Required by:** Nov 1, 2026  
**Impact:** Phase 3B implementation  

---

## ✅ NEXT STEPS

1. **This Week (Oct 5-11):**
   - [ ] Review this plan
   - [ ] Confirm Phase 1 timeline
   - [ ] Make Decision 1 (nilai vs nilai_erapor)
   - [ ] Begin Phase 1A-1

2. **Next Week (Oct 12-18):**
   - [ ] Complete Phase 1A (all sub-tasks)
   - [ ] Begin Phase 1B
   - [ ] Start code review for existing fixes

3. **Week 3 (Oct 19-25):**
   - [ ] Complete Phase 1B & 1C
   - [ ] Begin Phase 2A
   - [ ] Conduct security audit

---

**Document Version:** 1.0  
**Last Updated:** Oct 5, 2026  
**Next Review:** Oct 15, 2026

