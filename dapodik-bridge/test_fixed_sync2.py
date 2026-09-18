# -*- coding: utf-8 -*-
"""
Auto test sync dengan CSV files yang benar (tanpa TEMPLATE)
"""
import csv
import json
import requests
from pathlib import Path

CSV_FOLDER = Path("csv_files")
SIMANTAP_URL = "http://localhost:8000"
API_KEY = "11jtIdsSzunH3UDSAoNywFnLAkM9pGhsaK4RQm0Jafws4HCxC5yqH31lg8ZfSthQ"

print("=" * 70)
print("Auto-Sync Test dengan CSV Fixed")
print("=" * 70)

# Read CSV files (tanpa TEMPLATE)
csv_data = {}
for csv_file in sorted(CSV_FOLDER.glob("*.csv")):
    # Skip TEMPLATE files
    if "TEMPLATE" in csv_file.name:
        continue
        
    try:
        with open(csv_file, "r", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            rows = list(reader)
        csv_data[csv_file.stem] = rows
        print(f"[READ] {csv_file.stem}: {len(rows)} records")
    except Exception as e:
        print(f"[ERROR] {csv_file.stem}: {e}")

if not csv_data:
    print("[ERROR] No CSV files found!")
    exit(1)

# Sync to SIMANTAP
print(f"\n[SYNC] Starting sync...")

for modul, rows in csv_data.items():
    print(f"\n  [{modul}]")
    
    # Map modul name to API endpoint
    modul_map = {
        "PesertaDidik": "peserta-didik",
        "Sekolah": "sekolah",
        "PTK": "ptk",
        "RombonganBelajar": "rombongan-belajar",
    }
    
    modul_slug = modul_map.get(modul, modul.lower().replace("_", "-"))
    
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
            if result.get("success"):
                berhasil = result.get("berhasil", 0)
                diperbarui = result.get("diperbarui", 0)
                gagal = result.get("gagal", 0)
                print(f"    [SUCCESS] Berhasil: {berhasil}, Diperbarui: {diperbarui}, Gagal: {gagal}")
            else:
                print(f"    [FAILED] {result.get('message', 'Unknown error')}")
        else:
            print(f"    [ERROR] {resp.text[:150]}")
            
    except Exception as e:
        print(f"    [EXCEPTION] {e}")

print("\n" + "=" * 70)
print("Test completed")
print("=" * 70)
