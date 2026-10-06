# E-Rapor & Pembelajaran Alignment Guide

> Induk rencana: `docs/09-ERAPOR-ALIGNMENT-PLAN.md` · Desain: `docs/10-ALIGNMENT-DESIGN.md`.
> Nomor `06` sudah terpakai, jadi panduan ini menjadi `11`.

## 1. Struktur Data

### Foreign Key Relationship

- `nilai_erapor.jadwal_pelajaran_id` → `jadwal_pelajaran.id`
- Nullable: nilai lama / belum terpetakan menyimpan `NULL` (bukan error).
- `ON DELETE SET NULL`: hapus jadwal **tidak** menghapus nilai; baris nilai kembali "belum terpetakan".
- Indeks: `(jadwal_pelajaran_id, semester, tahun_ajaran)`.

### Migration

| File | Isi |
|---|---|
| `2026_10_04_202238_add_jadwal_pelajaran_id_to_nilai_erapor_table.php` | Tambah kolom + FK + indeks; `down()`: drop FK → drop indeks → drop kolom |
| `2026_10_04_202251_populate_jadwal_pelajaran_id_on_nilai_erapor.php` | Isi FK untuk data lama (idempoten, chunk 500); `down()`: nullify tanpa hapus data |

### Relasi Model

```php
$nilai->jadwalPelajaran;      // belongsTo, null-safe
$jadwal->nilaiErapot;         // hasMany
```

## 2. Aturan Matching (satu-satunya yang berlaku)

1. Semester teks (`'Ganjil'`/`'Genap'` + `tahun_ajaran`) dipetakan dulu ke `semesters.id` (`lower(nama_semester)` + `tahun_ajaran` persis).
2. Teks `mata_pelajaran`/`kelas` dinormalisasi `lower(trim())` di kedua sisi.
3. Cocokkan `guru_id` + mapel + kelas + `semester_id`.
4. Jadwal ganda antar-hari → menangkan `id` terkecil, tercatat sebagai ambigu.
5. Implementasi tunggal: `App\Services\JadwalPelajaranResolver::resolve()` — dipakai controller guru; migration populate memakai logika identik berbasis query builder.

## 3. Alur Auto-Resolve (nilai baru selalu terpetakan)

- `Guru\NilaiErapotController@simpan`: resolve 1× per request → kolom FK ikut `updateOrCreate`.
- `Guru\NilaiErapotController@autoSave`: resolve per baris (sekaligus backfill organik nilai lama yang masih NULL).
- Nilai yang tak cocok (mapel 'Umum', semester tak dikenal) tersimpan normal dengan FK NULL.

## 4. Halaman Alignment-Check (admin)

Route: `GET admin/referensi/alignment` (`admin.referensi.alignment`), menu sidebar DATA REFERENSI → **Cek Alignment**.

- 4 kartu: total, terpetakan, belum terpetakan, persen.
- Tabel sebab belum-terpetakan: `semester_tak_cocok`, `guru_tak_punya_jadwal`, `mapel_tak_cocok`, `kelas_tak_cocok`.
- Peringatan jadwal ambigu (guru+mapel+kelas+semester sama di beberapa hari).
- Tabel Data Pembelajaran kini berkode badge per baris: hijau "N terpetakan" / abu "0".

## 5. Operasional Rutin

| Kebutuhan | Cara |
|---|---|
| Petakan ulang setelah perbaiki master | Simpan ulang nilai (auto-resolve), atau jalankan ulang migration populate (idempoten, hanya proses yang NULL) |
| Cek kesehatan | Buka halaman alignment-check; target persen 100% |
| Nilai bandel tak terpetakan | Lihat kategori sebab → perbaiki master (nama mapel/kelas/semester) atau isi manual via simpan ulang |
| Audit log populate | `storage/logs/laravel.log` — cari `Populate jadwal_pelajaran_id selesai` / `nilai_erapor tanpa pasangan jadwal` |

## 6. Troubleshooting

| Gejala | Penyebab umum | Aksi |
|---|---|---|
| Semua baru = unmatched | `semesters` belum diisi / TA beda format (`2025/2026` vs `2025-2026`) | Samakan data semester dulu |
| Mapel tak cocok massal | Nama guru ("Matematika") vs Dapodik ("MATEMATIKA (WAJIB)") | Normalisasi hanya lower/trim — selisih kata perlu dibetulkan di master |
| Persen turun setelah sync Dapodik | Jadwal diimpor ulang (id baru) | Jalankan ulang populate / simpan ulang nilai |
| `restrict` error saat hapus jadwal | FK diganti manual ke restrict | Kembalikan ke `nullOnDelete` (§4 desain) |
