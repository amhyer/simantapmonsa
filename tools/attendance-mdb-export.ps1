param(
    [Parameter(Mandatory = $true)][string]$DbPath,
    [Parameter(Mandatory = $true)][string]$OutFile,
    [string]$From = '',
    [string]$To = ''
)

# Pembaca database Attendance Management (ZKTeco) untuk SIMANTAP.
# Dijalankan oleh Laravel memakai PowerShell 32-bit karena driver
# Microsoft Jet OLEDB hanya tersedia dalam versi 32-bit.

$ErrorActionPreference = 'Stop'

function Tulis-Hasil($payload) {
    $json = $payload | ConvertTo-Json -Depth 6 -Compress
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($OutFile, $json, $utf8)
}

try {
    if (-not (Test-Path -LiteralPath $DbPath)) {
        throw "Database tidak ditemukan: $DbPath"
    }

    $ekstensi = [System.IO.Path]::GetExtension($DbPath).ToLowerInvariant()
    $provider = if ($ekstensi -eq '.accdb') {
        'Microsoft.ACE.OLEDB.12.0'
    } else {
        'Microsoft.Jet.OLEDB.4.0'
    }

    $koneksi = New-Object -ComObject ADODB.Connection
    $koneksi.Open("Provider=$provider;Data Source=$DbPath;Persist Security Info=False;")

    $hasil = [ordered]@{ ok = $true; pesan = 'OK'; jumlah_pegawai = 0; jumlah_scan = 0; pegawai = @(); scans = @() }

    $rs = $koneksi.Execute('SELECT COUNT(*) AS JML FROM USERINFO')
    if (-not $rs.EOF) { $hasil.jumlah_pegawai = [int]$rs.Fields.Item('JML').Value }
    $rs.Close()

    $rs = $koneksi.Execute('SELECT COUNT(*) AS JML FROM CHECKINOUT')
    if (-not $rs.EOF) { $hasil.jumlah_scan = [int]$rs.Fields.Item('JML').Value }
    $rs.Close()

    $daftarPegawai = New-Object System.Collections.ArrayList
    $rs = $koneksi.Execute('SELECT BADGENUMBER AS NIK, NAME AS NAMA FROM USERINFO ORDER BY NAME')
    while (-not $rs.EOF) {
        [void]$daftarPegawai.Add([ordered]@{
            nik  = [string]$rs.Fields.Item('NIK').Value
            nama = [string]$rs.Fields.Item('NAMA').Value
        })
        $rs.MoveNext()
    }
    $rs.Close()
    $hasil.pegawai = $daftarPegawai.ToArray()

    if ($From -ne '' -and $To -ne '') {
        $awal = [datetime]::ParseExact($From, 'yyyy-MM-dd', $null)
        $akhir = [datetime]::ParseExact($To, 'yyyy-MM-dd', $null).AddDays(1)

        $sql = "SELECT U.BADGENUMBER AS NIK, U.NAME AS NAMA, C.CHECKTIME AS WAKTU, C.CHECKTYPE AS TIPE " +
               "FROM CHECKINOUT C INNER JOIN USERINFO U ON U.USERID = C.USERID " +
               "WHERE C.CHECKTIME >= #" + $awal.ToString('yyyy-MM-dd HH:mm:ss') + "# " +
               "AND C.CHECKTIME < #" + $akhir.ToString('yyyy-MM-dd HH:mm:ss') + "# " +
               "ORDER BY C.CHECKTIME"
    } else {
        $sql = "SELECT U.BADGENUMBER AS NIK, U.NAME AS NAMA, C.CHECKTIME AS WAKTU, C.CHECKTYPE AS TIPE " +
               "FROM CHECKINOUT C INNER JOIN USERINFO U ON U.USERID = C.USERID " +
               "ORDER BY C.CHECKTIME"
    }

    $rs = $koneksi.Execute($sql)
    $daftar = New-Object System.Collections.ArrayList

    while (-not $rs.EOF) {
        $waktu = $rs.Fields.Item('WAKTU').Value
        if ($waktu -is [datetime]) {
            $waktuTeks = $waktu.ToString('yyyy-MM-dd HH:mm:ss')
        } else {
            $waktuTeks = [string]$waktu
        }

        [void]$daftar.Add([ordered]@{
            nik   = [string]$rs.Fields.Item('NIK').Value
            nama  = [string]$rs.Fields.Item('NAMA').Value
            waktu = $waktuTeks
            tipe  = [string]$rs.Fields.Item('TIPE').Value
        })
        $rs.MoveNext()
    }

    $rs.Close()
    $hasil.scans = $daftar.ToArray()
    $hasil.jumlah_scan_rentang = $daftar.Count

    $koneksi.Close()
    Tulis-Hasil $hasil
} catch {
    Tulis-Hasil ([ordered]@{
        ok     = $false
        pesan  = $_.Exception.Message
        scans  = @()
    })
}
