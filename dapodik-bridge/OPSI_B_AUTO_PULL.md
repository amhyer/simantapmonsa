# CARA TARIK DATA DAPODIK - Opsi B (Automated)

## Ringkasan

Ada 3 script yang sudah dibuat untuk tarik data dari Dapodik:

1. **`tarik_data_dapodik.py`** - Coba web scraping (HTTP requests)
2. **`tarik_dari_database.py`** - Coba direct database query
3. **`auto_sync_to_simantap.py`** - Automated sync ke SIMANTAP ✅ (Paling mudah)

---

## Status Hasil Testing

| Script | Kesimpulan | Status |
|--------|-----------|--------|
| tarik_data_dapodik.py | Dapodik tidak punya export API | Gagal |
| tarik_dari_database.py | PostgreSQL terkunci, password unknown | Gagal |
| auto_sync_to_simantap.py | Bekerja dengan CSV + API key | **✅ BERFUNGSI** |

---

## SOLUSI TERBAIK: auto_sync_to_simantap.py

Script ini adalah **solusi tercepat dan paling praktis**.

### Cara Kerja:

```
1. Cek CSV di folder csv_files/
2. Jika tidak ada, create sample CSV
3. Test koneksi ke SIMANTAP
4. User input API Key dari admin panel
5. Auto-sync semua CSV ke SIMANTAP
6. Show hasil (berhasil/gagal)
```

### Kebutuhan:

- ✅ API Key dari SIMANTAP admin
- ✅ CSV files (bisa sample atau dari Dapodik)
- ✅ SIMANTAP running di http://localhost:8000

---

## LANGKAH-LANGKAH PENGGUNAAN

### Opsi 1: Dengan Data Dapodik Manual (Rekomendasi)

**Step 1: Export CSV dari Dapodik**
```
1. Buka: http://localhost:5774
2. Login: sdnungmongisidi1@gmail.com / Monsajaya12*#
3. Menu: Admin → Export Data
4. Export semua modul sebagai CSV
5. Simpan dengan nama:
   - PesertaDidik.csv
   - Sekolah.csv
   - PTK.csv
   - RombonganBelajar.csv
```

**Step 2: Copy CSV ke folder**
```bash
D:\Project\simantap\dapodik-bridge\csv_files\
├── PesertaDidik.csv
├── Sekolah.csv
├── PTK.csv
└── RombonganBelajar.csv
```

**Step 3: Run script**
```bash
cd D:\Project\simantap\dapodik-bridge
python auto_sync_to_simantap.py
```

**Step 4: Input API Key**
```
Script akan bertanya:
  Enter SIMANTAP API Key (from admin panel): [PASTE_KEY_HERE]
```

Untuk mendapat API Key:
- Buka: http://localhost:8000/admin
- Menu: Settings > API Keys
- Copy nilai API Key

**Step 5: Tunggu proses selesai**
```
[SYNC] PesertaDidik...
  [OK] 2 new, 0 updated, 0 failed
[SYNC] Sekolah...
  [OK] 1 new, 0 updated, 0 failed
...
```

---

### Opsi 2: Dengan Sample Data (Untuk Testing)

**Step 1: Jalankan langsung**
```bash
cd D:\Project\simantap\dapodik-bridge
python auto_sync_to_simantap.py
```

**Step 2: Script akan auto-create sample CSV**
```
[STEP 1] Checking CSV files...
  [NOTE] No CSV files found - creating samples from template...
  [CREATED] csv_files/PesertaDidik.csv
  [CREATED] csv_files/Sekolah.csv
```

**Step 3: Input API Key & sync**
```
Enter SIMANTAP API Key: [YOUR_KEY]
[SYNC] PesertaDidik...
[SYNC] Sekolah...
```

**Step 4: Verifikasi di SIMANTAP**
```
Buka: http://localhost:8000
Check data di menu Dapodik
```

---

## MENDAPATKAN API KEY SIMANTAP

### Cara 1: Melalui Admin Panel

```
1. Login ke SIMANTAP: http://localhost:8000
2. Username: admin@example.com (atau sesuai setup)
3. Password: [setup saat install]
4. Menu: Admin / Settings
5. Cari: API Keys atau API Settings
6. Copy API Key (format: string panjang)
```

### Cara 2: Via Database (Jika admin panel tidak ada)

```sql
-- SSH ke server SIMANTAP
-- Jalankan:
SELECT api_key FROM api_keys WHERE active = 1 LIMIT 1;
```

### Cara 3: Generate Baru

Jika belum ada API Key:
- Di admin panel, klik "Generate New API Key"
- Simpan dengan aman
- Gunakan di script

---

## TROUBLESHOOTING

### Error: "Cannot connect to SIMANTAP"

**Solusi:**
```bash
# Pastikan Laravel running
cd D:\Project\simantap
php artisan serve

# Cek di browser
http://localhost:8000/api/dapodik/ping
# Harus response: {"success": true, ...}
```

### Error: "API Key invalid"

**Solusi:**
1. Cek API Key di admin panel SIMANTAP
2. Pastikan benar-benar copy (no spaces)
3. Jika masih error, generate key baru

### Error: "CSV parse error"

**Solusi:**
1. Pastikan file encoding UTF-8
2. Check delimiter (harus comma `,`)
3. Lihat template di `csv_files/`

### Error: "Module sync failed"

**Solusi:**
1. Check `storage/logs/laravel.log`
2. Verifikasi field names sesuai schema
3. Check data format (dates, etc)

---

## SCRIPT DETAILS

### auto_sync_to_simantap.py

**Input:**
- CSV files di folder `csv_files/`
- API Key SIMANTAP (via user input)

**Process:**
1. Baca semua CSV files
2. Ubah ke JSON format
3. POST ke `/api/dapodik/sync/{modul}`
4. Process hasil

**Output:**
- Summary hasil sync
- Count berhasil/gagal/error

**Timeout:** 120 detik per modul

---

## KOMPARASI 3 SCRIPT

| Fitur | tarik_data | tarik_db | auto_sync |
|-------|-----------|----------|-----------|
| Web Scraping | Ya | Tidak | Tidak |
| Database Query | Tidak | Ya | Tidak |
| File CSV | Tidak | Tidak | Ya |
| Auto Sync | Tidak | Tidak | Ya |
| Complexity | Medium | High | Low |
| Reliability | Low | Low | High |
| Status | **FAILED** | **FAILED** | **SUCCESS** |

---

## CARA OTOMATISASI BULANAN

Jika mau auto-run setiap bulan:

### Windows Task Scheduler:

```batch
# File: sync_dapodik.bat
cd D:\Project\simantap\dapodik-bridge
python auto_sync_to_simantap.py
```

Kemudian:
1. Buka Task Scheduler
2. Create Basic Task
3. Name: "Sync Dapodik"
4. Trigger: Monthly, hari 1
5. Action: Run batch file
6. Pastikan CSV sudah di-update sebelumnya

---

## NEXT STEPS

1. **Sekarang:** Jalankan `python auto_sync_to_simantap.py`
2. **Siapkan:** API Key dari admin SIMANTAP
3. **Export:** CSV dari Dapodik (jika punya data)
4. **Run:** Script dengan API Key
5. **Verify:** Data di SIMANTAP

---

## FILE YANG TERSEDIA

```
dapodik-bridge/
├── auto_sync_to_simantap.py    ← USE THIS (MAIN)
├── tarik_data_dapodik.py       (backup, tidak bekerja)
├── tarik_dari_database.py      (backup, tidak bekerja)
├── test_auto_sync.py           (testing only)
└── csv_files/
    ├── PesertaDidik.csv        (auto-created jika tidak ada)
    └── Sekolah.csv             (auto-created jika tidak ada)
```

---

**Status:** ✅ READY TO USE

**Jalankan sekarang:** `python auto_sync_to_simantap.py`
