<?php

namespace App\Services\MesinPresensi;

use App\Services\PresensiMesinService;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Membaca log fingerprint langsung dari database Attendance Management
 * (att2000.mdb / .accdb) memakai dua mesin pembaca:
 *
 * 1. ekstensi PDO ODBC bila aktif di PHP;
 * 2. jembatan PowerShell 32-bit (ADODB/Jet) bila ekstensi tidak tersedia.
 *
 * Hasilnya selalu berupa daftar scan siap diimpor oleh PresensiMesinService.
 */
class AttendanceDatabaseReader
{
    private const EKSTENSI = ['mdb', 'accdb'];

    private PresensiMesinService $presensi;

    public function __construct(PresensiMesinService $presensi)
    {
        $this->presensi = $presensi;
    }

    /**
     * Lokasi database mesin yang akan dibaca. Bila belum diatur lewat .env,
     * cari di lokasi pemasangan Attendance Management yang umum.
     */
    public function path(): ?string
    {
        $dariEnv = trim((string) config('presensi.mesin_db_path', ''));
        if ($dariEnv !== '') {
            return $dariEnv;
        }

        foreach ((array) config('presensi.mesin_db_kandidat', []) as $kandidat) {
            $kandidat = trim((string) $kandidat);
            if ($kandidat !== '' && is_file($kandidat)) {
                return $kandidat;
            }
        }

        return null;
    }

    public function engine(): string
    {
        $pilihan = strtolower((string) config('presensi.mesin_db_engine', 'auto'));

        if ($pilihan === 'odbc') {
            return in_array('odbc', \PDO::getAvailableDrivers(), true) ? 'odbc' : 'tidak-tersedia';
        }

        if ($pilihan === 'powershell') {
            return $this->powershell() !== null ? 'powershell' : 'tidak-tersedia';
        }

        if (in_array('odbc', \PDO::getAvailableDrivers(), true)) {
            return 'odbc';
        }

        return $this->powershell() !== null ? 'powershell' : 'tidak-tersedia';
    }

    /**
     * Status singkat untuk tombol "Tes Koneksi".
     *
     * @return array{ok: bool, pesan: string, path: ?string, engine: string, jumlah_pegawai?: int, jumlah_scan?: int}
     */
    public function status(): array
    {
        $path = $this->path();

        if ($path === null) {
            return [
                'ok' => false,
                'pesan' => 'Lokasi database Attendance Management belum diatur. Isi PRESENSI_MESIN_DB_PATH di .env.',
                'path' => null,
                'engine' => $this->engine(),
            ];
        }

        if (! is_file($path)) {
            return [
                'ok' => false,
                'pesan' => 'Database tidak ditemukan di '.$path.'.',
                'path' => $path,
                'engine' => $this->engine(),
            ];
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EKSTENSI, true)) {
            return [
                'ok' => false,
                'pesan' => 'File database harus berekstensi .mdb atau .accdb.',
                'path' => $path,
                'engine' => $this->engine(),
            ];
        }

        $engine = $this->engine();
        if ($engine === 'tidak-tersedia') {
            return [
                'ok' => false,
                'pesan' => 'Tidak ada pembaca database yang tersedia. Aktifkan ekstensi pdo_odbc atau jalankan di Windows.',
                'path' => $path,
                'engine' => $engine,
            ];
        }

        try {
            $data = $this->bacaMentah($path, $engine);

            return [
                'ok' => true,
                'pesan' => 'Database berhasil dibaca.',
                'path' => $path,
                'engine' => $engine,
                'jumlah_pegawai' => (int) ($data['jumlah_pegawai'] ?? 0),
                'jumlah_scan' => (int) ($data['jumlah_scan'] ?? 0),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'pesan' => $e->getMessage(),
                'path' => $path,
                'engine' => $engine,
            ];
        }
    }

    /**
     * @return list<array{nik: string, waktu: Carbon, jenis: string}>
     */
    public function baca(Carbon $dari, Carbon $sampai): array
    {
        $path = $this->path();
        if ($path === null) {
            throw new RuntimeException('Lokasi database Attendance Management belum diatur. Isi PRESENSI_MESIN_DB_PATH di .env.');
        }
        if (! is_file($path)) {
            throw new RuntimeException('Database Attendance Management tidak ditemukan di '.$path.'.');
        }
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EKSTENSI, true)) {
            throw new RuntimeException('File database mesin harus berekstensi .mdb atau .accdb.');
        }

        $engine = $this->engine();
        if ($engine === 'tidak-tersedia') {
            throw new RuntimeException('Tidak ada pembaca database yang tersedia. Aktifkan ekstensi pdo_odbc atau jalankan di Windows.');
        }

        $data = $this->bacaMentah($path, $engine, $dari, $sampai);

        $scans = [];
        foreach ((array) ($data['scans'] ?? []) as $row) {
            $nik = $this->presensi->normalizeNik($row['nik'] ?? null);
            $waktu = $this->parseWaktu($row['waktu'] ?? null);
            if ($nik === null || $waktu === null) {
                continue;
            }

            $scans[] = [
                'nik' => $nik,
                'waktu' => $waktu,
                'jenis' => $this->presensi->jenisScan($row['tipe'] ?? null, $waktu),
            ];
        }

        return $scans;
    }

    /**
     * Daftar pegawai (NIK/No.PIN dan nama) dari database mesin, untuk
     * mengisi kolom users.nik sebelum log presensi ditarik.
     *
     * @return list<array{nik: string, nama: string}>
     */
    public function pegawai(): array
    {
        $path = $this->path();
        if ($path === null) {
            throw new RuntimeException('Lokasi database Attendance Management belum diatur. Isi PRESENSI_MESIN_DB_PATH di .env.');
        }
        if (! is_file($path)) {
            throw new RuntimeException('Database Attendance Management tidak ditemukan di '.$path.'.');
        }
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EKSTENSI, true)) {
            throw new RuntimeException('File database mesin harus berekstensi .mdb atau .accdb.');
        }

        $engine = $this->engine();
        if ($engine === 'tidak-tersedia') {
            throw new RuntimeException('Tidak ada pembaca database yang tersedia. Aktifkan ekstensi pdo_odbc atau jalankan di Windows.');
        }

        $data = $this->bacaMentah($path, $engine);

        $hasil = [];
        foreach ((array) ($data['pegawai'] ?? []) as $row) {
            $nik = $this->presensi->normalizeNik($row['nik'] ?? null);
            $nama = trim((string) ($row['nama'] ?? ''));
            if ($nik === null || $nama === '') {
                continue;
            }

            $hasil[$nik] = ['nik' => $nik, 'nama' => $nama];
        }

        return array_values($hasil);
    }

    /**
     * @return array{jumlah_pegawai?: int, jumlah_scan?: int, pegawai?: list<array<string, mixed>>, scans?: list<array<string, mixed>>}
     */
    private function bacaMentah(string $path, string $engine, ?Carbon $dari = null, ?Carbon $sampai = null): array
    {
        return $engine === 'odbc'
            ? $this->bacaLewatOdbc($path, $dari, $sampai)
            : $this->bacaLewatPowerShell($path, $dari, $sampai);
    }

    /**
     * @return array{jumlah_pegawai?: int, jumlah_scan?: int, pegawai?: list<array<string, mixed>>, scans?: list<array<string, mixed>>}
     */
    private function bacaLewatOdbc(string $path, ?Carbon $dari, ?Carbon $sampai): array
    {
        $dsn = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq='.$path.';';
        $pdo = new \PDO($dsn, '', '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, (int) config('presensi.mesin_db_timeout', 30));

        $jumlahPegawai = (int) $pdo->query('SELECT COUNT(*) FROM USERINFO')->fetchColumn();
        $jumlahScan = (int) $pdo->query('SELECT COUNT(*) FROM CHECKINOUT')->fetchColumn();

        $hasil = [
            'jumlah_pegawai' => $jumlahPegawai,
            'jumlah_scan' => $jumlahScan,
            'pegawai' => [],
            'scans' => [],
        ];

        foreach ($pdo->query('SELECT BADGENUMBER AS NIK, NAME AS NAMA FROM USERINFO ORDER BY NAME') as $row) {
            $hasil['pegawai'][] = [
                'nik' => $row['NIK'] ?? null,
                'nama' => $row['NAMA'] ?? null,
            ];
        }

        if ($dari === null || $sampai === null) {
            return $hasil;
        }

        $awal = $dari->copy()->startOfDay();
        $akhir = $sampai->copy()->addDay()->startOfDay();

        $sql = sprintf(
            'SELECT U.BADGENUMBER AS NIK, U.NAME AS NAMA, C.CHECKTIME AS WAKTU, C.CHECKTYPE AS TIPE
             FROM CHECKINOUT C INNER JOIN USERINFO U ON U.USERID = C.USERID
             WHERE C.CHECKTIME >= #%s# AND C.CHECKTIME < #%s#
             ORDER BY C.CHECKTIME',
            $awal->format('Y-m-d H:i:s'),
            $akhir->format('Y-m-d H:i:s'),
        );

        foreach ($pdo->query($sql) as $row) {
            $hasil['scans'][] = [
                'nik' => $row['NIK'] ?? null,
                'nama' => $row['NAMA'] ?? null,
                'waktu' => $row['WAKTU'] ?? null,
                'tipe' => $row['TIPE'] ?? null,
            ];
        }

        return $hasil;
    }

    /**
     * @return array{jumlah_pegawai?: int, jumlah_scan?: int, scans?: list<array<string, mixed>>}
     */
    private function bacaLewatPowerShell(string $path, ?Carbon $dari, ?Carbon $sampai): array
    {
        $powershell = $this->powershell();
        if ($powershell === null) {
            throw new RuntimeException('PowerShell tidak ditemukan pada server ini.');
        }

        $script = (string) config('presensi.mesin_db_script', base_path('tools/attendance-mdb-export.ps1'));
        if (! is_file($script)) {
            throw new RuntimeException('Skrip pembaca database mesin tidak ditemukan di '.$script.'.');
        }

        $out = tempnam(sys_get_temp_dir(), 'mesin-presensi-');
        if ($out === false) {
            throw new RuntimeException('Tidak dapat membuat berkas sementara untuk membaca database mesin.');
        }

        try {
            $perintah = [
                $powershell,
                '-NoProfile',
                '-NonInteractive',
                '-ExecutionPolicy', 'Bypass',
                '-File', $script,
                '-DbPath', $path,
                '-OutFile', $out,
            ];

            if ($dari !== null && $sampai !== null) {
                $perintah[] = '-From';
                $perintah[] = $dari->format('Y-m-d');
                $perintah[] = '-To';
                $perintah[] = $sampai->format('Y-m-d');
            }

            $process = new Process($perintah, null, null, null, (float) config('presensi.mesin_db_timeout', 120));
            $process->run();

            $mentah = trim((string) @file_get_contents($out));
            $mentah = preg_replace('/^\xEF\xBB\xBF/', '', $mentah) ?? $mentah;

            if ($mentah === '') {
                $pesan = trim($process->getErrorOutput()) ?: trim($process->getOutput());

                throw new RuntimeException('Pembaca database mesin tidak mengembalikan data.'.($pesan !== '' ? ' '.$pesan : ''));
            }

            $data = json_decode($mentah, true);
            if (! is_array($data)) {
                throw new RuntimeException('Hasil pembacaan database mesin tidak valid.');
            }
            if (($data['ok'] ?? false) !== true) {
                throw new RuntimeException((string) ($data['pesan'] ?? 'Gagal membaca database mesin.'));
            }

            return $data;
        } finally {
            @unlink($out);
        }
    }

    private function powershell(): ?string
    {
        $dariConfig = trim((string) config('presensi.mesin_db_powershell', ''));
        if ($dariConfig !== '' && is_file($dariConfig)) {
            return $dariConfig;
        }

        $kandidat = [
            'C:\\Windows\\SysWOW64\\WindowsPowerShell\\v1.0\\powershell.exe',
            'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
        ];

        foreach ($kandidat as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function parseWaktu($value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        // Access kadang mengembalikan tanggal dalam format Amerika atau serial.
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'm/d/Y H:i:s', 'm/d/Y H:i', 'd/m/Y H:i:s', 'd/m/Y H:i'] as $format) {
            try {
                $waktu = Carbon::createFromFormat($format, $value, config('app.timezone'));
                if ($waktu !== false) {
                    return $waktu;
                }
            } catch (Throwable) {
                // Coba format berikutnya.
            }
        }

        if (is_numeric($value) && (float) $value > 20000) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject((float) $value));
            } catch (Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
