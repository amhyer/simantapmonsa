> ⚠️ **KEDALUWARSA (17 Okt 2026).** Klaim "seluruh temuan sudah diperbaiki dan
> diverifikasi" tidak terbukti: audit eksekusi 17 Okt menemukan 18 bug CBT
> terkonfirmasi masih ada. Acuan tunggal: **`docs/RENCANA_PERBAIKAN_VERIFIED.md`**.
>
> **STATUS PER 14 OKTOBER 2026 (malam):** seluruh temuan pada laporan ini sudah
> diperbaiki dan diverifikasi (sebelum → sesudah). Rincian bukti, keputusan
> desain, langkah deployment, dan sisa pekerjaan ada di
> **`LAPORAN_PERBAIKAN_2026-10-14.md`**. Ringkasan verifikasi: skrip
> `tools/verifikasi-perbaikan.php` berubah dari **4 lulus / 33 gagal**
> menjadi **39 lulus / 0 gagal**; 381 berkas PHP lolos pemeriksaan sintaks.

# LAPORAN AUDIT TEKNIS — SIMANTAP
**Tanggal audit:** 14 Oktober 2026
**Metode:** Audit statis penuh (pembacaan kode, migrasi, route, view, service, job, perintah artisan, dan skrip bridge). Runtime PHP/PostgreSQL tidak tersedia di lingkungan audit, sehingga tidak ada eksekusi program; setiap temuan disertai bukti lokasi kode (`file:line`) agar dapat diverifikasi ulang.
**Cakupan:** `app/` (126 kelas), `routes/`, `resources/views/` (145 berkas), `database/migrations/` (53 berkas), `app/Jobs`, `app/Console/Commands`, `tests/`, `dapodik-bridge/`.

> Catatan: dokumen `AUDIT_SIMANTAP.md` (17 Sep 2026) sebagian **sudah usang**. Laporan ini menggantikannya dan hanya memuat temuan yang diverifikasi ulang terhadap kode saat ini.

---

## 1. IDENTIFIKASI PROYEK

### 1.1 Apa ini
**SIMANTAP** adalah aplikasi manajemen sekolah berbasis **Laravel 13** (`laravel/framework v13.31.0`) dengan database **PostgreSQL** (nama database contoh: `simantap_db`) dan autentikasi kustom:

- Kolom login bukan `email`/`password`, melainkan `nama_pengguna` + `kata_sandi` (di-hash), dengan `User::$authPasswordName = 'kata_sandi'`.
- Peran (`peran`): **admin, guru, siswa, ortu, kepsek** — dijaga middleware `role:...`.
- Middleware tambahan: `force.password.change` (paksa ganti sandi), `apikey` (untuk bridge Dapodik), serta alias `cbt.security`/`cbt.session.lock`.

### 1.2 Struktur utama
| Bagian | Isi |
|---|---|
| `app/Http/Controllers/` | 126 kelas: `Admin/`, `Api/`, `Auth/`, `Guru/`, `Kepsek/`, `Ortu/`, `Siswa/`, `DashboardController`, `LandingController`, `TkaController` |
| `app/Models/` | 31 model (User, Siswa, Nilai, NilaiErapot, Kuis, HasilKuis, Kehadiran, Kebiasaan, Dimensi, Catatan, Rombel, Ptk, Semester, MataPelajaran, JadwalPelajaran, Transkrip, Kokurikuler, dll.) |
| `app/Services/` | `NilaiService`, `LaporanService`, `AktivitasService`, `SchoolDataService`, `TKAService`, `TkaStatistikService`, `GoogleSheetsService`, `ErapotKemdikbudService`, `Dapodik/` (4 berkas) |
| `app/Jobs/` | `ExportBackupJob`, `ImportDapodikJob`, `DapodikAutoSyncJob`, `ProsesAkunMassalJob` |
| `app/Console/Commands/` | `simantap:backup` (harian 02:00), `tka:refresh` (tiap 6 jam), `sheets:full-sync` |
| `routes/web.php` | 261 definisi route • `routes/api.php` 5 route • `routes/console.php` penjadwalan |
| `database/migrations/` | 53 migrasi, 45 tabel dibuat |
| `dapodik-bridge/` | Skrip Python jembatan Dapodik (17 berkas `.py`, semua lolos `py_compile`) |

### 1.3 Alur & integrasi
- **Dapodik**: tarik data lewat `POST /api/dapodik/sync/{modul}` (middleware `apikey:dapodik:import`, throttle 120/menit) dan dorong data (push) dari panel admin (`/admin/dapodik/push/*`) via `DapodikPushService`.
- **Google Sheets**: sinkronisasi nilai/kehadiran/kebiasaan/dimensi, manual per-siswa (`SyncableToSheet`) atau massal (`IntegrasiController::fullSync`, `sheets:full-sync`).
- **e-Rapor**: input nilai formatif/sumatif/sumatif-akhir + cetak PDF (dompdf) & Excel (PhpSpreadsheet), transkrip ijazah, cetak leger admin.
- **Kuis daring**: bank soal tersimpan di kolom JSON `kuis.soal`, penilaian otomatis, timer.
- **TKA (Tes Kemampuan Akademik)**: statistik dari layanan eksternal, di-refresh berkala.
- **Cadangan**: export JSON asinkron (`ExportBackupJob`) + import/restore + fitur "wipe" (`wipeAll`).

---

## 2. RINGKASAN TEMUAN

| ID | Tingkat | Temuan | Lokasi kunci |
|---|---|---|---|
| K-1 | 🔴 Kritis | Modul CBT di-route (27 route) tetapi controller & view tidak ada → semua URL `/cbt*` error 500 | `routes/web.php:406-455` |
| K-2 | 🔴 Kritis | Kelas `App\Services\JadwalPelajaranResolver` tidak ada, padahal dipakai halaman alignment & simpan nilai e-Rapor | `Admin/DataReferensiController.php:14,76`; `Guru/NilaiErapotController.php:9,64,137` |
| K-3 | 🔴 Kritis | View `admin.referensi.alignment-check` tidak ada | `Admin/DataReferensiController.php:80` |
| K-4 | 🔴 Kritis | `kuis.batas_waktu` = integer (menit) tetapi di-cast `datetime` → kuis bertimer **tidak pernah bisa dikumpulkan** & auto-submit langsung | `app/Models/Kuis.php:44`; `Siswa/KuisController.php:79`; `resources/views/siswa/kuis/show.blade.php:197-227` |
| K-5 | 🔴 Kritis | Halaman "Input Nilai" menyimpan jenis `"UH"`, sedangkan enum DB & `NilaiService` memakai `"Ulangan Harian"` → simpan gagal / nilai tidak dihitung | `Guru/InputNilaiController.php:30,69,88`; `resources/views/guru/input-nilai/index.blade.php:33`; migrasi `..._create_nilai_table.php:19` |
| T-1 | 🟠 Tinggi | `Log::` dipakai tanpa `use` di dalam namespace → fatal `Class not found` tepat di blok `catch` (justru saat menangani error) | `Api/DapodikSyncController.php:132,209,311`; `Guru/NilaiErapotController.php:105` |
| T-2 | 🟠 Tinggi | `Rombel::` tanpa `use` di service → push Rombel ke Dapodik selalu 500 | `Dapodik/DapodikPushService.php:327` |
| T-3 | 🟠 Tinggi | `$models->sum->count()` pada array (bukan Collection) → 500 setelah full sync Google Sheets sukses | `Guru/IntegrasiController.php:160` |
| T-4 | 🟠 Tinggi | Sinkronisasi Dapodik lama (`DapodikService`) membuat user dengan sandi `guru123` **tanpa** memaksa ganti sandi | `app/Services/DapodikService.php:33` |
| T-5 | 🟠 Tinggi | Backup export vs wipe tidak seimbang: 31 tabel dikosongkan, hanya 14 yang diekspor → data hilang permanen setelah wipe+restore | `Admin/BackupController.php:194-204`; `app/Jobs/ExportBackupJob.php` |
| T-6 | 🟠 Tinggi | Kredensial/kunci rahasia ter-commit di repo (API key bridge + token Dapodik) | `dapodik-bridge/config.json`; `setup_api_key.php` |
| S-1 | 🟡 Sedang | Logika predikat ganda & tidak konsisten antar modul (4 implementasi berbeda) | `NilaiService.php`; `NilaiErapot.php:55-62`; `Kepsek/DashboardController.php:183`; `Ortu/HasPredikatKaih.php` |
| S-2 | 🟡 Sedang | `fullSync` guru menarik **seluruh** data sekolah (semua guru) ke Google Sheet | `Guru/IntegrasiController.php:118-168` |
| S-3 | 🟡 Sedang | Skrip `setup_api_key.php` pasti gagal (memakai kolom `email` yang tidak ada) | `setup_api_key.php:16-18,31` |
| S-4 | 🟡 Sedang | Perintah backup menulis ke `storage/app/private/backups`, tetapi pesannya menyebut `storage/app/backups` | `app/Console/Commands/BackupData.php:31-33`; `config/filesystems.php:35` |
| S-5 | 🟡 Sedang | Validasi `jenis` di endpoint input nilai hanya `required|string` (tidak dibatasi enum) | `Guru/InputNilaiController.php:69` |
| R-1 | 🔵 Rendah | `.env.example` tidak konsisten dengan konfigurasi aplikasi (locale `en`, DB sqlite) | `.env.example`; `config/app.php:81-83` |
| R-2 | 🔵 Rendah | Artefak lama: `all_routes.txt` berisi output error CLI, `api.json` tampak hasil ekspor lama | root repo |

---

## 3. TEMUAN KRITIS (detail)

### K-1. Modul CBT di-route tanpa controller & view
**Lokasi:** `routes/web.php:406-455` (grup `cbt.*` untuk guru dan `cbt.student.*` untuk siswa)
**Bukti:** terdapat 27 referensi `\App\Http\Controllers\Cbt\*` (mis. `CbtQuestionBankController`, `CbtTestSessionController`, `CbtStudentController`, `CbtIntegrityController`), tetapi direktori `app/Http/Controllers/Cbt/` **tidak ada**, dan `resources/views/cbt/` juga **tidak ada**. Middleware `cbt.security` dan `cbt.session.lock` sudah terdaftar (kelas middleware ada), dan tabel CBT tidak ada di migrasi.
**Dampak:** setiap akses `/cbt`, `/cbt/bank`, `/cbt/student`, dsb. melempar `Class ... not found` (HTTP 500). Fitur setengah jadi yang tayang di routing produksi.

**Perbaikan yang disarankan (pilih salah satu):**
1. Hapus/komentari blok route CBT sampai modulnya selesai, **atau**
2. Lengkapi controller, view, dan migrasi tabel CBT.

---

### K-2 & K-3. Kelas `JadwalPelajaranResolver` dan view `alignment-check` tidak ada
**Lokasi:**
- `app/Http/Controllers/Admin/DataReferensiController.php:14` (`use App\Services\JadwalPelajaranResolver;`), `:76` (`alignmentCheck(JadwalPelajaranResolver $resolver)`), `:80` (`view('admin.referensi.alignment-check')`)
- `app/Http/Controllers/Guru/NilaiErapotController.php:9, 64, 137` (`app(JadwalPelajaranResolver::class)->resolve(...)`)
- Route: `routes/web.php:143` → `GET /admin/referensi/alignment` (`admin.referensi.alignment`)
**Bukti:** `grep -rn "class JadwalPelajaranResolver" app/` → kosong; `ls app/Services/` tidak memuat berkas tersebut; `ls resources/views/admin/referensi/` tidak memuat `alignment-check.blade.php`.
**Dampak:**
- Halaman **alignment e-Rapor** → 500.
- **Simpan nilai e-Rapor** (`simpan`) dan **autoSave** → 500 (`Class "App\Services\JadwalPelajaranResolver" not found`) karena resolver dipanggil sebelum/ketika menyimpan, sehingga FK `jadwal_pelajaran_id` tidak pernah terisi. Ini jalur inti fitur e-Rapor.

**Perbaikan:** buat kelas `App\Services\JadwalPelajaranResolver` dengan method `resolve(int $guruId, string $mapel, string $kelas, string $semester, string $tahunAjaran): ?int` (cari `jadwal_pelajaran` yang cocok; `null` bila tidak ada) dan buat view `resources/views/admin/referensi/alignment-check.blade.php`, atau tangguhkan fitur terkait.

---

### K-4. `kuis.batas_waktu`: integer menit yang di-cast `datetime` → timer kuis rusak total
**Lokasi:**
- Migrasi: `database/migrations/2026_09_09_075730_create_kuis_table.php:25` → `$table->integer('batas_waktu')->default(0);`
- Model: `app/Models/Kuis.php:44` → `'batas_waktu' => 'datetime',`
- Form guru: `resources/views/guru/kuis/create.blade.php:78-79` dan `edit.blade.php:75-76` → label **"Batas Waktu (menit)"**, `<input type="number" min="0">`
- Validasi: `app/Http/Controllers/Guru/KuisController.php:44 & 108` → `'batas_waktu' => 'required|integer|min:0'`
- Konsumsi: `app/Http/Controllers/Siswa/KuisController.php:79` → `if ($kuis->batas_waktu && now()->greaterThan($kuis->batas_waktu->copy()->addMinutes(5))) { ... error 'Waktu pengerjaan kuis ini sudah berakhir.' }`
- JS: `resources/views/siswa/kuis/show.blade.php:197-227` → `const batasWaktu = {{ $kuis->batas_waktu->timestamp }};` lalu jika `remaining <= 0` → **auto-submit**.

**Bukti/analisis:** Laravel meng-cast nilai numerik `datetime` sebagai **Unix timestamp**, sehingga nilai `batas_waktu = 60` (menit) dibaca sebagai `1970-01-01 00:01:00`. Akibatnya:
- Semua kuis dengan `batas_waktu > 0` dianggap **sudah kedaluwarsa** sejak 1970 → server **menolak** pengumpulan ("Waktu pengerjaan kuis ini sudah berakhir").
- Halaman kuis siswa langsung menghitung `remaining <= 0` → **form auto-submit seketika**; siswa tidak mungkin mengerjakan.
- Tampilan "Tutup ..." menampilkan tanggal 1 Januari 1970.

**Perbaikan:** pilih satu semantik dan konsistenkan:
- Jika `batas_waktu` = **durasi menit**: hapus cast `datetime`, simpan integer; hitung tenggat = `$kuis->tanggal`/waktu mulai + menit; ubah JS & `Siswa/KuisController` agar memakai tenggat hasil hitungan.
- Jika `batas_waktu` = **timestamp absolut**: ubah migrasi ke `timestamp`, ubah form guru ke input `datetime-local`, lalu sesuaikan validasi.

---

### K-5. "Input Nilai" menulis jenis `"UH"` yang tidak dikenal DB & mesin nilai
**Lokasi:**
- `resources/views/guru/input-nilai/index.blade.php:33` → `<option value="UH">Ulangan Harian</option>`
- `app/Http/Controllers/Guru/InputNilaiController.php:30` → default query `$jenis = $request->jenis ?? 'UH';`
- `app/Http/Controllers/Guru/InputNilaiController.php:69` → `'jenis' => 'required|string'`; `:88` menyimpan `'jenis' => $validated['jenis']`
- Enum DB: `database/migrations/2026_09_09_075731_create_nilai_table.php:19` → `enum('jenis', ['Tugas','Ulangan Harian','Praktik','PTS','PAS'])`
- Mesin nilai: `app/Services/NilaiService.php:31-40` menghitung hanya untuk `'Tugas'`, `'Ulangan Harian'`, `'PTS'`, `'PAS'` (dan mencatat `'Praktik'` terpisah).

**Dampak:**
1. Menyimpan nilai dari halaman **Input Nilai** mengirim `jenis='UH'` → di PostgreSQL kolom enum berupa `varchar + CHECK` → **pelanggaran constraint → HTTP 500**; di MySQL juga gagal.
2. Sekalipun lolos, `NilaiService` tidak pernah memasukkannya ke rata-rata berbobot → **nilai akhir tidak berubah**.
3. Daftar nilai di halaman itu juga selalu kosong untuk pilihan default karena query `where('jenis','UH')`.

**Perbaikan:** samakan kosakata: ubah opsi view menjadi `value="Ulangan Harian"` (atau tambahkan pemetaan `UH → Ulangan Harian` di controller), dan batasi validasi `jenis` dengan `in:Tugas,Ulangan Harian,Praktik,PTS,PAS` seperti di `Guru/NilaiController.php:33`.

---

## 4. TEMUAN TINGGI (detail)

### T-1. `Log::` tanpa import → fatal pada blok `catch`
**Lokasi:**
- `app/Http/Controllers/Api/DapodikSyncController.php:132, 209, 311` → `Log::warning(...)` (catch sync sekolah, peserta didik, PTK)
- `app/Http/Controllers/Guru/NilaiErapotController.php:105` → `Log::error('Gagal menyimpan nilai erapor: ...')`

**Analisis:** berkas berada di namespace `App\Http\Controllers\...`. PHP **tidak** mencari kelas ke namespace global untuk referensi kelas, sehingga `Log::warning()` diresolusikan ke `App\Http\Controllers\Api\Log` (atau `...\Guru\Log`) yang tidak ada → `Error: Class "App\...\Log" not found`. Alias facade (`Log`, `DB`, dst.) hanya berlaku untuk kode di namespace global; jadi alias tidak menyelamatkan kasus ini. Karena tidak ada `use Illuminate\Support\Facades\Log;` pada kedua berkas, baris-baris tersebut **selalu fatal saat dieksekusi** — ironisnya tepat di jalur penanganan error, sehingga alih-alih mengembalikan JSON error yang rapi, endpoint mengirim 500 tanpa jejak log.

**Catatan tambahan:** di `NilaiErapotController:102-106`, `DB::rollBack()` sudah dijalankan sebelum `Log::error`, jadi tidak ada risiko data parsial — namun respons 500 dan tanpa log tetap merugikan operasional.

**Perbaikan:** tambahkan `use Illuminate\Support\Facades\Log;` di kedua berkas (idealnya juga audit menyeluruh: semua `X::` tanpa `import` di namespace).

---

### T-2. `Rombel::` tanpa import → push Rombel selalu gagal
**Lokasi:** `app/Services/Dapodik/DapodikPushService.php:327` (`$rombels = Rombel::with('guru')...`) di dalam `pushRombel()`.
**Bukti:** blok `use` berkas (baris 5-16) memuat `DapodikConfig, DapodikSyncLog, JadwalPelajaran, Kehadiran, Nilai, NilaiErapot, SekolahSettings, Siswa, User, DB, Log, Str` — **tidak ada** `App\Models\Rombel`. Referensi lain di berkas itu memakai FQN (`\App\Models\Semester::`) sehingga aman.
**Dampak:** route `POST /admin/dapodik/push/rombel` (`routes/web.php:271`) → `Error: Class "App\Services\Dapodik\Rombel" not found` → 500; endpoint push lain tidak terpengaruh.
**Perbaikan:** tambahkan `use App\Models\Rombel;`.

---

### T-3. `$models->sum->count()` pada array → 500 setelah full sync berhasil
**Lokasi:** `app/Http/Controllers/Guru/IntegrasiController.php:160` (di dalam `fullSync()`, blok pencatatan riwayat).
**Bukti:** `$models` didefinisikan sebagai **array** (`$models = ['nilai' => ..., 'kehadiran' => ..., ...]`) di baris ~122-127, lalu dipanggil `$models->sum->count()`. Membaca properti `sum` pada array menghasilkan `null` (warning), lalu `null->count()` → `Error: Call to a member function count() on null` yang **tidak tertangkap** `catch (\Exception)`.
**Dampak:** seluruh proses sinkronisasi ke Google Sheets **sudah selesai**, tetapi respons berakhir 500 dan riwayat sinkron tidak tersimpan.
**Perbaikan:** ganti dengan `collect($models)->sum(fn ($c) => $c->count())`.

---

### T-4. Sandi default `guru123` tanpa paksa ganti (jalur sinkronisasi lama)
**Lokasi:** `app/Services/DapodikService.php:33` → `'kata_sandi' => \Illuminate\Support\Facades\Hash::make('guru123')` saat membuat user GTK baru; **tidak** ada `force_password_change`.
Sebagai pembanding, jalur lain sudah aman:
- `Api/DapodikSyncController.php:295-305` → menyertakan `'force_password_change' => true`
- `Services/Dapodik/DapodikSyncService.php:514-521` → menyertakan `'force_password_change' => true`

**Dampak:** akun guru hasil sync lewat `DapodikService` dapat login memakai sandi default yang diketahui publik (juga terdokumentasi di `LandingController.php:51`, seeder, dan `docs/`).
**Perbaikan:** selaraskan `DapodikService` dengan dua jalur lain (`force_password_change = true`), dan pertimbangkan sandi acak.

---

### T-5. Backup: yang di-wipe jauh lebih banyak daripada yang diekspor
**Lokasi:**
- `Admin/BackupController.php:194-204` → daftar tabel yang dikosongkan `wipeAll` (31 tabel data, antara lain `nilai_erapor`, `nilai_mapel`, `nilai_cp`, `capaian_pembelajaran`, `ptk`, `rombel`, `jadwal_pelajaran`, `mata_pelajaran`, `orang_tua_siswa`, `dapodik_sync_logs`, `dapodik_import_logs`, `dapodik_data_cache`, `tanggal_rapor`, `ekstrakurikuler`, `siswa_ekstrakurikuler`, `tema/kegiatan/kelompok/anggota kokurikuler`).
- `app/Jobs/ExportBackupJob.php` → hanya mengekspor **14 tabel**: users, siswa, materi, kuis, nilai, kehadiran, hasil_kuis, catatan, dimensi, kebiasaan, pengaturan_guru, ringkasan_guru, sekolah_settings, aktivitas.
- Import: `Admin/BackupController.php:72` (`$validTables`) dan `:92-107` (`$fillableMap`) hanya mengenali 14 tabel yang sama.
- `Admin/BackupController.php:130-174` (`restoreTable`) mewajibkan tiap tabel ada di `$fillableMap`, jika tidak → dilewati.

**Dampak:** setelah `wipeAll` lalu `import` dari backup, tabel-tabel di luar 14 daftar tsb **tidak akan pernah kembali** (nilai e-Rapor, transkrip, PTK, rombel, jadwal, mapel, ekstrakurikuler, kokurikuler, dll. hilang permanen). Fitur wipe dipresentasikan sebagai operasi yang bisa dipulihkan lewat backup — tidak benar.
**Perbaikan:** tambahkan tabel yang hilang ke export/import (minimal `nilai_erapor`, `transkrip_nilai`, `ptk`, `rombel`, `jadwal_pelajaran`, `mata_pelajaran`, `semesters`, `orang_tua_siswa`, `tanggal_rapor`, `ekstrakurikuler`, tabel kokurikuler), atau batasi daftar `wipeAll` agar hanya mengosongkan tabel yang benar-benar dicadangkan.

---

### T-6. Kredensial rahasia tersimpan di repositori
**Lokasi:**
- `dapodik-bridge/config.json` → memuat `api_key` dan `dapodik_token` (nilai sengaja **tidak ditampilkan** di sini; kredensial sudah dianggap bocor dan wajib dirotasi), serta `server_url`/`dapodik_url`.
- `setup_api_key.php` → API key yang sama tertulis sebagai literal dan langsung dimasukkan ke tabel `api_keys`.
- `.gitignore` **tidak** mengecualikan `dapodik-bridge/config.json`.

**Dampak:** siapa pun yang memegang repo dapat memanggil endpoint sinkronisasi Dapodik (`apikey:dapodik:import`) bila host dapat dijangkau, dan token WebService Dapodik ikut terekspos.
**Perbaikan:** rotasi kunci/token, hapus dari repo dan riwayat git, ganti dengan pembacaan `env` (mis. `SIMANTAP_API_KEY`, `DAPODIK_TOKEN`), jadikan config bridge template (`config.example.json`).

---

## 5. TEMUAN SEDANG

### S-1. Empat implementasi predikat yang tidak konsisten
| Implementasi | Ambang | Dipakai oleh |
|---|---|---|
| `App\Services\NilaiService::getPredikat($nilai, $kkm=70)` | A ≥ KKM+20%, B ≥ KKM+10%, C ≥ KKM, D < KKM | Dashboard siswa/guru/kepsek, laporan, `InputNilaiController` |
| `App\Models\NilaiErapot::generatePredikat(?int $kkm)` | A ≥ 90, B ≥ 80, C ≥ KKM, D < KKM | `Guru/NilaiErapotController`, `Guru/ImportErapotController` |
| `Kepsek/DashboardController::getPredikat()` (`:183`) | implementasi keempat | Dashboard kepsek |
| `Ortu/HasPredikatKaih` | skala KAIH (3,5 / 2,5 / 1,5) | Kebiasaan/ortu |

Contoh nyata: nilai akhir **85** dengan KKM 70 → tampil **A** di laporan (NilaiService) tetapi **B** di e-Rapor (`NilaiErapot`). Komentar `Guru/InputNilaiController.php:166` menyatakan NilaiService sebagai "satu sumber kebenaran predikat", namun dua controller e-Rapor tidak memakainya.
**Perbaikan:** jadikan satu fungsi predikat bersama (mis. `NilaiService::getPredikat`) dan delegasikan semua tampilan ke sana.

### S-2. `fullSync` menarik seluruh data sekolah
`Guru/IntegrasiController.php:118-168` (`fullSync`) memuat `\App\Models\Nilai::with('siswa')->get()` **tanpa filter `guru_id`**, begitu pula Kehadiran/Kebiasaan/Dimensi, lalu mengirimkannya ke spreadsheet. Satu akun guru dapat memicu ekspor data seluruh sekolah dan membebani server (tanpa pembatasan jumlah baris). Sebaiknya filter per guru (atau batasi route ini untuk admin).

### S-3. `setup_api_key.php` pasti gagal dijalankan
`setup_api_key.php:16-18` memakai `User::firstOrCreate(['email' => 'admin@simantap.local'], ...)`, padahal tabel `users` **tidak memiliki kolom `email`** (lihat `database/migrations/2026_09_09_075727_create_users_table.php`). Skrip akan error SQL. Selain itu skrip ini menaruh API key literal (lihat T-6).

### S-4. Pesan path backup menyesatkan
`app/Console/Commands/BackupData.php:32-35` menulis via `Storage::disk('local')->put("backups/{$filename}")`, dan disk `local` berakar di `storage/app/private` (`config/filesystems.php:35`) — tetapi pesan yang dicetak menyebut `storage/app/backups/...`. Perbaiki teks atau gunakan `Storage::disk('local')->path(...)`.

### S-5. Validasi `jenis` terlalu longgar
`Guru/InputNilaiController.php:69` hanya `'jenis' => 'required|string'`, membiarkan nilai apa pun masuk (dan menjadi akar bug K-5). Gunakan `in:Tugas,Ulangan Harian,Praktik,PTS,PAS`.

---

## 6. TEMUAN RENDAH

- **R-1.** `.env.example` menetapkan `APP_LOCALE=en`/`DB_CONNECTION=sqlite`, sementara aplikasi dijalankan dengan PostgreSQL dan `config/app.php:81-83` memakai locale `id`. Menyalin `.env.example` akan mengubah perilaku aplikasi.
- **R-2.** Artefak lama di root: `all_routes.txt` berisi pesan error CLI (`The "--columns" option does not exist`), `api.json` tampak hasil ekspor OpenAPI lama. Sebaiknya dihapus dari repo agar tidak menyesatkan.
- **R-3.** Middleware `cbt.security`/`cbt.session.lock` terdaftar dan kelasnya ada, tetapi tidak dipakai karena modul CBT tidak ada (lihat K-1).

---

## 7. CATATAN TERHADAP DOKUMEN LAMA

Dokumen di `docs/` dan `AUDIT_SIMANTAP.md` menyatakan banyak hal "sudah selesai". Berdasarkan kode saat ini:

- ✅ **Benar & sudah diperbaiki** (diverifikasi): XSS pada jawaban kuis (kini `nl2br(e(...))`), pembatas `role` pada route admin/guru, `force_password_change` pada dua jalur sync, whitelist import backup, validasi bobot, rata-rata (`avg('nilai')` bukan `avg('nilai_akhir')`), verifikasi kepemilikan pada bulk PDF dan endpoint nilai.
- ❌ **Belum benar**: klaim "semua isu selesai" bertentangan dengan temuan K-1…K-5 dan T-1…T-6 (mis. `Log::` tanpa import masih ada, resolver hilang, route CBT tanpa controller).
- ❌ **Tidak akurat**: `docs/03-REMAINING-ISSUES.md` menyebut tabel tanpa model sebagai satu-satunya sisa; kenyataannya ada bug fatal yang belum tersentuh.

Dokumen `MENU_AND_ROLES_DOCUMENTATION.md`, `DATABASE_MODELS_AND_RELATIONSHIPS.md`, dan `COMPLETE_APPLICATION_OVERVIEW.md` sebaiknya diperbarui setelah perbaikan agar konsisten.

---

## 8. REKOMENDASI URUTAN PERBAIKAN

1. **Hari-1 (blocker fitur & data):** T-2 (`use App\Models\Rombel`), T-3 (`$models->sum->count()`), T-1 (import `Log`), lalu putuskan nasib modul CBT (K-1) — hapus route atau lengkapi.
2. **Hari-1…3 (fitur inti):** K-4 (semantik `batas_waktu`), K-5 + S-5 (kosakata `jenis`), K-2/K-3 (`JadwalPelajaranResolver` + view alignment).
3. **Minggu-1 (keamanan & operasional):** T-6 (rotasi kunci + `env`), T-4 (paksa ganti sandi), T-5 (perbaiki cakupan backup **sebelum** ada yang memakai `wipeAll`).
4. **Minggu-1 (konsistensi):** S-1 (satu sumber predikat), S-2 (filter data full sync), S-3/S-4/R-1/R-2 (pembersihan).

**Verifikasi cepat yang disarankan setelah perbaikan:**
```bash
php artisan route:list          # pastikan tidak ada route yang menunjuk controller yang tidak ada
php artisan migrate:fresh --seed
php artisan test                # test suite yang ada (MenuAuditTest, AdminReferensiTest, CetakTest, dll.)
```
Tambahkan test regresi untuk: buka `/cbt` (harus 404/redirect, bukan 500), simpan nilai dari halaman Input Nilai (jenis "Ulangan Harian"), kumpulkan kuis bertimer, `pushRombel`, `fullSync` guru, dan restore backup setelah wipe.

---

## 9. LAMPIRAN — METODE & KETERBATASAN

- **Alat:** pembacaan berkas statis, pencocokan silang definisi/pemakaian (route ↔ controller ↔ view, model ↔ relasi, migrasi ↔ kolom, `use` ↔ referensi kelas), serta pemeriksaan sintaks Python (`python3 -m py_compile`) untuk `dapodik-bridge/`.
- **Keterbatasan:** tidak ada PHP/Composer/PostgreSQL di lingkungan audit, sehingga klaim "fatal"/"500" dinyatakan berdasarkan aturan resolusi kelas PHP dan skema DB, bukan hasil eksekusi. Semua lokasi bukti disertakan agar mudah diverifikasi pada lingkungan asli.
- **Yang sudah diperiksa dan dinyatakan sehat (ringkas):** pemetaan seluruh nama route aplikasi ke definisinya (tidak ada yang hilang), relasi `with()/load()` pada seluruh model (kecuali satu positif palsu di `Kepsek/PetaKelasController`), 14 komponen Blade `<x-...>` semuanya tersedia, semua kelas middleware/alias ada, tidak ada pemanggilan `exec/shell_exec`, tidak ada SQL mentah berbahaya, dan berkas Python bridge lolos kompilasi.
