# Desain Teknis Gelombang 6 & 7: Perangkat Ajar (CP, TP, ATP, Prota, Promes, Modul Ajar, KKM, Jurnal) & Penataan Menu Lanjutan

Dokumen ini menjadi acuan implementasi **Gelombang 6** dan **Gelombang 7** SIMANTAP, selaras dengan [`PETA-TUGAS-AI-GEMINI.md`](PETA-TUGAS-AI-GEMINI.md), [`PROMPT-ELEARNING-PERANGKAT-MENU.md`](PROMPT-ELEARNING-PERANGKAT-MENU.md), dan [`DESAIN-ELEARNING-DAN-WALI-KELAS.md`](DESAIN-ELEARNING-DAN-WALI-KELAS.md).

---

## 1. Gelombang 6 — Perangkat Pembelajaran & Jurnal Mengajar

### 1.1 Cakupan Jenis Perangkat Ajar (Kurikulum Merdeka & Pembelajaran Mendalam)
Satu mesin terpadu (`perangkat_pembelajaran`) melayani seluruh jenis perangkat administrasi guru dengan template terstruktur & cetak PDF (DomPDF):

| Kode `jenis` | Nama Perangkat | Struktur Inti pada Kolom `konten` (JSON) |
| :--- | :--- | :--- |
| `cp` | **CP (Capaian Pembelajaran)** | `elemen`, `capaian_pembelajaran`, `kompetensi_inti`, `konten_materi`, `catatan_fase` |
| `tp` | **TP (Tujuan Pembelajaran)** | `elemen`, `capaian_rujukan`, `daftar_tp` (`kode`, `deskripsi`, `materi`, `jp`), `indikator_ketercapaian` |
| `atp` | **ATP (Alur Tujuan Pembelajaran)** | `elemen`, `alur_item` (`urutan`, `kode_tp`, `tujuan_pembelajaran`, `materi_pokok`, `alokasi_jp`, `semester`, `dimensi_profil`), `prasyarat` |
| `prota` | **Prota (Program Tahunan)** | `daftar_unit` (`semester`, `nomor_unit`, `materi_topik`, `alokasi_jp`, `keterangan`) |
| `promes` | **Promes (Program Semester)** | `bulan_efektif`, `daftar_minggu` (`bulan`, `minggu_ke`, `materi_topik`, `alokasi_jp`, `keterangan`), `catatan_kalender` |
| `modul_ajar` | **Modul Ajar / RPP (Pembelajaran Mendalam)** | `tujuan_pembelajaran`, `pm` (`berkesadaran`, `bermakna`, `menggembirakan`), `dimensi` (8 Dimensi Profil Lulusan), `pengalaman` (`memahami`, `mengaplikasi`, `merefleksi`), `pendahuluan`, `kegiatan_inti`, `penutup`, `asesmen`, `media_sumber`, `refleksi_guru` |
| `kkm` | **KKM / KKTP** | `kompleksitas`, `daya_dukung`, `intake`, `nilai_kkm`, `interval_perlu_bimbingan`, `interval_cukup`, `interval_baik`, `interval_sangat_baik`, `catatan_penilaian` |

Selain itu tersedia tabel **`jurnal_mengajar`** untuk pencatatan harian guru (tanggal, jam ke, kelas/rombel, mapel, pertemuan ke, topik, ringkasan kegiatan, jumlah hadir/tidak hadir, catatan & tindak lanjut) yang dapat ditautkan ke `perangkat_pembelajaran` maupun `kelas_digital`, serta dicetak sebagai rekap PDF DomPDF.

### 1.2 Skema & Isolasi Multi-Tenant
- Kedua tabel (`perangkat_pembelajaran`, `jurnal_mengajar`) wajib memakai:
  - `id`, `uuid` (unik, route key dengan guard `Str::isUuid` anti-PG `22P02`),
  - `tenant_id` (nullable FK ke `tenants`, `BelongsToTenant`),
  - `timestamps()` dan `softDeletes()`,
  - registrasi pada `App\Support\BackupTables`.
- Lampiran opsional pada `perangkat_pembelajaran` disimpan melalui `App\Services\Elearning\ElearningFileService` pada disk privat `local` dengan validasi MIME server-side dan header unduhan `X-Content-Type-Options: nosniff`.

### 1.3 Matriks Otorisasi
- **Guru pemilik (`guru_id = user.id`, tenant sama):** CRUD penuh + cetak PDF + unduh lampiran.
- **Wali Kelas (`user.isWaliKelas()`):** hak baca **read-only** (lihat detail, unduh lampiran, cetak PDF) terhadap perangkat guru lain pada tenant yang sama dengan `dibagikan_ke_walas = true` dan `rombel_id`/label `kelas` cocok dengan rombel yang diampu wali kelas. Tidak dapat mengubah atau menghapus.
- **Kepala Sekolah (`kepsek`) & Admin (`admin`):** hak supervisi **read-only** lintas guru di sekolah (`tenant_id`) yang sama + cetak PDF.
- **Peran lain (`siswa`, `ortu`, `tendik`) atau sekolah lain:** ditolak (`403` / `404`).

### 1.4 Pengelolaan Flag Wali Kelas di Admin
- Form Tambah/Ubah Akun (`admin.users.create` / `admin.users.edit`) menyediakan pilihan eksplisit `Status Wali Kelas` (`null` Otomatis dari Rombel / `1` Ya / `0` Bukan) khusus untuk akun berperan `guru`.

---

## 2. Gelombang 7 — Penataan Menu Lanjutan, Pencarian `Ctrl+K`, Validasi Data & Pusat Tugas

1. **Satu Sumber Kebenaran Pencarian Menu (`App\Services\Navigation\CommandPaletteService`):**
   - Menghasilkan daftar menu cepat (`Ctrl+K`) sesuai peran, status `guruAdalahWaliKelas()`, feature flag (`modulAsesmenAktif()`, `kesiapanTkaAktif()`), mode multi-sekolah (`TenantMode::isMultiSchool()`), dan keberadaan rute (`Route::has`).
   - Memastikan tidak ada satu pun rute 403/404/500 muncul di hasil pencarian pengguna mana pun.
2. **Validasi & Kelengkapan Data Admin (`admin.validasi-data.index`):**
   - Pemeriksaan read-only terisolasi `tenant_id`: siswa aktif tanpa rombel, rombel tanpa wali kelas, jadwal tanpa guru/rombel, siswa aktif tanpa akun login, dan kelengkapan profil sekolah, disertai tautan langsung ke halaman perbaikan.
3. **Pusat Notifikasi & Tugas Ringan (`App\Services\Navigation\RoleTaskSummaryService`):**
   - Hanya mengembalikan skalar/array (tidak pernah men-cache model Eloquent) dan tidak membocorkan informasi/alasan sensitif pada pratinjau notifikasi.
   - Menyediakan ringkasan relevan: tugas Kelas Digital belum dinilai (guru), tugas belum dikumpulkan (siswa), status sinkron & kelengkapan data (admin), dan supervisi perangkat/aktivitas (kepsek).
