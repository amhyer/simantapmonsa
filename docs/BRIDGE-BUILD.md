# Panduan Build Ulang `SIMANTAP-Bridge.exe`

Dokumen ini disusun karena `pyinstaller` tidak terpasang di lingkungan saat audit. Gunakan langkah berikut pada mesin build Windows yang bersih sebelum mengunggah `SIMANTAP-Bridge.exe` ke GitHub Releases atau object storage.

## 1. Persiapan Checkout Bersih
1. Pastikan tidak ada kredensial nyata di `dapodik-bridge/config.json`:
   ```powershell
   Copy-Item dapodik-bridge/config.example.json dapodik-bridge/config.json -Force
   ```
   Verifikasi bahwa `api_key` dan `dapodik_token` bernilai string kosong `""`.
2. Buat virtual environment Python baru:
   ```powershell
   cd dapodik-bridge
   python -m venv .venv
   .\.venv\Scripts\Activate.ps1
   pip install --upgrade pip
   pip install -r requirements.txt pyinstaller
   ```

## 2. Build Binary `.exe`
Jalankan PyInstaller memakai spesifikasi resmi:
```powershell
pyinstaller --clean SIMANTAP-Bridge.spec
```
Hasil build berada di `dapodik-bridge/dist/SIMANTAP-Bridge.exe`.

## 3. Verifikasi & Checksum SHA-256
1. Hitung hash SHA-256 untuk diisi ke `DAPODIK_BRIDGE_SHA256` di `.env` produksi:
   ```powershell
   Get-FileHash .\dist\SIMANTAP-Bridge.exe -Algorithm SHA256
   ```
2. (Opsional) Bongkar arsip PyInstaller dengan `pyi-archive_viewer` untuk memastikan `config.json` di dalam `.exe` kosong:
   ```powershell
   pyi-archive_viewer .\dist\SIMANTAP-Bridge.exe
   # ketik: x config.json -> periksa isinya di stdout/file keluaran
   ```

## 4. Publikasi
1. Unggah `SIMANTAP-Bridge.exe` ke GitHub Releases (repo publik rilis atau object storage).
2. Set `DAPODIK_BRIDGE_DOWNLOAD_URL`, `DAPODIK_BRIDGE_VERSION`, dan `DAPODIK_BRIDGE_SHA256` di `.env` server.
3. Untuk pengujian lokal tanpa URL eksternal, salin ke `storage/app/private/bridge/SIMANTAP-Bridge.exe`.
