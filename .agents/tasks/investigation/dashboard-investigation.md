# INVESTIGASI DASHBOARD SIMANTAP - SEMUA PERAN

**Status:** Investigasi Lengkap ✓  
**Tanggal:** 2025-01-13  
**Scope:** Dashboard untuk Admin, Guru, Siswa, Ortu, dan Kepsek  
**Fokus:** Status implementasi, fitur yang ada, dan gap yang perlu ditutup untuk alignment e-Rapor  

---

## DAFTAR PERAN & STATUS DASHBOARD

| Peran | Dashboard Ada | File Controller | File View | Status |
|-------|:-------------:|:---------------:|:---------:|--------|
| **Admin** | ✓ | `app/Http/Controllers/DashboardController.php::admin()` | `resources/views/admin/dashboard.blade.php` (422 baris) | **Lengkap & Kompleks** |
| **Guru** | ✓ | `app/Http/Controllers/Guru/DashboardController.php::index()` | `resources/views/guru/dashboard.blade.php` (366 baris) | **Lengkap & Aktual** |
| **Siswa** | ✓ | `app/Http/Controllers/Siswa/DashboardController.php::index()` | `resources/views/siswa/dashboard.blade.php` (253 baris) | **Lengkap** |
| **Ortu** | ⚠️ | ❌ **Tidak Ada** | `resources/views/ortu/dashboard.blade.php` (105 baris) | **Masalah: Controller Tidak Ada** |
| **Kepsek** | ✓ | `app/Http/Controllers/Kepsek/DashboardController.php::index()` | `resources/views/kepsek/dashboard.blade.php` (131 baris) | **Lengkap & Tersimpel** |

---

## A. ADMIN DASHBOARD

### Route & Akses
```
GET /admin/dashboard
Middleware: auth, role:admin, force.password.change
Controller: DashboardController@admin()
Route name: admin.dashboard
```

### Data yang Ditampilkan
1. **Statistik Utama (6 Kartu):**
   - Total Siswa (aktif/semua)
   - Guru & PTK (dari Dapodik)
   - Kelas/Rombel
   - Total Akun Pengguna
   - Login 15 menit terakhir
   - Jadwal Pembelajaran (dari Dapodik)

2. **Rincian Akun Per Peran (5 Kolom):**
   - Admin, Guru, Siswa, Ortu, Kepsek
   - Total + Aktif untuk masing-masing

3. **Status Kerja Administrator (15 Checklist):**
   - Progress bar agregat (persentase)
   - Contoh: "Ambil Data Dapodik", "Generate User", "Mapping Mapel Rapor"
   - Tabel dengan status done/belum

4. **Identitas Sekolah:**
   - Nama, NPSN, Alamat, Jenjang, Akreditasi
   - Semester aktif badge

5. **Sinkronisasi Dapodik:**
   - Siswa & Guru dari Dapodik
   - Log sinkronisasi terbaru (5 entry)

6. **Informasi Sistem:**
   - Health checks (environment, debug, antrian, DB, cache)
   - Ukuran backup
   - Tren pendaftaran 6 minggu

7. **Rekap Guru Aktif:**
   - Data terisi, Upload materi, Membuat kuis, Input nilai, E-Rapor

### Teknologi
- **Caching:** Cache::remember() 120 detik untuk ~30 query
- **Query Agregat:** groupBy + selectRaw untuk portable lintas DB (SQLite/MySQL/PostgreSQL)
- **Model Segar:** Eloquent di luar cache agar tidak korup saat unserialize

---

## B. GURU DASHBOARD

### Route & Akses
```
GET /guru/dashboard
Middleware: auth, role:guru, force.password.change
Controller: Guru\DashboardController@index()
Route name: guru.dashboard
```

### Data yang Ditampilkan
1. **Ringkasan Kelas (4 Kartu):**
   - Siswa aktif (jumlah)
   - Rata-rata kelas (nilai + KKM + tertinggi)
   - Ketuntasan (% + belum + belum dinilai)
   - Kehadiran rata-rata

2. **Jadwal Hari Ini:**
   - List jadwal mengajar hari ini
   - Jam, mata pelajaran, kelas
   - Diambil dari Dapodik

3. **Progres e-Rapor:**
   - Progress bar siswa sudah terisi
   - Deadline rapor (hari sisa)
   - Link "Isi Nilai"

4. **Google Sheets Integration:**
   - Status koneksi
   - Nama spreadsheet
   - Waktu sinkronisasi terakhir

5. **Chart Perkembangan Rata-rata Kelas:**
   - Tren nilai per jenis (Tugas, Ulangan, PTS, PAS)

6. **Siswa Perlu Pendampingan & Menonjol:**
   - List dengan nilai + predikat

7. **Konten Terbaru:**
   - Materi (5 terbaru)
   - Kuis aktif (5 terbaru)

8. **Agenda Guru:**
   - Status KKM diatur?
   - Jadwal hari ini
   - Deadline rapor
   - Progres e-Rapor
   - Esei menunggu koreksi (30 hari terakhir)

### Teknologi
- **Service NilaiService:** Menghitung nilai akhir & predikat
- **Batch Processing:** groupBy client-side untuk performance
- **DB Query Optimal:** 1 lookup KKM cached, selectRaw agregat

---

## C. SISWA DASHBOARD

### Route & Akses
```
GET /siswa/dashboard
Middleware: auth, role:siswa, force.password.change
Controller: Siswa\DashboardController@index()
Route name: siswa.dashboard
```

### Data yang Ditampilkan
1. **Kartu Profil (4 Kartu):**
   - Nama siswa
   - Rata-rata nilai
   - Kehadiran (%)
   - Predikat (A/B/C/D dengan warna)

2. **Perkembangan Nilai:**
   - Tabel per mata pelajaran
   - Rata-rata + predikat + status

3. **Riwayat Kehadiran:**
   - Per bulan (H/S/I/A)
   - Tahun berjalan

4. **Saran Personal:**
   - Auto-generate berdasarkan:
     - Rata-rata nilai vs KKM
     - Kehadiran (jika < 80% atau ≥ 95%)
     - Mata pelajaran terlemah (C/D)
     - Mata pelajaran terbaik (A)
   - Icon + warna konten

5. **Materi Terbaru:**
   - List 5 materi terbaru dari guru

6. **Kuis Aktif:**
   - List 5 kuis aktif

### Teknologi
- **HasSiswaLookup Trait:** Helper untuk lookup siswa dari auth
- **Portable Date Grouping:** Kelompok per bulan di PHP (lintas DB)
- **KKM Logic:** Predikat relatif KKM guru, bukan nilai mati

---

## D. ORTU (ORANG TUA) DASHBOARD

### ⚠️ MASALAH KRITIS DITEMUKAN

**Status Implementasi:** TIDAK LENGKAP

#### Temuan:
1. **Controller tidak ada:** Tidak ada `app/Http/Controllers/Ortu/DashboardController.php`
2. **View ada tapi orphan:** File `resources/views/ortu/dashboard.blade.php` (105 baris) ada tapi di-handle oleh DashboardController@ortu() (bukan di Ortu folder)
3. **Route menunjuk ke root:** 
   ```php
   Route::get('/dashboard', [DashboardController::class, 'ortu'])->name('ortu.dashboard');
   ```

#### Code Ditemukan
- **DashboardController@ortu()** di root `app/Http/Controllers/DashboardController.php`:
  ```php
  public function ortu(Request $request)
  {
      $user = auth()->user();
      $anakIds = $user->terhubung_dengan ?? [];
      // ... render view ortu.dashboard
  }
  ```

#### Fitur yang Ada:
1. **Dropdown Anak:** Jika multiple anak, pilih anak yang dipantau
2. **Data Anak:**
   - Nama, NIS, Kelas, No. HP
   - TTL, Gender, Alamat

3. **Ringkasan Pantauan:**
   - Nilai akhir + predikat
   - Kehadiran (%)
   - Status kebiasaan minggu ini (sudah/belum isi)
   - Catatan terbaru dari guru (3 entry)

4. **Akses Cepat:**
   - "Isi 7 Kebiasaan"
   - "Lihat Perkembangan Nilai"

#### Routes Ortu yang Ada:
```php
Route::get('/dashboard', [DashboardController::class, 'ortu'])->name('ortu.dashboard');
Route::get('/kebiasaan', [OrtuKebiasaanController::class, 'index'])->name('kebiasaan.index');
Route::post('/kebiasaan', [OrtuKebiasaanController::class, 'store'])->name('kebiasaan.store');
Route::get('/nilai', [DashboardController::class, 'ortuNilai'])->name('nilai.index');
Route::get('/rekap', [OrtuRekapController::class, 'index'])->name('rekap.index');
Route::get('/kehadiran', [OrtuKehadiranController::class, 'index'])->name('kehadiran.index');
Route::get('/catatan', [OrtuCatatanController::class, 'index'])->name('catatan.index');
Route::get('/laporan', [OrtuLaporanController::class, 'index'])->name('laporan.index');
```

---

## E. KEPSEK (KEPALA SEKOLAH) DASHBOARD

### Route & Akses
```
GET /kepsek/dashboard
Middleware: auth, role:kepsek, force.password.change
Controller: Kepsek\DashboardController@index()
Route name: kepsek.dashboard
```

### Data yang Ditampilkan
1. **Statistik Utama (4 Kartu):**
   - Total siswa aktif
   - Total guru aktif
   - Rata-rata nilai sekolah
   - Persentase kehadiran rata-rata

2. **Chart Aktivitas Guru 7 Hari:**
   - Line chart: Nilai Input, Materi, Kuis
   - Per hari

3. **Chart Distribusi Predikat:**
   - Doughnut chart: A/B/C/D
   - Skala keseluruhan

4. **Partisipasi Orang Tua:**
   - Persentase (%) + akun + siswa dilaporkan
   - Progress bar color-coded

5. **Top 5 Guru Aktif:**
   - Tabel: Guru, Total Nilai, Total Materi, Total Kuis, Siswa

6. **Ringkasan Per Kelas:**
   - Tabel: Kelas, Siswa, Rata Nilai, Kehadiran, Partisipasi Ortu

7. **Action Buttons:**
   - Rekap Lengkap
   - Hasil Belajar
   - Pantau Aktivitas
   - Peta Kelas

### Secondary Features
- **Pantau Activity:** `GET /kepsek/pantau` — filter by guru & tanggal, list aktivitas

### Teknologi
- **Chart.js:** Visualisasi data
- **Portable Date Ranged Query:** 6 hari terakhir di PHP, bukan DB

---

## SISTEM ROLE & MIDDLEWARE

### Role di Database
Kolom `users.peran` dengan nilai:
- `admin` — Administrator
- `guru` — Guru/Pendidik
- `siswa` — Peserta Didik
- `ortu` — Orang Tua/Wali
- `kepsek` — Kepala Sekolah

### Middleware
- **Route Middleware:** `role:admin|guru|siswa|ortu|kepsek` (custom RoleMiddleware)
- **File:** `app/Http/Middleware/RoleMiddleware.php`
- **Logic:** Check `auth()->user()->peran` terhadap list allowed roles

### Redirect Dashboard
```php
Route::get('/dashboard', function () {
    if (!auth()->check()) return redirect()->route('login');
    $user = auth()->user();
    return match($user->peran) {
        'admin' => redirect()->route('admin.dashboard'),
        'guru' => redirect()->route('guru.dashboard'),
        'siswa' => redirect()->route('siswa.dashboard'),
        'ortu' => redirect()->route('ortu.dashboard'),
        'kepsek' => redirect()->route('kepsek.dashboard'),
        default => redirect()->route('login'),
    };
})->name('home');
```

---

## SISTEM DATA & MODEL

### User Model
**File:** `app/Models/User.php`
- Kolom utama: `uuid`, `nama_lengkap`, `nama_pengguna`, `kata_sandi` (hashed), `peran`, `terhubung_dengan` (array), `aktif`, `terakhir_masuk`, `force_password_change`, `dapodik_id`
- Relasi: siswa(), materi(), kuis(), pengaturanGuru(), ringkasanGuru()
- Custom auth: `getAuthPassword()` menggunakan `kata_sandi`

### Data Relasi User
- **Guru ↔ Siswa:** Via `Siswa.guru_id` (1-many)
- **Guru ↔ Kuis/Materi/Nilai:** Via `guru_id`
- **Ortu ↔ Siswa:** Via `User.terhubung_dengan` (array JSON)
- **Siswa ↔ Nilai/Kehadiran/Kebiasaan:** Via `siswa_id`

### Model Penilaian
- `Nilai` — nilai per mata pelajaran
- `NilaiErapot` — nilai rapor terintegrasi (dengan `jadwal_pelajaran_id` FK untuk alignment e-Rapor)
- `Kehadiran` — attendance record
- `HasilKuis` — hasil kerjakan kuis
- `Kebiasaan` — 7 kebiasaan dari ortu

---

## FITUR UMUM YANG SAMA DI SEMUA DASHBOARD

1. **Status Check:**
   - Informasi apa yang sudah/belum diatur
   - Progress bar visualisasi

2. **Real-time Stats:**
   - Jumlah siswa/guru/kelas
   - Rata-rata nilai & kehadiran

3. **Latest Activity:**
   - Data terbaru (materi, kuis, catatan)

4. **Action Links:**
   - Button ke halaman terkait (nilai, materi, kuis, catatan)

5. **Color-coded Status:**
   - Green (ok): ✓ tuntas/baik
   - Yellow (gold): ⚠ cukup/warning
   - Red (bad): ✗ belum/perlu bimbingan

---

## GAP & MASALAH YANG DIIDENTIFIKASI

### 1. **ORTU DASHBOARD - STRUCTURE MISMATCH**
- **Masalah:** DashboardController di root, bukan di `App\Http\Controllers\Ortu\`
- **Dampak:** Tidak konsisten dengan struktur role lain
- **Rekomendasi:** Pindahkan ortu() ke `Ortu\DashboardController` atau buat class dedicated

### 2. **ORTU TIDAK ADA CONTROLLER CLASS DEDICATED**
- **Masalah:** Folder `app/Http/Controllers/Ortu/` tidak ada `DashboardController.php`
- **Kontras:** Guru, Siswa, Kepsek semuanya punya dedicated controller
- **Impact:** Sulit untuk maintenance & testing

### 3. **FITUR DASHBOARD ORTU MINIMAL**
- **Masalah:** Hanya menampilkan ringkasan — tidak ada chart/analisis detail
- **Kontras:** Admin, Guru, Kepsek punya chart dan analytics
- **Rekomendasi:** Tambah chart nilai perkembangan, trend kehadiran, visualisasi kebiasaan

### 4. **DASHBOARD KEPSEK TERLALU SIMPLE**
- **Masalah:** Hanya 131 baris view, fitur terbatas
- **Yang Ada:** Basic stats + 2 chart + 2 tabel
- **Yang Kurang:** Drill-down detail, filter temporal, export
- **Rekomendasi:** Tambah filter semester, tren tahunan, export PDF

### 5. **ALIGNMENT E-RAPOR BELUM FULLY INTEGRATED**
- **Masalah:** Dashboards belum menampilkan info alignment status
- **Konteks:** Docs (09-12) menyebut alignment jadwal_pelajaran ↔ nilai_erapor
- **Rekomendasi:** Admin dashboard perlu badge/section untuk alignment health check

### 6. **ADMIN DASHBOARD TIDAK ADA ACTION UNTUK SETUP CEPAT**
- **Masalah:** Status checklist ada tapi tidak linkable ke form setup
- **Rekomendasi:** Tambah link ke setiap checklist item

### 7. **GURU DASHBOARD - KUIS ESEI MENUNGGU KOREKSI**
- **Masalah:** Dihitung tapi belum ada UI untuk koreksi cepat
- **Rekomendasi:** Tambah shortcut ke page koreksi kuis

### 8. **SISWA - SARAN PERSONAL LOGIC**
- **Masalah:** Logic saran ada di controller, view agak kompleks
- **Rekomendasi:** Refactor ke Service untuk testability

### 9. **DATABASE MIGRATION ALIGNMENT NOT COMPLETED**
- **Status:** Docs (09-12) show alignment plan, but belum clear di dashboard
- **Masalah:** `jadwal_pelajaran_id` FK mungkin sudah ada, tapi dashboard tidak menampilkan status
- **Rekomendasi:** Tambah indicator alignment health di admin dashboard

### 10. **CROSS-PERAN VISIBILITY**
- **Masalah:** Tidak ada dashboard "overview" yang menunjukkan data across all peran
- **Kecuali:** Admin dashboard (tapi focused pada system health, bukan pedagogi)
- **Rekomendasi:** Dashboard khusus untuk Kepsek lebih mendalam untuk oversight

---

## PERBANDINGAN FITUR DASHBOARD ANTAR PERAN

| Fitur | Admin | Guru | Siswa | Ortu | Kepsek |
|-------|:-----:|:----:|:-----:|:----:|:------:|
| **Statistik Utama** | ✓ Lengkap | ✓ Kelas | ✓ Personal | ✓ Anak | ✓ Sekolah |
| **Chart/Visualisasi** | ✓ Trend | ✓ Perkembangan | ✗ | ✗ | ✓ 2 Chart |
| **Progress Bar** | ✓ Setup | ✓ E-Rapor | ✗ | ✗ | ✓ Partisipasi |
| **Tabel Ranking/Top** | ✗ | ✓ Siswa | ✗ | ✗ | ✓ Guru |
| **Saran/Tips** | ✗ | ✗ | ✓ Personal | ✗ | ✗ |
| **Recent Activity** | ✓ Log | ✓ Materi/Kuis | ✓ Materi/Kuis | ✓ Catatan | ✓ Aktivitas |
| **Maintenance Menu** | ✓ Banyak | ✗ | ✗ | ✗ | ✓ Links |
| **Multiple Records** | ✗ | ✗ | ✗ | ✓ Anak | ✓ Guru/Kelas |
| **Time-based Filter** | ✗ | ✗ | ✗ | ✗ | ✓ 7-hari |
| **Responsive Grid** | ✓ 4-6 kolom | ✓ 4 kolom | ✓ 4 kolom | ✗ Hardcoded | ✓ 4 kolom |

---

## TEKNOLOGI & PATTERN YANG DIGUNAKAN

### Caching
- `Cache::remember()` untuk mengurangi query (Admin: 120 detik)
- Hindari cache Model (risiko unserialize korup)

### Query Optimization
- `selectRaw()` dengan `groupBy` untuk agregat portable
- `withCount()` untuk optimisasi
- Batch processing di PHP daripada per-loop DB query

### Service Classes
- `NilaiService` — kalkulasi nilai, predikat
- Mungkin: `JadwalPelajaranResolver` (docs alignment)

### Traits
- `HasSiswaLookup` — helper Siswa controller

### Blade Components
- `.card`, `.stat-card`, `.tag` — styling component
- `grid-2`, `grid-3`, `grid-4` — responsive grid
- `.note`, `.note-warn`, `.note-gold` — alert styling

---

## KONVERSI DARI DOCS ALIGNMENT (09-12)

### Konteks e-Rapor Alignment
- **Tujuan:** Link jadwal_pelajaran ↔ nilai_erapor (via FK)
- **Migration:** `add_jadwal_pelajaran_id_to_nilai_erapor_table`
- **Controller:** `JadwalPelajaranResolver` service
- **Admin Feature:** Halaman `alignment-check` untuk monitor status matching

### Implikasi ke Dashboard
1. Admin dashboard perlu **alignment health badge**
2. Guru dashboard bisa menampilkan **jadwal ↔ nilai matching status**
3. Kepsek dashboard bisa group nilai by jadwal (bukan hanya by guru)

---

## REKOMENDASI PERBAIKAN

### Priority 1 (Critical)
1. **Refactor Ortu Dashboard Controller** — pindah ke `Ortu\DashboardController`
2. **Add Alignment Status Indicator** — di Admin dashboard, badge "Alignment Health"

### Priority 2 (Important)
3. **Enhance Ortu Dashboard** — tambah chart trend nilai, kehadiran, kebiasaan timeline
4. **Enhance Kepsek Dashboard** — tambah filter semester/period, drill-down per guru/kelas
5. **Guru Dashboard Alert** — shortcut ke kuis yang perlu koreksi

### Priority 3 (Nice-to-Have)
6. **Export Functionality** — Admin, Guru, Kepsek bisa export PDF/Excel
7. **Comparison Charts** — Guru: bandingkan kelas sebelumnya vs sekarang
8. **Siswa Dashboard Mobile Optimization** — enhance saran UI untuk mobile

### Priority 4 (Future)
9. **Real-time Notifications** — socket untuk update status real-time
10. **Dashboard Customization** — user bisa drag-drop card dashboard

---

## VERIFICATION CHECKLIST

- [x] Baca semua controller dashboard (Admin, Guru, Siswa, Ortu, Kepsek)
- [x] Baca semua view dashboard (5 file)
- [x] Identifikasi route mapping & middleware
- [x] Baca User model & sistem role
- [x] Baca RoleMiddleware
- [x] Identifikasi data model (Nilai, Kehadiran, Kebiasaan, etc.)
- [x] Cross-check dengan routes/web.php
- [x] Baca AGENTS.md untuk konteks kolaborasi
- [x] Baca docs alignment (09-12) untuk konteks e-Rapor
- [x] Identifikasi gap & masalah
- [x] Dokumentasi pattern & teknologi

---

## KESIMPULAN

**Status:** Dashboard untuk semua peran sudah implementasi, tapi dengan kualitas berbeda:

- ✓ **Admin, Guru, Kepsek:** Lengkap & terstruktur
- ⚠️ **Siswa:** Lengkap tapi sederhana (tapi sesuai kebutuhan)
- ❌ **Ortu:** Incomplete structure — controller di root, perlu refactor

**Priority Next:** Refactor Ortu, tambah alignment indicator di Admin, enhance Ortu & Kepsek untuk visualisasi lebih dalam.

