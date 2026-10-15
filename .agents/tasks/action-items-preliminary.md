# Action Items - Preliminary Assessment

## Status: DRAFT (Waiting for workflow consolidation)

---

## PRIORITY 1: CBT Frontend Implementation

### What's Done
- ✅ Backend: Models, Controllers, Routes, Middleware, Migrations
- ✅ Database schema (7 tables)
- ⏳ NOT DONE: Views & JavaScript

### What's Needed
1. **Blade Templates** (10+ files)
   - Guru interface for managing question banks
   - Guru interface for managing test sessions
   - Student exam-taking interface (critical: must handle security & timer)
   - Monitoring dashboard for guru (live monitoring)
   
2. **JavaScript** (4+ files)
   - `integrity-checker.js` - Anti-cheat: detect tab switches, screen lock, copy/paste
   - `timer.js` - Countdown with auto-submit on timeout
   - `answer-saver.js` - Auto-save answers (prevents loss)
   - `question-navigator.js` - Question jumping interface

3. **Styling** - CBT-specific CSS for exam interface

### Estimated Effort: MEDIUM (3-5 days)
- High complexity for exam interface (must be bulletproof)
- Security is critical (integrity checks)
- UX must be smooth (timer, auto-save, navigation)

---

## PRIORITY 2: E-Rapor Alignment Implementation (Phases 2-7)

### Current Status
- ✅ Phase 1: Audit & Design (2026-10-04)
- ✅ Corrected matching rules (documented in design doc)
- ⏳ Phase 2-7: NOT STARTED

### Phases to Execute
**Phase 2: Database Changes**
- Migration: Add `jadwal_pelajaran_id` FK to `nilai_erapor` table
- Migration: Populate FK data using corrected matching rules
- Index: Add compound index for query performance

**Phase 3: Model & Relationships**
- Update `NilaiErapot` model: Add `jadwalPelajaran()` relation
- Update `JadwalPelajaran` model: Add `nilaiErapot()` relation
- Add `jadwal_pelajaran_id` to `$fillable`

**Phase 4: Service & Controller**
- Create `JadwalPelajaranResolver` service
  - `resolve()` - Match nilai to jadwal
  - `alignmentStats()` - Report matching statistics
- Update `NilaiErapotController@simpan()` - Auto-resolve FK
- Update `NilaiErapotController@autoSave()` - Per-row FK resolution
- Add `DataReferensiController@alignmentCheck()` - Alignment verification page

**Phase 5: Views & Frontend**
- Create `admin/referensi/alignment-check.blade.php`
- Show alignment statistics (4 cards)
- Table of unmatched reasons
- Menu entry in sidebar

**Phase 6: Testing**
- Unit tests: Model relationships, service matching
- Feature tests: Controller alignment check
- Migration: Data integrity, rollback safety

**Phase 7: Documentation & Deployment**
- Update `docs/11-ALIGNMENT-GUIDE.md` - Operational guide
- Update `docs/12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md` - Pre/during/post checks

### Estimated Effort: MEDIUM-LARGE (1-2 weeks)
- Design is done (good foundation)
- Migration & matching logic is critical
- Testing is important
- Phases must be executed in order

### Risk: Dapodik Re-import Handling
- Must verify `nullOnDelete` works with Dapodik sync
- Need to handle orphaned values gracefully

---

## PRIORITY 3: Dashboard Enhancements

### Current Situation
- ✅ All 5 dashboards exist (Guru, Siswa, Kepsek, Admin, Ortu)
- ✅ Guru, Siswa, Kepsek, Admin: Fully featured
- ⚠️ Ortu: Basic (needs enhancement)

### Ortu Dashboard Gaps
Current state (basic):
- Multi-child selector
- Per-child summary: nilai, predikat, kehadiran, kebiasaan, catatan

What's missing:
- [ ] More detailed value trends
- [ ] Attendance breakdown (H/S/I/A)
- [ ] Subject performance comparison
- [ ] Communication with guru
- [ ] CBT exam results view (if applicable)
- [ ] TKA assessment view (if applicable)
- [ ] Report card preview

### CBT Integration in Dashboards
- [ ] Guru dashboard: Add CBT test sessions & results link
- [ ] Siswa dashboard: Add CBT exam availability & past results
- [ ] Kepsek dashboard: Add CBT analytics (school-wide test metrics)
- [ ] Ortu dashboard: Add CBT exam results for child

### TKA Integration in Dashboards
- [ ] Similar integration as CBT (if TKA dashboard needed)

### Tryout Integration
- [ ] Similar integration (if needed)

### Estimated Effort: SMALL-MEDIUM (2-3 days)
- Most of the plumbing exists
- Just need to add links & widgets
- CBT/TKA integration depends on feature completion

---

## PRIORITY 4: Views Layer Completion

### CBT Views Status
- [ ] `resources/views/cbt/layouts/cbt-app.blade.php` - Layout
- [ ] `resources/views/cbt/question-banks/` - 5 views
- [ ] `resources/views/cbt/sessions/` - 5 views
- [ ] `resources/views/cbt/student/` - 3 views
- [ ] `resources/views/admin/cbt/` - Admin views (if not already there)

### E-Rapor Alignment Views
- [ ] `resources/views/admin/referensi/alignment-check.blade.php` - Main view

### Dashboard Views Status
- [ ] Check if all dashboard views exist in `resources/views/`
- [ ] Verify layout consistency across all roles
- [ ] Check if CBT integration exists in views

### Estimated Effort: DEPENDS (varies by scope)

---

## PRIORITY 5: Testing Suite

### Current Test Status
- ⏳ No specific tests found for CBT, Alignment, or Dashboard integration

### Tests Needed

**CBT Tests** (Feature + Unit)
- [ ] Model tests: Relationships, data integrity
- [ ] Controller tests: CRUD, security checks
- [ ] Middleware tests: Session lock, security
- [ ] Integration: Full exam flow

**E-Rapor Alignment Tests** (Phase 6)
- [ ] Service tests: Matching logic, edge cases
- [ ] Controller tests: Alignment check page
- [ ] Migration tests: Data integrity, rollback
- [ ] Integration: Full alignment flow

**Dashboard Tests** (Optional but good)
- [ ] Admin dashboard: Data calculations
- [ ] Ortu dashboard: Multi-child access control

### Estimated Effort: MEDIUM (2-3 days)

---

## SUMMARY TABLE

| Item | Status | Priority | Effort | Days |
|------|--------|----------|--------|------|
| CBT Frontend Views | ⏳ Pending | 🔴 HIGH | MEDIUM | 3-5 |
| CBT JavaScript | ⏳ Pending | 🔴 HIGH | MEDIUM | 2-3 |
| E-Rapor Phase 2-7 | ⏳ Pending | 🔴 HIGH | LARGE | 7-10 |
| Dashboard Ortu Enhancement | ⏳ Pending | 🟡 MEDIUM | SMALL | 1-2 |
| CBT Dashboard Integration | ⏳ Pending | 🟡 MEDIUM | SMALL | 1 |
| Testing Suite | ⏳ Pending | 🟡 MEDIUM | MEDIUM | 2-3 |
| Views Layer Verification | ⏳ Pending | 🟡 MEDIUM | SMALL | 1 |

---

## RECOMMENDED EXECUTION ORDER

### Week 1-2: CBT Frontend (Get CBT Working)
1. Create blade templates
2. Create JavaScript (timer, integrity, auto-save)
3. Test manually (e2e)
4. Create feature tests

### Week 2-3: E-Rapor Alignment (Critical Data Integrity)
1. Execute Phase 2-3 (Database + Models)
2. Execute Phase 4 (Service + Controllers)
3. Execute Phase 5 (Views)
4. Execute Phase 6 (Tests)
5. Execute Phase 7 (Documentation)

### Week 3-4: Enhancements (Polish & Integration)
1. CBT integration in dashboards
2. Ortu dashboard enhancement
3. Testing suite for dashboards
4. View layer verification

### Post: Deployment
- Staging testing
- Production deployment checklist
- User training

---

## Questions for User

1. **CBT Priority**: Should we focus on CBT frontend first, or E-Rapor alignment?
2. **Ortu Dashboard**: How detailed should the Ortu dashboard be?
3. **CBT Features**: Should we include report card generation from CBT results?
4. **TKA/Tryout**: Are TKA and Tryout features meant to integrate with CBT or separate?
5. **Testing**: What's the testing priority (feature tests, unit tests, both)?
6. **Timeline**: What's the target completion date for each area?

---

## Notes

- All 3 focus areas have solid foundation
- CBT backend is production-ready (just needs frontend)
- E-Rapor alignment is well-designed (just needs implementation)
- Dashboards are mostly complete (just needs CBT integration)
- No critical blockers identified
