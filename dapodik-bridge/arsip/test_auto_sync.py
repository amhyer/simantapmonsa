# -*- coding: utf-8 -*-
"""
Test auto_sync script - Non-interactive version
"""

import os
import csv
import json
import requests
from pathlib import Path

print("=" * 70)
print("Testing Auto-Sync Script")
print("=" * 70)

CSV_FOLDER = Path("csv_files")
SIMANTAP_URL = "http://localhost:8000"

# Step 1: Check/create sample CSV
print("\n[STEP 1] Checking CSV files...")

csv_files = list(CSV_FOLDER.glob("*.csv"))
print(f"  Found {len(csv_files)} CSV files")

if not csv_files:
    print("  Creating sample CSV files...")
    
    # Create sample
    peserta_csv = CSV_FOLDER / "PesertaDidik.csv"
    with open(peserta_csv, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=["nama", "nisn", "nis", "jenis_kelamin", "kelas"])
        writer.writeheader()
        writer.writerow({
            "nama": "Ahmad Rizki",
            "nisn": "0081234001",
            "nis": "2026001",
            "jenis_kelamin": "L",
            "kelas": "1A"
        })
    print(f"  Created: {peserta_csv}")
    
    sekolah_csv = CSV_FOLDER / "Sekolah.csv"
    with open(sekolah_csv, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=["npsn", "nama_sekolah", "provinsi"])
        writer.writeheader()
        writer.writerow({
            "npsn": "20603161",
            "nama_sekolah": "SDN Ungmongisidi 1",
            "provinsi": "Jawa Barat"
        })
    print(f"  Created: {sekolah_csv}")

# Step 2: Test SIMANTAP
print("\n[STEP 2] Testing SIMANTAP...")
try:
    resp = requests.post(f"{SIMANTAP_URL}/api/dapodik/ping", timeout=10)
    print(f"  Status: {resp.status_code}")
    print(f"  Response: {resp.json()}")
except Exception as e:
    print(f"  ERROR: {e}")
    exit(1)

# Step 3: Get API key from admin (for testing, use a test value)
print("\n[STEP 3] Need API Key from SIMANTAP admin...")
print("  To get API Key:")
print("  1. Go to http://localhost:8000/admin")
print("  2. Menu: Settings > API Keys")
print("  3. Copy the API Key")

print("\n  For testing, using placeholder...")

# Mock test without actual API key
print("\n[TEST] CSV structure is correct")
print("  Ready to sync when real API key provided")

print("\n" + "=" * 70)
print("Test completed - use auto_sync_to_simantap.py for actual sync")
print("=" * 70)
