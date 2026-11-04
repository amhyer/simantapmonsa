# Bedah Teknis Mesin Ujian SIMANTAP

**CBT, Tryout, engine terpadu `exam_*`, dan TKA**

| | |
|---|---|
| Tanggal | 4 November 2026 |
| Commit acuan | `76ea58f docs: tambah peta proyek menyeluruh` |
| Cakupan | CBT pilihan ganda, Tryout, engine `exam_*`, analisis butir, integritas, TKA |
| Sumber kebenaran | kode di `app/`, migrasi `database/migrations/`, `routes/web.php`, `routes/console.php`, test di `tests/Feature/` |
| Dokumen terkait | `docs/13-PETA-PROYEK.md`, `docs/CBT.md`, `docs/CBT_TRYOUT_TKA_DESIGN.md`, `docs/REKONSILIASI-FASE-3.md`, `docs/ANALISIS-BUTIR.md`, `docs/RUNBOOK-GO-LIVE.md` |
| Sifat dokumen | Deskriptif + operasional. Tidak menggantikan test; semua klaim di sini dapat dilacak ke berkas yang disebut. |

> Dokumen ini menjawab satu pertanyaan: **"ujian di SIMANTAP sebenarnya bekerja bagaimana, dari klik siswa sampai nilai tersimpan?"** Fokusnya mesin, bukan tampilan.

---

## 0. Ringkasan eksekutif

SIMANTAP sedang memindahkan dua mesin ujian lama ke satu engine terpadu:

| Mesin lama | Tabel lama | Tabel baru | Status penulisan |
|---|---|---|---|
| CBT pilihan ganda | `cbt_*` | `exam_*` | **Dual-write** (engine + mirror ke `cbt_*`) |
| Tryout | `tryout_*` | `exam_*` | **Engine sebagai sumber kebenaran**, `tryout_*` dicerminkan |
| TKA diagnostik | `tka_*`, `dimensi`, `kebiasaan` | tidak ikut engine | Jalur terpisah, tidak berubah |
| Simulasi TKA | session browser | — | Prototipe, belum persisten |

Pola yang dipakai adalah **strangler**:

1. Engine baru (`exam_*`) ditambahkan tanpa menyentuh tabel lama.
2. Jalur tulis dialihkan bertahap lewat *bridge*.
3. Pembaca (dasbor, laporan) dipindahkan belakangan lewat flag `exam_engine_readers`.
4. Tabel lama baru dihapus setelah backfill + rekonsiliasi isi membuktikan parity.

### Status implementasi (bukti dari kode)

| Tahap | Bukti | Status |
|---|---|---|
| Skema `exam_*` | `2026_10_20_000002_create_exam_engine_tables.php` | Selesai (10 tabel) |
| Pemetaan legacy → engine | `app/Services/Exam/LegacyExamMapper.php` | Selesai, idempoten |
| Tulis CBT ke engine | `CbtEngineBridge`, dipanggil `CbtExamService` | Aktif via `simantap.exam_engine_cbt` (default `true`) |
| Tulis Tryout ke engine | `TryoutEngineBridge` | Aktif via `simantap.exam_engine_tryout` (default `true`) |
| Backfill data lama | `php artisan exam:backfill` | Selesai, ada `--dry-run` dan `--verify` |
| Rekonsiliasi isi | `php artisan exam:rekonsiliasi` | Selesai, read-only |
| Pembaca pindah ke engine | `CbtStudentReader`, `TryoutStudentReader`, `CbtDashboardProvider`, `TryoutResultProvider` | Selesai, default **nonaktif** (`exam_engine_readers=false`) |
| Laporan engine-only | `ExamReportService` | Selesai, belum jadi default |
| Hapus tabel lama | — | **Belum**. Menunggu parity produksi |
| Penilaian esai manual | — | **Belum ada**; esai bernilai 0 |

---

## 1. Peta berkas dan tanggung jawab

### 1.1 Inti engine

| Berkas | Tanggung jawab | Catatan |
|---|---|---|
| `app/Services/ExamAttemptService.php` | Membuat/melanjutkan attempt, menyimpan jawaban, menilai attempt, menyimpan `exam_results` | Satu-satunya pemilik siklus engine murni |
| `app/Services/ExamGradingService.php` | Aturan benar/salah dan poin per tipe soal | Satu-satunya penilai; tidak menerima kunci dari klien |
| `app/Services/Exam/LegacyExamMapper.php` | Memetakan `cbt_*`/`tryout_*` → `exam_*` | Satu-satunya tempat aturan pemetaan |
| `app/Services/Exam/ExamReconciliationService.php` | Membandingkan isi legacy vs engine | Read-only, dipakai command |
| `app/Services/Exam/ExamItemAnalysisService.php` | P, D, distraktor butir soal | Read-only |
| `app/Services/Exam/ExamReportService.php` | Laporan hasil yang hanya membaca `exam_*` | Bukti jalur "tanpa tabel lama" |
| `app/Services/ExamDashboardService.php` | Dasbor peran yang hanya membaca `exam_*` | Dipakai provider saat flag aktif |

### 1.2 Bridge dan pemilik siklus lama

| Berkas | Peran |
|---|---|
| `app/Services/CbtExamService.php` | **Pemilik siklus CBT**: publish, open, begin, answer, submit, complete, finalizeExpired, rekam pelanggaran |
| `app/Services/CbtEngineBridge.php` | Lapisan tulis CBT ke `exam_*` (bukan pengganti `CbtExamService`) |
| `app/Services/TryoutEngineBridge.php` | Siklus Tryout memakai engine, lalu mencerminkan ke `tryout_*` |
| `app/Services/CbtScoring.php` | Penilaian CBT legacy berbasis snapshot (tetap dipakai sebagai sumber nilai `cbt_results`) |
| `app/Services/TryoutScoring.php` | Penilaian Tryout legacy untuk mode flag off |

### 1.3 Pembaca berflag

| Berkas | Membaca | Fallback |
|---|---|---|
| `app/Services/Exam/CbtStudentReader.php` | Daftar ujian CBT siswa | `cbt_test_sessions` |
| `app/Services/Exam/TryoutStudentReader.php` | Sesi + riwayat Tryout siswa | `tryout_*` |
| `app/Services/Exam/CbtDashboardProvider.php` | Kartu dasbor CBT (siswa/guru/kepsek) | `cbt_*` |
| `app/Services/Exam/TryoutResultProvider.php` | Daftar + rincian hasil Tryout admin | `tryout_*` |

### 1.4 Controller dan rute utama

| Rute | Controller | Keterangan |
|---|---|---|
| `cbt.student.*` | `Cbt\CbtStudentController` | Siswa: daftar, mulai, jawab, submit, hasil |
| `cbt.*` | `Cbt\CbtQuestionBankController`, `CbtQuestionController`, `CbtTestSessionController` | Guru: bank soal dan sesi |
| `cbt.student.integrity` | `Cbt\CbtIntegrityController` | Telemetri pelanggaran |
| `admin.cbt.*` | `Admin\Cbt\CbtBankController`, `CbtSessionController` | Admin: bank, sesi, ekspor, analisis butir |
| `admin.tryout.*` | `Admin\Tryout\TryoutPackageAdminController` | Admin: paket, sesi, hasil, ekspor |
| `tryout.*` | `Tryout\TryoutSessionController`, `TryoutPackageController` | Guru: paket dan sesi |
| `tryout.student.*` | `Tryout\TryoutStudentController` | Siswa: mulai, jawab, selesai, hasil |
| `tka.*`, `admin.tka.*`, `tka.parent.*` | `Tka\*`, `Guru\TKAController`, `Admin\Tka\TkaAdminController` | TKA diagnostik (bukan `exam_*`) |
| `tka-simulasi.*` | `Siswa\TkaSimulasiController` | Simulasi TKA berbasis session |

### 1.5 Command

| Command | Berkas | Sifat |
|---|---|---|
| `exam:backfill` | `app/Console/Commands/ExamBackfill.php` | Menyalin data; `--dry-run`, `--verify`, `--only=cbt|tryout` |
| `exam:rekonsiliasi` | `app/Console/Commands/ExamReconciliation.php` | Read-only; `--json`, `--batas`, `--only` |
| `exam:analisis-butir` | `app/Console/Commands/AnalisisButir.php` | Read-only; `--cbt=ID` atau `--engine=ID` |
| `cbt:finalize-expired` | `routes/console.php` | Dijadwalkan tiap menit saat `cbt_enabled=true` |

---

## 2. Pemetaan konsep legacy → engine terpadu

### 2.1 Perbedaan bentuk data

| Konsep | CBT lama | Tryout lama | Engine terpadu |
|---|---|---|---|
| Wadah soal | `cbt_question_banks` | `tryout_packages` | `exam_banks` |
| Pengelompokan soal | tidak ada | `tryout_subjects` | `exam_sections` (opsional) |
| Soal | `cbt_questions` (opsi JSON) | `tryout_questions` + `tryout_question_options` | `exam_questions` + `exam_question_options` |
| Jadwal ujian | `cbt_test_sessions` | `tryout_sessions` | `exam_sessions` |
| Pengerjaan | `cbt_results` (jawaban JSON) | `tryout_results` + `tryout_student_answers` | `exam_attempts` + `exam_answers` |
| Nilai akhir | kolom di `cbt_results` | kolom di `tryout_results` | `exam_results` |
| Pelanggaran | `cbt_violations` | — | `exam_violations` |

Perbedaan filosofisnya:

- CBT lama menyimpan **seluruh jawaban dalam satu kolom JSON** di `cbt_results.answers`.
- Tryout lama menyimpan jawaban **per baris** di `tryout_student_answers`.
- Engine menyatukan keduanya menjadi `exam_answers` per `(attempt_id, question_id)`.

### 2.2 Kunci jejak asal

Semua tabel engine menyimpan:

```text
sumber_tabel VARCHAR(60) NULL
sumber_id    BIGINT UNSIGNED NULL
```

Nilai yang dipakai (`LegacyExamMapper`):

| Konstanta | Nilai |
|---|---|
| `SUMBER_CBT_BANK` | `cbt_question_banks` |
| `SUMBER_CBT_SOAL` | `cbt_questions` |
| `SUMBER_CBT_SESI` | `cbt_test_sessions` |
| `SUMBER_TRYOUT_PAKET` | `tryout_packages` |
| `SUMBER_TRYOUT_MAPEL` | `tryout_subjects` |
| `SUMBER_TRYOUT_SOAL` | `tryout_questions` |
| `SUMBER_TRYOUT_SESI` | `tryout_sessions` |

Selain itu, baris hasil memakai jejak tambahan:

| Tabel | `sumber_tabel` | `sumber_id` |
|---|---|---|
| `exam_attempts` | `cbt_results` | id `cbt_results` |
| `exam_attempts` | `tryout_results` | id `tryout_results` |
| `exam_results` | `cbt_results` | id `cbt_results` |
| `exam_results` | `tryout_results` | id `tryout_results` |
| `exam_violations` | `cbt_violations` | id `cbt_violations` |

**Invarian:** pasangan `(sumber_tabel, sumber_id)` adalah kunci natural. Semua operasi tulis memakai `updateOrCreate` pada pasangan ini, sehingga backfill dan alur langsung tidak pernah menggandakan baris.

### 2.3 Aturan pemetaan per entitas

| Legacy | Engine | Aturan |
|---|---|---|
| `cbt_question_banks` | `exam_banks` | `nama=title`, `mata_pelajaran=subject`, `guru_id` disalin, `engine_id` = engine `cbt` |
| `cbt_questions` | `exam_questions` | `tipe=pilihan_ganda`, `poin=1`, `pertanyaan=prompt`; opsi dari JSON `options` |
| Opsi CBT | `exam_question_options` | `adalah_benar` bila huruf = `correct_answer`; `urutan` dari angka huruf (A=1) |
| `cbt_test_sessions` | `exam_sessions` | `nama=title`, `mulai_at=opens_at`, `selesai_at=closes_at`, `soal_snapshot`, `peserta_ids` |
| `tryout_packages` | `exam_banks` | `kode=kode_paket`, `nama=nama_paket`; atribut paket masuk `meta` (`passing_grade`, `jumlah_mapel`, `durasi_total_menit`, `deskripsi`) |
| `tryout_subjects` | `exam_sections` | `nama` dari `mataPelajaran`, `bobot`, `durasi_menit`, `passing_grade` |
| `tryout_questions` | `exam_questions` | `tipe=tipe_soal`, `poin`, `media_*`, `pembahasan`, `aktif` |
| `tryout_sessions` | `exam_sessions` | `kode=kode_sesi`, `nama=nama_sesi`, `status` disalin apa adanya (`ongoing`, dst.) |

### 2.4 Sinkronisasi penghapusan

`LegacyExamMapper` menyediakan `lupakanBankCbt`, `lupakanSoalCbt`, `lupakanSesiCbt`, `lupakanPaketTryout`, `lupakanSesiTryout`.

- Penghapusan **tidak** menghapus permanen karena `exam_banks`, `exam_questions`, dan `exam_sessions` memakai soft delete.
- Pemanggilnya ada di CRUD CBT/Tryout (lihat `CbtCrudEngineSyncTest`, `TryoutCrudEngineSyncTest`).
- Opsi soal yang dihapus di bank ikut dihapus dari engine saat soal dipetakan ulang (`whereNotIn('huruf', $hurufAktif)->delete()`), sehingga opsi yatim tidak tertinggal.

---

## 3. Skema `exam_*`

Sumber tunggal: `database/migrations/2026_10_20_000002_create_exam_engine_tables.php` dan `2026_10_20_000003_create_exam_violations_table.php`. Semua pembuatan tabel dibungkus `Schema::hasTable()` agar migrasi aman dijalankan pada basis data yang sudah sebagian memiliki tabel.

### 3.1 `exam_engines`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `kode` | string(30) unik | `cbt` atau `tryout` |
| `nama` | string(100) | |
| `jenis` | string(30), default `cbt` | |
| `deskripsi` | text nullable | |
| `aktif` | boolean, default true | |

Konstanta: `Engine::CBT = 'cbt'`, `Engine::TRYOUT = 'tryout'`.

### 3.2 `exam_banks`

| Kolom | Tipe | Catatan |
|---|---|---|
| `engine_id` | FK `exam_engines`, cascade | |
| `guru_id` | FK `users`, null on delete | |
| `kode` | string unik nullable | Kode paket Tryout |
| `nama` | string | |
| `mata_pelajaran` | string nullable | Hanya CBT |
| `sumber_tabel`, `sumber_id` | jejak asal | |
| `meta` | JSON nullable | Atribut paket Tryout |
| `deleted_at` | soft delete | |

Index: `(engine_id, guru_id)`, `(sumber_tabel, sumber_id)`.

### 3.3 `exam_sections`

Dipakai engine berjenjang (Tryout: paket → mapel).

| Kolom | Tipe | Catatan |
|---|---|---|
| `bank_id` | FK cascade | |
| `nama` | string nullable | |
| `mata_pelajaran_id` | FK `mata_pelajaran`, null on delete | |
| `urutan` | unsigned int, default 0 | |
| `bobot` | decimal(6,3), default 1 | Dipakai skoring berbobot |
| `durasi_menit` | unsigned int nullable | |
| `passing_grade` | decimal(5,2) nullable | |
| `sumber_tabel`, `sumber_id`, `meta` | jejak + tambahan | |

### 3.4 `exam_questions`

| Kolom | Tipe | Catatan |
|---|---|---|
| `bank_id` | FK cascade | |
| `section_id` | FK `exam_sections`, null on delete | |
| `urutan` | unsigned int | |
| `tipe` | string(30), default `pilihan_ganda` | `pilihan_ganda`, `benar_salah`, `isian_singkat`, `essay` |
| `pertanyaan` | text | |
| `media_type`, `media_url` | string | |
| `poin` | decimal(8,2), default 1 | |
| `tingkat_kesulitan` | string(20) nullable | |
| `pembahasan` | text nullable | |
| `kunci_jawaban` | text nullable | Kunci eksplisit; **disembunyikan dari serialisasi** |
| `aktif` | boolean | |
| `sumber_tabel`, `sumber_id`, `meta` | jejak | |
| `deleted_at` | soft delete | |

Model `Exam\Question` menambahkan `protected $hidden = ['kunci_jawaban']`, sehingga kunci tidak pernah ikut `toArray()`/JSON ke klien.

### 3.5 `exam_question_options`

| Kolom | Tipe | Catatan |
|---|---|---|
| `question_id` | FK cascade | |
| `huruf` | string(5) | |
| `isi_opsi` | text | |
| `adalah_benar` | boolean, default false | Kunci alternatif untuk data Tryout lama |
| `urutan` | unsigned int | |

Unique: `(question_id, huruf)`.

### 3.6 `exam_sessions`

| Kolom | Tipe | Catatan |
|---|---|---|
| `bank_id` | FK cascade | |
| `guru_id` | FK `users`, null on delete | |
| `semester_id` | FK `semesters`, null on delete | |
| `rombel_id` | FK `rombel`, null on delete | |
| `kode` | string unik nullable | |
| `nama` | string | |
| `kelas` | string nullable | |
| `mulai_at`, `selesai_at` | dateTime nullable | |
| `durasi_menit` | unsigned int nullable | |
| `status` | string(20), default `draft` | Nilai mengikuti legacy: `draft`, `published`, `running`, `completed`, dan untuk Tryout `ongoing` |
| `soal_snapshot` | JSON nullable | Snapshot soal saat publish (paritas CBT) |
| `peserta_ids` | JSON nullable | Daftar id siswa |
| `instruksi` | text nullable | |
| `sumber_tabel`, `sumber_id`, `meta` | jejak | |
| `deleted_at` | soft delete | |

### 3.7 `exam_attempts`

| Kolom | Tipe | Catatan |
|---|---|---|
| `session_id` | FK cascade | |
| `siswa_id` | FK `siswa`, cascade | |
| `user_id` | FK `users`, null on delete | |
| `attempt_number` | unsigned int, default 1 | Percobaan ke-n |
| `mulai_at` | dateTime | |
| `deadline_at` | dateTime nullable | |
| `selesai_at` | dateTime nullable | |
| `status` | string(20), default `berjalan` | `berjalan`, `dikumpulkan`, `dinilai` |
| `nilai_total`, `nilai_maksimal` | decimal(8,2) nullable | Salinan nilai untuk pembacaan cepat |
| `sumber_tabel`, `sumber_id`, `meta` | jejak | |

Unique: `(session_id, siswa_id, attempt_number)`.
Index: `(session_id, status)`, `(sumber_tabel, sumber_id)`.

### 3.8 `exam_answers`

| Kolom | Tipe | Catatan |
|---|---|---|
| `attempt_id` | FK cascade | |
| `question_id` | FK cascade | |
| `jawaban` | text nullable | `NULL` = tidak dijawab |
| `adalah_benar` | boolean nullable | Komentar skema: `NULL` = belum dinilai |
| `poin_diperoleh` | decimal(8,2), default 0 | |
| `dijawab_at` | dateTime nullable | |

Unique: `(attempt_id, question_id)`.

### 3.9 `exam_results`

| Kolom | Tipe | Catatan |
|---|---|---|
| `attempt_id` | FK cascade, **unik** | Satu hasil per attempt |
| `nilai_total` | decimal(8,2) | |
| `nilai_maksimal` | decimal(8,2) | |
| `persentase` | decimal(6,2) | |
| `peringkat` | unsigned int nullable | |
| `lulus` | boolean nullable | `NULL` = passing grade tidak diberikan |
| `detail_per_section` | JSON nullable | Rincian per mapel/section |
| `analisis_kelemahan` | JSON nullable | Maks 3 section dengan persentase < 60 |
| `rekomendasi` | text nullable | |
| `sumber_tabel`, `sumber_id` | jejak | |

### 3.10 `exam_violations`

| Kolom | Tipe | Catatan |
|---|---|---|
| `attempt_id` | FK cascade | Menggantung ke attempt, bukan result |
| `tipe` | string(40) | |
| `sumber_tabel`, `sumber_id` | jejak | |

Index: `(attempt_id, tipe)`, `(sumber_tabel, sumber_id)`.

### 3.11 Relasi ringkas

```text
exam_engines 1─n exam_banks 1─n exam_sections 1─n exam_questions 1─n exam_question_options
                    │                              │
                    │                              └─n exam_answers
                    └─n exam_sessions 1─n exam_attempts 1─1 exam_results
                                              │
                                              └─n exam_answers / exam_violations
```

### 3.12 Yang **tidak** ada di skema

- Tidak ada kolom `uuid` di engine (legacy Tryout punya `uuid`).
- Tidak ada kolom untuk jawaban salah per opsi pada `exam_answers`; analisis butir membaca `jawaban` mentah.
- Tidak ada kolom status per soal untuk esai (mis. `menunggu_penilaian`).
- Tidak ada foreign key `exam_sessions.semester_id` di CBT legacy; kolom tetap ada untuk Tryout.

---

## 4. Model dan perilaku Eloquent

| Model | Tabel | Poin penting |
|---|---|---|
| `Exam\Engine` | `exam_engines` | Konstanta `CBT`, `TRYOUT` |
| `Exam\Bank` | `exam_banks` | Soft delete, relasi `engine`, `sections`, `questions`, `sessions` |
| `Exam\Section` | `exam_sections` | Relasi `bank`, `questions` |
| `Exam\Question` | `exam_questions` | Soft delete; `$hidden = ['kunci_jawaban']`; opsi `huruf`, `adalah_benar` |
| `Exam\QuestionOption` | `exam_question_options` | |
| `Exam\Session` | `exam_sessions` | Soft delete; `peserta_ids`, `soal_snapshot` cast array |
| `Exam\Attempt` | `exam_attempts` | Konstanta status; cast tanggal; relasi `session`, `siswa`, `user`, `answers`, `result`, `violations` |
| `Exam\Answer` | `exam_answers` | |
| `Exam\Result` | `exam_results` | `detail_per_section`, `analisis_kelemahan` cast array |
| `Exam\Violation` | `exam_violations` | |

Catatan:

- `Attempt::STATUS_DIKUMPULKAN` dideklarasikan tetapi **tidak pernah ditulis** oleh kode mana pun. Status yang benar-benar muncul: `berjalan` → `dinilai`.
- `ExamGradingService::nilaiAttempt()` ada tetapi **tidak dipakai**; penilaian massal dilakukan di `ExamAttemptService::selesaikan()`.
- `exam_answers.adalah_benar` secara skema boleh `NULL`, tetapi `ExamGradingService::simpanJawaban()` selalu menulis boolean (`true`/`false`), termasuk untuk esai. Komentar "NULL = belum dinilai" belum tercermin di perilaku.

---

## 5. Siklus hidup percobaan

Ada tiga jalur yang harus dibedakan:

1. **CBT** — `CbtExamService` pemilik siklus; engine hanya menerima salinan.
2. **Tryout** — engine pemilik siklus; `tryout_*` menerima cermin.
3. **Engine murni** — `ExamAttemptService` dipakai langsung oleh Tryout dan test fondasi.

### 5.1 CBT: state machine sesi dan hasil

**Sesi (`cbt_test_sessions.status`)**

```text
draft ──publish()──> published ──open()──> running ──complete()──> completed
  │                      │                    │
  └── butuh 1–200 soal   └── closes_at belum lewat
      dan 1–500 siswa
```

Aturan `publish()`:

- Transaksi dengan lock berurutan: **bank → sesi → hasil**.
- Hanya boleh dari status `draft`, kalau tidak `409`.
- Soal diambil dari bank, diurutkan `id`.
- Peserta = siswa aktif (`aktif=true`, `archived_at IS NULL`) pada `kelas` sesi, dengan syarat:
  - `guru_id` sama dengan guru sesi, **atau**
  - `dapodik_id` tidak null, **atau**
  - `guru_id` siswa null.
- Batas: 1–200 soal dan 1–500 peserta.
- `closes_at` harus belum lewat.
- Snapshot disimpan **tanpa** `toArray()` agar `correct_answer` yang di-hidden tidak hilang.
- Status menjadi `published`; `participant_ids` diisi.

Aturan `open()`:

- Hanya dari `published`; `running` bila belum kedaluwarsa.

Aturan `begin()`:

- Lock sesi.
- `eligible()`: siswa aktif, tidak diarsipkan, ada di `participant_ids`; siswa non-Dapodik harus satu guru dengan sesi.
- Bila sudah ada `cbt_results` untuk siswa:
  - hanya pemilik `user_id` yang sama boleh melanjutkan;
  - mengembalikan baris lama (tidak membuat attempt kedua).
- Harus status `running` dan di dalam jendela `opens_at`–`closes_at`.
- `deadline_at = min(now + duration_minutes, closes_at)`.
- Bila `exam_engine_cbt=true`, bridge mencatat attempt.

**Hasil (`cbt_results`)**

```text
(baru) ──begin()──> answers=[] , submitted_at=NULL
   │
   ├──answer()──> answers[id]=opsi  (ditolak setelah deadline; auto-finalize)
   ├──submit()──> answers digabung, lalu finish()
   ├──complete() sesi ──> finish() semua yang belum submit
   └──finalizeExpired() ──> finish() yang deadline_at <= now
```

`finish()`:

- Bila `submitted_at` masih null: nilai dengan `CbtScoring::grade(snapshot, answers)` lalu set `submitted_at`.
- Bila flag engine aktif: `catatAttempt` → `catatJawaban` → `catatPenilaian`.
- `answer()` setelah kedaluwarsa **tidak melempar exception**; ia memfinalisasi lalu mengembalikan `false`. Controller membalas `409` dengan pesan "Waktu habis atau ujian telah dikumpulkan", tetapi transaksi tetap commit.
- `submit()` setelah deadline hanya menilai jawaban yang sudah tersimpan.
- `finalizeExpired()` dijalankan scheduler tiap menit (`withoutOverlapping`) hanya saat `cbt_enabled=true`.

**Kunci dan konkurensi CBT**

- Semua mutasi memakai `locked()`: lock sesi dulu, lalu hasil.
- Retry transaksi 3 kali.
- `begin()` mengunci sesi (bukan baris hasil yang belum ada), sehingga dua request paralel tidak bisa membuat dua baris hasil.
- Tidak ada unique index `(session_id, siswa_id)` di `cbt_results`; pengamannya adalah lock + pengecekan `first()`.

### 5.2 Tryout: engine pemilik, legacy mencermin

```text
Siswa klik mulai
  └─ TryoutStudentController::start
       └─ TryoutEngineBridge::mulai
            ├─ LegacyExamMapper::pastikanSesiTryout   (bank + section + soal + sesi)
            ├─ ExamAttemptService::mulai              (resume atau attempt baru)
            └─ cerminHasil()                          (buat/perbarui tryout_results)
```

- `ExamAttemptService::mulai()`:
  - lock attempt berjalan (`selesai_at IS NULL`) untuk siswa+sesi;
  - bila ada, kembalikan (resume);
  - bila tidak, `attempt_number = max + 1`;
  - `deadline_at = now + durasi` bila durasi > 0.
- Durasi yang dipakai adalah `tryout_sessions.durasi_menit`. Pembaca siswa menampilkan `package.meta.durasi_total_menit` bila ada; ini **dua sumber durasi berbeda** yang perlu disadari.
- `cerminHasil()` menulis `tryout_results` dengan kunci natural `(tryout_session_id, siswa_id, attempt_number)`.
- Setelah cermin dibuat, `exam_attempts.sumber_tabel/sumber_id` diarahkan balik ke `tryout_results` agar pencarian O(1).

**Menjawab**

```text
POST tryout.student.answer
  └─ authorize('answer', result)
       ├─ tolak bila waktu_selesai != null (409)
       ├─ tolak bila sesi tidak ongoing (409)
       ├─ validasi soal milik paket sesi (422)
       └─ bridge->simpanJawabanSoal
            ├─ pastikan soal Tryout punya padanan exam_questions (buat bila belum)
            ├─ ExamAttemptService::simpanJawaban → ExamGradingService
            └─ cerminJawaban → tryout_student_answers
```

- `pastikanSoal()` membuat section/soal engine saat ujian berjalan bila soal ditambahkan setelah sesi dimulai. Ini mencegah poin maksimal meleset.
- Respons JSON tidak pernah memuat kunci atau poin.

**Menyelesaikan**

```text
POST tryout.student.complete
  └─ bridge->selesaikan(result, passing_grade)
       ├─ segarkan pemetaan (soal baru ikut dinilai)
       ├─ ExamAttemptService::selesaikan(attempt, passingGrade)
       ├─ cerminSeluruhJawaban
       └─ cerminHasil(sudahSelesai=true)
```

### 5.3 Engine murni (`ExamAttemptService`)

Dipakai langsung oleh Tryout dan test fondasi. Kontraknya:

| Metode | Kontrak |
|---|---|
| `mulai(session, siswa, userId, durasiMenit)` | Resume attempt `selesai_at IS NULL`, atau buat nomor berikutnya |
| `simpanJawaban(attempt, questionId, jawaban)` | `false` bila soal bukan milik bank sesi; tidak melempar |
| `selesaikan(attempt, passingGrade)` | Idempoten; menulis attempt + result |

`selesaikan()` selalu menilai **seluruh soal bank**, bukan hanya yang dijawab. Soal yang tidak dijawab memberi poin 0 tetapi tetap menambah `nilai_maksimal`.

---

## 6. Aturan penilaian

Sumber tunggal: `ExamGradingService`.

### 6.1 Urutan pencarian kunci

1. `exam_questions.kunci_jawaban` bila tidak null dan tidak kosong.
2. Opsi dengan `adalah_benar = true`.
3. Bila tidak ada keduanya → jawaban **salah** (0 poin), bukan dianggap benar.

Untuk pilihan ganda, perbandingan `strtoupper(trim(...))`.
Untuk `benar_salah` dan `isian_singkat`, perbandingan memakai normalisasi.

### 6.2 Normalisasi teks

```php
mb_strtolower(preg_replace('/\s+/u', ' ', trim($teks)) ?? '')
```

Efek:

- Huruf besar/kecil tidak membedakan.
- Spasi berlebih disatukan.
- Trim awal/akhir.
- **Tidak** menghapus tanda baca, jadi "Jakarta." ≠ "Jakarta".

### 6.3 Matriks tipe soal

| Tipe | Dinilai otomatis | Aturan |
|---|---|---|
| `pilihan_ganda` | Ya | Bandingkan huruf dengan kunci eksplisit/opsi benar |
| `benar_salah` | Ya | Bandingkan teks setelah normalisasi |
| `isian_singkat` | Ya | Sama seperti `benar_salah` |
| `essay` | **Tidak** | `benar()` mengembalikan `false`, poin 0 |
| Tipe tak dikenal | Tidak | `false` |

Konsekuensi esai:

- `simpanJawaban()` menulis `adalah_benar=false`, `poin_diperoleh=0`, `dijawab_at=now()`.
- Tidak ada antrean penilaian manual, tidak ada status "menunggu".
- Bila sekolah memakai esai, nilai akan selalu 0 sampai fitur penilaian manual ditambahkan.

### 6.4 Menyimpan jawaban

`ExamGradingService::simpanJawaban()`:

- `updateOrCreate` pada `(attempt_id, question_id)`.
- Jawaban kosong/null disimpan sebagai `NULL`.
- `dijawab_at` selalu diisi `now()`, termasuk saat jawaban dikosongkan.
- Poin langsung dihitung saat menyimpan; tidak menunggu submit.

### 6.5 Penilaian akhir (`ExamAttemptService::selesaikan`)

Pseudokode:

```text
questions = semua soal bank, urut (urutan, id)
jawaban   = map question_id => jawaban attempt
total = 0; maksimal = 0; benar = 0
detail[section] = { nama_mapel, mata_pelajaran_id, skor, maksimal, bobot }

untuk setiap soal:
    (isBenar, poin) = nilai(soal, jawaban[soal])
    total += poin
    maksimal += soal.poin
    detail[section].skor += poin
    detail[section].maksimal += soal.poin

untuk setiap section:
    detail[section].persentase = 100 * skor / maksimal

totalBerbobot    = Σ (section.skor * section.bobot)
maksimalBerbobot = Σ (section.maksimal * section.bobot)
persentase       = 100 * totalBerbobot / maksimalBerbobot

peringkat = jumlah result lain di sesi yang selesai dengan nilai_total > totalBerbobot + 1
lulus     = passingGrade === null ? null : persentase >= passingGrade
kelemahan = section dengan persentase < 60, urut naik, maks 3
```

Poin penting:

- Soal tanpa section masuk ke `section_id = 0` dengan nama `Umum` dan bobot 1.
- Bobot section hanya memengaruhi total, **bukan** persentase per section.
- `peringkat` dihitung terhadap `exam_results.nilai_total` attempt lain yang sudah selesai; seri mendapat peringkat sama.
- `lulus` tetap `null` bila `passingGrade` tidak diberikan.

### 6.6 Perbedaan skor CBT legacy vs engine

| Aspek | `CbtScoring` (legacy) | `ExamGradingService` (engine) |
|---|---|---|
| Skala | 0–100 (`benar / jumlah soal * 100`) | Poin mentah, lalu persentase |
| Bobot | Tidak ada | Ada (`exam_sections.bobot`) |
| Tipe soal | Hanya pilihan ganda | PG, benar/salah, isian, esai |
| Sumber kunci | `correct_answer` snapshot | `kunci_jawaban` → opsi benar |
| `cbt_results.score` | 0–100 | — |

Bridge CBT menulis ke engine dengan `nilai_maksimal = 100` dan `persentase = score` (0–100) agar bentuknya sama dengan legacy. Ini **berbeda** dari `ExamAttemptService::selesaikan()` yang memakai poin soal dan bobot. Keduanya tidak boleh dicampur untuk sesi yang sama.

### 6.7 Ranking dan kelulusan di jalur cermin

- `TryoutEngineBridge::cerminHasil()` menyalin `peringkat`, `lulus`, `detail_nilai_mapel`, `analisis_kelemahan`, dan `rekomendasi` dari `exam_results` ke `tryout_results`.
- `detail_nilai_mapel` menambahkan `lulus` per mapel dengan ambang **60**, terlepas dari `passing_grade_mapel` di section. Ini perilaku yang dipertahankan dari jalur lama; kalau sekolah memakai ambang berbeda, nilai per mapel di laporan bisa menyesatkan.
- `lulus` tingkat paket memakai `passing_grade` paket yang dikirim controller (`(float) $result->session->package->passing_grade`).

---

## 7. Idempotensi, konkurensi, dan penguncian

### 7.1 Kunci natural

| Operasi | Kunci |
|---|---|
| Pemetaan bank/soal/sesi | `(sumber_tabel, sumber_id)` |
| Attempt CBT | `(sumber_tabel='cbt_results', sumber_id)` |
| Attempt Tryout | `(sumber_tabel='tryout_results', sumber_id)` |
| Jawaban engine | `(attempt_id, question_id)` unik |
| Hasil engine | `(attempt_id)` unik |
| Pelanggaran | `(sumber_tabel, sumber_id)` |
| Nomor attempt | `(session_id, siswa_id, attempt_number)` unik |

Semua penulisan memakai `updateOrCreate`, sehingga:

- Backfill berulang tidak menggandakan.
- Menyimpan jawaban yang sama dua kali tidak membuat baris baru.
- `selesaikan()` dua kali menghasilkan result yang sama (satu baris).

### 7.2 Urutan lock

CBT (`CbtExamService`):

```text
bank → sesi → hasil
```

Mutasi soal juga mengunci bank, sehingga publikasi tidak bisa balapan dengan edit soal.

Tryout (`TryoutEngineBridge` + `ExamAttemptService`):

```text
transaksi → attempt berjalan (lockForUpdate) → tulis jawaban/attempt
```

### 7.3 Retry

Transaksi memakai `DB::transaction(..., 3)` di:

- `CbtExamService::publish/open/begin/locked/complete`
- `TryoutEngineBridge::mulai/selesaikan`
- `ExamAttemptService::selesaikan`

Retry 3 kali mengurangi kegagalan deadlock di PostgreSQL, tetapi tidak menghilangkan kemungkinan; pesan error tetap harus dipantau.

### 7.4 Jebakan yang diketahui

| Jebakan | Dampak | Mitigasi saat ini |
|---|---|---|
| `cbt_results` tidak punya unique `(session_id, siswa_id)` | Dua baris hasil mungkin bila lock gagal | Lock sesi di `begin()` |
| `ExamAttemptService::mulai()` mengunci baris yang mungkin tidak ada | Dua request paralel bisa sama-sama membuat attempt | Unique `(session_id, siswa_id, attempt_number)` + retry |
| `nilai_maksimal` CBT bridge = 100 | Tidak sama dengan total poin bank | Hanya untuk paritas legacy; jangan dipakai jalur engine murni |
| `peringkat` dihitung sebelum semua peserta selesai | Peringkat sementara | Wajar; peringkat final baru akurat setelah sesi selesai |
| `finalizeExpired` tiap menit | Beban query bila banyak hasil | Index `(deadline_at)` belum ada; pantau di produksi |

---

## 8. Bridge dan aturan cermin

### 8.1 CBT: dual-write dengan legacy sebagai pemilik

`CbtEngineBridge` dipanggil di tiga titik:

| Titik | Metode bridge | Yang ditulis |
|---|---|---|
| `begin()` | `catatAttempt()` | `exam_attempts` + jawaban yang sudah ada |
| `answer()` / `finish()` | `catatJawaban()` | `exam_answers` |
| `finish()` | `catatPenilaian()` | `exam_attempts` + `exam_results` |
| `recordViolation()` | `catatPelanggaran()` | `exam_violations` |

Aturan penting:

- `sesi()` menyelaraskan `exam_questions.kunci_jawaban` dengan `question_snapshot.correct_answer` **sebelum** attempt dibuat. Ini membekukan kunci pada saat publikasi; mengubah bank setelahnya tidak menggeser nilai.
- `catatAttempt()` selalu memakai `attempt_number = 1` karena CBT lama hanya punya satu hasil per siswa per sesi.
- `catatPenilaian()` menulis `nilai_maksimal=100`, `persentase=score`, dan **tidak menulis `lulus`** (tetap `null`).
- Bila `exam_engine_cbt=false`, bridge tidak dipanggil sama sekali; CBT berjalan penuh di tabel lama.

### 8.2 Tryout: engine sebagai sumber kebenaran

| Operasi | Engine | Cermin legacy |
|---|---|---|
| Mulai | `ExamAttemptService::mulai` | `tryout_results` |
| Jawab | `ExamAttemptService::simpanJawaban` | `tryout_student_answers` |
| Selesai | `ExamAttemptService::selesaikan` | `tryout_results` + semua jawaban |
| Buat paket/mapel/soal | `LegacyExamMapper` | tidak berubah |

Aturan cermin:

- `tryout_results` memakai kunci `(tryout_session_id, siswa_id, attempt_number)`.
- `tryout_student_answers` memakai kunci `(tryout_session_id, siswa_id, question_id, attempt_number)`.
- `exam_attempts.sumber_tabel/sumber_id` diarahkan balik ke `tryout_results`.
- `cerminJawaban()` mencari `tryout_subject_id` lewat `exam_questions.section.sumber_id`.
- Bila flag `exam_engine_tryout=false`, controller memakai jalur lama penuh (`Result::create`, `TryoutScoring`, `StudentAnswer::updateOrCreate`).

### 8.3 Matriks flag

| Flag | Default | `true` | `false` |
|---|---|---|---|
| `simantap.cbt_enabled` | `false` (env) | Rute CBT/Tryout/TKA aktif | Rute modul asesmen 404 (admin bypass) |
| `simantap.exam_engine_cbt` | `true` | CBT menulis ke `exam_*` + mirror | CBT hanya menulis `cbt_*` |
| `simantap.exam_engine_tryout` | `true` | Tryout memakai engine + mirror | Tryout hanya menulis `tryout_*` |
| `simantap.exam_engine_readers` | `false` | Dasbor/laporan/hasil membaca `exam_*` | Membaca tabel lama |

Catatan penting:

- `cbt_enabled=false` membuat **seluruh** rute Tryout dan TKA juga 404 karena middleware `module.asesmen` memakai flag yang sama (`EnsureModuleEnabled`), kecuali admin.
- `exam_engine_readers` boleh `true` hanya setelah backfill + rekonsiliasi bersih; jika tidak, halaman bisa kosong karena data lama belum punya padanan.

### 8.4 Rollout yang aman

```text
1. migrate
2. exam:backfill --dry-run        → lihat delta
3. exam:backfill                  → commit salinan
4. exam:rekonsiliasi              → harus 0 masalah
5. uji baca: exam_engine_readers=true di staging
6. uji tulis: exam_engine_cbt/tryout=true di staging
7. produksi: aktifkan flag baca lebih dulu, lalu tulis
8. setelah stabil: hapus jalur lama (langkah 6)
```

Urutan ini penting: mengaktifkan pembaca sebelum backfill akan menampilkan halaman kosong; mengaktifkan penulis tanpa pembaca akan membuat dua sumber data yang tidak dibandingkan.

---

## 9. Pembaca dan kontrak parity

### 9.1 `CbtStudentReader`

- `pakaiEngine()` membaca `exam_engine_readers`.
- Dua cabang mengembalikan `LengthAwarePaginator` dengan bentuk baris identik:
  - `id` = **id tabel lama** (`sumber_id`), karena rute `cbt.student.start` masih mengikat model `CbtTestSession`.
  - `title`, `jadwal` (`d/m/Y H:i — d/m/Y H:i`), `duration_minutes`, `start_url`.
- Filter non-Dapodik: `guru_id` siswa harus sama dengan `guru_id` sesi.
- Cabang engine memfilter `sumber_tabel = cbt_test_sessions` dan `peserta_ids` JSON.

### 9.2 `TryoutStudentReader`

- `sesiTersedia()`: sesi `ongoing`, dalam jendela tanggal, cocok `kelas` atau `rombel_id`; diurutkan berdasarkan `id`.
- `hasilSaya()`: semua attempt/result siswa, termasuk yang belum selesai.
- Cabang engine mengambil `meta` paket dari `exam_banks.meta` (`deskripsi`, `jumlah_mapel`, `durasi_total_menit`, `passing_grade`).
- `detail_url` hanya dibuat bila `id > 0`; baris engine tanpa `sumber_id` tidak dapat ditautkan ke rute lama.

### 9.3 `CbtDashboardProvider`

- `ujianTersedia()`: maks 3 sesi, sama seperti dasbor lama.
- `hasilTerbaru()`: 5 hasil terakhir berdasarkan `submitted_at`.
- `ringkasanGuru()`: total bank, sesi aktif, sesi selesai, total peserta.
- `ringkasanSekolah()`: total sesi, ongoing, completed, peserta, rata-rata, pass rate (ambang 70).
- Cabang engine mendelegasikan ke `ExamDashboardService`.

### 9.4 `TryoutResultProvider`

- `daftar()` mengembalikan `{hasil, ringkasan}`.
- Ringkasan dihitung dari **halaman yang sedang tampil**, bukan seluruh data; ini meniru perilaku lama dan perlu diketahui saat membaca kartu statistik.
- Cabang engine memakai `withCount` jawaban benar/salah/kosong.
- Rincian engine memakai `route('admin.tryout.results.engine.show', $attempt)`; rincian legacy memakai `admin.tryout.results.show`.

### 9.5 `ExamReportService`

- Hanya membaca `exam_*`, tanpa fallback.
- `hasilSesiCbt()` dan `hasilSesiTryout()` menerima model legacy sebagai **kunci pencarian** (`sumber_id`), bukan sebagai sumber data.
- `ringkasanTryout()` menghitung paket, sesi, sesi aktif, total hasil, dan pass rate dari attempt yang sudah selesai.
- Ini adalah bukti bahwa laporan bisa disusun tanpa tabel lama; belum menjadi default.

---

## 10. Backfill, rekonsiliasi, dan runbook

### 10.1 `exam:backfill`

Signature:

```bash
php artisan exam:backfill [--only=cbt|tryout] [--dry-run] [--verify]
```

Alur:

1. Validasi `--only`.
2. `--verify` → langsung menjalankan `ExamReconciliationService::laporan()` dan keluar.
3. Catat jumlah tabel sebelum.
4. Buka transaksi.
5. `backfillCbt()` dan/atau `backfillTryout()`.
6. `--dry-run` → rollback; selain itu commit.
7. Tampilkan tabel `Sebelum / Sesudah / Delta`.

**Urutan backfill CBT:** bank → soal → sesi → hasil → jawaban → pelanggaran.

**Urutan backfill Tryout:** paket → mapel (section) → soal → sesi → hasil → jawaban.

**Perilaku penting:**

- Tabel lama tidak pernah diubah.
- `cbt_results` selalu menjadi attempt nomor 1.
- Status: `dinilai` bila sudah submit, `berjalan` bila belum.
- Nilai CBT dihitung ulang dari opsi benar **saat backfill**, bukan dari snapshot. Bila bank diubah setelah ujian, hasil backfill bisa berbeda dari hasil live yang memakai snapshot. Ini risiko nyata; jalankan backfill sebelum mengubah bank, atau bandingkan dengan rekonsiliasi.
- Pelanggaran CBT ikut dipetakan agar `cbt_violations` bisa dihapus nanti.
- `--dry-run` tetap menjalankan seluruh penulisan; bedanya hanya rollback. Untuk data besar, ini tetap membebani database.

### 10.2 `exam:rekonsiliasi`

Signature:

```bash
php artisan exam:rekonsiliasi [--only=cbt|tryout] [--batas=25] [--json]
```

Yang dibandingkan:

- Jumlah baris per tabel legacy vs engine.
- Atribut bank, soal, opsi, sesi (memakai fungsi atribut yang sama dengan mapper).
- Attempt: siswa, user, nomor, tanggal, status, nilai.
- Jawaban: jawaban, benar, poin, waktu.
- Hasil: nilai, maksimal, persentase, peringkat, lulus, detail, analisis, rekomendasi.
- Pelanggaran: tipe dan attempt.
- Relasi induk-anak (mis. `bank_id`, `session_id`, `attempt_id`).

Aturan perbandingan:

- Tanggal dinormalisasi ke `Y-m-d H:i:s`.
- JSON string di-decode; array asosiatif di-`ksort`; list diurutkan.
- Boolean dibandingkan sebagai boolean.
- Numerik dibandingkan dengan toleransi `< 0.011`.
- String di-trim.

Keluaran:

```json
{
  "ok": true,
  "diperiksa": 1234,
  "total_masalah": 0,
  "masalah": [],
  "jumlah": { "legacy": {...}, "engine": {...} }
}
```

`masalah` dibatasi `--batas`, tetapi `total_masalah` menghitung semua.

### 10.3 Runbook produksi

```bash
# 1. pastikan tidak ada proses tulis besar
php artisan down --retry=60   # opsional, tergantung kebijakan sekolah

# 2. cek salinan tanpa menyimpan
php artisan exam:backfill --dry-run

# 3. salin
php artisan exam:backfill

# 4. buktikan isi sama
php artisan exam:rekonsiliasi --json > rekonsiliasi.json
#    exit code != 0 berarti ada masalah

# 5. bila ada masalah, jangan aktifkan pembaca.
#    Perbaiki data lama atau jalankan backfill ulang setelah mapper diperbaiki.

php artisan up
```

Setelah rekonsiliasi bersih:

```env
SIMANTAP_EXAM_ENGINE_READERS=true
```

Pantau:

- `storage/logs/laravel.log` untuk error 500.
- Halaman dasbor siswa/guru/kepsek.
- Perbandingan angka dengan tabel lama selama satu periode.

### 10.4 Kapan **tidak** menjalankan backfill

- Saat guru sedang publish/complete sesi (lock dan data berubah).
- Saat bank soal sedang diedit besar-besaran.
- Tanpa backup, terutama bila `--dry-run` tidak dipakai.
- Setelah tabel lama dihapus (tidak ada sumber).

---

## 11. Analisis butir dan laporan

### 11.1 `ExamItemAnalysisService`

Sumber:

1. Bila sesi CBT punya padanan engine **dan** ada attempt → analisis engine.
2. Bila tidak → analisis legacy dari `question_snapshot` (fallback ke bank bila snapshot kosong).
3. Bila snapshot kosong dan bank kosong → hasil kosong.

Rumus:

| Ukuran | Rumus |
|---|---|
| `P` (tingkat kesukaran) | `jumlah benar / jumlah peserta` |
| `D` (daya beda) | `P kelompok atas 27% − P kelompok bawah 27%` |
| Distraktor | Frekuensi tiap opsi selain kunci |
| Kosong | Jawaban `NULL` |

Aturan:

- `D` hanya dihitung bila peserta ≥ 4; ukuran kelompok = `max(1, round(0.27 * n))`.
- Peserta yang dipakai: yang sudah selesai bila ada; bila belum ada yang selesai, semua attempt.
- Urutan kelompok: nilai tertinggi → terendah; tie-break `id`.
- Kategori `P`: `< 0.30` sulit, `> 0.80` mudah, selain itu sedang.
- Kategori `D`: `< 0.20` lemah, `< 0.30` cukup, selain itu baik.
- Catatan otomatis: kunci tidak ditemukan, terlalu sulit/mudah, daya beda lemah, jumlah kosong, distraktor tidak dipilih.
- Read-only; tidak mengubah nilai.

Command:

```bash
php artisan exam:analisis-butir --cbt=12
php artisan exam:analisis-butir --engine=34 --json
```

### 11.2 Laporan dan ekspor

| Halaman | Rute | Sumber |
|---|---|---|
| Monitoring sesi CBT | `cbt.monitoring`, `cbt.api.monitoring` | `cbt_results` |
| Analisis butir CBT | `admin.cbt.analisis-butir` | Engine lalu legacy |
| Ekspor hasil CBT | `admin.cbt.export` | Legacy |
| Ekspor hasil CBT engine | `admin.cbt.export-engine` | Engine |
| Hasil Tryout admin | `admin.tryout.results.*` | Flag readers |
| Rincian engine | `admin.tryout.results.engine.show` | `exam_attempts` |
| Laporan engine-only | `ExamReportService` | `exam_*` |

---

## 12. Integritas dan pelanggaran

### 12.1 Alur

```text
Browser siswa (event visibilitychange/blur)
  └─ POST cbt.student.integrity  (throttle 30/menit)
       ├─ authorize('recordViolation', result)
       ├─ pastikan siswa eligible
       └─ CbtExamService::recordViolation
            ├─ lock sesi + hasil
            ├─ tolak bila sudah submit/kedaluwarsa (409)
            ├─ batas 100 pelanggaran per hasil
            └─ bridge->catatPelanggaran → exam_violations
```

### 12.2 Aturan

- Pelanggaran bersifat **indikasi**, bukan bukti kecurangan, dan tidak otomatis menolkan nilai.
- Setelah submit atau deadline, pencatatan ditolak.
- Batas 100 mencegah banjir data dari klien nakal.
- `getViolations()` hanya untuk pemilik/guru berwenang lewat `CbtResultPolicy::viewViolations`.
- Pelanggaran CBT ikut dipetakan ke engine dan direkonsiliasi.

### 12.3 Yang belum ada

- Tidak ada korelasi otomatis pelanggaran → sanksi.
- Tidak ada rekap pelanggaran lintas sesi di dasbor.
- Tidak ada batas pelanggaran yang memicu penghentian ujian; hanya pencatatan.

---

## 13. TKA: jalur terpisah

TKA **tidak** memakai `exam_*`. Ada dua hal berbeda yang sama-sama disebut TKA:

### 13.1 TKA diagnostik (persisten)

| Tabel | Isi |
|---|---|
| `tka_dimensions_enhanced` | Dimensi (kode, nama, deskripsi) |
| `tka_assessment_items` | Butir per dimensi |
| `tka_student_assessments` | Assessment siswa, `skor_per_dimensi` |
| `tka_assessment_responses` | Jawaban per butir |
| `tka_parent_reports` | Laporan untuk orang tua |
| `dimensi`, `kebiasaan` | Data TKA lama |

Alur:

```text
Guru membuat dimension + item
  └─ TkaAssessmentController::store
       └─ menilai respons → skor_per_dimensi
            ├─ TKAService::hitungTKA (predikat)
            ├─ TkaStatistikService (agregat)
            └─ TkaParentController::generateReport
```

`TKAService::hitungTKA()`:

- Mengambil `Dimensi` per siswa.
- Rata-rata skor per dimensi.
- Total = rata-rata seluruh dimensi.
- Predikat: ≥90 Sangat Baik, ≥75 Baik, ≥60 Cukup, ≥40 Perlu Bimbingan, selain itu Kurang.

### 13.2 Simulasi TKA (session browser)

`Siswa\TkaSimulasiController`:

- **Belum dilacak git** (`??` di `git status`) bersama test-nya.
- 3 subtes × 5 soal, didefinisikan di kode.
- Token = `TKA-` + 5 karakter pertama `md5(id + user + tanggal)`.
- Jawaban dan hasil disimpan di session Laravel, bukan database.
- Riwayat disimpan di `session('tka_simulasi_riwayat')`.
- Tidak ada kaitan ke `exam_*`, tidak ada rekonsiliasi, tidak ada audit.
- Cocok untuk demo, **bukan** untuk ujian bernilai rapor.

Risiko:

- Session bisa hilang saat cache dibersihkan atau siswa berganti perangkat.
- Tidak ada bukti hasil untuk orang tua/sekolah.
- Token deterministik per hari, mudah ditebak bila id dan user diketahui.

---

## 14. Test dan invarian yang dijaga

| Test | Invarian |
|---|---|
| `ExamEngineFoundationTest` | Skema, model, relasi, idempotensi dasar |
| `CbtExamTest` | Siklus CBT: publish, eligible, begin, answer, submit, complete, deadline |
| `CbtEngineParityTest` | Nilai CBT legacy = engine untuk snapshot yang sama |
| `CbtCrudEngineSyncTest` | CRUD bank/soal/sesi mencerminkan ke engine |
| `CbtStudentReaderTest` | Bentuk baris legacy = engine |
| `CbtAuthorizationTest` | Siswa hanya bisa mengakses hasil/sesi miliknya |
| `CbtDisabledTest` | Flag off → 404 (kecuali admin) |
| `TryoutTest` | Siklus Tryout legacy |
| `TryoutEngineParityTest` | Nilai dan bentuk hasil engine = legacy |
| `TryoutCrudEngineSyncTest` | CRUD paket/mapel/soal mencerminkan ke engine |
| `TryoutStudentReaderTest` | Bentuk sesi/hasil sama di dua cabang |
| `ExamReaderTest` | Provider dasbor/hasil parity |
| `ExamBackfillDryRunTest` | Dry-run tidak menyimpan; backfill idempoten |
| `ExamReconciliationTest` | Rekonsiliasi mendeteksi selisih dan bersih saat data sama |
| `ExamItemAnalysisTest` | P, D, distraktor, fallback legacy |
| `TkaAssessmentFlowTest` | Alur assessment TKA |
| `TkaAdminPagesTest` | Halaman admin TKA |
| `TkaStatistikServiceTest` | Agregat statistik |
| `TkaSimulasiMandiriTest` | Simulasi session (belum dilacak git) |

Cara menjalankan fokus:

```bash
php artisan test --filter=Exam
php artisan test --filter=Cbt
php artisan test --filter=Tryout
php artisan test --filter=Tka
```

Lingkungan test memakai SQLite `:memory:`; hindari SQL khusus PostgreSQL (`ilike`, `to_char`, `jsonb` operator) di query yang dieksekusi test.

---

## 15. Risiko dan utang teknis

| # | Risiko | Dampak | Saran |
|---|---|---|---|
| 1 | Backfill CBT menghitung ulang kunci dari opsi saat ini, bukan snapshot | Nilai hasil backfill bisa berbeda dari nilai live bila bank diubah setelah ujian | Jalankan backfill sebelum edit bank; bandingkan dengan rekonsiliasi; pertimbangkan memakai snapshot di backfill |
| 2 | Esai tidak dinilai otomatis dan tidak ada antrean manual | Nilai esai selalu 0 | Tambah status `menunggu_penilaian` + layar penilaian guru |
| 3 | `STATUS_DIKUMPULKAN` tidak pernah ditulis | Status attempt hanya `berjalan`/`dinilai` | Hapus konstanta atau pakai di alur submit |
| 4 | `ExamGradingService::nilaiAttempt()` tidak dipakai | Kode mati, membingungkan | Hapus atau jadikan satu jalur dengan `selesaikan()` |
| 5 | Dua sumber durasi Tryout (`exam_sessions.durasi_menit` vs `bank.meta.durasi_total_menit`) | Deadline bisa berbeda dari yang ditampilkan | Satukan saat mapping; dokumentasikan mana yang otoritatif |
| 6 | Ambang lulus per mapel di cermin Tryout hardcoded 60 | Laporan bisa menyesatkan bila sekolah memakai ambang lain | Pakai `section.passing_grade` |
| 7 | `cbt_results` tanpa unique `(session_id, siswa_id)` | Risiko duplikat bila lock gagal | Tambah unique index setelah data dibersihkan |
| 8 | Tidak ada index `cbt_results.deadline_at` | `finalizeExpired` memindai banyak baris | Tambah index parsial `WHERE submitted_at IS NULL` |
| 9 | `exam_engine_readers=false` di produksi | Halaman masih membaca tabel lama; parity belum terbukti di produksi | Jalankan backfill + rekonsiliasi, lalu aktifkan bertahap |
| 10 | Simulasi TKA hanya di session | Hasil hilang, tidak auditabel | Pindahkan ke tabel atau hapus dari menu |
| 11 | `cbt_enabled=false` memblokir Tryout dan TKA | Sekolah yang hanya ingin Tryout tidak bisa | Pisahkan flag per modul |
| 12 | Pelanggaran tidak memengaruhi nilai | Kecurangan hanya tercatat | Definisikan kebijakan sekolah secara eksplisit |
| 13 | `peringkat` dihitung saat penyelesaian, bukan saat sesi ditutup | Peringkat sementara berubah | Hitung ulang saat `complete()`, atau beri label "sementara" |
| 14 | `ExamReportService` belum jadi default | Dua jalur laporan bisa berbeda | Tambah test parity laporan, lalu alihkan |
| 15 | Tabel lama belum dihapus | Dual-write menambah beban dan risiko divergensi | Selesaikan langkah 6 setelah parity stabil |

---

## 16. Lampiran: "mau ubah X, sentuh apa?"

| Kebutuhan | Berkas |
|---|---|
| Mengubah aturan benar/salah | `app/Services/ExamGradingService.php` |
| Mengubah bobot/ranking/kelulusan | `app/Services/ExamAttemptService.php` |
| Mengubah cara data lama dipetakan | `app/Services/Exam/LegacyExamMapper.php` |
| Mengubah siklus CBT (publish/open/deadline) | `app/Services/CbtExamService.php` |
| Mengubah cermin CBT ke engine | `app/Services/CbtEngineBridge.php` |
| Mengubah cermin Tryout ke engine | `app/Services/TryoutEngineBridge.php` |
| Mengubah pembaca CBT siswa | `app/Services/Exam/CbtStudentReader.php` |
| Mengubah pembaca Tryout siswa | `app/Services/Exam/TryoutStudentReader.php` |
| Mengubah kartu dasbor CBT | `app/Services/Exam/CbtDashboardProvider.php` + `ExamDashboardService.php` |
| Mengubah daftar hasil Tryout admin | `app/Services/Exam/TryoutResultProvider.php` |
| Mengubah laporan engine-only | `app/Services/Exam/ExamReportService.php` |
| Mengubah analisis butir | `app/Services/Exam/ExamItemAnalysisService.php` |
| Mengubah cara backfill | `app/Console/Commands/ExamBackfill.php` |
| Mengubah cara rekonsiliasi | `app/Console/Commands/ExamReconciliation.php` + `ExamReconciliationService.php` |
| Menambah tabel/kolom engine | migrasi baru; jangan edit migrasi lama |
| Mengubah endpoint siswa | `app/Http/Controllers/Cbt/CbtStudentController.php`, `Tryout/TryoutStudentController.php` |
| Mengubah endpoint admin | `app/Http/Controllers/Admin/Cbt/*`, `Admin/Tryout/*` |
| Mengubah flag/perilaku modul | `config/simantap.php` + `app/Http/Middleware/EnsureModuleEnabled.php` |
| Mengubah jadwal finalisasi | `routes/console.php` |
| Mengubah TKA diagnostik | `app/Services/TKAService.php`, `app/Http/Controllers/Tka/*` |
| Mengubah simulasi TKA | `app/Http/Controllers/Siswa/TkaSimulasiController.php` |
| Menambah test parity | `tests/Feature/Exam*Test.php`, `Cbt*Test.php`, `Tryout*Test.php` |

---

## 17. Checklist sebelum go-live ujian

- [ ] `php artisan migrate` berhasil; 10 tabel `exam_*` ada.
- [ ] `php artisan exam:backfill --dry-run` selesai tanpa error.
- [ ] `php artisan exam:backfill` selesai; delta sesuai harapan.
- [ ] `php artisan exam:rekonsiliasi` → 0 masalah.
- [ ] Backup database tersedia dan pernah diuji restore (`docs/DRILL-RESTORE-BACKUP.md`).
- [ ] `SIMANTAP_CBT_ENABLED=true` bila modul asesmen dipakai.
- [ ] `cbt:finalize-expired` berjalan tiap menit (`schedule:run` aktif).
- [ ] Zona waktu server benar; deadline dihitung dari waktu server.
- [ ] Guru sudah publish sesi dan peserta masuk `participant_ids`.
- [ ] Siswa uji coba satu sesi: mulai → jawab → submit → hasil.
- [ ] Nilai di `cbt_results`/`tryout_results` sama dengan `exam_results`.
- [ ] Analisis butir menampilkan P/D masuk akal.
- [ ] Pelanggaran tercatat dan tidak menolkan nilai.
- [ ] `storage/logs/laravel.log` bersih dari error 500.
- [ ] Rencana rollback: set flag `false`, data lama tetap utuh.

---

*Dokumen ini dibuat dari pembacaan langsung kode pada commit `76ea58f`. Bila kode berubah, perbarui bagian yang terdampak dan jalankan ulang test terkait.*
