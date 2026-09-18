# QUICK START - SIMANTAP Dapodik Bridge v3.1

## TL;DR

❌ **Old way (BROKEN):** Token auth dari Dapodik webservice  
✅ **New way (WORKING):** Manual CSV export → Auto sync to SIMANTAP

---

## 5-Minute Setup

### 1️⃣ Export CSV dari Dapodik

```
1. Buka browser: http://localhost:5774
2. Login: sdnungmongisidi1@gmail.com / Monsajaya12*#
3. Cari menu "Export Data" atau "Manajemen Data"
4. Export & save sebagai CSV:
   - PesertaDidik.csv
   - Sekolah.csv
   - PTK.csv (optional)
   - RombonganBelajar.csv (optional)
```

### 2️⃣ Copy CSV ke Folder

```
Copy file CSV ke:
  D:\Project\simantap\dapodik-bridge\csv_files\

Contoh:
  csv_files/
  ├── PesertaDidik.csv
  ├── Sekolah.csv
  └── (optional files)
```

### 3️⃣ Jalankan Aplikasi

```bash
cd D:\Project\simantap\dapodik-bridge
python main.py
```

### 4️⃣ Sync Data

```
GUI akan muncul:

1. Input Server URL: http://localhost:8000
2. Input API Key: (dari SIMANTAP admin panel)
3. Klik "Test SIMANTAP" → harus OK
4. Klik "Pilih File CSV" → auto-load
5. Klik "Sinkronisasi" → mulai sync
6. Tunggu selesai, check hasil di SIMANTAP
```

---

## File Locations

```
D:\Project\simantap\dapodik-bridge\
├── main.py                      ← Aplikasi utama (run ini)
├── csv_files/                   ← Folder CSV
│   ├── PesertaDidik.csv        (dari Dapodik export)
│   ├── Sekolah.csv
│   ├── PesertaDidik_TEMPLATE.csv  (template reference)
│   └── Sekolah_TEMPLATE.csv
├── config.json                  (auto-created)
└── Documents/
    ├── ERROR_ANALYSIS_REPORT.md (technical)
    ├── README_REFACTOR.md       (detailed guide)
    └── REFACTOR_NOTES.md        (architecture)
```

---

## CSV Format Reminder

### PesertaDidik.csv
```
nama,nisn,nis,jenis_kelamin,kelas
Ahmad Rizki,0081234001,2026001,L,1A
Siti Nur Azizah,0081234002,2026002,P,1A
```

### Sekolah.csv
```
npsn,nama_sekolah,alamat,provinsi,kabupaten
20603161,SDN Ungmongisidi 1,Jl Pendidikan No 1,Jawa Barat,Indramayu
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| "File CSV tidak ditemukan" | Pastikan file di folder `csv_files/` dengan nama yang tepat |
| "Gagal terhubung SIMANTAP" | Pastikan Laravel running (`php artisan serve`) port 8000 |
| "API Key invalid" | Cek di SIMANTAP admin > API Keys |
| "CSV parse error" | Pastikan file encoding UTF-8, delimiter comma (`,`) |
| "Data tidak masuk SIMANTAP" | Check Laravel logs: `storage/logs/laravel.log` |

---

## What's Different from v3.0?

```
v3.0 (BROKEN):
  - Coba ambil data via Dapodik token auth
  - Complex session/cookie logic
  - Database connection required
  - Frequently fails

v3.1 (WORKING):
  ✓ CSV file import
  ✓ Simple file reading
  ✓ No database needed
  ✓ Reliable & controllable
```

---

## Next (After First Sync)

1. **Delete old debug files** (optional):
   ```bash
   del test_*.py check_endpoints.py debug_dapodik.py step1_login_test.py
   ```

2. **Keep this:**
   ```bash
   main.py, config.json, csv_files/
   ```

3. **For next sync:**
   - Export new CSV from Dapodik
   - Replace old CSV in folder
   - Run `python main.py` again
   - Click sync

---

## Still Having Issues?

Check detailed guides:
- **ERROR_ANALYSIS_REPORT.md** - Why v3.0 failed
- **README_REFACTOR.md** - Complete setup guide
- **REFACTOR_NOTES.md** - Technical deep dive

---

## Support

- Dapodik export issue → Contact Dapodik admin
- SIMANTAP API issue → Check `http://localhost:8000/api/dapodik/ping`
- CSV format issue → See templates in `csv_files/`

---

**Version:** 3.1.0  
**Status:** Production Ready ✅  
**Last Updated:** 2026-01-12
