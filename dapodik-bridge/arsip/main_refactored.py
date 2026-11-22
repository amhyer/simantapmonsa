"""
SIMANTAP Dapodik Bridge v3.1 - REFACTORED
Desktop application for syncing Dapodik data to SIMANTAP server.
Changed: Direct CSV file import (workaround for Dapodik web app complexity)
"""

import json
import os
import sys
import threading
import time
from datetime import datetime
from pathlib import Path
import csv

import requests
import PySimpleGUI as sg

APP_NAME = "SIMANTAP Dapodik Bridge"
APP_VERSION = "3.1.0 (CSV Import)"
CONFIG_FILE = "config.json"

DEFAULT_CONFIG = {
    "server_url": "http://localhost:8000",
    "api_key": "",
    "semester_id": "20261",
    "tahun_ajaran": "2025/2026",
    "nama_semester": "ganjil",
    "last_sync": None,
    "csv_folder": "./csv_files",
}

CSV_TEMPLATES = {
    "PesertaDidik": ["nama", "nisn", "nis", "jenis_kelamin", "kelas"],
    "Sekolah": ["npsn", "nama_sekolah", "alamat", "provinsi", "kabupaten"],
    "PTK": ["nip", "nama", "jabatan", "bidang_keahlian"],
    "RombonganBelajar": ["nama", "tingkat", "jumlah_siswa"],
}

def load_config():
    config_path = Path(CONFIG_FILE)
    if config_path.exists():
        with open(config_path, "r", encoding="utf-8") as f:
            saved = json.load(f)
            config = DEFAULT_CONFIG.copy()
            config.update(saved)
            return config
    return DEFAULT_CONFIG.copy()

def save_config(config):
    with open(CONFIG_FILE, "w", encoding="utf-8") as f:
        json.dump(config, f, indent=2, ensure_ascii=False)

def test_simantap(url, api_key):
    try:
        resp = requests.post(
            f"{url}/api/dapodik/ping",
            headers={"Content-Type": "application/json"},
            timeout=10,
        )
        if resp.status_code == 200:
            data = resp.json()
            return True, data.get("message", "Server OK")
        return False, f"HTTP {resp.status_code}"
    except requests.exceptions.ConnectionError:
        return False, "Tidak dapat terhubung ke server SIMANTAP"
    except Exception as e:
        return False, str(e)

def read_csv_file(filepath):
    """Baca CSV dan return data sebagai list of dicts"""
    try:
        data = []
        with open(filepath, "r", encoding="utf-8-sig") as f:
            reader = csv.DictReader(f)
            for row in reader:
                if row:
                    data.append(dict(row))
        return {"success": True, "data": data, "total": len(data)}
    except Exception as e:
        return {"success": False, "message": str(e)}

def sync_to_simantap(url, api_key, modul, data, semester_id, tahun_ajaran, nama_semester):
    try:
        payload = {
            "semester_id": semester_id,
            "tahun_ajaran": tahun_ajaran,
            "nama_semester": nama_semester,
            "data": data,
        }
        resp = requests.post(
            f"{url}/api/dapodik/sync/{modul}",
            headers={
                "X-API-Key": api_key,
                "Content-Type": "application/json",
            },
            json=payload,
            timeout=120,
        )
        return resp.json()
    except requests.exceptions.ConnectionError:
        return {"success": False, "message": "Tidak dapat terhubung ke server SIMANTAP"}
    except Exception as e:
        return {"success": False, "message": str(e)}

def create_gui():
    config = load_config()

    sg.theme("DarkBlue13")
    sg.set_options(font=("Segoe UI", 10))

    layout = [
        [sg.Text(APP_NAME, font=("Segoe UI", 16, "bold"), text_color="white")],
        [sg.Text(f"v{APP_VERSION} - Import CSV dari Dapodik", font=("Segoe UI", 9), text_color="gray")],
        [sg.HorizontalSeparator()],
        [sg.Text("Server SIMANTAP:", size=(16, 1)),
         sg.Input(config["server_url"], key="-SERVER-", size=(40, 1))],
        [sg.Text("API Key SIMANTAP:", size=(16, 1)),
         sg.Input(config["api_key"], key="-APIKEY-", size=(40, 1), password_char="*")],
        [sg.HorizontalSeparator()],
        [sg.Text("Semester ID:", size=(16, 1)),
         sg.Input(config["semester_id"], key="-SEMESTER_ID-", size=(8, 1)),
         sg.Text("Tahun Ajaran:"),
         sg.Input(config["tahun_ajaran"], key="-TAHUN_AJARAN-", size=(12, 1)),
         sg.Text("Semester:"),
         sg.Combo(["ganjil", "genap"], default_value=config["nama_semester"], key="-NAMA_SEMESTER-", size=(10, 1))],
        [sg.HorizontalSeparator()],
        [sg.Text("Folder CSV:", font=("Segoe UI", 10, "bold"))],
        [sg.Input(config.get("csv_folder", "./csv_files"), key="-CSV_FOLDER-", size=(55, 1)),
         sg.FolderBrowse(size=(10, 1))],
        [sg.HorizontalSeparator()],
        [
            sg.Button("Test SIMANTAP", size=(16, 1), button_color=("white", "#1F3864")),
            sg.Button("Pilih File CSV", size=(16, 1), button_color=("white", "#1F3864")),
            sg.Button("Sinkronisasi", size=(14, 1), button_color=("white", "green")),
        ],
        [
            sg.Button("Simpan Config", size=(12, 1)),
            sg.Button("Keluar", size=(8, 1)),
        ],
        [sg.HorizontalSeparator()],
        [sg.Text("Log Aktivitas:", font=("Segoe UI", 10, "bold"))],
        [sg.Multiline(size=(70, 18), key="-LOG-", autoscroll=True, disabled=True, font=("Consolas", 9))],
        [sg.StatusBar("Siap", key="-STATUS-", size=(70, 1))],
    ]

    window = sg.Window(
        APP_NAME,
        layout,
        finalize=True,
        resizable=True,
    )

    csv_files = {}  # {modul: filepath}

    def log(msg):
        timestamp = datetime.now().strftime("%H:%M:%S")
        window["-LOG-"].print(f"[{timestamp}] {msg}")
        window["-STATUS-"].update(msg)

    while True:
        event, values = window.read()

        if event in (sg.WIN_CLOSED, "Keluar"):
            break

        if event == "Simpan Config":
            config["server_url"] = values["-SERVER-"].rstrip("/")
            config["api_key"] = values["-APIKEY-"]
            config["semester_id"] = values["-SEMESTER_ID-"]
            config["tahun_ajaran"] = values["-TAHUN_AJARAN-"]
            config["nama_semester"] = values["-NAMA_SEMESTER-"]
            config["csv_folder"] = values["-CSV_FOLDER-"]
            save_config(config)
            log("Konfigurasi tersimpan.")

        if event == "Test SIMANTAP":
            url = values["-SERVER-"].rstrip("/")
            log(f"Testing koneksi ke {url}...")
            window.refresh()
            ok, msg = test_simantap(url, values["-APIKEY-"])
            if ok:
                log(f"BERHASIL: {msg}")
            else:
                log(f"GAGAL: {msg}")

        if event == "Pilih File CSV":
            folder = values["-CSV_FOLDER-"]
            if not os.path.exists(folder):
                log(f"ERROR: Folder tidak ada: {folder}")
                continue
            
            log(f"Mencari file CSV di: {folder}")
            window.refresh()
            
            csv_files.clear()
            csv_count = 0
            
            for file in os.listdir(folder):
                if file.endswith(".csv"):
                    filepath = os.path.join(folder, file)
                    # Coba identify modul dari nama file
                    modul = None
                    for mod_name in CSV_TEMPLATES.keys():
                        if mod_name.lower() in file.lower():
                            modul = mod_name
                            break
                    
                    if modul:
                        csv_files[modul] = filepath
                        csv_count += 1
                        log(f"  Ditemukan: {modul} -> {file}")
                    else:
                        log(f"  Diabaikan: {file} (nama tidak dikenal)")
            
            if csv_count == 0:
                log(f"Tidak ada file CSV yang dikenali di {folder}")
            else:
                log(f"Total: {csv_count} file CSV siap untuk disinkronisasi")

        if event == "Sinkronisasi":
            url = values["-SERVER-"].rstrip("/")
            api_key = values["-APIKEY-"]
            semester_id = values["-SEMESTER_ID-"].strip()
            tahun_ajaran = values["-TAHUN_AJARAN-"].strip()
            nama_semester = values["-NAMA_SEMESTER-"]

            if not api_key:
                log("ERROR: API Key SIMANTAP wajib diisi.")
                continue

            if not csv_files:
                log("ERROR: Belum ada file CSV. Klik 'Pilih File CSV' terlebih dahulu.")
                continue

            log(f"Menyinkronkan {len(csv_files)} modul ke {url}...")

            def do_sync():
                results = {}
                for modul, filepath in csv_files.items():
                    log_sync = f"Membaca CSV: {modul}..."
                    window.write_event_value("-SYNC_PROGRESS-", log_sync)
                    
                    # Baca CSV
                    csv_result = read_csv_file(filepath)
                    if not csv_result["success"]:
                        results[modul] = {"success": False, "message": csv_result["message"]}
                        log_sync = f"GAGAL membaca {modul}: {csv_result['message']}"
                        window.write_event_value("-SYNC_PROGRESS-", log_sync)
                        continue
                    
                    data = csv_result["data"]
                    log_sync = f"Sync {modul} ({len(data)} data)..."
                    window.write_event_value("-SYNC_PROGRESS-", log_sync)
                    
                    modul_slug = modul.lower().replace("_", "-")
                    result = sync_to_simantap(url, api_key, modul_slug, data, semester_id, tahun_ajaran, nama_semester)
                    results[modul] = result
                
                window.write_event_value("-SYNC_DONE-", results)

            threading.Thread(target=do_sync, daemon=True).start()

        if event == "-SYNC_PROGRESS-":
            log(values[event])

        if event == "-SYNC_DONE-":
            results = values[event]
            total_berhasil = 0
            total_gagal = 0
            for modul, result in results.items():
                if result.get("success"):
                    b = result.get("berhasil", 0)
                    d = result.get("diperbarui", 0)
                    g = result.get("gagal", 0)
                    total_berhasil += b + d
                    total_gagal += g
                    log(f"  {modul}: {b} baru, {d} update, {g} gagal")
                else:
                    log(f"  {modul}: GAGAL - {result.get('message', 'Unknown error')}")
                    total_gagal += 1

            log(f"Selesai! Total: {total_berhasil} berhasil, {total_gagal} gagal")
            config["last_sync"] = datetime.now().isoformat()
            save_config(config)

    window.close()

if __name__ == "__main__":
    create_gui()
