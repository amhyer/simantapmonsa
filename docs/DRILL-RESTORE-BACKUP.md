# Drill Restore Backup — Bukti Sebelum Insiden

Backup yang belum pernah diuji restore belum bisa disebut siap insiden. Perintah
`simantap:backup:drill` memulihkan berkas backup **ke database SQLite sementara**,
lalu membandingkan jumlah baris, kelengkapan ID, dan relasi induk-anak. Database
aplikasi tidak disentuh.

## 1. Menjalankan drill

```bash
# Berkas terbaru di disk backup utama (BACKUP_DISK, default local)
php artisan simantap:backup:drill

# Berkas tertentu (relatif terhadap folder backups)
php artisan simantap:backup:drill --file=backup-simantap-2026-10-26-120000.json

# Salinan offsite (disk harus terdaftar di config/filesystems.php)
php artisan simantap:backup:drill --disk=s3

# Laporan mesin untuk arsip/CI
php artisan simantap:backup:drill --json > drill-restore-2026-10-26.json
```

Exit code `0` hanya bila **semua** tabel yang ada di backup memiliki jumlah baris
sama, seluruh ID backup ditemukan, dan tidak ada relasi yatim. Exit code `1`
berarti drill gagal dan wajib diselidiki sebelum go-live.

Opsi `--keep` menyimpan database sementara (path dicetak) untuk pemeriksaan
manual. Tanpa opsi itu, berkas sementara dihapus.

## 2. Yang dibuktikan

| Pemeriksaan | Arti |
|---|---|
| Jumlah baris per tabel | Tidak ada baris backup yang hilang saat restore |
| Kelengkapan ID | Setiap ID di backup benar-benar ada di database hasil restore |
| Relasi induk-anak | Siswa→user, CBT bank/soal/sesi/hasil, Tryout paket/mapel/soal/sesi/hasil, seluruh `exam_*` tidak yatim |

Drill memakai `App\Services\BackupRestoreService` yang **sama** dengan tombol
Import di Admin › Cadangan. Jadi keberhasilan drill menguji prosedur produksi,
bukan implementasi tiruan.

## 3. Batas yang belum dibuktikan

Drill ini membuktikan **logika restore dan konsistensi data**, bukan seluruh
prosedur insiden. Yang masih harus diuji di staging:

- [ ] Restore ke PostgreSQL versi produksi (drill memakai SQLite).
- [ ] Unduh/unggah dari penyimpanan offsite dengan kredensial nyata (`--disk=s3`).
- [ ] Waktu pemulihan (RTO) dan ukuran data produksi.
- [ ] Restore ke server bersih yang belum memiliki aplikasi/skema.
- [ ] Pencocokan jumlah **dan relasi** dengan database produksi setelah restore.
- [ ] Uji login akun setelah restore (sandi sengaja diacak; pengguna wajib ganti sandi).
- [ ] Simulasi kehilangan database primer, bukan hanya berkas backup.

## 4. Prosedur staging yang disarankan

1. Buat backup dari salinan data produksi di staging.
2. Unduh berkas yang sama dari disk offsite.
3. Jalankan drill terhadap berkas offsite; simpan laporan JSON.
4. Pulihkan berkas itu ke database staging kosong (bukan database aplikasi).
5. Bandingkan jumlah baris/relasi terhadap sumber; jalankan smoke test login,
   dasbor, CBT, Tryout, dan e-Rapor.
6. Catat waktu mulai/selesai sebagai RTO awal.
7. Tandatangani checklist di atas; simpan bukti bersama runbook go-live.

## 5. Jika drill gagal

1. Jangan menghapus backup lama; pertahankan berkas yang gagal sebagai bukti.
2. Periksa tabel yang berstatus `GAGAL` dan relasi dengan `yatim > 0`.
3. Ulangi dengan `--keep`, lalu periksa database sementara memakai klien SQLite.
4. Bila kegagalan berasal dari backup lama (kolom/tabel berubah), uji backup
   terbaru; bila tetap gagal, perbaiki restore **sebelum** menyentuh produksi.
5. Catat penyebab dan perbaikan di `docs/02-FIXES-LOG.md`.
