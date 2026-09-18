# -*- coding: utf-8 -*-
"""
SIMANTAP Dapodik Bridge - Simplified API Method
Menggunakan requests library untuk komunikasi HTTP langsung
"""

import requests
import json
from datetime import datetime
from pathlib import Path

print("=" * 70)
print("SIMANTAP Dapodik Bridge - Tarik Data (Simplified Method)")
print("=" * 70)

# Konfigurasi
DAPODIK_URL = "http://localhost:5774"
USERNAME = "sdnungmongisidi1@gmail.com"
PASSWORD = "Monsajaya12*#"
CSV_FOLDER = Path("csv_files")

# Buat session
session = requests.Session()
session.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
})

print(f"\n[CONFIG]")
print(f"  URL: {DAPODIK_URL}")
print(f"  User: {USERNAME}")
print(f"  CSV Folder: {CSV_FOLDER}")

# Step 1: Test koneksi
print(f"\n[STEP 1] Test koneksi ke Dapodik...")
try:
    resp = session.get(DAPODIK_URL, timeout=10)
    print(f"  Status: {resp.status_code}")
    if resp.status_code == 200:
        print("  Result: OK - Dapodik accessible")
    else:
        print(f"  Result: ERROR - HTTP {resp.status_code}")
except Exception as e:
    print(f"  ERROR: {e}")
    exit(1)

# Step 2: Login
print(f"\n[STEP 2] Login ke Dapodik...")
try:
    login_url = f"{DAPODIK_URL}/roleperan"
    login_data = {
        "username": USERNAME,
        "password": PASSWORD,
        "semester_id": "20261"
    }
    
    resp = session.post(login_url, data=login_data, timeout=10, allow_redirects=True)
    print(f"  Status: {resp.status_code}")
    print(f"  Redirect URL: {resp.url}")
    
    # Check cookies
    if session.cookies:
        print(f"  Cookies: {len(session.cookies)} received")
        for name, value in session.cookies.items():
            print(f"    - {name}: {value[:20]}...")
    
    if "login" in resp.url.lower() or "login" in resp.text[:500].lower():
        print("  Result: FAILED - Still on login page")
    else:
        print("  Result: Possible SUCCESS")
        
except Exception as e:
    print(f"  ERROR: {e}")

# Step 3: Cek REST API endpoints
print(f"\n[STEP 3] Mencari REST API endpoints...")
endpoints = [
    ("/api/sekolah", "GET"),
    ("/api/peserta-didik", "GET"),
    ("/api/v1/sekolah", "GET"),
    ("/customrest/sekolah", "GET"),
    ("/customrest/getPesertaDidik", "GET"),
    ("/?act=Sekolah", "GET"),
    ("/?act=PesertaDidik", "GET"),
]

for endpoint, method in endpoints:
    try:
        url = DAPODIK_URL + endpoint
        if method == "GET":
            resp = session.get(url, timeout=5)
        else:
            resp = session.post(url, timeout=5)
        
        status = resp.status_code
        content_type = resp.headers.get("Content-Type", "unknown")
        body_preview = resp.text[:100] if resp.text else "empty"
        
        # Check apakah bukan error page
        if status in [200, 201, 202]:
            if "login" not in body_preview.lower() and "<html" not in body_preview.lower():
                print(f"  [CANDIDATE] {method} {endpoint}")
                print(f"    Status: {status}, Content-Type: {content_type}")
                print(f"    Preview: {body_preview}")
                
                # Try JSON parse
                try:
                    data = resp.json()
                    print(f"    JSON Valid: YES ({type(data).__name__})")
                except:
                    print(f"    JSON Valid: NO")
    except requests.exceptions.Timeout:
        pass
    except Exception as e:
        pass

# Step 4: Coba download export langsung
print(f"\n[STEP 4] Mencoba download export files...")
try:
    # Coba endpoint yang biasanya ada di apps Dapodik
    export_endpoints = [
        "/export/sekolah",
        "/export/peserta_didik",
        "/download/sekolah",
        "/download/csv/sekolah",
    ]
    
    for ep in export_endpoints:
        try:
            url = DAPODIK_URL + ep
            resp = session.get(url, timeout=5)
            
            if resp.status_code == 200 and len(resp.content) > 100:
                filename = f"export_{ep.split('/')[-1]}.csv"
                print(f"  [SUCCESS] Downloaded: {filename}")
                with open(filename, "wb") as f:
                    f.write(resp.content)
        except:
            pass
            
except Exception as e:
    print(f"  ERROR: {e}")

# Step 5: Informasi untuk manual action
print(f"\n[STEP 5] Instruksi manual...")
print("""
Karena Dapodik web app tidak punya public API export, 
silakan lakukan manual export:

1. Buka http://localhost:5774 di browser
2. Pastikan sudah login
3. Cari menu: Admin > Export Data
4. Export setiap modul:
   - Peserta Didik
   - Sekolah
   - PTK
   - Rombongan Belajar
5. Simpan dengan nama:
   - PesertaDidik.csv
   - Sekolah.csv
   - PTK.csv
   - RombonganBelajar.csv
6. Copy ke folder: csv_files/
7. Run: python main.py
8. Click "Sinkronisasi"
""")

print("\n" + "=" * 70)
print("SELESAI")
print("=" * 70)
