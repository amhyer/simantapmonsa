# Desain Fondasi Wali Kelas, Filter Materi Siswa & E-Learning (Kelas Digital)

Dokumen desain teknis untuk **Fase 0 (Fondasi)** dan **Gelombang 5 (E-Learning / Kelas Digital)** SIMANTAP, disusun sesuai panduan [`PROMPT-ELEARNING-PERANGKAT-MENU.md`](PROMPT-ELEARNING-PERANGKAT-MENU.md) dan [`PETA-TUGAS-AI-GEMINI.md`](PETA-TUGAS-AI-GEMINI.md).

---

## 1. Flag Wali Kelas (`users.is_wali_kelas`)

### 1.1 Hasil Investigasi Sumber Kebenaran Eksisting
Pemeriksaan skema dan kode produksi (`app/Models/Rombel.php`, `app/Models/Siswa.php`, `app/Models/Ptk.php`, `app/Services/Dapodik/DapodikSyncService.php`, `app/Http/Controllers/Guru/IzinPresensiController.php`, `app/Http/Controllers/Guru/WaliKelasController.php`) menunjukkan:
1. **`rombel.guru_id`** adalah sumber kebenaran otoritatif wali kelas hasil sinkronisasi Dapodik maupun pengaturan rombel.
2. **`siswa.guru_id`** dipakai sebagai fallback untuk siswa aktif yang belum tertaut ke `rombel_id` (pola yang sama sudah dipakai oleh `IzinPresensiController::siswaWali()`).
3. **`ptk`** (`jenis_ptk`, `jabatan`) tidak memiliki relasi foreign key langsung ke `users.id` dan dapat berbeda penamaan antar-sekolah, sehingga tidak boleh dipakai untuk menebak akun wali kelas secara otomatis.
4. **`users`** sebelumnya belum memiliki penanda eksplisit wali kelas, sehingga seluruh guru (termasuk guru mapel non-wali) melihat grup **MENU WALI KELAS** di sidebar.

### 1.2 Keputusan Desain & Migrasi Aman
- Tambahkan kolom nullable boolean **`users.is_wali_kelas`** (default `null`) melalui migrasi aditif `2026_11_16_150000_add_is_wali_kelas_to_users_table.php`.
- **Tanpa backfill agresif / tebakan pada data lama:** baris lama tetap `NULL` dan dievaluasi secara dinamis dari data otoritatif:
  - `is_wali_kelas === true` → **Wali Kelas** (override eksplisit admin/pengelola).
  - `is_wali_kelas === false` → **Bukan Wali Kelas** (override eksplisit admin/pengelola).
  - `is_wali_kelas === null` → dihitung otomatis melalui `User::isWaliKelas()`:
    1. Bernilai `false` jika `peran !== 'guru'`.
    2. Bernilai `true` jika ada baris `rombel` (ter-scope tenant via `BelongsToTenant`) dengan `rombel.guru_id = $user->id`.
    3. Fallback aman untuk data sebelum rombel terbentuk: bernilai `true` jika ada baris `siswa` aktif (`aktif = true`, `archived_at IS NULL`, `rombel_id IS NULL`) dengan `siswa.guru_id = $user->id`.
    4. Selain itu bernilai `false`.
- Helper global **`guruAdalahWaliKelas(?User $user = null): bool`** di `app/helpers.php` membungkus pengecekan dengan aman (mengembalikan `false` saat tidak ada user login, seperti pada uji render partial view tanpa sesi).
- Grup **MENU WALI KELAS** pada `resources/views/guru/partials/sidebar.blade.php` hanya dirender saat `guruAdalahWaliKelas()` bernilai `true`, dan seluruh 4 rute duplikat (`guru.siswa.index`, `guru.kehadiran.index`, `guru.catatan.index`, `guru.erapor.index`) dibersihkan sehingga setiap rute hanya muncul sekali di sidebar Guru.

---

## 2. Perbaikan Filter Materi Siswa (`SiswaMateriVisibilityService`)

### 2.1 Kelemahan Sebelumnya
`App\Http\Controllers\Siswa\MateriController` sebelumnya hanya melakukan:
```php
Materi::where('guru_id', $siswa->guru_id)
```
Dampaknya:
1. Materi dari **guru mata pelajaran lain** (mis. PJOK, Pendidikan Agama, Bahasa Inggris) yang mengajar rombel/kelas siswa melalui `jadwal_pelajaran` tidak pernah tampil di akun siswa.
2. Jika wali kelas membuat materi untuk **kelas lain**, materi kelas lain tersebut ikut tampil kepada siswa (kebocoran lintas kelas / IDOR).

### 2.2 Aturan Visibilitas Baru (Multi-Tenant & SQLite/PostgreSQL Compatible)
`App\Services\Elearning\SiswaMateriVisibilityService::queryForSiswa(Siswa $siswa)` menerapkan aturan berikut (seluruh model sudah ter-scope `BelongsToTenant`):
1. Kumpulkan label kelas siswa ter-normalisasi (`strtolower(trim(...))`) dari `$siswa->kelas` dan `$siswa->rombel?->nama_rombel`.
2. Kumpulkan seluruh penugasan pengajar siswa dari `jadwal_pelajaran` yang cocok dengan `rombel_id = $siswa->rombel_id` atau `LOWER(kelas) IN ($kelasLabels)`. Setiap penugasan menghasilkan pasangan `(guru_id, LOWER(mata_pelajaran))`.
3. Tentukan ID wali kelas siswa (`$siswa->rombel?->guru_id`, atau fallback `$siswa->guru_id` saat `rombel_id` kosong).
4. Sebuah baris `materi` **boleh dilihat siswa** jika dan hanya jika:
   - Cocok dengan `tenant_id` siswa (dijaga `BelongsToTenant` + pengecekan eksplisit bila `$siswa->tenant_id !== null`), **DAN**
   - Jika siswa memiliki `kelas`/`rombel`: `LOWER(materi.kelas) IN ($kelasLabels)` **DAN** salah satu terpenuhi:
     - `(materi.guru_id, LOWER(materi.mata_pelajaran))` terdaftar pada `jadwal_pelajaran` siswa, **ATAU**
     - `materi.guru_id` adalah wali kelas siswa (agar materi tematik/guru kelas SD tetap muncul meski jadwal mapel rinci belum diinput).
   - Fallback terakhir hanya berlaku bila siswa sama sekali belum memiliki `kelas` maupun `rombel_id`: dibatasi ketat pada `materi.guru_id = $siswa->guru_id` dan `materi.kelas` kosong/null atau tidak berkonflik.
5. Query menggunakan `LOWER(...)` standar SQL — **tanpa `ILIKE` atau `to_char` khusus PostgreSQL** — sehingga 100% kompatibel dengan SQLite (PHPUnit) dan PostgreSQL 16 (produksi & CI).
6. Baik `index()` maupun `show($id)` menggunakan scope visibilitas yang sama persis sehingga akses langsung ke ID materi kelas lain atau sekolah lain ditolak (`404`).

---

## 3. Arsitektur E-Learning / Kelas Digital (Gelombang 5)

### 3.1 Prinsip Integrasi
- **Bukan LMS terpisah:** Kelas Digital berevolusi langsung dari `Materi`, memakai ulang `Kuis` (+ `HasilKuis`), dan mengalirkan penilaian tugas langsung ke tabel `nilai` (`jenis = 'Tugas'`, sesuai enum `['Tugas','Ulangan Harian','Praktik','PTS','PAS']`) sehingga langsung terlihat pada **Daftar Nilai** dan siap masuk ke perhitungan e-Rapor.
- **Pembelajaran Mendalam terjaga:** setiap `KelasDigitalModul` menyimpan `tujuan_pembelajaran`, `pm` (`berkesadaran`, `bermakna`, `menggembirakan`), `dimensi` (8 Dimensi Profil Lulusan), dan `pengalaman` (`memahami`, `mengaplikasi`, `merefleksi`).
- **Isolasi & Backup:** seluruh 7 tabel baru memiliki kolom `tenant_id`, `uuid`, dan `deleted_at` (`SoftDeletes`), memakai trait `BelongsToTenant`, memiliki `resolveRouteBinding` yang memvalidasi UUID (mencegah `SQLSTATE 22P02` di PostgreSQL), dan didaftarkan ke `App\Support\BackupTables` sesuai urutan foreign key induk → anak.

### 3.2 Skema Tabel (`2026_11_16_160000_create_kelas_digital_tables.php`)
| Tabel | Relasi Utama | Fungsi |
| :--- | :--- | :--- |
| `kelas_digital` | `guru_id`, `rombel_id` (nullable), `semester_id` (nullable) | Wadah kelas per guru + rombel/kelas + mata pelajaran |
| `kelas_digital_modul` | `kelas_digital_id` | Bab/unit belajar + metadata Pembelajaran Mendalam |
| `kelas_digital_pertemuan` | `modul_id`, `materi_id` (nullable), `kuis_id` (nullable) | Pertemuan berurutan; bisa menautkan `Materi` & `Kuis` eksisting |
| `kelas_digital_materi_item` | `pertemuan_id` | Konten butir: `teks`, `video` (embed/link), `file` (lampiran privat), `link` |
| `kelas_digital_tugas` | `pertemuan_id`, `kelas_digital_id`, `guru_id` | Tugas ber-deadline, lampiran soal, opsi `sinkron_ke_nilai` |
| `kelas_digital_pengumpulan` | `tugas_id`, `siswa_id`, `nilai_id` (nullable) | Jawaban teks/file siswa, status, nilai & catatan guru, tautan ke `nilai` |
| `kelas_digital_progress` | `pertemuan_id`, `siswa_id` | Penanda pertemuan selesai dibaca oleh siswa |

---

## 4. Kebijakan Upload File E-Learning (`ElearningFileService`)

| Aspek | Kebijakan |
| :--- | :--- |
| **Lokasi penyimpanan** | Disk `local` (`storage/app/private`) — **tidak pernah** di `public/storage`. Struktur folder: `elearning/tenant-{id|default}/{materi|tugas|pengumpulan}/YYYY/MM/{uuid}.{ext}`. |
| **Validasi server-side** | Memeriksa ekstensi + MIME aktual file di server (`mimes:` + `finfo`/MIME sniff), tidak mempercayai ekstensi dari client. Menolak eksplisit berkas eksekusi/skrip (`php`, `phtml`, `phar`, `js`, `html`, `htm`, `svg`, `exe`, `bat`, `sh`). |
| **Format diizinkan** | Dokumen & media ajar aman: `pdf`, `doc`, `docx`, `ppt`, `pptx`, `xls`, `xlsx`, `txt`, `jpg`, `jpeg`, `png`, `webp`, `mp3`, `mp4`. |
| **Batas ukuran** | Lampiran materi & tugas guru: maks **10 MB** (`10240 KB`). Pengumpulan tugas siswa: maks **5 MB** (`5120 KB`). |
| **Otorisasi unduhan** | Tidak ada URL publik langsung. Semua unduhan melewati endpoint controller terautentikasi (`guru.kelas-digital.*.download` & `siswa.kelas-digital.*.download`) yang memverifikasi kepemilikan guru atau hak akses siswa + tenant, lalu mengirim file dengan `Content-Disposition: attachment` dan `X-Content-Type-Options: nosniff`. |
| **Pembersihan file lama** | Saat siswa mengumpulkan ulang file tugas atau saat item/tugas dihapus oleh guru, file lama pada disk `local` dihapus aman melalui `ElearningFileService::deleteQuietly()`. |
