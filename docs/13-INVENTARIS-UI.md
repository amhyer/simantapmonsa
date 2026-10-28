# Inventaris Antarmuka SIMANTAP — Halaman, Menu, Tombol & Aksi

> Dokumen ini **dihasilkan dari pembacaan kode** (`routes/web.php`, 230 template Blade,
> controller, partial sidebar, dan komponen UI) pada branch `arena/f505ac82-simantapmonsa`.
> Angka di bawah dihitung otomatis, jadi bisa dipakai sebagai *baseline* untuk
> audit/regresi UI berikutnya.

---

## 1. Ringkasan angka

| Metrik | Jumlah |
|---|---:|
| Route terdaftar (CBT nonaktif — kondisi default) | **360** |
|  · Halaman (GET) | **199** |
|  · Endpoint aksi (POST/PUT/PATCH/DELETE) | **161** |
| Rute tambahan bila CBT diaktifkan | **+28** (total 388) |
| Template Blade | **229** |
| Elemen `<button>` | **308** |
| Elemen `<form>` | **167** |
| Tautan `<a>` | **320** |
| `<select>` (filter/pilihan) | **127** |
| Halaman dengan pemanggilan `fetch()` | **17** |

_Angka diperbarui 28 Oktober 2026 setelah perbaikan temuan T1–T6, T14, dan T15 (kolom Status di §6)._
_Metode penghitungan: kemunculan `<button`, `<form`, `<select`, dan `<a` pada seluruh berkas `.blade.php` — lihat §8._

Sebaran per area (dihitung dari awalan URI):

| Area | GET | Aksi | Jumlah |
|---|---:|---:|---:|
| Admin (e-Rapor & sistem) (`/admin`) | 64 | 77 | **141** |
| Guru (`/guru`) | 42 | 35 | **77** |
| Admin › Tryout (`/admin/tryout`) | 13 | 9 | **22** |
| Tryout (guru & siswa) (`/tryout`) | 12 | 9 | **21** |
| TKA (guru & ortu) (`/tka`) | 12 | 8 | **20** |
| Admin › CBT (`/admin/cbt`) | 11 | 7 | **18** |
| Admin › TKA (`/admin/tka`) | 9 | 6 | **15** |
| Publik / autentikasi (`/publik`) | 9 | 5 | **14** |
| Siswa (`/siswa`) | 9 | 2 | **11** |
| Kepala sekolah (`/kepsek`) | 10 | 0 | **10** |
| Orang tua (`/ortu`) | 7 | 1 | **8** |
| Presensi pegawai (`/presensi`) | 1 | 2 | **3** |
## 2. Arsitektur antarmuka

### 2.1 Dua kerangka (layout) utama

| Layout | Dipakai oleh | Ciri |
|---|---|---|
| `layouts/admin-with-tabs.blade.php` | **81 view admin** | Sidebar dipilih otomatis sesuai **tab aktif**; ada **bilah tab** di bawah topbar |
| `layouts/app.blade.php` | **91 view** (guru, siswa, ortu, kepsek, tendik, tryout, tka) | Sidebar diisi lewat `@section('sidebar')` oleh masing-masing halaman |
| `layouts/guest.blade.php` | — (**tidak dipakai**; komponen `GuestLayout` tidak pernah dipanggil) | Kartu tengah, tanpa sidebar |
| `auth/*.blade.php` | 5 halaman auth | Berdiri sendiri: masing-masing memuat `<!DOCTYPE html>` + `@vite` sendiri |
| `landing.blade.php` + `landing/tka-statistik.blade.php` | Publik | Halaman pemasaran, navigasi sendiri |
| `admin/cetak/nilai-show`, `admin/transkrip/cetak-show`, `admin/exports/table`, `guru/erapor/pdf*`, `guru/laporan/pdf` | Cetak/unduh | Tanpa layout aplikasi (siap cetak / PDF) |

`resources/views/layouts/navigation.blade.php` (navbar ala Breeze) **tidak dipakai** layout mana pun — sisa *scaffolding* awal.

### 2.2 Anatomi satu halaman

```
┌─ sidebar (#sidebar) ─────────────┬─ topbar ────────────────────────────────┐
│  brand SIMANTAP                  │ ☰ | judul halaman + subjudul            │
│  kartu pengguna (foto/avatar)    │                    🔔 notif │ ▼ akun    │
│  ── menu (label / <details>) ──  ├─ [bilah tab admin] ─────────────────────┤
│  [Keluar]                        │ page-content                            │
│                                  │  · note sukses / error / validasi       │
│                                  │  · @yield('content')                    │
└──────────────────────────────────┴─────────────────────────────────────────┘
```

* **Topbar** — tombol ☰ (`toggleSidebar()`), panel notifikasi (`$layoutNotifs`), dropdown akun
  (Beranda · *Profil Saya* khusus siswa · Keluar), plus `@yield('header_actions')`.
* **Pesan** — tiga blok bawaan: `session('success')` → `note-ok`, `session('error')` → `note-warn`,
  `$errors->any()` → `note-bad` berisi daftar isian yang salah.
* **Toast** — `showToast(pesan, tipe)` menambah elemen ke `#toastContainer`, hilang otomatis 3 detik.

### 2.3 Komponen menu

| Komponen | Peran |
|---|---|
| `x-sidebar-label` | Judul grup (`nav-label`) |
| `x-sidebar-link` | Item menu biasa; mendukung `badge` + `badge-type` (dipakai untuk status sinkron Dapodik) |
| `x-sidebar-submenu` | Grup lipat berbasis `<details>`; otomatis terbuka bila salah satu anaknya aktif |
| `x-sidebar-planned` | Item **nonaktif** berlabel *Segera* untuk fitur yang backend-nya belum ada |
| `components/admin-tabs` | Bilah 4 tab admin |

Ikon sidebar memakai **Lucide** (`mallardduck/blade-lucide-icons`), sedangkan isi halaman memakai
**Font Awesome** (`<i class="fas fa-…">`) — dua set ikon berdampingan.

### 2.4 Tema & peran

`layout.css` mendefinisikan token warna (`--navy` #1F3864, `--gold` #B8860B, `--violet` #7C3AED,
`--ok/--warn/--bad`). Untuk **siswa**, `layouts/app.blade.php` menimpa `--navy` dengan `--violet`,
sehingga seluruh aksen berubah ungu. Enam peran (`admin, guru, siswa, ortu, kepsek, tendik`)
diarahkan oleh closure `/dashboard` (`route('home')`) ke dasbor masing-masing; `tendik` langsung
dikirim ke `presensi.pegawai.index`.

### 2.5 Berkas JavaScript

| Berkas | Dipakai di |
|---|---|
| `resources/js/app.js` | Semua halaman (Alpine, AutoAnimate, reveal-on-scroll, count-up, progress bar, back-to-top, live animate tabel) |
| `resources/js/charts.js` | 10+ halaman grafik (di-`@vite` terpisah): guru/analisis, guru/dashboard, guru/kebiasaan, guru/laporan, guru/tka, kepsek/* |
| `resources/js/presensi-pegawai.js` | `pegawai/presensi/index` (geofencing) |
| `resources/js/presensi-qr.js` | `admin/presensi/kartu`, `guru/kehadiran/jadwal` (pemindai QR) |
| `resources/js/halaman-publik.js` | Landing & statistik TKA |

---

## 3. Struktur menu per peran

Penanda: `▸` = grup lipat (`<details>`), `·` = item langsung, 🅰 = hanya muncul bila
`config('simantap.cbt_enabled')` aktif.

### 3.1 Admin — bilah tab + sidebar per tab

Bilah tab (`components/admin-tabs.blade.php`) — penentuan tab aktif oleh `activeAdminTab()`
di `app/helpers.php`:

| Tab | Rute tujuan | Aktif bila rute | Sidebar yang dirender |
|---|---|---|---|
| **E-Rapor** | `admin.dashboard` | `admin.dashboard`, `admin.penilaian.*`, `admin.perkembangan.*`, `admin.transkrip.*`, `admin.cetak.*`, `admin.referensi.*`, `admin.profile`, `admin.sekolah.*` | `admin.partials.sidebar-erapor` |
| **CBT/Ujian** | `admin.cbt.sessions.index` | `admin.cbt.*`, `admin.tryout.*` | `admin.partials.sidebar-cbt` |
| **TKA** | `admin.tka.dashboard` | `admin.tka.*` | `admin.partials.sidebar-tka` |
| **Setup** | `admin.users.index` | selainnya (`admin.users.*`, `admin.presensi.*`, `admin.dapodik.*`, `admin.mapel.*`, `admin.semester.*`, `admin.kokurikuler.*`, `admin.kode-akses.*`, `admin.modul.*`, `admin.bobot.*`, `admin.sheet.*`, `admin.backup.*`, `admin.log.*`) | `admin.partials.sidebar-setup` |

> **Catatan (28 Okt 2026).** Tab **CBT/Ujian** dan **TKA** hanya dirender bila
> `modulAsesmenAktif()` bernilai `true` (isi: `config('simantap.cbt_enabled')`). Saat flag mati —
> kondisi bawaan — kedua tab tidak ditampilkan dan `sidebar-cbt` / `sidebar-tka` menghasilkan
> kosong, karena seluruh rute di baliknya dijawab 404 oleh middleware `module.asesmen`.
> Sebelum perbaikan ini keempat tab selalu tampil (lihat T1 di §6.1).

**Tab E-Rapor**

```
UTAMA
 · Dasbor Sistem            admin.dashboard
 · Profile                  admin.profile
STATUS PENILAIAN ▸
 · Status per Guru          admin.penilaian.status
 · Statistik Nilai Rapor    admin.penilaian.statistik
PERKEMBANGAN NILAI ▸
 · Perkembangan Nilai       admin.perkembangan.index
 · Grafik Nilai Rapor       admin.perkembangan.grafik
TRANSKRIP IJAZAH ▸
 · Setting Transkrip        admin.transkrip.setting
 · Nomor Ijazah             admin.transkrip.nomor
 · Mapping Mapel            admin.referensi.mapping-rapor
 · Input Nilai Transkrip    admin.transkrip.input
 · Import Nilai Transkrip   admin.transkrip.import
 · Cetak Transkrip Nilai    admin.transkrip.cetak
CETAK NILAI ▸
 · Leger Rapor              admin.cetak.leger
 · Pelengkap Rapor          admin.cetak.pelengkap
 · Nilai Rapor              admin.cetak.nilai
DATA REFERENSI ▸
 · Data Sekolah             admin.sekolah.index
 · Data Guru                admin.referensi.guru
 · Data Siswa               admin.users.siswa
 · Data Kelas               admin.peta-kelas.daftar
 · Data Mapel               admin.mapel.index
 · Data Pembelajaran        admin.referensi.pembelajaran
 · Cek Alignment            admin.referensi.alignment
 · Data Semester            admin.semester.index
 · Data Ekstrakurikuler     admin.referensi.ekstrakurikuler
 · Data Kelompok Mapel      admin.referensi.kelompok-mapel
 · Mapping Rapor            admin.referensi.mapping-rapor   ← tampil dua kali
 · Logo dan TTD             admin.referensi.logo-ttd
 · Tanggal Rapor            admin.referensi.tanggal-rapor
 · Foto Siswa               admin.referensi.foto-siswa
```

**Tab CBT/Ujian**

```
CBT & E-LEARNING ▸
 · Sesi Ujian CBT           admin.cbt.sessions.index
 · Bank Soal CBT            admin.cbt.banks.index
 · Dashboard Tryout         admin.tryout.statistics
 · Paket Tryout             admin.tryout.packages.index
 · Sesi Tryout              admin.tryout.sessions.index
 · Hasil Tryout             admin.tryout.results.index
```

**Tab TKA**

```
TKA (PROFIL PELAJAR) ▸
 · Dashboard TKA            admin.tka.dashboard
 · Dimensi P5               admin.tka.dimensions.index
 · Data Penilaian           admin.tka.assessments.index
 · Early Warning            admin.tka.warnings.index
```

**Tab Setup**

```
PENGGUNA
 · Data Pengguna            admin.users.index
PRESENSI ▸
 · Presensi Pegawai & Lokasi      admin.presensi.index
 · Pemetaan QR Kartu Siswa        admin.presensi.kartu.index
DAPODIK ▸
 · Ambil Data Dapodik       admin.dapodik.index        (badge: waktu sinkron terakhir / "Belum sync")
 · Kirim Nilai Ke Dapodik   admin.dapodik.push.index
 · API Keys & Bridge        admin.api-keys.index
DATA REFERENSI
 · Data Sekolah             admin.sekolah.index
PETA KELAS
 · Daftar Peta Kelas        admin.peta-kelas.daftar
DATA MAPEL & PEMBELAJARAN ▸
 · Data Mapel               admin.mapel.index
 · Data Pembelajaran        admin.referensi.pembelajaran
DATA SEMESTER
 · Data Semester            admin.semester.index
DATA KOKURIKULER ▸
 · Daftar Tema              admin.kokurikuler.tema
 · Kegiatan Kokurikuler     admin.kokurikuler.kegiatan
 · Kelompok Kokurikuler     admin.kokurikuler.kelompok
KODE & AKSES
 · Kode & Akses             admin.kode-akses.index
MODUL & TAMPILAN
 · Modul & Tampilan         admin.modul.index
BOBOT & KETUNTASAN
 · Bobot & Ketuntasan       admin.bobot.index
SYSTEM ▸
 · Penyimpanan Data         admin.sheet.index
 · Backup & Restore         admin.backup.index
 · Log Aktivitas            admin.log.index
```

> **Catatan (diperbarui 28 Okt 2026):** halaman **API Keys** (`admin.api-keys.index`) kini
> masuk menu **DAPODIK** pada tab Setup (sebelumnya tidak ada di sidebar mana pun — temuan T14).

### 3.2 Guru

```
MENU UTAMA
 · Dasbor                   guru.dashboard
PEMBELAJARAN
 · Materi Ajar              guru.materi.index
 · Profil Lulusan           guru.dimensi.index
 · Kesiapan TKA             guru.tka.index
ASESMEN
 ▸ Asesmen & Nilai
    · Input Nilai Cepat     guru.input-nilai.index
    · Input Nilai e-Rapor   guru.nilai-erapor.index
    · Kuis & Soal           guru.kuis.index
    · Daftar Nilai          guru.nilai.index
    · Analisis Belajar      guru.analisis.index
CBT & TRYOUT
 ▸ Tryout
    · Paket Tryout          tryout.packages.index
    · Sesi Tryout           tryout.sessions.index
 ▸ Asesmen TKA
    · Dimensi TKA           tka.dimensions.index
    · Penilaian TKA         tka.assessments.index
PEMANTAUAN
 · Presensi Saya            presensi.pegawai.index
 · Kehadiran Siswa          guru.kehadiran.index
 · Catatan Siswa            guru.catatan.index
 · 7 Kebiasaan              guru.kebiasaan.index
PELAPORAN
 · Laporan & Rapor          guru.laporan.index
 · Generate e-Rapor         guru.erapor.index
 · Data Siswa               guru.siswa.index
PENGATURAN
 · Google Sheet             guru.integrasi.index
 · Pengaturan               guru.pengaturan.index
UJIAN CBT 🅰
 · CBT • Sesi Ujian         cbt.sessions.index
 · CBT • Bank Soal          cbt.question-banks.index
```

> Grup **CBT & TRYOUT** (Tryout + Asesmen TKA) kini hanya tampil bila modul asesmen aktif;
> seluruh rute di dalamnya berada di balik `module.asesmen`. **Kesiapan TKA**
> (`guru.tka.index`) tetap di grup PEMBELAJARAN karena rutenya tidak memakai middleware itu.

### 3.3 Siswa

```
MENU SISWA
 · Beranda                  siswa.dashboard
 · Materi                   siswa.materi.index
 · Kuis                     siswa.kuis.index
 · Nilaiku                  siswa.nilai.index
 · Profil Saya              siswa.profil.show
UJIAN & TRYOUT
 · Tryout                   tryout.student.index
UJIAN CBT 🅰
 · Ujian CBT                cbt.student.index
```

> Grup **UJIAN & TRYOUT** kini hanya tampil bila modul asesmen aktif (`module.asesmen`
> menjawab 404 untuk `tryout.student.*` saat flag mati).

### 3.4 Orang tua

```
MENU ORANG TUA
 · Ringkasan                ortu.dashboard
KEMITRAAN
 · Isi 7 Kebiasaan          ortu.kebiasaan.index
 · Rekap Kebiasaan          ortu.rekap.index
PEMANTAUAN
 ▸ Pemantauan Anak (terbuka default)
    · Perkembangan Nilai    ortu.nilai.index
    · Kehadiran Anak        ortu.kehadiran.index
    · Catatan Guru          ortu.catatan.index
    · Laporan / Rapor       ortu.laporan.index
    · Laporan TKA           tka.parent.index
```

> **Laporan TKA** (`tka.parent.index`) kini disembunyikan bila modul asesmen nonaktif.

### 3.5 Kepala sekolah

```
MENU KEPSEK
 · Dashboard                kepsek.dashboard
 · Rekap Sekolah            kepsek.rekap.index
 · Presensi Saya            presensi.pegawai.index
PEMETAAN
 · Peta Kelas               kepsek.peta-kelas.index
 · Hasil Belajar            kepsek.hasil-belajar.index
PEMANTAUAN
 · Pantau Aktivitas         kepsek.pantau.index
 · Rekap Kebiasaan          kepsek.kebiasaan.index
 · Log Aktivitas            kepsek.aktivitas.index
```

### 3.6 Tenaga kependidikan (tendik)

```
· Presensi Saya             presensi.pegawai.index
```

Tendik hanya memiliki satu item menu; `/dashboard` langsung mengarah ke halaman presensi.

---

## 4. Inventaris halaman & aksi

199 halaman (rute GET) beserta seluruh tombol, form, tautan aksi, dan panggilan AJAX yang ada di
masing-masing view. Konvensi pada tabel aksi:

| Kolom | Arti |
|---|---|
| **Aksi** | Teks tombol / tautan apa adanya di UI |
| **Metode** | `POST/PUT/DELETE/GET` = form yang dikirim · `JS` = tombol yang menjalankan fungsi JavaScript · `→` = tautan navigasi · `fetch` = permintaan asinkron |
| **Endpoint / fungsi** | Rute tujuan yang berhasil dipetakan; `⚠` menandai nama rute yang tidak terdaftar pada konfigurasi default |
| **Catatan** | Kolom form, ada/tidaknya konfirmasi, atau keterangan lain |

Halaman yang tidak menghasilkan HTML (unduhan PDF/CSV/XLSX, respons JSON, pengalihan) ditandai
tersendiri dan tidak memiliki tabel aksi.

### Publik & autentikasi — 9 halaman

#### Landing
`GET /` · `landing` · `LandingController@index` · `landing.blade.php` (1444 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Reset | `JS` | `tkaResetFilter()` | tombol client-side |
| Calon Peserta | `JS` | `tkaTabClick()` | tombol client-side |
| Pendaftar | `JS` | `tkaTabClick()` | tombol client-side |
| (ikon) | `JS` | `sharePage()` | tombol client-side |
| Mengerti | `JS` | `acknowledgeCookieNotice()` | tombol client-side |
| Mulai Quiz | `JS` | `startQuiz()` | tombol client-side |
| '+letters[idx]+' '+opt+' | `JS` | `selectQuizOption()` | tombol client-side |
| Sebelumnya | `JS` | `prevQuiz()` | tombol client-side |
| Lihat Hasil | `JS` | `finishQuiz()` | tombol client-side |
| Selanjutnya | `JS` | `nextQuiz()` | tombol client-side |
| Ulangi | `JS` | `resetQuiz()` | tombol client-side |
| SM SIMAN TAP | `→` | `landing` | navigasi (GET /) |
| Login | `→` | `login` | navigasi (GET /login) |
| Masuk ke Aplikasi | `→` | `login` | navigasi (GET /login) |
| Masuk ke aplikasi | `→` | `login` | navigasi (GET /login) |
| Lihat halaman lengkap → | `→` | `tka.statistik` | navigasi (GET /tka-statistik) |
| Lihat Statistik Lengkap | `→` | `tka.statistik` | navigasi (GET /tka-statistik) |
| Guru · Login | `→` | `login` | navigasi (GET /login) |
| Siswa · Login | `→` | `login` | navigasi (GET /login) |
| Orang Tua · Login | `→` | `login` | navigasi (GET /login) |
| Kepala Sekolah · Login | `→` | `login` | navigasi (GET /login) |

#### ⚙︎ /api/tka-statistik
`GET /api/tka-statistik` · `tka.api` · `TkaController@api`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /dashboard
`GET /dashboard` · `home`

_Bukan halaman: closure `home` — mengarahkan tiap peran ke dasbornya._

#### ⚙︎ /demo
`GET /demo` · `demo` · `LandingController@demo`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Force Change Password
`GET /force-password-change` · `force-password-change.show` · `Auth\ForcePasswordChangeController@show` · `auth/force-change-password.blade.php` (69 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan & Lanjutkan | `PUT` | `PUT /force-password-change` | kolom: password, password_confirmation |

#### Login
`GET /login` · `login` · `Auth\LoginController@showLoginForm` · `auth/login.blade.php` (70 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Masuk | `POST` | `POST /login` | kolom: nama_pengguna, kata_sandi |
| Lupa kata sandi? | `→` | `password.request` | navigasi (GET /lupa-sandi) |

#### Forgot Password
`GET /lupa-sandi` · `password.request` · `Auth\ForgotPasswordController@showLinkRequestForm` · `auth/forgot-password.blade.php` (61 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kirim Tautan Reset | `POST` | `POST /lupa-sandi` | kolom: login |
| Kembali ke halaman masuk | `→` | `login` | navigasi (GET /login) |

#### ⚙︎ /register
`GET /register` · `register`

_Bukan halaman: closure pengalihan — pendaftaran mandiri ditutup, `GET /register` mengarah ke `/login`._

#### Reset Password
`GET /reset-sandi/{token}` · `password.reset` · `Auth\ResetPasswordController@showResetForm` · `auth/reset-password.blade.php` (66 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Kata Sandi Baru | `POST` | `POST /reset-sandi` | kolom: token, email, kata_sandi, kata_sandi_confirmation |
| Kembali ke halaman masuk | `→` | `login` | navigasi (GET /login) |

### Presensi pegawai (guru · kepsek · tendik) — 1 halaman

#### Presensi Pegawai
`GET /presensi/pegawai` · `presensi.pegawai.index` · `Pegawai\PresensiController@index` · `pegawai/presensi/index.blade.php` (76 baris)

Subjudul: _Catat jam masuk dan jam pulang menggunakan lokasi sekolah_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

### Admin — tab E-Rapor — 64 halaman

#### API Keys & Dapodik
`GET /admin/api-keys` · `admin.api-keys.index` · `Admin\ApiKeyController@index` · `admin/api-keys/index.blade.php` (159 baris)

Subjudul: _Kelola akses API untuk aplikasi eksternal_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| … | `POST` | `POST /admin/api-keys/{apiKey}/toggle` |  |
| 🗑 | `DELETE` | `DELETE /admin/api-keys/{apiKey}` | onsubmit: return confirm('Hapus?') |
| Batal / 🔑 Buat API Key | `POST` | `POST /admin/api-keys` | kolom: name |
| 📋 Salin | `JS` | `salin()` | tombol client-side |
| ➕ Buat Key | `JS` | `document.getElementById('modalForm').sty…` | tombol client-side |
| × | `JS` | `document.getElementById('modalForm').sty…` | tombol client-side |
| Batal | `JS` | `document.getElementById('modalForm').sty…` | tombol client-side |

#### Data & Pemulihan
`GET /admin/backup` · `admin.backup.index` · `Admin\BackupController@index` · `admin/backup/index.blade.php` (151 baris)

Subjudul: _Ekspor dan impor data sistem_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Impor Data | `POST` | `POST /admin/backup/import` | kolom: backup_file; ada konfirmasi |
| Hapus Semua Data Sekarang | `POST` | `POST /admin/backup/wipe` | kolom: konfirmasi, password; onsubmit: return confirm('YAKIN menghapus SEMUA da… |
| Ekspor Data Sekarang | `→` | `admin.backup.export` | navigasi (GET /admin/backup/export) |

#### ⚙︎ /admin/backup/export
`GET /admin/backup/export` · `admin.backup.export` · `Admin\BackupController@export`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Bobot & Ketuntasan
`GET /admin/bobot` · `admin.bobot.index` · `Admin\BobotController@index` · `admin/bobot/index.blade.php` (145 baris)

Subjudul: _Pengaturan bobot penilaian dan KKM per kelas_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Batal / 💾 Simpan | `POST` | `(diatur lewat JS)` | kolom: kkm, bobot_tugas, bobot_uh, bobot_uts, bobot_uas, bobot_formatif, bobot_sumatif, bobot_sumatif_akhir |
| Ubah | `JS` | `editBobot()` | tombol client-side |
| Batal | `JS` | `closeModal()` | tombol client-side |

#### Leger Rapor
`GET /admin/cetak/leger` · `admin.cetak.leger` · `Admin\CetakController@leger` · `admin/cetak/leger.blade.php` (90 baris)

Subjudul: _Rekap nilai akhir semua mapel per kelas_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tampilkan | `GET` | `GET /admin/cetak/leger` | kolom: kelas, semester, tahun_ajaran |
| Cetak | `JS` | `window.print()` | tombol client-side |

#### Nilai Rapor
`GET /admin/cetak/nilai` · `admin.cetak.nilai` · `Admin\CetakController@nilai` · `admin/cetak/nilai.blade.php` (80 baris)

Subjudul: _Cetak hasil belajar per peserta didik_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tampilkan | `GET` | `GET /admin/cetak/nilai` | kolom: kelas, semester, tahun_ajaran |
| Cetak | `→` | `admin.cetak.nilai.show` | navigasi (GET /admin/cetak/nilai/{siswa}) |

#### Nilai Show
`GET /admin/cetak/nilai/{siswa}` · `admin.cetak.nilai.show` · `Admin\CetakController@showNilai` · `admin/cetak/nilai-show.blade.php` (103 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cetak / Simpan PDF | `JS` | `window.print()` | tombol client-side |
| Kembali | `→` | `admin.cetak.nilai` | navigasi (GET /admin/cetak/nilai) |

#### Pelengkap Rapor
`GET /admin/cetak/pelengkap` · `admin.cetak.pelengkap` · `Admin\CetakController@pelengkap` · `admin/cetak/pelengkap.blade.php` (76 baris)

Subjudul: _Ketidakhadiran, catatan, dan data fisik per siswa_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tampilkan | `GET` | `GET /admin/cetak/pelengkap` | kolom: kelas |
| Cetak | `JS` | `window.print()` | tombol client-side |

#### Konfigurasi & Sinkronisasi Dapodik
`GET /admin/dapodik` · `admin.dapodik.index` · `Admin\DapodikImportController@index` · `admin/dapodik/index.blade.php` (885 baris)

Subjudul: _Pilih metode sinkronisasi data Dapodik_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Konfigurasi | `GET` | `GET /admin/dapodik` | onsubmit: return saveConfig(event) |
| Hapus (ikon) | `DELETE` | `DELETE /admin/dapodik/log/{id}` | onsubmit: return confirm('Hapus log ini?') |
| Dapodik Bridge Sinkron otomatis via aplikasi desktop D… | `JS` | `switchTab()` | tombol client-side |
| Kelola Langsung Import data manual dari file atau inpu… | `JS` | `switchTab()` | tombol client-side |
| (ikon) | `JS` | `toggleToken()` | tombol client-side |
| Test Koneksi | `JS` | `testConnection()` | tombol client-side |
| Preview Data | `JS` | `previewData()` | tombol client-side |
| Dry Run | `JS` | `runSync()` | tombol client-side |
| Sinkronisasi | `JS` | `runSync()` | tombol client-side |
| Tutup | `JS` | `hideSyncModal()` | tombol client-side |
| API Keys | `→` | `admin.api-keys.index` | navigasi (GET /admin/api-keys) |
| Download Bridge v3.0 | `→` | `admin.dapodik.download-bridge` | navigasi (GET /admin/dapodik/download-bridge) |
| (ikon) | `→` | `admin.dapodik.log-detail` | navigasi (GET /admin/dapodik/log/{id}) |
| (AJAX) | `fetch` | `'{{ route("admin.dapodik.api.config"` | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ route("admin.dapodik.api.test-connection"` | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ route("admin.dapodik.api.preview"` | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ route("admin.dapodik.api.sync"` | permintaan asinkron |

#### ⚙︎ /admin/dapodik/api/config
`GET /admin/dapodik/api/config` · `admin.dapodik.api.config` · `Api\DapodikController@config`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /admin/dapodik/api/semesters
`GET /admin/dapodik/api/semesters` · `admin.dapodik.api.semesters` · `Api\DapodikController@semesters`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /admin/dapodik/download-bridge
`GET /admin/dapodik/download-bridge` · `admin.dapodik.download-bridge` · `Admin\DapodikImportController@downloadBridge`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Detail Log Sinkronisasi
`GET /admin/dapodik/log/{id}` · `admin.dapodik.log-detail` · `Admin\DapodikImportController@logDetail` · `admin/dapodik/log-detail.blade.php` (72 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `admin.dapodik.index` | navigasi (GET /admin/dapodik) |

#### Kirim Nilai Ke Dapodik
`GET /admin/dapodik/push` · `admin.dapodik.push.index` · `Admin\DapodikPushController@index` · `admin/dapodik/push/index.blade.php` (189 baris)

Subjudul: _Kirim data dari SIMANTAP ke server Dapodik pusat_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Web Service Dapodik | `→` | `admin.dapodik.index` | navigasi (GET /admin/dapodik) |
| (AJAX) | `fetch` | `routeFor(key` | permintaan asinkron |

#### ⚙︎ /admin/dapodik/push/sekolah
`GET /admin/dapodik/push/sekolah` · `admin.dapodik.push.sekolah` · `Api\DapodikPushController@pushSekolah`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /admin/dapodik/push/status
`GET /admin/dapodik/push/status` · `admin.dapodik.push.status` · `Api\DapodikPushController@status`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Admin Dashboard
`GET /admin/dashboard` · `admin.dashboard` · `DashboardController@admin` · `admin/dashboard.blade.php` (481 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Detail | `→` | `admin.referensi.alignment` | navigasi (GET /admin/referensi/alignment) |
| Perbaiki → | `→` | `admin.referensi.alignment` | navigasi (GET /admin/referensi/alignment) |
| Buka Import Dapodik | `→` | `admin.dapodik.index` | navigasi (GET /admin/dapodik) |
| Atur Sekarang | `→` | `admin.dapodik.index` | navigasi (GET /admin/dapodik) |
| Lihat Semua | `→` | `admin.log.index` | navigasi (GET /admin/log) |
| Kelola | `→` | `admin.backup.index` | navigasi (GET /admin/backup) |
| Rincian per Guru | `→` | `admin.penilaian.status` | navigasi (GET /admin/penilaian/status) |
| Kelola Pengguna … akun | `→` | `admin.users.index` | navigasi (GET /admin/users) |
| Peta Kelas … kelas | `→` | `admin.peta-kelas.index` | navigasi (GET /admin/peta-kelas) |
| Import Dapodik … | `→` | `admin.dapodik.index` | navigasi (GET /admin/dapodik) |
| Identitas Sekolah … | `→` | `admin.sekolah.index` | navigasi (GET /admin/sekolah) |
| Semester … | `→` | `admin.semester.index` | navigasi (GET /admin/semester) |
| Backup & Restore Data system | `→` | `admin.backup.index` | navigasi (GET /admin/backup) |

#### Kode & Akses
`GET /admin/kode-akses` · `admin.kode-akses.index` · `Admin\KodeAksesController@index` · `admin/kode-akses/index.blade.php` (93 baris)

Subjudul: _Pengaturan kode akses per guru/kelas_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Batal / 💾 Simpan | `POST` | `(diatur lewat JS)` | kolom: kode_akses |
| Ubah | `JS` | `editKode()` | tombol client-side |
| Batal | `JS` | `closeModal()` | tombol client-side |

#### Kegiatan Kokurikuler
`GET /admin/kokurikuler/kegiatan` · `admin.kokurikuler.kegiatan` · `Admin\KokurikulerController@kegiatan` · `admin/kokurikuler/kegiatan.blade.php` (100 baris)

Subjudul: _Kegiatan per tema beserta koordinator dan jadwalnya_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tambah Kegiatan | `POST` | `POST /admin/kokurikuler/kegiatan` | kolom: tema_id, nama, kelas, koordinator, tanggal_mulai, tanggal_selesai |
| Hapus | `DELETE` | `DELETE /admin/kokurikuler/kegiatan/{kegiatan}` | onsubmit: return confirm('Hapus kegiatan beserta k… |

#### Kelompok Kokurikuler
`GET /admin/kokurikuler/kelompok` · `admin.kokurikuler.kelompok` · `Admin\KokurikulerController@kelompok` · `admin/kokurikuler/kelompok.blade.php` (87 baris)

Subjudul: _Kelompok siswa per kegiatan beserta anggotanya_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tambah Kelompok | `POST` | `POST /admin/kokurikuler/kelompok` | kolom: kegiatan_id, nama, keterangan |
| Hapus | `DELETE` | `DELETE /admin/kokurikuler/kelompok/{kelompok}` | onsubmit: return confirm('Hapus kelompok ini?') |
| Kelola Anggota | `→` | `admin.kokurikuler.kelompok.show` | navigasi (GET /admin/kokurikuler/kelompok/{kelompok}) |

#### Anggota Kelompok
`GET /admin/kokurikuler/kelompok/{kelompok}` · `admin.kokurikuler.kelompok.show` · `Admin\KokurikulerController@showKelompok` · `admin/kokurikuler/kelompok-show.blade.php` (49 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Anggota | `PUT` | `PUT /admin/kokurikuler/kelompok/{kelompok}/anggota` | kolom: anggota[] |
| ← Kembali | `→` | `admin.kokurikuler.kelompok` | navigasi (GET /admin/kokurikuler/kelompok) |

#### Tema Kokurikuler
`GET /admin/kokurikuler/tema` · `admin.kokurikuler.tema` · `Admin\KokurikulerController@tema` · `admin/kokurikuler/tema.blade.php` (75 baris)

Subjudul: _Tema kegiatan kokurikuler / projek_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tambah | `POST` | `POST /admin/kokurikuler/tema` | kolom: nama, deskripsi |
| Hapus | `DELETE` | `DELETE /admin/kokurikuler/tema/{tema}` | onsubmit: return confirm('Hapus tema beserta kegia… |

#### Log Aktivitas
`GET /admin/log` · `admin.log.index` · `Admin\LogController@index` · `admin/log/index.blade.php` (89 baris)

Subjudul: _Riwayat aktivitas seluruh pengguna sistem_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Mata Pelajaran
`GET /admin/mapel` · `admin.mapel.index` · `Admin\MapelController@index` · `admin/mapel/index.blade.php` (189 baris)

Subjudul: _Kelola daftar mata pelajaran_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Default … | `POST` | `POST /admin/mapel/seed` | onsubmit: return confirm('Tambahkan mata pelajaran… |
| Import Dapodik | `POST` | `POST /admin/mapel/import-dapodik` | onsubmit: return confirm('Import mata pelajaran {{… |
| Import Lokal | `POST` | `POST /admin/mapel/import-lokal` | onsubmit: return confirm('Import dari data lokal?'… |
| Cari / Filter (ikon) | `GET` | `GET /admin/mapel` | kolom: search |
| Hapus Terpilih / Batal | `DELETE` | `(diatur lewat JS)` | kolom: ids[] |
| Hapus (ikon) | `DELETE` | `DELETE /admin/mapel/{mapel}` | onsubmit: return confirm('Hapus mata pelajaran ini… |
| Hapus Semua | `DELETE` | `DELETE /admin/mapel/hapus-semua` | onsubmit: return confirm('YAKIN HAPUS SEMUA mata p… |
| Hapus (ikon) | `DELETE` | `DELETE /admin/mapel/hapus-terpilih` | kolom: ids |
| Hapus Terpilih | `JS` | `deleteSelected()` | tombol client-side |
| Batal | `JS` | `clearSelection()` | tombol client-side |
| Tambah Manual | `→` | `admin.mapel.create` | navigasi (GET /admin/mapel/create) |
| (ikon) | `→` | `admin.mapel.edit` | navigasi (GET /admin/mapel/{mapel}/edit) |

#### Tambah Mata Pelajaran
`GET /admin/mapel/create` · `admin.mapel.create` · `Admin\MapelController@create` · `admin/mapel/create.blade.php` (55 baris)

Subjudul: _Jenjang:_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan | `POST` | `POST /admin/mapel` | kolom: nama, kode |
| Batal | `→` | `admin.mapel.index` | navigasi (GET /admin/mapel) |

#### Edit Mata Pelajaran
`GET /admin/mapel/{mapel}/edit` · `admin.mapel.edit` · `Admin\MapelController@edit` · `admin/mapel/edit.blade.php` (73 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Hapus | `DELETE` | `DELETE /admin/mapel/{mapel}` | onsubmit: return confirm('Hapus mata pelajaran ini… |
| Simpan Perubahan | `PUT` | `PUT /admin/mapel/{mapel}` | kolom: nama, kode, aktif, aktif |
| Batal | `→` | `admin.mapel.index` | navigasi (GET /admin/mapel) |

#### Modul & Tampilan
`GET /admin/modul` · `admin.modul.index` · `Admin\ModulController@index` · `admin/modul/index.blade.php` (80 baris)

Subjudul: _Aktifkan atau nonaktifkan modul sistem_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan Pengaturan | `POST` | `POST /admin/modul` | kolom: modul[{{ $key }}] |

#### Statistik Nilai Rapor
`GET /admin/penilaian/statistik` · `admin.penilaian.statistik` · `Admin\PenilaianController@statistik` · `admin/penilaian/statistik.blade.php` (97 baris)

Subjudul: _Sebaran predikat dan rata-rata per mata pelajaran (dari nilai e-rapor)_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tampilkan | `GET` | `GET /admin/penilaian/statistik` | kolom: kelas |
| Reset | `→` | `admin.penilaian.statistik` | navigasi (GET /admin/penilaian/statistik) |

#### Status Penilaian
`GET /admin/penilaian/status` · `admin.penilaian.status` · `Admin\PenilaianController@status` · `admin/penilaian/status.blade.php` (65 baris)

Subjudul: _Pantau kelengkapan input setiap guru (siswa, materi, kuis, nilai, e-rapor)_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Perkembangan Nilai
`GET /admin/perkembangan` · `admin.perkembangan.index` · `Admin\PerkembanganController@index` · `admin/perkembangan/index.blade.php` (56 baris)

Subjudul: _Tren rata-rata nilai rapor antar semester_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Grafik Nilai Rapor
`GET /admin/perkembangan/grafik` · `admin.perkembangan.grafik` · `Admin\PerkembanganController@grafik` · `admin/perkembangan/grafik.blade.php` (102 baris)

Subjudul: _Sebaran predikat dan rata-rata per kelas_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tampilkan | `GET` | `GET /admin/perkembangan/grafik` | kolom: kelas |
| Reset | `→` | `admin.perkembangan.grafik` | navigasi (GET /admin/perkembangan/grafik) |

#### Peta Kelas
`GET /admin/peta-kelas` · `admin.peta-kelas.index` · `Admin\PetaKelasController@index` · `admin/peta-kelas/index.blade.php` (307 baris)

Subjudul: _Pemetaan guru dan siswa per kelas — seret siswa untuk pindah kelas_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 📦 | `JS` | `openModal()` | tombol client-side |
| ✕ | `JS` | `keluarkan()` | tombol client-side |
| × | `JS` | `closeModal()` | tombol client-side |
| Batal | `JS` | `closeModal()` | tombol client-side |
| Pindahkan | `JS` | `prosesPindah()` | tombol client-side |
| (AJAX) | `fetch` | `'{{ route("admin.peta-kelas.kelas-options"` | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ route("admin.peta-kelas.pindah"` | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ route("admin.peta-kelas.keluarkan"` | permintaan asinkron |

#### Data Kelas
`GET /admin/peta-kelas/daftar` · `admin.peta-kelas.daftar` · `Admin\PetaKelasController@daftar` · `admin/peta-kelas/daftar.blade.php` (69 baris)

Subjudul: _Daftar rombongan belajar dari Dapodik_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Papan Pemetaan | `→` | `admin.peta-kelas.index` | navigasi (GET /admin/peta-kelas) |
| Semua | `→` | `admin.peta-kelas.daftar` | navigasi (GET /admin/peta-kelas/daftar) |
| … | `→` | `admin.peta-kelas.daftar` | navigasi (GET /admin/peta-kelas/daftar) |
| Anggota | `→` | `admin.peta-kelas.detail` | navigasi (GET /admin/peta-kelas/{rombel}) |

#### ⚙︎ /admin/peta-kelas/kelas-options
`GET /admin/peta-kelas/kelas-options` · `admin.peta-kelas.kelas-options` · `Admin\PetaKelasController@getKelasOptions`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Anggota Rombel
`GET /admin/peta-kelas/{rombel}` · `admin.peta-kelas.detail` · `Admin\PetaKelasController@detail` · `admin/peta-kelas/detail.blade.php` (71 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `admin.peta-kelas.daftar` | navigasi (GET /admin/peta-kelas/daftar) |

#### Presensi Pegawai
`GET /admin/presensi` · `admin.presensi.index` · `Admin\PresensiController@index` · `admin/presensi/index.blade.php` (147 baris)

Subjudul: _Atur area absensi dan pantau presensi guru, kepala sekolah, serta tendik_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Gunakan lokasi perangkat / Tambah titik | `POST` | `POST /admin/presensi/lokasi` | kolom: nama, latitude, longitude, radius_meter, akurasi_maksimal_meter, aktif |
| Simpan | `PUT` | `PUT /admin/presensi/lokasi/{lokasi}` | kolom: nama, latitude, longitude, radius_meter, akurasi_maksimal_meter, aktif |
| Hapus titik | `DELETE` | `DELETE /admin/presensi/lokasi/{lokasi}` | onsubmit: return confirm('Hapus titik lokasi {{ ad… |
| Tampilkan | `GET` | `GET /admin/presensi` | kolom: tanggal |
| Simpan koreksi | `POST` | `POST /admin/presensi/pegawai/koreksi` | kolom: user_id, tanggal, status, jam_masuk, jam_pulang, alasan_koreksi |

#### Pemetaan QR Kartu Siswa
`GET /admin/presensi/kartu-siswa` · `admin.presensi.kartu.index` · `Admin\PresensiKartuController@index` · `admin/presensi/kartu.blade.php` (67 baris)

Subjudul: _Hubungkan QR yang sudah tercetak ke data siswa tanpa menyimpan isi QR mentah_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cari | `GET` | `GET /admin/presensi/kartu-siswa` | kolom: q |
| Lepaskan | `DELETE` | `DELETE /admin/presensi/kartu-siswa/{siswa}` | onsubmit: return confirm('Lepaskan QR kartu {{ add… |

#### Profile
`GET /admin/profile` · `admin.profile` · `Admin\ProfileController@show` · `admin/profile.blade.php` (115 baris)

Subjudul: _Detail akun dan ubah password_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Password | `PUT` | `PUT /admin/profile/password` | kolom: kata_sandi_saat_ini, password, password_confirmation |

#### Cek Alignment E-Rapor
`GET /admin/referensi/alignment` · `admin.referensi.alignment` · `Admin\DataReferensiController@alignmentCheck` · `admin/referensi/alignment-check.blade.php` (122 baris)

_Layout diubah 28 Okt 2026: `layouts.app` → `layouts.admin-with-tabs`, sehingga halaman ini kini punya bilah tab dan sidebar per tab (sebelumnya tanpa navigasi sama sekali — lihat T3 di §6.1)._

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Coba Cocokkan Ulang | `POST` | `POST /admin/referensi/alignment/refresh` |  |
| Ekspor Semua Belum Terpetakan (CSV) | `→` | `admin.referensi.alignment.export` | navigasi (GET /admin/referensi/alignment/export) |

#### ⚙︎ /admin/referensi/alignment/export
`GET /admin/referensi/alignment/export` · `admin.referensi.alignment.export` · `Admin\DataReferensiController@exportUnmatchedAlignment`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Ekstrakurikuler
`GET /admin/referensi/ekstrakurikuler` · `admin.referensi.ekstrakurikuler` · `Admin\DataReferensiController@ekstrakurikuler` · `admin/referensi/ekstrakurikuler.blade.php` (84 baris)

Subjudul: _Kelola kegiatan ekstrakurikuler sekolah_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tambah Ekstrakurikuler | `POST` | `POST /admin/referensi/ekstrakurikuler` | kolom: nama, pembina_id, deskripsi |
| Hapus | `DELETE` | `DELETE /admin/referensi/ekstrakurikuler/{ekstrakurikuler}` | onsubmit: return confirm('Hapus ekstrakurikuler in… |

#### Foto Siswa
`GET /admin/referensi/foto-siswa` · `admin.referensi.foto-siswa` · `Admin\DataReferensiController@fotoSiswa` · `admin/referensi/foto-siswa.blade.php` (99 baris)

Subjudul: _Unggah foto massal — nama file harus sama dengan NIS atau NISN (mis. 2401001.jpg)_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Unggah & Pasangkan | `POST` | `POST /admin/referensi/foto-siswa` | kolom: foto[] |
| Upload | `POST` | `POST /admin/referensi/foto-siswa/{siswa}` | kolom: foto |

#### Data Guru
`GET /admin/referensi/guru` · `admin.referensi.guru` · `Admin\DataReferensiController@guru` · `admin/referensi/guru.blade.php` (113 baris)

Subjudul: _Data pendidik dan tenaga kependidikan dari Dapodik_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Gelar Guru | `PUT` | `PUT /admin/referensi/gelar-ptk` |  |
| Import Dapodik | `→` | `admin.dapodik.index` | navigasi (GET /admin/dapodik) |
| Peta Kelas | `→` | `admin.peta-kelas.index` | navigasi (GET /admin/peta-kelas) |

#### Kelompok Mapel
`GET /admin/referensi/kelompok-mapel` · `admin.referensi.kelompok-mapel` · `Admin\DataReferensiController@kelompokMapel` · `admin/referensi/kelompok-mapel.blade.php` (72 baris)

Subjudul: _Kelompokkan mata pelajaran (mis. A, B, C) untuk pengelompokan di rapor_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Kelompok | `PUT` | `PUT /admin/referensi/mapel-meta` |  |

#### Logo dan TTD
`GET /admin/referensi/logo-ttd` · `admin.referensi.logo-ttd` · `Admin\DataReferensiController@logoTtd` · `admin/referensi/logo-ttd.blade.php` (45 baris)

Subjudul: _Unggah logo pemda, logo sekolah, tanda tangan kepala sekolah, dan kop sekolah (maks 2MB pe…_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Semua | `POST` | `POST /admin/referensi/logo-ttd` | kolom: {{ $key }} |

#### Mapping Rapor
`GET /admin/referensi/mapping-rapor` · `admin.referensi.mapping-rapor` · `Admin\DataReferensiController@mappingRapor` · `admin/referensi/mapping-rapor.blade.php` (63 baris)

Subjudul: _Atur urutan tampil mata pelajaran di rapor (angka kecil tampil lebih dulu)_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Urutan | `PUT` | `PUT /admin/referensi/mapel-meta` |  |

#### Data Pembelajaran
`GET /admin/referensi/pembelajaran` · `admin.referensi.pembelajaran` · `Admin\DataReferensiController@pembelajaran` · `admin/referensi/pembelajaran.blade.php` (150 baris)

Subjudul: _Jadwal pembelajaran (rombongan belajar × mapel × guru); baris DAPODIK berasal dari hasil s…_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tambah | `POST` | `POST /admin/referensi/pembelajaran` | kolom: hari, mata_pelajaran, kelas, guru_id, jam_mulai, jam_selesai, ruangan |
| Hapus | `DELETE` | `DELETE /admin/referensi/pembelajaran/{pembelajaran}` | onsubmit: return confirm('Hapus jadwal {{ $j->mata… |
| Import Dapodik | `→` | `admin.dapodik.index` | navigasi (GET /admin/dapodik) |
| Peta Kelas | `→` | `admin.peta-kelas.index` | navigasi (GET /admin/peta-kelas) |

#### Tanggal Rapor
`GET /admin/referensi/tanggal-rapor` · `admin.referensi.tanggal-rapor` · `Admin\DataReferensiController@tanggalRapor` · `admin/referensi/tanggal-rapor.blade.php` (95 baris)

Subjudul: _Atur tanggal pembagian rapor per tahun ajaran dan semester_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Tanggal Rapor | `POST` | `POST /admin/referensi/tanggal-rapor` | kolom: tahun_ajaran, semester, tanggal, tempat, keterangan |
| Hapus | `DELETE` | `DELETE /admin/referensi/tanggal-rapor/{tanggalRapor}` | onsubmit: return confirm('Hapus tanggal rapor ini?… |

#### Identitas Sekolah
`GET /admin/sekolah` · `admin.sekolah.index` · `Admin\SekolahController@index` · `admin/sekolah/index.blade.php` (275 baris)

Subjudul: _Profil dan identitas resmi sekolah_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cari / 💾 Simpan Identitas | `POST` | `POST /admin/sekolah` | kolom: nama_sekolah, npsn, jenis_sekolah, jenjang, akreditasi, kepala_sekolah, alamat, kecamatan, kota, provin… |
| Cari | `JS` | `lookupNpsn()` | tombol client-side |
| (AJAX) | `fetch` | `'{{ route("admin.sekolah.search"` | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ url("/api/sekolah/lookup"` | permintaan asinkron |

#### Semester
`GET /admin/semester` · `admin.semester.index` · `Admin\SemesterController@index` · `admin/semester/index.blade.php` (120 baris)

Subjudul: _Kelola semester aktif dan tahun ajaran_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan | `POST` | `POST /admin/semester` | kolom: semester_id, tahun_ajaran, nama_semester |
| Aktifkan | `POST` | `POST /admin/semester/{semester}/activate` |  |
| Hapus (ikon) | `DELETE` | `DELETE /admin/semester/{semester}` | onsubmit: return confirm('Hapus semester ini?') |

#### Penyimpanan Data
`GET /admin/sheet` · `admin.sheet.index` · `Admin\SheetController@index` · `admin/sheet/index.blade.php` (91 baris)

Subjudul: _Koneksi Google Sheet untuk penyimpanan data_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Putuskan Koneksi | `POST` | `POST /admin/sheet/disconnect` | ada konfirmasi |
| Hubungkan | `POST` | `POST /admin/sheet/connect` | kolom: spreadsheet_id, spreadsheet_url |

#### Cetak Transkrip Nilai
`GET /admin/transkrip/cetak` · `admin.transkrip.cetak` · `Admin\TranskripController@cetak` · `admin/transkrip/cetak.blade.php` (52 baris)

Subjudul: _Pilih siswa untuk mencetak transkrip_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cari | `GET` | `GET /admin/transkrip/cetak` | kolom: q |
| Cetak | `→` | `admin.transkrip.cetak.show` | navigasi (GET /admin/transkrip/cetak/{siswa}) |

#### Cetak Show
`GET /admin/transkrip/cetak/{siswa}` · `admin.transkrip.cetak.show` · `Admin\TranskripController@showCetak` · `admin/transkrip/cetak-show.blade.php` (99 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cetak / Simpan PDF | `JS` | `window.print()` | tombol client-side |
| Kembali | `→` | `admin.transkrip.cetak` | navigasi (GET /admin/transkrip/cetak) |

#### Import Nilai Transkrip
`GET /admin/transkrip/import` · `admin.transkrip.import` · `Admin\TranskripController@importNilai` · `admin/transkrip/import.blade.php` (41 baris)

Subjudul: _Unggah CSV nilai akhir per siswa per mapel_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Import | `POST` | `POST /admin/transkrip/import` | kolom: file |
| Unduh Template | `→` | `admin.transkrip.import.template` | navigasi (GET /admin/transkrip/import/template) |

#### ⚙︎ /admin/transkrip/import/template
`GET /admin/transkrip/import/template` · `admin.transkrip.import.template` · `Admin\TranskripController@templateNilai`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Input Nilai Transkrip
`GET /admin/transkrip/input` · `admin.transkrip.input` · `Admin\TranskripController@input` · `admin/transkrip/input.blade.php` (55 baris)

Subjudul: _Nilai akhir per mapel untuk ijazah_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cari | `GET` | `GET /admin/transkrip/input` | kolom: q |
| Input Nilai | `→` | `admin.transkrip.input.form` | navigasi (GET /admin/transkrip/input/{siswa}) |

#### Input Nilai Transkrip
`GET /admin/transkrip/input/{siswa}` · `admin.transkrip.input.form` · `Admin\TranskripController@formInput` · `admin/transkrip/input-form.blade.php` (56 baris)

Subjudul: _NISN_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Nilai | `PUT` | `PUT /admin/transkrip/input/{siswa}` |  |
| Kembali | `→` | `admin.transkrip.input` | navigasi (GET /admin/transkrip/input) |

#### Nomor Ijazah
`GET /admin/transkrip/nomor` · `admin.transkrip.nomor` · `Admin\TranskripController@nomor` · `admin/transkrip/nomor.blade.php` (79 baris)

Subjudul: _Kelola nomor ijazah per peserta didik_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Import | `POST` | `POST /admin/transkrip/nomor/import` | kolom: file |
| Cari | `GET` | `GET /admin/transkrip/nomor` | kolom: q |
| Simpan | `PUT` | `PUT /admin/transkrip/nomor/{siswa}` | kolom: nomor_ijazah |

#### Setting Transkrip
`GET /admin/transkrip/setting` · `admin.transkrip.setting` · `Admin\TranskripController@setting` · `admin/transkrip/setting.blade.php` (42 baris)

Subjudul: _Kop penerbitan transkrip nilai ijazah_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Setting | `PUT` | `PUT /admin/transkrip/setting` | kolom: tempat, tahun_lulus, nip_kepala_sekolah, keterangan |

#### Kelola Pengguna
`GET /admin/users` · `admin.users.index` · `Admin\UserController@index` · `admin/users/index.blade.php` (265 baris)

Subjudul: _Kelola semua akun pengguna sistem_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Semua (…) | `JS` | `switchTab()` | tombol client-side |
| Admin (…) | `JS` | `switchTab()` | tombol client-side |
| Guru (…) | `JS` | `switchTab()` | tombol client-side |
| Siswa (…) | `JS` | `switchTab()` | tombol client-side |
| Orang Tua (…) | `JS` | `switchTab()` | tombol client-side |
| Kepala Sekolah (…) | `JS` | `switchTab()` | tombol client-side |
| Tendik (…) | `JS` | `switchTab()` | tombol client-side |
| Belum Punya Akun (…) | `JS` | `switchTab()` | tombol client-side |
| Hapus Semua Guru | `JS` | `hapusSemua()` | tombol client-side |
| Hapus Semua Siswa | `JS` | `hapusSemua()` | tombol client-side |
| Hapus Semua Orang Tua | `JS` | `hapusSemua()` | tombol client-side |
| Hapus | `JS` | `hapusUser()` | tombol client-side |
| + Tambah akun | `→` | `admin.users.create` | navigasi (GET /admin/users/create) |
| Akun Massal | `→` | `admin.users.akun-massal` | navigasi (GET /admin/users/akun-massal) |
| Ubah | `→` | `admin.users.edit` | navigasi (GET /admin/users/{param}/edit) |
| Buat Akun Massal | `→` | `admin.users.akun-massal` | navigasi (GET /admin/users/akun-massal) |
| Buat Akun | `→` | `admin.users.create` | navigasi (GET /admin/users/create) |
| (AJAX) | `fetch` | `'/admin/users/' + id` | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ url("/admin/users/hapus-semua"` | permintaan asinkron |

#### Akun Massal
`GET /admin/users/akun-massal` · `admin.users.akun-massal` · `Admin\UserController@akunMassal` · `admin/users/massal.blade.php` (119 baris)

Subjudul: _Buat akun siswa secara massal_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Centang Semua / Batal Centang / Buat Akun Terpilih | `GET` | `GET /admin/users/akun-massal` |  |
| Centang Semua | `JS` | `centangSemua()` | tombol client-side |
| Batal Centang | `JS` | `batalCentang()` | tombol client-side |
| Kembali | `→` | `admin.users.index` | navigasi (GET /admin/users) |
| (AJAX) | `fetch` | `'{{ route("admin.users.proses-massal"` | permintaan asinkron |

#### Tambah Akun
`GET /admin/users/create` · `admin.users.create` · `Admin\UserController@create` · `admin/users/create.blade.php` (66 baris)

Subjudul: _Buat akun pengguna baru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ðŸ’¾ Simpan | `POST` | `POST /admin/users` | kolom: nama_lengkap, nama_pengguna, email, kata_sandi, peran, kelas_mata_pelajaran |
| â† Kembali | `→` | `admin.users.index` | navigasi (GET /admin/users) |
| Batal | `→` | `admin.users.index` | navigasi (GET /admin/users) |

#### Data Siswa
`GET /admin/users/siswa` · `admin.users.siswa` · `Admin\UserController@siswaIndex` · `admin/users/siswa.blade.php` (116 baris)

Subjudul: _Data peserta didik dari Dapodik beserta status akunnya (berbeda dengan Kelola Pengguna yan…_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Akun Massal | `→` | `admin.users.akun-massal` | navigasi (GET /admin/users/akun-massal) |
| Import Dapodik | `→` | `admin.dapodik.index` | navigasi (GET /admin/dapodik) |

#### Ubah Akun
`GET /admin/users/{param}/edit` · `admin.users.edit` · `Admin\UserController@edit` · `admin/users/edit.blade.php` (65 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ðŸ’¾ Simpan | `PUT` | `PUT /admin/users/{param}` | kolom: nama_lengkap, nama_pengguna, email, kata_sandi, peran, kelas_mata_pelajaran |
| â† Kembali | `→` | `admin.users.index` | navigasi (GET /admin/users) |
| Batal | `→` | `admin.users.index` | navigasi (GET /admin/users) |

### Admin — tab CBT/Ujian — 11 halaman

#### Manajemen Bank Soal CBT - Admin
`GET /admin/cbt/banks` · `admin.cbt.banks.index` · `Admin\Cbt\CbtBankController@index` · `admin/cbt/banks/index.blade.php` (130 baris)

Subjudul: _Kelola semua bank soal_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Filter | `GET` | `GET /admin/cbt/banks` | kolom: guru_id, q |
| Hapus (ikon) | `DELETE` | `DELETE /admin/cbt/banks/{bank}` | onsubmit: return confirm('Yakin ingin menghapus ba… |
| Reset | `→` | `admin.cbt.banks.index` | navigasi (GET /admin/cbt/banks) |
| Buat Bank Soal | `→` | `admin.cbt.banks.create` | navigasi (GET /admin/cbt/banks/create) |
| (ikon) | `→` | `admin.cbt.banks.show` | navigasi (GET /admin/cbt/banks/{bank}) |
| (ikon) | `→` | `admin.cbt.banks.edit` | navigasi (GET /admin/cbt/banks/{bank}/edit) |

#### ⚙︎ /admin/cbt/banks/api/statistics
`GET /admin/cbt/banks/api/statistics` · `admin.cbt.banks.statistics` · `Admin\Cbt\CbtBankController@statistics`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Buat Bank Soal CBT - Admin
`GET /admin/cbt/banks/create` · `admin.cbt.banks.create` · `Admin\Cbt\CbtBankController@create` · `admin/cbt/banks/create.blade.php` (69 baris)

Subjudul: _Bank soal baru untuk seluruh guru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan | `POST` | `POST /admin/cbt/banks` | kolom: title, subject, guru_id |
| Kembali | `→` | `admin.cbt.banks.index` | navigasi (GET /admin/cbt/banks) |

#### Detail Bank Soal CBT - Admin
`GET /admin/cbt/banks/{bank}` · `admin.cbt.banks.show` · `Admin\Cbt\CbtBankController@show` · `admin/cbt/banks/show.blade.php` (118 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Edit | `→` | `admin.cbt.banks.edit` | navigasi (GET /admin/cbt/banks/{bank}/edit) |
| Kembali | `→` | `admin.cbt.banks.index` | navigasi (GET /admin/cbt/banks) |
| … | `→` | `admin.cbt.sessions.show` | navigasi (GET /admin/cbt/sessions/{session}) |

#### Edit Bank Soal CBT - Admin
`GET /admin/cbt/banks/{bank}/edit` · `admin.cbt.banks.edit` · `Admin\Cbt\CbtBankController@edit` · `admin/cbt/banks/edit.blade.php` (69 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Perubahan | `PUT` | `PUT /admin/cbt/banks/{bank}` | kolom: title, subject, guru_id |
| Kembali | `→` | `admin.cbt.banks.index` | navigasi (GET /admin/cbt/banks) |

#### Sesi Ujian CBT - Admin
`GET /admin/cbt/sessions` · `admin.cbt.sessions.index` · `Admin\Cbt\CbtSessionController@index` · `admin/cbt/sessions/index.blade.php` (134 baris)

Subjudul: _Monitoring seluruh sesi ujian_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Filter | `GET` | `GET /admin/cbt/sessions` | kolom: status, guru_id |
| Reset | `→` | `admin.cbt.sessions.index` | navigasi (GET /admin/cbt/sessions) |
| Buat Sesi Ujian | `→` | `admin.cbt.sessions.create` | navigasi (GET /admin/cbt/sessions/create) |
| (ikon) | `→` | `admin.cbt.sessions.show` | navigasi (GET /admin/cbt/sessions/{session}) |
| (ikon) | `→` | `admin.cbt.sessions.export` | navigasi (GET /admin/cbt/sessions/{session}/export/{format}) |

#### Buat Sesi Ujian CBT - Admin
`GET /admin/cbt/sessions/create` · `admin.cbt.sessions.create` · `Admin\Cbt\CbtSessionController@create` · `admin/cbt/sessions/create.blade.php` (102 baris)

Subjudul: _Sesi baru (status draf)_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Draf | `POST` | `POST /admin/cbt/sessions` | kolom: guru_id, bank_id, title, kelas, opens_at, closes_at, duration_minutes |
| Kembali | `→` | `admin.cbt.sessions.index` | navigasi (GET /admin/cbt/sessions) |

#### Detail Sesi Ujian CBT - Admin
`GET /admin/cbt/sessions/{session}` · `admin.cbt.sessions.show` · `Admin\Cbt\CbtSessionController@show` · `admin/cbt/sessions/show.blade.php` (180 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Publikasikan | `POST` | `POST /admin/cbt/sessions/{session}/update-status` | kolom: status; ada konfirmasi |
| Buka Sesi | `POST` | `POST /admin/cbt/sessions/{session}/update-status` | kolom: status |
| Tutup Paksa | `POST` | `POST /admin/cbt/sessions/{session}/force-complete` | ada konfirmasi |
| Hapus Sesi | `DELETE` | `DELETE /admin/cbt/sessions/{session}` | ada konfirmasi |
| Ekspor CSV | `→` | `admin.cbt.sessions.export` | navigasi (GET /admin/cbt/sessions/{session}/export/{format}) |
| Analisis Butir | `→` | `admin.cbt.sessions.analisis-butir` | navigasi (GET /admin/cbt/sessions/{session}/analisis-butir) |
| Kembali | `→` | `admin.cbt.sessions.index` | navigasi (GET /admin/cbt/sessions) |

#### Analisis Butir Soal - Admin
`GET /admin/cbt/sessions/{session}/analisis-butir` · `admin.cbt.sessions.analisis-butir` · `Admin\Cbt\CbtSessionController@analisisButir` · `admin/cbt/sessions/analisis.blade.php` (148 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali ke Sesi | `→` | `admin.cbt.sessions.show` | navigasi (GET /admin/cbt/sessions/{session}) |

#### ⚙︎ /admin/cbt/sessions/{session}/export-engine/{format}
`GET /admin/cbt/sessions/{session}/export-engine/{format}` · `admin.cbt.sessions.export-engine` · `Admin\Cbt\CbtSessionController@exportResultsEngine`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /admin/cbt/sessions/{session}/export/{format}
`GET /admin/cbt/sessions/{session}/export/{format}` · `admin.cbt.sessions.export` · `Admin\Cbt\CbtSessionController@exportResults`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

### Admin — tab Tryout — 13 halaman

#### Kelola Paket Tryout - Admin
`GET /admin/tryout/packages` · `admin.tryout.packages.index` · `Admin\Tryout\TryoutPackageAdminController@index` · `admin/tryout/packages/index.blade.php` (244 baris)

Subjudul: _Kelola paket tryout_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Filter | `GET` | `GET /admin/tryout/packages` | kolom: jenjang, tipe_ujian, aktif, mata_pelajaran_id, tahun, search |
| Kirim (ikon) | `POST` | `POST /admin/tryout/packages/{package}/toggle-status` |  |
| Hapus (ikon) | `DELETE` | `DELETE /admin/tryout/packages/{package}` | onsubmit: return confirm('Yakin ingin menghapus pa… |
| Reset | `→` | `admin.tryout.packages.index` | navigasi (GET /admin/tryout/packages) |
| Tambah Paket | `→` | `admin.tryout.packages.create` | navigasi (GET /admin/tryout/packages/create) |
| Kembali ke Statistik | `→` | `admin.tryout.statistics` | navigasi (GET /admin/tryout/statistics) |
| (ikon) | `→` | `admin.tryout.packages.show` | navigasi (GET /admin/tryout/packages/{package}) |
| (ikon) | `→` | `admin.tryout.packages.edit` | navigasi (GET /admin/tryout/packages/{package}/edit) |

#### Tambah Paket Tryout
`GET /admin/tryout/packages/create` · `admin.tryout.packages.create` · `Admin\Tryout\TryoutPackageAdminController@create` · `admin/tryout/packages/create.blade.php` (88 baris)

Subjudul: _Paket soal tryout baru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Paket | `POST` | `POST /admin/tryout/packages` | kolom: kode_paket, nama_paket, jenjang, tipe_ujian, tahun_paket, mata_pelajaran_id, total_soal, waktu_menit, k… |
| Kembali | `→` | `admin.tryout.packages.index` | navigasi (GET /admin/tryout/packages) |

#### Detail Paket Tryout - Admin
`GET /admin/tryout/packages/{package}` · `admin.tryout.packages.show` · `Admin\Tryout\TryoutPackageAdminController@show` · `admin/tryout/packages/show.blade.php` (163 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Edit | `→` | `admin.tryout.packages.edit` | navigasi (GET /admin/tryout/packages/{package}/edit) |
| Kembali | `→` | `admin.tryout.packages.index` | navigasi (GET /admin/tryout/packages) |

#### Edit Paket Tryout - Admin
`GET /admin/tryout/packages/{package}/edit` · `admin.tryout.packages.edit` · `Admin\Tryout\TryoutPackageAdminController@edit` · `admin/tryout/packages/edit.blade.php` (94 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Perubahan | `PUT` | `PUT /admin/tryout/packages/{package}` | kolom: kode_paket, nama_paket, jenjang, tipe_ujian, tahun_paket, mata_pelajaran_id, total_soal, waktu_menit, k… |
| Kembali | `→` | `admin.tryout.packages.show` | navigasi (GET /admin/tryout/packages/{package}) |

#### Kelola Hasil Tryout - Admin
`GET /admin/tryout/results` · `admin.tryout.results.index` · `Admin\Tryout\TryoutPackageAdminController@allResults` · `admin/tryout/results/index.blade.php` (243 baris)

Subjudul: _Lihat semua hasil tryout_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Filter / Export Excel | `GET` | `GET /admin/tryout/results` | kolom: lulus, package_id, session_id, tanggal_from, tanggal_to, search |
| Export Excel | `JS` | `exportResults()` | tombol client-side |
| Reset | `→` | `admin.tryout.results.index` | navigasi (GET /admin/tryout/results) |
| Kembali ke Statistik | `→` | `admin.tryout.statistics` | navigasi (GET /admin/tryout/statistics) |

#### Detail Hasil Tryout - Admin
`GET /admin/tryout/results/engine/{attempt}` · `admin.tryout.results.engine.show` · `Admin\Tryout\TryoutPackageAdminController@showEngineResult` · `admin/tryout/results/show.blade.php` (154 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali | `→` | `admin.tryout.results.index` | navigasi (GET /admin/tryout/results) |

#### ⚙︎ /admin/tryout/results/export
`GET /admin/tryout/results/export` · `admin.tryout.results.export` · `Admin\Tryout\TryoutPackageAdminController@exportResults`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Detail Hasil Tryout - Admin
`GET /admin/tryout/results/{result}` · `admin.tryout.results.show` · `Admin\Tryout\TryoutPackageAdminController@showResult` · `admin/tryout/results/show.blade.php` (154 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali | `→` | `admin.tryout.results.index` | navigasi (GET /admin/tryout/results) |

#### Kelola Sesi Tryout - Admin
`GET /admin/tryout/sessions` · `admin.tryout.sessions.index` · `Admin\Tryout\TryoutPackageAdminController@allSessions` · `admin/tryout/sessions/index.blade.php` (272 baris)

Subjudul: _Kelola sesi tryout_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Filter | `GET` | `GET /admin/tryout/sessions` | kolom: status, package_id, tanggal_mulai_from, tanggal_mulai_to, tahun_ajaran_id, search |
| Kirim (ikon) | `POST` | `POST /admin/tryout/sessions/{session}/force-complete` | onsubmit: return confirm('Yakin ingin memaksa meny… |
| Hapus (ikon) | `DELETE` | `DELETE /admin/tryout/sessions/{session}` | onsubmit: return confirm('Yakin ingin menghapus se… |
| Reset | `→` | `admin.tryout.sessions.index` | navigasi (GET /admin/tryout/sessions) |
| Buat Sesi Baru | `→` | `admin.tryout.sessions.create` | navigasi (GET /admin/tryout/sessions/create) |
| Kembali ke Statistik | `→` | `admin.tryout.statistics` | navigasi (GET /admin/tryout/statistics) |
| (ikon) | `→` | `admin.tryout.sessions.show` | navigasi (GET /admin/tryout/sessions/{session}) |
| (ikon) | `→` | `admin.tryout.sessions.edit` | navigasi (GET /admin/tryout/sessions/{session}/edit) |

#### Buat Sesi Tryout
`GET /admin/tryout/sessions/create` · `admin.tryout.sessions.create` · `Admin\Tryout\TryoutPackageAdminController@createSession` · `admin/tryout/sessions/create.blade.php` (78 baris)

Subjudul: _Jadwal pelaksanaan tryout_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Sesi | `POST` | `POST /admin/tryout/sessions` | kolom: kode_sesi, nama_sesi, package_id, semester_id, tanggal_mulai, tanggal_selesai, durasi_menit, instruksi |
| Kembali | `→` | `admin.tryout.sessions.index` | navigasi (GET /admin/tryout/sessions) |

#### Detail Sesi Tryout - Admin
`GET /admin/tryout/sessions/{session}` · `admin.tryout.sessions.show` · `Admin\Tryout\TryoutPackageAdminController@showSession` · `admin/tryout/sessions/show.blade.php` (182 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Paksa Selesai | `POST` | `POST /admin/tryout/sessions/{session}/force-complete` | onsubmit: return confirm('Yakin ingin memaksa meny… |
| Edit | `→` | `admin.tryout.sessions.edit` | navigasi (GET /admin/tryout/sessions/{session}/edit) |
| Kembali | `→` | `admin.tryout.sessions.index` | navigasi (GET /admin/tryout/sessions) |
| Detail | `→` | `admin.tryout.results.show` | navigasi (GET /admin/tryout/results/{result}) |

#### Edit Sesi Tryout - Admin
`GET /admin/tryout/sessions/{session}/edit` · `admin.tryout.sessions.edit` · `Admin\Tryout\TryoutPackageAdminController@editSession` · `admin/tryout/sessions/edit.blade.php` (81 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Perubahan | `PUT` | `PUT /admin/tryout/sessions/{session}` | kolom: kode_sesi, nama_sesi, package_id, semester_id, tanggal_mulai, tanggal_selesai, durasi_menit, instruksi |
| Kembali | `→` | `admin.tryout.sessions.show` | navigasi (GET /admin/tryout/sessions/{session}) |

#### Statistik Tryout - Admin
`GET /admin/tryout/statistics` · `admin.tryout.statistics` · `Admin\Tryout\TryoutPackageAdminController@statistics` · `admin/tryout/statistics.blade.php` (211 baris)

Subjudul: _Statistik dan ringkasan tryout_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Lihat Semua | `→` | `admin.tryout.sessions.index` | navigasi (GET /admin/tryout/sessions) |
| Kelola Paket | `→` | `admin.tryout.packages.index` | navigasi (GET /admin/tryout/packages) |
| Semua Sesi | `→` | `admin.tryout.sessions.index` | navigasi (GET /admin/tryout/sessions) |
| Semua Hasil | `→` | `admin.tryout.results.index` | navigasi (GET /admin/tryout/results) |
| View Guru | `→` | `tryout.packages.index` | navigasi (GET /tryout/packages) |

### Admin — tab TKA — 9 halaman

#### Dashboard TKA - Admin
`GET /admin/tka` · `admin.tka.dashboard` · `Admin\Tka\TkaAdminController@index` · `admin/tka/dashboard.blade.php` (352 baris)

Subjudul: _Monitoring Profil Pelajar_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Unduh Laporan | `POST` | `POST /admin/tka/generate-report` | kolom: semester_id, format |
| Ekspor Data | `POST` | `POST /admin/tka/export` | kolom: type, semester_id, format |
| (ikon) | `→` | `admin.tka.warnings.index` | navigasi (GET /admin/tka/warnings) |
| Lihat Semua | `→` | `admin.tka.assessments.index` | navigasi (GET /admin/tka/assessments) |
| Kelola Dimensi | `→` | `admin.tka.dimensions.index` | navigasi (GET /admin/tka/dimensions) |
| Semua Penilaian | `→` | `admin.tka.assessments.index` | navigasi (GET /admin/tka/assessments) |
| Early Warnings | `→` | `admin.tka.warnings.index` | navigasi (GET /admin/tka/warnings) |

#### Kelola Penilaian TKA - Admin
`GET /admin/tka/assessments` · `admin.tka.assessments.index` · `Admin\Tka\TkaAdminController@assessments` · `admin/tka/assessments/index.blade.php` (214 baris)

Subjudul: _Kelola data penilaian_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Filter | `GET` | `GET /admin/tka/assessments` | kolom: status_kesiapan, dimension_id, semester_id, tanggal_from, tanggal_to, search |
| Reset | `→` | `admin.tka.assessments.index` | navigasi (GET /admin/tka/assessments) |
| Kembali ke Dashboard | `→` | `admin.tka.dashboard` | navigasi (GET /admin/tka) |
| (ikon) | `→` | `admin.tka.assessments.history` | navigasi (GET /admin/tka/assessments/siswa/{siswaId}/history) |

#### Riwayat TKA Siswa - Admin
`GET /admin/tka/assessments/siswa/{siswaId}/history` · `admin.tka.assessments.history` · `Admin\Tka\TkaAdminController@studentHistory` · `admin/tka/students/history.blade.php` (189 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali | `→` | `admin.tka.assessments.index` | navigasi (GET /admin/tka/assessments) |

#### Kelola Dimensi TKA - Admin
`GET /admin/tka/dimensions` · `admin.tka.dimensions.index` · `Admin\Tka\TkaAdminController@dimensions` · `admin/tka/dimensions/index.blade.php` (160 baris)

Subjudul: _Kelola dimensi P5_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kirim (ikon) | `POST` | `POST /admin/tka/dimensions/{dimension}/toggle-status` |  |
| Tambah Dimensi | `→` | `admin.tka.dimensions.create` | navigasi (GET /admin/tka/dimensions/create) |
| Kembali ke Dashboard | `→` | `admin.tka.dashboard` | navigasi (GET /admin/tka) |
| (ikon) | `→` | `admin.tka.dimensions.show` | navigasi (GET /admin/tka/dimensions/{dimension}) |
| (ikon) | `→` | `admin.tka.dimensions.edit` | navigasi (GET /admin/tka/dimensions/{dimension}/edit) |

#### Tambah Dimensi TKA
`GET /admin/tka/dimensions/create` · `admin.tka.dimensions.create` · `Admin\Tka\TkaAdminController@createDimension` · `admin/tka/dimensions/create.blade.php` (50 baris)

Subjudul: _Dimensi penilaian baru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Dimensi | `POST` | `POST /admin/tka/dimensions` | kolom: kode_dimensi, nama_dimensi, kategori, deskripsi |
| Kembali | `→` | `admin.tka.dimensions.index` | navigasi (GET /admin/tka/dimensions) |

#### Detail Dimensi TKA - Admin
`GET /admin/tka/dimensions/{dimension}` · `admin.tka.dimensions.show` · `Admin\Tka\TkaAdminController@showDimension` · `admin/tka/dimensions/show.blade.php` (97 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| … | `POST` | `POST /admin/tka/dimensions/{dimension}/toggle-status` |  |
| Edit | `→` | `admin.tka.dimensions.edit` | navigasi (GET /admin/tka/dimensions/{dimension}/edit) |
| Kembali | `→` | `admin.tka.dimensions.index` | navigasi (GET /admin/tka/dimensions) |

#### Edit Dimensi TKA - Admin
`GET /admin/tka/dimensions/{dimension}/edit` · `admin.tka.dimensions.edit` · `Admin\Tka\TkaAdminController@editDimension` · `admin/tka/dimensions/edit.blade.php` (76 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Perubahan | `PUT` | `PUT /admin/tka/dimensions/{dimension}` | kolom: kode_dimensi, nama_dimensi, kategori, deskripsi |
| Kembali | `→` | `admin.tka.dimensions.show` | navigasi (GET /admin/tka/dimensions/{dimension}) |

#### Kelola Early Warning TKA - Admin
`GET /admin/tka/warnings` · `admin.tka.warnings.index` · `Admin\Tka\TkaAdminController@earlyWarnings` · `admin/tka/warnings/index.blade.php` (237 baris)

Subjudul: _Pantau peringatan dini_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Filter | `GET` | `GET /admin/tka/warnings` | kolom: status, tingkat_peringatan, dimension_id, tanggal_from, tanggal_to, search |
| Kirim (ikon) | `POST` | `POST /admin/tka/warnings/{warning}/update-status` | kolom: status |
| Reset | `→` | `admin.tka.warnings.index` | navigasi (GET /admin/tka/warnings) |
| Kembali ke Dashboard | `→` | `admin.tka.dashboard` | navigasi (GET /admin/tka) |
| (ikon) | `→` | `admin.tka.warnings.show` | navigasi (GET /admin/tka/warnings/{warning}) |

#### Detail Early Warning TKA - Admin
`GET /admin/tka/warnings/{warning}` · `admin.tka.warnings.show` · `Admin\Tka\TkaAdminController@showWarning` · `admin/tka/warnings/show.blade.php` (103 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Status | `POST` | `POST /admin/tka/warnings/{warning}/update-status` | kolom: status |
| Kembali | `→` | `admin.tka.warnings.index` | navigasi (GET /admin/tka/warnings) |

### Guru — 42 halaman

#### Analisis Belajar
`GET /guru/analisis` · `guru.analisis.index` · `Guru\AnalisisController@index` · `guru/analisis/index.blade.php` (265 baris)

Subjudul: _Evaluasi mendalam hasil pembelajaran kelas_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Analisis Siswa
`GET /guru/analisis/{siswaId}` · `guru.analisis.detail` · `Guru\AnalisisController@detail` · `guru/analisis/detail.blade.php` (95 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `guru.analisis.index` | navigasi (GET /guru/analisis) |

#### Catatan Siswa
`GET /guru/catatan` · `guru.catatan.index` · `Guru\CatatanController@index` · `guru/catatan/index.blade.php` (104 baris)

Subjudul: _Catatan perkembangan dan perilaku siswa_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan Catatan | `GET` | `GET /guru/catatan` | kolom: siswa_id, tanggal, jenis, catatan |
| 🗑 | `DELETE` | `DELETE /guru/catatan/{param}` | onsubmit: return confirm('Hapus?') |
| (AJAX) | `fetch` | `'{{ route("guru.catatan.store"` | permintaan asinkron |

#### Dashboard Guru
`GET /guru/dashboard` · `guru.dashboard` · `Guru\DashboardController@index` · `guru/dashboard.blade.php` (421 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cetak Laporan | `→` | `guru.laporan.index` | navigasi (GET /guru/laporan) |
| Atur KKM sekarang → | `→` | `guru.pengaturan.index` | navigasi (GET /guru/pengaturan) |
| Isi Nilai | `→` | `guru.nilai-erapor.index` | navigasi (GET /guru/nilai-erapor) |
| Kelola | `→` | `guru.integrasi.index` | navigasi (GET /guru/integrasi) |
| Hubungkan Sheet | `→` | `guru.integrasi.index` | navigasi (GET /guru/integrasi) |
| Telaah | `→` | `guru.analisis.detail` | navigasi (GET /guru/analisis/{siswaId}) |
| Kelola | `→` | `guru.materi.index` | navigasi (GET /guru/materi) |
| … esei perlu diperiksa | `→` | `guru.kuis.index` | navigasi (GET /guru/kuis) |
| Kelola | `→` | `guru.kuis.index` | navigasi (GET /guru/kuis) |
| Masukkan Nilai | `→` | `guru.nilai.create` | navigasi (GET /guru/nilai/create) |
| Isi Kehadiran | `→` | `guru.kehadiran.index` | navigasi (GET /guru/kehadiran) |
| Tambah Materi | `→` | `guru.materi.create` | navigasi (GET /guru/materi/create) |
| Susun Kuis | `→` | `guru.kuis.create` | navigasi (GET /guru/kuis/create) |
| Input Nilai e-Rapor | `→` | `guru.nilai-erapor.index` | navigasi (GET /guru/nilai-erapor) |
| Generate e-Rapor | `→` | `guru.erapor.index` | navigasi (GET /guru/erapor) |
| Analisis Belajar | `→` | `guru.analisis.index` | navigasi (GET /guru/analisis) |
| Kelola Ujian | `→` | `cbt.sessions.index` | navigasi ⚠ route tidak terdaftar |
| Buat Bank Soal | `→` | `cbt.question-banks.create` | navigasi ⚠ route tidak terdaftar |
| Buat Sesi Ujian | `→` | `cbt.sessions.create` | navigasi ⚠ route tidak terdaftar |
| Monitoring | `→` | `cbt.sessions.index` | navigasi ⚠ route tidak terdaftar |

#### Profil Lulusan
`GET /guru/dimensi` · `guru.dimensi.index` · `Guru\DimensiController@index` · `guru/dimensi/index.blade.php` (128 baris)

Subjudul: _Dimensi Profil Pelajar Pancasila_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan Dimensi | `GET` | `GET /guru/dimensi` | kolom: siswa_id, no_dimensi, dimensi, skor |
| 🗑 | `DELETE` | `DELETE /guru/dimensi/{param}` | onsubmit: return confirm('Hapus?') |
| (AJAX) | `fetch` | `'{{ route("guru.dimensi.store"` | permintaan asinkron |

#### Generate e-Rapor
`GET /guru/erapor` · `guru.erapor.index` · `Guru\ErapotGeneratorController@index` · `guru/erapor/index.blade.php` (113 baris)

Subjudul: _Download rapor siswa dalam format PDF atau Excel_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 📥 Generate Semua (PDF) | `JS` | `bulkGenerate()` | tombol client-side |
| 📊 Export Excel | `JS` | `exportExcel()` | tombol client-side |
| 📥 PDF | `→` | `guru.erapor.pdf` | navigasi (GET /guru/erapor/pdf/{siswa}) |

#### ⚙︎ /guru/erapor/excel
`GET /guru/erapor/excel` · `guru.erapor.excel` · `Guru\ErapotGeneratorController@exportExcel`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /guru/erapor/pdf/{siswa}
`GET /guru/erapor/pdf/{siswa}` · `guru.erapor.pdf` · `Guru\ErapotGeneratorController@generatePdf`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Input Nilai
`GET /guru/input-nilai` · `guru.input-nilai.index` · `Guru\InputNilaiController@index` · `guru/input-nilai/index.blade.php` (324 baris)

Subjudul: _Input semua nilai siswa dalam satu halaman_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 🔄 Muat | `JS` | `loadNilai()` | tombol client-side |
| 📋 Paste dari Excel | `JS` | `bukaPaste()` | tombol client-side |
| 🔢 Isi Semua | `JS` | `isiSemua()` | tombol client-side |
| 💾 Simpan Semua | `JS` | `simpanSemua()` | tombol client-side |
| × | `JS` | `tutupPaste()` | tombol client-side |
| Batal | `JS` | `tutupPaste()` | tombol client-side |
| ✅ Terapkan | `JS` | `applyPaste()` | tombol client-side |
| × | `JS` | `document.getElementById('modalIsiSemua')…` | tombol client-side |
| Batal | `JS` | `document.getElementById('modalIsiSemua')…` | tombol client-side |
| ✅ Terapkan | `JS` | `applyIsiSemua()` | tombol client-side |
| (AJAX) | `fetch` | `/guru/input-nilai/data?jenis=${jenis}&judul=${encodeUR… | permintaan asinkron |
| (AJAX) | `fetch` | `'/guru/input-nilai/simpan'` | permintaan asinkron |
| (AJAX) | `fetch` | `'/guru/input-nilai/paste'` | permintaan asinkron |

#### ⚙︎ /guru/input-nilai/data
`GET /guru/input-nilai/data` · `guru.input-nilai.data` · `Guru\InputNilaiController@getNilai`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Google Sheet Integration
`GET /guru/integrasi` · `guru.integrasi.index` · `Guru\IntegrasiController@index` · `guru/integrasi/index.blade.php` (227 baris)

Subjudul: _Hubungkan data SIMANTAP dengan Google Spreadsheet_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Putuskan | `POST` | `POST /guru/integrasi/disconnect` | onsubmit: return confirm('Putuskan koneksi ke Goog… |
| Sync Semua Data | `POST` | `POST /guru/integrasi/full-sync` |  |
| Hubungkan Google Sheet | `POST` | `POST /guru/integrasi/connect` | kolom: spreadsheet_url, sheet_name |
| Simpan Pengaturan | `POST` | `POST /guru/integrasi/update-settings` | kolom: sinkron_nilai, sinkron_kehadiran, sinkron_kebiasaan, sinkron_dimensi |

#### 7 Kebiasaan dari Orang Tua
`GET /guru/kebiasaan` · `guru.kebiasaan.index` · `Guru\GuruKebiasaanController@index` · `guru/kebiasaan/index.blade.php` (198 baris)

Subjudul: _Laporan pembiasaan siswa dari rumah_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Riwayat (per baris) | `→` | `guru.kebiasaan.show` | navigasi (GET /guru/kebiasaan/{id}) — **ditambahkan**, sebelumnya halaman detail tidak tertaut |
| Filter Tanggal | `GET` | `GET /guru/kebiasaan` | kolom: tanggal (diarahkan lewat `onchange`) |

#### Detail Kebiasaan
`GET /guru/kebiasaan/{id}` · `guru.kebiasaan.show` · `Guru\GuruKebiasaanController@show` · `guru/kebiasaan/show.blade.php` (81 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `guru.kebiasaan.index` | navigasi (GET /guru/kebiasaan) |

#### Kehadiran
`GET /guru/kehadiran` · `guru.kehadiran.index` · `Guru\KehadiranController@index` · `guru/kehadiran/index.blade.php` (99 baris)

Subjudul: _Catat kehadiran melalui jadwal pelajaran yang menjadi tugas Anda_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 🗑 | `DELETE` | `DELETE /guru/kehadiran/{param}` | onsubmit: return confirm('Hapus?') |
| Presensi Masuk / Pulang Saya | `→` | `presensi.pegawai.index` | navigasi (GET /presensi/pegawai) |
| Buka absensi & scan QR | `→` | `guru.kehadiran.jadwal` | navigasi (GET /guru/kehadiran/jadwal/{jadwal}) |

#### Absensi Kelas
`GET /guru/kehadiran/jadwal/{jadwal}` · `guru.kehadiran.jadwal` · `Guru\KehadiranController@jadwal` · `guru/kehadiran/jadwal.blade.php` (118 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan harian | `POST` | `POST /guru/kehadiran/jadwal/{jadwal}/siswa/{siswa}/status` | kolom: cakupan, status, keterangan |
| Simpan mapel | `POST` | `POST /guru/kehadiran/jadwal/{jadwal}/siswa/{siswa}/status` | kolom: cakupan, status, keterangan |
| ← Kembali ke Kehadiran | `→` | `guru.kehadiran.index` | navigasi (GET /guru/kehadiran) |

#### Kuis & Soal
`GET /guru/kuis` · `guru.kuis.index` · `Guru\KuisController@index` · `guru/kuis/index.blade.php` (76 baris)

Subjudul: _Kelola asesmen pembelajaran_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| … | `POST` | `POST /guru/kuis/{kuis}/toggle` |  |
| + Buat kuis | `→` | `guru.kuis.create` | navigasi (GET /guru/kuis/create) |
| Hasil | `→` | `guru.kuis.hasil` | navigasi (GET /guru/kuis/{kuis}/hasil) |
| Ubah | `→` | `guru.kuis.edit` | navigasi (GET /guru/kuis/{param}/edit) |

#### Buat Kuis
`GET /guru/kuis/create` · `guru.kuis.create` · `Guru\KuisController@create` · `guru/kuis/create.blade.php` (102 baris)

Subjudul: _Susun asesmen baru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ðŸ’¾ Simpan Kuis | `POST` | `POST /guru/kuis` | kolom: judul, tanggal, mata_pelajaran, kelas, jenis, kkm, mode, sumber, batas_waktu, cara_nilai, tautan |
| â† Kembali | `→` | `guru.kuis.index` | navigasi (GET /guru/kuis) |
| Batal | `→` | `guru.kuis.index` | navigasi (GET /guru/kuis) |

#### Hasil Kuis
`GET /guru/kuis/{kuis}/hasil` · `guru.kuis.hasil` · `Guru\KuisController@hasil` · `guru/kuis/hasil.blade.php` (106 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `guru.kuis.index` | navigasi (GET /guru/kuis) |

#### Ubah Kuis
`GET /guru/kuis/{param}/edit` · `guru.kuis.edit` · `Guru\KuisController@edit` · `guru/kuis/edit.blade.php` (99 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan | `PUT` | `PUT /guru/kuis/{param}` | kolom: judul, tanggal, mata_pelajaran, kelas, jenis, kkm, mode, sumber, batas_waktu, cara_nilai, tautan |
| ← Kembali | `→` | `guru.kuis.index` | navigasi (GET /guru/kuis) |
| Batal | `→` | `guru.kuis.index` | navigasi (GET /guru/kuis) |

#### Laporan & Rapor
`GET /guru/laporan` · `guru.laporan.index` · `Guru\LaporanController@index` · `guru/laporan/index.blade.php` (348 baris)

Subjudul: _Rekap nilai seluruh siswa_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cari / Filter (ikon) | `GET` | `GET /guru/laporan` | kolom: kelas, semester_id, mata_pelajaran |
| Cetak | `JS` | `window.print()` | tombol client-side |
| Download PDF | `→` | `guru.laporan.pdf` | navigasi (GET /guru/laporan/pdf) |
| Reset | `→` | `guru.laporan.index` | navigasi (GET /guru/laporan) |

#### ⚙︎ /guru/laporan/pdf
`GET /guru/laporan/pdf` · `guru.laporan.pdf` · `Guru\LaporanController@pdf`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Materi Ajar
`GET /guru/materi` · `guru.materi.index` · `Guru\MateriController@index` · `guru/materi/index.blade.php` (95 baris)

Subjudul: _Kelola materi pembelajaran mendalam_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Hapus | `JS` | `hapusMateri()` | tombol client-side |
| + Tambah materi | `→` | `guru.materi.create` | navigasi (GET /guru/materi/create) |
| Ubah | `→` | `guru.materi.edit` | navigasi (GET /guru/materi/{param}/edit) |
| (AJAX) | `fetch` | `/guru/materi/${id}` | permintaan asinkron |

#### Tambah Materi
`GET /guru/materi/create` · `guru.materi.create` · `Guru\MateriController@create` · `guru/materi/create.blade.php` (91 baris)

Subjudul: _Buat materi pembelajaran baru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan Materi | `POST` | `POST /guru/materi` | kolom: judul, tanggal, mata_pelajaran, kelas, tautan, tujuan, konten, pm[], pm[], pm[], dimensi[] |
| ← Kembali | `→` | `guru.materi.index` | navigasi (GET /guru/materi) |
| Batal | `→` | `guru.materi.index` | navigasi (GET /guru/materi) |

#### Show
`GET /guru/materi/{param}` · `guru.materi.show` · `Guru\MateriController@show` · `guru/materi/show.blade.php` (35 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `guru.materi.index` | navigasi (GET /guru/materi) |
| Ubah | `→` | `guru.materi.edit` | navigasi (GET /guru/materi/{param}/edit) |

#### Ubah Materi
`GET /guru/materi/{param}/edit` · `guru.materi.edit` · `Guru\MateriController@edit` · `guru/materi/edit.blade.php` (68 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan | `PUT` | `PUT /guru/materi/{param}` | kolom: judul, tanggal, mata_pelajaran, kelas, tautan, tujuan, konten |
| ← Kembali | `→` | `guru.materi.index` | navigasi (GET /guru/materi) |
| Batal | `→` | `guru.materi.index` | navigasi (GET /guru/materi) |

#### Daftar Nilai
`GET /guru/nilai` · `guru.nilai.index` · `Guru\NilaiController@index` · `guru/nilai/index.blade.php` (131 baris)

Subjudul: _Semua penilaian yang sudah diinput_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan Nilai | `GET` | `GET /guru/nilai` | kolom: siswa_id, jenis, tanggal, judul_penilaian, mata_pelajaran, nilai |
| 🗑 | `DELETE` | `DELETE /guru/nilai/{param}` | onsubmit: return confirm('Hapus?') |
| ✏️ | `→` | `guru.nilai.edit` | navigasi (GET /guru/nilai/{param}/edit) |
| (AJAX) | `fetch` | `'{{ route("guru.nilai.store"` | permintaan asinkron |

#### Input Nilai e-Rapor
`GET /guru/nilai-erapor` · `guru.nilai-erapor.index` · `Guru\NilaiErapotController@index` · `guru/nilai-erapor/index.blade.php` (339 baris)

Subjudul: _Input nilai formatif, sumatif, dan sumatif akhir_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Batal / 📤 Import | `GET` | `GET /guru/nilai-erapor` | kolom: file |
| 📥 Template | `JS` | `downloadTemplate()` | tombol client-side |
| 📊 Export | `JS` | `exportNilai()` | tombol client-side |
| 💾 Simpan Semua | `JS` | `simpanSemua()` | tombol client-side |
| 👁 | `JS` | `lihatDeskripsi()` | tombol client-side |
| × | `JS` | `document.getElementById('modalDeskripsi'…` | tombol client-side |
| Tutup | `JS` | `document.getElementById('modalDeskripsi'…` | tombol client-side |
| × | `JS` | `document.getElementById('modalImport').s…` | tombol client-side |
| Batal | `JS` | `document.getElementById('modalImport').s…` | tombol client-side |
| (AJAX) | `fetch` | `'{{ route("guru.nilai-erapor.auto-save"` | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ route("guru.nilai-erapor.import"` | permintaan asinkron |
| (AJAX) | `fetch` | `/guru/nilai-erapor/deskripsi/${sid}?mapel=${m}&semeste… | permintaan asinkron |
| (AJAX) | `fetch` | `'{{ route("guru.nilai-erapor.simpan"` | permintaan asinkron |

#### ⚙︎ /guru/nilai-erapor/deskripsi/{siswaId}
`GET /guru/nilai-erapor/deskripsi/{siswaId}` · `guru.nilai-erapor.deskripsi` · `Guru\NilaiErapotController@deskripsi`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /guru/nilai-erapor/export
`GET /guru/nilai-erapor/export` · `guru.nilai-erapor.export` · `Guru\ImportErapotController@export`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /guru/nilai-erapor/template
`GET /guru/nilai-erapor/template` · `guru.nilai-erapor.template` · `Guru\ImportErapotController@downloadTemplate`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Input Nilai Baru
`GET /guru/nilai/create` · `guru.nilai.create` · `Guru\NilaiController@create` · `guru/nilai/create.blade.php` (62 baris)

Subjudul: _Tambahkan penilaian baru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ðŸ’¾ Simpan Nilai | `POST` | `POST /guru/nilai` | kolom: siswa_id, jenis, tanggal, judul_penilaian, mata_pelajaran, nilai |
| â† Kembali | `→` | `guru.nilai.index` | navigasi (GET /guru/nilai) |
| Batal | `→` | `guru.nilai.index` | navigasi (GET /guru/nilai) |

#### Edit Nilai
`GET /guru/nilai/{param}/edit` · `guru.nilai.edit` · `Guru\NilaiController@edit` · `guru/nilai/edit.blade.php` (60 baris)

Subjudul: _Ubah data penilaian_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Update Nilai | `PUT` | `PUT /guru/nilai/{param}` | kolom: siswa_id, jenis, tanggal, judul_penilaian, mata_pelajaran, nilai |
| ← Kembali | `→` | `guru.nilai.index` | navigasi (GET /guru/nilai) |
| Batal | `→` | `guru.nilai.index` | navigasi (GET /guru/nilai) |

#### Pengaturan
`GET /guru/pengaturan` · `guru.pengaturan.index` · `Guru\PengaturanController@index` · `guru/pengaturan/index.blade.php` (56 baris)

Subjudul: _Atur kelas dan pengaturan pembelajaran_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan Pengaturan | `POST` | `POST /guru/pengaturan` | kolom: mata_pelajaran, kelas, kkm, semester, tahun_pelajaran, fase |

#### Data Siswa
`GET /guru/siswa` · `guru.siswa.index` · `Guru\SiswaController@index` · `guru/siswa/index.blade.php` (143 baris)

Subjudul: _Kelola peserta didik di kelas Anda_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Impor / Batal | `POST` | `POST /guru/siswa/impor` | kolom: csv |
| Impor CSV | `JS` | `document.getElementById('imporForm').sty…` | tombol client-side |
| Batal | `JS` | `document.getElementById('imporForm').sty…` | tombol client-side |
| Hapus | `JS` | `hapusSiswa()` | tombol client-side |
| Ambil dari Kelas Lain | `→` | `guru.siswa.pungut` | navigasi (GET /guru/siswa/pungut) |
| Unduh CSV | `→` | `guru.siswa.unduh` | navigasi (GET /guru/siswa/unduh/csv) |
| Tambah Siswa | `→` | `guru.siswa.create` | navigasi (GET /guru/siswa/create) |
| Ubah | `→` | `guru.siswa.edit` | navigasi (GET /guru/siswa/{param}/edit) |
| (AJAX) | `fetch` | `/guru/siswa/${id}` | permintaan asinkron |

#### Tambah Siswa
`GET /guru/siswa/create` · `guru.siswa.create` · `Guru\SiswaController@create` · `guru/siswa/create.blade.php` (63 baris)

Subjudul: _Tambahkan peserta didik baru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan | `POST` | `POST /guru/siswa` | kolom: nama, nis, jenis_kelamin, kelas, nama_orang_tua |
| ← Kembali | `→` | `guru.siswa.index` | navigasi (GET /guru/siswa) |
| Batal | `→` | `guru.siswa.index` | navigasi (GET /guru/siswa) |

#### Ambil dari Kelas Lain
`GET /guru/siswa/pungut` · `guru.siswa.pungut` · `Guru\SiswaController@pungut` · `guru/siswa/pungut.blade.php` (156 baris)

Subjudul: _Pungut siswa dari kelas wali lain (tanpa ketik ulang)_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Cari | `JS` | `loadSiswa()` | tombol client-side |
| Centang Semua | `JS` | `centangSemua()` | tombol client-side |
| Batal Semua | `JS` | `batalSemua()` | tombol client-side |
| Ambil yang Dipilih | `JS` | `simpanPungut()` | tombol client-side |
| Kembali | `→` | `guru.siswa.index` | navigasi (GET /guru/siswa) |
| (AJAX) | `fetch` | `'{{ route("guru.siswa.pungut.kelas"` | permintaan asinkron |

#### ⚙︎ /guru/siswa/pungut/kelas
`GET /guru/siswa/pungut/kelas` · `guru.siswa.pungut.kelas` · `Guru\SiswaController@getKelasSiswa`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### ⚙︎ /guru/siswa/unduh/csv
`GET /guru/siswa/unduh/csv` · `guru.siswa.unduh` · `Guru\SiswaController@unduh`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Ubah Siswa
`GET /guru/siswa/{param}/edit` · `guru.siswa.edit` · `Guru\SiswaController@edit` · `guru/siswa/edit.blade.php` (61 baris)

Subjudul: _Perbarui data_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan | `PUT` | `PUT /guru/siswa/{param}` | kolom: nama, nis, jenis_kelamin, kelas, nama_orang_tua |
| ← Kembali | `→` | `guru.siswa.index` | navigasi (GET /guru/siswa) |
| Batal | `→` | `guru.siswa.index` | navigasi (GET /guru/siswa) |

#### Kesiapan TKA
`GET /guru/tka` · `guru.tka.index` · `Guru\TKAController@index` · `guru/tka/index.blade.php` (252 baris)

Subjudul: _Analisis kesiapan siswa menghadapi Asesmen Nasional_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Analisis Kesiapan | `→` | `guru.tka.analysis` | navigasi (GET /guru/tka/analisis) — **ditambahkan**, sebelumnya halaman ini tidak tertaut dari mana pun |
| Detail (per baris) | `→` | `guru.tka.show` | navigasi (GET /guru/tka/{id}) — **ditambahkan** |
| Skor dimensi (per baris) | `→` | `guru.dimensi.index` | navigasi (GET /guru/dimensi?siswa_id={id}) |

#### Analisis TKA
`GET /guru/tka/analisis` · `guru.tka.analysis` · `Guru\TKAController@analysis` · `guru/tka/analysis.blade.php` (79 baris)

Subjudul: _Analisis dimensi profil lulusan per siswa_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `guru.tka.index` | navigasi (GET /guru/tka) |

#### Detail Dimensi
`GET /guru/tka/{id}` · `guru.tka.show` · `Guru\TKAController@show` · `guru/tka/show.blade.php` (43 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `guru.tka.index` | navigasi (GET /guru/tka) |

### Tryout (guru & siswa) — 12 halaman

#### Paket Tryout
`GET /tryout/packages` · `tryout.packages.index` · `Tryout\TryoutPackageController@index` · `tryout/packages/index.blade.php` (72 baris)

Subjudul: _Kelola paket soal tryout_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tambah Paket | `→` | `tryout.packages.create` | navigasi (GET /tryout/packages/create) |
| Detail (per baris) | `→` | `tryout.packages.show` | navigasi (GET /tryout/packages/{param}) — **ditambahkan** |
| Ubah (per baris) | `→` | `tryout.packages.edit` | navigasi (GET /tryout/packages/{param}/edit) — **ditambahkan** |
| Hapus (per baris) | `DELETE` | `DELETE /tryout/packages/{param}` | konfirmasi `confirm()` — **ditambahkan** |

#### Tambah Paket Tryout
`GET /tryout/packages/create` · `tryout.packages.create` · `Tryout\TryoutPackageController@create` · `tryout/packages/create.blade.php` (125 baris)

Subjudul: _Paket soal beserta mata pelajarannya_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Hapus / + Tambah Mapel / Simpan Paket | `POST` | `POST /tryout/packages` | kolom: category_id, jenjang, kode_paket, nama_paket, tahun_paket, durasi_total_menit, passing_grade, deskripsi… |
| Hapus | `JS` | `this.closest('.subject-row').remove()` | tombol client-side |
| Hapus | `JS` | `this.closest(\'.subject-row\').remove()` | tombol client-side |
| Kembali | `→` | `tryout.packages.index` | navigasi (GET /tryout/packages) |

#### Detail Paket Tryout
`GET /tryout/packages/{param}` · `tryout.packages.show` · `Tryout\TryoutPackageController@show` · `tryout/packages/show.blade.php` (104 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Edit | `→` | `tryout.packages.edit` | navigasi (GET /tryout/packages/{param}/edit) |
| Kembali | `→` | `tryout.packages.index` | navigasi (GET /tryout/packages) |

#### Edit Paket Tryout
`GET /tryout/packages/{param}/edit` · `tryout.packages.edit` · `Tryout\TryoutPackageController@edit` · `tryout/packages/edit.blade.php` (137 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Perubahan | `PUT` | `PUT /tryout/packages/{param}` | kolom: category_id, jenjang, kode_paket, nama_paket, tahun_paket, durasi_total_menit, passing_grade, deskripsi… |
| Kembali | `→` | `tryout.packages.show` | navigasi (GET /tryout/packages/{param}) |

#### Sesi Tryout
`GET /tryout/sessions` · `tryout.sessions.index` · `Tryout\TryoutSessionController@index` · `tryout/sessions/index.blade.php` (53 baris)

Subjudul: _Jadwal pelaksanaan tryout_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Buat Sesi | `→` | `tryout.sessions.create` | navigasi (GET /tryout/sessions/create) |

#### Buat Sesi Tryout
`GET /tryout/sessions/create` · `tryout.sessions.create` · `Tryout\TryoutSessionController@create` · `tryout/sessions/create.blade.php` (86 baris)

Subjudul: _Jadwal pelaksanaan tryout_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Sesi | `POST` | `POST /tryout/sessions` | kolom: package_id, semester_id, kelas, nama_sesi, rombel_id, tanggal_mulai, tanggal_selesai |
| Kembali | `→` | `tryout.sessions.index` | navigasi (GET /tryout/sessions) |

#### Detail Sesi Tryout
`GET /tryout/sessions/{session}` · `tryout.sessions.show` · `Tryout\TryoutSessionController@show` · `tryout/sessions/show.blade.php` (177 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Publikasikan | `POST` | `POST /tryout/sessions/{session}/publish` |  |
| Mulai Sesi | `POST` | `POST /tryout/sessions/{session}/start` |  |
| Selesaikan Sesi | `POST` | `POST /tryout/sessions/{session}/complete` | onsubmit: return confirm('Yakin ingin menyelesaika… |
| Monitoring | `→` | `tryout.sessions.monitoring` | navigasi (GET /tryout/sessions/{session}/monitoring) |
| Kembali | `→` | `tryout.sessions.index` | navigasi (GET /tryout/sessions) |

#### ⚙︎ /tryout/sessions/{session}/api/monitoring
`GET /tryout/sessions/{session}/api/monitoring` · `tryout.sessions.api.monitoring` · `Tryout\TryoutSessionController@apiMonitoring`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Monitoring Sesi Tryout
`GET /tryout/sessions/{session}/monitoring` · `tryout.sessions.monitoring` · `Tryout\TryoutSessionController@monitoring` · `tryout/sessions/monitoring.blade.php` (164 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali ke Sesi | `→` | `tryout.sessions.show` | navigasi (GET /tryout/sessions/{session}) |

#### Tryout - Dashboard Siswa
`GET /tryout/student` · `tryout.student.index` · `Tryout\TryoutStudentController@index` · `tryout/student/index.blade.php` (156 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Mulai Tryout (per sesi) | `→` | `tryout.student.start` | navigasi (GET /tryout/student/{session}/start) + `confirm()` durasi; sekarang dibangun dengan `route()` di Blade (sebelumnya `$session['mulai_url']` dari service) |
| Detail (per hasil) | `→` | `tryout.student.result` | navigasi (GET /tryout/student/{result}/result); baris tanpa id lawas tidak ditautkan |

#### Hasil Tryout
`GET /tryout/student/{result}/result` · `tryout.student.result` · `Tryout\TryoutStudentController@result` · `tryout/student/result.blade.php` (218 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali | `→` | `tryout.student.index` | navigasi (GET /tryout/student) |

#### Mengerjakan Tryout
`GET /tryout/student/{session}/start` · `tryout.student.start` · `Tryout\TryoutStudentController@start` · `tryout/student/take.blade.php` (268 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Selesaikan Tryout | `POST` | `POST /tryout/student/{result}/complete` | onsubmit: return confirm('Yakin ingin menyelesaika… |

### TKA (guru & orang tua) — 12 halaman

#### Tka Statistik
`GET /tka-statistik` · `tka.statistik` · `TkaController@index` · `landing/tka-statistik.blade.php` (218 baris)

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Penilaian TKA
`GET /tka/assessments` · `tka.assessments.index` · `Tka\TkaAssessmentController@index` · `tka/assessments/index.blade.php` (60 baris)

Subjudul: _Hasil asesmen kesiapan belajar siswa_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Nilai Siswa | `→` | `tka.assessments.create` | navigasi (GET /tka/assessments/create) |
| Detail (per baris) | `→` | `tka.assessments.show` | navigasi (GET /tka/assessments/{param}) — **ditambahkan** |
| Riwayat (per baris) | `→` | `tka.assessments.by-siswa` | navigasi (GET /tka/assessments/siswa/{siswaId}) — **ditambahkan** |

#### Penilaian TKA - Tambah
`GET /tka/assessments/create` · `tka.assessments.create` · `Tka\TkaAssessmentController@create` · `tka/assessments/create.blade.php` (220 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Penilaian | `POST` | `POST /tka/assessments` | kolom: siswa_id, semester_id, tanggal_penilaian |
| Batal | `→` | `tka.assessments.index` | navigasi (GET /tka/assessments) |

#### Riwayat Penilaian TKA Siswa
`GET /tka/assessments/siswa/{siswaId}` · `tka.assessments.by-siswa` · `Tka\TkaAssessmentController@bySiswa` · `tka/assessments/by-siswa.blade.php` (102 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali | `→` | `tka.assessments.index` | navigasi (GET /tka/assessments) |
| Detail | `→` | `tka.assessments.show` | navigasi (GET /tka/assessments/{assessment}) |

#### Detail Penilaian TKA
`GET /tka/assessments/{assessment}` · `tka.assessments.show` · `Tka\TkaAssessmentController@show` · `tka/assessments/show.blade.php` (166 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Riwayat Siswa | `→` | `tka.assessments.by-siswa` | navigasi (GET /tka/assessments/siswa/{siswaId}) |
| Kembali | `→` | `tka.assessments.index` | navigasi (GET /tka/assessments) |

#### Dimensi TKA
`GET /tka/dimensions` · `tka.dimensions.index` · `Tka\TkaDimensionController@index` · `tka/dimensions/index.blade.php` (71 baris)

Subjudul: _Dimensi penilaian kesiapan belajar_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Tambah Dimensi | `→` | `tka.dimensions.create` | navigasi (GET /tka/dimensions/create) |
| Detail (per baris) | `→` | `tka.dimensions.show` | navigasi (GET /tka/dimensions/{param}) — **ditambahkan** |
| Ubah (per baris) | `→` | `tka.dimensions.edit` | navigasi (GET /tka/dimensions/{param}/edit) — **ditambahkan** |
| Hapus (per baris) | `DELETE` | `DELETE /tka/dimensions/{param}` | konfirmasi `confirm()` — **ditambahkan** |

#### Tambah Dimensi TKA
`GET /tka/dimensions/create` · `tka.dimensions.create` · `Tka\TkaDimensionController@create` · `tka/dimensions/create.blade.php` (105 baris)

Subjudul: _Dimensi penilaian beserta rubriknya_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Hapus / + Tambah Baris / Simpan Dimensi | `POST` | `POST /tka/dimensions` | kolom: semester_id, kode_dimensi, nama_dimensi, kategori, bobot, threshold_siap, deskripsi, rubrik_penilaian[0… |
| Hapus | `JS` | `this.closest('.rubrik-row').remove()` | tombol client-side |
| Hapus | `JS` | `this.closest(\'.rubrik-row\').remove()` | tombol client-side |
| Kembali | `→` | `tka.dimensions.index` | navigasi (GET /tka/dimensions) |

#### ⚙︎ /tka/dimensions/{dimension}/api/items
`GET /tka/dimensions/{dimension}/api/items` · `tka.dimensions.api.items` · `Tka\TkaDimensionController@getItems`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Detail Dimensi TKA
`GET /tka/dimensions/{param}` · `tka.dimensions.show` · `Tka\TkaDimensionController@show` · `tka/dimensions/show.blade.php` (131 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Edit | `→` | `tka.dimensions.edit` | navigasi (GET /tka/dimensions/{param}/edit) |
| Kembali | `→` | `tka.dimensions.index` | navigasi (GET /tka/dimensions) |

#### Edit Dimensi TKA
`GET /tka/dimensions/{param}/edit` · `tka.dimensions.edit` · `Tka\TkaDimensionController@edit` · `tka/dimensions/edit.blade.php` (131 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Hapus / + Tambah Baris / Simpan Perubahan | `PUT` | `PUT /tka/dimensions/{param}` | kolom: semester_id, kode_dimensi, nama_dimensi, kategori, bobot, threshold_siap, deskripsi, rubrik_penilaian[{… |
| Hapus | `JS` | `this.closest('.rubrik-row').remove()` | tombol client-side |
| Hapus | `JS` | `this.closest(\'.rubrik-row\').remove()` | tombol client-side |
| Kembali | `→` | `tka.dimensions.show` | navigasi (GET /tka/dimensions/{param}) |

#### Laporan TKA Anak
`GET /tka/parent` · `tka.parent.index` · `Tka\TkaParentController@index` · `tka/parent/index.blade.php` (61 baris)

Subjudul: _Hasil asesmen TKA anak Anda_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Lihat | `→` | `tka.parent.show` | navigasi (GET /tka/parent/{report}) |

#### Laporan TKA untuk Orang Tua
`GET /tka/parent/{report}` · `tka.parent.show` · `Tka\TkaParentController@show` · `tka/parent/show.blade.php` (183 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali | `→` | `tka.parent.index` | navigasi (GET /tka/parent) |

### Siswa — 9 halaman

#### Dashboard Siswa
`GET /siswa/dashboard` · `siswa.dashboard` · `Siswa\DashboardController@index` · `siswa/dashboard.blade.php` (331 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Lihat | `→` | `siswa.materi.show` | navigasi (GET /siswa/materi/{id}) |
| Lihat | `→` | `siswa.kuis.show` | navigasi (GET /siswa/kuis/{kuis}) |
| Kerjakan | `→` | `siswa.kuis.show` | navigasi (GET /siswa/kuis/{kuis}) |
| Lihat Profil | `→` | `siswa.profil.show` | navigasi (GET /siswa/profil) |
| Lihat Semua | `→` | `cbt.student.index` | navigasi ⚠ route tidak terdaftar |
| Mulai | `→` | `cbt.student.start` | navigasi ⚠ route tidak terdaftar |

#### Daftar Kuis
`GET /siswa/kuis` · `siswa.kuis.index` · `Siswa\KuisController@index` · `siswa/kuis/index.blade.php` (135 baris)

Subjudul: _Kerjakan kuis dari guru Anda_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kerjakan | `→` | `siswa.kuis.show` | navigasi (GET /siswa/kuis/{kuis}) |

#### Kerjakan Kuis
`GET /siswa/kuis/{kuis}` · `siswa.kuis.show` · `Siswa\KuisController@show` · `siswa/kuis/show.blade.php` (324 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Sebelumnya / Selanjutnya / Kirim Jawaban | `POST` | `POST /siswa/kuis/{kuis}/submit` | kolom: durasi, start_time, soal_aktif, jawaban[{{ $i }}], jawaban[{{ $i }}]; ada konfirmasi |
| ← Kembali | `→` | `siswa.kuis.index` | navigasi (GET /siswa/kuis) |

#### Materi Pembelajaran
`GET /siswa/materi` · `siswa.materi.index` · `Siswa\MateriController@index` · `siswa/materi/index.blade.php` (86 baris)

Subjudul: _Daftar materi dari guru Anda_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Baca Materi | `→` | `siswa.materi.show` | navigasi (GET /siswa/materi/{id}) |

#### Show
`GET /siswa/materi/{id}` · `siswa.materi.show` · `Siswa\MateriController@show` · `siswa/materi/show.blade.php` (102 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `siswa.materi.index` | navigasi (GET /siswa/materi) |

#### Nilai Saya
`GET /siswa/nilai` · `siswa.nilai.index` · `Siswa\NilaiController@index` · `siswa/nilai/index.blade.php` (245 baris)

Subjudul: _Rekapitulasi penilaian_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Detail Nilai
`GET /siswa/nilai/{jenis}` · `siswa.nilai.detail` · `Siswa\NilaiController@detail` · `siswa/nilai/detail.blade.php` (89 baris)

Subjudul: _Rincian penilaian_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `siswa.nilai.index` | navigasi (GET /siswa/nilai) |

#### Profil Saya
`GET /siswa/profil` · `siswa.profil.show` · `Siswa\ProfilController@show` · `siswa/profil/show.blade.php` (135 baris)

Subjudul: _Data lengkap dari Dapodik_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Edit Profil | `→` | `siswa.profil.edit` | navigasi (GET /siswa/profil/edit) |
| Kembali ke Beranda | `→` | `siswa.dashboard` | navigasi (GET /siswa/dashboard) |

#### Edit Profil
`GET /siswa/profil/edit` · `siswa.profil.edit` · `Siswa\ProfilController@edit` · `siswa/profil/edit.blade.php` (132 baris)

Subjudul: _Perbarui data profil Anda_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Simpan Perubahan | `PUT` | `PUT /siswa/profil` | kolom: telepon, alamat, foto |
| Batal | `→` | `siswa.profil.show` | navigasi (GET /siswa/profil) |

### Orang tua — 7 halaman

#### Catatan Guru
`GET /ortu/catatan` · `ortu.catatan.index` · `Ortu\CatatanController@index` · `ortu/catatan/index.blade.php` (123 baris)

Subjudul: _Catatan perkembangan dari guru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Semua … | `JS` | `filterCatatan()` | tombol client-side |
| Apresiasi … | `JS` | `filterCatatan()` | tombol client-side |
| Perhatian … | `JS` | `filterCatatan()` | tombol client-side |
| Umum … | `JS` | `filterCatatan()` | tombol client-side |

#### Dashboard Orang Tua
`GET /ortu/dashboard` · `ortu.dashboard` · `Ortu\DashboardController@index` · `ortu/dashboard.blade.php` (157 baris)

Subjudul: _Pantau perkembangan anak_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Isi 7 Kebiasaan | `→` | `ortu.kebiasaan.index` | navigasi (GET /ortu/kebiasaan) |
| Lihat Perkembangan Nilai | `→` | `ortu.nilai.index` | navigasi (GET /ortu/nilai) |
| Detail | `→` | `ortu.nilai.index` | navigasi (GET /ortu/nilai) |

#### 7 Kebiasaan - Orang Tua
`GET /ortu/kebiasaan` · `ortu.kebiasaan.index` · `Ortu\KebiasaanController@index` · `ortu/kebiasaan/index.blade.php` (118 baris)

Subjudul: _Laporan harian dari rumah_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 💾 Simpan Laporan | `POST` | `POST /ortu/kebiasaan` | kolom: siswa_id, tanggal, {{ $field }}, catatan |

#### Kehadiran Anak
`GET /ortu/kehadiran` · `ortu.kehadiran.index` · `Ortu\KehadiranController@index` · `ortu/kehadiran/index.blade.php` (222 baris)

Subjudul: _Rekap kehadiran di sekolah_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Laporan / Rapor
`GET /ortu/laporan` · `ortu.laporan.index` · `Ortu\LaporanController@index` · `ortu/laporan/index.blade.php` (258 baris)

Subjudul: _Laporan perkembangan lengkap anak_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 🖨️ Cetak | `JS` | `window.print()` | tombol client-side |

#### Perkembangan Nilai
`GET /ortu/nilai` · `ortu.nilai.index` · `DashboardController@ortuNilai` · `ortu/nilai/index.blade.php` (21 baris)

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Rekap 7 Kebiasaan
`GET /ortu/rekap` · `ortu.rekap.index` · `Ortu\RekapController@index` · `ortu/rekap/index.blade.php` (229 baris)

Subjudul: _Rangkuman perkembangan kebiasaan anak_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

### Kepala sekolah — 10 halaman

#### Pantau Aktivitas
`GET /kepsek/aktivitas` · `kepsek.aktivitas.index` · `Kepsek\AktivitasController@index` · `kepsek/aktivitas/index.blade.php` (301 baris)

Subjudul: _Log aktivitas dan statistik penggunaan sistem_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Dashboard Kepala Sekolah
`GET /kepsek/dashboard` · `kepsek.dashboard` · `Kepsek\DashboardController@index` · `kepsek/dashboard.blade.php` (197 baris)

Subjudul: _Monitoring aktivitas sekolah secara real-time_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 📊 Rekap Lengkap | `→` | `kepsek.rekap.index` | navigasi (GET /kepsek/rekap) |
| 🎓 Hasil Belajar | `→` | `kepsek.hasil-belajar.index` | navigasi (GET /kepsek/hasil-belajar) |
| 📡 Pantau Aktivitas | `→` | `kepsek.pantau.index` | navigasi (GET /kepsek/pantau) |
| 🏫 Peta Kelas | `→` | `kepsek.peta-kelas.index` | navigasi (GET /kepsek/peta-kelas) |
| Detail Ujian | `→` | `admin.cbt.sessions.index` | navigasi (GET /admin/cbt/sessions) |

#### Hasil Belajar
`GET /kepsek/hasil-belajar` · `kepsek.hasil-belajar.index` · `Kepsek\HasilBelajarController@index` · `kepsek/hasil-belajar/index.blade.php` (272 baris)

Subjudul: _Analisis lintas kelas dan perbandingan capaian_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Rekap Kebiasaan
`GET /kepsek/kebiasaan` · `kepsek.kebiasaan.index` · `Kepsek\KebiasaanController@index` · `kepsek/kebiasaan/index.blade.php` (251 baris)

Subjudul: _Partisipasi kebiasaan seluruh siswa_

_Halaman baca-saja — tidak ada tombol, form, atau tautan aksi._

#### Pantau Aktivitas Guru
`GET /kepsek/pantau` · `kepsek.pantau.index` · `Kepsek\DashboardController@pantau` · `kepsek/pantau/index.blade.php` (104 baris)

Subjudul: _Monitoring aktivitas guru secara real-time_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| 🔍 Filter | `GET` | `GET /kepsek/pantau` | kolom: guru_id, tanggal |

#### Peta Kelas
`GET /kepsek/peta-kelas` · `kepsek.peta-kelas.index` · `Kepsek\PetaKelasController@index` · `kepsek/peta-kelas/index.blade.php` (94 baris)

Subjudul: _Overview seluruh kelas, wali, siswa, dan akun ortu_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Detail | `→` | `kepsek.peta-kelas.detail` | navigasi (GET /kepsek/peta-kelas/{guruId}) |

#### Detail Kelas
`GET /kepsek/peta-kelas/{guruId}` · `kepsek.peta-kelas.detail` · `Kepsek\PetaKelasController@detail` · `kepsek/peta-kelas/detail.blade.php` (275 baris)

Subjudul: _Peta kelas wali_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Kembali | `→` | `kepsek.peta-kelas.index` | navigasi (GET /kepsek/peta-kelas) |

#### Rekap Sekolah
`GET /kepsek/rekap` · `kepsek.rekap.index` · `Kepsek\RekapController@index` · `kepsek/rekap/index.blade.php` (107 baris)

Subjudul: _Gabungan data seluruh guru_

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| Unduh CSV | `→` | `kepsek.rekap.unduh` | navigasi (GET /kepsek/rekap/unduh/csv) |

#### ⚙︎ /kepsek/rekap/unduh/csv
`GET /kepsek/rekap/unduh/csv` · `kepsek.rekap.unduh` · `Kepsek\RekapController@unduh`

_Bukan halaman HTML — unduhan berkas (PDF/CSV/XLSX), respons JSON, atau pengalihan._

#### Detail Rekap Guru
`GET /kepsek/rekap/{guruId}` · `kepsek.rekap.detail` · `Kepsek\RekapController@detail` · `kepsek/rekap/detail.blade.php` (94 baris)

| Aksi | Metode | Endpoint / fungsi | Catatan |
|---|---|---|---|
| ← Kembali | `→` | `kepsek.rekap.index` | navigasi (GET /kepsek/rekap) |
## 5. Pola interaksi berulang

Dua belas pola berikut menutupi hampir seluruh interaksi di aplikasi. Memahaminya cukup untuk
menebak perilaku halaman yang belum terdokumentasi.

| # | Pola | Jumlah | Contoh | Catatan |
|---|---|---:|---|---|
| 1 | **Filter → Tampilkan** (`<form method="GET">` + tombol *Tampilkan/Filter/Cari*) | 26 form | `admin.cetak.leger`, `admin.perkembangan.grafik`, `admin.tryout.packages.index` | Selalu disertai tautan **Reset** ke rute yang sama tanpa parameter |
| 2 | **Simpan (POST/PUT)** | 88 form POST · 25 form PUT/PATCH | `admin.mapel.store`, `admin.users.update` | Semua sudah memuat `@csrf` (0 temuan form tanpa CSRF) |
| 3 | **Hapus (DELETE + konfirmasi)** | 26 form DELETE | `admin.kokurikuler.tema.destroy`, `guru.catatan.destroy` | 25 di antaranya memakai `onsubmit="return confirm(...)"`; **1 tanpa konfirmasi** (`admin/mapel/index`, form hapus baris) |
| 4 | **Cetak lewat `window.print()`** | 6 tombol | `admin.cetak.leger`, `admin.cetak.nilai-show`, `admin.transkrip.cetak-show`, `admin.cetak.pelengkap` | Bukan PDF server-side; bergantung pada dialog cetak browser |
| 5 | **Ekspor / unduh / cetak berkas** | 16 rute | `admin.backup.export`, `guru.nilai-erapor.export`, `admin.cbt.sessions.export`, `kepsek.rekap.unduh` | Format: CSV, XLSX, ZIP backup, PDF (DomPDF) |
| 6 | **Impor berkas (CSV/XLSX/ZIP)** | 8 rute | `admin.mapel.import-dapodik`, `admin.transkrip.import.store`, `guru.nilai-erapor.import`, `admin.backup.import`, `admin.referensi.foto-siswa.store` | Umumnya disertai tautan **Unduh Template** |
| 7 | **Aksi massal (centang → hapus/buat)** | 3 halaman | `admin.mapel.index` (Hapus Terpilih/Hapus Semua), `admin.users.akun-massal` (Centang Semua → Buat Akun Terpilih), `guru.siswa.pungut` | Memakai `<input type="checkbox" name="ids[]">` lalu `fetch()` |
| 8 | **Modal sederhana (inline `style.display`)** | ±15 halaman | `admin.api-keys.index` (`#modalForm`), `admin.bobot.index` (`editBobot()`), `admin.peta-kelas.index` (`openModal()`), `guru.input-nilai.index` (paste Excel, isi semua), `admin.sekolah.index`, `admin.kode-akses.index`, `guru.nilai-erapor.index`, `ortu.catatan.index` | Dibuka/ditutup dengan JS inline, bukan komponen `<x-modal>` (komponen `components/modal.blade.php` tersedia tapi **tidak dipakai** mana pun) |
| 9 | **Penyimpanan asinkron (`fetch()`)** | 17 halaman · 31 panggilan | `guru.nilai-erapor.auto-save` (throttle 120/menit), `guru.input-nilai.simpan/paste`, `admin.dapodik.api.*`, `guru.kehadiran.scan` (throttle 120/menit) | Semua mengirim header CSRF dari `<meta name="csrf-token">` |
| 10 | **Unduhan/publikasi data dua arah Dapodik** | 10 rute | `admin.dapodik.index` (tab Bridge vs Manual: Test Koneksi → Preview → Dry Run → Sinkronisasi), `admin.dapodik.push.*` (Sekolah/GTK/Peserta Didik/Rombel/Jadwal/Nilai Rapor/Kehadiran) | Semua berbasis `fetch()` + modal progres |
| 11 | **Pindai QR / geofencing** | 3 halaman | `admin.presensi.kartu` (kamera + bind QR), `guru.kehadiran.jadwal` (scan absensi), `presensi.pegawai.index` (geofence) | Memakai `html5-qrcode` dan Geolocation API |
| 12 | **Tab di dalam halaman** | 4 halaman | `admin.dapodik.index` (Bridge/Manual), `admin.users.index` (8 tab peran), landing (Calon Peserta/Pendaftar), `landing.tka-statistik` | Tombol bergaya `switchTab()` / `tkaTabClick()` |

Pola pendukung lain:

* **Empty state** — 50 view memakai blok `.empty-state` (ikon + judul + ajakan).
* **Tabel** — 122 view memuat `<table>`; 26 di antaranya memanggil `{{ $x->links() }}` untuk pagination.
  Sisanya (96) menampilkan seluruh baris tanpa pagination.
* **Toast** — dipanggil dari JS setelah aksi asinkron berhasil/gagal.
* **Auto-save** — hanya ada di `guru.nilai-erapor.index` (`auto-save`, throttle 120/menit).
* **Throttle** — 47 rute tulis dibatasi: `throttle:120,1` (auto-save & scan), `60,1` (input nilai, bind kartu),
  `30,1` (koreksi presensi, unggah foto, referensi), `10,1` (login, impor, push Dapodik),
  `5,1` (hapus massal, backup impor, sinkron penuh), `10,5` (hapus seluruh data).

---

## 6. Temuan & catatan

Daftar ini adalah hasil pembacaan silang antara menu, rute, dan isi halaman — bukan hasil uji jalan.

**Status perbaikan (28 Oktober 2026).** Kolom *Status* pada tabel 6.1 & 6.2 menandai tindak lanjut:
✅ sudah diperbaiki · ⏳ belum dikerjakan (butuh keputusan desain/produk) · ➖ bukan temuan atau sekadar informasi.
Perbaikan ✅ ada pada cabang `arena/553884e4-simantapmonsa` (commit `fix(ui): sembunyikan menu modul nonaktif, lengkapi navigasi dan aksi baris`);
dampaknya juga tercatat pada kolom aksi tiap halaman di §4 (baris bertanda **ditambahkan**).

### 6.1 Berdampak pada pengguna

| # | Temuan | Bukti | Dampak | Saran | Status |
|---|---|---|---|---|---|
| T1 | **28 % halaman (56 dari 199) diblokir `module.asesmen`** | `EnsureModuleEnabled` mengembalikan **404** bila `config('simantap.cbt_enabled')` false (nilai default). 94 rute — `admin/cbt`, `admin/tryout`, `admin/tka`, `tryout.*`, `tka.*` — memakai middleware ini | Tab **CBT/Ujian** dan **TKA** di admin, menu **Tryout** & **Asesmen TKA** guru, menu **Tryout** siswa, dan **Laporan TKA** ortu semuanya berujung halaman 404, padahal menunya tetap tampil (rute terdaftar, hanya middleware yang menolak) | Sembunyikan tab/menu saat flag nonaktif, atau pisahkan flag menjadi `tryout_enabled` / `tka_enabled` agar TKA & Tryout tidak ikut terkunci oleh `cbt_enabled` | ✅ Diperbaiki — tab & menu disembunyikan saat modul mati (`modulAsesmenAktif()`) |

| T2 | **Menu admin "lengkap" tidak pernah dirender** | 80 view admin mengisi `@section('sidebar')` dengan `@include('admin.partials.sidebar')`, tetapi `layouts/admin-with-tabs` **tidak** memiliki `@yield('sidebar')` — ia memilih 4 partial per tab | `admin/partials/sidebar.blade.php` (13 grup, lengkap dengan Kokurikuler, CBT, TKA, API Keys) menjadi kode mati dan perlahan menyimpang dari yang tampil | Hapus `@include` yang sia-sia dari 80 view, atau jadikan `sidebar.blade.php` satu-satunya sumber menu | ✅ Diperbaiki — 80 `@section('sidebar')` sia-sia dihapus; `admin/partials/sidebar.blade.php` dihapus |

| T3 | **6 halaman tanpa sidebar** | `layouts.app` tanpa `@section('sidebar')`: `admin/referensi/alignment-check`, `tka/assessments/create`, `tka/parent/show`, `tryout/student/index`, `tryout/student/result`, `tryout/student/take` | Siswa yang sedang mengerjakan tryout (`take`, `result`, `index`) kehilangan seluruh navigasi — tidak ada cara keluar selain tombol browser | Tambahkan `@include('siswa.partials.sidebar')` (dan partial terkait) pada keenam view | ✅ Diperbaiki sebagian — sidebar ditambah pada 5 halaman; `tryout/student/take` sengaja tetap tanpa sidebar selama ujian |

| T4 | **Halaman indeks tanpa aksi baris** | `tryout/packages/index` (guru), `tka/dimensions/index` (guru), `guru/kebiasaan/index`, `guru/tka/index`, `tka/assessments/index` | Rute `show`/`edit`/`destroy` untuk paket tryout, dimensi TKA, dan detail kebiasaan **ada** tetapi tidak terjangkau dari antarmuka — data bisa dibuat namun tidak bisa dibuka, diubah, atau dihapus | Tambahkan kolom aksi pada tabel (lihat/ubah/hapus) | ✅ Diperbaiki — kolom aksi ditambahkan pada kelima indeks |

| T5 | **Tautan lintas-peran** | `kepsek/dashboard.blade.php:151` menaut ke `admin.cbt.sessions.index`, sedangkan grup rute itu dilindungi `role:admin` | Kepala sekolah yang mengeklik **Detail Ujian** memperoleh 403 | Arahkan ke halaman pemantauan kepsek, atau buat rute khusus kepsek | ✅ Diperbaiki — tombol kini menuju `kepsek.hasil-belajar.index` |

| T6 | **Mojibake pada 4 view** | `admin/users/create`, `admin/users/edit`, `guru/kuis/create`, `guru/nilai/create` berisi `ðŸ’¾` dan `â†\x90` (UTF-8 terenkode ganda) | Tombol **Simpan** dan tautan **Kembali** menampilkan karakter rusak | Simpan ulang berkas sebagai UTF-8, ganti dengan `💾` / `←` | ✅ Diperbaiki — 0 mojibake tersisa di seluruh `resources/views` |

| T7 | **Kelas Bootstrap tidak dimuat global** | Bootstrap hanya diimpor oleh `resources/css/tka-statistik.css`, tetapi 22 view memakai `container-fluid`, `col-md-6`, `btn-close`, `bg-primary`, `border-primary` (mis. `tryout/student/*`, `admin/tka/*`, `admin/tryout/*`, `admin/cbt/*`) | Tata letak halaman ujian/TKA/tryout tampil berbeda (sebagian tidak bergaya); tombol tutup `.btn-close` pada alert tidak berfungsi karena Bootstrap JS juga tidak dimuat | Impor Bootstrap hanya di halaman itu, atau seragamkan ke kelas `layout.css` (`.card`, `.btn`, `.table-wrapper`) | ⏳ Belum — menunggu keputusan desain (Bootstrap vs kelas `layout.css`) |

| T8 | **Dua set ikon** | Sidebar: `<x-lucide-*>`; isi halaman: `<i class="fas fa-*">` | Tebal-tipis dan ukuran ikon tidak konsisten antara menu dan konten | Pilih satu set | ⏳ Belum — menunggu keputusan desain (Lucide vs Font Awesome) |

| T9 | **1 form hapus tanpa konfirmasi** | `admin/mapel/index.blade.php` — form DELETE tanpa `onsubmit confirm` | Salah klik langsung menghapus mata pelajaran | Samakan dengan 25 form hapus lainnya | ➖ Bukan temuan — `deleteSelectedForm` memang tanpa `onsubmit`, tetapi dikonfirmasi dari JS `deleteSelected()` |

| T10 | **Endpoint berbahaya tanpa jeda konfirmasi ganda** | `admin.backup.wipe` sudah meminta `konfirmasi` + `password` (baik); `admin.users.destroy-all` dan `admin.mapel.destroy-all` hanya mengandalkan `confirm()` | Risiko salah klik pada aksi yang menghapus banyak baris | Pertimbangkan konfirmasi bertingkat seperti `backup.wipe` | ⏳ Belum — pertimbangan produk (konfirmasi bertingkat) |


### 6.2 Kebersihan kode & konsistensi

| # | Temuan | Bukti | Status |
|---|---|---|---|
| T11 | 18 view tidak direferensikan mana pun: 8 komponen Breeze (`components/{auth-session-status, danger-button, input-error, input-label, modal, primary-button, secondary-button, text-input}`), `layouts/navigation`, 6 `errors/*` (dipanggil framework saat runtime), 3 `vendor/scramble/*` (paket). Tambahan: `layouts/guest` hanya direferensikan komponen `App\View\Components\GuestLayout`, yang sendiri tidak pernah dipanggil | Pemeriksaan silang `@extends/@include/<x-*>` dan `view()/loadView()/Pdf::loadView()` di seluruh controller — **tidak ada** view halaman yang benar-benar yatim | ➖ Tidak berubah — 18 view yatim di atas tetap ada (komponen Breeze, `errors/*`, `vendor/scramble/*`). Terpisah dari daftar itu, `admin/partials/sidebar.blade.php` kini dihapus karena kehilangan seluruh penyertanya (lihat T2) |

| T12 | Duplikasi menu: **Mapping Rapor** muncul di grup *Transkrip Ijazah* dan *Data Referensi*; **Data Sekolah** ada di tab E-Rapor dan Setup; **Data Mapel**, **Data Semester**, **Pemetaan Peta Kelas** juga ganda | `sidebar-erapor` vs `sidebar-setup` | ⏳ Belum — pemeliharaan menu |

| T13 | Sisa *scaffolding* Breeze: `layouts/navigation`, `layouts/guest` + `GuestLayout`, dan 8 komponen form/tombol (`components/modal` dll.) tidak dipakai mana pun — halaman auth justru berdiri sendiri dengan `<!DOCTYPE html>` lengkap | Pencarian `@extends('layouts.guest')`, `<x-guest-layout>`, `<x-modal`, `<x-primary-button>` → 0 | ⏳ Belum — pembersihan *scaffolding* Breeze |

| T14 | `admin.api-keys.index` tidak masuk menu mana pun — hanya dapat dijangkau dari tautan di halaman Dapodik | Pemeriksaan keempat partial sidebar | ✅ Diperbaiki — "API Keys & Bridge" masuk menu **Setup » Dapodik** |

| T15 | `tryout/student/index` membangun tautan di controller (`$session['mulai_url']`), bukan `route()` di Blade — menyulitkan pelacakan tautan statis | `resources/views/tryout/student/index.blade.php` | ✅ Diperbaiki — tautan dibangun dengan `route()` di Blade |

| T16 | 32 pemanggilan `route()` mengarah ke nama yang **tidak terdaftar** pada konfigurasi default; semuanya `cbt.*` (karena blok rute CBT dibungkus `if (config(...))`) dan sudah dijaga `Route::has()` di siswa/guru, tetapi **tidak** di `cbt/banks/*`, `cbt/sessions/*`, `cbt/student/*` | Pemeriksaan `route()` vs daftar rute | ➖ Diperiksa ulang — aman: rute `cbt.*` ikut terbit bersama blok `if (config(...))` (routes/web.php:446), jadi view-nya tidak pernah dirender tanpa rute |

| T17 | Siswa: 5 halaman; namun **Kuis** menuntut JS (timer & navigasi soal) — `siswa/kuis/show` mem-push skrip hanya saat form tersedia | `siswa/kuis/show.blade.php` | ➖ Informasi |

| T18 | Tidak ada halaman yang memanggil `{{ $x->links() }}` pada 96 dari 122 view bertabel | Pemeriksaan pagination | ➖ Informasi |


### 6.3 Hal yang sudah baik

* **0 form POST/PUT/PATCH/DELETE tanpa `@csrf`** — semua token tersemat.
* **47 rute tulis ber-throttle**, termasuk auto-save (120/menit) dan login (10/menit).
* **`force.password.change`** diterapkan pada 7 grup rute (kecuali landing & auth) — pengguna dengan sandi awal dipaksa mengganti sandi.
* **`x-sidebar-planned`** dipakai untuk menandai fitur yang belum siap, bukan tautan mati.
* Halaman berbahaya (`backup.wipe`) sudah meminta kata sandi ulang.

---


---

## 7. Lampiran A — endpoint aksi tanpa halaman sendiri

161 endpoint tulis (POST/PUT/PATCH/DELETE). Dikelompokkan agar mudah dipakai sebagai daftar
periksa saat menguji ulang tombol.

### Publik & autentikasi — 5 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `POST` | `/login` | `—` | `Auth\LoginController@login` | `throttle:10,1` |
| `POST` | `/logout` | `logout` | `Auth\LoginController@logout` | `throttle:30,1` |
| `POST` | `/lupa-sandi` | `password.email` | `Auth\ForgotPasswordController@sendResetLinkEmail` | `throttle:5,1` |
| `POST` | `/reset-sandi` | `password.update` | `Auth\ResetPasswordController@reset` | `throttle:5,1` |
| `PUT` | `/force-password-change` | `force-password-change.update` | `Auth\ForcePasswordChangeController@update` | — |

### Presensi pegawai — 2 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `POST` | `/presensi/pegawai/masuk` | `presensi.pegawai.masuk` | `Pegawai\PresensiController@masuk` | `throttle:10,1` |
| `POST` | `/presensi/pegawai/pulang` | `presensi.pegawai.pulang` | `Pegawai\PresensiController@pulang` | `throttle:10,1` |

### Admin — e-Rapor & sistem — 77 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `DELETE` | `/admin/api-keys/{apiKey}` | `admin.api-keys.destroy` | `Admin\ApiKeyController@destroy` | — |
| `DELETE` | `/admin/dapodik/log/{id}` | `admin.dapodik.log-destroy` | `Admin\DapodikImportController@logDestroy` | — |
| `DELETE` | `/admin/kokurikuler/kegiatan/{kegiatan}` | `admin.kokurikuler.kegiatan.destroy` | `Admin\KokurikulerController@destroyKegiatan` | `throttle:30,1` |
| `DELETE` | `/admin/kokurikuler/kelompok/{kelompok}` | `admin.kokurikuler.kelompok.destroy` | `Admin\KokurikulerController@destroyKelompok` | `throttle:30,1` |
| `DELETE` | `/admin/kokurikuler/tema/{tema}` | `admin.kokurikuler.tema.destroy` | `Admin\KokurikulerController@destroyTema` | `throttle:30,1` |
| `DELETE` | `/admin/mapel/hapus-semua` | `admin.mapel.destroy-all` | `Admin\MapelController@destroyAll` | — |
| `DELETE` | `/admin/mapel/hapus-terpilih` | `admin.mapel.destroy-selected` | `Admin\MapelController@destroySelected` | — |
| `DELETE` | `/admin/mapel/{mapel}` | `admin.mapel.destroy` | `Admin\MapelController@destroy` | — |
| `DELETE` | `/admin/peta-kelas/{id}` | `admin.peta-kelas.destroy` | `Admin\PetaKelasController@destroy` | — |
| `DELETE` | `/admin/presensi/kartu-siswa/{siswa}` | `admin.presensi.kartu.reset` | `Admin\PresensiKartuController@reset` | — |
| `DELETE` | `/admin/presensi/lokasi/{lokasi}` | `admin.presensi.lokasi.destroy` | `Admin\PresensiController@destroyLocation` | — |
| `DELETE` | `/admin/referensi/ekstrakurikuler/{ekstrakurikuler}` | `admin.referensi.ekstrakurikuler.destroy` | `Admin\DataReferensiController@destroyEkstrakurikuler` | `throttle:30,1` |
| `DELETE` | `/admin/referensi/pembelajaran/{pembelajaran}` | `admin.referensi.pembelajaran.destroy` | `Admin\DataReferensiController@destroyPembelajaran` | `throttle:30,1` |
| `DELETE` | `/admin/referensi/tanggal-rapor/{tanggalRapor}` | `admin.referensi.tanggal-rapor.destroy` | `Admin\DataReferensiController@destroyTanggalRapor` | `throttle:30,1` |
| `DELETE` | `/admin/semester/{semester}` | `admin.semester.destroy` | `Admin\SemesterController@destroy` | — |
| `DELETE` | `/admin/users/hapus-semua/{peran}` | `admin.users.destroy-all` | `Admin\UserController@destroyAll` | `throttle:5,1` |
| `DELETE` | `/admin/users/{param}` | `admin.users.destroy` | `Admin\UserController@destroy` | — |
| `POST` | `/admin/api-keys` | `admin.api-keys.store` | `Admin\ApiKeyController@store` | — |
| `POST` | `/admin/api-keys/{apiKey}/toggle` | `admin.api-keys.toggle` | `Admin\ApiKeyController@toggle` | — |
| `POST` | `/admin/backup/import` | `admin.backup.import` | `Admin\BackupController@import` | `throttle:5,1` |
| `POST` | `/admin/backup/wipe` | `admin.backup.wipe` | `Admin\BackupController@wipeAll` | `throttle:10,5` |
| `POST` | `/admin/bobot/{id}` | `admin.bobot.update` | `Admin\BobotController@update` | — |
| `POST` | `/admin/dapodik/api/config` | `admin.dapodik.api.config.save` | `Api\DapodikController@saveConfig` | — |
| `POST` | `/admin/dapodik/api/preview-data` | `admin.dapodik.api.preview` | `Api\DapodikController@preview` | — |
| `POST` | `/admin/dapodik/api/pull` | `admin.dapodik.api.pull` | `Api\DapodikController@pull` | — |
| `POST` | `/admin/dapodik/api/sync` | `admin.dapodik.api.sync` | `Api\DapodikController@sync` | — |
| `POST` | `/admin/dapodik/api/test-connection` | `admin.dapodik.api.test-connection` | `Api\DapodikController@testConnection` | — |
| `POST` | `/admin/dapodik/import` | `admin.dapodik.import` | `Admin\DapodikImportController@import` | — |
| `POST` | `/admin/dapodik/preview` | `admin.dapodik.preview` | `Admin\DapodikImportController@preview` | — |
| `POST` | `/admin/dapodik/push/gtk` | `admin.dapodik.push.gtk` | `Api\DapodikPushController@pushGtk` | — |
| `POST` | `/admin/dapodik/push/jadwal` | `admin.dapodik.push.jadwal` | `Api\DapodikPushController@pushJadwal` | — |
| `POST` | `/admin/dapodik/push/kehadiran` | `admin.dapodik.push.kehadiran` | `Api\DapodikPushController@pushKehadiran` | — |
| `POST` | `/admin/dapodik/push/nilai-rapor` | `admin.dapodik.push.nilai-rapor` | `Api\DapodikPushController@pushNilaiRapor` | — |
| `POST` | `/admin/dapodik/push/peserta-didik` | `admin.dapodik.push.peserta-didik` | `Api\DapodikPushController@pushPesertaDidik` | — |
| `POST` | `/admin/dapodik/push/rombel` | `admin.dapodik.push.rombel` | `Api\DapodikPushController@pushRombel` | — |
| `POST` | `/admin/kode-akses/{id}` | `admin.kode-akses.update` | `Admin\KodeAksesController@update` | — |
| `POST` | `/admin/kokurikuler/kegiatan` | `admin.kokurikuler.kegiatan.store` | `Admin\KokurikulerController@storeKegiatan` | `throttle:30,1` |
| `POST` | `/admin/kokurikuler/kelompok` | `admin.kokurikuler.kelompok.store` | `Admin\KokurikulerController@storeKelompok` | `throttle:30,1` |
| `POST` | `/admin/kokurikuler/tema` | `admin.kokurikuler.tema.store` | `Admin\KokurikulerController@storeTema` | `throttle:30,1` |
| `POST` | `/admin/mapel` | `admin.mapel.store` | `Admin\MapelController@store` | — |
| `POST` | `/admin/mapel/import-dapodik` | `admin.mapel.import-dapodik` | `Admin\MapelController@importDapodik` | `throttle:10,1` |
| `POST` | `/admin/mapel/import-lokal` | `admin.mapel.import-lokal` | `Admin\MapelController@importLokal` | `throttle:10,1` |
| `POST` | `/admin/mapel/seed` | `admin.mapel.seed` | `Admin\MapelController@seedDefault` | — |
| `POST` | `/admin/modul` | `admin.modul.update` | `Admin\ModulController@update` | — |
| `POST` | `/admin/peta-kelas` | `admin.peta-kelas.store` | `Admin\PetaKelasController@store` | — |
| `POST` | `/admin/peta-kelas/keluarkan` | `admin.peta-kelas.keluarkan` | `Admin\PetaKelasController@keluarkanSiswa` | — |
| `POST` | `/admin/peta-kelas/pindah` | `admin.peta-kelas.pindah` | `Admin\PetaKelasController@pindahSiswa` | — |
| `POST` | `/admin/presensi/kartu-siswa/{siswa}/hubungkan` | `admin.presensi.kartu.bind` | `Admin\PresensiKartuController@bind` | `throttle:60,1` |
| `POST` | `/admin/presensi/lokasi` | `admin.presensi.lokasi.store` | `Admin\PresensiController@storeLocation` | — |
| `POST` | `/admin/presensi/pegawai/koreksi` | `admin.presensi.pegawai.koreksi` | `Admin\PresensiController@koreksi` | `throttle:30,1` |
| `POST` | `/admin/referensi/alignment/refresh` | `admin.referensi.alignment.refresh` | `Admin\DataReferensiController@refreshAlignment` | `throttle:5,1` |
| `POST` | `/admin/referensi/ekstrakurikuler` | `admin.referensi.ekstrakurikuler.store` | `Admin\DataReferensiController@storeEkstrakurikuler` | `throttle:30,1` |
| `POST` | `/admin/referensi/foto-siswa` | `admin.referensi.foto-siswa.store` | `Admin\DataReferensiController@storeFotoSiswa` | `throttle:10,1` |
| `POST` | `/admin/referensi/foto-siswa/{siswa}` | `admin.referensi.foto-siswa.satuan` | `Admin\DataReferensiController@storeFotoSiswaSatuan` | `throttle:30,1` |
| `POST` | `/admin/referensi/logo-ttd` | `admin.referensi.logo-ttd.store` | `Admin\DataReferensiController@storeLogoTtd` | `throttle:30,1` |
| `POST` | `/admin/referensi/pembelajaran` | `admin.referensi.pembelajaran.store` | `Admin\DataReferensiController@storePembelajaran` | `throttle:30,1` |
| `POST` | `/admin/referensi/tanggal-rapor` | `admin.referensi.tanggal-rapor.store` | `Admin\DataReferensiController@storeTanggalRapor` | `throttle:30,1` |
| `POST` | `/admin/sekolah` | `admin.sekolah.update` | `Admin\SekolahController@update` | — |
| `POST` | `/admin/sekolah/search` | `admin.sekolah.search` | `Admin\SekolahController@search` | — |
| `POST` | `/admin/semester` | `admin.semester.store` | `Admin\SemesterController@store` | — |
| `POST` | `/admin/semester/{semester}/activate` | `admin.semester.activate` | `Admin\SemesterController@activate` | — |
| `POST` | `/admin/sheet/connect` | `admin.sheet.connect` | `Admin\SheetController@connect` | — |
| `POST` | `/admin/sheet/disconnect` | `admin.sheet.disconnect` | `Admin\SheetController@disconnect` | — |
| `POST` | `/admin/transkrip/import` | `admin.transkrip.import.store` | `Admin\TranskripController@storeImportNilai` | `throttle:10,1` |
| `POST` | `/admin/transkrip/nomor/import` | `admin.transkrip.nomor.import` | `Admin\TranskripController@importNomor` | `throttle:10,1` |
| `POST` | `/admin/users` | `admin.users.store` | `Admin\UserController@store` | — |
| `POST` | `/admin/users/proses-massal` | `admin.users.proses-massal` | `Admin\UserController@prosesAkunMassal` | `throttle:5,1` |
| `PUT` | `/admin/kokurikuler/kelompok/{kelompok}/anggota` | `admin.kokurikuler.anggota.update` | `Admin\KokurikulerController@updateAnggota` | `throttle:30,1` |
| `PUT` | `/admin/mapel/{mapel}` | `admin.mapel.update` | `Admin\MapelController@update` | — |
| `PUT` | `/admin/presensi/lokasi/{lokasi}` | `admin.presensi.lokasi.update` | `Admin\PresensiController@updateLocation` | — |
| `PUT` | `/admin/profile/password` | `admin.profile.password` | `Admin\ProfileController@updatePassword` | `throttle:10,1` |
| `PUT` | `/admin/referensi/gelar-ptk` | `admin.referensi.gelar-ptk.update` | `Admin\DataReferensiController@updateGelarPtk` | `throttle:30,1` |
| `PUT` | `/admin/referensi/mapel-meta` | `admin.referensi.mapel-meta.update` | `Admin\DataReferensiController@updateMapelMeta` | `throttle:30,1` |
| `PUT` | `/admin/transkrip/input/{siswa}` | `admin.transkrip.input.store` | `Admin\TranskripController@storeInput` | `throttle:30,1` |
| `PUT` | `/admin/transkrip/nomor/{siswa}` | `admin.transkrip.nomor.update` | `Admin\TranskripController@updateNomor` | `throttle:30,1` |
| `PUT` | `/admin/transkrip/setting` | `admin.transkrip.setting.update` | `Admin\TranskripController@updateSetting` | `throttle:30,1` |
| `PUT` | `/admin/users/{param}` | `admin.users.update` | `Admin\UserController@update` | — |

### Admin › CBT — 7 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `DELETE` | `/admin/cbt/banks/{bank}` | `admin.cbt.banks.destroy` | `Admin\Cbt\CbtBankController@destroy` | — |
| `DELETE` | `/admin/cbt/sessions/{session}` | `admin.cbt.sessions.destroy` | `Admin\Cbt\CbtSessionController@destroy` | — |
| `POST` | `/admin/cbt/banks` | `admin.cbt.banks.store` | `Admin\Cbt\CbtBankController@store` | — |
| `POST` | `/admin/cbt/sessions` | `admin.cbt.sessions.store` | `Admin\Cbt\CbtSessionController@store` | — |
| `POST` | `/admin/cbt/sessions/{session}/force-complete` | `admin.cbt.sessions.force-complete` | `Admin\Cbt\CbtSessionController@forceComplete` | — |
| `POST` | `/admin/cbt/sessions/{session}/update-status` | `admin.cbt.sessions.update-status` | `Admin\Cbt\CbtSessionController@updateStatus` | — |
| `PUT` | `/admin/cbt/banks/{bank}` | `admin.cbt.banks.update` | `Admin\Cbt\CbtBankController@update` | — |

### Admin › Tryout — 9 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `DELETE` | `/admin/tryout/packages/{package}` | `admin.tryout.packages.destroy` | `Admin\Tryout\TryoutPackageAdminController@destroy` | — |
| `DELETE` | `/admin/tryout/sessions/{session}` | `admin.tryout.sessions.destroy` | `Admin\Tryout\TryoutPackageAdminController@destroySession` | — |
| `POST` | `/admin/tryout/packages` | `admin.tryout.packages.store` | `Admin\Tryout\TryoutPackageAdminController@store` | — |
| `POST` | `/admin/tryout/packages/{package}/duplicate` | `admin.tryout.packages.duplicate` | `Admin\Tryout\TryoutPackageAdminController@duplicate` | — |
| `POST` | `/admin/tryout/packages/{package}/toggle-status` | `admin.tryout.packages.toggle-status` | `Admin\Tryout\TryoutPackageAdminController@toggleStatus` | — |
| `POST` | `/admin/tryout/sessions` | `admin.tryout.sessions.store` | `Admin\Tryout\TryoutPackageAdminController@storeSession` | — |
| `POST` | `/admin/tryout/sessions/{session}/force-complete` | `admin.tryout.sessions.force-complete` | `Admin\Tryout\TryoutPackageAdminController@forceCompleteSession` | — |
| `PUT` | `/admin/tryout/packages/{package}` | `admin.tryout.packages.update` | `Admin\Tryout\TryoutPackageAdminController@update` | — |
| `PUT` | `/admin/tryout/sessions/{session}` | `admin.tryout.sessions.update` | `Admin\Tryout\TryoutPackageAdminController@updateSession` | — |

### Admin › TKA — 6 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `POST` | `/admin/tka/dimensions` | `admin.tka.dimensions.store` | `Admin\Tka\TkaAdminController@storeDimension` | — |
| `POST` | `/admin/tka/dimensions/{dimension}/toggle-status` | `admin.tka.dimensions.toggle-status` | `Admin\Tka\TkaAdminController@toggleDimensionStatus` | — |
| `POST` | `/admin/tka/export` | `admin.tka.export` | `Admin\Tka\TkaAdminController@export` | — |
| `POST` | `/admin/tka/generate-report` | `admin.tka.generate-report` | `Admin\Tka\TkaAdminController@generateReport` | — |
| `POST` | `/admin/tka/warnings/{warning}/update-status` | `admin.tka.warnings.update-status` | `Admin\Tka\TkaAdminController@updateWarningStatus` | — |
| `PUT` | `/admin/tka/dimensions/{dimension}` | `admin.tka.dimensions.update` | `Admin\Tka\TkaAdminController@updateDimension` | — |

### Guru — 35 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `DELETE` | `/guru/catatan/{param}` | `guru.catatan.destroy` | `Guru\CatatanController@destroy` | — |
| `DELETE` | `/guru/dimensi/{param}` | `guru.dimensi.destroy` | `Guru\DimensiController@destroy` | — |
| `DELETE` | `/guru/kehadiran/{param}` | `guru.kehadiran.destroy` | `Guru\KehadiranController@destroy` | — |
| `DELETE` | `/guru/kuis/{param}` | `guru.kuis.destroy` | `Guru\KuisController@destroy` | — |
| `DELETE` | `/guru/materi/{param}` | `guru.materi.destroy` | `Guru\MateriController@destroy` | — |
| `DELETE` | `/guru/nilai/{param}` | `guru.nilai.destroy` | `Guru\NilaiController@destroy` | — |
| `DELETE` | `/guru/siswa/{param}` | `guru.siswa.destroy` | `Guru\SiswaController@destroy` | — |
| `POST` | `/guru/catatan` | `guru.catatan.store` | `Guru\CatatanController@store` | — |
| `POST` | `/guru/dimensi` | `guru.dimensi.store` | `Guru\DimensiController@store` | — |
| `POST` | `/guru/erapor/pdf-bulk` | `guru.erapor.pdf-bulk` | `Guru\ErapotGeneratorController@generateBulkPdf` | — |
| `POST` | `/guru/input-nilai/paste` | `guru.input-nilai.paste` | `Guru\InputNilaiController@pasteFromExcel` | `throttle:30,1` |
| `POST` | `/guru/input-nilai/simpan` | `guru.input-nilai.simpan` | `Guru\InputNilaiController@simpanNilai` | `throttle:60,1` |
| `POST` | `/guru/integrasi/connect` | `guru.integrasi.connect` | `Guru\IntegrasiController@connect` | — |
| `POST` | `/guru/integrasi/disconnect` | `guru.integrasi.disconnect` | `Guru\IntegrasiController@disconnect` | — |
| `POST` | `/guru/integrasi/full-sync` | `guru.integrasi.full-sync` | `Guru\IntegrasiController@fullSync` | `throttle:5,1` |
| `POST` | `/guru/integrasi/update-settings` | `guru.integrasi.update-settings` | `Guru\IntegrasiController@updateSettings` | — |
| `POST` | `/guru/kehadiran` | `guru.kehadiran.store` | `Guru\KehadiranController@store` | — |
| `POST` | `/guru/kehadiran/jadwal/{jadwal}/scan` | `guru.kehadiran.scan` | `Guru\KehadiranController@scan` | `throttle:120,1` |
| `POST` | `/guru/kehadiran/jadwal/{jadwal}/siswa/{siswa}/status` | `guru.kehadiran.status` | `Guru\KehadiranController@setStatus` | `throttle:120,1` |
| `POST` | `/guru/kuis` | `guru.kuis.store` | `Guru\KuisController@store` | — |
| `POST` | `/guru/kuis/{kuis}/toggle` | `guru.kuis.toggle` | `Guru\KuisController@toggle` | — |
| `POST` | `/guru/materi` | `guru.materi.store` | `Guru\MateriController@store` | — |
| `POST` | `/guru/nilai` | `guru.nilai.store` | `Guru\NilaiController@store` | — |
| `POST` | `/guru/nilai-erapor/auto-save` | `guru.nilai-erapor.auto-save` | `Guru\NilaiErapotController@autoSave` | `throttle:120,1` |
| `POST` | `/guru/nilai-erapor/import` | `guru.nilai-erapor.import` | `Guru\ImportErapotController@import` | — |
| `POST` | `/guru/nilai-erapor/simpan` | `guru.nilai-erapor.simpan` | `Guru\NilaiErapotController@simpan` | `throttle:60,1` |
| `POST` | `/guru/pengaturan` | `guru.pengaturan.update` | `Guru\PengaturanController@update` | — |
| `POST` | `/guru/siswa` | `guru.siswa.store` | `Guru\SiswaController@store` | — |
| `POST` | `/guru/siswa/impor` | `guru.siswa.impor` | `Guru\SiswaController@impor` | — |
| `POST` | `/guru/siswa/pungut/simpan` | `guru.siswa.pungut.simpan` | `Guru\SiswaController@simpanPungut` | — |
| `POST` | `/guru/tka` | `guru.tka.store` | `Guru\TKAController@store` | — |
| `PUT` | `/guru/kuis/{param}` | `guru.kuis.update` | `Guru\KuisController@update` | — |
| `PUT` | `/guru/materi/{param}` | `guru.materi.update` | `Guru\MateriController@update` | — |
| `PUT` | `/guru/nilai/{param}` | `guru.nilai.update` | `Guru\NilaiController@update` | — |
| `PUT` | `/guru/siswa/{param}` | `guru.siswa.update` | `Guru\SiswaController@update` | — |

### Tryout (guru & siswa) — 9 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `DELETE` | `/tryout/packages/{param}` | `tryout.packages.destroy` | `Tryout\TryoutPackageController@destroy` | — |
| `POST` | `/tryout/packages` | `tryout.packages.store` | `Tryout\TryoutPackageController@store` | — |
| `POST` | `/tryout/sessions` | `tryout.sessions.store` | `Tryout\TryoutSessionController@store` | — |
| `POST` | `/tryout/sessions/{session}/complete` | `tryout.sessions.complete` | `Tryout\TryoutSessionController@complete` | — |
| `POST` | `/tryout/sessions/{session}/publish` | `tryout.sessions.publish` | `Tryout\TryoutSessionController@publish` | — |
| `POST` | `/tryout/sessions/{session}/start` | `tryout.sessions.start` | `Tryout\TryoutSessionController@start` | — |
| `POST` | `/tryout/student/{result}/answer` | `tryout.student.answer` | `Tryout\TryoutStudentController@submitAnswer` | — |
| `POST` | `/tryout/student/{result}/complete` | `tryout.student.complete` | `Tryout\TryoutStudentController@complete` | — |
| `PUT` | `/tryout/packages/{param}` | `tryout.packages.update` | `Tryout\TryoutPackageController@update` | — |

### TKA (guru & ortu) — 8 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `DELETE` | `/tka/dimensions/{dimension}/item/{item}` | `tka.dimensions.destroy-item` | `Tka\TkaDimensionController@destroyItem` | — |
| `DELETE` | `/tka/dimensions/{param}` | `tka.dimensions.destroy` | `Tka\TkaDimensionController@destroy` | — |
| `POST` | `/tka-statistik/refresh` | `tka.refresh` | `TkaController@refresh` | `auth` |
| `POST` | `/tka/assessments` | `tka.assessments.store` | `Tka\TkaAssessmentController@store` | — |
| `POST` | `/tka/assessments/{assessment}/generate-report` | `tka.assessments.generate-report` | `Tka\TkaParentController@generateReport` | — |
| `POST` | `/tka/dimensions` | `tka.dimensions.store` | `Tka\TkaDimensionController@store` | — |
| `POST` | `/tka/dimensions/{dimension}/item` | `tka.dimensions.add-item` | `Tka\TkaDimensionController@addItem` | — |
| `PUT` | `/tka/dimensions/{param}` | `tka.dimensions.update` | `Tka\TkaDimensionController@update` | — |

### Siswa — 2 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `POST` | `/siswa/kuis/{kuis}/submit` | `siswa.kuis.submit` | `Siswa\KuisController@submit` | `throttle:10,1` |
| `PUT` | `/siswa/profil` | `siswa.profil.update` | `Siswa\ProfilController@update` | — |

### Orang tua — 1 endpoint

| Metode | Endpoint | Nama rute | Controller | Throttle |
|---|---|---|---|---|
| `POST` | `/ortu/kebiasaan` | `ortu.kebiasaan.store` | `Ortu\KebiasaanController@store` | — |
## 8. Lampiran B — metode & cara memperbarui

Dokumen ini dihasilkan dengan membaca sumber, bukan dengan menjalankan aplikasi:

1. **Rute** — `routes/web.php` diurai dengan parser berbasis kedalaman kurung kurawal
   (mengikuti `Route::middleware()->prefix()->name()->group()` bersarang, `Route::resource()`,
   dan membuang blok `if (config(...))` serta baris komentar).
2. **Pemetaan rute → view** — setiap `public function` di 89 controller dipindai isi tubuhnya
   (penyeimbang kurung kurawal) untuk menemukan `view('…')`.
3. **Isi view** — 229 berkas `.blade.php` dipindai untuk `<form>` (action, method, `@method`,
   `@csrf`, kolom, tombol submit), `<button>` (teks, `type`, `onclick`), `<a>` (`href` → `route()`),
   `fetch()`, serta jumlah `<table>`, `<select>`, `<input>`.
4. **Menu** — kelima partial sidebar + `components/admin-tabs` dibaca langsung; pohon menu di
   bagian 3 disalin apa adanya dari berkas tersebut.
5. **Keterjangkauan** — penutupan transitif dari menu tiap peran melalui semua `route()` di view
   yang bersangkutan.

**Batasan yang perlu diingat:**

* Tautan yang dibangun di controller (mis. `$session['mulai_url']` di `tryout/student/index`)
  tidak terlihat oleh pemeriksaan statis — jadi beberapa "halaman tak tercapai" di bagian 6
  sebenarnya bisa dibuka melalui tautan dinamis. Temuan T4 tetap relevan karena tidak ada
  tautan apa pun (statis maupun dinamis) pada halaman yang disebut.
* Rute CBT (`/cbt/*`) baru terdaftar bila `SIMANTAP_CBT_ENABLED=true`; angka di dokumen ini
  memakai kondisi **default (CBT nonaktif)**. Dengan CBT aktif jumlah rute bertambah
  28 (388 total, 213 halaman).
* Jumlah rute absolut bisa berbeda 1–2 dari `php artisan route:list` karena keterbatasan parser;
  untuk angka resmi pakailah `php artisan route:list`.
