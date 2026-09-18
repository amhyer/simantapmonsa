# SIMANTAP Dapodik Bridge - Refactored v3.1

## Penyebab Error Penarikan Data

### Problem Analysis:
1. **Dapodik Local BUKAN API Service**
   - Dapodik adalah web application, bukan pure API
   - Tidak support query string auth seperti `?act=Sekolah&token=xxx`
   - Database PostgreSQL terkunci dengan password

2. **Authentication Failed**
   - Web form login tidak bekerja dengan credential yang diberikan
   - Kemungkinan: user belum punya role/permission di Dapodik

3. **No Direct Database Access**
   - PostgreSQL database terkunci
   - Credential database tidak tersedia

## Solusi: CSV Direct Import

Alih-alih menarik data dari Dapodik API/Database, gunakan **manual CSV export dari Dapodik**:

### Workflow:

1. **Export data dari Dapodik Web App**
   - Login ke Dapodik di browser: http://localhost:5774
   - Menu: Admin > Export Data
   - Export sebagai CSV untuk setiap modul:
     - `PesertaDidik.csv`
     - `Sekolah.csv`
     - `PTK.csv`
     - `RombonganBelajar.csv`

2. **Tempatkan CSV files**
   - Buat folder: `D:\Project\simantap\dapodik-bridge\csv_files\`
   - Pindahkan semua CSV ke folder tersebut

3. **Jalankan SIMANTAP Bridge**
   - `python main_refactored.py`
   - Klik "Pilih File CSV" → akan auto-detect dan load semua CSV
   - Klik "Sinkronisasi" → data akan push ke Laravel SIMANTAP

### CSV Format Requirements:

**PesertaDidik.csv:**
```
nama,nisn,nis,jenis_kelamin,kelas,nama_ayah,nama_ibu,tempat_lahir,tanggal_lahir
Ahmad Rizki,0081234001,2026001,L,1A,Budi Santoso,Siti Rahayu,Jakarta,2011-01-15
```

**Sekolah.csv:**
```
npsn,nama_sekolah,alamat,provinsi,kabupaten
20603161,SDN Ungmongisidi 1,Jalan Pendidikan No 1,Jawa Barat,Indramayu
```

**PTK.csv:**
```
nip,nama,jabatan,bidang_keahlian
197508101998021001,Drs. Bambang,Kepala Sekolah,Administrasi
```

**RombonganBelajar.csv:**
```
nama,tingkat,jumlah_siswa
Kelas 1A,1,35
Kelas 1B,1,36
```

### Step-by-Step:

```bash
# 1. Install dependencies
pip install psycopg2-binary  # Sudah ada
pip install requests PySimpleGUI

# 2. Export CSV dari Dapodik
# - Buka http://localhost:5774 di browser
# - Login dengan credential Anda
# - Export masing-masing modul ke CSV

# 3. Buat folder CSV
mkdir csv_files

# 4. Pindahkan CSV ke folder
# - Pastikan nama file mengandung modul name:
#   - PesertaDidik.csv atau peserta_didik.csv
#   - Sekolah.csv
#   - PTK.csv
#   - RombonganBelajar.csv

# 5. Jalankan aplikasi
python main_refactored.py
```

### Jika Ingin Langsung Database Access:

Jika Anda bisa unlock database PostgreSQL:
1. Cari password PostgreSQL di config Dapodik
2. Uncomment & modifikasi fungsi `fetch_direct_from_postgres()` di script
3. Hubungi admin Dapodik untuk unlock database

## Migration Path (Future):

### Option A: Sidecar Dapodik Connector Service
Buat service Python standalone yang:
- Login ke Dapodik web app via headless browser (Selenium)
- Scrape data dari UI
- Export ke SIMANTAP API

### Option B: Dapodik Module Integration
Jika Dapodik support plugin/extension:
- Buat module Dapodik yang expose REST API
- Push data ke SIMANTAP via webhook

### Option C: Event-driven Sync
- Monitor Dapodik database changes (database triggers)
- Send notification ke SIMANTAP
- Real-time sync

## Quick Test:

```bash
# Test koneksi SIMANTAP
python -c "
import requests
resp = requests.post('http://localhost:8000/api/dapodik/ping', timeout=10)
print(resp.json())
"

# Test baca CSV
python -c "
from main_refactored import read_csv_file
result = read_csv_file('csv_files/Sekolah.csv')
print('Status:', result['success'])
print('Records:', result['total'])
"
```

---

**Next Steps:**
1. Export CSV dari Dapodik
2. Jalankan `main_refactored.py`
3. Test sync ke SIMANTAP
4. Jika sukses, replace original `main.py` dengan versi baru
