# Panduan Cutover Migrasi Foto Siswa ke Penyimpanan Private (O3)

Dokumen ini adalah urutan operasional resmi untuk memindahkan foto siswa dari disk `public` (`storage/app/public/foto-siswa/`) ke disk `local` private (`storage/app/private/foto-siswa/`) tanpa memutus tampilan foto bagi pengguna berwenang.

> **Penting:** Kolom database `siswa.foto` **tidak berubah** (tetap `foto-siswa/<nama>.<ext>`). Jangan menghapus seluruh symlink `public/storage` karena masih digunakan oleh aset publik lain (logo pemda dan logo sekolah).

---

## Urutan Cutover Operator

### 1. Backup Penuh (Wajib)
Ambil cadangan terverifikasi di luar direktori aplikasi sebelum mengubah apa pun:
```bash
tar -czf /backup/simantap-foto-public-$(date +%F).tar.gz storage/app/public/foto-siswa
tar -czf /backup/simantap-private-$(date +%F).tar.gz storage/app/private
```
*(Pastikan arsip backup dapat dibaca dan ukurannya wajar. Kolom `siswa.foto` dikecualikan dari `BackupTables`, sehingga berkas foto wajib di-backup terpisah.)*

### 2. Deploy Kode Baru (O1 + O2 + O3)
- Pastikan di `.env` produksi sementara disetel:
  ```env
  SIMANTAP_FOTO_FALLBACK_PUBLIC=true
  ```
- Jalankan `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.
- Pada tahap ini, unggah foto baru langsung masuk ke `storage/app/private/foto-siswa/`, sedangkan foto lama masih dilayani lewat route terotorisasi `/media/siswa/{uuid}/foto` menggunakan fallback baca dari `public`.

### 3. Audit Dry-Run
Jalankan perintah migrasi dalam mode default (`DRY-RUN`) dengan laporan ID siswa di luar direktori repositori:
```bash
php artisan siswa:migrasi-foto-private --report=/tmp/migrasi-foto-dryrun.json
```
Periksa ringkasan hitungan:
- `hanya_public`: berkas yang perlu disalin.
- `ada_di_keduanya_berbeda` (**KONFLIK**): tidak akan disentuh otomatis; selidiki manual sebelum menghapus salinan publik.
- `yatim_public`: berkas di `storage/app/public/foto-siswa/` yang tidak dirujuk tabel `siswa` (tidak dihapus otomatis; tinjau manual).

### 4. Jalankan Migrasi (`--apply`)
Salin berkas dari `public` → `private` dengan verifikasi SHA-256 sumber == tujuan:
```bash
php artisan siswa:migrasi-foto-private --apply --report=/tmp/migrasi-foto-apply.json
```
- Perintah bersifat **idempotent** (aman dijalankan ulang).
- Pastikan `gagal_verifikasi: 0` dan jalankan ulang dry-run untuk memastikan `hanya_public: 0`.

### 5. Verifikasi Sampel di Browser
Uji akses foto lewat browser pada route terotorisasi `/media/siswa/{uuid}/foto` untuk tiap peran:
- **Admin**: halaman Referensi Foto Siswa & Cetak Kartu Siswa menampilkan foto.
- **Siswa**: Dashboard & Profil menampilkan foto sendiri; URL siswa lain ditolak (`403`).
- **Orang Tua**: avatar anak terhubung tampil; anak lain ditolak (`403`).
- **Guru**: halaman Kehadiran Jadwal menampilkan foto siswa ajarnya; siswa kelas lain ditolak (`403`).
- **Tamu (incognito / belum login)**: `/media/siswa/{uuid}/foto` diarahkan ke login (`302`).

### 6. Matikan Fallback & Hapus Salinan Publik
1. Ubah di `.env` produksi:
   ```env
   SIMANTAP_FOTO_FALLBACK_PUBLIC=false
   ```
   Lalu jalankan `php artisan config:cache`.
2. Hapus salinan publik **hanya** untuk berkas yang terverifikasi identik:
   ```bash
   php artisan siswa:migrasi-foto-private --apply --delete-public --yes-backup-done
   ```
3. Jalankan ulang dry-run untuk memastikan `hanya_public: 0` dan `ada_di_keduanya_identik: 0`.

### 7. Blokir Jalur `/storage/foto-siswa/` di Web Server & Purge CDN
Tambahkan aturan blokir eksplisit agar direktori lama tidak pernah disajikan sebagai file statis lagi:

- **Nginx** (di dalam blok `server {}`, sebelum `location /`):
  ```nginx
  location ^~ /storage/foto-siswa/ {
      return 404;
  }
  ```
- **Apache** (vhost atau `public/.htaccess` sebelum aturan rewrite Laravel):
  ```apache
  RewriteRule ^storage/foto-siswa/ - [R=404,L]
  ```
- Jika memakai **CDN / reverse proxy cache** (mis. Cloudflare), lakukan **purge cache** untuk prefix `/storage/foto-siswa/*`.
- Uji dari luar (incognito / `curl -I`): `GET https://<domain>/storage/foto-siswa/<contoh>` wajib mengembalikan `404`.

### 8. Tinjau Access Log & Penanganan Privasi
- Periksa access log web server / CDN untuk permintaan `GET /storage/foto-siswa/*` tanpa sesi atau dari IP tidak dikenal sebelum blokir diterapkan.
- Catat bahwa URL lama yang pernah diakses dapat tersimpan di riwayat atau cache browser klien; penanganan insiden privasi dan pemberitahuan mengikuti kebijakan organisasi/sekolah.

---

## Rencana Rollback
Karena kolom `siswa.foto` tidak diubah dan backup penuh telah diambil pada Langkah 1:
1. Pulihkan `storage/app/public/foto-siswa/` dari arsip backup Langkah 1 (jika sudah dihapus).
2. Setel kembali `SIMANTAP_FOTO_FALLBACK_PUBLIC=true` dan jalankan `php artisan config:cache`.
3. Jika perlu mundur ke rilis kode sebelumnya, hapus sementara aturan blokir `/storage/foto-siswa/` di web server hanya setelah berkoordinasi dengan penanggung jawab privasi.
