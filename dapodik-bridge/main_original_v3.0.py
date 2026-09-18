"""
SIMANTAP Dapodik Bridge v3.0
Desktop application for syncing Dapodik data to SIMANTAP server.
Connects to LOCAL Dapodik webservice (localhost:5774) - no external API needed.
"""

import json
import os
import sys
import threading
import time
from datetime import datetime
from pathlib import Path

import requests
import PySimpleGUI as sg

APP_NAME = "SIMANTAP Dapodik Bridge"
APP_VERSION = "3.0.0"
CONFIG_FILE = "config.json"

DEFAULT_CONFIG = {
    "server_url": "http://localhost:8000",
    "api_key": "",
    "dapodik_url": "http://localhost:5774",
    "dapodik_token": "",
    "semester_id": "20261",
    "tahun_ajaran": "2025/2026",
    "nama_semester": "ganjil",
    "auto_sync": False,
    "last_sync": None,
    "modules": {
        "Sekolah": True,
        "PesertaDidik": True,
        "PTK": True,
        "RombonganBelajar": True,
        "Jadwal": True,
    },
}

MODULES = [
    ("Sekolah", "Sekolah"),
    ("PesertaDidik", "PesertaDidik"),
    ("PTK", "PTK"),
    ("RombonganBelajar", "RombonganBelajar"),
    ("Jadwal", "Jadwal"),
]

MODULE_MAP = {
    "Sekolah": "sekolah",
    "PesertaDidik": "peserta-didik",
    "PTK": "ptk",
    "RombonganBelajar": "rombongan-belajar",
    "Jadwal": "jadwal",
}


def load_config():
    config_path = Path(CONFIG_FILE)
    if config_path.exists():
        with open(config_path, "r") as f:
            saved = json.load(f)
            config = DEFAULT_CONFIG.copy()
            config.update(saved)
            if "modules" in saved:
                config["modules"] = DEFAULT_CONFIG["modules"].copy()
                config["modules"].update(saved["modules"])
            return config
    return DEFAULT_CONFIG.copy()


def save_config(config):
    with open(CONFIG_FILE, "w") as f:
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


def test_dapodik_local(dapodik_url, token=""):
    """Test koneksi ke Dapodik webservice lokal, coba beberapa format URL."""
    test_endpoints = [
        ("GET", f"{dapodik_url}/?act=Sekolah&token={token}"),
        ("GET", f"{dapodik_url}/webservice?act=Sekolah&token={token}"),
        ("POST", f"{dapodik_url}/webservice", {"act": "Sekolah", "token": token}),
        ("GET", f"{dapodik_url}/?act=PesertaDidik&token={token}"),
        ("GET", f"{dapodik_url}/?act=Sekolah&token={token}&output=json"),
    ]
    errors = []
    for method, ep, *post_data in test_endpoints:
        try:
            if method == "GET":
                resp = requests.get(ep, timeout=5)
            else:
                resp = requests.post(ep, data=post_data[0] if post_data else {}, timeout=5)
            
            status = resp.status_code
            body = resp.text[:1000] if resp.text else ""
            content_type = resp.headers.get("Content-Type", "")
            path = ep.split(dapodik_url)[1] if dapodik_url in ep else ep
            
            if status == 200:
                try:
                    data = resp.json()
                    if isinstance(data, dict) and (data.get("data") or data.get("rows") or data.get("nama")):
                        return True, f"Dapodik OK — {path}"
                    elif isinstance(data, list) and len(data) > 0:
                        return True, f"Dapodik OK — {path} ({len(data)} data)"
                    else:
                        errors.append(f"{path}: JSON kosong/tidak dikenal")
                except:
                    if "html" in content_type.lower():
                        import re
                        title = re.search(r'<title>(.*?)</title>', body, re.IGNORECASE)
                        title_text = title.group(1) if title else "tanpa judul"
                        errors.append(f"{path}: HTML → <{title_text}>")
                    else:
                        errors.append(f"{path}: {body[:60]}")
            else:
                errors.append(f"{path}: HTTP {status}")
        except requests.exceptions.ConnectionError:
            return False, f"Dapodik tidak aktif di {dapodik_url}"
        except Exception as e:
            errors.append(f"Error: {str(e)[:60]}")
    return False, "Coba: " + "; ".join(errors[:4])


def fetch_dapodik_module(dapodik_url, token, act, semester_id=None):
    """Ambil data dari Dapodik webservice, coba beberapa format URL."""
    endpoints = [
        f"{dapodik_url}/webservice?act={act}&token={token}" + (f"&semester={semester_id}" if semester_id else ""),
        f"{dapodik_url}/?act={act}&token={token}" + (f"&semester={semester_id}" if semester_id else ""),
        f"{dapodik_url}/webservice",
    ]

    for i, ep in enumerate(endpoints):
        try:
            if i < 2:
                resp = requests.get(ep, timeout=30)
            else:
                resp = requests.post(ep, data={"act": act, "token": token, "semester": semester_id or ""}, timeout=30)
            
            if resp.status_code == 200:
                try:
                    data = resp.json()
                    rows = data.get("rows", data) if isinstance(data, dict) else data
                    if isinstance(rows, list):
                        return {"success": True, "data": rows, "total": len(rows)}
                    return {"success": True, "data": [], "total": 0}
                except:
                    continue
            elif resp.status_code == 404:
                continue
        except requests.exceptions.ConnectionError:
            return {"success": False, "message": "Dapodik webservice tidak aktif"}
        except Exception as e:
            continue

    return {"success": False, "message": f"Semua endpoint gagal untuk modul {act}. Token mungkin salah atau format berbeda."}


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

    module_checks = []
    for label, key in MODULES:
        module_checks.append(
            sg.Checkbox(label, default=config["modules"].get(key, True),
                       key=f"-MOD_{key}-", font=("Segoe UI", 9))
        )

    layout = [
        [sg.Text(APP_NAME, font=("Segoe UI", 16, "bold"), text_color="white")],
        [sg.Text(f"v{APP_VERSION} - Koneksi langsung ke Dapodik lokal", font=("Segoe UI", 9), text_color="gray")],
        [sg.HorizontalSeparator()],
        [sg.Text("Server SIMANTAP:", size=(16, 1)),
         sg.Input(config["server_url"], key="-SERVER-", size=(40, 1))],
        [sg.Text("API Key SIMANTAP:", size=(16, 1)),
         sg.Input(config["api_key"], key="-APIKEY-", size=(40, 1), password_char="*")],
        [sg.HorizontalSeparator()],
        [sg.Text("Dapodik URL:", size=(16, 1)),
         sg.Input(config["dapodik_url"], key="-DAPODIK_URL-", size=(40, 1))],
        [sg.Text("Token Dapodik:", size=(16, 1)),
         sg.Input(config["dapodik_token"], key="-DAPODIK_TOKEN-", size=(40, 1), password_char="*")],
        [sg.HorizontalSeparator()],
        [sg.Text("Semester ID:", size=(16, 1)),
         sg.Input(config["semester_id"], key="-SEMESTER_ID-", size=(8, 1)),
         sg.Text("Tahun Ajaran:"),
         sg.Input(config["tahun_ajaran"], key="-TAHUN_AJARAN-", size=(12, 1)),
         sg.Text("Semester:"),
         sg.Combo(["ganjil", "genap"], default_value=config["nama_semester"], key="-NAMA_SEMESTER-", size=(10, 1))],
        [sg.HorizontalSeparator()],
        [sg.Text("Modul Dapodik:", font=("Segoe UI", 10, "bold"))],
        [module_checks],
        [sg.HorizontalSeparator()],
        [
            sg.Button("Test SIMANTAP", size=(16, 1), button_color=("white", "#1F3864")),
            sg.Button("Test Dapodik", size=(16, 1), button_color=("white", "#1F3864")),
            sg.Button("Ambil Semua Data", size=(16, 1), button_color=("white", "#B8860B")),
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

    fetched_data = {}

    def log(msg):
        timestamp = datetime.now().strftime("%H:%M:%S")
        window["-LOG-"].print(f"[{timestamp}] {msg}")
        window["-STATUS-"].update(msg)

    config_path = Path(CONFIG_FILE)

    while True:
        event, values = window.read()

        if event in (sg.WIN_CLOSED, "Keluar"):
            break

        if event == "Simpan Config":
            config["server_url"] = values["-SERVER-"].rstrip("/")
            config["api_key"] = values["-APIKEY-"]
            config["dapodik_url"] = values["-DAPODIK_URL-"].rstrip("/")
            config["dapodik_token"] = values["-DAPODIK_TOKEN-"]
            config["semester_id"] = values["-SEMESTER_ID-"]
            config["tahun_ajaran"] = values["-TAHUN_AJARAN-"]
            config["nama_semester"] = values["-NAMA_SEMESTER-"]
            for _, key in MODULES:
                config["modules"][key] = values[f"-MOD_{key}-"]
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

        if event == "Test Dapodik":
            dapodik_url = values["-DAPODIK_URL-"].rstrip("/")
            token = values["-DAPODIK_TOKEN-"].strip()
            log(f"Testing Dapodik webservice di {dapodik_url}...")
            window.refresh()
            ok, msg = test_dapodik_local(dapodik_url, token)
            if ok:
                log(f"BERHASIL: {msg}")
            else:
                log(f"GAGAL: {msg}")

        if event == "Ambil Semua Data":
            dapodik_url = values["-DAPODIK_URL-"].rstrip("/")
            token = values["-DAPODIK_TOKEN-"].strip()
            semester_id = values["-SEMESTER_ID-"].strip()

            if not token:
                log("ERROR: Token Dapodik wajib diisi.")
                continue

            fetched_data.clear()
            for label, act in MODULES:
                if not values[f"-MOD_{act}-"]:
                    continue
                log(f"Mengambil data {label} dari Dapodik...")
                window.refresh()
                result = fetch_dapodik_module(dapodik_url, token, act, semester_id)
                if result["success"]:
                    fetched_data[act] = result["data"]
                    log(f"  {label}: {result['total']} data ditemukan.")
                    if result["total"] > 0:
                        first = result["data"][0]
                        keys = list(first.keys())[:8]
                        log(f"  Fields: {', '.join(keys)}...")
                else:
                    log(f"  GAGAL {label}: {result['message']}")

            total = sum(len(v) for v in fetched_data.values())
            log(f"Total data diambil: {total} dari {len(fetched_data)} modul.")

        if event == "Sinkronisasi":
            url = values["-SERVER-"].rstrip("/")
            api_key = values["-APIKEY-"]
            semester_id = values["-SEMESTER_ID-"].strip()
            tahun_ajaran = values["-TAHUN_AJARAN-"].strip()
            nama_semester = values["-NAMA_SEMESTER-"]

            if not api_key:
                log("ERROR: API Key SIMANTAP wajib diisi.")
                continue

            if not fetched_data:
                log("ERROR: Belum ada data. Klik 'Ambil Semua Data' terlebih dahulu.")
                continue

            log(f"Menyinkronkan {len(fetched_data)} modul ke {url}...")

            def do_sync():
                results = {}
                for act, data in fetched_data.items():
                    modul_name = MODULE_MAP.get(act, act)
                    log_sync = f"Sync {act} ({len(data)} data)..."
                    window.write_event_value("-SYNC_PROGRESS-", log_sync)
                    result = sync_to_simantap(url, api_key, modul_name, data, semester_id, tahun_ajaran, nama_semester)
                    results[act] = result
                window.write_event_value("-SYNC_DONE-", results)

            threading.Thread(target=do_sync, daemon=True).start()

        if event == "-SYNC_PROGRESS-":
            log(values[event])

        if event == "-SYNC_DONE-":
            results = values[event]
            total_berhasil = 0
            total_gagal = 0
            for act, result in results.items():
                if result.get("success"):
                    b = result.get("berhasil", 0)
                    d = result.get("diperbarui", 0)
                    g = result.get("gagal", 0)
                    total_berhasil += b + d
                    total_gagal += g
                    log(f"  {act}: {b} baru, {d} update, {g} gagal")
                else:
                    log(f"  {act}: GAGAL - {result.get('message', 'Unknown error')}")
                    total_gagal += 1

            log(f"Selesai! Total: {total_berhasil} berhasil, {total_gagal} gagal")
            config["last_sync"] = datetime.now().isoformat()
            save_config(config)

    window.close()


if __name__ == "__main__":
    create_gui()
