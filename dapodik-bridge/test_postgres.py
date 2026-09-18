# -*- coding: utf-8 -*-
"""
Test koneksi langsung ke PostgreSQL Dapodik
"""
import psycopg2
import json

print("=" * 70)
print("TESTING DIRECT POSTGRESQL CONNECTION")
print("=" * 70)

# Dari install.ini: DbPort=54532
# Database name: pendataan
# Default postgres user

configs = [
    {"host": "localhost", "port": 54532, "database": "pendataan", "user": "postgres", "password": ""},
    {"host": "localhost", "port": 54532, "database": "pendataan", "user": "postgres", "password": "postgres"},
    {"host": "127.0.0.1", "port": 54532, "database": "pendataan", "user": "postgres", "password": ""},
]

for cfg in configs:
    print("\n[TRY] Connecting to {}@{}:{}...".format(cfg["user"], cfg["host"], cfg["port"]))
    try:
        conn = psycopg2.connect(
            host=cfg["host"],
            port=cfg["port"],
            database=cfg["database"],
            user=cfg["user"],
            password=cfg["password"]
        )
        print("[SUCCESS] Connected!")
        
        # Test query
        cursor = conn.cursor()
        
        # Cari table yang ada
        cursor.execute("""
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            LIMIT 10
        """)
        tables = cursor.fetchall()
        print("\n[TABLES] Found {} tables:".format(len(tables)))
        for table in tables:
            print("  - {}".format(table[0]))
        
        # Cek table sekolah
        cursor.execute("""
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name IN ('sekolah', 'peserta_didik', 'ptk', 'rombongan_belajar')
        """)
        found = cursor.fetchone()[0]
        print("\n[DATA TABLES] Found {} standard tables".format(found))
        
        # Coba ambil data sekolah
        cursor.execute("SELECT COUNT(*) FROM sekolah")
        count = cursor.fetchone()[0]
        print("[SEKOLAH] Total: {} records".format(count))
        
        if count > 0:
            cursor.execute("SELECT * FROM sekolah LIMIT 1")
            cols = [desc[0] for desc in cursor.description]
            row = cursor.fetchone()
            print("[SAMPLE] Columns: {}".format(", ".join(cols[:5])))
            print("[SAMPLE] First row: {}".format(row[:3]))
        
        cursor.close()
        conn.close()
        break
        
    except psycopg2.OperationalError as e:
        print("[FAIL] {}".format(str(e)[:80]))
    except Exception as e:
        print("[ERROR] {} - {}".format(type(e).__name__, str(e)[:80]))

print("\n" + "=" * 70)
print("DONE")
print("=" * 70)
