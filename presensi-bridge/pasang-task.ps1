# Jalankan Task Scheduler untuk SIMANTAP Presensi Bridge.
# Terjadwal: sekali saat PC login, lalu berulang setiap 5 menit.

$action = New-ScheduledTaskAction -Execute 'C:\Windows\SysWOW64\WindowsPowerShell\v1.0\powershell.exe' -Argument '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "C:\Program Files\SIMANTAP\presensi-bridge\presensi-bridge.ps1"'
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date.AddHours(1)
$trigger.RepetitionInterval = New-TimeSpan -Minutes 5
$trigger.RepetitionDuration = ([TimeSpan]::MaxValue)

Register-ScheduledTask -TaskName 'SIMANTAP Presensi Bridge' -Action $action -Trigger $trigger -Settings (New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries) -RunLevel Highest -Force
Write-Host 'Task Scheduler "SIMANTAP Presensi Bridge" dibuat.'
Write-Host 'Cek status dengan: Get-ScheduledTask -TaskName "SIMANTAP Presensi Bridge" | Format-List'
