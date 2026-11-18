# Panduan Operasional Produksi SIMANTAP MONSA

## 1. Driver Cache & Kunci (`CACHE_STORE`)
- **WAJIB** menggunakan driver cache persisten yang mendukung atomic lock (`database`, `redis`, atau `file`) di `.env` produksi.
- **DILARANG** menggunakan `CACHE_STORE=array` di produksi:
  - Tautan ekspor kata sandi awal sekali-unduh disimpan sementara di cache antar-request; dengan `array`, token hilang seketika setelah request selesai.
  - `Cache::lock` pada fitur *Reset & Ekspor Sandi Massal* tidak akan mencegah eksekusi bersamaan antar-worker bila memakai `array`.

## 2. Penyetelan `SIMANTAP_RESET_MASSAL_MAX`
- Pada pengukuran lokal (bcrypt 12 rounds ≈ `269 ms`/hash), `100` akun membutuhkan ~`27 detik`.
- **Ukur ulang** waktu `Hash::make()` di server produksi Anda lalu sesuaikan `SIMANTAP_RESET_MASSAL_MAX` di `.env` agar eksekusi tetap berada di bawah timeout Nginx/PHP-FPM (umumnya 60 detik).

## 3. Prosedur Jika Siswa / Guru Tidak Bisa Login
1. **Backup database** terlebih dahulu sebelum melakukan perubahan massal.
2. Buka panel Admin → **Data Pengguna** → tombol **Reset & Ekspor Sandi Massal**.
3. Pilih peran target (`Siswa`, `Guru`, dll.) dan filter **Kelas** (bila diperlukan).
4. Biarkan centang **Hanya yang belum pernah login** tetap aktif agar akun yang sudah mengganti sandi tidak terganggu.
   - Bila unduhan CSV sebelumnya gagal/terputus untuk batch yang sama, centang **Sertakan akun yang password awalnya sudah diterbitkan** (ini akan mengganti sandi batch tersebut dan membatalkan CSV lama).
5. Masukkan ulang **Kata Sandi Administrator** untuk konfirmasi, lalu klik **Reset & Siapkan Ekspor**.
6. Segera klik **Unduh Daftar Password Awal (Sekali Unduh)** dan bagikan dengan aman ke wali kelas/guru/siswa. File CSV hanya dapat diunduh satu kali.
7. Untuk audit akun lama yang masih memakai sandi bawaan sebelum pembaruan, gunakan CLI `php artisan akun:audit-password-bawaan` (mode `dry-run` default; lihat [BACKLOG.md](BACKLOG.md)).
