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
| Pembaca MDB | `tools/attendance-mdb-export.ps1` | Ekspor `att2000.mdb` via Jet OLEDB 32-bit (**wajib disalin**) |
| Task Scheduler | `presensi-bridge/pasang-task.ps1` | Berulang tiap 5 menit |
| Konfigurasi | `presensi-bridge/config.json` | `server_url`, `api_key`, `db_path`, `interval_menit`, `maks_hari` |
| API server | `app/Http/Controllers/Api/PresensiMesinSyncController.php` | Terima scan & pegawai, import |
| Import logic | `app/Services/PresensiMesinService.php` | Aturan presisi (terlambat, pulang, izin/sakit/cuti) |

## Langkah instalasi (PC sekolah)

Struktur folder yang benar:

```text
C:\Program Files\SIMANTAP\
├── presensi-bridge\
│   ├── presensi-bridge.ps1
│   ├── jalankan-bridge.cmd
│   ├── pasang-task.ps1
│   └── config.json
└── tools\
    └── attendance-mdb-export.ps1   <-- WAJIB ikut disalin
```

1. Salin folder `presensi-bridge/` ke `C:\Program Files\SIMANTAP\presensi-bridge\`.
2. **Salin folder `tools/`** ke `C:\Program Files\SIMANTAP\tools\`. Bridge mencari
   pembaca database di `..\tools\attendance-mdb-export.ps1`; bila tidak ada,
   bridge memakai salinan `attendance-mdb-export.ps1` di folder bridge sendiri.
3. Salin `config.example.json` → `config.json` dan isi:
   - `server_url`: `https://<domain-simantap>/`
   - `api_key`: kunci API (ability `presensi:import`)
   - `db_path`: `C:\Program Files (x86)\Solution\ATT2000.MDB` (sesuaikan)
4. Buat API Key di website: Admin → **Kunci API Bridge** → buat key dengan akses **Presensi Mesin (sync fingerprint)**.
5. Uji manual:
   ```bat
   C:\Program Files\SIMANTAP\presensi-bridge\jalankan-bridge.cmd
   ```
6. Pasang Task Scheduler (setiap 5 menit):
   ```powershell
   & 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' -File 'C:\Program Files\SIMANTAP\presensi-bridge\pasang-task.ps1'
   ```
7. Cek log: `C:\Program Files\SIMANTAP\presensi-bridge\presinsi-bridge.log`.

## Verifikasi

1. Website: `GET /api/presensi/mesin/status` dengan `X-API-Key` → `status: online`.
2. Website: `POST /api/presensi/mesin/sinkron` dengan payload `{ "scans": [...], "pegawai": [...] }`.
3. Admin website → halaman **Presensi → Import Presensi Mesin** → **Tes Koneksi** / **Tarik Data Sekarang**.
4. Admin → **Kunci API Bridge** menampilkan key terakhir dipakai.

## Dashboard Riwayat Sinkron (hasil bridge)

Setelah bridge mengirim scan, website menyimpan riwayat hasil sinkron di tabel
`presensi_mesin_sync_logs` dan menampilkannya di:

- **Admin → Presensi → Riwayat Sinkron Mesin** (`/admin/presensi/mesin/sinkron`).

Halaman ini menampilkan:
- Status sinkron terakhir (berhasil/gagal) + jumlah scan + data dibuat/diperbarui + dilindungi/NIK tidak dikenal.
- Detail hasil sinkron terakhir (waktu, sumber, durasi, IP, pesan error bila gagal).
- Riwayat sinkron terbaru (maks. 25) beserta ringkasan per sinkron.

Gagal sinkron (mis. bridge tidak bisa menjangkau server) menuliskan status `gagal`
pada log, sehingga admin bisa membedakan kegagalan dari kapan.

## Troubleshooting

| Gejala | Penyebab | Perbaikan |
|---|---|---|
| `401` | API key salah/expired/tidak aktif | Buat ulang via Admin → Kunci API Bridge |
| `403` | Key tidak punya ability `presensi:import` | Buat key dengan akses **Presensi Mesin** |
| `422` | Format waktu/nik tidak valid | Pastikan `waktu` ISO (`2026-11-09T07:05:00`) |
| `Tidak ada pembaca database` | PowerShell 32-bit tidak ditemukan / MDB tidak ada | Pastikan `db_path`, install ZKTime/Attendance Management |
| `Pembaca database mesin tidak ditemukan` | `attendance-mdb-export.ps1` belum disalin | Salin ke `C:\Program Files\SIMANTAP\tools\` atau ke folder `presensi-bridge\` |
| Log kosong | Bridge gagal sebelum POST | Cek `presinsi-bridge.log` + status task scheduler |

## Catatan keamanan

- Expose mesin ke internet **tidak disarankan**; bridge memakai HTTPS + API key.
- Throttle server: 60 request/menit per API key.
- Payload dibatasi maks 5.000 scan per request.
