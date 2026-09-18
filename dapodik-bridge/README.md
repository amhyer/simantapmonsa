# SIMANTAP Dapodik Bridge

Desktop aplikasi untuk sinkronisasi data Dapodik ke server SIMANTAP.

## Fitur

- Import siswa dari file CSV, Excel (.xlsx), atau JSON
- Preview data sebelum sinkronisasi
- Test koneksi ke server
- Auto-detect format file
- Simpan konfigurasi server
- Log aktivitas lengkap

## Cara Pakai

### 1. Install Python 3.8+
Download dari https://www.python.org/downloads/

### 2. Install Dependencies
```
pip install -r requirements.txt
```

### 3. Jalankan
```
python main.py
```

### 4. Build EXE (Opsional)
```
build.bat
```
File EXE ada di `dist/SIMANTAP-Bridge.exe`

## Format File

### CSV
```csv
nama,nisn,nis,jenis_kelamin,kelas,nama_ayah,nama_ibu
Ahmad Rizki,0081234001,2026001,L,1A,Budi Santoso,Siti Rahayu
```

### Kolom yang Didukung
| Kolom | Wajib | Keterangan |
|-------|-------|------------|
| nama | Ya | Nama lengkap siswa |
| nisn | Tidak | NISN dari Dapodik |
| nis | Tidak | NIS lokal |
| jenis_kelamin | Ya | L/P |
| kelas | Ya | Contoh: 1A, 2B |
| nama_ayah | Tidak | Nama ayah |
| nama_ibu | Tidak | Nama ibu |
| tempat_lahir | Tidak | Kota lahir |
| tanggal_lahir | Tidak | YYYY-MM-DD |
| agama | Tidak | Agama |
| alamat | Tidak | Alamat lengkap |
| telepon | Tidak | Nomor HP |

## Konfigurasi

File `config.json` otomatis dibuat saat pertama kali dijalankan.

## Kebutuhan Server

- Laravel SIMANTAP sudah berjalan
- API Key sudah dibuat di menu Admin > API Keys
- Route `/api/dapodik/import-siswa` aktif
