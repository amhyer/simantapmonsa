@echo off
REM ============================================
REM  SIMANTAP Dapodik Bridge - Build Script
REM  Run this to create the EXE file
REM ============================================

echo [1/3] Installing dependencies...
pip install -r requirements.txt

echo [2/3] Building EXE with PyInstaller...
pyinstaller --onefile --windowed --name "SIMANTAP-Bridge" ^
    --add-data "config.json;." ^
    --clean ^
    main.py

echo [3/3] Build complete!
echo EXE file: dist\SIMANTAP-Bridge.exe
pause
