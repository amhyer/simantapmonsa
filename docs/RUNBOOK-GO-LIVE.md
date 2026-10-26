# Runbook Go-Live dan Rollback

Runbook ini adalah prosedur operasional, bukan bukti bahwa langkah eksternal
sudah dijalankan. Setiap centang harus disertai tanggal, nama pelaksana, dan
bukti (log, tiket, atau tangkapan layar).

## 1. Prinsip

- Tidak ada go-live tanpa backup yang dapat direstore dan sudah diuji.
- Kredensial yang pernah masuk repository dianggap bocor dan wajib dirotasi.
- Rekonsiliasi data harus membuktikan isi/relasi, bukan hanya jumlah baris.
- Tabel legacy `cbt_*`/`tryout_*` tidak dihapus pada go-live pertama. Penghapusan
  adalah perubahan terpisah setelah masa observasi dan persetujuan pemilik data.
- Uji beban hanya di staging dengan data mirip produksi; jangan pernah ke produksi.
- Perubahan aplikasi dan perubahan data adalah dua keputusan rollback yang berbeda.

## 2. Peran dan jadwal

| Peran | Tanggung jawab |
|---|---|
| Pemilik layanan | menyetujui go/no-go dan rollback |
| Operator database | backup, restore, migrasi, verifikasi data |
| Operator aplikasi | deploy, cache, queue, health check |
| Guru/admin perwakilan | uji fungsional setelah deploy |
| Keamanan/IT | rotasi kredensial dan pemeriksaan akses |

Tetapkan maintenance window, nomor insiden, dan kanal komunikasi sebelum mulai.
Bekukan perubahan kode selama jendela deploy.

## 3. Preflight wajib

### 3.1 Kode dan konfigurasi

- [ ] Commit yang akan dideploy sudah ada di `origin/main` dan CI hijau.
- [ ] `composer install --no-dev --optimize-autoloader` selesai tanpa error.
- [ ] `npm ci && npm run build` selesai; `public/build/manifest.json` ada.
- [ ] `.env` produksi:
  - [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
  - [ ] `DB_CONNECTION=pgsql`, host/port/database/user sesuai server
  - [ ] `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`
  - [ ] `MAIL_*` nyata jika reset sandi mandiri dipakai
  - [ ] `BACKUP_DISK` dan kredensial offsite terisi
  - [ ] `SIMANTAP_CBT_ENABLED` sesuai keputusan rilis
  - [ ] `SIMANTAP_EXAM_ENGINE_CBT`/`TRYOUT` sesuai bridge yang disetujui
  - [ ] `SIMANTAP_EXAM_ENGINE_READERS=false` sampai rekonsiliasi staging disetujui
- [ ] `php artisan about` dan `php artisan config:show simantap` diperiksa tanpa
      membocorkan rahasia ke log/tiket.

### 3.2 Kredensial

- [ ] Ikuti `docs/ROTASI-KREDENSIAL.md`.
- [ ] API key/token Dapodik dan Google yang pernah di repository sudah dicabut.
- [ ] Kredensial baru disimpan di secret manager/`.env` server, bukan git.
- [ ] Akses akun lama dicabut; aktivitas API dicatat.

### 3.3 Backup dan restore

- [ ] Backup penuh produksi terbaru dibuat sebelum migrasi.
- [ ] Backup terbaru diunduh/diverifikasi checksum dan ukurannya.
- [ ] `php artisan simantap:backup:drill` lulus pada salinan/backup tersebut.
- [ ] Restore ke staging (bukan produksi) membuktikan jumlah baris, ID kunci, dan
      relasi utama (siswa, guru, nilai, ujian, hasil).
- [ ] Rencana rollback data menggunakan snapshot/restore yang sama, bukan
      `migrate:rollback` sebagai satu-satunya rencana.

### 3.4 Integrasi dan data

- [ ] `php artisan integrasi:dapodik:cek` dan `integrasi:sheets:cek` lulus di staging.
- [ ] Jika perlu satu panggilan live, jalankan mode `--live` hanya di staging dan
      catat hasilnya; jangan mengulang tanpa alasan.
- [ ] `php artisan exam:rekonsiliasi` membandingkan isi/opsi/kunci/relasi; bukan
      sekadar row count.
- [ ] `php artisan exam:backfill --dry-run` selesai tanpa mutasi.
- [ ] Selisih rekonsiliasi memiliki pemilik, alasan, dan rencana koreksi.
- [ ] `php artisan nilai:audit:verifikasi` lulus pada data yang akan dipakai.

### 3.5 Uji beban dan fungsional

- [ ] `docs/LOAD-TEST.md` dijalankan di staging dengan `LOAD_TEST_PASSWORD` khusus.
- [ ] Hasil login, CBT, Tryout, e-Rapor, dan dashboard dicatat; throttle login
      diperhitungkan (jangan memaksa ribuan login per IP).
- [ ] Smoke test manual: login tiap peran, input nilai, mulai ujian, simpan jawaban,
      kumpulkan, lihat hasil, cetak/ekspor, backup, restore staging.
- [ ] Uji antrean offline sesuai `docs/ANTREAN-OFFLINE-UJIAN.md` di staging.

## 4. Urutan deploy

```bash
# 0. Catat versi dan buat backup/snapshot
php artisan --version
git rev-parse HEAD
php artisan simantap:backup   # atau prosedur backup server yang disetujui

# 1. Mode maintenance (keputusan pemilik layanan)
php artisan down --render="errors::503" --retry=60

# 2. Kode
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Migrasi (hanya setelah backup terverifikasi)
php artisan migrate --force

# 4. Cache dan aset
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# 5. Queue dan scheduler
php artisan queue:restart
# pastikan worker permanen dan cron schedule:run aktif

# 6. Nyalakan kembali
php artisan up
```

Catat keluaran setiap perintah. Jika migrasi gagal, **jangan** mengulang buta;
tentukan apakah transaksi sudah rollback, periksa log, dan gunakan snapshot bila
perlu. Jangan menjalankan `migrate:fresh`, `db:wipe`, atau tombol **Hapus Semua
Data** di produksi.

## 5. Verifikasi pasca-deploy

- [ ] `GET /up` merespons sehat.
- [ ] Login admin/guru/siswa/ortu/kepsek/tendik berhasil; akun nonaktif tetap ditolak.
- [ ] Halaman nilai e-Rapor dapat dibuka; satu perubahan nilai menghasilkan audit
      baru dan `nilai:audit:verifikasi` tetap lulus.
- [ ] CBT/Tryout: mulai, autosave, reload, kumpulkan, hasil.
- [ ] Analisis butir admin menampilkan P/D/distraktor untuk sesi yang punya hasil.
- [ ] Backup manual berhasil dan dapat dibaca.
- [ ] Tidak ada lonjakan 5xx, queue gagal, atau login failure yang tidak wajar.
- [ ] `php artisan queue:failed` diperiksa; tidak ada kegagalan baru yang tidak
      dijelaskan.

## 6. Rollback

### 6.1 Putuskan lebih dulu

| Gejala | Tindakan |
|---|---|
| Error aplikasi lokal, data belum berubah | rollback kode ke commit sebelumnya |
| Migrasi merusak skema/data | hentikan aplikasi, restore snapshot, verifikasi |
| Nilai/ujian tidak konsisten | hentikan input, investigasi audit/log, restore bila perlu |
| Integrasi eksternal gagal | matikan flag integrasi, pertahankan data lokal, eskalasi |
| Kinerja memburuk tetapi data benar | rollback kode/cache/queue, bukan data |

### 6.2 Rollback kode

```bash
php artisan down --render="errors::503" --retry=60
git checkout <commit-stabil>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Rollback kode **tidak** otomatis membalik migrasi. Jika migrasi mengubah data,
restore snapshot.

### 6.3 Rollback data

1. Hentikan traffic tulis (maintenance atau matikan flag).
2. Simpan database/log saat ini untuk forensik.
3. Restore snapshot/backup yang disetujui.
4. Jalankan `php artisan nilai:audit:verifikasi`, cek rekonsiliasi, dan smoke test.
5. Catat waktu, backup yang dipakai, dan dampak data.

Jangan menghapus audit log untuk "membersihkan" hasil verifikasi. Jika rantai
rusak, itu bukti yang harus diselidiki.

## 7. Setelah go-live

- Pantau 5xx, login gagal, queue, waktu respons, dan error integrasi minimal 24 jam.
- Jangan langsung menghapus tabel legacy; tunggu periode observasi dan keputusan
  pemilik data.
- Simpan bukti: commit, backup, hasil migrasi, hasil verifikasi, hasil uji, dan
  catatan insiden.
- Perbarui `docs/RENCANA_PERBAIKAN_VERIFIED.md` dengan hasil nyata, bukan asumsi.

## 8. Template catatan go-live

```text
Tanggal/waktu:
Commit:
Pelaksana:
Backup/checksum:
Hasil migrasi:
Hasil nilai:audit:verifikasi:
Hasil exam:rekonsiliasi:
Hasil smoke test:
Masalah/rollback:
Keputusan go/no-go:
```
