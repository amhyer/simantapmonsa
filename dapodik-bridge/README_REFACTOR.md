# SIMANTAP Dapodik Bridge - Troubleshooting & Setup

## Masalah Ditemukan

### ERROR: Penarikan Data dari Dapodik Lokal Gagal

**Root Cause:**
1. Dapodik lokal adalah **web application**, bukan pure API
2. Token-based query string (`?act=Sekolah&token=xxx`) tidak support
3. Database PostgreSQL terkunci (password-protected)
4. Web form login memerlukan session/cookie management yang kompleks

**Original Architecture Issue:**
```
main.py v3.0 mengasumsikan:
  ✗ Dapodik punya webservice API
  ✗ Token auth via URL query string
  ✗ Response JSON format
  ✗ Database accessible tanpa password

Kenyataan:
  ✓ Dapodik adalah web app PHP+PostgreSQL
  ✓ Memerlukan browser session untuk akses
  ✓ Database terkunci
  ✓ API endpoint tidak konsisten
```

---

## Solusi: CSV Direct Import (v3.1)

**Strategi Baru:**
- Alih-alih pull dari Dapodik backend
- **Manual export CSV dari Dapodik UI** → simpan file
- **Auto-import CSV** ke SIMANTAP Laravel app
- Lebih reliable, tidak bergantung Dapodik internals

### Keuntungan:
✓ Sederhana & reliable  
✓ Tidak perlu akses database Dapodik  
✓ User kontrol data sebelum sync  
✓ Bisa batch import  

### Kekurangan:
✗ Manual export step (tapi one-time effort)

---

## Setup Guide

### Step 1: Export CSV dari Dapodik

1. Buka browser: **http://localhost:5774**
2. Login dengan credential:
   ```
   Email: sdnungmongisidi1@gmail.com
   Password: Monsajaya12*#
   ```
3. Cari menu **Export Data** atau **Manajemen Data**
4. Export setiap modul sebagai CSV:
   - **PesertaDidik** → `PesertaDidik.csv`
   - **Sekolah** → `Sekolah.csv`
   - **PTK** → `PTK.csv`
   - **RombonganBelajar** → `RombonganBelajar.csv`

### Step 2: Persiapkan Folder

```bash
# Buka Command Prompt / PowerShell
cd D:\Project\simantap\dapodik-bridge

# Folder csv_files sudah ada, hanya perlu copy file
```

### Step 3: Copy CSV Files

```
D:\Project\simantap\dapodik-bridge\csv_files\
├── PesertaDidik.csv          (dari Dapodik export)
├── Sekolah.csv               (dari Dapodik export)
├── PTK.csv                   (dari Dapodik export)
└── RombonganBelajar.csv      (dari Dapodik export)
```

**Template files available:**
```
├── PesertaDidik_TEMPLATE.csv
├── Sekolah_TEMPLATE.csv
```

### Step 4: Jalankan Application

```bash
# Di folder dapodik-bridge
python main.py
```

### Step 5: Sync ke SIMANTAP

1. Pastikan SIMANTAP sudah running: **http://localhost:8000**
2. Input configuration:
   - Server: `http://localhost:8000`
   - API Key: (dari admin panel SIMANTAP)
3. Klik **"Test SIMANTAP"** - harus OK
4. Klik **"Pilih File CSV"** - akan auto-load semua CSV
5. Klik **"Sinkronisasi"** - mulai sync

---

## CSV Format Reference

### PesertaDidik.csv
```
nama,nisn,nis,jenis_kelamin,kelas,nama_ayah,nama_ibu,tempat_lahir,tanggal_lahir
Ahmad Rizki,0081234001,2026001,L,1A,Budi Santoso,Siti Rahayu,Jakarta,2011-01-15
Siti Nur Azizah,0081234002,2026002,P,1A,Hadi Purnomo,Liswanti,Bandung,2011-03-22
```

**Field Requirements:**
- `nama` - Wajib, string
- `nisn` - Optional, string (nomor induk siswa nasional)
- `nis` - Optional, string (nomor induk siswa)
- `jenis_kelamin` - Wajib, `L` or `P`
- `kelas` - Wajib, string (contoh: `1A`, `2B`)
- `nama_ayah` - Optional
- `nama_ibu` - Optional
- `tempat_lahir` - Optional
- `tanggal_lahir` - Optional, format `YYYY-MM-DD`

### Sekolah.csv
```
npsn,nama_sekolah,alamat,provinsi,kabupaten
20603161,SDN Ungmongisidi 1,Jalan Pendidikan No 1,Jawa Barat,Indramayu
```

### PTK.csv
```
nip,nama,jabatan,bidang_keahlian
197508101998021001,Drs. Bambang,Kepala Sekolah,Administrasi
```

### RombonganBelajar.csv
```
nama,tingkat,jumlah_siswa
Kelas 1A,1,35
Kelas 1B,1,36
```

---

## Troubleshooting

### Error: "Folder tidak ada"
**Solution:**
```bash
# Buat folder secara manual
mkdir csv_files
```

### Error: "Tidak ada file CSV"
**Solution:**
1. Cek folder `csv_files` ada file `.csv`
2. Pastikan nama file mengandung salah satu:
   - `peserta` (untuk PesertaDidik)
   - `sekolah`
   - `ptk`
   - `rombongan` (untuk RombonganBelajar)

### Error: "Gagal terhubung SIMANTAP"
**Solution:**
1. Pastikan Laravel SIMANTAP running: `php artisan serve` (port 8000)
2. Cek Server URL format: `http://localhost:8000` (tanpa `/`)
3. API Key benar dari admin panel SIMANTAP

### Error: "File CSV parsing gagal"
**Solution:**
1. Pastikan file CSV encoding UTF-8
2. Cek delimiter: harus comma (`,`)
3. Contoh buat file baru:
   ```bash
   # Di Excel: Save As → CSV UTF-8 (Comma delimited)
   ```

### Error: Sync berhasil tapi data tidak masuk SIMANTAP
**Solution:**
1. Cek API endpoint `/api/dapodik/sync/*` di Laravel
2. Cek field names match dengan database schema
3. Lihat log Laravel: `storage/logs/laravel.log`

---

## Advanced: Alternative Solutions

### Opsi A: Selenium Web Scraping
Jika perlu automated pull dari Dapodik UI:
```bash
pip install selenium
# Gunakan headless browser untuk auto-login & export
```

### Opsi B: SQL Direct Query (Jika Database Unlocked)
```python
import psycopg2
conn = psycopg2.connect("dbname=pendataan user=postgres password=xxx host=localhost port=54532")
```

### Opsi C: Dapodik REST API Module
Jika Dapodik support plugin, buat module yang expose:
```
GET /api/sekolah
GET /api/peserta-didik
GET /api/ptk
```

---

## Files Changed

```
main.py (v3.0 → v3.1)
├── Removed: Dapodik webservice token auth
├── Removed: Local token-based query string logic
├── Added: CSV file reading
├── Added: Auto-detect CSV by filename
└── Added: Batch CSV import UI

dapodik-bridge/
├── main_original_v3.0.py      (backup original)
├── REFACTOR_NOTES.md          (technical details)
├── requirements.txt           (updated)
└── csv_files/                 (new folder)
    ├── PesertaDidik_TEMPLATE.csv
    └── Sekolah_TEMPLATE.csv
```

---

## Next Steps

1. **Test dengan sample data:**
   - Copy template files ke production names
   - Run sync
   - Verify di SIMANTAP

2. **Production Workflow:**
   - Monthly export CSV dari Dapodik
   - Copy to `csv_files/` folder
   - Run sync via GUI
   - Audit results

3. **Future Improvements:**
   - WebSocket real-time sync
   - Scheduled auto-sync (Windows Task Scheduler)
   - Data validation before sync
   - Rollback capability

---

## Support

**Database Locked?** 
- Contact Dapodik admin untuk unlock `pendataan` database

**Token Invalid?**
- Login Dapodik web app test dulu (http://localhost:5774)
- Confirm credential working

**SIMANTAP API Issue?**
- Test endpoint: `POST /api/dapodik/ping`
- Response harus: `{"message": "OK"}`

---

**Version:** 3.1.0 (CSV Direct Import)  
**Last Updated:** 2026-01-12  
**Status:** Production Ready
