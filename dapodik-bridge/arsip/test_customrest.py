# -*- coding: utf-8 -*-
import requests
import json

print("=" * 70)
print("DAPODIK CUSTOMREST API TEST")
print("=" * 70)

session = requests.Session()

# Login terlebih dahulu (format: /?act=[module]&token=xxx tapi BUKAN API)
# Tapi Dapodik versi ini butuh login via form

# Coba akses customrest tanpa login dulu (mungkin public)
print("\n[1] Testing /customrest/ceksekolah without login...")
try:
    resp = session.get("http://localhost:5774/customrest/ceksekolah", timeout=5)
    print("    Status: {}".format(resp.status_code))
    print("    Body: {}".format(resp.text[:200]))
    try:
        data = resp.json()
        print("    JSON: {}".format(data))
    except:
        pass
except Exception as e:
    print("    ERROR: {}".format(e))

# Coba login dengan credential yang benar
print("\n[2] Login with valid credentials...")
login_url = "http://localhost:5774/roleperan"

# Coba default Dapodik credentials
credentials = [
    {"username": "admin", "password": "admin123"},
    {"username": "operator", "password": "operator123"},
    {"username": "admin@dapodik.test", "password": "admin"},
]

success = False
for cred in credentials:
    login_data = {
        "username": cred["username"],
        "password": cred["password"],
        "semester_id": "20261"
    }
    print("    Trying: {} / {}".format(cred["username"], cred["password"]))
    try:
        resp = session.post(login_url, data=login_data, timeout=5, allow_redirects=True)
        if "PenggunaTidakTerdaftar" not in resp.url and "PasswordSalah" not in resp.url and "login" not in resp.text.lower():
            print("    [SUCCESS]")
            success = True
            break
        else:
            print("    [FAILED] - {}".format(resp.url.split("#")[-1] if "#" in resp.url else "Login page"))
    except Exception as e:
        print("    [ERROR] {}".format(str(e)[:50]))

if success:
    print("\n[3] Authenticated - trying /customrest endpoints...")
    
    endpoints = [
        "/customrest/ceksekolah",
        "/customrest/sekolah",
        "/customrest/peserta_didik",
        "/customrest/siswa",
        "/customrest/student",
        "/customrest/Sekolah",
        "/customrest/PesertaDidik",
    ]
    
    for ep in endpoints:
        try:
            resp = session.get("http://localhost:5774{}".format(ep), timeout=5)
            if resp.status_code == 200 or resp.status_code == 405:
                print("\n    [{}] {}".format(resp.status_code, ep))
                print("    Content-Type: {}".format(resp.headers.get("Content-Type", "")))
                print("    Body preview: {}".format(resp.text[:150]))
                
                # Try JSON
                try:
                    data = resp.json()
                    print("    JSON Data: {}".format(json.dumps(data, indent=2)[:300]))
                except:
                    pass
        except Exception as e:
            pass

else:
    print("\n[ERROR] Login gagal. Coba dengan credential yang benar.")

print("\n" + "=" * 70)
print("NEXT STEPS:")
print("1. Coba login manual di http://localhost:5774")
print("2. Catat credential yang benar")
print("3. Coba akses /customrest endpoints setelah login")
print("=" * 70)
