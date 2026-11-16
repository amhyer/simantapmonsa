param(
    [Parameter(Mandatory = $true)][string]$DbPath,
    [Parameter(Mandatory = $true)][string]$OutFile,
    [string]$From = '',
    [string]$To = '',
    [ValidateSet('lengkap', 'statistik', 'pegawai')][string]$Mode = 'lengkap'
)

# Pembaca database Attendance Management (ZKTeco) untuk SIMANTAP.
# Dijalankan oleh Laravel memakai PowerShell 32-bit karena driver
# Microsoft Jet OLEDB hanya tersedia dalam versi 32-bit.
#
# Mode:
#   lengkap   - jumlah pegawai/scan + daftar pegawai + daftar scan (default).
#   statistik - hanya jumlah pegawai/scan dan scan paling awal/akhir.
#               Dipakai agar halaman admin tidak perlu mengekspor seluruh
#               tabel CHECKINOUT hanya untuk menampilkan status.
#   pegawai   - hanya daftar pegawai (USERINFO), tanpa log scan.

$ErrorActionPreference = 'Stop'

function Tulis-Hasil($payload) {
    $json = $payload | ConvertTo-Json -Depth 6 -Compress
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($OutFile, $json, $utf8)
}

function Format-Waktu($nilai) {
    if ($null -eq $nilai -or "$nilai" -eq '') {
        return $null
    }
    if ($nilai -is [datetime]) {
        return $nilai.ToString('yyyy-MM-dd HH:mm:ss')
    }

    return [string]$nilai
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
    # Mode=Read mencegah SIMANTAP mengunci/mengubah database yang sedang
    # dipakai aplikasi Attendance Management (ZKTime).
    $koneksi.Open("Provider=$provider;Data Source=$DbPath;Mode=Read;Persist Security Info=False;")

    $hasil = [ordered]@{
        ok             = $true
        pesan          = 'OK'
        jumlah_pegawai = 0
        jumlah_scan    = 0
        min            = $null
        maks           = $null
        pegawai        = @()
        scans          = @()
    }

    $rs = $koneksi.Execute('SELECT COUNT(*) AS JML FROM USERINFO')
    if (-not $rs.EOF) { $hasil.jumlah_pegawai = [int]$rs.Fields.Item('JML').Value }
    $rs.Close()

    $rs = $koneksi.Execute('SELECT COUNT(*) AS JML, MIN(CHECKTIME) AS MINWAKTU, MAX(CHECKTIME) AS MAKSWAKTU FROM CHECKINOUT')
    if (-not $rs.EOF) {
        $hasil.jumlah_scan = [int]$rs.Fields.Item('JML').Value
        $hasil.min = Format-Waktu $rs.Fields.Item('MINWAKTU').Value
        $hasil.maks = Format-Waktu $rs.Fields.Item('MAKSWAKTU').Value
    }
    $rs.Close()

    if ($Mode -ne 'statistik') {
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
    }

    if ($Mode -eq 'lengkap') {
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
        # GetRows() mengambil seluruh hasil dalam satu panggilan COM. Iterasi
        # per baris lewat ADODB sangat lambat untuk ribuan log scan.
        $baris = $rs.GetRows()
        $rs.Close()

        $daftar = New-Object System.Collections.ArrayList

        if ($null -ne $baris) {
            $jumlahBaris = $baris.GetLength(1)
            for ($i = 0; $i -lt $jumlahBaris; $i++) {
                $nik = [string]$baris[0, $i]
                $nama = [string]$baris[1, $i]
                $waktuTeks = Format-Waktu $baris[2, $i]
                $tipe = [string]$baris[3, $i]

                [void]$daftar.Add([ordered]@{
                    nik   = $nik
                    nama  = $nama
                    waktu = $waktuTeks
                    tipe  = $tipe
                })
            }
        }

        $hasil.scans = $daftar.ToArray()
        $hasil.jumlah_scan_rentang = $daftar.Count
    }

    $koneksi.Close()
    Tulis-Hasil $hasil
} catch {
    Tulis-Hasil ([ordered]@{
        ok     = $false
        pesan  = $_.Exception.Message
        scans  = @()
    })
}
