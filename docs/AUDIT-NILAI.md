# Jejak Audit Nilai e-Rapor

Dokumen ini menjelaskan kontrol integritas untuk perubahan `nilai_erapor`.
Tujuannya bukan menggantikan backup, melainkan membuat perubahan nilai dapat
dibuktikan dan diperiksa setelah kejadian.

## Cakupan

Observer `App\Observers\NilaiErapotObserver` mencatat:

| Event Eloquent | Aksi audit | Contoh jalur |
|---|---|---|
| `created` | `dibuat` | input nilai baru, import, sinkronisasi |
| `updated` | `diperbarui` | guru mengubah nilai, sinkronisasi e-Rapor |
| `deleted` | `dihapus` | koreksi/hapus nilai |

Snapshot menyimpan kolom akademik penting (siswa, guru, jadwal, mapel, kelas,
semester, nilai formatif/sumatif/akhir, predikat, deskripsi, status kirim) dan
kolom `created_at`/`updated_at` baris nilai.

Yang **tidak** tercatat otomatis: perubahan lewat raw SQL (`DB::table(...)`)
atau tool eksternal yang menulis langsung ke database. Jalur aplikasi harus
memakai model Eloquent.

## Rantai hash

Setiap catatan memiliki `prev_hash` (hash catatan sebelumnya untuk baris nilai
yang sama) dan `hash`:

```text
hash = SHA-256( payload )
payload = nilai_erapor_id + siswa_id + guru_id + aksi + status +
          snapshot + prev_hash + dicatat_at
```

`payload` diurutkan secara deterministik sebelum di-hash. Jika satu angka di
dalam `snapshot` diubah langsung di database, verifikasi akan menandai
`hash_tidak_cocok`. Jika catatan dihapus atau urutannya diubah, verifikasi
menandai `prev_hash_tidak_cocok`.

`nilai_erapor_id` sengaja tidak memakai foreign key agar riwayat tetap ada
setelah baris nilai dihapus.

## Memeriksa

```bash
# Semua catatan
php artisan nilai:audit:verifikasi

# Satu baris nilai
php artisan nilai:audit:verifikasi --nilai=123

# Mesin/otomasi: JSON + exit code
php artisan nilai:audit:verifikasi --json
```

Exit code `0` berarti rantai utuh; selain itu ada masalah yang harus diselidiki.
Perintah ini **read-only** dan tidak memperbaiki hash.

## Backup dan restore

`nilai_erapor_audit_logs` termasuk dalam `App\Support\BackupTables`, sehingga ikut
export, wipe, dan restore. Selama restore, observer dinonaktifkan sementara
(`NilaiErapotAuditService::tanpaAudit`) agar proses restore tidak membuat catatan
baru di tengah pemulihan catatan lama.

Setelah restore produksi/staging:

```bash
php artisan nilai:audit:verifikasi
```

Jika hasilnya rusak, jangan mengubah hash secara manual. Pertahankan database,
file backup, dan log; bandingkan dengan snapshot backup terakhir.

## Batas yang harus disadari

- Rantai hash mendeteksi perubahan yang tidak disertai perhitungan ulang seluruh
  rantai. Administrator database yang berniat buruk tetap dapat menghitung ulang
  hash; ini bukan notaris eksternal atau WORM storage.
- `ip_address` dan `user_agent` adalah konteks tambahan, bukan bukti identitas.
- Status akademik `draft -> disahkan -> terbit` dan snapshot saat publish belum
  diimplementasikan. Saat ini yang tersedia adalah jejak perubahan nilai.
- Backup offsite dan drill restore nyata tetap wajib diuji dengan kredensial
  staging/produksi; lihat `docs/DRILL-RESTORE-BACKUP.md`.
