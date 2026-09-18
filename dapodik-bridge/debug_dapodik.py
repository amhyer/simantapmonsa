# -*- coding: utf-8 -*-
"""
Debug script untuk test penarikan data dari Dapodik lokal.
Jalankan: python debug_dapodik.py [token]
Contoh: python debug_dapodik.py abc123def456
"""

import requests
import json
import sys
from pathlib import Path

# Konfigurasi default (sama seperti di main.py)
DAPODIK_URL = "http://localhost:5774"
SEMESTER_ID = "20261"

MODULES = [
    ("Sekolah", "Sekolah"),
    ("PesertaDidik", "PesertaDidik"),
    ("PTK", "PTK"),
    ("RombonganBelajar", "RombonganBelajar"),
    ("Jadwal", "Jadwal"),
]

def log(msg):
    print("[LOG] " + msg)

def test_dapodik_local(dapodik_url, token=""):
    """Test koneksi ke Dapodik webservice lokal, coba beberapa format URL."""
    test_endpoints = [
        ("GET", f"{dapodik_url}/?act=Sekolah&token={token}"),
        ("GET", f"{dapodik_url}/webservice?act=Sekolah&token={token}"),
        ("GET", f"{dapodik_url}/?act=Sekolah&token={token}&output=json"),
    ]
    errors = []
    for method, ep, *post_data in test_endpoints:
        try:
            log("")
            log("--- Testing: {} {}".format(method, ep))
            if method == "GET":
                resp = requests.get(ep, timeout=5)
            else:
                resp = requests.post(ep, data=post_data[0] if post_data else {}, timeout=5)
            
            status = resp.status_code
            body = resp.text[:500] if resp.text else ""
            content_type = resp.headers.get("Content-Type", "")
            path = ep.split(dapodik_url)[1] if dapodik_url in ep else ep
            
            log("Status: {}".format(status))
            log("Content-Type: {}".format(content_type))
            log("Body Preview: {}".format(body))
            
            if status == 200:
                try:
                    data = resp.json()
                    log("OK: JSON Valid. Type: {}".format(type(data).__name__))
                    log("JSON Content: {}".format(str(data)[:200]))
                    if isinstance(data, dict) and (data.get("data") or data.get("rows") or data.get("nama")):
                        return True, "Dapodik OK - {}".format(path)
                    elif isinstance(data, list) and len(data) > 0:
                        return True, "Dapodik OK - {} ({} data)".format(path, len(data))
                    else:
                        errors.append("{}: JSON kosong/tidak dikenal -> {}".format(path, str(data)[:100]))
                except json.JSONDecodeError as je:
                    if "html" in content_type.lower():
                        if "login" in body.lower():
                            errors.append("{}: HTML LOGIN PAGE (token salah/kosong)".format(path))
                        else:
                            errors.append("{}: HTML (bukan API response)".format(path))
                    else:
                        errors.append("{}: JSON Parse Error -> {}".format(path, str(je)[:60]))
            else:
                errors.append("{}: HTTP {}".format(path, status))
        except requests.exceptions.ConnectionError as ce:
            log("FAIL: Connection Error: {}".format(ce))
            return False, "Dapodik tidak aktif di {}".format(dapodik_url)
        except Exception as e:
            log("FAIL: Exception {}: {}".format(type(e).__name__, e))
            errors.append("Error: {}".format(str(e)[:60]))
    return False, "Coba: " + "; ".join(errors[:4])


def fetch_dapodik_module(dapodik_url, token, act, semester_id=None):
    """Ambil data dari Dapodik webservice, coba beberapa format URL."""
    endpoints = [
        "{}/webservice?act={}&token={}".format(dapodik_url, act, token) + ("&semester={}".format(semester_id) if semester_id else ""),
        "{}/?act={}&token={}".format(dapodik_url, act, token) + ("&semester={}".format(semester_id) if semester_id else ""),
        "{}/webservice".format(dapodik_url),
    ]

    for i, ep in enumerate(endpoints):
        log("")
        log("--- Attempt {}: {}".format(i+1, ep))
        try:
            if i < 2:
                resp = requests.get(ep, timeout=30)
            else:
                resp = requests.post(ep, data={"act": act, "token": token, "semester": semester_id or ""}, timeout=30)
            
            log("Status: {}".format(resp.status_code))
            log("Content-Type: {}".format(resp.headers.get("Content-Type", "")))
            log("Body Preview: {}".format(resp.text[:300]))
            
            if resp.status_code == 200:
                try:
                    data = resp.json()
                    log("OK: JSON Valid. Type: {}".format(type(data).__name__))
                    rows = data.get("rows", data) if isinstance(data, dict) else data
                    if isinstance(rows, list):
                        log("OK: Found {} rows".format(len(rows)))
                        return {"success": True, "data": rows, "total": len(rows)}
                    else:
                        log("FAIL: Not a list: {}".format(type(rows).__name__))
                        return {"success": True, "data": [], "total": 0}
                except json.JSONDecodeError as je:
                    log("FAIL: JSON Parse Error: {}".format(je))
                    continue
            elif resp.status_code == 404:
                log("FAIL: Endpoint not found (404)")
                continue
        except requests.exceptions.ConnectionError as ce:
            log("FAIL: Connection Error: {}".format(ce))
            return {"success": False, "message": "Dapodik webservice tidak aktif"}
        except Exception as e:
            log("FAIL: Exception {}: {}".format(type(e).__name__, e))
            continue

    return {"success": False, "message": "Semua endpoint gagal untuk modul {}. Token mungkin salah atau format berbeda.".format(act)}


# ============= MAIN DEBUG =============
if __name__ == "__main__":
    print("=" * 70)
    print("SIMANTAP DAPODIK BRIDGE - DEBUG SCRIPT")
    print("=" * 70)
    
    # Ambil token dari argument atau interaktif
    DAPODIK_TOKEN = sys.argv[1] if len(sys.argv) > 1 else ""
    
    if not DAPODIK_TOKEN:
        print("[WARNING] TOKEN DAPODIK KOSONG!")
        print("")
        print("Cara pakai:")
        print("  python debug_dapodik.py [TOKEN]")
        print("")
        print("Contoh:")
        print("  python debug_dapodik.py abc123def456")
        print("")
        print("Atau masukkan token sekarang:")
        DAPODIK_TOKEN = input("Token: ").strip()
    
    if not DAPODIK_TOKEN:
        print("[ERROR] Token harus diisi!")
        sys.exit(1)
    
    print("")
    print("[INFO] Konfigurasi:")
    print("   Dapodik URL: {}".format(DAPODIK_URL))
    print("   Token: [ADA - {}***]".format(DAPODIK_TOKEN[:4]))
    print("   Semester ID: {}".format(SEMESTER_ID))
    
    # 1. Test koneksi Dapodik
    print("")
    print("[STEP 1] TEST KONEKSI DAPODIK WEBSERVICE")
    print("-" * 70)
    ok, msg = test_dapodik_local(DAPODIK_URL, DAPODIK_TOKEN)
    status_str = "OK" if ok else "FAIL"
    print("Result: {}: {}".format(status_str, msg))
    
    if not ok:
        print("")
        print("[SARAN]:")
        print("   - Pastikan Dapodik sudah running di localhost:5774")
        print("   - Cek apakah URL/port Dapodik berbeda")
        print("   - Cek Token Dapodik di admin panel (harus benar)")
        print("   - Jika masih login page, berarti TOKEN SALAH")
        print("")
        print("[STOP] Berhenti di sini karena koneksi gagal.")
        sys.exit(1)
    else:
        # 2. Ambil data semua modul
        print("")
        print("[STEP 2] AMBIL DATA DARI SEMUA MODUL")
        print("-" * 70)
        fetched_data = {}
        for label, act in MODULES:
            log("")
            log("[FETCH] Mengambil data {}...".format(label))
            result = fetch_dapodik_module(DAPODIK_URL, DAPODIK_TOKEN, act, SEMESTER_ID)
            if result["success"]:
                fetched_data[act] = result["data"]
                log("OK: {}: {} data ditemukan.".format(label, result["total"]))
                if result["total"] > 0:
                    first = result["data"][0]
                    keys = list(first.keys())[:8]
                    log("  Sample fields: {}".format(", ".join(keys)))
                    log("  Sample data: {}".format(str(first)[:150]))
            else:
                log("FAIL: {}: {}".format(label, result["message"]))
        
        total = sum(len(v) for v in fetched_data.values())
        print("")
        print("[RESULT] HASIL AKHIR:")
        print("   Total data: {} dari {} modul".format(total, len(fetched_data)))
        for modul, data in fetched_data.items():
            print("   - {}: {} data".format(modul, len(data)))
        
        # 3. Save hasil ke file untuk inspeksi
        if fetched_data:
            output_file = "debug_output.json"
            with open(output_file, "w", encoding="utf-8") as f:
                json.dump(fetched_data, f, indent=2, ensure_ascii=False)
            print("")
            print("[SAVED] Output disimpan ke: {}".format(output_file))
        
        print("")
        print("[SUCCESS] Test selesai!")
