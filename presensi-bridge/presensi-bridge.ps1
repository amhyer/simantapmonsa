param(
    [string]$ConfigPath = ''
)

$ErrorActionPreference = 'Stop'

function Write-Log($m) {
    $msg = "[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $m
    Add-Content -Path $script:log_file -Value $msg
    Write-Host $msg
}

function Get-Config {
    if ([string]::IsNullOrWhiteSpace($script:config_path)) {
        throw 'File konfigurasi config.json tidak ditemukan.'
    }
    if (-not (Test-Path -LiteralPath $script:config_path)) {
        throw "File konfigurasi tidak ditemukan: $($script:config_path)"
    }

    $raw = Get-Content -Raw -Path $script:config_path | ConvertFrom-Json
    if ([string]::IsNullOrWhiteSpace([string]$raw.server_url) -or [string]::IsNullOrWhiteSpace([string]$raw.api_key) -or [string]::IsNullOrWhiteSpace([string]$raw.db_path)) {
        throw 'Isi server_url, api_key, dan db_path pada config.json.'
    }

    $intervalMenit = 300
    if ($raw.interval_menit) { $intervalMenit = [int]$raw.interval_menit }
    $maksHari = 30
    if ($raw.maks_hari) { $maksHari = [int]$raw.maks_hari }

    return @{
        server_url = ([string]$raw.server_url).TrimEnd('/')
        api_key    = [string]$raw.api_key
        db_path    = [string]$raw.db_path
        log_file   = [string]$raw.log_file
        interval_menit = $intervalMenit
        maks_hari  = $maksHari
    }
}

function Baca-State {
    $statePath = Join-Path -Path $script:root -ChildPath 'state.json'
    if (Test-Path -LiteralPath $statePath) {
        try {
            $state = Get-Content -Raw -Path $statePath | ConvertFrom-Json
            if ($state -and $state.terakhir_sukses) {
                return [datetime]::Parse([string]$state.terakhir_sukses)
            }
        } catch {
            Write-Log 'state.json tidak valid; pakai rentang default.'
        }
    }

    return $null
}

function Simpan-State([datetime]$terakhir) {
    $state = [pscustomobject]@{ terakhir_sukses = $terakhir.ToString('yyyy-MM-dd HH:mm:ss') }
    $json = $state | ConvertTo-Json -Depth 3 -Compress
    [System.IO.File]::WriteAllText((Join-Path -Path $script:root -ChildPath 'state.json'), $json)
}

function Jalankan-Export([datetime]$dari, [datetime]$sampai) {
    $psExe = 'C:\Windows\SysWOW64\WindowsPowerShell\v1.0\powershell.exe'

    # Utamakan struktur instalasi resmi: <induk>\tools\attendance-mdb-export.ps1.
    # Bila tidak ada, cari di folder bridge sendiri supaya instalasi yang hanya
    # menyalin folder presensi-bridge tetap bisa berjalan.
    $exporter = Join-Path -Path (Split-Path -Parent $script:root) -ChildPath 'tools/attendance-mdb-export.ps1'
    $exporter = [System.IO.Path]::GetFullPath($exporter)
    if (-not (Test-Path -LiteralPath $exporter)) {
        $fallback = Join-Path -Path $script:root -ChildPath 'attendance-mdb-export.ps1'
        if (Test-Path -LiteralPath $fallback) {
            $exporter = [System.IO.Path]::GetFullPath($fallback)
        }
    }
    if (-not (Test-Path -LiteralPath $exporter)) {
        throw "Pembaca database mesin tidak ditemukan. Salin attendance-mdb-export.ps1 ke folder 'tools\' (satu tingkat di atas presensi-bridge) atau ke folder presensi-bridge itu sendiri."
    }

    $out = [System.IO.Path]::Combine([System.IO.Path]::GetTempPath(), ('simantap-mesin-bridge-{0}.json' -f $PID))

    $argList = @(
        '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
        '-File', $exporter, '-DbPath', $cfg.db_path, '-OutFile', $out,
        '-From', $dari.ToString('yyyy-MM-dd'), '-To', $sampai.ToString('yyyy-MM-dd')
    )
    & $psExe @argList *> $null
    if ($LASTEXITCODE -ne 0) {
        throw "Pembaca database mesin gagal (exit $($LASTEXITCODE))."
    }

    $mentah = [string](Get-Content -Raw -Path $out)
    Remove-Item -LiteralPath $out -Force -ErrorAction SilentlyContinue
    return $mentah
}

function Kirim-Ke-Server($payload) {
    $body = $payload | ConvertTo-Json -Depth 10 -Compress
    return Invoke-RestMethod -Uri ($cfg.server_url + '/api/presensi/mesin/sinkron') -Method Post -ContentType 'application/json' -Body $body -Headers @{ 'X-API-Key' = $cfg.api_key } -TimeoutSec 120
}

$script:root = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($ConfigPath)) {
    $script:config_path = Join-Path -Path $script:root -ChildPath 'config.json'
} else {
    $script:config_path = $ConfigPath
}
$script:config = Get-Config
$cfg = $script:config

if ([string]::IsNullOrWhiteSpace([string]$cfg.log_file)) {
    $script:log_file = Join-Path -Path $script:root -ChildPath 'presinsi-bridge.log'
} else {
    $script:log_file = [string]$cfg.log_file
}

Write-Log 'Presensi Bridge dimulai.'

$today = Get-Date
$terakhir = Baca-State
if ($null -ne $terakhir) {
    $dari = $terakhir.AddDays(-1)
} else {
    $dari = $today.AddDays(-1)
}
$dari = $dari.Date
if ($dari -lt $today.AddDays(-$($cfg.maks_hari))) {
    $dari = $today.AddDays(-$($cfg.maks_hari))
}
$sampai = $today.AddDays(1)

$mentah = Jalankan-Export $dari $sampai
$data = $mentah | ConvertFrom-Json
if ($data.ok -ne $true) {
    throw "Pembaca database mesin gagal: $($data.pesan)"
}

$scans = @()
if ($data.scans) {
    foreach ($s in @($data.scans)) {
        $nik = ([string]$s.nik)
        $tipe = ([string]$s.tipe)
        $waktuTeks = ([string]$s.waktu)
        if ([string]::IsNullOrWhiteSpace($nik) -or [string]::IsNullOrWhiteSpace($waktuTeks)) {
            continue
        }

        $jenis = 'masuk'
        $tipeLower = $tipe.ToLowerInvariant()
        if ($tipeLower.IndexOf('keluar') -ge 0 -or $tipeLower.IndexOf('pulang') -ge 0 -or $tipeLower.IndexOf('out') -ge 0) {
            $jenis = 'pulang'
        } elseif ($tipeLower.IndexOf('masuk') -ge 0 -or $tipeLower.IndexOf('datang') -ge 0 -or $tipeLower.IndexOf('in') -ge 0) {
            $jenis = 'masuk'
        }

        $bagian = ([string]$waktuTeks -split ' ', 2)
        $waktuBersih = ([string]$bagian[0]) -replace '(\d{1,2})\.(\d{2})\.(\d{2})', '$1:$2:$3'
        $waktuIso = if ($bagian.Count -gt 1) { $waktuBersih + 'T' + [string]$bagian[1] } else { $waktuBersih }

        $scans += [pscustomobject]@{
            nik   = $nik
            waktu = $waktuIso
            jenis = $jenis
        }
    }
}

$pegawai = @()
if ($data.pegawai) {
    foreach ($p in @($data.pegawai)) {
        $nik = ([string]$p.nik)
        $nama = ([string]$p.nama)
        if ([string]::IsNullOrWhiteSpace($nik) -or [string]::IsNullOrWhiteSpace($nama)) {
            continue
        }

        $pegawai += [pscustomobject]@{ nik = $nik; nama = $nama }
    }
}

if ($scans.Count -gt 0) {
    $payload = [pscustomobject]@{ scans = $scans; pegawai = $pegawai }
    $hasil = Kirim-Ke-Server $payload
    $script:terakhir = Get-Date
    Simpan-State $script:terakhir

    Write-Log ("Sinkron berhasil: {0} scan dari mesin." -f $scans.Count)
    Write-Log ("Respons: {0}" -f ($hasil | ConvertTo-Json -Depth 6 -Compress))

    exit 0
}

Write-Log ('Tidak ada scan pada rentang {0:yyyy-MM-dd} s/d {1:yyyy-MM-dd}.' -f $dari, $sampai.AddDays(-1))
exit 0
