# -*- coding: utf-8 -*-
"""
SIMANTAP Dapodik Bridge - Direct Database Query v2
Tarik data langsung dari PostgreSQL Dapodik
"""

import psycopg2
import json
import csv
from pathlib import Path

print("=" * 70)
print("SIMANTAP Dapodik Bridge - Direct Database Query v2")
print("=" * 70)

# Konfigurasi Database
DB_HOST = "localhost"
DB_PORT = 54532
DB_NAME = "pendataan"
DB_USER = "postgres"

# Coba berbagai password
DB_PASSWORDS = [
    "",                    # Empty password
    "postgres",           # Default
    "123456",
    "root",
    "password",
    "dapodik",
    "P@ssw0rd",
    "Dapodik123",
]

CSV_FOLDER = Path("csv_files")
CSV_FOLDER.mkdir(exist_ok=True)

print("[CONFIG]")
print(f"  Host: {DB_HOST}:{DB_PORT}")
print(f"  Database: {DB_NAME}")
print(f"  User: {DB_USER}")
print(f"  CSV Folder: {CSV_FOLDER}")

# Step 1: Coba koneksi
print("\n[STEP 1] Trying database connections...")

connection = None
password_used = None

for password in DB_PASSWORDS:
    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME,
            user=DB_USER,
            password=password,
            connect_timeout=5
        )
        print(f"  [SUCCESS] Password: '{password}'")
        connection = conn
        password_used = password
        break
    except psycopg2.OperationalError as e:
        error_msg = str(e)
        if "password" in error_msg.lower():
            print(f"  [TRYING] Password '{password}': FAIL")
        elif "connection refused" in error_msg.lower():
            print(f"  [ERROR] Connection refused - port not open")
            break
    except Exception as e:
        pass

if not connection:
    print("\n[ERROR] Could not connect to database")
    print("  Tried passwords:", DB_PASSWORDS)
    print("\n[SOLUTION]")
    print("  Option 1: Contact Dapodik admin for database password")
    print("  Option 2: Use CSV manual export method")
    print("  Option 3: Ask system admin to check PostgreSQL settings")
    exit(1)

# Step 2: Query tables
print("\n[STEP 2] Querying database tables...")

try:
    cursor = connection.cursor()
    
    # Cek table yang ada
    cursor.execute("""
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
        ORDER BY table_name
    """)
    
    tables = cursor.fetchall()
    print(f"  Found {len(tables)} tables:")
    for table in tables[:10]:
        print(f"    - {table[0]}")
    if len(tables) > 10:
        print(f"    ... and {len(tables) - 10} more")
    
    # Step 3: Extract data
    print("\n[STEP 3] Extracting data...")
    
    extracted_count = 0
    
    # Query Sekolah
    try:
        cursor.execute("SELECT * FROM sekolah")
        columns = [desc[0] for desc in cursor.description]
        rows = cursor.fetchall()
        
        print(f"  [OK] Sekolah: {len(rows)} records")
        
        csv_file = CSV_FOLDER / "Sekolah.csv"
        with open(csv_file, "w", newline="", encoding="utf-8") as f:
            writer = csv.writer(f)
            writer.writerow(columns)
            writer.writerows(rows)
        print(f"      Saved: {csv_file}")
        extracted_count += 1
    except Exception as e:
        print(f"  [SKIP] Sekolah: {str(e)[:50]}")
    
    # Query Peserta Didik
    try:
        cursor.execute("SELECT * FROM peserta_didik LIMIT 100")
        columns = [desc[0] for desc in cursor.description]
        rows = cursor.fetchall()
        
        print(f"  [OK] Peserta Didik: {len(rows)} records")
        
        csv_file = CSV_FOLDER / "PesertaDidik.csv"
        with open(csv_file, "w", newline="", encoding="utf-8") as f:
            writer = csv.writer(f)
            writer.writerow(columns)
            writer.writerows(rows)
        print(f"      Saved: {csv_file}")
        extracted_count += 1
    except Exception as e:
        print(f"  [SKIP] Peserta Didik: {str(e)[:50]}")
    
    # Query PTK
    try:
        cursor.execute("SELECT * FROM ptk")
        columns = [desc[0] for desc in cursor.description]
        rows = cursor.fetchall()
        
        print(f"  [OK] PTK: {len(rows)} records")
        
        csv_file = CSV_FOLDER / "PTK.csv"
        with open(csv_file, "w", newline="", encoding="utf-8") as f:
            writer = csv.writer(f)
            writer.writerow(columns)
            writer.writerows(rows)
        print(f"      Saved: {csv_file}")
        extracted_count += 1
    except Exception as e:
        print(f"  [SKIP] PTK: {str(e)[:50]}")
    
    # Query Rombongan Belajar
    try:
        cursor.execute("SELECT * FROM rombongan_belajar")
        columns = [desc[0] for desc in cursor.description]
        rows = cursor.fetchall()
        
        print(f"  [OK] Rombongan Belajar: {len(rows)} records")
        
        csv_file = CSV_FOLDER / "RombonganBelajar.csv"
        with open(csv_file, "w", newline="", encoding="utf-8") as f:
            writer = csv.writer(f)
            writer.writerow(columns)
            writer.writerows(rows)
        print(f"      Saved: {csv_file}")
        extracted_count += 1
    except Exception as e:
        print(f"  [SKIP] Rombongan Belajar: {str(e)[:50]}")
    
    cursor.close()
    
    print(f"\n[RESULT] Extracted {extracted_count} tables")
    
except Exception as e:
    print(f"  [ERROR] {e}")
finally:
    if connection:
        connection.close()
        print("[OK] Connection closed")

# Step 4: Next actions
print("\n[NEXT STEPS]")
print("1. Run: python main.py")
print("2. Click: Test SIMANTAP")
print("3. Click: Pilih File CSV")
print("4. Click: Sinkronisasi")

print("\n" + "=" * 70)
print("SELESAI")
print("=" * 70)
