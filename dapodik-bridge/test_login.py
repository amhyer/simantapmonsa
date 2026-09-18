# -*- coding: utf-8 -*-
import requests
import re

print("=" * 70)
print("DAPODIK LOGIN TEST")
print("=" * 70)

# Dari HTML yang diekstrak, form login ke endpoint: /roleperan
# Field: username, password, semester_id

session = requests.Session()

# Coba login
login_url = "http://localhost:5774/roleperan"
login_data = {
    "username": "admin@sekolah.test",  # Default test coba
    "password": "admin123",              # Default test coba  
    "semester_id": "20261"
}

print("\n[1] Trying default credentials...")
print("    URL: {}".format(login_url))
print("    Username: {}".format(login_data["username"]))
print("    Password: {}".format(login_data["password"]))

try:
    resp = session.post(login_url, data=login_data, timeout=10, allow_redirects=True)
    print("    Response status: {}".format(resp.status_code))
    print("    Response URL: {}".format(resp.url))
    
    # Cek apakah berhasil login
    if "Sekolah" in resp.text or "Dashboard" in resp.text or "logout" in resp.text.lower():
        print("    [SUCCESS] Login berhasil!")
    else:
        print("    [FAILED] Masih belum login")
    
    # Coba ambil data Sekolah dengan session yang sudah login
    print("\n[2] Trying to fetch Sekolah data with authenticated session...")
    resp2 = session.get("http://localhost:5774/?act=Sekolah", timeout=10)
    print("    Status: {}".format(resp2.status_code))
    
    if "login" not in resp2.text.lower():
        print("    [POSSIBLE SUCCESS] No login page detected!")
        # Cari JSON data
        try:
            data = resp2.json()
            print("    Data type: {}".format(type(data).__name__))
            print("    Data preview: {}".format(str(data)[:200]))
        except:
            print("    Response body preview: {}".format(resp2.text[:200]))
    else:
        print("    [FAILED] Still getting login page")
        
except Exception as e:
    print("    ERROR: {}".format(e))

# Juga coba cek apakah Dapodik punya REST API endpoint
print("\n[3] Checking for REST API endpoints...")
rest_endpoints = [
    "http://localhost:5774/api/sekolah",
    "http://localhost:5774/api/v1/sekolah",
    "http://localhost:5774/rest/sekolah",
    "http://localhost:5774/customrest/sekolah",
    "http://localhost:5774/customrest/ceksekolah",
]

for ep in rest_endpoints:
    try:
        resp = session.get(ep, timeout=5)
        if resp.status_code != 404 and "login" not in resp.text.lower():
            print("    [{} OK] {}".format(resp.status_code, ep))
            print("        Preview: {}".format(resp.text[:100]))
        else:
            print("    [{} SKIP] {}".format(resp.status_code, ep))
    except:
        pass

print("\n" + "=" * 70)
print("KESIMPULAN:")
print("1. Dapodik tidak memiliki API query string seperti ?act=Sekolah&token=...")
print("2. Dapodik adalah web app dengan login form, bukan API service")
print("3. Penarikan data memerlukan: login terlebih dahulu")
print("4. Alternatif: gunakan database Dapodik langsung atau REST API jika ada")
print("=" * 70)
