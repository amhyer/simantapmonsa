# -*- coding: utf-8 -*-
"""
SIMANTAP Dapodik Bridge - Auto-Pull Script
Menggunakan Selenium untuk auto-login & download CSV dari Dapodik
"""

import os
import time
import csv
import json
from datetime import datetime
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options as ChromeOptions
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.chrome.service import Service

# Konfigurasi
DAPODIK_URL = "http://localhost:5774"
USERNAME = "sdnungmongisidi1@gmail.com"
PASSWORD = "Monsajaya12*#"
CSV_FOLDER = Path("csv_files")
DOWNLOAD_FOLDER = Path.home() / "Downloads"

print("=" * 70)
print("SIMANTAP Dapodik Bridge - Auto-Pull Script v1.0")
print("=" * 70)

# Step 1: Setup Chrome Options
print("\n[STEP 1] Setup Chrome WebDriver...")
chrome_options = ChromeOptions()

# Set download folder
prefs = {
    "download.default_directory": str(DOWNLOAD_FOLDER),
    "download.prompt_for_download": False,
    "profile.default_content_settings.popups": 0,
}
chrome_options.add_experimental_option("prefs", prefs)

# Uncomment untuk headless (tanpa buka window)
# chrome_options.add_argument("--headless")
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--disable-dev-shm-usage")

try:
    service = Service(ChromeDriverManager().install())
    driver = webdriver.Chrome(service=service, options=chrome_options)
    print("✓ ChromeDriver loaded successfully")
except Exception as e:
    print(f"✗ Error loading ChromeDriver: {e}")
    exit(1)

# Step 2: Login ke Dapodik
print("\n[STEP 2] Logging in to Dapodik...")
try:
    driver.get(DAPODIK_URL)
    print(f"✓ Opened {DAPODIK_URL}")
    
    # Wait untuk halaman load
    WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.NAME, "username"))
    )
    print("✓ Login form loaded")
    
    # Input username
    username_field = driver.find_element(By.NAME, "username")
    username_field.send_keys(USERNAME)
    print(f"✓ Username entered: {USERNAME}")
    
    # Input password
    password_field = driver.find_element(By.NAME, "password")
    password_field.send_keys(PASSWORD)
    print("✓ Password entered")
    
    # Klik tombol login
    login_button = driver.find_element(By.XPATH, "//button[contains(text(), 'Masuk')]")
    login_button.click()
    print("✓ Login button clicked")
    
    # Wait untuk dashboard load (biasanya 3-5 detik)
    time.sleep(5)
    print("✓ Waiting for dashboard...")
    
    # Check apakah login berhasil
    current_url = driver.current_url
    if "login" not in current_url.lower() and "roleperan" not in current_url:
        print(f"✓ Login successful! Current URL: {current_url}")
    else:
        # Bisa jadi masih di halaman login tapi ada redirect
        print(f"⚠ Current URL: {current_url}")
    
except Exception as e:
    print(f"✗ Login failed: {e}")
    print(f"Current URL: {driver.current_url}")
    print(f"Page title: {driver.title}")
    driver.quit()
    exit(1)

# Step 3: Navigate ke export menu
print("\n[STEP 3] Navigating to export menu...")
try:
    # Cari menu export atau data management
    # Coba berbagai opsi
    
    # Opsi 1: Cari link yang mengandung "export"
    time.sleep(2)
    
    # Buka inspect untuk lihat struktur
    print("✓ Checking page structure...")
    page_source = driver.page_source
    
    if "export" in page_source.lower():
        print("✓ Found 'export' in page")
    
    if "manajemen" in page_source.lower():
        print("✓ Found 'manajemen' in page")
    
    # Print semua link yang ada
    links = driver.find_elements(By.TAG_NAME, "a")
    print(f"\n✓ Found {len(links)} links on page")
    
    export_links = []
    for link in links:
        text = link.text.lower()
        href = link.get_attribute("href") or ""
        
        if any(keyword in text for keyword in ["export", "download", "data", "manajemen"]):
            print(f"  - {link.text} ({href})")
            export_links.append(link)
    
    if export_links:
        print(f"\n✓ Found {len(export_links)} potential export links")
        # Klik yang pertama
        export_links[0].click()
        print("✓ Clicked on export link")
        time.sleep(3)
    else:
        print("⚠ No export links found - checking page structure...")
        print(f"Current URL: {driver.current_url}")
        print(f"Page title: {driver.title}")
    
except Exception as e:
    print(f"✗ Navigation failed: {e}")
    print(f"Current URL: {driver.current_url}")

# Step 4: Download CSV
print("\n[STEP 4] Downloading CSV files...")
try:
    # Tunggu untuk tombol download
    time.sleep(2)
    
    # Cari tombol download atau export
    buttons = driver.find_elements(By.TAG_NAME, "button")
    print(f"✓ Found {len(buttons)} buttons")
    
    csv_buttons = []
    for button in buttons:
        text = button.text.lower()
        if any(keyword in text for keyword in ["download", "export", "csv", "excel"]):
            print(f"  - Button: {button.text}")
            csv_buttons.append(button)
    
    if csv_buttons:
        print(f"✓ Found {len(csv_buttons)} download buttons")
    else:
        print("⚠ No download buttons found")
    
except Exception as e:
    print(f"✗ Download check failed: {e}")

# Step 5: Save screenshot untuk debugging
print("\n[STEP 5] Saving debug information...")
try:
    screenshot_path = "dapodik_screenshot.png"
    driver.save_screenshot(screenshot_path)
    print(f"✓ Screenshot saved: {screenshot_path}")
    
    # Save page source
    with open("dapodik_page_source.html", "w", encoding="utf-8") as f:
        f.write(driver.page_source)
    print("✓ Page source saved: dapodik_page_source.html")
    
except Exception as e:
    print(f"✗ Debug save failed: {e}")

# Keep browser open untuk manual inspection
print("\n[INFO] Browser tetap terbuka untuk inspeksi manual")
print("Silakan lakukan export manual jika diperlukan")
print("Press Enter untuk close browser...")
input()

driver.quit()
print("\n✓ Browser closed")

print("\n" + "=" * 70)
print("SELESAI")
print("=" * 70)
