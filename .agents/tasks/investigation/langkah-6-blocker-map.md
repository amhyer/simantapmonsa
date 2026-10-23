# Peta Blocker Fase 3 — Langkah 6 (drop tabel lama)

Tanggal: 23 Okt 2026
Baseline: HEAD `3b0e678` (langkah 5d), working tree bersih.
Sumber status: `docs/RENCANA_PERBAIKAN_VERIFIED.md` §1e.

## Kesimpulan utama

Engine `exam_*` **belum menjadi sumber kebenaran** — ia masih *proyeksi* dari
`cbt_*` / `tryout_*`. Penulisan (CRUD guru/admin) dan pembacaan alur ujian siswa
masih di tabel lama; bridge hanya menyalin ke engine.

Karena itu langkah 6 tidak bisa dikerjakan sebagai "ganti beberapa query".
Blocker-nya ada 4 lapis, dan lapis 1–2 jauh lebih besar daripada pembaca laporan.

| Lapis | Isi | Berat |
|---|---|---|
| L1 | Penulis/CRUD (bank, soal, sesi, paket) masih milik tabel lama | **paling berat** |
| L2 | Alur ujian siswa membaca soal/jawaban/ranking dari tabel lama | berat |
| L3 | Pembaca laporan & dasbor (bisa dipindah paling cepat) | sedang |
| L4 | Infrastruktur: policy, backup/wipe, model, backfill, route lama | ringan–sedang |

---

## L1 — Penulis / CRUD masih menulis ke tabel lama

Jika tabel lama dihapus tanpa membalik kepemilikan ini, **bank soal dan sesi
baru akan hilang** karena tidak ada lagi tempat menyimpan.

| Area | Titik tulis (file:baris) | Padanan engine |
|---|---|---|
| Bank soal CBT (guru) | `app/Http/Controllers/Cbt/CbtQuestionBankController.php:28,47,58` | `exam_banks` (+ `engine_id` CBT) |
| Soal CBT (guru) | `app/Http/Controllers/Cbt/CbtQuestionController.php:32,43,52` | `exam_questions` + `exam_question_options`; kunci → `kunci_jawaban` |
| Sesi CBT (guru) | `app/Http/Controllers/Cbt/CbtTestSessionController.php:31` | `exam_sessions` (`sumber_*` = `cbt_test_sessions`) |
| Bank & sesi CBT (admin) | `app/Http/Controllers/Admin/Cbt/CbtBankController.php`, `CbtSessionController.php:64,106` | idem |
| Paket/mapel/soal Tryout (guru) | `app/Http/Controllers/Tryout/TryoutPackageController.php:35,104` | `exam_banks` / `exam_sections` / `exam_questions` |
| Sesi Tryout (guru) | `app/Http/Controllers/Tryout/TryoutSessionController.php:48` | `exam_sessions` |
| Paket & sesi Tryout (admin) | `app/Http/Controllers/Admin/Tryout/TryoutPackageAdminController.php` (7 titik tulis, `store`/`update`/`duplicate`/`storeSession`/`updateSession`/`forceCompleteSession`) | idem |

Catatan: `Admin\Cbt\CbtSessionController` juga membaca tabel lama untuk daftar &
ekspor (`:26`), sementara `:154` sudah punya jalur `exportResultsEngine`.

**Arah perbaikan yang disarankan:** balik kepemilikan — tulis ke `exam_*`, lalu
`cbt_*`/`tryout_*` dijadikan cermin (kebalikan bridge sekarang). Dengan begitu
UI lama tetap jalan sampai langkah 6 selesai.

---

## L2 — Alur ujian siswa masih membaca tabel lama

| Alur | Bukti | Padanan engine |
|---|---|---|
| CBT: daftar ujian | `Cbt/CbtStudentController.php:39` (`CbtTestSession`) | `exam_sessions` + `peserta_ids` |
| CBT: soal saat mulai | `CbtStudentController.php:60` (`$session->question_snapshot`) | `exam_sessions.soal_snapshot` (sudah diisi mapper `:111`) |
| CBT: jawaban & submit | `CbtExamService.php` (semua lock pada `CbtTestSession`/`CbtResult`) | `exam_attempts` + `exam_answers` |
| CBT: monitoring | `Cbt/CbtTestSessionController.php:81-90` (`$r->answers`, `deadline_at`) | `exam_attempts` + `exam_answers` |
| CBT: integritas | `Cbt/CbtIntegrityController` + `CbtViolation` | `exam_violations` (sudah ditulis bridge) |
| Tryout: sesi tersedia | `Tryout/TryoutStudentController.php:33,46` | `exam_sessions` |
| Tryout: mulai/attempt | `TryoutStudentController.php:70,77,81` | `exam_attempts` (`attempt_number`) |
| Tryout: soal | `TryoutStudentController.php:122` (`TryoutQuestion`) | `exam_questions` + opsi |
| Tryout: jawaban | `TryoutStudentController.php:151` (`StudentAnswer`) | `exam_answers` |
| Tryout: hasil/ranking | `TryoutStudentController.php:192,218` | `exam_results` (`peringkat` sudah dihitung `ExamAttemptService:143`) |
| Tryout: result page | `TryoutStudentController.php:186,250` + `views/tryout/student/result.blade.php` | idem |

---

## L3 — Pembaca laporan & dasbor

| Pembaca | Bukti | Status test |
|---|---|---|
| Dasbor siswa | `resources/views/siswa/dashboard.blade.php:274,285` | hanya `MenuAuditTest` (200 OK), **tanpa parity** |
| Dasbor guru | `resources/views/guru/dashboard.blade.php:338,342,346` (+ total bank `:333`) | `MenuAuditTest` saja |
| Dasbor kepsek | `resources/views/kepsek/dashboard.blade.php:116-121` | `MenuAuditTest` saja |
| Dasbor ortu | `Ortu/DashboardController.php:35-42` + `views/ortu/dashboard.blade.php:105+` | `ExamReaderTest::test_dasbor_ortu_...` (nilai, bukan parity) |
| Ekspor CBT lama | route `admin.cbt.sessions.export` (`routes/web.php:509`) | ✅ parity penuh vs `export-engine` (`ExamReaderTest:209`) |
| Statistik Tryout | `TryoutPackageAdminController.php:178-181` | ❌ tidak ada |
| Semua hasil Tryout admin | `TryoutPackageAdminController.php:347-387` | nilai dasar (`ExamReaderTest:166`), **bukan parity** |
| Detail hasil Tryout admin | `:389` (`showResult`) | hanya 200 OK |
| Daftar sesi Tryout admin | `:199-232` | hanya 200 OK (`TryoutTest:196`) |
| Ekspor Tryout | `:399` — **masih stub** ("akan segera tersedia") | ❌ tidak ada |
| Laporan engine pengganti | `ExamReportService::hasilSesiCbt()` | hanya CBT |

Gap terbesar di L3: **belum ada pembaca murni `exam_*` untuk 4 dasbor dan untuk
seluruh laporan Tryout**. Baru CBT ekspor yang punya pasangan engine + parity.

---

## L4 — Infrastruktur

- Policy masih menempel model lama: `AppServiceProvider:40-43`
  (`CbtTestSessionPolicy`, `CbtResultPolicy`, `Tryout\SessionPolicy`, `TryoutResultPolicy`).
- Backup & wipe: `App\Support\BackupTables:95-114`, `Admin\BackupController:191-198`.
- Model lama: `CbtResult`, `CbtTestSession`, `CbtViolation`, `Tryout\{Session,Result,StudentAnswer,TryoutQuestion,...}`.
- Perintah backfill `exam:backfill` membaca tabel lama — harus jadi alat migrasi
  sekali pakai, bukan bagian runtime, sebelum tabel dihapus.
- Route lama `admin.cbt.sessions.export` (`routes/web.php:509`) masih aktif.

---

## Temuan bug nyata saat identifikasi

### 1. `tryout_*` tidak pernah dicadangkan dan tidak ikut di-wipe

- `App\Support\BackupTables:94-114` memuat `cbt_*` dan `exam_*`, **tidak ada satu pun `tryout_*`**
  (padahal komentar `:103` menyebut "`cbt_*`/`tryout_*` hanyalah cermin").
- `Admin\BackupController:191-198` mengosongkan `cbt_*` + `exam_*`, **tanpa `tryout_*`**,
  meskipun komentar `:193-195` menyatakan keduanya harus dikosongkan.

Akibat: "Hapus Semua Data" menyisakan seluruh data Tryout (paket, sesi, hasil,
jawaban) sementara engine-nya kosong, dan backup tidak bisa memulihkannya.
Aturan §8 `tools/verifikasi-perbaikan.php:400` tidak menangkap ini karena hanya
memeriksa satu arah (yang di-wipe harus tercadang), bukan sebaliknya.

Perbaikan kecil & aman: tambahkan 7 tabel `tryout_*` ke `BackupTables` dan daftar
`$tabelData`, lalu perkuat aturan §8 agar memeriksa dua arah.

### 2. Ekspor Tryout admin masih tombol kosong

`TryoutPackageAdminController::exportResults()` (`:399`) hanya `back()->with('info', ...)`.
Tidak terkait langsung langkah 6, tapi jadi blocker parity karena tidak ada
keluaran lama yang bisa dipakai sebagai pembanding.

---

## Progres

- ✅ **Perbaikan bug backup/wipe** — `tryout_*` + `transkrip_nilai` kini tercadang
  dan ikut dikosongkan; wipe diturunkan dari registry (`BackupTables::dikosongkan()`).
- ✅ **6a (sebagian)** — pembaca engine murni untuk 4 dasbor CBT + statistik
  Tryout sudah ada (`ExamDashboardService`, `ExamReportService::ringkasanTryout`),
  dengan 4 test parity di `ExamReaderTest`.
- ⏭️ **6b** — alihkan view/controller ke pembaca engine (di balik flag), lalu
  parity HTTP kedua jalur.

## Urutan pengerjaan yang disarankan

1. **6a — Bekukan pembaca yang belum diuji** (L3): parity test untuk dasbor
   siswa/guru/kepsek, statistik Tryout, daftar hasil Tryout, dan ekspor Tryout
   (implementasikan ekspor lama dulu sebagai pembanding).
2. **6b — Pembaca pengganti murni engine** (L3): perluas `ExamReportService`
   (atau service baru `ExamDashboardService`) untuk 4 dasbor + laporan Tryout;
   uji parity terhadap 6a.
3. **6c — Balik kepemilikan tulis** (L1): CRUD bank/soal/sesi CBT dan
   paket/mapel/soal/sesi Tryout menulis ke `exam_*`; tabel lama jadi cermin.
4. **6d — Alur siswa membaca engine** (L2): soal dari `soal_snapshot`/`exam_questions`,
   jawaban dari `exam_answers`, ranking dari `exam_results`; hapus ketergantungan
   `cbt_results.answers` dan `tryout_student_answers`.
5. **6e — Pemutusan**: hapus route/jalur lama, hentikan mirror, hapus
   `exam:backfill` dari runtime, migrasi `drop` tabel lama, bersihkan model &
   policy, perbarui backup/wipe.
6. **Prasyarat produksi**: rekonsiliasi jumlah baris `exam_*` vs `cbt_*`/`tryout_*`
   (`exam:backfill` dry-run + hitung selisih) dan snapshot backup sebelum drop.

## Risiko

- Dual-write saat 6c/6d: harus ada flag (`simantap.exam_engine_*`) agar bisa
  balik tanpa kehilangan data.
- `cbt_questions.options` berupa JSON; `exam_question_options` ternormalisasi —
  edit opsi harus menghapus opsi yang tak lagi ada, bukan hanya updateOrCreate.
- `snapshot` CBT harus tetap menang atas bank yang diedit setelah publish
  (sudah diuji di `CbtEngineParityTest`).
- Data produksi bisa punya baris lama tanpa padanan engine (dibuat sebelum
  langkah 5) → wajib backfill final sebelum drop.
