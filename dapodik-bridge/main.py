"""
SIMANTAP Dapodik Bridge v4.0
Desktop app: hubungkan Dapodik lokal (localhost:5774) ke server SIMANTAP (internet/hosting).
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
APP_VERSION = "4.0.0"
CONFIG_FILE = "config.json"

DEFAULT_CONFIG = {
    "server_url": "http://localhost:8000",
    "api_key": "",
    "dapodik_host": "localhost",
    "dapodik_port": 5774,
    "dapodik_protocol": "http",
    "dapodik_token": "",
    "npsn": "",
    "semester_id": "20261",
    "tahun_ajaran": "2026/2027",
    "nama_semester": "ganjil",
    "last_sync": None,
}


def load_config():
    p = Path(CONFIG_FILE)
    if p.exists():
        with open(p, "r", encoding="utf-8") as f:
            saved = json.load(f)
            cfg = DEFAULT_CONFIG.copy()
            cfg.update(saved)
            return cfg
    return DEFAULT_CONFIG.copy()


def save_config(cfg):
    with open(CONFIG_FILE, "w", encoding="utf-8") as f:
        json.dump(cfg, f, indent=2, ensure_ascii=False)


# ─── DAPODIK WEB SERVICE ────────────────────────────────────────

def dapodik_base(protocol, host, port):
    url = f"{protocol}://{host}"
    if (protocol == "https" and port != 443) or (protocol == "http" and port != 80):
        url += f":{port}"
    return url + "/WebService"


def dapodik_request(protocol, host, port, token, npsn, endpoint, params=None):
    """Request ke Dapodik WebService. Return (success, data_or_error)."""
    base = dapodik_base(protocol, host, port)
    url = f"{base}/{endpoint}"
    all_params = {"npsn": npsn}
    if params:
        all_params.update(params)

    for attempt in range(1, 4):
        try:
            resp = requests.get(
                url,
                headers={
                    "Authorization": f"Bearer {token}",
                    "Accept": "application/json",
                },
                params=all_params,
                timeout=30,
            )
            if resp.status_code == 200:
                try:
                    data = resp.json()
                    return True, data
                except Exception:
                    if attempt < 3:
                        time.sleep(0.5 * attempt)
                        continue
                    return False, f"Response bukan JSON dari {endpoint}"
            elif resp.status_code >= 500:
                if attempt < 3:
                    time.sleep(0.5 * attempt)
                    continue
                return False, f"Server error HTTP {resp.status_code}"
            else:
                body = resp.text[:200]
                return False, f"HTTP {resp.status_code}: {body}"
        except requests.exceptions.ConnectionError:
            if attempt < 3:
                time.sleep(1)
                continue
            return False, f"Gagal koneksi ke {url}"
        except requests.exceptions.Timeout:
            if attempt < 3:
                continue
            return False, f"Timeout koneksi ke {endpoint}"
        except Exception as e:
            return False, str(e)

    return False, "Semua attempt gagal"


def dapodik_fetch_all(protocol, host, port, token, npsn):
    """Ambil semua data dari Dapodik (4 endpoint, sequential)."""
    results = {}

    for endpoint in ["getSekolah", "getPesertaDidik", "getGtk", "getRombonganBelajar"]:
        ok, data = dapodik_request(protocol, host, port, token, npsn, endpoint)
        if ok:
            rows = data.get("rows", data.get("data", []))
            if isinstance(rows, dict):
                rows = [rows]
            results[endpoint] = rows
        else:
            results[endpoint] = {"error": data}

    return results


# ─── SIMANTAP SERVER ─────────────────────────────────────────────

def simantap_test(url):
    try:
        resp = requests.post(f"{url}/api/dapodik/ping", timeout=10)
        if resp.status_code == 200:
            return True, resp.json().get("message", "OK")
        return False, f"HTTP {resp.status_code}"
    except requests.exceptions.ConnectionError:
        return False, "Tidak dapat terhubung ke server SIMANTAP"
    except Exception as e:
        return False, str(e)


def simantap_sync_module(url, api_key, modul, data, semester_id, tahun_ajaran, nama_semester):
    try:
        resp = requests.post(
            f"{url}/api/dapodik/sync/{modul}",
            headers={"X-API-Key": api_key, "Content-Type": "application/json"},
            json={
                "semester_id": semester_id,
                "tahun_ajaran": tahun_ajaran,
                "nama_semester": nama_semester,
                "data": data,
            },
            timeout=180,
        )
        return resp.json()
    except requests.exceptions.ConnectionError:
        return {"success": False, "message": "Tidak dapat terhubung ke server SIMANTAP"}
    except Exception as e:
        return {"success": False, "message": str(e)}


# ─── GUI ─────────────────────────────────────────────────────────

def create_gui():
    cfg = load_config()

    sg.theme("DarkBlue13")
    sg.set_options(font=("Segoe UI", 10))

    tab_dapodik = [
        [sg.Text("Host Dapodik:", size=(14, 1)),
         sg.Input(cfg["dapodik_host"], key="-DHOST-", size=(20, 1)),
         sg.Text("Port:"),
         sg.Input(str(cfg["dapodik_port"]), key="-DPORT-", size=(6, 1)),
         sg.Combo(["http", "https"], default_value=cfg["dapodik_protocol"], key="-DPROTO-", size=(6, 1))],
        [sg.Text("Token Dapodik:", size=(14, 1)),
         sg.Input(cfg["dapodik_token"], key="-DTOKEN-", size=(40, 1), password_char="*")],
        [sg.Text("NPSN:", size=(14, 1)),
         sg.Input(cfg["npsn"], key="-NPSN-", size=(12, 1))],
    ]

    tab_server = [
        [sg.Text("URL Server:", size=(14, 1)),
         sg.Input(cfg["server_url"], key="-SERVER-", size=(40, 1))],
        [sg.Text("API Key:", size=(14, 1)),
         sg.Input(cfg["api_key"], key="-APIKEY-", size=(40, 1), password_char="*")],
    ]

    tab_semester = [
        [sg.Text("Semester ID:", size=(14, 1)),
         sg.Input(cfg["semester_id"], key="-SEMID-", size=(8, 1)),
         sg.Text("Tahun Ajaran:"),
         sg.Input(cfg["tahun_ajaran"], key="-TA-", size=(12, 1)),
         sg.Text("Semester:"),
         sg.Combo(["ganjil", "genap"], default_value=cfg["nama_semester"], key="-NSEM-", size=(10, 1))],
    ]

    layout = [
        [sg.Text(APP_NAME, font=("Segoe UI", 16, "bold"), text_color="white"),
         sg.Text(f"v{APP_VERSION}", font=("Segoe UI", 9), text_color="gray", justification="right", expand_x=True)],
        [sg.HorizontalSeparator()],
        [sg.TabGroup([
            [sg.Tab("Dapodik Lokal", tab_dapodik)],
            [sg.Tab("Server SIMANTAP", tab_server)],
            [sg.Tab("Semester", tab_semester)],
        ], key="-TABS-")],
        [sg.HorizontalSeparator()],
        [
            sg.Button("Test Dapodik", size=(14, 1), button_color=("white", "#1F3864")),
            sg.Button("Test SIMANTAP", size=(14, 1), button_color=("white", "#1F3864")),
            sg.Button("Tarik & Sinkron", size=(16, 1), button_color=("white", "green")),
        ],
        [
            sg.Button("Simpan Config", size=(12, 1)),
            sg.Button("Keluar", size=(8, 1)),
        ],
        [sg.HorizontalSeparator()],
        [sg.Text("Log Aktivitas:", font=("Segoe UI", 10, "bold"))],
        [sg.Multiline(size=(70, 20), key="-LOG-", autoscroll=True, disabled=True, font=("Consolas", 9))],
        [sg.ProgressBar(100, orientation="h", size=(50, 20), key="-PROGRESS-", bar_color=("#1F3864", "#E4E7EC"))],
        [sg.StatusBar("Siap", key="-STATUS-", size=(70, 1))],
    ]

    window = sg.Window(APP_NAME, layout, finalize=True, resizable=True)

    def log(msg):
        ts = datetime.now().strftime("%H:%M:%S")
        window["-LOG-"].print(f"[{ts}] {msg}")
        window["-STATUS-"].update(msg)
        window.refresh()

    def get_cfg():
        return {
            "server_url": values["-SERVER-"].rstrip("/"),
            "api_key": values["-APIKEY-"],
            "dapodik_host": values["-DHOST-"],
            "dapodik_port": int(values["-DPORT-"]),
            "dapodik_protocol": values["-DPROTO-"],
            "dapodik_token": values["-DTOKEN-"],
            "npsn": values["-NPSN-"],
            "semester_id": values["-SEMID-"].strip(),
            "tahun_ajaran": values["-TA-"].strip(),
            "nama_semester": values["-NSEM-"],
        }

    while True:
        event, values = window.read()

        if event in (sg.WIN_CLOSED, "Keluar"):
            break

        if event == "Simpan Config":
            save_config(get_cfg())
            log("Konfigurasi tersimpan.")

        if event == "Test Dapodik":
            c = get_cfg()
            log(f"Test koneksi Dapodik: {c['dapodik_protocol']}://{c['dapodik_host']}:{c['dapodik_port']}...")
            window.refresh()
            ok, data = dapodik_request(
                c["dapodik_protocol"], c["dapodik_host"], c["dapodik_port"],
                c["dapodik_token"], c["npsn"], "getSekolah"
            )
            if ok:
                rows = data.get("rows", data.get("data", []))
                if isinstance(rows, dict):
                    rows = [rows]
                nama = rows[0].get("nama", rows[0].get("nama_sekolah", "?")) if rows else "?"
                log(f"BERHASIL: {nama}")
            else:
                log(f"GAGAL: {data}")

        if event == "Test SIMANTAP":
            c = get_cfg()
            log(f"Test koneksi SIMANTAP: {c['server_url']}...")
            window.refresh()
            ok, msg = simantap_test(c["server_url"])
            log(f"{'BERHASIL' if ok else 'GAGAL'}: {msg}")

        if event == "Tarik & Sinkron":
            c = get_cfg()

            if not c["dapodik_token"]:
                log("ERROR: Token Dapodik wajib diisi.")
                continue
            if not c["api_key"]:
                log("ERROR: API Key SIMANTAP wajib diisi.")
                continue
            if not c["npsn"]:
                log("ERROR: NPSN wajib diisi.")
                continue

            def do_sync():
                try:
                    log_sync = "Mengambil data dari Dapodik..."
                    window.write_event_value("-PROGRESS-", (10, log_sync))

                    raw = dapodik_fetch_all(
                        c["dapodik_protocol"], c["dapodik_host"], c["dapodik_port"],
                        c["dapodik_token"], c["npsn"]
                    )

                    for ep in ["getSekolah", "getPesertaDidik", "getGtk", "getRombonganBelajar"]:
                        d = raw.get(ep, {})
                        if isinstance(d, dict) and "error" in d:
                            log_sync = f"GAGAL {ep}: {d['error']}"
                            window.write_event_value("-SYNC_LOG-", log_sync)
                        else:
                            log_sync = f"{ep}: {len(d)} data"
                            window.write_event_value("-SYNC_LOG-", log_sync)

                    modules_map = {
                        "getSekolah": "sekolah",
                        "getPesertaDidik": "peserta-didik",
                        "getGtk": "ptk",
                        "getRombonganBelajar": "rombongan-belajar",
                    }

                    total_steps = len(modules_map)
                    step = 0

                    for ep, modul in modules_map.items():
                        step += 1
                        data = raw.get(ep, [])
                        if isinstance(data, dict) and "error" in data:
                            log_sync = f"Lewati {modul} (error fetch)"
                            window.write_event_value("-SYNC_LOG-", log_sync)
                            continue

                        if not data:
                            log_sync = f"Lewati {modul} (kosong)"
                            window.write_event_value("-SYNC_LOG-", log_sync)
                            continue

                        log_sync = f"Kirim {modul} ({len(data)} data) ke SIMANTAP..."
                        window.write_event_value("-SYNC_LOG-", log_sync)
                        pct = 30 + int(60 * step / total_steps)
                        window.write_event_value("-PROGRESS-", (pct, log_sync))

                        result = simantap_sync_module(
                            c["server_url"], c["api_key"], modul, data,
                            c["semester_id"], c["tahun_ajaran"], c["nama_semester"]
                        )

                        if result.get("success"):
                            b = result.get("berhasil", 0)
                            d = result.get("diperbarui", 0)
                            g = result.get("gagal", 0)
                            log_sync = f"  {modul}: {b} baru, {d} update, {g} gagal"
                        else:
                            log_sync = f"  {modul}: GAGAL - {result.get('message', '?')}"
                        window.write_event_value("-SYNC_LOG-", log_sync)

                    window.write_event_value("-PROGRESS-", (100, "Selesai!"))
                    window.write_event_value("-SYNC_DONE-", True)

                except Exception as e:
                    window.write_event_value("-SYNC_LOG-", f"ERROR: {e}")
                    window.write_event_value("-SYNC_DONE-", False)

            threading.Thread(target=do_sync, daemon=True).start()

        if event == "-SYNC_LOG-":
            log(values[event])

        if event == "-PROGRESS-":
            pct, msg = values[event]
            window["-PROGRESS-"].update(pct)
            window["-STATUS-"].update(msg)

        if event == "-SYNC_DONE-":
            ok = values[event]
            if ok:
                log("Sinkronisasi selesai!")
                cfg_update = get_cfg()
                cfg_update["last_sync"] = datetime.now().isoformat()
                save_config(cfg_update)
            else:
                log("Sinkronisasi gagal.")
            window["-PROGRESS-"].update(0)

    window.close()


if __name__ == "__main__":
    create_gui()
