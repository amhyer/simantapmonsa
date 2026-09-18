# MENU & ROADMAP SIMANTAP — Usulan vs Kondisi Kode Saat Ini

> Dokumen ini menjawab: "menu apa yang sudah ada, apa yang belum, dan urutan kerja yang disarankan."
> Disusun dari pemetaan `routes/web.php`, 5 sidebar Blade, controller, dan view per role.
> Legenda: ✅ ada · ⚠️ sebagian/perlu verifikasi isi · ❌ belum ada.

## 1. Peta Menu Eksisting (ringkas)

- **Admin (17 menu + 1 aksi, gaya e-Rapor dengan submenu):** UTAMA: Dasbor Sistem · INTEGRASI DAPODIK: Web Service + Ambil Data (satu halaman, dua pintu) · PENGGUNA: Data Pengguna, Data Siswa · DATA REFERENSI (submenu): Data Sekolah, Data Guru (baru), Data Kelas, Data Mapel, Data Pembelajaran (baru) · PENGATURAN: Semester, Kode & Akses, Modul & Tampilan, Bobot & Ketuntasan · SISTEM & LOG: Penyimpanan Data, Data & Pemulihan, API Keys, Log Aktivitas, Keluar
- **Guru (17 menu):** Dasbor · Materi Ajar · Profil Lulusan (Dimensi) · Kesiapan TKA · Input Nilai Cepat · Input Nilai e-Rapor · Kuis & Soal · Daftar Nilai · Analisis Belajar · Kehadiran · Catatan Siswa · 7 Kebiasaan · Laporan & Rapor · Generate e-Rapor · Data Siswa · Google Sheet · Pengaturan
- **Siswa (5 menu):** Beranda · Materi · Kuis · Nilaiku · Profil Saya
- **Ortu (7 menu):** Ringkasan · Isi 7 Kebiasaan · Perkembangan Nilai · Rekap Kebiasaan · Kehadiran Anak · Catatan Guru · Laporan/Rapor
- **Kepsek (7 menu):** Dashboard · Rekap Sekolah · Peta Kelas · Hasil Belajar · Pantau Aktivitas · Rekap Kebiasaan · Log Aktivitas

Detail route→controller→view per menu ada di laporan pemetaan (arsip sesi ini).

## 2. Gap Analysis per Role

### 2.1 Administrator
| Usulan | Status | Catatan |
|---|---|---|
| Setting Web Service Dapodik | ⚠️ | Route `admin.dapodik.api.*` ada; verifikasi UI-nya di halaman Dapodik |
| Tarik Data Dapodik | ✅ | Import Dapodik + Jobs async sudah jalan |
| Data Referensi (gelar guru, hapus anggota rombel, mapel lokal) | ⚠️ | Hapus anggota rombel ✅ (`keluarkan`), mapel lokal ✅ (import-lokal/seed); gelar guru ❌ (kolom belum ada di tabel PTK) |
| Data Guru (master PTK) | ✅ | Baru: `admin.referensi.guru` (read-only, badge DAPODIK + peran) |
| Data Pembelajaran (jadwal) | ✅ | Baru: `admin.referensi.pembelajaran` (read-only; kosong sampai ada sync jadwal) |
| Mapping/urutan mapel rapor | ❌ | Belum ada pengaturan urutan tampil |
| Kelola Pengguna + Generate Akun Massal | ✅ | Lengkap + Jobs async |
| Reset Password & Status Login | ⚠️ | Reset kemungkinan di form edit user; halaman "siapa sedang login" ❌ |
| Bobot & KKM, Identitas Sekolah | ✅ | Lengkap |
| Referensi P5/Kokurikuler | ❌ | Belum ada modul P5 |
| Tanggal rapor & penanggalan | ❌ | Belum ada setting khusus |
| Upload center (logo, TTD, kop, foto massal) | ❌ | Belum ada; butuh kebijakan storage + validasi |
| Backup & Restore, Log Aktivitas | ✅ | Lengkap (export chunked + Jobs) |

### 2.2 Guru / Wali Kelas
| Usulan | Status | Catatan |
|---|---|---|
| TP per Mapel, Materi, Nilai P3 | ⚠️ | Materi punya `tujuan_pembelajaran`; manajer TP khusus ❌ |
| Bank Soal/Kuis, TKA, Nilai Harian | ✅ | Lengkap |
| Nilai Akhir Rapor + Deskripsi otomatis | ✅ | Lengkap |
| Nilai P5 / Ekskul | ❌ | Belum ada |
| Data Siswa, Kehadiran, Catatan Wali | ✅ | Lengkap |
| Kenaikan kelas | ❌ | Belum ada workflow |
| Pantau 7 Kebiasaan, rekap P3 | ✅/⚠️ | Pantau ✅; rekap lintas-mapel ⚠️ |
| Leger, Pelengkap+Rapor PDF | ✅/⚠️ | Generate PDF ✅; leger khusus ⚠️ verifikasi |
| Rapor P5, Transkrip Ijazah | ❌ | Belum ada |
| Kirim Nilai ke Dapodik (push-back) | ❌ | Sinkron saat ini satu arah (tarik); cek kapabilitas API bridge dulu |

### 2.3 Kepala Sekolah
| Usulan | Status | Catatan |
|---|---|---|
| Rekap Sekolah, Peta Kelas | ✅ | Lengkap (+unduh CSV) |
| Grafik perkembangan, statistik predikat | ⚠️ | Verifikasi isi halaman rekap/detail |
| Analisis TKA | ❌ | Hanya ada di sisi guru |
| P3 8 dimensi, P5 per kelas | ❌ | Belum ada |
| Rekap Kehadiran | ❌ | Kepsek tidak punya halaman kehadiran |
| 7 Kebiasaan | ✅ | Ada |
| Pantau aktivitas guru, log | ✅ | Lengkap |
| Status pengiriman ke Dapodik, cetak rekapitulasi | ❌/⚠️ | Status kirim ❌; unduh CSV ✅ |

### 2.4 Siswa
| Usulan | Status | Catatan |
|---|---|---|
| Profil & Rombel, ringkasan, materi, kuis, nilai | ✅ | Lengkap |
| Latihan TKA mandiri | ❌ | Route `siswa/tka` tidak ada (TKA hanya milik guru) |
| Grafik & P3 pribadi | ❌ | Belum ada halaman P3 siswa |
| Download Rapor PDF & Transkrip | ❌ | Belum ada |
| Daftar tugas aktif | ⚠️ | Kuis aktif ✅; tugas non-kuis ⚠️ |

### 2.5 Orang Tua
| Usulan | Status | Catatan |
|---|---|---|
| Dashboard, notifikasi catatan, isi 7 kebiasaan | ✅ | Lengkap |
| Rekap 14 hari (grafik) | ⚠️ | Halaman rekap ada; verifikasi grafiknya |
| Nilai, kehadiran | ✅ | Lengkap |
| Hasil kuis & TKA anak | ❌ | Tidak ada halaman kuis ortu |
| P3 anak | ❌ | Belum ada |
| Download rapor anak | ⚠️ | Halaman laporan ada; verifikasi tombol unduh + izin publikasi |

## 3. Temuan Teknis Kecil (terkait menu)
1. **Route yatim:** `kepsek.hasil-belajar.index` (`/kepsek/hasil-belajar`, `HasilBelajar@index`) tidak ter-link di sidebar; sidebar "Hasil Belajar" mengarah ke `kepsek.belajar.index` (`Dashboard@belajar`). Putuskan satu yang kanonis.
2. **Method mati:** `Admin\User@siswaIndex()` tidak punya route. Hapus atau beri route.
3. **Signature janggal:** `GET /guru/tka/{id}/analisis` → `TKA@analysis()` tanpa parameter `$id` (param diabaikan; semua ID tampil sama). Konfirmasi niat vs `show($id)`.
4. **Active-state sidebar:** admin/kepsek memakai `routeIs($route)` tanpa wildcard yang benar; sub-route tidak ter-highlight. Kosmetik.

## 4. Tanggapan & Prinsip
1. **Struktur usulan selaras** dengan arsitektur eksisting (±70% sudah ada). Ini kabar baik: kerja lanjutan bersifat menambah, bukan merombak.
2. **Jangan bangun fitur wali-kelas sebelum flag-nya ada.** Usulan menyebut banyak fitur "khusus Wali Kelas", tapi role `guru` saat ini tunggal. Butuh penanda wali (mis. kolom/relasi) + middleware/cek sebelum menu wali disembunyikan/ditampilkan.
3. **"Dirilis/dirilis publikasi" butuh workflow.** Download rapor bersyarat → tambah status publikasi per siswa/semester + UI rilis di sisi guru/wali. Tanpa ini, tombol download tidak punya aturan main.
4. **Samakan istilah dulu:** Dimensi = Profil Lulusan = P3 dipakai bergantian. Pilih satu untuk label menu + dokumentasi agar tidak membingungkan pengguna.
5. **Push nilai ke Dapodik berisiko scope-creep.** Verifikasi dulu API bridge mendukung tulis; kalau tidak, coret dari roadmap fase berjalan.
6. **Upload center butuh kebijakan keamanan** (tipe file, ukuran maks, lokasi storage, hapus file lama) sebelum dibangun.

## 5. Roadmap yang Disarankan
- **Fase 0 (±1 jam):** bereskan 4 temuan teknis di §3.
- **Fase 1 (fondasi):** flag wali kelas; flag publikasi rapor; standardisasi istilah P3; verifikasi semua sel ⚠️ di §2.
- **Fase 2 (fitur hilang prioritas):** TKA mandiri siswa; download rapor siswa/ortu + izin rilis; Nilai P5 + Ekskul; Rapor P5; Transkrip Ijazah; Upload center; Rekap Kehadiran kepsek; Analisis TKA kepsek.
- **Fase 3 (lanjutan):** grafik perkembangan multi-semester; workflow kenaikan kelas; mapping urutan mapel; tanggal rapor; push nilai ke Dapodik (jika API mendukung).

## 6. Cara Verifikasi Sel ⚠️ (untuk pemilik produk)
Buka sebagai peran terkait dan cek: Admin → Dapodik (setting WS), Mapel (kolom urutan?), Users (kolom status login?); Guru → Laporan (apakah ada Leger?), Nilai e-Rapor (rekap P3 lintas mapel?); Kepsek → Rekap/Detail (adakah grafik + sebaran predikat?); Ortu → Rekap (grafik 14 hari?) & Laporan (tombol unduh berfungsi?); Siswa → Dashboard (ringkasan + tugas aktif?).
