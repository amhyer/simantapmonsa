# -*- coding: utf-8 -*-
"""
Coba akses web form login Dapodik dan extract session/cookies
"""
import requests
from bs4 import BeautifulSoup
import time

print("=" * 70)
print("SIMANTAP DAPODIK BRIDGE - STEP 1B: LOGIN & SESSION TEST")
print("=" * 70)

session = requests.Session()
base_url = "http://localhost:5774"

# Step 1: Get login page
print("\n[1] Fetching login page...")
try:
    resp = session.get(base_url, timeout=10)
    soup = BeautifulSoup(resp.text, 'html.parser')
    
    # Find all forms
    forms = soup.find_all('form')
    print("    Found {} forms".format(len(forms)))
    
    # Ambil form login
    for i, form in enumerate(forms):
        action = form.get('action', '')
        method = form.get('method', 'GET').upper()
        inputs = form.find_all('input')
        print("\n    [FORM {}] Action: {} ({})" .format(i+1, action, method))
        print("    Fields:")
        for inp in inputs:
            name = inp.get('name', '')
            type_ = inp.get('type', 'text')
            print("      - {} ({})".format(name, type_))
            
except Exception as e:
    print("    ERROR: {}".format(e))

# Step 2: Try login POST
print("\n[2] Attempting login...")
login_url = base_url + "/roleperan"  # Form action
credentials = {
    "username": "sdnungmongisidi1@gmail.com",
    "password": "Monsajaya12*#",
    "semester_id": "20261"
}

try:
    resp = session.post(login_url, data=credentials, timeout=10, allow_redirects=True)
    print("    Status: {} | URL: {}".format(resp.status_code, resp.url))
    
    # Check if login success
    if "login" in resp.text.lower() and len(resp.text) < 30000:
        print("    Result: FAILED (still login page)")
    else:
        print("    Result: POSSIBLE SUCCESS")
        
    # Check cookies
    print("\n[3] Session Cookies:")
    for name, value in session.cookies.items():
        print("    - {}: {}".format(name, value[:30] if len(value) > 30 else value))
        
except Exception as e:
    print("    ERROR: {}".format(e))

# Step 3: Try akses data endpoint dengan authenticated session
print("\n[4] Trying /customrest endpoints with authenticated session...")

endpoints = [
    "/customrest/ceksekolah",
    "/customrest/sekolah",
    "/customrest/getPesertaDidik",
    "/api/sekolah",
]

for ep in endpoints:
    try:
        resp = session.get(base_url + ep, timeout=5)
        print("\n    [GET {}]".format(ep))
        print("    Status: {}".format(resp.status_code))
        print("    Content-Type: {}".format(resp.headers.get("Content-Type", "")))
        
        if resp.status_code == 200 or resp.status_code == 405:
            print("    Body preview: {}".format(resp.text[:100]))
            try:
                data = resp.json()
                print("    JSON: {}".format(str(data)[:150]))
            except:
                pass
                
    except Exception as e:
        print("    ERROR: {}".format(str(e)[:50]))

print("\n" + "=" * 70)
print("NEXT: Jika login masih gagal, alternatif adalah query database PostgreSQL")
print("      atau gunakan API endpoint yang berbeda")
print("=" * 70)
