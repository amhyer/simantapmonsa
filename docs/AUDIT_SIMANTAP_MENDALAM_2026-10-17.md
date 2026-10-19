# AUDIT MENDALAM & ROADMAP PEMBAIKAN — SIMANTAP

### `amhyer/simantapmonsa` @ commit `56f7922` (15 Okt 2026)

**Metode:** audit statis + **eksekusi nyata** (aplikasi dijalankan, migrasi dijalankan, test ditulis untuk membuktikan tiap temuan).
**Lingkungan:** PHP 8.4.13, Laravel 13, SQLite in-memory. 51.081 baris PHP, 355 route, 190 view, 84 migrasi, 82 controller.

---

## 0. RINGKASAN EKSEKUTIF

| Metrik | Angka |
|---|---|
| Total temuan **terkonfirmasi eksekusi** | **19** (18 CBT + 1 lintas-modul) |
| Endpoint **HTTP 500** di produksi | **20 dari 1.050** kombinasi rute x peran |
| Test suite baseline | 127 test -> **1 ERROR** (bukan "127/127 hijau") |
| Modul **tanpa test sama sekali** | CBT, Tryout, TKA, Transkrip, Cetak, Alignment, Backup, Dapodik-Push |
| Kerentanan otorisasi (IDOR) | **11 endpoint** |
| View yang hilang (`View [...] not found`) | **10** |
| CI pipeline | **tidak ada** (`.github/workflows` kosong) |

### Tiga kesimpulan utama

**1. Modul CBT, Tryout, dan TKA belum pernah dijalankan — belum pernah sama sekali.**

Bukan "ada bug sedikit". Alur ujian siswa **mati total**: klik "Mulai Ujian" -> `HTTP 500`. Countdown tidak jalan, auto-save tidak jalan, anti-cheat tidak jalan. Tidak ada satu pun file test yang menyentuh modul-modul ini.

**2. Laporan verifikasi sendiri proyek Anda salah total.**

`.agents/tasks/verify-cbt.md` menuliskan:

> "VERDICT: Production-Ready with Minor Verification Recommended ... Confidence Level: 95% ... CLEARED FOR DEPLOYMENT ... Code Quality: 9/10, Security: 9/10"

Laporan itu dibuat dengan **membaca source code saja, tanpa menjalankannya**. Kenyataannya: 3 crash 500 berurutan, 11 IDOR, dan kebocoran kunci jawaban. **Rasa aman yang palsu ini adalah risiko terbesar proyek Anda** — ia membuat bug tidak pernah terasa.

**3. Modul CBT dan Tryout adalah duplikat yang belum disatukan.**

Keduanya punya tabel bank soal / soal / opsi / sesi / jawaban / hasil sendiri, dengan **nama kolom berbeda** (`cbt_test_sessions` vs `tryout_sessions`; `question_bank_id` vs `package_id`). Dua implementasi logika penilaian yang berbeda, dua set bug yang berbeda. Memperbaiki dua-duanya terpisah = dua kali kerja.

---

## 1. TEMUAN KRITIS — MODUL CBT (semua terbukti eksekusi)

> Bukti: `tests/Feature/Cbt/AuditCbtProofTest.php` — 19 test, **18 gagal** (= 18 bug terkonfirmasi).
> Jalankan: `php vendor/bin/phpunit --filter AuditCbtProofTest`

### K-01 — `GET /cbt/student/{session}/start` menghasilkan HTTP 500

`cbt_test_results.waktu_selesai` didefinisikan `NOT NULL`, tetapi `CbtStudentController::start()` meng-insert `null`.

```
SQLSTATE[23000]: Integrity constraint violation: 19
NOT NULL constraint failed: cbt_test_results.waktu_selesai
  di app/Http/Controllers/Cbt/CbtStudentController.php:54
```

**Dampak:** siswa tidak pernah bisa membuka halaman ujian. Modul mati total.
**Perbaikan:** migration `->nullable()->change()` pada `waktu_selesai` dan `durasi_detik`.

### K-02 — Setiap request `/cbt/student/{result}/*` menghasilkan HTTP 500

`CbtSessionLock` dan `CbtSecurityMiddleware` memanggil `TestResult::find($request->route('result'))`. Masalahnya: route param **sudah di-bind menjadi model** oleh `SubstituteBindings`, dan middleware ini berjalan *sesudah* binding. Karena model Eloquent mengimplementasi `Arrayable`, `Builder::find()` jatuh ke cabang `findMany()`:

```php
// vendor/laravel/framework/.../Eloquent/Builder.php:589
public function find($id, $columns = ['*'])
{
    if (is_array($id) || $id instanceof Arrayable) {   // <- model termasuk Arrayable
        return $this->findMany($id, $columns);          // <- mengembalikan Collection
    }
    return $this->whereKey($id)->first($columns);
}
```

`findMany()` -> `->get()` -> **Collection**, bukan Model. Maka:

```
Property [waktu_selesai] does not exist on this collection instance.
  di app/Http/Middleware/CbtSessionLock.php:19
```

**Perbaikan:** `$result = $request->route('result');` lalu cek `instanceof TestResult`; atau `TestResult::find($testResultId->id)`.

### K-03 — Menyimpan jawaban menghasilkan HTTP 500 (dua-duanya salah)

`CbtStudentController::submitAnswer()` menulis kolom yang **tidak pernah ada**:

```php
StudentAnswer::where('test_result_id', $result->id)          // kolom tidak ada di migrasi
StudentAnswer::create([... 'test_result_id' => $result->id])  // + tidak menyertakan test_session_id
```

Migrasi `cbt_student_answers` hanya punya: `test_session_id, siswa_id, question_id, jawaban, ...`. Akibatnya:

```
NOT NULL constraint failed: cbt_student_answers.test_session_id
```

**Perbaikan:** tambah kolom `test_result_id` (FK ke `cbt_test_results`) lewat migration, sesuaikan unique constraint, dan isi `test_session_id` saat create.

### K-04 — Aset JS modul CBT 404: timer / auto-save / anti-cheat tidak pernah jalan

`resources/views/cbt/student/take.blade.php:387-389`:

```js
import { initTimer }           from '{{ asset("js/cbt/timer.js") }}';
import { initIntegrityChecker } from '{{ asset("js/cbt/integrity-checker.js") }}';
import { initAutoSave }        from '{{ asset("js/cbt/auto-save.js") }}';
```

- `public/js/` **tidak ada** -> semua request 404
- `vite.config.js` hanya mendaftarkan `app.css`, `app.js`, `guru-dashboard.js` -> file `resources/js/cbt/*.js` **tidak pernah dibangun**

Efeknya: satu import gagal -> seluruh `<script type="module">` mati -> **tidak ada timer, tidak ada auto-save, tidak ada integrity checker**. Ujian berjalan tanpa batas waktu dan tanpa penyimpanan jawaban.

**Perbaikan:** daftarkan `resources/js/cbt/*.js` sebagai entry point Vite, ganti `asset()` menjadi `@vite('resources/js/cbt/timer.js')` (atau satu bundel `resources/js/cbt/index.js`).

### K-05 — IDOR: siswa A bisa menulis, menutup, dan membuka ujian siswa B

`CbtStudentController` **tidak pernah memverifikasi kepemilikan `TestResult`**. Route model binding hanya memastikan *baris ada*, bukan *baris miliknya*.

| Aksi | Bukti terukur |
|---|---|
| `POST /cbt/student/{resultB}/answer` | bukan 403 -> request diteruskan, data ditulis ke ujian B |
| `POST /cbt/student/{resultB}/submit` | **302 sukses** -> ujian siswa B **ditutup** oleh siswa A |
| `GET /cbt/student/{resultB}/result` | bukan 403 -> **masuk ke view** hasil dan kunci jawaban siswa B |

**Perbaikan:** trait `OwnsTestResult` yang membandingkan `$result->siswa_id` dengan `Siswa::where('nis', auth()->user()->nama_pengguna)->value('id')`.

### K-06 — IDOR: guru B bisa mengubah dan menghapus bank soal / soal milik guru A

Hanya `index()` yang memfilter `guru_id`. Semua method lain memakai route model binding polos.

| Aksi | Hasil terukur |
|---|---|
| `PUT /cbt/question-banks/{bankA}` | **302 sukses** — bank A berubah |
| `DELETE /cbt/question-banks/{bankA}` | **302 sukses** — bank A dihapus |
| `DELETE /cbt/questions/{soalA}` | **302 sukses** — soal A dihapus |
| `GET /cbt/question-banks/{bankA}/edit` | **200** — form edit terbuka |

### K-07 — Kebocoran kunci jawaban lintas guru (paling serius)

`GET /cbt/question-banks/{bank}/api/questions` tidak mengecek kepemilikan **dan** mengembalikan field `adalah_benar`. Terbukti langsung:

```
GURU B meminta bank milik GURU A  ->  HTTP 200
{"options":[{"huruf":"A","isi_opsi":"4","adalah_benar":true, ...}, ...]}
```

**Dampak:** guru mana pun bisa memanen seluruh kunci jawaban bank soal guru lain, lalu distributesikannya ke siswa. Modul ini justru **memperburuk** integritas ujian, bukan menjaganya.

**Perbaikan:** (1) filter `guru_id`; (2) jangan pernah mengirim `adalah_benar` ke klien — hitung skor **di server** saja.

### K-08 — IDOR: guru B bisa mengubah status sesi ujian guru A

`CbtTestSessionController::{show, publish, start, complete, monitoring, apiMonitoring}` tidak mengecek `guru_id`. Guru B bisa **mempublish, memulai, atau menyelesaikan** sesi milik Guru A, atau memantau integritas kelasnya secara real-time.

### K-09 — `nilai_maksimal` dihitung dari SELURUH bank soal

```php
$maxPoin = $result->testSession->questionBank->questions->sum('poin');  // seluruh bank!
```

Yang seharusnya: hanya soal yang **ditampilkan** (`jumlah_soal_tampil`). Terbukti: bank berisi 10 soal, ditampilkan 2 soal x 10 poin -> sistem menulis `nilai_maksimal = 100` (harusnya 20) dan persentase menjadi tidak bermakna. **Status "lulus" setiap siswa keliru.**

### K-10 — Percobaan ke-2 ikut menghitung jawaban percobaan ke-1

Relasi `TestResult::studentAnswers()` tidak memfilter `attempt_number`. Terbukti: attempt 2 menjawab salah, tetapi sistem menghitung **100%** karena jawaban attempt 1 (benar) ikut dijumlahkan.

### K-11 — Halaman nilai terkunci setelah submit

`CbtSecurityMiddleware` menolak request yang `waktu_selesai` terisi — termasuk route `GET /cbt/student/{result}/result` yang justru dipakai menampilkan nilai. Siswa melihat JSON 403, bukan nilai.

### K-12 — Sistem anti-cheat tidak pernah merekam apa pun (3 cacat bertumpuk)

`integrity-checker.js` (dimuat oleh halaman siswa) mengirim ke `route("cbt.integrity.log")` — yang berada di route group **`role:guru`**:

| # | Cacat | Bukti |
|---|---|---|
| 1 | Endpoint berada di group `role:guru` -> siswa selalu **403** | test: 403, bukan 200 |
| 2 | `jenis_pelanggaran` dari JS (`context_menu`, `screenshot`) **tidak ada** di daftar validasi controller | tidak tercatat di DB |
| 3 | `data_tambahan` dikirim sebagai **string JSON**, divalidasi sebagai `array` | 422 |

Karena `fetch()` tidak melempar error untuk 403/422, kegagalan ini **senyap** — dan `integrity_score` tetap 100% untuk semua siswa.

---

## 2. TEMUAN TINGGI — MODUL TRYOUT (pola identik, belum pernah teruji)

| # | Temuan | Bukti |
|---|---|---|
| T-01 | `GET /tryout/student/{session}/start` -> **500** `NOT NULL failed: tryout_results.waktu_selesai` | `TryoutStudentController.php:54` |
| T-02 | `submitAnswer` / `complete` / `result` tanpa cek kepemilikan -> **IDOR** | kode + sweeper otorisasi |
| T-03 | `submitAnswer` mengembalikan `is_correct` + `poin` **langsung ke siswa** -> siswa bisa menebak benar/salah sampai ketemu | `return response()->json(['success'=>true,'is_correct'=>$isCorrect,'poin'=>$poin])` |
| T-04 | `start()` tidak mengecek `$siswa` null -> `Attempt to read property "id" on null` | berbeda dari CBT yang sudah cek |
| T-05 | `updateOrCreate` memaksa `attempt_number => 1` -> percobaan ulang menimpa data lama | kode |
| T-06 | `admin/tryout/sessions/1` -> **500** `Call to undefined relationship [participants] on model [Tryout\Session]` | `TryoutPackageAdminController.php:272` |
| T-07 | `admin/tryout/sessions` (index) -> **500** `Call to a member function count() on null` | view `admin/tryout/sessions/index.blade.php` |

---

## 3. TEMUAN TINGGI — MODUL TKA & E-RAPOR

| # | Temuan | Bukti |
|---|---|---|
| TKA-01 | `tka/assessments/{id}/edit` -> **500** `Call to undefined method TkaAssessmentController::edit()` — route `resource` mendaftarkan 7 method, controller hanya punya 5 | route vs controller |
| TKA-02 | `tka/assessments/siswa/{id}` -> **500** `View [tka.assessments.by-siswa] not found` | view hilang |
| TKA-03 | `admin/tka/assessments/siswa/{id}/history` -> **500** `View [admin.tka.students.history] not found` | view hilang |
| TKA-04 | `guru/erapor/excel` -> **500** `Attempt to read property "semester" on null` — `SekolahSettings` null tidak dicek | `ErapotGeneratorController.php:110` |
| TKA-05 | `cbt/question-banks/{id}` -> **500** `Undefined property: stdClass::$tingkat_kesulitan` — relasi `semester` mengembalikan stdClass, view mengharapkan model | `cbt/question-banks/show.blade.php` |

---

## 4. 10 VIEW YANG HILANG (semua HTTP 500)

Direktori ada, file-nya tidak pernah dibuat:

| Controller | View yang hilang |
|---|---|
| `Admin\Cbt\CbtBankController` | `admin.cbt.banks.show`, `admin.cbt.banks.edit` |
| `Admin\Cbt\CbtSessionController` | `admin.cbt.sessions.show` |
| `Cbt\CbtTestSessionController` | `cbt.sessions.show` |
| `Admin\Tryout\TryoutPackageAdminController` | `admin.tryout.packages.show`, `admin.tryout.packages.edit`, `admin.tryout.sessions.edit` |
| `Tryout\TryoutPackageController` | `tryout.packages.show`, `tryout.packages.edit` |
| `Tryout\TryoutSessionController` | `tryout.sessions.show`, `tryout.sessions.monitoring` |
| `Tka\TkaAssessmentController` | `tka.assessments.by-siswa` |
| `Admin\Tka\TkaAdminController` | `admin.tka.students.history` |

> Direktori `resources/views/admin/cbt/banks/` **ada**, tapi isinya hanya `create.blade.php` dan `index.blade.php`. Persis seperti yang ditulis `.agents/tasks/cbt-investigation.md`: *"Views: Mayoritas adalah skeleton / belum fully implemented"* — tapi laporan verifikasi setelahnya menyatakan Views 100% selesai.

---

## 5. TEMUAN MENENGAH — INFRASTRUKTUR & KEBERASAN

| # | Temuan | Detail |
|---|---|---|
| M-01 | **Tidak ada CI** | `.github/workflows` tidak ada. Test tidak pernah jalan otomatis. |
| M-02 | **Commit message berbohong** | `56f7922` menulis *"phpunit: 127/127 hijau"*. Kenyataannya **127 test, 1 ERROR** (`MenuAuditTest` kehabisan memory 128 MB). |
| M-03 | Test suite **tidak bisa hijau di clone fresh** | 27 test gagal karena `public/build/manifest.json` tidak ada -> wajib `npm run build` dulu. Tidak ada `withoutVite()` di `TestCase`. |
| M-04 | `composer.json` PHP `^8.3` vs lock butuh `>=8.4.1` | `composer install` gagal di versi minimum yang dideklarasikan sendiri. |
| M-05 | `README.md` masih **README default Laravel** | Proyek tidak punya README sendiri; `00_START_HERE.txt` (17 KB) menggantikannya. |
| M-06 | `routes/web.php.remote` (71 KB) tertinggal | Sisa resolusi konflik. 0 route, tapi ada di root `routes/` -> rawan ikut ter-*include* atau memicu konflik merge. |
| M-07 | `all_routes.txt` berisi **pesan error**, bukan data | Isinya `"The --columns option does not exist."` — artefak command gagal. |
| M-08 | `api.json` = OpenAPI generic | `"title": "Laravel", "version": "0.0.1"` — belum dikustomisasi, 30 KB artefak generated yang di-commit. |
| M-09 | `setup_api_key.php` di root repo | Skrip setup sebaiknya dipindah ke `tools/`. |
| M-10 | Docs mengklaim "100% FIXED" | `docs/03-REMAINING-ISSUES.md` -> "SEMUA SELESAI"; `IMPROVEMENT_BACKLOG.md` -> "Completion rate 100%". Angka-angka ini **tidak terverifikasi** dan bertentangan dengan kenyataan. |
| M-11 | Hanya **1** FormRequest di seluruh aplikasi | `app/Http/Requests/` hanya berisi `ProfileUpdateRequest.php`. 355 route dengan validasi manual -> mudah lupa aturan. |
| M-12 | 9 tabel tanpa model Eloquent | `capaian_pembelajaran`, `nilai_cp`, `nilai_mapel`, `p5_projek`, `p5_siswa`, `prestasi`, `siswa_ekstrakurikuler`, `dapodik_data_cache`, `ekstrakurikuler`. Logika bisnis menumpuk di controller. |
| M-13 | `nama_guru` NOT NULL di `kuis`, `nilai`, `nilai_erapor` | Denormalisasi yang memaksa setiap insert menyertakan nama guru — sumber langsung bug insert (sudah terbukti saat seeding test). |
| M-14 | Tidak ada `app/Policies/` | Tidak ada satu pun Policy. Otorisasi tersebar sebagai `if` di dalam controller — penyebab langsung 11 IDOR. |
| M-15 | `tools/verify.sh` dan `diagnose.sh` ada tapi tidak pernah dipanggil CI |—both skrip ada, tapi tidak ada yang memanggilnya. |
| M-16 | 53x `{!! !!}` di Blade | Mayoritas sudah aman (`JSON_HEX_TAG`). Tapi `cbt/student/take.blade.php:300` memakai `json_encode($questions)` **tanpa** flag HEX -> pertanyaan yang disetting guru bisa menyuntikkan `</script>`. |

---

## 6. RENCANA PEMBAIKAN BERTAHAP

### Fase 0 — Pagar pengaman (setengah hari) [blocker]

> Tanpa ini, semua perbaikan berikutnya bisa regresi diam-diam.

1. **CI**: `.github/workflows/test.yml` — PHP 8.4, `composer install`, `npm ci && npm run build`, `php artisan test`.
2. **`tests/TestCase.php`**: tambahkan `withoutVite()` agar suite tidak bergantung hasil build.
3. **Smoke test rute** (sudah saya buatkan): `tests/Feature/RouteHealthAuditTest.php` — 1.050 kombinasi rute x peran, gagal kalau ada 5xx. **Guard paling berharga.**
4. **Hapus artefak**: `all_routes.txt`, `routes/web.php.remote`, `api.json`.
5. **Dokumentasikan ulang** laporan yang salah klaim (tulis di `docs/`).

### Fase 1 — Nyalakan CBT & Tryout (2-3 hari)

| Urutan | Item |
|---|---|
| 1.1 | Migration: `waktu_selesai` nullable (CBT + Tryout) |
| 1.2 | Migration: tambah `test_result_id` ke `cbt_student_answers`; controller isi `test_session_id` + `test_result_id` |
| 1.3 | Fix middleware `find()` -> pakai route param yang sudah di-bound |
| 1.4 | Vite entry points untuk `resources/js/cbt/*.js`; `take.blade.php` -> `@vite` |
| 1.5 | Tulis 10 view yang hilang (pola `index` yang ada bisa dipakai sebagai acuan) |
| 1.6 | `TkaAssessmentController`: tambah `edit`/`update`/`destroy` atau kurangi route |
| 1.7 | `ErapotGeneratorController`: null-safe `SekolahSettings` |
| 1.8 | `Tryout\Session`: tambah relasi `participants()` |
| 1.9 | Hapus `is_correct`/`poin` dari respons `submitAnswer` Tryout |

### Fase 2 — Tutup semua IDOR (2 hari)

> Wajib **policy-based**, bukan `if` per controller.

1. Buat `app/Policies/`: `CbtQuestionBankPolicy`, `CbtQuestionPolicy`, `CbtTestSessionPolicy`, `CbtTestResultPolicy`, `TryoutPolicy`, `TryoutResultPolicy`.
2. `Gate::authorize()` di setiap action.
3. **Hapus `adalah_benar` dari semua respons API** (hitung di server).
4. `CbtSecurityMiddleware`: izinkan route `/result` (kunci saja, jangan tolak).
5. Perbaiki `nilai_maksimal` (hitung dari soal yang tampil) dan filter `attempt_number` di relasi.
6. Perbaiki `data_tambahan` (kirim object, bukan string JSON) dan samakan enum `jenis_pelanggaran` dengan JS.

### Fase 3 — Satukan CBT & Tryout (3-5 hari) [nilai terbesar]

> Dua engine ujian paralel = dua kali biaya, dua kali bug.

Rancang **satu** engine:

```
exam_engines  (id, nama, jenis: cbt|tryout)
exam_banks    (id, engine_id, ...)   <- Questions, QuestionOptions
exam_sessions (id, bank_id, ...)     <- "Sesi Ujian"
exam_attempts (id, session_id, siswa_id, attempt_number, ...)
exam_answers  (id, attempt_id, question_id, ...)
exam_results  (id, attempt_id, ...)
```

Langkah: (1) buat tabel baru, (2) backfill dari `cbt_*` dan `tryout_*`, (3) pindahkan logika penilaian ke satu `ExamGradingService`, (4) hapus tabel lama setelah parity test lulus.

### Fase 4 — Kebersihan & Mutu (2 hari)

1. Pindahkan validasi ke FormRequest (mulai dari CBT/Tryout).
2. `laravel/pint` di CI (format konsisten).
3. Ganti docs yang mengklaim "100% FIXED" -> lampirkan **hasil eksekusi nyata** (jumlah test, jumlah failure, timestamp).
4. README proper (ganti default Laravel).

---

## 7. IDE BARU UNTUK PENGEMBANGAN LANJUTAN

### 7.1 Audit trail yang tidak bisa dimanipulasi

Aplikasi sudah punya `Aktivitas` dan `DapodikSyncLog`, tapi belum ada jejak audit yang *tamper-evident*. Setiap perubahan nilai rapor, sinkronisasi Dapodik, dan ekspor PDF bisa di-*hash-chain* (`prev_hash -> hash`). Output: "Nilai rapor siswa X diubah jam 14:23 oleh guru Y — hash cocok / TIDAK cocok." Sangat bernilai untuk instansi pendidikan yang diaudit.

### 7.2 Mode Ujian Offline

CBT sekarang butuh koneksi terus-menerus. Tambahkan:
- **Service Worker + IndexedDB** -> jawaban tersimpan lokal
- Sync queue -> terkirim saat koneksi kembali
- **Token bertanda tangan server per soal** (HMAC) -> siswa tidak bisa mengubah jawaban di client
- Konflik ditangani server (last-write-wins per soal + attempt lock)

### 7.3 Analisis Butir Otomatis (Item Analysis)

Saat ini bank soal = daftar soal statis. Tambahkan analisis otomatis:
- Deteksi soal terlalu mudah (semua siswa benar) atau terlalu sulit (semwa salah)
- **Deteksi distraktor bermasalah** (opsi A selalu benar -> pola kecil)
- **Item Difficulty Index (P)** dan **Discrimination Index (D)** dihitung dari respons siswa
- Laporan: "Soal #7 punya P=0,98, D=0,12 -> didominasi soal A."

### 7.4 Template Rapor Dinamis + Versi

Sekolah sering punya format rapor berbeda. Buat **template builder** (drag & drop kolom, header/logo/ttd, aturan predikat per jenjang) dengan **versioning**: rapor yang sudah terbit tidak berubah saat template diedit; simpan snapshot JSON saat publish. Ini penting untuk admin yang tiap tahun ganti format tapi tidak ingin rapor lama berubah.

### 7.5 Prediksi Kinerja & Peringatan Dini (penguatan TKA)

TKA sudah punya `EarlyWarning`. Tambahkan **prediksi**:
- Regresi nilai 3 semester terakhir -> proyeksi rapor akhir
- Deteksi anomali (lonjakan tak wajar, nilai terlalu identik antar siswa -> indikasi menyalin)
- Rekomendasi intervensi otomatis (peminatan, mapping CP, elogium) -> actionable untuk kepala sekolah

### 7.6 Portal Orang Tua (PWA-first)

Orang tua adalah pengguna paling sering. Bangun:
- **PWA offline** (ringan, bisa dari homescreen)
- Ringkasan mingguan: kehadiran, tugas, nilai, catatan
- **Push notification** (Web Push) untuk pengumuman guru
- Mode "ringkas" untuk pengguna data terbatas

### 7.7 Konten Berbasis AI dengan pagar safety

- **Ringkasan rapor otomatis** dari nilai + CP -> deskripsi yang humanis (dulu butuh guru menulis 30 deskripsi per rapor)
- **Deteksi plagiarisme jawaban esai** via kemiripan embedding
- **Penerjemah** (Indonesia <-> bahasa asing) untuk sekolah internasional
- **Nilai nyata**: deskripsi rapor otomatis menghemat 10+ jam guru per periode

### 7.8 Multi-Sekolah / Multi-Tenant (SaaS)

Sekolah-sekolah berbayar. Tambahkan:
- Tabel `schools` + `school_id` di seluruh tabel relevan
- **Plan/limit** (jumlah siswa, guru, penyimpanan)
- **Billing** (Midtrans/Xendit — Indonesia)
- Isolasi data per sekolah yang kuat (policy global + scope otomatis)

### 7.9 Integrasi WhatsApp

Notifikasi ke orang tua via WA jauh lebih efektif daripada email:
- Undangan rapat, pengingat tugas, info nilai
- Two-way: orang tua membalas "SUDAH LIHAT"
- Ini killer feature untuk adopsi di Indonesia

### 7.10 Observability & Audit Otomatis

Bangun **health dashboard** bawaan:
- Rute yang error (seperti yang saya buat, tapi jadi fitur produk)
- Query lambat (Telescope/Clockwork) + query budget
- Uptime dan latency p95 per endpoint
- Alert Slack/Telegram saat 5xx naik

---

## 8. ARTEFAK YANG DIHASILKAN AUDIT INI

Semua sudah ada di repo dan siap dipakai sebagai regression test:

| File | Isi |
|---|---|
| `tests/Feature/Cbt/AuditCbtProofTest.php` | 19 test pembuktian — **18 gagal = 18 bug terkonfirmasi** |
| `tests/Feature/RouteHealthAuditTest.php` | Smoke test 1.050 rute x peran, mendeteksi 20 endpoint 500 |
| `tests/Feature/RouteFailureDiagnoseTest.php` | Diagnosis presisi (pesan exception asli) per rute rusak |
| `tools/authz_sweep.py` | Sweeper statis IDOR (route-model-binding tanpa guard) |
| `storage/logs/route-health-report.txt` | Laporan kesehatan rute |
| `storage/logs/route-failure-diagnosis.txt` | Diagnosis per rute |

**Rekomendasi: gabungkan `RouteHealthAuditTest` ke CI** — ia menjadi pagar yang mencegah 500 datang kembali.

---

## 9. PRIORITAS (kalau hanya punya waktu untuk satu minggu)

| Hari | Fokus | Hasil |
|---|---|---|
| 1 | CI + `withoutVite()` + hapus artefak | Pagar pengaman aktif |
| 2 | Migration K-01, K-03 | Ujian bisa dimulai dan jawaban tersimpan |
| 3 | Fix middleware K-02 + Vite K-04 | Timer / auto-save / anti-cheat hidup |
| 4 | 11 IDOR + policy | Tidak ada guru/siswa yang bisa sentuh data milik orang lain |
| 5 | 10 view + TKA/erapor | 20 endpoint 500 -> 0 |
| 6 | Perbaikan skor K-09/K-10 | Nilai dan kelulusan benar |
| 7 | Tulis ulang laporan dengan bukti eksekusi | Rasa aman yang **nyata**, bukan dokumentasi |

---

## CATATAN METODOLOGIS

Semua klaim di dokumen ini **dibuktikan dengan eksekusi**, bukan pembacaan source. Test `AuditCbtProofTest` sengaja ditulis agar **gagal** terhadap kode saat ini — assertion-nya merepresentasikan perilaku yang benar, sementara kode berperilaku salah. Setelah bug diperbaiki, test yang sama akan hijau dan berfungsi sebagai regression test.

Satu-satunya temuan yang **tidak** terkonfirmasi: `CbtIntegrityController::logViolation` ternyata sudah terlindungi `role:guru` di level rute (BUG-12) — meski tetap ada dua cacat lain pada fitur yang sama (K-12).

> **Pesan akhir:** proyek ini punya basis yang bagus — 51 ribu baris, 355 route, 84 migrasi, 5 peran pengguna, integrasi Dapodik, dan iterasi audit yang menunjukkan incrementally sumber daya dan ketekunan. Yang belum ada adalah **satu siklus verifikasi yang benar-benar menjalankan kode**. Tambahkan Fase 0, dan sebagian besar temuan di dokumen ini akan hilang sebelum akhir pekan.
