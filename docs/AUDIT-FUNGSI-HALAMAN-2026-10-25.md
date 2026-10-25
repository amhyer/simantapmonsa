# Audit Fungsi, Halaman & Tombol SIMANTAP — 25 Oktober 2026

**Branch:** `arena/e708f370-simantapmonsa` · ** Basis:** `3b0e678`
**Permintaan:** *"Bertindaklah sebagai seorang programmer yang mengecek ulang semua
fungsi dan kesesuaian halaman yang ada dan memastikan tidak ada error yang terjadi
di setiap halaman dan setiap button yang diklik."*

---

## 1. Ringkasan Eksekutif

| Metrik | Hasil |
|---|---|
| Route terdaftar | **363** (default) / **391** (CBT aktif) |
| Template Blade | **224** |
| File PHP | **353** — semua lolos `php -l` |
| Request runtime dieksekusi | **2.737** (CBT aktif) + **1.400** (CBT nonaktif/default) + **600** spot pasca-perbaikan |
| Error HTTP ≥ 500 setelah perbaikan | **0** |
| Test PHPUnit dieksekusi | **244 test / 1.069 assertions lulus**; 2 metode berbasis Artisan dikecualikan karena runtime WASM (lihat §6) |
| Pemeriksaan statis repo | **39/39 lulus** |
| Build frontend (`npm run build`) | **Berhasil** |
| Temuan aplikasi & frontend diperbaiki | **23 temuan runtime + temuan tautan, form, teks, dan klik frontend** |
| Test regresi baru | **13 test / 197 assertions** (`tests/Feature/AuditHalamanRegressionTest.php`) |

**Kesimpulan:** crawl Laravel sebelumnya mencatat 0 error HTTP 500; pemeriksaan lanjutan juga menguji klik landing page di JSDOM dan menemukan 0 error JavaScript. Halaman pendaftaran, tautan, materi blog, dan teks yang tidak sesuai implementasi telah dibenahi. Dua metode PHPUnit tetap memiliki batasan PHP-WASM yang dijelaskan di §6.

---

## 2. Metodologi & Lingkungan

Audit dijalankan di sandbox dengan PHP 8.5 (PHP‑WASM), SQLite, dependensi
`composer.lock` (99 paket produksi + 35 dev) dan `npm`. Basis data diisi seeder
`DatabaseSeeder` + `DemoDataSeeder`, lalu 63 tabel kosong diisi data uji agar
halaman detail/show ikut terender.

Lapisan pemeriksaan:

1. **Statis** — lint seluruh PHP, `view:cache`, `route:cache`, `config:cache`,
   `npm run build`, `tools/verifikasi-perbaikan.php`, serta pemindaian silang
   semua `route()`, `@extends/@include`, `@method()` form, dan `fetch()` JS
   terhadap route yang benar-benar terdaftar.
2. **Runtime halaman** — crawler HTTP yang mengunjungi **setiap** route GET
   untuk 7 peran (`guest, admin, guru, siswa, ortu, kepsek, tendik`).
3. **Runtime tombol** — request POST/PUT/PATCH/DELETE ke **setiap** endpoint
   tulis dengan payload kosong untuk memastikan validasi bekerja (bukan 500).
4. **PHPUnit** — seluruh `tests/Unit` dan `tests/Feature`, dijalankan per file
   karena runtime WASM memiliki keterbatasan (lihat §6).
5. **Regresi** — test baru yang mengunci setiap bug agar tidak kembali.

> Catatan: `.env`, `vendor/`, `node_modules/`, `public/build/`,
> `database/database.sqlite` dan cache adalah artefak lokal yang **diabaikan
> git**; tidak ada yang ikut ter-commit.

---

## 3. Temuan & Perbaikan

### 3.1 Error 500 yang terdeteksi saat halaman dibuka

| # | Lokasi | Akar masalah | Dampak | Perbaikan |
|---|---|---|---|---|
| 1 | `Guru/GuruKebiasaanController.php` | `$semuaSkor` berisi **nama kolom** (string) lalu di-`avg()` → `TypeError: int + string`; `$kebiasaanByDate` di-`toArray()` padahal view membaca `$item->bangun_pagi` | Halaman **Kebiasaan guru** 500 bila ada laporan | Rata-rata dihitung dari **nilai angka 1–4**; grouping tetap berupa model Eloquent |
| 2 | `Kepsek/DashboardController.php` | `$kehadiranSemua` sudah `groupBy('siswa_id')`, tapi difilter seolah berisi model (`$h->siswa_id`) | **Dashboard kepsek** 500 saat ada data kehadiran | Filter berdasarkan **key** koleksi lalu `flatten(1)` |
| 3 | `Kepsek/RekapController.php` | `getPredikat()` mengembalikan array, view mencetak `{{ $ds['predikat'] }}` → `htmlspecialchars(): array given` | **Detail Rekap Guru** 500 | Ambil hanya `['huruf']` |
| 4 | `Admin/PetaKelasController.php` | `$pengaturan->siswa->count()` — relasi `siswa` tidak ada di model | Tombol **Hapus peta kelas** 500 | Hitung langsung `Siswa::where('guru_id', …)->exists()` |
| 5 | `Admin/DataReferensiController.php` (`storeLogoTtd`) | Membuat `sekolah_settings` tanpa `nama_sekolah` (NOT NULL) | Tombol **Simpan Logo/TTD** 500 di instalasi baru | Arahkan ke Identitas Sekolah dengan pesan, tanpa membuat baris setengah jadi |
| 6 | `Admin/DataReferensiController.php` (`destroyPembelajaran`) | FK `kehadiran_mapel` memakai `RESTRICT` | Tombol **Hapus jadwal pembelajaran** 500 | Tangkap `QueryException`, tampilkan pesan ramah |
| 7 | `Jobs/ExportBackupJob.php` | Baris pivot tanpa model adalah `stdClass`, tapi dipanggil `->toArray()` | Tombol **Export Backup** 500 saat tabel pivot berisi data | Konversi `(array) $row` untuk stdClass |
| 8 | `Models/CbtQuestionBank.php` | Controller/admin memakai `with('guru')`, relasi tidak ada | **Admin › Bank Soal CBT** 500 | Tambahkan `guru(): BelongsTo` |
| 9 | `Cbt/CbtTestSessionController.php` + `cbt/sessions/monitoring.blade.php` | `count($r->answers)`, `count($question_snapshot)`, `$deadline_at->format()` tanpa penjaga null | **Monitoring CBT** 500 pada data belum lengkap | `?? []`, null-safe `?->`, dan fallback `'—'` |
| 10 | `Services/CbtExamService.php` | `CbtScoring::grade(null, …)` saat snapshot/jawaban sesi kosong | Scheduler **`cbt:finalize-expired`** berhenti | Paksa array sebelum penilaian |
| 11 | `Cbt/CbtStudentController.php`, `cbt/student/result.blade.php` | `publicQuestions(null)`, `count(null)`, `submitted_at->format()` | Halaman **petunjuk/hasil CBT** 500 | Default `[]` dan null-safe operator |
| 12 | `siswa/nilai/index.blade.php` | `Eloquent\Collection::merge()` memanggil `getKey()` pada `stdClass` hasil kuis | Halaman **Nilai siswa** 500 bila ada nilai kuis | `collect()->concat()` (Support Collection) |
| 13 | `siswa/kuis/show.blade.php` | `$soal` hanya didefinisikan di cabang `@else`; `@push('scripts')` tetap berjalan saat kuis **sudah dikerjakan** | Halaman kuis 500 + error JS di browser | `$soal` didefinisikan di atas; skrip hanya di-push saat form benar-benar ada |
| 14 | `tryout/student/result.blade.php` | `waktu_mulai/selesai->format()` dan `count(analisis_kelemahan)` tanpa penjaga null | Halaman **Hasil Tryout** 500 | `?->format() ?? '—'`, `?? []`, `durasi ?? 0` |

### 3.2 Error pada tombol / form (405 dan RouteNotFound)

| # | Lokasi | Masalah | Perbaikan |
|---|---|---|---|
| 15 | `cbt/question-banks/show.blade.php` | `route('cbt.questions.edit', …)` berada di dalam **komentar HTML** `<!-- -->`; Blade tetap mengeksekusinya, padahal route tidak ada → `RouteNotFoundException` | Diganti komentar Blade `{{-- --}}` |
| 16 | `admin/tka/warnings/index.blade.php` | Form `POST` memakai `@method('PATCH')`, route hanya `POST` → klik **Update Status** 405 | `@method` dihapus |
| 17 | `guru/integrasi/index.blade.php` | Form `POST` memakai `@method('DELETE')`, route hanya `POST` → klik **Putuskan** 405 | `@method` dihapus |
| 18 | `Admin/TranskripController.php` | `$validated['nomor_ijazah']` undefined saat field opsional tidak dikirim | `?? null` |
| 19 | `Admin/PresensiController.php` | `$validated['jam_masuk']` / `jam_pulang` undefined saat opsional kosong | `?? null` pada pengecekan |
| 20 | `Guru/SiswaController.php` | `$validated['nama_orang_tua']` undefined saat opsional kosong | `?? null` |

### 3.3 Alat verifikasi & konfigurasi

| # | Lokasi | Masalah | Perbaikan |
|---|---|---|---|
| 21 | `tools/verifikasi-perbaikan.php` | Memindai FQCN di dalam **komentar PHP** → lapor palsu `TryoutCategoryController tidak ditemukan` (padahal route sengaja dinonaktifkan) | Gunakan `kodeBersih()` (tokenizer) sebelum `preg_match` |
| 22 | `verify.sh` | Timezone di-hardcode `Asia/Jakarta`, sementara `.env.example` & `config/app.php` memakai `Asia/Makassar` (WITA) → skrip selalu gagal | Bandingkan `config('app.timezone')` dengan `APP_TIMEZONE` dari `.env` |
| 23 | `tests/Feature/AuditHalamanRegressionTest.php` | — | 13 test regresi baru: halaman kebiasaan, dashboard kepsek, tombol hapus/nomor ijazah/logo/backup, nilai & kuis siswa, relasi CBT, pemindaian route dan `@method`, tautan landing, dan kecocokan peran pendaftaran |

---

## 4. Pemeriksaan Lanjutan: Landing Page, Pendaftaran & Klik Frontend

Audit statis berikutnya menemukan bahwa sebagian elemen tampak aktif tetapi
mengarahkan ke target kosong, mengaku berhasil tanpa menyimpan apa pun, atau
menampilkan klaim yang tidak didukung implementasi. Perbaikan tambahan:

- Form pendaftaran menampilkan opsi **Kepala Sekolah**, padahal controller hanya
  menerima `guru`, `siswa`, dan `ortu`; opsi yang tidak diterima dihapus.
- Petunjuk password menyebut minimal 6 karakter, sementara controller meminta
  minimal 8; teks diperbaiki. Pemberitahuan aktivasi akun dan pembuatan akun
  kepala sekolah oleh administrator dibuat jelas. Tautan legal kosong diganti
  pemberitahuan faktual agar pengguna tidak dianggap menyetujui dokumen yang
  belum tersedia.
- Tombol video sebelumnya memutar video YouTube yang tidak terkait. Kini klik
  membuka dialog yang menjelaskan video belum tersedia, menyediakan tautan
  Login, dan dapat ditutup dengan tombol, klik backdrop, atau Escape; fokus
  dikembalikan ke pemicu.
- Form newsletter sebelumnya menampilkan sukses palsu setelah `setTimeout`
  tanpa mengirim/menyimpan email. Form tersebut diganti penjelasan jujur bahwa
  newsletter belum tersedia dan tautan ke repositori proyek.
- Tautan `href="#"`, alamat WhatsApp dummy, ikon sosial yang tidak punya tujuan,
  gambar placeholder eksternal, angka penggunaan/kepuasan yang tidak punya
  sumber, dan testimoni beratribusi yang tidak dapat diverifikasi dihapus atau
  diganti konten internal/terukur. Klaim biaya, penyimpanan Google Sheet, hosting,
  privasi, dan keamanan diperjelas agar mengikuti konfigurasi tiap sekolah.
- FAQ yang sebelumnya berupa `<div>` dengan click handler diubah menjadi tombol
  native yang dapat diakses keyboard dan menyinkronkan `aria-expanded`.
- CTA hasil kuis memakai URL `route('register')`, bukan path `/register` yang
  mengasumsikan aplikasi dipasang di root. AOS dan penyimpanan browser kini
  ditangani dengan aman jika CDN/storage tidak tersedia; cookie notice tidak lagi
  berpura-pura bisa menolak cookie sesi esensial.

### Uji klik frontend (JSDOM)

HTML landing dirender oleh Laravel (HTTP 200, 93 KB), lalu diuji dengan JSDOM.
**8 interaksi lulus:** tidak ada link kosong, FAQ buka/tutup dan atribut ARIA,
modal info demo buka/tutup/Escape serta pemulihan fokus, menu responsif,
seluruh alur kuis (pilih/jawab/hasil/ulang/CTA), dark mode, pemberitahuan cookie,
dan tombol berbagi. Pengujian ulang dengan `localStorage` sengaja dibuat tidak
tersedia juga lulus. Tiga blok skrip inline landing lolos `node --check`; tidak
ada error JS saat skenario klik dijalankan.

> JSDOM menguji DOM dan event handler, bukan Chromium/aksesibilitas lintas-browser.
> Tidak ada browser Chromium tersedia di sandbox, sehingga uji penerimaan akhir
> di browser nyata tetap disarankan.

---

## 5. Hasil Verifikasi Akhir

| Pemeriksaan | Sebelum | Sesudah |
|---|---|---|
| Crawl CBT **aktif** (7 peran × semua route) | 5 → 26 error 500 saat data diperkaya | **0** dari 2.737 request |
| Crawl CBT **nonaktif** (default produksi) | — | **0** dari 1.400 request GET |
| Spot pasca-perubahan terakhir (admin/guru/siswa) | — | **0** dari 600 request |
| `tools/verifikasi-perbaikan.php` | 38 lulus, **1 gagal** | **39 lulus, 0 gagal** |
| Statis: nama route hilang / view hilang / `@method` mismatch | 1 / 0 / 2 | **0 / 0 / 0** |
| Link `href="#"` / video & kontak palsu / handler newsletter palsu | ditemukan | **dihapus/diperbaiki** |
| PHP lint + `view:cache` + `route:cache` + `config:cache` | lolos | **lolos** |
| `npm run build` (Vite 8) | lolos | **lolos** |
| Klik frontend JSDOM | belum | **8 interaksi + storage terblokir, tanpa error JS** |
| PHPUnit | 38/41 file hijau | **40/42 file selesai penuh; dua file sisanya lulus saat masing-masing satu metode Artisan dikecualikan** |
| Total test tereksekusi | — | **244 test, 1.069 assertions lulus; 2 metode Artisan-in-test tidak dapat dieksekusi di WASM** |

Distribusi respons crawl terakhir (CBT aktif): `403` = 1.860 (hak akses, wajar),
`302` = 592 (redirect login/validasi), `200` = 243, `404` = 13 (data dummy tidak
benar-benar ada), `422/401/409/400` = 29 (validasi & API), **`500` = 0**.

---

## 6. Batasan Audit (Kejujuran Operasional)

1. **Runtime PHP‑WASM sandbox.** Dua metode test tidak dapat dieksekusi di
   lingkungan ini karena bug runtime (`null function or function signature
   mismatch` saat `$this->artisan(...)` dipanggil dalam PHPUnit bersama data
   penuh):
   - `CbtExamTest::test_command_finalisasi_bekerja_tanpa_browser_dan_idempoten`
   - `ExamEngineFoundationTest::test_backfill_menyalin_cbt_dan_tryout_lalu_idempoten`

   Keduanya **bukan bug aplikasi**: perintah `cbt:finalize-expired` dan
   `exam:backfill` sudah diverifikasi berjalan sukses lewat `php artisan …`
   **dan** `Artisan::call()` dengan database berisi data. Metode lain di kedua
   file tersebut lulus (27 test / 102 assertions dan 3 test / 13 assertions).
   Masalah `usleep()` pada `Illuminate\Support\Timebox` (login kata sandi salah)
   juga sudah diidentifikasi sebagai keterbatasan WASM dan diatasi di level
   vendor-only untuk keperluan audit; `AuthenticationTest` kini **4/4 lulus**.
2. **Database uji memakai SQLite.** `.env.example` produksi memakai PostgreSQL.
   Seluruh 83 migrasi lolos di SQLite; disarankan menjalankan ulang
   `php artisan test` dan `./verify.sh` di server/target PostgreSQL sebelum
   deploy.
3. **Integrasi eksternal** (TKA Kemendikdasmen, Google Sheets, Dapodik bridge)
   tidak dapat dipanggil dengan kredensial asli dari sandbox (jaringan dibatasi).
   Jalur gagalnya sudah terekam di log tanpa menimbulkan 500 pada halaman.
4. **Modul CBT** sengaja `false` secara default (`config/simantap.php`). Audit
   dilakukan dua kali: CBT aktif **dan** nonaktif. Tautan CBT sudah dijaga
   `@if(config(...) && Route::has(...))`, sehingga mode nonaktif tetap aman.

---

## 7. Cara Menjalankan Ulang di Mesin Anda

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --force

# Pemeriksaan statis & regresi
php tools/verifikasi-perbaikan.php
php artisan test --filter=AuditHalamanRegressionTest
php artisan test

# Cache produksi & verifikasi deploy
php artisan config:cache && php artisan route:cache && php artisan view:cache
./verify.sh
```

---

## 8. Berkas yang Diubah

**Controller / Model / Service / Job (14):**
`Admin/DataReferensiController.php`, `Admin/PetaKelasController.php`,
`Admin/PresensiController.php`, `Admin/TranskripController.php`,
`Cbt/CbtStudentController.php`, `Cbt/CbtTestSessionController.php`,
`Guru/GuruKebiasaanController.php`, `Guru/SiswaController.php`,
`Kepsek/DashboardController.php`, `Kepsek/RekapController.php`,
`Jobs/ExportBackupJob.php`, `Models/CbtQuestionBank.php`,
`Services/CbtExamService.php`, `tests/Feature/AuditHalamanRegressionTest.php` (baru).

**View (10):** `admin/tka/warnings/index`, `cbt/question-banks/show`,
`cbt/sessions/monitoring`, `cbt/student/result`, `guru/integrasi/index`,
`siswa/kuis/show`, `siswa/nilai/index`, `tryout/student/result`, `landing`, `auth/register`.

**Perkakas (2):** `tools/verifikasi-perbaikan.php`, `verify.sh`.

---

## 9. Tindak Lanjut Verifikasi (setelah merge audit)

Pemeriksaan lanjutan menemukan beberapa aksi yang masih berupa placeholder serta
ketidakcocokan tampilan/filter TKA. Tindak lanjut pada PR #8:

- Tombol ekspor hasil Tryout kini menghasilkan **XLSX** dan memakai filter yang
  sama dengan halaman daftar (kelulusan, paket, sesi, tanggal, pencarian).
- Dashboard TKA kini menyediakan laporan massal per semester dan ekspor data
  **penilaian, early warning, dan dimensi** ke XLSX/PDF. Ekspor ditolak dengan
  pesan jelas ketika tidak ada data yang sesuai.
- Halaman alignment e-Rapor kini dapat mencoba memetakan ulang nilai yang masih
  belum terhubung dan mengunduh seluruh nilai unmatched sebagai CSV UTF-8.
- Filter tingkat early warning disesuaikan dengan enum DB (`rendah`, `sedang`,
  `tinggi`, `kritis`); kartu hitungan memakai agregat seluruh data dan tidak
  mengulang status yang sama.
- Kolom tabel penilaian TKA dirapikan agar jumlah sel cocok dengan header.
- Halaman bank soal CBT yang aktif memakai `resources/views/cbt/banks/show.blade.php`,
  yang sudah memiliki form edit soal. File `resources/views/cbt/question-banks/show.blade.php`
  adalah view lama yang tidak dipanggil controller aktif; placeholder di file
  lama itu tidak menjadi tombol pada halaman aktif.

**Verifikasi tindak lanjut:** PHP lint PHP-WASM untuk sembilan file PHP yang
berubah lulus; job CI **PHPUnit (PHP 8.4)** pada PR #8 juga lulus. File dependensi
`vendor/` tidak tersedia di sandbox sehingga PHPUnit tidak dijalankan lokal.

**Masih perlu verifikasi di lingkungan target:** PostgreSQL produksi, integrasi
langsung TKA/Google Sheets/Dapodik dengan kredensial asli, dan browser Chromium
serta uji aksesibilitas lintas-browser. Periksa hasil PR #8 sebelum deploy.
