# SIMANTAP — Arsitektur Sistem

**Versi:** 5.0  
**Tanggal Pembaruan:** 30 Oktober 2026  
**Status Audit:** Terverifikasi Otomatis (PHPUnit 364 tests PASS, Playwright E2E 135+ route GET PASS)

---

## 1. Overview Sistem

SIMANTAP (**Situs Mandiri Terintegrasi Aplikasi Pembelajaran**) adalah platform terpadu untuk sekolah yang mengintegrasikan tiga pilar utama:
1. **Asesmen & Ujian**: Modul Tryout Akademik, TKA (Tes Kesiapan Akademik) Berbobot, dan CBT (Computer-Based Test) dengan engine ujian terpadu (`exam_*`).
2. **Pembelajaran Mendalam & Karakter**: Materi Ajar, Kuis Interaktif, Pemantauan 7 Kebiasaan Anak, dan Penilaian Dimensi Profil Lulusan.
3. **Manajemen Kelas & Akademik**: Manajemen Rombel/Peta Kelas, e-Rapor Kemdikbud terintegrasi dengan audit hash tamper-evident, Transkrip Ijazah, Presensi Geofence Pegawai, dan Presensi Siswa berbasis QR Code.

```
┌─────────────────────────────────────────────────────────────────┐
│                    SIMANTAP v5.0 Architecture                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐     ┌──────────────┐     ┌──────────────┐    │
│  │   Dapodik    │────▶│dapodik-bridge│────▶│   SIMANTAP   │    │
│  │  Desktop v6  │◀────│  Python / EXE│◀────│  Laravel 13  │    │
│  └──────────────┘     └──────────────┘     └──────────────┘    │
│         │                    │                    │             │
│         ▼                    ▼                    ▼             │
│  ┌──────────────┐     ┌──────────────┐     ┌──────────────┐    │
│  │  WebService  │     │   Rest API   │     │  PostgreSQL  │    │
│  │ :5774 (Local)│     │  /api/dapodik│     │  (Prod) /    │    │
│  └──────────────┘     │  sync & push │     │  SQLite (CI) │    │
│                       └──────────────┘     └──────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Tech Stack

| Layer | Teknologi | Versi | Keterangan |
|-------|-----------|-------|------------|
| **Backend Framework** | Laravel | 13.x (`^13.17`) | Routing, Eloquent, Queues, Security Headers |
| **PHP Runtime** | PHP | 8.4+ (min ^8.3) | Herd environment, OPcache CLI enabled |
| **Database Produksi** | PostgreSQL | 16 | Primary database multi-tabel |
| **Database Pengujian** | SQLite | 3.x | In-memory untuk Unit/Feature, file `e2e.sqlite` untuk Playwright |
| **Frontend UI** | Blade + Tailwind CSS 4 + Alpine.js | - | Ringan (bundle app.js ~76 kB, tanpa dependensi font eksternal) |
| **Ikon UI** | Lucide Icons | 2.x | Paket `mallardduck/blade-lucide-icons` |
| **PDF Generation** | Barryvdh DomPDF | 3.1 | Cetak rapor, transkrip ijazah, dan kartu presensi |
| **Spreadsheet Engine** | PhpSpreadsheet | 5.9 | Ekspor-impor nilai e-Rapor, transkrip, data Dapodik |
| **API Documentation** | Dedoc Scramble | 0.13.x | OpenAPI documentation otomatis (terkunci di `local`) |
| **Dapodik Bridge** | Python (Custom EXE) | 4.x | Sinkronisasi dua arah via Dapodik WebService lokal :5774 |

---

## 3. Enam Peran Pengguna (6 User Roles)

SIMANTAP membedakan otorisasi ke dalam 6 peran dengan isolasi middleware ketat:

### 1. Admin (`peran = 'admin'`)
- **Dashboard & Analitik**: Statistik agregat sekolah, pemantauan status penilaian, tren perkembangan nilai.
- **Kelola Pengguna**: CRUD akun pengguna, pembuatan massal, pencarian akun siswa.
- **Peta Kelas & Rombel**: Penataan anggota rombongan belajar, mutasi kelas, pengeluaran siswa.
- **Mata Pelajaran**: Pengaturan mapel lokal dan Dapodik, pengurutan, kelompok mapel.
- **Data Referensi (Dapodik & Rapor)**: Referensi guru/PTK, tanggal cetak rapor, mapping rapor, logo & tanda tangan kepsek/wali kelas, ekstrakurikuler.
- **Kokurikuler**: Manajemen tema, kegiatan projek kokurikuler, dan kelompok siswa.
- **Transkrip Ijazah**: Format nomor ijazah, input manual nilai transkrip, impor Excel, cetak transkrip kelulusan.
- **Cetak Agregat**: Cetak leger nilai kelas, lembar pelengkap rapor, rekap nilai siswa.
- **Presensi & Lokasi**: Konfigurasi radius geofence sekolah, koreksi presensi pegawai, penautan dan cetak QR kartu siswa.
- **Integrasi Dapodik**: Tarik data (Pull via API / Bridge), unggah data (Push nilai rapor, kehadiran, rombel ke Dapodik), log aktivitas sinkronisasi.
- **Pengaturan Sistem**: Profil identitas sekolah, semester aktif, bobot penilaian (Formatif/Sumatif), modul & tampilan, Google Sheets sync, backup & restore mandiri, API Key management.
- **Asesmen Admin**: Manajemen Bank Soal CBT, Sesi CBT (analisis butir soal), Paket & Sesi Tryout, Dimensi & Early Warning TKA.

### 2. Guru (`peran = 'guru'`)
- **Dashboard Pengajar**: Statistik siswa di bawah bimbingan, jadwal mengajar.
- **Manajemen Kelas & Siswa**: Daftar siswa kelas binaan, fitur "Pungut Siswa" dari Dapodik, pengunduhan data CSV.
- **Pembelajaran**: Materi Ajar daring, Kuis interaktif pilihan ganda mandiri.
- **Penilaian & e-Rapor**:
  - Input Nilai Cepat (dukungan paste massal dari Excel).
  - Input Nilai e-Rapor (dengan auto-save berkala dan penjanaan deskripsi otomatis capaian kompetensi).
  - Generator e-Rapor: Cetak PDF individual, cetak massal (bulk), ekspor Excel.
- **Asesmen Kelas**:
  - CBT Guru: Bank soal, pembukaan sesi ujian kelas, pemantauan real-time status siswa.
  - Tryout Guru: Paket tryout, pembukaan sesi tryout, live monitoring.
  - TKA Guru: Penilaian indikator dimensi kesiapan belajar siswa, analisis profil lulusan.
- **Karakter & Presensi**: Pemantauan input 7 Kebiasaan dari orang tua, absensi kelas/mapel via pemindai kamera QR code, presensi mandiri pegawai dengan geofence.

### 3. Siswa (`peran = 'siswa'`)
- **Dashboard Siswa**: Ringkasan capaian nilai, materi aktif, tugas baru.
- **Materi & Kuis**: Membaca materi modul ajar, mengerjakan kuis latihan.
- **Ujian Daring**:
  - Pengerjaan Tryout Akademik (timer otomatis, navigasi soal, hasil ujian langsung).
  - Pengerjaan CBT (dilindungi integritas anti-cheat, autosave jawaban).
- **Nilai & Profil**: Melihat riwayat nilai ulangan/ujian, melihat profil data pribadi.

### 4. Orang Tua (`peran = 'ortu'`)
- **Dashboard Orang Tua**: Multi-anak (pemilih anak / switcher jika memiliki lebih dari 1 siswa).
- **Karakter 7 Kebiasaan**: Pengisian ceklis harian pembiasaan anak di rumah (bangun pagi, ibadah, membaca, berolahraga, dll).
- **Pemantauan Akademik**: Melihat nilai perkembangan anak, kehadiran harian, catatan khusus guru.
- **Laporan TKA Orang Tua**: Melihat kartu kesiapan belajar anak beserta saran aktivitas pendampingan di rumah.

### 5. Kepala Sekolah (`peran = 'kepsek'`)
- **Dashboard Eksekutif**: Overview capaian akademik dan kedisiplinan seluruh sekolah.
- **Pantau Guru**: Monitoring progres pengisian nilai e-Rapor oleh guru kelas dan guru mapel.
- **Rekapitulasi**: Rekap nilai seluruh kelas, unduh CSV nilai agregat sekolah.
- **Peta Kelas & Aktivitas**: Monitoring penempatan rombel dan log aktivitas pengisian nilai/sistem.

### 6. Tenaga Kependidikan / Tendik (`peran = 'tendik'`)
- **Presensi Mandiri**: Akses langsung ke `/presensi/pegawai` (pencatatan jam masuk & pulang kerja terlindung radius geofence koordinat sekolah).

---

## 4. Alur Autentikasi & Keamanan

### Kredensial & Sesi
- **Identifikasi Login**: Menggunakan atribut `nama_pengguna` (bukan email). Kata sandi disimpan dengan enkripsi bcrypt (`BCRYPT_ROUNDS=12` di produksi, rounds=4 di testing). Kolom `kata_sandi` selalu disembunyikan via `$hidden`.
- **Force Password Change**: Pengguna yang baru dibuat oleh admin atau direset kata sandinya ditandai `force_password_change = true`, dan wajib mengganti kata sandi di `/force-password-change` sebelum bisa mengakses halaman fungsional apa pun.
- **Pendaftaran Mandiri Ditutup**: Route `/register` otomatis mengalihkan pengguna ke `/login` untuk mencegah pembuatan akun liar. Semua akun dibuat oleh Administrator Sekolah.
- **Anti-Brute Force**: Login dibatasi 10 percobaan per menit (`throttle:10,1`). Reset sandi dibatasi 5 per menit.
- **Rantai Audit Nilai e-Rapor**: Setiap modifikasi nilai e-Rapor dicatat dalam tabel `nilai_erapor_audit_logs` dengan format hash berantai (`prev_hash` -> `hash`) untuk mendeteksi manipulasi database manual (`php artisan nilai:audit:verifikasi`).

---

## 5. Middleware Stack

| Middleware Alias | Class Implementation | Fungsi |
|------------------|----------------------|--------|
| `role` | `App\Http\Middleware\RoleMiddleware` | Memverifikasi kecocokan `user()->peran` dengan parameter route |
| `force.password.change` | `App\Http\Middleware\ForcePasswordChange` | Memaksa user dengan flag belum ganti sandi ke halaman ganti sandi |
| `apikey` | `App\Http\Middleware\ApiKeyMiddleware` | Autentikasi token SHA-256 untuk API sinkronisasi Bridge |
| `module.asesmen` | `App\Http\Middleware\EnsureModuleEnabled` | Kill switch modul asesmen (CBT/Tryout/TKA) jika dinonaktifkan di konfigurasi |
| `cbt.access` | `App\Http\Middleware\CbtAccessMiddleware` | Kontrol akses jendela waktu dan partisipasi sesi ujian CBT |
| Global | `App\Http\Middleware\SecurityHeaders` | Menerapkan header X-Frame-Options, X-Content-Type-Options, CSP, dan HSTS |

---

## 6. Integrasi Eksternal

### A. Dapodik WebService (Tarik & Dorong Data)
- **Komunikasi Lokal**: Berjalan via `dapodik-bridge/` (skrip Python di komputer operator yang mengakses server lokal Dapodik di port 5774).
- **Pull Modul**: Mengimpor `getSekolah`, `getGtk`, `getPesertaDidik`, `getRombonganBelajar`, `getPembelajaran`.
- **Push Modul**: Mengirimkan pembaruan data balik ke Dapodik (`/admin/dapodik/push`): Sekolah, Siswa, GTK, Rombel, Jadwal, Nilai Rapor, dan Kehadiran.

### B. Google Sheets Backup Sync
- Menggunakan `google/apiclient` untuk mencadangkan data tabel transaksional secara real-time atau berkala ke spreadsheet Google Drive sekolah yang ditentukan di menu `/admin/sheet`.

### C. Cadangan & Pemulihan Mandiri (Backup/Restore)
- Menyimpan snapshot seluruh tabel penting ke dalam arsip JSON streaming berintegritas tinggi.
- Dilengkapi otomatisasi rotasi backup harian di cron job (02:00) dan verifikasi drill restore berkala (`BackupRestoreDrill`).

---

## 7. Engine Ujian Terpadu (`exam_*`)

Dalam fase penyempurnaan CBT dan Tryout, sistem telah menggunakan arsitektur **Engine Ujian Terpadu**:
- Tabel terpadu: `exam_packages`, `exam_sections`, `exam_questions`, `exam_sessions`, `exam_attempts`, `exam_answers`, `exam_violations`.
- Menghilangkan duplikasi logika penilaian dengan `ExamGradingService` dan `ExamAttemptService`.
- Melindungi ujian siswa dari insiden koneksi putus melalui sinkronisasi state jawaban lokal dan finalisasi server terjadwal (`cbt:finalize-expired`).
- Mendukung fitur analisis butir soal otomatis (`ExamItemAnalysisService`): tingkat kesukaran, daya pembeda, dan efektivitas pengecoh.
