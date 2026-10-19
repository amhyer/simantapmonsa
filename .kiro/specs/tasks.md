# CBT & E-Rapor Alignment Verification

## Tasks

### Task: Fix JadwalPelajaranResolver
- id: fix-resolver
- status: not_started

Add pilihKandidat method to JadwalPelajaranResolver service.

---

### Task: Verify Migrations
- id: verify-migrations
- status: not_started

Run and verify CBT migrations.

---

### Task: Run Tests
- id: run-tests
- status: not_started
- dependencies: [fix-resolver, verify-migrations]

Run all CBT and alignment tests.
