# Uji Beban (k6)

Uji beban bukan pengganti uji fungsi. Tujuannya menjawab: **apakah halaman
utama tetap cepat dan stabil saat banyak pengguna membuka SIMANTAP bersamaan?**
Jalankan hanya di staging dengan data menyerupai produksi, bukan di produksi.

## 1. Prasyarat

- [k6](https://k6.io/docs/get-started/installation/) terpasang di mesin pemuat.
- Staging dapat diakses dari mesin tersebut.
- Database staging berisi data realistis (bukan 3 siswa).
- `APP_DEBUG=false`, cache/config produksi sudah dibangun.
- Queue worker dan scheduler berjalan seperti produksi.
- Backup staging dibuat sebelum tes yang menulis data.

## 2. Menyiapkan akun uji

```bash
# Di staging, bukan produksi
export LOAD_TEST_PASSWORD='SandiUjiYangKuat#2026'
php artisan db:seed --class=LoadTestSeeder
```

Seeder membuat akun `loadtest.guru01..NN` dan `loadtest.siswa01..NN` beserta
siswa minimal. Seeder aditif dan tidak menghapus data. Jumlah dapat diatur:

```bash
LOAD_TEST_GURU=20 LOAD_TEST_SISWA=100 php artisan db:seed --class=LoadTestSeeder
```

## 3. Smoke publik

```bash
BASE_URL=https://staging.sekolah.sch.id \
PUBLIC_VUS=10 DURATION=30s \
k6 run tests/load/k6-smoke.js
```

Yang diuji: landing page dan form login. Tidak ada autentikasi.

## 4. Beban terautentikasi

```bash
BASE_URL=https://staging.sekolah.sch.id \
LOAD_TEST_USER=loadtest.guru01 \
LOAD_TEST_PASSWORD='SandiUjiYangKuat#2026' \
PUBLIC_VUS=5 AUTH_VUS=3 DURATION=1m \
k6 run tests/load/k6-smoke.js
```

Default halaman adalah `/dashboard`; ganti dengan `LOAD_TEST_PATH=/guru/dashboard`
atau halaman lain yang ingin diukur. Setiap VU login sekali, lalu mengulang
halaman tersebut.

**Batas login:** route login memakai `throttle:10,1` (10 percobaan/menit per IP).
Karena itu `AUTH_VUS` default 3. Jangan menaikkan tanpa menyesuaikan throttle
staging; jangan melemahkan throttle produksi hanya untuk tes. Untuk konkurensi
tinggi, gunakan sesi yang sudah ada atau uji endpoint read-only setelah login
dengan mekanisme yang disetujui.

## 5. Ambang yang dipakai

Skrip gagal bila:

- `http_req_failed` > 2%
- `http_req_duration` p95 > 2 detik atau p99 > 4 detik
- `checks` < 98%

Angka ini titik awal, bukan SLA. Sesuaikan dengan perangkat sekolah dan ekspektasi
pengguna. Catat juga:

- RPS dan jumlah VU.
- Waktu respons p50/p95/p99.
- Error rate per halaman.
- Penggunaan CPU/RAM web, database, queue.
- Perlambatan yang tumbuh seiring waktu (indikasi kebocoran/sesi/lock).

## 6. Skenario minimum sebelum go-live

- [ ] Smoke publik 10 VU / 30 detik lulus.
- [ ] Login + dashboard 3 VU / 1 menit lulus.
- [ ] Halaman hasil CBT/Tryout (bila diaktifkan) lulus dengan data realistis.
- [ ] Tidak ada error 500/502 di log selama tes.
- [ ] Database connection pool tidak habis.
- [ ] Queue tidak menumpuk tanpa batas.
- [ ] Hasil dicatat di laporan go-live.

## 7. Batas

- k6 tidak menguji WebSocket/real-time atau perangkat siswa.
- Login per VU menyentuh session lock Laravel; beban tinggi pada satu akun bisa
  memperlihatkan serialisasi sesi, bukan beban aplikasi murni.
- Data hasil tes tidak otomatis dibersihkan. Hapus akun `loadtest.*` secara
  manual hanya setelah konfirmasi, atau gunakan salinan database staging.
- Uji beban tidak membuktikan keamanan, integrasi Dapodik, atau kebenaran nilai.
