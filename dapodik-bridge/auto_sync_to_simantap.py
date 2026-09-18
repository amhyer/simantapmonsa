# -*- coding: utf-8 -*-
"""
SIMANTAP Dapodik Bridge - Complete Automated Solution
Menampilkan data sample yang bisa disync, atau user upload CSV manual
"""

import os
import csv
import json
import requests
from pathlib import Path
from datetime import datetime

print("=" * 70)
print("SIMANTAP Dapodik Bridge - Automated Data Pull & Sync")
print("=" * 70)

CSV_FOLDER = Path("csv_files")
CSV_FOLDER.mkdir(exist_ok=True)

SIMANTAP_URL = "http://localhost:8000"

# Step 1: Create sample CSV files jika belum ada
print("\n[STEP 1] Checking CSV files...")

sample_files_created = False

if not list(CSV_FOLDER.glob("*.csv")):
    print("  [NOTE] No CSV files found - creating samples from template...")
    
    # Create PesertaDidik sample
    peserta_didik_file = CSV_FOLDER / "PesertaDidik.csv"
    if not peserta_didik_file.exists():
        with open(peserta_didik_file, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=[
                "nama", "nisn", "nis", "jenis_kelamin", "kelas",
                "nama_ayah", "nama_ibu", "tempat_lahir", "tanggal_lahir"
            ])
            writer.writeheader()
            writer.writerows([
                {
                    "nama": "Ahmad Rizki",
                    "nisn": "0081234001",
                    "nis": "2026001",
                    "jenis_kelamin": "L",
                    "kelas": "1A",
                    "nama_ayah": "Budi Santoso",
                    "nama_ibu": "Siti Rahayu",
                    "tempat_lahir": "Jakarta",
                    "tanggal_lahir": "2011-01-15"
                },
                {
                    "nama": "Siti Nur Azizah",
                    "nisn": "0081234002",
                    "nis": "2026002",
                    "jenis_kelamin": "P",
                    "kelas": "1A",
                    "nama_ayah": "Hadi Purnomo",
                    "nama_ibu": "Liswanti",
                    "tempat_lahir": "Bandung",
                    "tanggal_lahir": "2011-03-22"
                },
            ])
        print(f"  [CREATED] {peserta_didik_file}")
        sample_files_created = True
    
    # Create Sekolah sample
    sekolah_file = CSV_FOLDER / "Sekolah.csv"
    if not sekolah_file.exists():
        with open(sekolah_file, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=[
                "npsn", "nama_sekolah", "alamat", "provinsi", "kabupaten"
            ])
            writer.writeheader()
            writer.writerows([
                {
                    "npsn": "20603161",
                    "nama_sekolah": "SDN Ungmongisidi 1",
                    "alamat": "Jalan Pendidikan No 1",
                    "provinsi": "Jawa Barat",
                    "kabupaten": "Indramayu"
                },
            ])
        print(f"  [CREATED] {sekolah_file}")
        sample_files_created = True

if sample_files_created:
    print("\n  [NOTE] Sample CSV files created!")
    print("  [NOTE] Please REPLACE with your actual data from Dapodik:")
    print("    1. Export from Dapodik: http://localhost:5774")
    print("    2. Download CSV for each module")
    print("    3. Copy to csv_files/ folder")
    print("    4. Run this script again")
else:
    print("  [OK] CSV files found")

# Step 2: List CSV files
print("\n[STEP 2] Available CSV files:")
csv_files = list(CSV_FOLDER.glob("*.csv"))
for csv_file in csv_files:
    size = csv_file.stat().st_size
    print(f"  - {csv_file.name} ({size} bytes)")

if not csv_files:
    print("  [ERROR] No CSV files!")
    exit(1)

# Step 3: Read and validate CSV
print("\n[STEP 3] Reading CSV files...")
csv_data = {}

for csv_file in csv_files:
    try:
        with open(csv_file, "r", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            rows = list(reader)
        
        csv_data[csv_file.stem] = rows
        print(f"  [OK] {csv_file.stem}: {len(rows)} records")
    except Exception as e:
        print(f"  [ERROR] {csv_file.stem}: {e}")

# Step 4: Test SIMANTAP connection
print("\n[STEP 4] Testing SIMANTAP connection...")
try:
    resp = requests.post(
        f"{SIMANTAP_URL}/api/dapodik/ping",
        timeout=10
    )
    
    if resp.status_code == 200:
        result = resp.json()
        print(f"  [OK] SIMANTAP API working")
        print(f"      Message: {result.get('message', 'OK')}")
        print(f"      Version: {result.get('version', 'N/A')}")
    else:
        print(f"  [ERROR] HTTP {resp.status_code}")
except requests.exceptions.ConnectionError:
    print(f"  [ERROR] Cannot connect to {SIMANTAP_URL}")
    print("         Make sure Laravel is running: php artisan serve")
    exit(1)
except Exception as e:
    print(f"  [ERROR] {e}")

# Step 5: Sync to SIMANTAP
print("\n[STEP 5] Syncing to SIMANTAP...")

api_key_input = input("\nEnter SIMANTAP API Key (from admin panel): ").strip()

if not api_key_input:
    print("  [ERROR] API Key required!")
    exit(1)

print(f"  API Key: {api_key_input[:10]}...")

sync_results = {}

for modul, rows in csv_data.items():
    print(f"\n  [SYNC] {modul}...")
    
    # Map modul name to API endpoint
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
                "X-API-Key": api_key_input,
                "Content-Type": "application/json",
            },
            json=payload,
            timeout=120
        )
        
        result = resp.json() if resp.status_code == 200 else {"error": resp.text}
        
        if resp.status_code == 200 and result.get("success"):
            berhasil = result.get("berhasil", 0)
            diperbarui = result.get("diperbarui", 0)
            gagal = result.get("gagal", 0)
            print(f"    [OK] {berhasil} new, {diperbarui} updated, {gagal} failed")
            sync_results[modul] = "SUCCESS"
        else:
            print(f"    [ERROR] HTTP {resp.status_code}")
            print(f"    Response: {str(result)[:100]}")
            sync_results[modul] = "FAILED"
    except Exception as e:
        print(f"    [ERROR] {e}")
        sync_results[modul] = "ERROR"

# Step 6: Summary
print("\n[SUMMARY]")
print("=" * 70)

for modul, status in sync_results.items():
    symbol = "OK" if status == "SUCCESS" else "FAIL"
    print(f"  [{symbol}] {modul}: {status}")

success_count = sum(1 for s in sync_results.values() if s == "SUCCESS")
print(f"\nTotal: {success_count}/{len(sync_results)} modules synced")

print("\n[NEXT STEPS]")
print("1. Check SIMANTAP: http://localhost:8000")
print("2. Verify data in database")
print("3. For next sync, replace CSV files with actual Dapodik data")
print("4. Run this script again")

print("\n" + "=" * 70)
print("SELESAI")
print("=" * 70)
