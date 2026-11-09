# Presensi Mesin & SIMANTAP di Hosting — Presensi Bridge

Dokumen operasional untuk memastikan mesin absensi (ZKTeco Attendance Management / `att2000.mdb`) **terbaca otomatis** oleh SIMANTAP yang berjalan di hosting.

## Konteks

- Mesin absensi (fingerprint) memakai database lokal di PC sekolah (`att2000.mdb`).
- Website SIMANTAP di hosting tidak dapat mengakses file lokal itu, dan IP mesin (mis. `192.168.1.7`) bukan alamat publik.
- Solusi: **Presensi Bridge** — script di PC sekolah yang membaca `att2000.mdb` lalu mengirim scan + daftar pegawai ke API SIMANTAP.

## Arsitektur

```
[PC Sekolah]                                  [Website / Hosting]
ATT2000.MDB  --(32-bit Jet OLEDB)-->  presensi-bridge.ps1  --(HTTPS + X-API-Key)-->  POST /api/presensi/mesin/sinkron
                                                                                          |
                                                                                          v
                                                        PresensiMesinService::importScans
                                                                                          |
                                                                                          v
                                                        presensi_pegawai
```

## Komponen

| Komponen | Lokasi | Peran |
|---|---|---|
| Bridge | `presensi-bridge/presensi-bridge.ps1` | Baca `att2000.mdb` (read-only), kirim ke API |
| Task Scheduler | `presensi-bridge/pasang-task.ps1` | Berulang tiap 5 menit |
| Konfigurasi | `presensi-bridge/config.json` | `server_url`, `api_key`, `db_path`, `interval_menit`, `maks_hari` |
| API server | `app/Http/Controllers/Api/PresensiMesinSyncController.php` | Terima scan & pegawai, import |
| Import logic | `app/Services/PresensiMesinService.php` | Aturan presisi (terlambat, pulang, izin/sakit/cuti) |

## Langkah instalasi (PC sekolah)

1. Salin folder `presensi-bridge/` ke `C:\Program Files\SIMANTAP\presensi-bridge\`.
2. Salin `config.example.json` → `config.json` dan isi:
   - `server_url`: `https://<domain-simantap>/`
   - `api_key`: kunci API (ability `presensi:import`)
   - `db_path`: `C:\Program Files (x86)\Solution\ATT2000.MDB` (sesuaikan)
3. Buat API Key di website: Admin → **Kunci API Bridge** → buat key dengan akses **Presensi Mesin (sync fingerprint)**.
4. Uji manual:
   ```bat
   C:\Program Files\SIMANTAP\presensi-bridge\jalankan-bridge.cmd
   ```
5. Pasang Task Scheduler (setiap 5 menit):
   ```powershell
   & 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' -File 'C:\Program Files\SIMANTAP\presensi-bridge\pasang-task.ps1'
   ```
6. Cek log: `C:\Program Files\SIMANTAP\presensi-bridge\presinsi-bridge.log`.

## Verifikasi

1. Website: `GET /api/presensi/mesin/status` dengan `X-API-Key` → `status: online`.
2. Website: `POST /api/presensi/mesin/sinkron` dengan payload `{ "scans": [...], "pegawai": [...] }`.
3. Admin website → halaman **Presensi → Import Presensi Mesin** → **Tes Koneksi** / **Tarik Data Sekarang**.
4. Admin → **Kunci API Bridge** menampilkan key terakhir dipakai.

## Troubleshooting

| Gejala | Penyebab | Perbaikan |
|---|---|---|
| `401` | API key salah/expired/tidak aktif | Buat ulang via Admin → Kunci API Bridge |
| `403` | Key tidak punya ability `presensi:import` | Buat key dengan akses **Presensi Mesin** |
| `422` | Format waktu/nik tidak valid | Pastikan `waktu` ISO (`2026-11-09T07:05:00`) |
| `Tidak ada pembaca database` | PowerShell 32-bit tidak ditemukan / MDB tidak ada | Pastikan `db_path`, install ZKTime/Attendance Management |
| Log kosong | Bridge gagal sebelum POST | Cek `presinsi-bridge.log` + status task scheduler |

## Catatan keamanan

- Expose mesin ke internet **tidak disarankan**; bridge memakai HTTPS + API key.
- Throttle server: 60 request/menit per API key.
- Payload dibatasi maks 5.000 scan per request.
