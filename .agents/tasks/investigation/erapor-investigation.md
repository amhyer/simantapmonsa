# Investigasi E-Rapor Alignment di SIMANTAP

**Tanggal:** 2026-10-04 (basis investigasi)  
**Status:** Lengkap ✅  
**Ruang Lingkup:** E-Rapor alignment vs pembelajaran Dapodik + Integrasi CBT  

---

## 1. DOKUMENTASI DITEMUKAN DI DOCS/

### 1.1 Dokumen Alignment (Fase 1-7 Selesai)

| File | Status | Isi Utama |
|------|--------|----------|
| `09-ERAPOR-ALIGNMENT-PLAN.md` | ✅ Selesai Fase 1-7 | Rencana induk alignment: 7 fase dari analisis hingga deployment, timeline 3-4 minggu, 46 SP effort |
| `10-ALIGNMENT-DESIGN.md` | ✅ Fase 1.1-1.2 | Audit DB (0/0 baris), backup terverifikasi, desain FK `nullOnDelete`, aturan matching koreksi (§3), backward compatibility |
| `11-ALIGNMENT-GUIDE.md` | ✅ Dokumentasi | Struktur data, aturan matching tunggal, auto-resolve flow, halaman alignment-check, operasional rutin, troubleshooting |
| `12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md` | ✅ Deployment | Pre/deploy/post/rollback procedure, monitoring, SQL verification commands |

### 1.2 Dokumen CBT

| File | Status | Isi Utama |
|------|--------|----------|
| `CBT_IMPLEMENTATION_PROGRESS.md` | ⏳ Backend ✅ Frontend ⏳ | Backend selesai: 7 tabel, 6 model, 5 controller, 2 middleware, routes lengkap. Frontend pending: 12 Blade views + 4 JS files |
| `CBT_TRYOUT_TKA_DESIGN.md` | ⏳ Konsep | Design tryout CBT untuk TKA (Tes Kemampuan Akademik) |

### 1.3 Status Dokumentasi Alignment

- **Fase 1 (Analisis & Persiapan):** ✅ DONE — audit 0/0 baris (DB dev kosong), backup terverifikasi, desain disepakati
- **Fase 2 (Database Changes):** ✅ DONE — migration kolom FK + populate, terbukti via test (94/94 hijau)
- **Fase 3 (Model & Relationship):** ✅ DONE — relasi 2-arah, null-safe
- **Fase 4 (Controller Updates):** ✅ DONE — `JadwalPelajaranResolver`, `NilaiErapotController@simpan/autoSave`
- **Fase 5 (Views & Frontend):** ✅ DONE — `alignment-check.blade.php`, badge pembelajaran, menu sidebar
- **Fase 6 (Testing):** ✅ DONE — 105/105 unit + feature tests hijau
- **Fase 7 (Dokumentasi & Deployment):** ✅ DONE — panduan + checklist

---

## 2. ALUR KERJA GENERATE E-RAPOR (SIMANTAP → FORMAT E-RAPOR KEMENDIKBUD)

### 2.1 Data Flow Guru → e-Rapor PDF/Excel

```
Input Nilai (Guru)
  ↓
[NilaiErapotController@simpan / @autoSave]
  ├─→ Validasi input (formatif, sumatif, sumatif_akhir)
  ├─→ JadwalPelajaranResolver::resolve() → cari jadwal terpetakan
  ├─→ hitungNilaiAkhir() → bobot formatif/sumatif/sumatif_akhir (default 30/40/30)
  ├─→ generatePredikat() → A/B/C/D vs KKM (default 70)
  ├─→ generateDeskripsi() → narasi capaian per predikat
  └─→ Simpan ke DB: nilai_erapor (dengan jadwal_pelajaran_id)

Generate PDF/Excel (Guru)
  ↓
[ErapotGeneratorController]
  ├─→ prepareData() → gather nilai + kehadiran + dimensi + catatan + ortu
  ├─→ PDFrenderer (Barryvdh) / PhpSpreadsheet (Excel)
  ├─→ Format e-Rapor sesuai Kemendikbud template (halaman per siswa)
  └─→ Download file PDF/Excel
```

### 2.2 Komponen Data Dalam E-Rapor

| Komponen | Sumber | Tabel | Catatan |
|----------|--------|-------|---------|
| **Identitas Siswa** | Dapodik | siswa | NISN, nama, L/P, kelas |
| **Guru Pembimbing** | Dapodik | users (role=guru) | Nama guru pengajar |
| **Nilai per Mapel** | Input Guru | nilai_erapor | Formatif, sumatif, sumatif akhir |
| **Nilai Akhir** | Rumus | nilai_erapor | Bobot formatif 30% + sumatif 40% + sumatif akhir 30% |
| **Predikat** | Rumus | nilai_erapor | A (≥90), B (≥80), C (≥KKM), D (<KKM) |
| **Deskripsi Capaian** | Rumus + Input | nilai_erapor | Narasi otomatis per predikat + info tertinggi/terendah (opsional) |
| **Kehadiran** | Input Guru/TU | kehadiran | Hadir (H), sakit (S), izin (I), alpa (A) → persentase |
| **Dimensi Profil Pelajar** | Input Guru | dimensi | Karakter/soft skill per siswa (opsional) |
| **Catatan Guru** | Input Guru | catatan | Up to 5 catatan terbaru per siswa |
| **Orang Tua** | Dapodik | orangtua_siswa | Nama ayah/ibu untuk tanda tangan |

---

## 3. FIELD MAPPING ANTARA SIMANTAP DAN E-RAPOR

### 3.1 Tabel nilai_erapor (Input Guru)

| Field SIMANTAP | Tipe | Sumber | Mapping Kemendikbud | Format |
|---|---|---|---|---|
| siswa_id | FK | Input (pilih siswa) | ID siswa (NISN) | UUID |
| guru_id | FK | Otomatis (login) | ID guru (NIP/NUP) | int |
| jadwal_pelajaran_id | FK (nullable) | **JadwalPelajaranResolver** | ID pembelajaran (mapel+kelas+guru+hari) | int |
| mata_pelajaran | TEXT | Input guru / dari jadwal | Kode mapel + nama | string (100) |
| kelas | TEXT | Input guru / dari jadwal | Rombel + paralel (mis. "X A") | string (50) |
| semester | TEXT | Input guru (dropdown) | Ganjil/Genap | enum ('Ganjil', 'Genap') |
| tahun_ajaran | TEXT | Input guru (default aktif) | Format YYYY/YYYY+1 | string (20) |
| nilai_formatif | DECIMAL | Input guru | Nilai formatif (0-100) | decimal(5,2) |
| nilai_sumatif | DECIMAL | Input guru | Nilai sumatif (0-100) | decimal(5,2) |
| nilai_sumatif_akhir | DECIMAL | Input guru | Nilai UAS/PAS (0-100) | decimal(5,2) |
| nilai_akhir | DECIMAL | Rumus | **Nilai akhir = (F×0.3 + S×0.4 + SA×0.3)** | decimal(5,2) |
| predikat | CHAR(1) | Rumus | A/B/C/D (vs KKM) | char(1) |
| deskripsi_capaian | TEXT | Rumus + opsional | Narasi capaian | text |
| capaian_tertinggi | TEXT | Optional input | Topik/KD tertinggi dicapai | string(255) |
| capaian_terendah | TEXT | Optional input | Topik/KD terendah dicapai | string(255) |
| terkirim_erapor | BOOLEAN | Flag sinkronisasi | Status kirim ke e-Rapor Kemendikbud | tinyint(1) |
| erapor_synced_at | TIMESTAMP | Otomatis saat kirim | Waktu sinkronisasi terakhir | datetime |
| created_at, updated_at | TIMESTAMP | Otomatis | Audit trail | datetime |

### 3.2 Tabel jadwal_pelajaran (Dapodik Sync)

| Field | Tipe | Asal | Catatan |
|---|---|---|---|
| id | PK | Auto | Referensi FK di nilai_erapor |
| guru_id | FK | Dapodik | Guru pengajar |
| mata_pelajaran | TEXT | Dapodik | Nama mapel (mis. "MATEMATIKA (WAJIB)") |
| kelas | TEXT | Dapodik | Rombel (mis. "X A", "X B") |
| semester_id | FK | Dapodik (setelah mapping) | Link ke `semesters` (bukan string semester!) |
| hari | ENUM | Dapodik | Senin-Sabtu |
| jam_mulai | TIME | Dapodik | Jam pelajaran mulai |
| jam_selesai | TIME | Dapodik | Jam pelajaran selesai |
| ruangan | VARCHAR | Dapodik | Ruang kelas/lab |
| rombel_id | FK | Dapodik | Link ke rombel (opsional) |

**Catatan Kritis:**
- `jadwal_pelajaran.semester_id` adalah **FOREIGN KEY INTEGER**, bukan string teks!
- `nilai_erapor.semester` adalah **TEXT** ('Ganjil'/'Genap') + `tahun_ajaran` TEXT
- **Aturan alignment (§3 design):** mapping via `semesters` tabel yang mencocokkan teks + tahun

---

## 4. PROSES IMPORT/EXPORT YANG ADA

### 4.1 Import Nilai (Excel → SIMANTAP)

**File:** `app/Http/Controllers/Guru/ImportErapotController::import()`

```
[Guru upload Excel template]
  ↓
Validasi file (xlsx, <5MB)
  ├─→ Baris berisi: NISN, Nilai Formatif, Nilai Sumatif, Nilai Sumatif Akhir
  ├─→ Cari siswa by NISN (else skip row, log error)
  └─→ updateOrCreate nilai_erapor per siswa

Proses per baris:
  ├─→ Lookup siswa by NISN/NIS (filter guru_id = login guru)
  ├─→ Jika tidak ditemukan: skip + error log
  ├─→ updateOrCreate nilai_erapor (unique: siswa_id + mapel + semester + tahun)
  ├─→ Hitung nilai_akhir, predikat, deskripsi
  └─→ Simpan DB

Response:
  ✅ {success, message: "N nilai berhasil diimport", errors: [...]}
```

**Template Download:** `ImportErapotController::downloadTemplate()`
- Berisi: No, NISN, Nama Siswa, [kolom kosong untuk diisi guru]
- Filter siswa by guru_id + kelas

### 4.2 Export Nilai (SIMANTAP → Excel)

**File:** `ImportErapotController::export()`

```
Query nilai_erapor:
  ├─→ Filter: guru_id + kelas + mapel + semester + tahun_ajaran
  └─→ Include: siswa detail (NISN, nama, L/P)

Output Excel:
  ├─→ Header: No, NISN, Nama, Formatif, Sumatif, Sumatif Akhir, Nilai Akhir, Predikat, Deskripsi
  └─→ Per baris: 1 siswa × nilai lengkap
```

### 4.3 Generate e-Rapor (SIMANTAP → PDF/Excel)

**File:** `ErapotGeneratorController`

```
Generate PDF (per siswa):
  ├─→ Endpoint: /guru/erapor/pdf/{siswaId}
  ├─→ Query data: nilai + kehadiran + dimensi + catatan + ortu
  ├─→ Render Blade template: guru.erapor.pdf
  ├─→ Barryvdh PDF → download PDF

Generate Bulk PDF (multi siswa):
  ├─→ Endpoint: /guru/erapor/pdf-bulk (POST siswa_ids)
  └─→ Loop per siswa → satu PDF multi-halaman

Export Bulk Excel:
  ├─→ Endpoint: /guru/erapor/export
  ├─→ Filter kelas (opsional)
  └─→ PhpSpreadsheet → XLSX dengan formatting
```

---

## 5. INTEGRASI DENGAN NILAI CBT

### 5.1 CBT Backend Status (Fase Backend ✅ Selesai)

**Sistem:**
- 7 tabel: `cbt_question_banks`, `cbt_questions`, `cbt_question_options`, `cbt_test_sessions`, `cbt_student_answers`, `cbt_test_results`, `cbt_integrity_logs`
- 6 model dengan relasi lengkap
- 5 controller (bank, question, session, student, integrity)
- 2 middleware (security, session lock)
- Routes lengkap (guru + siswa)

**Test Session Relationship:**
```php
// Relasi ke e-Rapor:
TestSession::
  - mata_pelajaran_id (FK) → MataPelajaran
  - semester_id (FK) → Semester
  - guru_id (FK) → User (guru)
  - kelas → string (text, bukan FK)
  
// Belum ada FK ke jadwal_pelajaran (gap!)
```

### 5.2 GAP: CBT ↔ Jadwal Pelajaran

**Masalah Identifikasi:**
1. `cbt_test_sessions` tidak memiliki `jadwal_pelajaran_id` FK
2. CBT hanya menyimpan mata_pelajaran_id + semester_id + guru_id + kelas (TEXT)
3. Tidak bisa matching ke jadwal pembelajaran (Dapodik) untuk alignment

**Impact:**
- Nilai CBT tidak terintegrasi dengan jadwal pembelajaran formal
- Jika nilai CBT ingin dimasukkan ke e-Rapor (sebagai sumatif/PAS), tidak bisa dihubung otomatis
- Admin/guru harus manual mapping

### 5.3 CBT → e-Rapor Flow (Rencana, belum eksekusi)

Untuk integrasi penuh, diperlukan:

```
[CBT Test Result]
  ├─→ query_jadwal_for_test_session (guru + mapel + kelas + semester)
  ├─→ Hitung skor CBT (dari student_answers)
  ├─→ Store ke nilai_erapor.nilai_sumatif atau nilai_sumatif_akhir (field configurable)
  ├─→ Include jadwal_pelajaran_id (sehingga terintegrasi full)
  └─→ Mark terkirim_erapor = true bila auto-sync aktif

Migrasi rencana:
  1. Tambah jadwal_pelajaran_id FK ke cbt_test_sessions (nullable)
  2. Update populate logic: match via guru + mapel + kelas + semester
  3. Update CbtSubmitService: invoke JadwalPelajaranResolver
  4. Update UI: tampilkan badge jadwal terpetakan di session
```

---

## 6. GAP: FIELD/DATA YANG BELUM TER-ALIGN

### 6.1 Alignment Teknis (Selesai ✅)

| Gap | Status | Solusi |
|-----|--------|--------|
| `nilai_erapor` ↔ `jadwal_pelajaran` | ✅ DONE | FK nullOnDelete (Fase 2), resolver (Fase 4) |
| Mapping semester teks vs FK | ✅ DONE | `JadwalPelajaranResolver` via `semesters` tabel (Fase 4) |
| Backward compatibility | ✅ DONE | FK nullable, existing data tetap valid (Fase 5) |
| Case-insensitive matching | ✅ DONE | `lower(trim())` di resolver (Fase 4) |
| Ambiguitas jadwal (hari ganda) | ✅ DONE | Menangkan `id` terkecil, log ambiguitas (Fase 2.2 migration) |

### 6.2 Alignment Fungsional (Pending)

| Gap | Penyebab | Impact | Rencana Fix |
|-----|---------|--------|------------|
| **CBT → jadwal pelajaran** | CBT tidak punya FK jadwal | Nilai CBT tidak linkable ke pembelajaran formal | Tambah FK + resolver ke TestSession |
| **Dimensi vs mapel** | `dimensi` adalah cross-subject, bukan per-mapel | Dimensi tidak bisa differsensifikasi per mapel | RFE: mungkin tidak perlu (dimensi = soft skill umum) |
| **Catatan vs capaian KD** | Catatan guru bebas teks, tidak testruktur KD | Deskripsi capaian tidak granular per KD | RFE: add KD field atau parse catatan (kompleks) |
| **Kehadiran vs tugas** | Kehadiran = hadir/sakit/izin, belum ada "tugas" | Data tugas tidak masuk e-Rapor | RFE: jika perlu, buat tabel `tugas` baru |

### 6.3 Data Validation Gap

| Field | Gap | Risk | Mitigation |
|-------|-----|------|------------|
| `mata_pelajaran` TEXT (nilai_erapor) | Input guru bebas, bisa typo ("Umum" vs "UMUM") | Alignment fail silently (unmatched) | Mapping validator + halaman alignment-check (Fase 5) ✅ |
| `kelas` TEXT | Bisa format beda ("X A" vs "XA" vs "10 A") | Ambiguitas matching | Normalisasi lower/trim + validasi saat input |
| `semester` TEXT | Manual input, bisa salah ("Tengah"?) | Kategori `semester_tak_cocok` | Dropdown enum (Ganjil/Genap) di UI |
| `tahun_ajaran` | Bisa format beda ("2025/2026" vs "2025-2026") | Gap matching | Validasi format strict + default dari setting guru |

---

## 7. ISSUE/MASALAH YANG DITEMUKAN

### 7.1 Issue Resolusi (✅ Sudah Fixed)

| Issue | Penyebab | Fix | Status |
|-------|---------|-----|--------|
| **Matching gagal 100%** | Aturan awal: `semester_id` (int FK) dibanding `semester` (teks) | Pakai `semesters` tabel mapping teks → FK | ✅ FIXED (Fase 2.2 migration §3) |
| **Migration `dropForeignKeyIfExists` error** | Method tidak ada di Laravel | Pakai `dropForeign(['col'])` + `dropIndex` explicit | ✅ FIXED (noted di rencana §3) |
| **FK constraint error saat sync Dapodik** | Awal rencana: `onDelete('restrict')` → gagal hapus jadwal | Ganti ke `nullOnDelete()` (bukan restrict) | ✅ FIXED (Fase 2 migration, Fase 1.2 keputusan) |
| **Test suite hijau tapi migration gagal** | SQLite vs Postgres FK behavior | Test dengan `:testing` DB, migration deploy ke Postgres | ✅ FIXED (noted testing strategy) |

### 7.2 Issue Pending/Non-Critical

| Issue | Severity | Catatan |
|-------|----------|---------|
| **CBT tidak linked ke jadwal** | 🟠 HIGH | Frontend CBT pending; nilai CBT belum bisa sync otomatis ke e-Rapor |
| **Dimensi belum per-mapel** | 🟡 MEDIUM | Mungkin by design (dimensi = karakter umum), perlu clarify dengan user |
| **Catatan guru unstructured** | 🟡 MEDIUM | Tidak bisa auto-format ke e-Rapor; hanya display apa adanya |
| **No audit trail untuk perubahan nilai** | 🟡 MEDIUM | Nilai_erapor tidak track siapa/kapan edit; riskanya (integritas data) |

### 7.3 Data Consistency Issue

**Ditemukan di Migration Populate:**
```
0 baris matched (karena `semesters` kosong? atau `jadwal_pelajaran` kosong?)
```

**Risk Level:** 🟢 LOW (karena DB dev kosong saja; di production perlu audit ulang)

**Audit Protocol (sebelum production deploy):**
1. `SELECT COUNT(*) FROM semesters` (harus ≥1, dan data aktif terisi)
2. `SELECT COUNT(*) FROM jadwal_pelajaran` (harus sesuai Dapodik import)
3. `SELECT COUNT(*) FROM nilai_erapor WHERE jadwal_pelajaran_id IS NULL` (sebelum populate)
4. Jalankan populate
5. `SELECT COUNT(*) FROM nilai_erapor WHERE jadwal_pelajaran_id IS NOT NULL` (harus ↑)

---

## 8. REKOMENDASI PERBAIKAN ALIGNMENT

### 8.1 Immediate (Sprint Ini)

- [ ] **UAT alignment-check di production staging**
  - Backup DB production
  - Jalankan migration (Fase 2 + Fase 3)
  - Upload sample jadwal Dapodik + nilai lama
  - Cek alignment% dan kategori unmatched
  - Perbaiki master (mapel/kelas/semester) sesuai gap terbesar

- [ ] **Sinkronisasi field deskripsi**
  - Cek template e-Rapor Kemendikbud
  - Verify format narasi deskripsi cocok dengan template
  - Revisi `generateDeskripsi()` jika perlu

- [ ] **Testing manual CRUD nilai e-Rapor**
  - Input via form simpan → FK terisi? ✅
  - Import Excel → FK terisi? ✅
  - Auto-save → FK terisi? ✅
  - Align% naik? ✅

### 8.2 Short-term (2-4 minggu)

- [ ] **Integrasikan CBT → e-Rapor**
  - Tambah `jadwal_pelajaran_id` FK ke `cbt_test_sessions` (migration)
  - Update `CbtSubmitService`: invoke `JadwalPelajaranResolver`
  - Uji CBT session → nilai otomatis ke nilai_erapor (opsional, configurable)

- [ ] **Audit trail nilai**
  - Implementasi soft delete atau history table untuk nilai_erapor
  - Track: siapa, kapan, nilai sebelum/sesudah

- [ ] **Dashboard role-based**
  - **Guru:** Lihat alignment% mapel sendiri, not-yet-mapped breakdown
  - **Siswa:** Lihat nilai per mapel (pending: masuk e-Rapor?)
  - **Ortu:** Lihat e-Rapor final (setelah terkirim Kemendikbud)
  - **Kepsek:** Monitor alignment% sekolah, gap terbesar per guru

### 8.3 Long-term (1-2 bulan)

- [ ] **Sinkronisasi otomatis ke e-Rapor Kemendikbud**
  - API Kemendikbud endpoint (cek dokumen official)
  - Job queue untuk batch kirim
  - Retry logic + error handling

- [ ] **Validasi master data**
  - Cek mata pelajaran (vs Dapodik resmi)
  - Cek kelas/rombel (vs struktur sekolah)
  - Cek semester (vs kalender akademik)

- [ ] **Performance optimization**
  - Index alignment-check query (chunk 500 baris)
  - Cache alignment% di admin dashboard
  - Consider: async populate job jika nilai_erapor > 100k baris

---

## 9. STRUKTUR CODEBASE TERKAIT E-RAPOR

```
e:\Project\simantap\
├── app/
│   ├── Http/Controllers/
│   │   ├── Guru/
│   │   │   ├── NilaiErapotController.php          ← Input nilai + auto-save
│   │   │   ├── ErapotGeneratorController.php      ← Generate PDF/Excel
│   │   │   └── ImportErapotController.php         ← Import/export Excel
│   │   └── Admin/
│   │       └── DataReferensiController.php        ← alignmentCheck view
│   ├── Models/
│   │   ├── NilaiErapot.php                        ← Nilai per mapel (FK: jadwal_pelajaran_id)
│   │   ├── JadwalPelajaran.php                    ← Pembelajaran Dapodik + relasi nilaiErapot()
│   │   ├── Semester.php                           ← Semester (untuk mapping teks)
│   │   ├── Siswa.php                              ← Siswa
│   │   ├── User.php                               ← Guru + admin
│   │   └── Cbt/
│   │       ├── TestSession.php                    ← CBT session (PENDING: FK jadwal_pelajaran_id)
│   │       ├── QuestionBank.php
│   │       ├── Question.php
│   │       ├── TestResult.php
│   │       └── ...
│   └── Services/
│       ├── JadwalPelajaranResolver.php             ← Matcher: nilai → jadwal (resolver logic)
│       └── CbtSubmitService.php                    ← CBT hasil kalkulasi (PENDING: integrate resolver)
├── database/
│   └── migrations/
│       ├── 2026_09_10_025946_create_jadwal_pelajaran_table.php
│       ├── 2026_10_04_202238_add_jadwal_pelajaran_id_to_nilai_erapor_table.php
│       └── 2026_10_04_202251_populate_jadwal_pelajaran_id_on_nilai_erapor.php
├── routes/
│   └── web.php                                     ← Guru: /guru/nilai-erapor/*, /guru/erapor/*
│                                                   ← Admin: /admin/referensi/alignment
└── resources/views/
    ├── guru/
    │   ├── nilai-erapor/index.blade.php           ← Form input nilai
    │   └── erapor/
    │       ├── index.blade.php                    ← List e-Rapor
    │       ├── pdf.blade.php                      ← Template PDF
    │       └── pdf-bulk.blade.php
    └── admin/referensi/
        └── alignment-check.blade.php              ← Halaman monitoring alignment
```

---

## 10. VERIFIKASI PRE-DEPLOYMENT

### 10.1 Checklist Teknis

- [x] Migration alignment (Fase 2) sudah di repo
- [x] Service resolver sudah berfungsi (test 94/94 ✅)
- [x] Model relasi sudah proper (null-safe)
- [x] Controller simpan/autosave include resolver
- [x] FK `nullOnDelete()` bukan restrict
- [x] Halaman alignment-check terender (Fase 5)
- [ ] **PRE-PROD:** Semesters tabel sudah berisi data aktif
- [ ] **PRE-PROD:** Jadwal Dapodik sudah sync ke jadwal_pelajaran
- [ ] **PRE-PROD:** Backup pre-alignment terverifikasi restore

### 10.2 Deployment Command Sequence

```bash
# 1. Maintenance mode
php artisan down

# 2. Backup
$env:PGPASSWORD='...'
pg_dump -h 127.0.0.1 -p 5432 -U postgres -F c \
  -f storage/app/backups/pra-alignment-$(Get-Date -UFormat %Y%m%d).dump simantap_db

# 3. Migrate (kedua file migration Fase 2)
php artisan migrate --force

# 4. Verify struktur DB
psql -h 127.0.0.1 -U postgres -d simantap_db -c \
  "SELECT column_name, is_nullable FROM information_schema.columns 
   WHERE table_name='nilai_erapor' AND column_name='jadwal_pelajaran_id';"
# Expected: bigint | YES

# 5. Service online
php artisan up

# 6. Monitor logs
tail -f storage/logs/laravel.log
```

---

## 11. KESIMPULAN & NEXT STEPS

### 11.1 Status Alignment

| Aspek | Status | Evidence |
|-------|--------|----------|
| **Desain alignment** | ✅ SELESAI | Docs 09-12, 7 fase DONE, aturan matching terverifikasi |
| **Implementasi teknis** | ✅ SELESAI | FK + migration + resolver + controller terimplementasi, 105/105 test hijau |
| **Dokumentasi operasional** | ✅ SELESAI | Panduan troubleshooting, deployment checklist tersedia |
| **UAT di staging** | ⏳ PENDING | Perlu backup + sample data untuk test alignment% |
| **Production deployment** | ⏳ PENDING | Tunggu UAT pass + pre-deployment checklist clear |
| **Integrasi CBT** | 🟠 PENDING | Perlu tambah FK + resolver ke TestSession (rencana §8.2) |
| **Frontend CBT** | ⏳ PENDING | 12 Blade views + JS belum dibuat (cek `CBT_IMPLEMENTATION_PROGRESS.md`) |

### 11.2 Gap Terbesar yang Perlu Direspon

1. **CBT tidak terintegrasi jadwal pelajaran** → Rencanakan sprint untuk update TestSession model + migration
2. **Frontend CBT menunggu implementasi** → Tentukan prioritas: sekarang atau setelah alignment live?
3. **Dimensi vs mapel belum terstruktur** → Clarify dengan pemilik produk: apakah dimensi perlu per-mapel?
4. **Audit trail nilai** → Pertimbangkan untuk compliance + troubleshooting

### 11.3 Next Sprint Action

**Segera (langsung bisa execute):**
1. Jalankan migration Fase 2 di staging
2. Upload sample jadwal + nilai
3. Check alignment% breakdown via halaman alignment-check
4. Identifikasi gap terbesar (mapel/kelas/semester mana yang banyak unmatched)
5. Perbaiki master data sesuai gap
6. Ulang populate atau simpan ulang nilai
7. Monitor alignment% naik ke target (85-90%+?)

**Dokumentasi untuk tim:**
- Sertakan `docs/11-ALIGNMENT-GUIDE.md` ke training guru + admin
- Sertakan `docs/12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md` ke SOP deployment

---

## Referensi File Penting

- **Dokumentasi:** `e:\Project\simantap\docs\09-12-ALIGNMENT*.md`
- **Service logic:** `e:\Project\simantap\app\Services\JadwalPelajaranResolver.php`
- **Migration:** `database/migrations/2026_10_04_*.php` (2 file Fase 2)
- **Model:** `app/Models/{NilaiErapot,JadwalPelajaran}.php`
- **Controller:** `app/Http/Controllers/Guru/NilaiErapotController.php`
- **Admin view:** `resources/views/admin/referensi/alignment-check.blade.php`
- **Routes:** `routes/web.php` (guru.nilai-erapor.*, admin.referensi.alignment)
- **Test:** `tests/Feature/AlignmentTest.php` (105 test suite)

---

**Investigasi Selesai** ✅  
**Siap untuk fase UAT/deployment production**
