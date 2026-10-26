# Checklist Validasi Integrasi di Staging

Dokumen ini dipakai **sebelum** produksi. Semua perintah bersifat read-only
kecuali yang disebut eksplisit. Jangan menjalankan `exam:backfill` atau
`sheets:full-sync` di produksi tanpa persetujuan pemilik data.

## 1. Persiapan

- [ ] Staging memakai salinan database produksi (disamarkan bila perlu).
- [ ] `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL` staging benar.
- [ ] Mail staging dikonfigurasi dan dapat mengirim ke alamat uji.
- [ ] Kredensial Dapodik dan Google **staging** tersedia; jangan memakai kredensial produksi.
- [ ] Backup database staging dibuat sebelum uji tulis.

## 2. Integrasi Dapodik

```bash
php artisan integrasi:dapodik:cek
php artisan integrasi:dapodik:cek --live
php artisan integrasi:dapodik:cek --live --json > dapodik-check.json
```

Yang diperiksa: NPSN, token, host/port/protokol, status auto-sync, dan (dengan
`--live`) satu panggilan `getSekolah` read-only. Exit code `1` berarti konfigurasi
belum siap atau koneksi gagal.

- [ ] Exit code `0`.
- [ ] Nama sekolah pada laporan sama dengan sekolah staging.
- [ ] Tidak ada pesan auto-sync gagal/kedaluwarsa.
- [ ] Token **tidak** muncul di log/console (hanya bentuk masked).
- [ ] Uji satu import Dapodik di UI staging, lalu periksa jumlah siswa/guru.
- [ ] Pastikan akun hasil import wajib ganti sandi.

## 3. Integrasi Google Sheets

```bash
php artisan integrasi:sheets:cek
php artisan integrasi:sheets:cek --live
php artisan integrasi:sheets:cek --live --json > sheets-check.json
```

Yang diperiksa: sambungan di pengaturan sekolah, Spreadsheet ID/URL, kredensial
service account, dan (dengan `--live`) daftar tab. Perintah ini **tidak** menulis
atau membuat tab.

- [ ] Exit code `0`.
- [ ] Spreadsheet staging dapat dibuka oleh service account.
- [ ] Semua 14 tab wajib ada; jika tidak, jalankan `php artisan sheets:full-sync`
      di staging lalu ulangi pemeriksaan.
- [ ] Data sekolah tidak bocor ke spreadsheet produksi.
- [ ] Uji satu perubahan data di staging dan pastikan hanya sheet staging berubah.

## 4. Reset sandi mandiri

```bash
php artisan migrate --force
```

- [ ] Halaman `/lupa-sandi` terbuka.
- [ ] Akun uji dengan email menerima tautan dan dapat mengganti sandi.
- [ ] Sandi lama gagal, sandi baru berhasil.
- [ ] Akun tanpa email mendapat arahan menghubungi admin.
- [ ] Tautan kedaluwarsa/berubah token ditolak.

Detail: [`RESET-SANDI-MANDIRI.md`](RESET-SANDI-MANDIRI.md).

## 5. Backup dan rekonsiliasi

```bash
php artisan simantap:backup:drill
php artisan exam:backfill --dry-run
php artisan exam:backfill
php artisan exam:rekonsiliasi --json > rekonsiliasi.json
```

- [ ] Drill restore lulus (jumlah, ID, relasi) pada backup staging terbaru.
- [ ] Dry-run tidak mengubah jumlah baris.
- [ ] Rekonsiliasi exit `0` pada salinan data produksi.
- [ ] Laporan JSON diarsipkan.
- [ ] Rollback plan dibaca dan disetujui: [`REKONSILIASI-FASE-3.md`](REKONSILIASI-FASE-3.md).

## 6. Uji alur pengguna

- [ ] Admin: login, tambah email akun, reset sandi pengguna.
- [ ] Guru: login, buat materi/kuis/nilai, lihat dasbor.
- [ ] Siswa: login, kerjakan CBT/Tryout, lihat hasil.
- [ ] Orang tua: login, lihat rekap.
- [ ] Kepala sekolah: dasbor dan rekap.
- [ ] Ekspor/laporan yang masih memakai tabel lama tetap benar.
- [ ] Flag pembaca engine dinyalakan, alur diulang, lalu bandingkan hasil.

## 7. Uji beban

```bash
LOAD_TEST_PASSWORD='...' php artisan db:seed --class=LoadTestSeeder
BASE_URL=https://staging.sekolah.sch.id k6 run tests/load/k6-smoke.js
```

- [ ] Smoke publik lulus (10 VU/30 detik).
- [ ] Login + dashboard lulus (3 VU/1 menit).
- [ ] Tidak ada error 500/502 atau connection pool habis.
- [ ] Hasil p95/p99 dicatat di laporan go-live.

Detail: [`LOAD-TEST.md`](LOAD-TEST.md).

## 8. Sinyal go/no-go

Go hanya jika:

- [ ] Semua perintah di atas exit `0` (kecuali item yang memang belum tersedia).
- [ ] Tidak ada error 500 di log staging selama uji alur.
- [ ] Backup drill dan rekonsiliasi lulus pada salinan produksi.
- [ ] Pemilik data menyetujui laporan rekonsiliasi.
- [ ] Rencana rollback dan kontak eskalasi tersedia.
