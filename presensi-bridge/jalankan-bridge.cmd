@echo off
REM SIMANTAP Presensi Bridge - jalankan sekali (untuk uji manual).
REM Untuk otomatis, gunakan Task Scheduler (pasang-task.ps1).
set "PS32=C:\Windows\SysWOW64\WindowsPowerShell\v1.0\powershell.exe"
set "SCRIPT=C:\Program Files\SIMANTAP\presensi-bridge\presensi-bridge.ps1"
set "CONFIG=C:\Program Files\SIMANTAP\presensi-bridge\config.json"

"%PS32%" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "%SCRIPT%" -ConfigPath "%CONFIG%"
exit /b %ERRORLEVEL%
