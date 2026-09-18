# -*- coding: utf-8 -*-
"""
Test Dapodik Sync API (port 5437) - prefill push endpoint
Dari config: SyncPortDikdas=5437
"""
import requests
import json

print("=" * 70)
print("TESTING DAPODIK SYNC API (PORT 5437)")
print("=" * 70)

sync_port = 5437
base_url = "http://localhost:{}".format(sync_port)

endpoints = [
    "/",
    "/index.php",
    "/prefill/push_prefill.php",
    "/api",
    "/webservice",
    "/customrest",
    "/customrest/ceksekolah",
]

print("\n[1] Testing Sync API endpoints...")

for ep in endpoints:
    url = base_url + ep
    try:
        resp = requests.get(url, timeout=5)
        print("\n[GET {}]".format(ep))
        print("  Status: {}".format(resp.status_code))
        print("  Content-Type: {}".format(resp.headers.get("Content-Type", "")))
        print("  Body: {}".format(resp.text[:80]))
        
        # Try JSON
        try:
            data = resp.json()
            print("  JSON: {}".format(str(data)[:100]))
        except:
            pass
            
    except requests.exceptions.ConnectionError:
        print("\n[GET {}] - NOT RUNNING (port 5437 tidak aktif)".format(ep))
        break
    except Exception as e:
        print("\n[GET {}] ERROR: {}".format(ep, str(e)[:50]))

print("\n" + "=" * 70)
print("[INFO] Jika port 5437 aktif, test prefill API")
print("=" * 70)

# Test push prefill (POST)
if True:
    print("\n[2] Testing POST /prefill/push_prefill.php...")
    
    push_data = {
        "act": "Sekolah",
        "data": [],
        "token": "YY8j57VCCbAVdJg"
    }
    
    try:
        resp = requests.post(
            base_url + "/prefill/push_prefill.php",
            json=push_data,
            timeout=10
        )
        print("  Status: {}".format(resp.status_code))
        print("  Response: {}".format(resp.text[:200]))
    except requests.exceptions.ConnectionError:
        print("  ERROR: Port 5437 tidak aktif")
    except Exception as e:
        print("  ERROR: {}".format(e))
