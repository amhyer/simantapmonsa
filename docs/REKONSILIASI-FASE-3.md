# Rekonsiliasi & Rollback Fase 3 (CBT/Tryout → `exam_*`)

Dokumen ini menjelaskan cara membuktikan isi data lama dan engine baru sama,
serta rencana rollback bila hasilnya tidak cocok. **Jangan menghapus tabel lama**
sebelum semua langkah di sini lulus di staging dengan salinan data produksi.

## 1. Perintah

```bash
# 1) Salin data lama ke engine (idempoten, tabel lama tidak diubah)
php artisan exam:backfill

# 2) Buktikan isi sama (read-only; exit code 1 bila ada beda)
php artisan exam:rekonsiliasi

# 3) Hanya satu area, atau laporan JSON untuk mesin/arsip
php artisan exam:rekonsiliasi --only=cbt
php artisan exam:rekonsiliasi --only=tryout --json > rekonstruksi-tryout.json
```

Perintah `exam:backfill` juga menerima:

| Opsi | Arti |
|---|---|
| `--dry-run` | Menjalankan seluruh penyalinan di dalam transaksi lalu **rollback**. Tidak ada perubahan yang disimpan; kolom Delta menunjukkan baris yang akan dibuat. |
| `--verify` | Tidak menyalin apa pun; hanya menjalankan rekonsiliasi dan keluar dengan kode 1 bila ada beda. |
| `--only=cbt\|tryout` | Membatasi lingkup. |

## 2. Apa yang dibandingkan

Rekonsiliasi memakai aturan atribut yang sama dengan `LegacyExamMapper`, sehingga
pembuat dan pemeriksa tidak dapat berbeda aturan.

| Area | Yang diperiksa |
|---|---|
| CBT bank/soal | `nama`, `mata_pelajaran`, `guru_id`, teks soal, poin, seluruh opsi (isi, kunci, urutan), relasi bank |
| CBT sesi | nama, kelas, jadwal, durasi, status, snapshot soal, peserta, relasi bank |
| CBT hasil | siswa/user, jadwal, status, nilai attempt, setiap jawaban (isi/benar/poin), nilai hasil, persentase, pelanggaran |
| Tryout paket/mapel/soal | kode/nama/meta paket, nama mapel, bobot/durasi/passing grade, seluruh atribut soal & opsi, relasi bank/section |
| Tryout sesi | kode, nama, kelas, jadwal, durasi, status, instruksi, relasi bank |
| Tryout hasil | attempt & hasil (nilai, persentase, peringkat, kelulusan, detail per mapel, analisis, rekomendasi), seluruh jawaban siswa |

Baris yang hilang, *soft-deleted*, atau berbeda satu kolom akan dilaporkan dengan
`area`, `tabel`, `id`, nama kolom, nilai harapan, dan nilai aktual (dipotong 120
karakter). Laporan detail dibatasi `--batas` (default 25); jumlah total masalah
tetap dihitung penuh.

## 3. Prosedur staging (wajib sebelum produksi)

1. Pulihkan salinan database produksi ke staging.
2. Catat jumlah baris tabel lama sebagai pembanding.
3. Jalankan `exam:backfill --dry-run`; periksa Delta.
4. Jalankan `exam:backfill`.
5. Jalankan `exam:rekonsiliasi --json`; simpan laporan sebagai bukti.
6. Nyalakan `SIMANTAP_EXAM_ENGINE_READERS=true` di staging.
7. Uji alur siswa: mulai ujian, jawab, selesai, lihat hasil; uji dasbor 4 peran,
   daftar/rincian hasil Tryout, dan ekspor CBT.
8. Matikan flag kembali bila ada beda; tabel lama masih utuh.

## 4. Rollback

### 4.1 Sebelum tabel lama dihapus (keadaan sekarang)

Rollback paling murah: **jangan nyalakan pembaca engine**.

1. Set `SIMANTAP_EXAM_ENGINE_READERS=false` (atau hapus variabelnya).
2. Set `SIMANTAP_EXAM_ENGINE_CBT=false` dan `SIMANTAP_EXAM_ENGINE_TRYOUT=false`
   bila ingin menghentikan dual-write.
3. Bersihkan `exam_*` hanya bila perlu; tabel lama tidak pernah diubah oleh
   backfill sehingga aplikasi langsung kembali ke data lama.
4. Tidak perlu restore database.

### 4.2 Setelah tabel lama dihapus (langkah 6e, belum dilakukan)

Rollback **wajib** memakai snapshot database, bukan membalik mapping:

1. Hentikan aplikasi/masukkan maintenance mode.
2. Pulihkan snapshot database terakhir yang masih memuat `cbt_*`/`tryout_*`.
3. Deploy ulang commit sebelum 6e.
4. Jalankan `exam:rekonsiliasi` untuk memastikan snapshot konsisten.
5. Baru nyalakan kembali lalu lintas.

Karena `exam_*` menyimpan `sumber_tabel` + `sumber_id`, data dapat ditelusuri,
tetapi **tidak ada perintah reverse-mapping** yang dijamin lengkap. Karena itu
snapshot database adalah satu-satunya rollback resmi untuk 6e.

## 5. Kriteria go/no-go 6e

- [ ] `exam:rekonsiliasi` exit 0 di staging dengan salinan produksi.
- [ ] Laporan JSON diarsipkan dan ditandatangani pemilik data.
- [ ] Alur siswa CBT & Tryout lulus di staging dengan flag pembaca engine.
- [ ] Ekspor/laporan yang masih memakai tabel lama sudah dipindahkan/diuji.
- [ ] Snapshot database pra-6e dibuat dan **diuji restore** (lihat panduan drill backup).
- [ ] Rencana rollback §4.2 disetujui operator.
