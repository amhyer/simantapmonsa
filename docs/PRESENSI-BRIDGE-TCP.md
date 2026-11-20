# Presensi Bridge TCP — Mesin Fingerprint ke SIMANTAP Hosting

Panduan operasional untuk sekolah yang **SIMANTAP-nya di hosting** dan mesin
fingerprint (ZKTeco) hanya bisa dijangkau lewat **TCP 4370** di jaringan sekolah.

Bridge berjalan di satu PC sekolah yang selalu menyala:

```
[Mesin fingerprint 192.168.1.7:4370]
          │  TCP 4370 (LAN sekolah)
          ▼
[PC sekolah: php artisan presensi:mesin-bridge]   ← Task Scheduler tiap 15 menit
          │  HTTPS + X-API-Key: presensi:import
          ▼
[SIMANTAP hosting]
   POST /api/presensi/mesin/sinkron
          │
          ▼
   PresensiMesinService::importScans  →  presensi_pegawai
```

Bridge **tidak menyimpan data ke database lokal** dan **tidak mengubah apa pun
di PC sekolah**. Tugasnya hanya membaca scan dari mesin lalu meneruskannya ke
SIMANTAP. Aturan presensi (jam masuk, terlambat, status pulang, perlindungan
izin/sakit/cuti dan koreksi admin) tetap dijalankan di server.

> Butuh jalur `att2000.mdb` / ZKTime? Lihat `docs/PRESENSI-BRIDGE-HOSTING.md`.
> Dokumen ini khusus bridge **TCP langsung**, tanpa Attendance Management.

---

## 1. Prasyarat

| Komponen | Syarat |
|---|---|
| PC bridge | Windows, selalu menyala saat jam sekolah, satu jaringan dengan mesin |
| PHP CLI | PHP 8.2+ (disarankan 8.4), ekstensi `openssl`, `curl`, `mbstring`, `pdo_sqlite` |
| Aplikasi | Salinan repo SIMANTAP (minimal `artisan`, `app/`, `bootstrap/`, `config/`, `routes/`, `storage/`, `vendor/`) |
| Mesin | IP stabil (statis / DHCP reservation), port **4370** terbuka |
| SIMANTAP | Dapat diakses via **HTTPS** dari PC sekolah |
| API key | Ability `presensi:import`, dibuat dari akun admin sekolah |

Cek dasar di PC sekolah:

```powershell
php -v
php -m | findstr /I "curl openssl mbstring pdo_sqlite"
Test-NetConnection 192.168.1.7 -Port 4370
```

`TcpTestSucceeded : True` wajib. Kalau `False`, bridge tidak akan bisa membaca
mesin — perbaiki jaringan dulu (mesin mati, IP berubah, AP isolation, atau port
tertutup).

---

## 2. Buat API key

Di SIMANTAP: **Admin → Kunci API Bridge → Buat Kunci** dengan nama mis.
`Bridge Presensi TCP` dan akses **Presensi Mesin (sync fingerprint)**.

Kunci polos hanya ditampilkan **sekali**. Simpan di `.env` PC sekolah, jangan di
repo atau chat.

---

## 3. Siapkan aplikasi di PC sekolah

Contoh lokasi: `C:\simantap-bridge`.

1. Salin repo SIMANTAP ke `C:\simantap-bridge` (tanpa folder `node_modules`).
2. Jalankan Composer bila `vendor/` belum ada:
   ```powershell
   cd C:\simantap-bridge
   composer install --no-dev --optimize-autoloader
   ```
3. Buat file SQLite kosong agar aplikasi bisa boot tanpa database produksi:
   ```powershell
   New-Item -ItemType File -Force C:\simantap-bridge\database\bridge.sqlite
   ```
4. Salin `.env.example` → `.env`, lalu isi seperti bagian berikut.

> **Penting:** jangan memakai kredensial database produksi di PC sekolah.
> Bridge hanya memerlukan HTTP client; database lokal cukup SQLite kosong.
> Pengaturan mesin dibaca dari `.env` PC ini, bukan dari database hosting.

### Isi `.env` PC bridge

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://simantap.sekolah.id
APP_TIMEZONE=Asia/Makassar
APP_KEY=base64:ISI_HASIL_KEY_GENERATE

# Database lokal kosong; bridge tidak menulis presensi di PC sekolah.
DB_CONNECTION=sqlite
DB_DATABASE=C:/simantap-bridge/database/bridge.sqlite

# Tujuan kirim
PRESENSI_BRIDGE_URL=https://simantap.sekolah.id
PRESENSI_BRIDGE_KEY=KUNCI_API_DARI_LANGKAH_2
PRESENSI_BRIDGE_STATE_PATH=storage/app/presensi-bridge-state.json

# Mesin fingerprint di LAN sekolah
PRESENSI_MESIN_DB_ENGINE=tcp
SIMANTAP_MESIN_HOST=192.168.1.7
SIMANTAP_MESIN_PORT=4370
PRESENSI_MESIN_COMM_KEY=0
PRESENSI_MESIN_USER_ID_PAD=10
```

Catatan:

- `SIMANTAP_MESIN_HOST/PORT` adalah nama utama. Alias lama
  `PRESENSI_MESIN_DB_TCP_HOST/PORT` tetap didukung bila nama utama kosong.
- `PRESENSI_MESIN_COMM_KEY=0` sesuai mesin saat ini. Bila mesin memakai comm key
  lain, samakan dengan menu **Komunikasi** di perangkat (error `2005`).
- `PRESENSI_MESIN_USER_ID_PAD=10` memadankan PIN mesin dengan `users.nik`
  10 digit. Isi `0` bila NIK memang tanpa nol di depan.
- `APP_KEY` bisa dibuat dengan:
  ```powershell
  php artisan key:generate
  ```
- `PRESENSI_BRIDGE_URL` boleh dihilangkan bila `APP_URL` sudah menunjuk ke
  SIMANTAP hosting.

---

## 4. Uji manual

### 4a. Tanpa menghubungi server

```powershell
cd C:\simantap-bridge
php artisan presensi:mesin-bridge --dry-run --dari=2026-11-17 --sampai=2026-11-17
```

Contoh keluaran:

```text
Membaca mesin 2026-11-17 s/d 2026-11-17...
DRY RUN: 37 scan dalam 1 permintaan. Tidak ada data dikirim.
  #1 2026-11-17 s/d 2026-11-17: 37 scan
```

`--dry-run` tidak menulis state dan tidak memerlukan URL/API key. Kalau langkah
ini gagal, masalahnya ada di koneksi mesin — belum menyentuh server.

### 4b. Kirim sungguhan satu hari

```powershell
php artisan presensi:mesin-bridge --dari=2026-11-17 --sampai=2026-11-17
```

Contoh keluaran:

```text
Membaca mesin 2026-11-17 s/d 2026-11-17...
Mengirim 1/1: 2026-11-17 s/d 2026-11-17 (37 scan)...
  OK: 0 dibuat, 37 diperbarui, 0 tetap, 0 dilindungi, 0 NIK tidak dikenal.
Selesai: 37 scan terkirim (0 dibuat, 37 diperbarui, 0 tetap, 0 dilindungi, 0 NIK tidak dikenal).
```

Verifikasi di SIMANTAP: **Admin → Presensi → Riwayat Sinkron Mesin**
(`/admin/presensi/mesin/sinkron`) harus muncul entri `berhasil` dengan jumlah
scan yang sama.

> Rentang yang ditulis manual (`--dari` / `--sampai`) **tidak menggeser state
> otomatis**. Aman untuk mengulang hari tertentu tanpa mengacaukan jadwal.

### 4c. Pastikan NIK pegawai sudah terpetakan

Bridge hanya mengirim scan (PIN/NIK mesin), bukan daftar nama pegawai. Server
mencocokkan NIK itu dengan `users.nik` di SIMANTAP. Bila hasil kiriman berisi
banyak `NIK tidak dikenal`:

1. Di SIMANTAP buka **Presensi → Import Presensi Mesin → Import Data Karyawan
   (NIK)**.
2. Unggah file XLS/XLSX/CSV berheader `nik,nama` (format yang sama dengan
   export mesin).
3. Kirim ulang rentang yang sama lewat `--dari` / `--sampai`; data yang sudah
   ada tidak akan tergandakan.

Tombol **Tarik NIK dari Database Mesin** hanya bisa dipakai bila server
SIMANTAP dapat menjangkau database mesin. Pada hosting, gunakan unggah file.

---

## 5. Jadwalkan tiap 15 menit

### 5a. Buat runner

Buat `C:\simantap-bridge\jalankan-bridge.cmd`:

```bat
@echo off
cd /d C:\simantap-bridge
"C:\php\php.exe" artisan presensi:mesin-bridge >> "C:\simantap-bridge\storage\logs\presensi-bridge.log" 2>&1
```

Sesuaikan `C:\php\php.exe` dengan lokasi PHP CLI di PC sekolah (mis.
`C:\laragon\bin\php\php-8.4\php.exe`).

Uji dulu dengan double-click `jalankan-bridge.cmd`, lalu periksa log.

### 5b. Daftarkan ke Task Scheduler

GUI:

1. **Task Scheduler → Create Task** (bukan Basic Task).
2. General: nama `SIMANTAP Presensi Bridge`, pilih **Run whether user is logged
   on or not**.
3. Triggers: **New → Daily**, ulangi **every 15 minutes** selama 1 hari.
4. Actions: **New → Start a program** →
   `C:\simantap-bridge\jalankan-bridge.cmd`.
5. Settings: centang **If the task is already running, do not start a new
   instance** (default) dan **Start the task only if the network connection is
   available**.
6. Simpan, lalu **Run** sekali dan cek halaman Riwayat Sinkron Mesin.

Atau lewat PowerShell (jalankan sebagai Administrator):

```powershell
schtasks /Create /TN "SIMANTAP Presensi Bridge" `
  /TR "C:\simantap-bridge\jalankan-bridge.cmd" `
  /SC MINUTE /MO 15 /RU SYSTEM /RL HIGHEST /F
```

`/RU SYSTEM` cocok bila PC hanya menjalankan bridge. Bila memakai akun lain,
tambahkan `/RU "NAMAPC\user" /RP "sandi"` dan pastikan akun itu bisa menulis
folder `storage\` serta mengakses jaringan.

---

## 6. Cara kerja rentang dan state

State disimpan di `storage/app/presensi-bridge-state.json` (bisa diubah lewat
`PRESENSI_BRIDGE_STATE_PATH`).

| Kondisi | Rentang yang dikirim |
|---|---|
| Belum ada state (jalan pertama) | 7 hari terakhir |
| Ada state sukses | sejak `terakhir_sukses` **dikurangi 1 hari**, maksimal 30 hari |
| `--dari` / `--sampai` | persis rentang itu, state tidak diubah |

Aturan pengiriman:

- Maksimal **30 hari** per jalan.
- Dipotong menjadi paket **7 hari** dan maksimal **4.000 scan** per request.
  Satu hari presensi tidak dipecah antar paket; pemecahan hanya terjadi bila
  satu hari berisi lebih dari 4.000 scan (tidak terjadi pada sekolah biasa).
  Hari presensi dimulai pukul **01:00**, jadi scan `00:00–00:59` ikut hari
  sebelumnya.
- State hanya maju bila **semua** paket berhasil. Gagal di tengah jalan berarti
  jadwal berikutnya mengulang rentang yang sama.
- Kiriman bersifat **idempoten**: scan yang terkirim ulang tidak menggandakan
  data, dan koreksi admin / izin / sakit / cuti tetap dilindungi.

Isi state contohnya:

```json
{
  "terakhir_sukses": "2026-11-20 15:10:02",
  "terakhir_jalan": "2026-11-20 15:10:02",
  "pesan_terakhir": "ok",
  "rentang_terakhir": { "dari": "2026-11-18", "sampai": "2026-11-20" },
  "jumlah_scan_terakhir": 118
}
```

Saat gagal, `terakhir_sukses` **tidak berubah** dan `pesan_terakhir` berisi
`gagal: ...` untuk memudahkan diagnosa.

---

## 7. Troubleshooting

| Gejala | Penyebab | Perbaikan |
|---|---|---|
| `Gagal koneksi ke mesin via TCP (…:4370)` | Mesin mati, Wi-Fi putus, IP berubah, AP isolation | Nyalakan mesin, cek IP di menu perangkat, `Test-NetConnection`, matikan client isolation |
| `… dan database cadangan Attendance Management tidak ditemukan` | TCP gagal dan PC bridge tidak punya `att2000.mdb` | Normal untuk bridge TCP; fokus perbaiki koneksi mesin |
| Error `2005` / `CMD_ACK_UNAUTH` | Comm key mesin tidak cocok | Samakan `PRESENSI_MESIN_COMM_KEY` dengan menu Komunikasi mesin |
| `401` | API key salah, nonaktif, atau kedaluwarsa | Buat ulang di **Kunci API Bridge**, perbarui `.env` |
| `403` | Key tidak punya ability `presensi:import` | Buat key dengan akses **Presensi Mesin** |
| `422` | Payload ditolak server | Cek pesan `errors` pada output; pastikan jam PC bridge benar |
| `429` | Melebihi throttle 60 request/menit | Jarangkan jadwal; satu kali jalan normal hanya 1–3 request |
| `cURL error 60` / SSL certificate | CA bundle PHP belum diset | Isi `curl.cainfo` di `php.ini` atau set `CURL_CA_BUNDLE` ke `cacert.pem` |
| `could not find driver` | `pdo_sqlite` belum aktif | Aktifkan `extension=pdo_sqlite` di `php.ini` |
| `NIK tidak dikenal` > 0 | PIN mesin tidak sama dengan `users.nik` di SIMANTAP | Di SIMANTAP buka **Presensi → Import Presensi Mesin → Import Data Karyawan (NIK)**, unggah file `nik,nama`; atau sesuaikan `PRESENSI_MESIN_USER_ID_PAD` |
| State tidak maju | Ada paket yang gagal | Lihat `pesan_terakhir` dan log runner |
| Jam masuk/pulang kacau | Jam mesin atau PC bridge melenceng | Samakan jam mesin dan PC ke `Asia/Makassar` (NTP) |

Bila mesin memakai **Wi-Fi**, sebisa mungkin pindahkan ke kabel LAN. Alat
fingerprint Wi-Fi sering tidur/putus dan IP-nya mudah berubah.

---

## 8. Keamanan

- Kirim hanya lewat **HTTPS**; jangan pakai `http://` untuk domain publik.
- API key hanya berability `presensi:import`; batasi izin baca file `.env` ke
  akun yang menjalankan task.
- **Jangan** port-forward `4370` ke internet dan jangan mengekspos mesin.
  Bridge hanya membuka koneksi **keluar** (LAN ke mesin, HTTPS ke server).
- Server membatasi 5.000 scan/request dan throttle 60 request/menit per key.
- Bridge tidak menyimpan data presensi di PC sekolah; hanya state kecil berisi
  waktu sinkron terakhir.

---

## 9. Checklist go-live

- [ ] IP mesin statis / DHCP reservation; `Test-NetConnection … -Port 4370` True.
- [ ] Jam mesin dan PC bridge benar (`Asia/Makassar`).
- [ ] API key `presensi:import` dibuat dan disimpan di `.env`.
- [ ] `php artisan presensi:mesin-bridge --dry-run` membaca scan tanpa error.
- [ ] Kirim manual satu hari berhasil dan muncul di Riwayat Sinkron Mesin.
- [ ] `jalankan-bridge.cmd` berjalan dan menulis log.
- [ ] Task Scheduler aktif tiap 15 menit, **Run whether user is logged on or not**.
- [ ] Besoknya: state `terakhir_sukses` maju dan data hari baru masuk.
