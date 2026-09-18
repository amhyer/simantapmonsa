# SIMANTAP — Referensi Kolom Database

**Tanggal:** 13 September 2026

---

## ⚠️ Kolom yang Sering Salah Akses

### `siswa` table
```
✅ ADA: telepon
❌ TIDAK ADA: no_hp, no_hp_ortu

✅ ADA: nama_orang_tua
❌ TIDAK ADA: nama_ortu, ortu_nama
```

### `users` table
```
✅ ADA: nama_lengkap, nama_pengguna, kata_sandi, peran
❌ TIDAK ADA: name, email, password, role
```

### `semesters` table
```
✅ ADA: semester_id, tahun_ajaran, nama_semester
❌ TIDAK ADA: aktif, is_active
```

### `dimensi` table
```
✅ ADA: id, uuid, guru_id, siswa_id, nama_guru, nama_siswa, no_dimensi, dimensi, skor, predikat, catatan, rekaman
```

### `nilai` table
```
✅ ADA: id, uuid, guru_id, siswa_id, nama_guru, nama_siswa, tanggal, jenis, judul_penilaian, mata_pelajaran, nilai, kuis_id, rekaman
❌ TIDAK ADA: kelas, semester, tahun_ajaran
```

### `pengaturan_guru` table
```
✅ ADA: id, guru_id, nama_guru, mata_pelajaran, kelas, kkm, semester, tahun_pelajaran, fase, pembaruan, pengaturan, sistem
```

### `kehadiran` table
```
✅ ADA: id, uuid, guru_id, siswa_id, tanggal, status, keterangan, rekaman
```

---

## Query Lengkap Semua Tabel

### siswa (35 kolom)
```sql
id, uuid, guru_id, nama_guru, nis, nama_peserta_didik, kelas, jenis_kelamin,
nama_orang_tua, aktif, telepon, nik, nisn, no_kk, agama, tempat_lahir,
tanggal_lahir, alamat, status_siswa, is_active, archived_at, rekaman,
created_at, updated_at, dapodik_id, semester_id, foto, email,
alamat_orang_tua, pekerjaan_orang_tua, telepon_orang_tua,
pendidikan_orang_tua, nama_sekolah_sebelumnya, tahun_masuk, jurusan,
extras (JSON)
```

### users (13 kolom)
```sql
id, uuid, nama_lengkap, nama_pengguna, kata_sandi, peran,
terhubung_dengan, kelas_mata_pelajaran, aktif, terakhir_masuk,
dapodik_id, is_active, archived_at, created_at, updated_at
```

### semesters (5 kolom)
```sql
id, semester_id, tahun_ajaran, nama_semester, created_at, updated_at
```

### dimensi (12 kolom)
```sql
id, uuid, guru_id, siswa_id, nama_guru, nama_siswa, no_dimensi,
dimensi, skor, predikat, catatan, rekaman, created_at, updated_at
```

### nilai (14 kolom)
```sql
id, uuid, guru_id, siswa_id, nama_guru, nama_siswa, tanggal, jenis,
judul_penilaian, mata_pelajaran, nilai, kuis_id, rekaman,
created_at, updated_at
```

### pengaturan_guru (14 kolom)
```sql
id, guru_id, nama_guru, mata_pelajaran, kelas, kkm, semester,
tahun_pelajaran, fase, pembaruan, pengaturan, sistem,
created_at, updated_at
```

### kehadiran (8 kolom)
```sql
id, uuid, guru_id, siswa_id, tanggal, status, keterangan,
rekaman, created_at, updated_at
```

### kuis (18 kolom)
```sql
id, uuid, guru_id, nama_guru, judul, soal, opsi_a, opsi_b, opsi_c,
opsi_d, jawaban_benar, kelas, mata_pelajaran, tanggal, aktif,
tipe_soal, jumlah_soal, rekaman, created_at, updated_at
```

### materi (12 kolom)
```sql
id, uuid, guru_id, nama_guru, tanggal, judul, mata_pelajaran, kelas,
deskripsi, file_path, tipe_file, rekaman, created_at, updated_at
```

### api_keys (7 kolom)
```sql
id, user_id, name, key, abilities, active, last_used_at,
created_at, updated_at
```

### dapodik_sync_logs (9 kolom)
```sql
id, modul, tipe, jumlah_data, status, pesan, waktu_mulai,
waktu_selesai, created_at, updated_at
```

### backups (8 kolom)
```sql
id, user_id, nama_file, ukuran, status, keterangan,
created_at, updated_at
```
