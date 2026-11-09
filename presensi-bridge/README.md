# SIMANTAP Presensi Bridge

Bridge dari PC sekolah (Windows) ke website SIMANTAP, untuk instalasi **hosting**.

## Tujuan

Membaca log fingerprint mesin absensi (ZKTeco Attendance Management, `att2000.mdb`)
di PC sekolah lalu mengirimkannya ke website SIMANTAP melalui API:

- `POST /api/presensi/mesin/sinkron` — menerima `{ "scans": [...], "pegawai": [...] }`
- `GET  /api/presensi/mesin/status` — status bridge

Server memakai `PresensiMesinService::importScans` yang sama, sehingga aturan
keamanan (izin / sakit / cuti, koreksi admin, scan pulang tanpa jam masuk) tetap berlaku.

## Prasyarat

1. PC Windows di mana mesin fingerprint / Attendance Management (ZKTime) dipasang.
2. PHP server SIMANTAP berjalan dan dapat diakses via HTTPS (mis. `https://simantap.example.com`).
3. API Key SIMANTAP dengan ability `presensi:import` (halaman Admin → **Kunci API Bridge**).

## Instalasi

1. Salin folder `presensi-bridge/` ke `C:\Program Files\SIMANTAP\presensi-bridge\`.
2. Salin `config.example.json` → `config.json`, lalu isi:
   - `server_url`: URL website SIMANTAP (wajib HTTPS kecuali localhost).
   - `api_key`: kunci API yang dibuat admin (ability `presensi:import`).
   - `db_path`: lokasi `att2000.mdb` (mis. `C:\Program Files (x86)\Solution\ATT2000.MDB`).
3. Uji manual sekali:
   ```bat
   C:\Program Files\SIMANTAP\presensi-bridge\jalankan-bridge.cmd
   ```
   Atau lewat PowerShell:
   ```powershell
   C:\Windows\SysWOW64\WindowsPowerShell\v1.0\powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "C:\Program Files\SIMANTAP\presensi-bridge\presensi-bridge.ps1" -ConfigPath "C:\Program Files\SIMANTAP\presensi-bridge\config.json"
   ```

## Jadwalkan (Task Scheduler)

1. Buka Task Scheduler → **Create Task**.
2. General: nama `SIMANTAP Presensi Bridge`, lanjutkan.
3. Triggers: **New** → **Once** + mulai, **Repeat task every 5 minutes** selamanya.
4. Actions: **New** → Program/script: `C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe`, arguments: `-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "C:\Program Files\SIMANTAP\presensi-bridge\presensi-bridge.ps1" -ConfigPath "C:\Program Files\SIMANTAP\presensi-bridge\config.json"`.
5. Conditions: pilih "Start the task only if network connection is available" (opsional).
6. OK → jalankan task sekali untuk verifikasi.

Atau lewat PowerShell:
```powershell
& 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' -File 'C:\Program Files\SIMANTAP\presensi-bridge\pasang-task.ps1'
```

## Konfigurasi state

`state.json` di folder bridge menyimpan timestamp terakhir sinkron berhasil, dipakai
sebagai dasar rentang scan berikutnya (sekali sebelumnya s/d hari ini, maks 30 hari).

## Keamanan

- API key disimpan di `config.json` di PC sekolah — batasi akses file.
- Kirim ke server wajib HTTPS (kecuali `server_url` localhost).
- Throttle server: 60 request/menit per API key.
