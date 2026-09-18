# -*- coding: utf-8 -*-
"""
Auto test sync dengan API key yang sudah fixed
"""
import csv
import json
import requests
from pathlib import Path

CSV_FOLDER = Path("csv_files")
SIMANTAP_URL = "http://localhost:8000"
API_KEY = "11jtIdsSzunH3UDSAoNywFnLAkM9pGhsaK4RQm0Jafws4HCxC5yqH31lg8ZfSthQ"

print("=" * 70)
print("Testing Auto-Sync dengan API Key Fixed")
print("=" * 70)

# Read CSV files
csv_data = {}
for csv_file in CSV_FOLDER.glob("*.csv"):
    try:
        with open(csv_file, "r", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            rows = list(reader)
        csv_data[csv_file.stem] = rows
        print(f"[READ] {csv_file.stem}: {len(rows)} records")
    except Exception as e:
        print(f"[ERROR] {csv_file.stem}: {e}")

# Sync to SIMANTAP
print(f"\n[SYNC] Starting sync to {SIMANTAP_URL}...")

for modul, rows in csv_data.items():
    print(f"\n  [SYNC] {modul}...")
    
    modul_slug = modul.lower().replace("_", "-")
    
    payload = {
        "semester_id": "20261",
        "tahun_ajaran": "2025/2026",
        "nama_semester": "ganjil",
        "data": rows
    }
    
    try:
        resp = requests.post(
            f"{SIMANTAP_URL}/api/dapodik/sync/{modul_slug}",
            headers={
                "X-API-Key": API_KEY,
                "Content-Type": "application/json",
            },
            json=payload,
            timeout=120
        )
        
        print(f"    Status: {resp.status_code}")
        
        if resp.status_code == 200:
            result = resp.json()
            print(f"    Response: {json.dumps(result, indent=2)[:200]}")
        else:
            print(f"    ERROR: {resp.text[:200]}")
            
    except Exception as e:
        print(f"    [ERROR] {e}")

print("\n" + "=" * 70)
print("Test completed")
print("=" * 70)
