# DESAIN: E-Rapor & Pembelajaran Alignment (Fase 1.2)

> Induk rencana: `docs/09-ERAPOR-ALIGNMENT-PLAN.md`.
> Status: ✅ Fase 1 selesai 2026-10-04. Acuan wajib sebelum eksekusi Fase 2.

## 1. Bukti audit Fase 1.1 (DB lokal `simantap_db`, pgsql)

| Metrik | Hasil |
|---|---|
| `nilai_erapor` total | 0 baris |
| `jadwal_pelajaran` total | 0 baris |
| `semesters` | 1 baris (`20261 / 2026-2027 / ganjil`) |
| Orphan estimate | 0 (tidak ada data untuk di-orphan-kan) |
| `nilai_erapor.guru_id` tak ada di `users` | 0 |
| Backup baseline | `storage/app/backups/baseline-pra-alignment-20261004.dump` (5,4 MB, custom format) |
| Verifikasi backup | TOC 471 entri terbaca + restore uji ke `simantap_restore_test` sukses (0/0 baris sesuai sumber), scratch DB sudah dihapus |

**Kesimpulan audit:** risiko bukan orphan eksisting, melainkan **aturan matching yang salah di rencana awal** (lihat §3). Wajib pakai aturan koreksi di bawah saat Fase 2.2, dan ulangi audit ini di database production/staging yang berisi data sebelum populate.

## 2. Fakta skema (terverifikasi dari model + migration)

| Aspek | `nilai_erapor` | `jadwal_pelajaran` |
|---|---|---|
| Guru | `guru_id` → `users.id` | `guru_id` → `users.id` |
| Mapel | `mata_pelajaran` TEXT bebas (input guru, mis. "Umum") | `mata_pelajaran` TEXT (dari Dapodik) |
| Kelas | `kelas` TEXT bebas | `kelas` TEXT + `rombel_id` FK |
| Semester | `semester` TEXT (`'Ganjil'` kapital) + `tahun_ajaran` TEXT (`'2025/2026'`) | `semester_id` FK → `semesters.id` (integer!) |
| Tabel semester | — | `semesters(id, semester_id='20261', tahun_ajaran, nama_semester enum 'ganjil'/'genap' lowercase) |
| FK baru | `jadwal_pelajaran_id` nullable → `jadwal_pelajaran.id` | — |

## 3. Aturan matching KOREKSI (pengganti skrip populate di rencana awal)

Skrip awal salah: `where('semester_id', $nilai->semester)` membandingkan FK integer dengan teks `'Ganjil'` → **tidak akan pernah cocok** (0 matched, semua unmatched diam-diam).

Aturan yang benar:

1. Petakan semester dulu: `semesters` WHERE `lower(nama_semester) = lower(nilai.semester)` AND `tahun_ajaran = nilai.tahun_ajaran` → koleksi `semesters.id`. Jika kosong → unmatched kategori `semester_tak_cocok` (jangan paksa null-semester match).
2. Normalisasi teks: `lower(trim(...))` untuk `mata_pelajaran` dan `kelas` di kedua sisi.
3. Cocokkan `jadwal_pelajaran` WHERE `guru_id` + mapel + kelas + `semester_id` IN (hasil langkah 1).
4. **Ambiguitas hari:** satu kombinasi guru+mapel+kelas+semester bisa muncul di beberapa hari (`Senin`, `Selasa`, …). FK menunjuk SATU baris → ambil `orderBy('id')->first()`, catat jumlah kandidat di log (`ambiguous_count`) untuk ditindaklanjuti manual.
5. Kategori unmatched untuk laporan: `guru_tak_punya_jadwal` | `mapel_tak_cocok` | `kelas_tak_cocok` | `semester_tak_cocok`.
6. Idempoten: hanya proses `whereNull('jadwal_pelajaran_id')`; aman dijalankan ulang.

## 4. Desain foreign key

```php
$table->foreignId('jadwal_pelajaran_id')
      ->nullable()
      ->after('guru_id')
      ->constrained('jadwal_pelajaran')
      ->nullOnDelete();   // revisi dari 'restrict' di rencana awal
$table->index(['jadwal_pelajaran_id', 'semester', 'tahun_ajaran']);
```

Alasan revisi `restrict` → `nullOnDelete`: jadwal Dapodik diimpor ulang rutin (hapus-tulis); `restrict` akan menggagalkan sinkronisasi Dapodik begitu ada nilai terkait. `nullOnDelete` menjaga nilai tetap ada + tercatat orphan untuk dipetakan ulang. Jika tim tetap ingin proteksi hapus manual, proteksi di level controller admin (konfirmasi), bukan FK.

Relasi model:

```php
// NilaiErapot
public function jadwalPelajaran() { return $this->belongsTo(JadwalPelajaran::class); }
// JadwalPelajaran
public function nilaiErapot() { return $this->hasMany(NilaiErapot::class); }
```

Tambahkan `'jadwal_pelajaran_id'` ke `$fillable` NilaiErapot.

## 5. Backward compatibility

- Kolom **nullable** → semua insert/update eksisting tetap jalan tanpa perubahan kode.
- Semua query eksisting berbasis `guru_id`/`mata_pelajaran`/`kelas` tidak berubah; FK hanya dipakai fitur baru (alignment check, validasi input).
- Kode baru yang membaca relasi **wajib null-safe** (`?->`, `withDefault()` dilarang menutupi data hilang — tampilkan badge "belum terpetakan").
- `down()` migration: `dropForeign` + `dropColumn` (bukan `dropForeignKeyIfExists` — method itu tidak ada).

## 6. Strategi rollback

| Skenario | Prosedur |
|---|---|
| Batal sebelum populate | `php artisan migrate:rollback --step=1` (kolom hilang, data utuh) |
| Batal setelah populate | rollback 2 step (kolom + reset), atau jalankan `down()` populate (nullify) saja bila kolom ingin dipertahankan |
| Korupsi data / darurat | `pg_restore -d simantap_db baseline-pra-alignment-*.dump` (prosedur terbukti 2026-10-04). Wajib backup ulang sesaat sebelum eksekusi Fase 2 di environment target |
| Perintah backup | `$env:PGPASSWORD='...'; pg_dump -h 127.0.0.1 -p 5432 -U postgres -F c -f storage/app/backups/pra-alignment-<tanggal>.dump simantap_db` |

## 7. Syarat masuk Fase 2

- [x] Audit F1.1 (metodologi teruji; ulangi di DB berisi data)
- [x] Backup + verifikasi restore (prosedur teruji)
- [x] Aturan matching koreksi (§3) disetujui
- [x] Keputusan `nullOnDelete` vs `restrict` disetujui pemilik produk → **`nullOnDelete`** (dieksekusi Fase 2, 2026-10-04)
- [ ] Backup fresh di environment target sesaat sebelum migrate
