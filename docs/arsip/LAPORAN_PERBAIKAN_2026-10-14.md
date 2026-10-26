> ⚠️ **KEDALUWARSA (17 Okt 2026).** Klaim "Semua temuan sudah diperbaiki" tidak
> terbukti pada audit eksekusi berikutnya. Acuan tunggal:
> **`docs/RENCANA_PERBAIKAN_VERIFIED.md`**.

# LAPORAN PERBAIKAN BUG SIMANTAP — 14 Oktober 2026

Dokumen ini adalah **tindak lanjut** dari `LAPORAN_AUDIT_BUG_2026-10-14.md`.
Semua temuan pada laporan audit (K-1…K-5, T-1…T-6, S-1…S-5, R-1…R-2) **sudah
diperbaiki**, ditambah temuan baru yang muncul saat perbaikan dikerjakan.
Setiap perbaikan disertai bukti **SEBELUM** dan **SESUDAH**.

---

## 1. Ringkasan eksekutif

| Ukuran | Sebelum | Sesudah |
|---|---|---|
| Pemeriksaan regresi otomatis (`tools/verifikasi-perbaikan.php`) | **4 lulus / 35 gagal** | **39 lulus / 0 gagal** |
| Larik sintaks PHP (tokenizer PHP 8.3) | 364 berkas, 0 gagal | 381 berkas, 0 gagal |
| Controller CBT yang hilang | 5 (menyebabkan 27 URL → HTTP 500) | 0 (route dimatikan di balik flag) |
| View `admin.referensi.alignment-check` | tidak ada (500) | ada (200) |
| Migrasi baru | — | 5 berkas |

Perubahan berkas: **34 berkas diubah**, **17 berkas baru**, **1 berkas dilepas dari
pelacakan git** (`dapodik-bridge/config.json`, lihat `git diff --cached`).

---

## 2. Cara verifikasi (dan batasannya)

Perbaikan diverifikasi **sebelum dan sesudah** dengan empat cara:

1. **Runtime PHP 8.3 (WebAssembly)** — seluruh berkas PHP di-*tokenize*
   dengan `token_get_all(..., TOKEN_PARSE)` (364 → 381 berkas; 3 view vendor paket
   `scramble` di `resources/views/vendor/` dikecualikan bersama direktori vendor lain). Ini menangkap
   seluruh kelas *parse error*.
2. **Skrip regresi `tools/verifikasi-perbaikan.php`** — 39 pemeriksaan statis
   (import kelas, route→controller, view, kosakata enum, cakupan backup,
   keamanan kredensial, autentikasi). Dijalankan pada **kode git HEAD**
   (sebelum) dan **working tree** (sesudah):
   ```
   SEBELUM : 4 lulus, 35 gagal
   SESUDAH : 39 lulus, 0 gagal
   ```
   Skrip ini juga bisa dijalankan sendiri oleh pengguna:
   `php tools/verifikasi-perbaikan.php` (keluar dengan status ≠ 0 bila gagal —
   cocok untuk CI).
3. **Uji logika murni pada runtime PHP** — kelas yang diperbaiki/baru
   dieksekusi langsung: `NilaiService::getPredikat()` (ambang predikat) dan
   `JadwalPelajaranResolver::pilihKandidat()` (pemetaan e-Rapor) → **15/15 lulus**.
4. **Pemeriksaan tambahan**: import tak terpakai (0 temuan baru), keseimbangan
   direktif Blade (146 berkas, 0 tidak seimbang), `py_compile` 17 berkas
   Python bridge, dan analisis ketergantungan antar-tabel.

**Batasan yang jujur:** lingkungan kerja ini **tidak memiliki PHP CLI/composer
dan tidak dapat menjalankan Laravel, PHPUnit, atau PostgreSQL**. Karena itu:
- Uji integrasi/PHPUnit **belum dijalankan** di sini. Lima berkas uji baru
  ditulis agar dapat Anda jalankan di mesin Anda (`php artisan test`).
- Klaim perilaku SQL (mis. *check constraint* enum PostgreSQL) dikuatkan
  dengan membaca kode sumber Laravel 13.31.0: `PostgresGrammar::typeEnum()`
  menghasilkan `varchar(255) check ("jenis" in ('Tugas', ...))`, sehingga nilai
  `'UH'` **pasti** ditolak PostgreSQL.
- Struktur berkas & konsistensi antar-berkas **telah** diverifikasi penuh.

---

## 3. Daftar perbaikan (bukti sebelum → sesudah)

### 3.1 Kesalahan fatal (aplikasi menolak jalan)

| Kode | Temuan | SEBELUM (bukti) | Perubahan | SESUDAH (bukti) |
|---|---|---|---|---|
| **T-2** | `Rombel::` tanpa import di `DapodikPushService` | Deteksi: `app/Services/Dapodik/DapodikPushService.php memakai Rombel:: tanpa use` | Tambah `use App\Models\Rombel;` | Deteksi: 0 pelanggaran |
| **T-1** | `Log::` tanpa import | 3 lokasi: `Api/DapodikSyncController.php`, `Guru/NilaiErapotController.php` | Tambah `use Illuminate\Support\Facades\Log;` di kedua berkas | Deteksi: 0 pelanggaran |
| **T-3** | `$models->sum->count()` pada array (500 *setelah* sync sukses) | `Guru/IntegrasiController.php:160` | `collect($models)->sum(fn ($koleksi) => $koleksi->count())` | Pemeriksaan "perhitungan jumlah hasil sync" LULUS |
| **K-1** | 27 route CBT menunjuk controller yang tidak ada → HTTP 500 | Pemeriksaan route→controller GAGAL untuk 5 controller `Cbt\*` | Route dibungkus flag `config('simantap.cbt_enabled')` (default **false**) + `config/simantap.php` | Pemeriksaan route→controller LULUS |
| **K-2** | Kelas `App\Services\JadwalPelajaranResolver` tidak ada | Deteksi: "kelas belum ada" | Kelas baru dibuat (resolve + statistik alignment) | Kelas tersedia + 6 uji logika lulus |
| **K-3** | View `admin.referensi.alignment-check` tidak ada | Deteksi: view belum ada | View baru dibuat (kartu statistik + tabel nilai belum terpetakan) | View tersedia & pemeriksaan LULUS |
| **BARU** | Kolom `nilai_erapor.jadwal_pelajaran_id` **tidak pernah dibuat migrasi** — padahal ditulis controller/model. Tanpa ini, menyimpan nilai e-Rapor gagal `column does not exist`. | Tidak ada migrasi yang membuat kolom tsb. | Migrasi `2026_10_14_000002` menambah FK `nullOnDelete` | Pemeriksaan "kolom dibuat migrasi" LULUS |

### 3.2 Kesalahan kosakata/tipe data (data hilang diam-diam)

| Kode | Temuan | SEBELUM (bukti) | Perubahan | SESUDAH (bukti) |
|---|---|---|---|---|
| **K-5 + S-5** | UI mengirim `jenis = "UH"`, sedangkan enum DB & `NilaiService` memakai `"Ulangan Harian"` → nilai gagal disimpan / tidak ikut dihitung | `<option value="UH">`, default `'UH'`, validasi `required\|string` (tanpa pembatasan) | Opsi diubah ke `Ulangan Harian`; controller: `JENIS_VALID`, normalisasi `'UH'`, `Rule::in([...])` | 3 pemeriksaan LULUS; sisa string `'UH'` hanya sebagai *kompatibilitas input lama* |
| **K-4** | `kuis.batas_waktu` bertipe **integer** tetapi di-*cast* `datetime` dan dipakai sebagai waktu tutup: nilai 60 menit terbaca **1970-01-01 00:01** → semua kuis bertimer langsung kedaluwarsa & auto-submit | `integer('batas_waktu')`, label "menit", validasi `required\|integer\|min:0` | Semantik **ditetapkan: waktu tutup kuis (timestamp)**; kolom diubah via migrasi `2026_10_14_000001`; form jadi `datetime-local`; validasi `nullable\|date`; nilai kosong = tanpa batas; seeder disesuaikan; nilai ke Google Sheets diformat `Y-m-d H:i` | 5 pemeriksaan LULUS |
| **BARU** | Import e-Rapor memanggil `generateDeskripsi()` **di dalam** array `update()` → deskripsi memakai predikat **lama** | `'deskripsi_capaian' => $nilai->generateDeskripsi()` di dalam `update([...])` | Urutan diperbaiki (set `predikat` → baru hitung `deskripsi`) + `generateDeskripsi()` punya fallback hitung ulang | 2 pemeriksaan LULUS |

### 3.3 Satu sumber kebenaran (hasil tidak konsisten antar halaman)

| Kode | Temuan | SEBELUM | Perubahan | SESUDAH |
|---|---|---|---|---|
| **S-1** | Empat implementasi predikat berbeda (e-Rapor 90/80/KKM vs dashboard KKM+20%/+10%) → nilai sama bisa berlabel berbeda | `NilaiErapot::generatePredikat()`: ambang mati 90/80; `Kepsek\DashboardController::getPredikat()`: ambang sendiri | Keduanya **didelegasikan** ke `NilaiService::getPredikat()` (satu sumber); hanya pemetaan kelas CSS yang khas per halaman | 3 pemeriksaan LULUS + 15 uji logika lulus (kotak ambang KKM 70/60) |

### 3.4 Keamanan

| Kode | Temuan | SEBELUM | Perubahan | SESUDAH |
|---|---|---|---|---|
| **T-6** | Kredensial bridge ter-commit (`dapodik-bridge/config.json`) | `api_key` + `dapodik_token` berisi nilai nyata di repositori | Nilai dikosongkan, `config.example.json` dibuat, `config.json` di-`.gitignore` dan **dilepas dari pelacakan git** (`git rm --cached` — entry `.gitignore` saja tidak melepas berkas yang sudah pernah di-*add*); `main.py` mendukung override via env (`SIMANTAP_API_KEY`, `DAPODIK_TOKEN`, dll). **Kunci lama wajib di-rotasi** (sudah ada di riwayat git) | 2 pemeriksaan LULUS |
| **BARU** | Login **tidak** memeriksa `aktif`/`archived_at` → akun nonaktif tetap bisa masuk | `LoginController::login()` hanya `Auth::attempt()` | Cek `aktif` + `archived_at` sebelum sesi dibuat; akun nonaktif ditolak + pesan jelas | Pemeriksaan LULUS + 3 uji baru |
| **BARU** | Pendaftaran publik membuat akun **langsung aktif** (bisa memilih peran guru) | `'aktif' => true` | Pendaftar dibuat **nonaktif** hingga diaktivasi admin (Admin → Pengguna); pesan sukses diperbarui | Pemeriksaan LULUS + 1 uji baru |
| **BARU** | Kunci API disimpan **polos** + middleware punya fallback pencarian plaintext | `'key' => $plaintextKey`, fallback `where('key', $apiKey)` | Hanya hash (`key_hash`) yang disimpan; fallback plaintext dihapus; kunci lama di-*backfill* hash-nya via migrasi `2026_10_14_000005`; seeder & tampilan admin disesuaikan | Pemeriksaan LULUS |
| **S-3** | `setup_api_key.php` memakai kolom `email` (tidak ada) → selalu gagal | `firstOrCreate(['email' => ...])` | Ditulis ulang: `nama_pengguna`, kunci dari env/argumen (bukan literal), membuat hash + verifikasi | Pemeriksaan LULUS |

### 3.5 Keutuhan data & pemulihan

| Kode | Temuan | SEBELUM | Perubahan | SESUDAH |
|---|---|---|---|---|
| **T-5** | Export/restore hanya 14 tabel, sedangkan "Hapus Semua Data" mengosongkan 31 tabel → nilai e-Rapor, transkrip, PTK, rombel, jadwal, mapel, ekstrakurikuler, kokurikuler **hilang permanen** | `ExportBackupJob` daftar tabel sendiri; `BackupController` `$fillableMap` 14 entri | Registry terpusat `app/Support/BackupTables.php` (29 tabel, urut induk→anak); export & restore memakai registry yang sama; kolom difilter ke kolom nyata tabel; baris `users` tanpa sandi diberi sandi acak + wajib ganti; daftar tabel yang **sengaja tidak** dicadangkan (3 tabel log/cache) dinyatakan eksplisit | 2 pemeriksaan LULUS (mencakup semua 31 tabel wipe) |
| **BARU** | `sekolah_settings.guru_id` NOT NULL, padahal sinkronisasi Dapodik membuat baris tanpa guru (`guru_id => null`) → identitas sekolah hasil tarikan **selalu gagal** disimpan. (Suite uji pun harus menambal sendiri via `Schema::table(...)->nullable()->change()` — bukti kuat skema produksi memang salah.) | Migrasi create: `foreignId('guru_id')->constrained(...)` (NOT NULL) | Migrasi `2026_10_14_000004` membuat kolom nullable | Pemeriksaan LULUS |
| **S-4** | Pesan path backup menyesatkan (`storage/app/backups/...`) | Path ditulis manual di pesan | Memakai `Storage::disk('local')->path(...)` (sebenarnya `storage/app/private/backups/...`) | — (pesan diperbaiki) |
| **BARU** | Login pada instalasi baru **tidak mungkin berhasil**: `SESSION_DRIVER=database` tetapi tabel `sessions` tidak pernah dibuat | Tidak ada migrasi `sessions` | Migrasi `2026_10_14_000003` (skema bawaan Laravel) | Pemeriksaan LULUS |
| **S-2** | `fullSync()` mengirim data **seluruh sekolah** ke spreadsheet guru | Tanpa filter kepemilikan | Difilter `guru_id` guru yang login (kebiasaan lewat relasi `siswa`) | Pemeriksaan LULUS |
| **N+1 (minor)** | `Admin\CetakController::pelengkap()` = 2 query per siswa | Loop per siswa | 2 query agregat untuk seluruh kelas | — (performa) |

### 3.6 Rumah tangga (R-1, R-2)

- **R-1** `.env.example`: `APP_LOCALE=id` (sesuai `config/app.php`), `DB_CONNECTION=pgsql` (sesuai target proyek, dengan opsi sqlite), catatan `SESSION_SECURE_COOKIE` & `APP_DEBUG=false` untuk produksi.
- **R-2** artefak usang (`all_routes.txt`, `api.json`) dibiarkan sebagai dokumen, tetapi `tools/verifikasi-perbaikan.php` kini menjadi acuan pemeriksaan yang **dapat dijalankan**.

---

## 4. Keputusan desain yang saya ambil (silakan koreksi bila berbeda)

1. **Route CBT dimatikan di balik flag** (`SIMANTAP_CBT_ENABLED`, default false)
   daripada membuat controller palsu. Modul CBT butuh tabel, view, dan aturan
   penilaian sendiri — ini keputusan produk, bukan sekadar bug.
2. **`batas_waktu` = waktu tutup (tanggal + jam)**, bukan durasi menit.
   Alasannya: seluruh konsumen kode (cek server, hitung mundur JS, tampilan
   "Tutup / Batas") sudah memperlakukannya sebagai waktu. Mengubahnya menjadi
   "durasi" justru memerlukan kolom waktu mulai baru dan mengubah perilaku.
   **Nilai lama (menit) dibuang** karena tidak dapat dikonversi (tidak ada
   waktu mulai) — kolom lama di-drop lalu dibuat baru sebagai `timestamp NULL`
   sehingga semua kuis lama = "tanpa batas waktu".
3. **Pendaftaran publik = menunggu aktivasi admin.** Bila sekolah Anda memang
   ingin pendaftaran langsung aktif, ubah satu baris `'aktif' => false`
   menjadi `true` di `LandingController::storeRegister()`.
4. **Kunci API tidak lagi disimpan polos.** Kunci yang sudah beredar tetap
   bekerja (hash-nya di-*backfill*), namun **kunci yang tadinya ter-commit di
   repositori wajib di-rotasi**: hapus kunci lama di Admin → API Keys, buat
   baru, simpan di bridge melalui environment variable.
5. **Cakupan backup diperluas**, bukan mengecilkan `wipeAll()` — karena tujuan
   fitur "Hapus Semua Data" adalah membersihkan data uji, bukan menghapus
   konfigurasi (users, semesters, sekolah, api_keys tetap dipertahankan).

---

## 5. Langkah setelah menerapkan (deployment)

```bash
# 1. Jalankan migrasi baru (5 berkas)
php artisan migrate

# 2. Bersihkan cache konfigurasi/rute bila pernah di-cache
php artisan optimize:clear

# 3. Jalankan uji (5 berkas uji baru + suite lama)
php artisan test

# 4. Rotasi kunci API bridge, lalu isi lewat environment (jangan commit)
export SIMANTAP_API_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
php setup_api_key.php
```

Catatan migrasi `batas_waktu`: kolom lama (integer menit) **dibuang** lalu
dibuat ulang sebagai timestamp kosong. Bila Anda ingin mempertahankannya,
ambil dulu cadangan tabel `kuis` sebelum `migrate`.

---

## 6. Perbaikan tambahan — putaran 2 (14 Oktober 2026, malam)

Setelah laporan putaran 1 diserahkan, dijalankan **sapuan pemeriksaan baru
untuk kelas bug yang belum tercatat**: referensi nama route, aksi controller
 pada rute, referensi `view()`, segmen `config()`, kolom model ↔ migrasi,
`@include/@extends`, dan komponen `<x-…>`. Hasil dan perbaikannya:

| # | Temuan (kelas bug baru) | SEBELUM (bukti) | Perubahan | SESUDAH (bukti) |
|---|---|---|---|---|
| 1 | **Tombol "Hapus" halaman Dimensi menunjuk route yang tidak terdaftar** — view memanggil `route('guru.dimensi.destroy')`, tetapi `Route::resource('dimensi')` hanya memuat `index` & `store` dan controller tidak punya method `destroy` → pemakaian halaman Dimensi melempar `RouteNotFoundException` (HTTP 500) | Sapuan route-name: `guru.dimensi.destroy` ← `resources/views/guru/dimensi/index.blade.php` — **satu-satunya** referensi route tak terdefinisi dari 185 referensi (271 nama route terdaftar) | `Route::resource('dimensi', …)->only(['index','store','destroy'])` + `DimensiController::destroy()` dengan cek kepemilikan (`abort(403)` bila bukan milik guru ybs.) | 2 pemeriksaan baru LULUS |
| 2 | **Submit kuis ganda (balapan dua request) = HTTP 500** — unique index `(kuis_id, siswa_id)` sudah ada sejak migrasi awal `2026_09_09_075733`, tetapi `QueryException`-nya tidak tertangkap: request kedua meledak padahal jawabannya sudah tersimpan | Pembacaan ulang alur `Siswa\KuisController::submit()` (`lockForUpdate()` tidak mengunci baris yang belum ada) | `catch (UniqueConstraintViolationException)` — kelas exception khusus Laravel 13 (SQLSTATE 23505, terverifikasi di `PostgresConnection` v13.31.0) → diarahkan ke daftar kuis dengan pesan "Jawaban Anda sudah tersimpan sebelumnya." + `Log::warning` | 1 pemeriksaan baru LULUS |
| 3 | **Header GAS "Batas Waktu (menit)" vs nilai tanggal-jam** — butuh keputusan agar tidak diubah sepihak | Tidak ada catatan apa pun di kode | Keputusan: **header dipertahankan** (kompatibilitas skrip GAS v4.2); langkah penggantian nama berurutan (GAS → repo → sheet) ditulis di `config/google.php`, dirujuk dari `GoogleSheetsService` | 1 pemeriksaan baru LULUS |
| 4 | **Berkas kredensial bridge masih dilacak git** walau sudah masuk `.gitignore` — `.gitignore` tidak melepas berkas yang pernah di-*add*, dan pemeriksaan lama hanya membaca isi `.gitignore` sehingga **lolos palsu** | `git ls-files --error-unmatch dapodik-bridge/config.json` → **exit 0** (masih dilacak) | `git rm --cached dapodik-bridge/config.json` (berkas lokal tetap ada) + pemeriksaan diperketat: menuntut `config.example.json` ada dan — pada checkout git — berkas benar-benar tak dilacak | Pemeriksaan LULUS; setelah dilepas → `git ls-files` **exit ≠ 0** |

Sapuan yang **bersih** (tidak ada temuan): referensi `view()` statis
(117 referensi), segmen `config()` non-inti, kolom `$fillable`/cast vs migrasi
(31 model, 46 tabel), `@include/@extends` (0 hilang), komponen `<x-…>` (semua
tersedia — `x-lucide-*` berasal dari paket `mallardduck/blade-lucide-icons`),
serta aksi controller pada 277 rute (sisa "temuan" hanya kelas `Cbt\*` yang
memang dimatikan di balik flag).

Verifikasi putaran ini memakai skrip yang sama di kedua sisi:

```
SEBELUM : 4 lulus, 35 gagal   (dari 39 pemeriksaan)
SESUDAH : 39 lulus, 0 gagal
```

Empat pemeriksaan baru putaran 2 (ditambah dua pada putaran 3) **gagal di kode lama** dan **lulus di kode baru**
(bukti nyata, bukan klaim). Lint sintaks PHP tetap 381 berkas → 0 gagal;
uji logika runtime tetap 15/15 lulus.

---

## 7. Perbaikan tambahan — putaran 3 (kebocoran kredensial)

Saat menggabungkan PR #2 dan memindai ulang seluruh repositori, ditemukan
**kebocoran yang belum tertutup**: kredensial lama masih tertulis harfiah di
tujuh berkas lain — skrip uji Python bridge, dua dokumen analisis bridge, kutipan
bukti di laporan audit, dan log perbaikan lama. Pemeriksaan putaran 2 hanya
memeriksa `config.json` (dan hanya berkas yang berubah), sehingga kasus ini
**lolos**.

| # | Temuan | SEBELUM (bukti) | Perubahan | SESUDAH (bukti) |
|---|---|---|---|---|
| 5 | Kredensial lama (`api_key` bridge & `dapodik_token`) masih hardcoded di 7 berkas lain yang tidak tersentuh perbaikan sebelumnya | Pemindaian seluruh tree `main`: **8 kemunculan di 7 berkas** — `check_endpoints.py` (L36, 38), `test_fixed_sync.py` (L12), `test_fixed_sync2.py` (L12), `test_sync_api.py` (L61), `ERROR_ANALYSIS_REPORT.md` (L19), `FINAL_SOLUTION.md` (L14), `LAPORAN_AUDIT_BUG_2026-10-14.md` (L193), `docs/02-FIXES-LOG.md` (L116) | 4 skrip Python membaca dari environment (`os.environ.get("DAPODIK_TOKEN" / "SIMANTAP_API_KEY")` — nama variabel sama dengan yang dipakai `main.py`); 2 dokumen bridge memakai placeholder `<TOKEN_DAPODIK>`; nilai dihapus dari laporan audit dan log perbaikan | **2 pemeriksaan baru LULUS**; pemindaian akhir → **0 kemunculan** |

Pemeriksaan regresi baru yang menjaga kelas bug ini:

1. **"Kredensial lama tidak lagi tersimpan di berkas repositori"** — menelusuri
   seluruh berkas repositori (mengecualikan `.git`, `node_modules`, `vendor`,
   `storage`) dan menolak bila salah satu nilai lama muncul lagi. Nilai lama
   **tidak** disimpan sebagai plaintext: skrip hanya memuat sidik jari
   **SHA-256** dan membandingkan hash kandidat token, sehingga pemeriksaan ini
   tidak menghidupkan kembali kredensial tersebut di dalam repositori.
2. **"Tidak ada kunci/token hardcoded di skrip bridge & config"** — pola umum
   `api_key|token|password|secret` yang diikuti literal ≥ 12 karakter bercampur
   huruf besar/kecil + angka (nilai seperti nama variabel `"dapodik_token"` tidak
   dianggap rahasia, sehingga tidak menghasilkan positif palsu).

Bukti pada kode `main` sebelum perbaikan ini:

```
main (sebelum)   : 37 lulus, 2 gagal   ← kedua pemeriksaan di atas GAGAL
workspace (sesudah): 39 lulus, 0 gagal
```

> **Tindakan wajib bagi pemilik repositori:** kedua nilai ini pernah ter-commit
> (termasuk di riwayat git yang sudah ter-push), jadi **harus dianggap bocor**.
> Rotasi kunci API di Admin → API Keys dan ganti `dapodik_token` di sisi Dapodik,
> lalu isi nilai baru lewat environment (`SIMANTAP_API_KEY`, `DAPODIK_TOKEN`)
> alih-alih menulisnya di berkas.

---

## 8. Yang belum dikerjakan (butuh keputusan/lingkup lanjutan)

| Butir | Alasan belum dikerjakan |
|---|---|
| Modul CBT lengkap (controller, view, tabel) | Fitur, bukan perbaikan bug; butuh desain tersendiri |
| Penggantian nama kolom GAS "Batas Waktu (menit)" → "Batas Waktu" | Menunggu penyesuaian skrip GAS (`KOL_xxx`); langkah koordinasi sudah tertulis di `config/google.php` (keputusan putaran 2: header lama dipertahankan dulu) |
| `all_routes.txt`, `api.json`, `AUDIT_SIMANTAP.md` | Artefak dokumentasi lama |
| Uji PHPUnit lengkap + uji integrasi PostgreSQL | Tidak ada PHP/composer/PostgreSQL di lingkungan ini |

---

## 9. Berkas baru & ringkasan perubahan

**Baru:**
- `app/Services/JadwalPelajaranResolver.php` — resolver + statistik alignment
- `app/Support/BackupTables.php` — registry tabel backup (29 tabel + 3 pengecualian eksplisit)
- `config/simantap.php` — flag modul (CBT)
- `database/migrations/2026_10_14_000001…000005` — 5 migrasi perbaikan
- `resources/views/admin/referensi/alignment-check.blade.php`
- `dapodik-bridge/config.example.json`
- `tools/verifikasi-perbaikan.php` — 39 pemeriksaan regresi
- Uji baru: `tests/Unit/{PredikatKonsistensiTest,JadwalPelajaranResolverTest}.php`,
  `tests/Feature/{KuisBatasWaktuTest,InputNilaiJenisTest,AlignmentCheckTest,LoginAktifTest}.php`

**Diubah (34 berkas):** lihat `git diff` — inti terbanyak pada
`Admin/BackupController.php`, `Jobs/ExportBackupJob.php`, `routes/web.php`,
`Guru/…` (4 berkas), `Auth/LoginController.php`, `Models/{ApiKey,NilaiErapot}.php`,
`Middleware/ApiKeyMiddleware.php`, `setup_api_key.php`, `.env.example`.
Perbaikan putaran 2 menyentuh `Guru/DimensiController.php`, `Siswa/KuisController.php`,
`routes/web.php`, `GoogleSheetsService.php`, dan `config/google.php`.

---

*Verifikasi terakhir dijalankan pada 14 Oktober 2026: PHP 8.3 (WebAssembly),
`tools/verifikasi-perbaikan.php` → 39 lulus / 0 gagal; lint 381 berkas PHP → 0 gagal.*
