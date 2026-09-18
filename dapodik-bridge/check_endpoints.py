# -*- coding: utf-8 -*-
import requests
import re

print("=" * 70)
print("CHECKING DAPODIK - DETAILED INSPECTION")
print("=" * 70)

# Test 1: Cek homepage
print("[1] GET / - cari form login")
try:
    resp = requests.get("http://localhost:5774/", timeout=5)
    if "login" in resp.text.lower() or "password" in resp.text.lower():
        print("    FOUND: Login form detected")
        # Cari input field
        inputs = re.findall(r'<input[^>]*name=["\']([^"\'>/]+)', resp.text, re.IGNORECASE)
        if inputs:
            print("    Form inputs: {}".format(inputs[:5]))
    else:
        print("    No login form found")
    
    # Cari title
    title_match = re.search(r"<title>([^<]+)</title>", resp.text, re.IGNORECASE)
    if title_match:
        print("    Page title: {}".format(title_match.group(1)))
except Exception as e:
    print("    ERROR: {}".format(e))

# Test 2: Cek dengan session
print()
print("[2] Testing dengan SESSION & COOKIES")
session = requests.Session()
try:
    # Coba login dengan token sebagai username/password
    login_data = {
        "username": "YY8j57VCCbAVdJg",
        "password": "",
        "token": "YY8j57VCCbAVdJg",
    }
    resp = session.post("http://localhost:5774/", data=login_data, timeout=5)
    print("    POST / with token: status {}".format(resp.status_code))
    
    # Sekarang coba ambil data dengan session yang sudah login
    resp2 = session.get("http://localhost:5774/?act=Sekolah", timeout=5)
    if "login" not in resp2.text.lower():
        print("    GET ?act=Sekolah after POST: Mungkin berhasil!")
        print("    Body preview: {}".format(resp2.text[:150]))
    else:
        print("    GET ?act=Sekolah: Masih login page")
except Exception as e:
    print("    ERROR: {}".format(e))

# Test 3: Lihat HTML response secara lengkap (cek ada hidden field apa)
print()
print("[3] Checking HTML structure")
try:
    resp = requests.get("http://localhost:5774/?act=Sekolah", timeout=5)
    # Cari semua form
    forms = re.findall(r'<form[^>]*action=["\']([^"\'>/]+)', resp.text, re.IGNORECASE)
    if forms:
        print("    Found forms with actions: {}".format(forms[:3]))
    
    # Cari CSRF token atau session token
    csrf = re.findall(r'name=["\']csrf|name=["\']token|name=["\']_token|value=["\'][a-f0-9]{32,}', resp.text, re.IGNORECASE)
    if csrf:
        print("    Found potential tokens: {}".format(csrf[:3]))
    
    # Save HTML untuk inspeksi manual
    with open("dapodik_response.html", "w", encoding="utf-8") as f:
        f.write(resp.text)
    print("    HTML response saved to: dapodik_response.html")
except Exception as e:
    print("    ERROR: {}".format(e))

print()
print("=" * 70)
print("NEXT STEPS:")
print("1. Cek dapodik_response.html untuk melihat struktur halaman")
print("2. Cari form login field names yang sebenarnya")
print("3. Coba login manual di browser dan lihat cookies")
print("=" * 70)
