# Telemetri penggunaan halaman menu

SIMANTAP mencatat agregat pemakaian halaman internal agar keputusan merapikan atau menambah menu bisa memakai data, bukan tebakan.

## Yang dicatat

Hanya permintaan **GET** yang terautentikasi, memiliki named route, menghasilkan status **200**, dan bertipe `text/html`. Rekaman diagregasikan per hari dengan kunci cakupan, peran, dan named route. Tidak ada baris per pengguna.

Skema sengaja tidak menyimpan `user_id`, alamat IP, user-agent, ID session, query string, payload, nama siswa/guru, atau isi halaman. Cakupan instalasi tunggal ditandai `single`; sekolah tenant dicakup dengan ID internal seperti `tenant:73`. Angka `visits` adalah jumlah respons halaman sukses, bukan jumlah pengguna unik atau klik menu.

## Laporan

```bash
php artisan menu:usage-report --days=30
php artisan menu:usage-report --days=60
```

`--days` dibatasi 1–60 hari. Laporan CLI menampilkan cakupan, role, route, dan jumlah kunjungan; jangan membagikan laporan tenant kepada pihak yang tidak berwenang.

## Retensi dan kendali

- Retensi default: 60 hari; scheduler menjalankan `menu:usage-prune` setiap hari pukul 03:30.
- Untuk membersihkan manual: `php artisan menu:usage-prune`.
- Untuk mematikan pencatatan: set `SIMANTAP_MENU_USAGE_TELEMETRY=false` lalu reload konfigurasi/deploy ulang sesuai cara deployment.
- Middleware hanya menambah agregat lokal di database aplikasi; tidak ada pengiriman ke layanan analitik eksternal.

## Cara mengevaluasi menu

Ambil sekurangnya 30 hari, idealnya mencakup aktivitas akademik yang relevan. Bandingkan route dalam role yang sama; bedakan menu jarang digunakan karena musiman, empty-state, atau memang tidak dibutuhkan. Jangan menghapus menu hanya karena hit rendah tanpa konfirmasi pemilik proses dan uji alur utama.
