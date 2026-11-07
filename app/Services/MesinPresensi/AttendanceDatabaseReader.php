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

    private const COMMAND_CONNECT = 1000;
    private const COMMAND_EXIT = 1001;
    private const COMMAND_ENABLE_DEVICE = 1002;
    private const COMMAND_DISABLE_DEVICE = 1003;
    private const COMMAND_ACK_OK = 2000;
    private const COMMAND_ACK_ERROR = 2001;
    private const COMMAND_ACK_DATA = 2002;
    private const COMMAND_PREPARE_DATA = 1500;
    private const COMMAND_DATA = 1501;
    private const COMMAND_FREE_DATA = 1502;
    private const COMMAND_ATTLOG_RRQ = 13;

    private const MESIN_EPOCH = '2000-01-01 00:00:00';

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

        if ($pilihan === 'tcp') {
            $host = trim((string) config('presensi.mesin_db_tcp_host', ''));
            $port = (int) config('presensi.mesin_db_tcp_port', 4370);

            if ($host === '' || $port <= 0) {
                return 'tidak-tersedia';
            }

            return 'tcp';
        }

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

        if ($engine === 'tcp') {
            $tcpHost = trim((string) config('presensi.mesin_db_tcp_host', ''));
            $tcpPort = (int) config('presensi.mesin_db_tcp_port', 4370);

            if ($tcpHost === '') {
                return [
                    'ok' => false,
                    'pesan' => 'Lokasi mesin belum diatur. Isi PRESENSI_MESIN_DB_TCP_HOST di .env.',
                    'path' => null,
                    'engine' => $engine,
                ];
            }

            $timeoutTcp = (float) config('presensi.mesin_db_timeout', 120);
            $errnoTcp = 0;
            $errstrTcp = '';
            $socketTcp = @fsockopen($tcpHost, $tcpPort, $errnoTcp, $errstrTcp, $timeoutTcp);
            if ($socketTcp === false) {
                return [
                    'ok' => false,
                    'pesan' => 'Tidak dapat terhubung ke mesin via TCP ('.$tcpHost.':'.$tcpPort.') : '.$errstrTcp,
                    'path' => $tcpHost.':'.$tcpPort,
                    'engine' => $engine,
                ];
            }
            fclose($socketTcp);

            return [
                'ok' => true,
                'pesan' => 'Terhubung ke mesin via TCP.',
                'path' => $tcpHost.':'.$tcpPort,
                'engine' => $engine,
                'jumlah_pegawai' => 0,
                'jumlah_scan' => null,
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
        $engine = $this->engine();
        if ($engine === 'tidak-tersedia') {
            throw new RuntimeException('Tidak ada pembaca database yang tersedia. Aktifkan ekstensi pdo_odbc atau jalankan di Windows.');
        }

        if ($engine === 'tcp') {
            $host = (string) config('presensi.mesin_db_tcp_host', '192.168.1.7');
            $port = (int) config('presensi.mesin_db_tcp_port', 4370);

            try {
                $hasilTcp = $this->bacaLewatTcp($host, $port);
                $scans = [];
                foreach ((array) ($hasilTcp['scans'] ?? []) as $row) {
                    $nik = $this->presensi->normalizeNik($row['nik'] ?? null);
                    $waktu = $this->parseWaktu($row['waktu'] ?? null);
                    if ($nik === null || $waktu === null) {
                        continue;
                    }

                    $scans[] = [
                        'nik' => $nik,
                        'waktu' => $waktu,
                        'jenis' => $this->presensi->jenisScan((string) ($row['tipe'] ?? ''), $waktu),
                    ];
                }

                return $scans;
            } catch (Throwable $e) {
                // TCP gagal (misal mesin sedang tidak aktif/reachable) -> fallback ke database.
                $data = $this->bacaMentah($path, 'powershell', $dari, $sampai);

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
        }

        if (! is_file($path)) {
            throw new RuntimeException('Database Attendance Management tidak ditemukan di '.$path.'.');
        }
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EKSTENSI, true)) {
            throw new RuntimeException('File database mesin harus berekstensi .mdb atau .accdb.');
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
     * Ambil rentang waktu scan yang tersedia di database mesin, dipakai tombol
     * "Tarik Semua Data" supaya admin tidak perlu mengetik rentang tanggal.
     *
     * @return array{min: ?Carbon, maks: ?Carbon}
     */
    public function rentang(): array
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

        $min = null;
        $maks = null;
        foreach ((array) ($data['scans'] ?? []) as $row) {
            $waktu = $this->parseWaktu($row['waktu'] ?? null);
            if ($waktu === null) {
                continue;
            }

            if ($min === null || $waktu->lt($min)) {
                $min = $waktu;
            }
            if ($maks === null || $waktu->gt($maks)) {
                $maks = $waktu;
            }
        }

        return ['min' => $min, 'maks' => $maks];
    }

    /**
     * @return array{jumlah_pegawai?: int, jumlah_scan?: int, pegawai?: list<array<string, mixed>>, scans?: list<array<string, mixed>>}
     */
    private function bacaMentah(string $path, string $engine, ?Carbon $dari = null, ?Carbon $sampai = null): array
    {
        // Tanpa rentang waktu (null), kedua mesin pembaca membaca seluruh isi CHECKINOUT.
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

        $awal = ($dari ?? Carbon::parse('1900-01-01'))->copy()->startOfDay();
        $akhir = ($sampai ?? Carbon::parse('2100-01-01'))->copy()->addDay()->startOfDay();

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
                $detailProses = trim((string) $process->getErrorOutput());
                if ($detailProses === '') {
                    $detailProses = trim((string) $process->getOutput());
                }

                $pesanAkhir = (string) ($data['pesan'] ?? 'Gagal membaca database mesin.');
                if ($detailProses !== '') {
                    $pesanAkhir .= ' ['.$detailProses.']';
                }

                throw new RuntimeException('Pembaca database mesin gagal: '.$pesanAkhir);
            }

            return $data;
        } finally {
            @unlink($out);
        }
    }

    /**
     * Baca log presensi langsung dari mesin ZKTeco via TCP (port default: 4370).
     *
     * @return array{ok: bool, pesan: string, scans?: list<array<string, mixed>>}
     */
    private function bacaLewatTcp(string $host, int $port): array
    {
        $timeout = (float) config('presensi.mesin_db_timeout', 120);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket === false) {
            throw new RuntimeException('Gagal koneksi ke mesin via TCP ('.$host.':'.$port.') : '.$errstr);
        }

        try {
            // Sesi: kirim CMD_CONNECT lalu tunggu ACK.
            $replyId = 0;
            $this->kirimPaketTcp($socket, self::COMMAND_CONNECT, 0, $replyId, '');
            $replyId++;
            $resp = $this->terimaPaketTcp($socket);
            if ((int) $resp['command'] !== self::COMMAND_ACK_OK) {
                throw new RuntimeException('Mesin menolak sesi koneksi (kode: '.$resp['command'].').');
            }
            $sessionId = (int) $resp['sessionId'];
            $replyId = 1;

            // Matikan sementara mode capture (best effort).
            $this->kirimPaketTcp($socket, self::COMMAND_DISABLE_DEVICE, $sessionId, $replyId, '');
            $replyId++;
            try {
                $this->terimaPaketTcp($socket);
            } catch (Throwable) {
                // Tidak ada respons yang diharapkan.
            }

            // Minta log presensi (CMD_ATTLOG_RRQ).
            $rawData = '';
            $this->kirimPaketTcp($socket, self::COMMAND_ATTLOG_RRQ, $sessionId, $replyId, '');
            $replyId++;
            $resp = $this->terimaPaketTcp($socket);

            if ((int) $resp['command'] === self::COMMAND_ACK_DATA) {
                if (strlen((string) $resp['data']) >= 4) {
                    $dataSize = unpack('V', (string) substr((string) $resp['data'], 0, 4))[1];
                    if ((int) $dataSize > 0 && strlen((string) $resp['data']) - 4 >= $dataSize) {
                        $rawData = substr((string) $resp['data'], 4, (int) $dataSize);
                    }
                } elseif (strlen((string) $resp['data']) > 0) {
                    $rawData = (string) $resp['data'];
                }
            } elseif ((int) $resp['command'] === self::COMMAND_PREPARE_DATA) {
                if (strlen((string) $resp['data']) >= 4) {
                    $dataSize = unpack('V', (string) substr((string) $resp['data'], 0, 4))[1];
                    $buffer = '';
                    $maksBaca = max((int) $dataSize, 1);
                    while (strlen($buffer) < $maksBaca) {
                        $this->kirimPaketTcp($socket, self::COMMAND_DATA, $sessionId, $replyId, '');
                        $replyId++;
                        $chunk = $this->terimaPaketTcp($socket);
                        if ((int) $chunk['command'] !== self::COMMAND_DATA) {
                            break;
                        }
                        $buffer .= (string) $chunk['data'];
                    }
                    $rawData = $buffer;
                }
            }

            // Bebas buffer (best effort).
            $this->kirimPaketTcp($socket, self::COMMAND_FREE_DATA, $sessionId, $replyId, '');
            $replyId++;
            try {
                $this->terimaPaketTcp($socket);
            } catch (Throwable) {
                // Tidak ada respons yang diharapkan.
            }

            // Aktifkan kembali mesin (best effort).
            $this->kirimPaketTcp($socket, self::COMMAND_ENABLE_DEVICE, $sessionId, $replyId, '');
            $replyId++;
            try {
                $this->terimaPaketTcp($socket);
            } catch (Throwable) {
                // Tidak ada respons yang diharapkan.
            }

            // Tutup sesi (best effort).
            $this->kirimPaketTcp($socket, self::COMMAND_EXIT, $sessionId, $replyId, '');

            $scans = [];
            $dataTeks = $rawData;
            $recordSize = $this->deteksiUkuranRecord($dataTeks);
            if ($recordSize > 0) {
                for ($i = 0; $i + $recordSize <= strlen($dataTeks); $i += $recordSize) {
                    $record = substr($dataTeks, $i, $recordSize);
                    $log = $this->parseRecordTcp($record, $recordSize);
                    if ($log === null) {
                        continue;
                    }

                    $scans[] = [
                        'nik' => (string) $log['userId'],
                        'nama' => 'Pegawai Mesin #'.$log['userId'],
                        'waktu' => $log['waktu'],
                        'tipe' => (string) $log['state'],
                    ];
                }
            }

            return ['ok' => true, 'pesan' => 'OK', 'scans' => $scans];
        } finally {
            @fclose($socket);
        }
    }

    /**
     * @return array{command: int, sessionId: int, replyId: int, data: string}
     */
    private function terimaPaketTcp($socket): array
    {
        $header = (string) fread($socket, 8);
        if (strlen($header) < 8) {
            throw new RuntimeException('Respons dari mesin via TCP terlalu pendek.');
        }

        $dataLen = unpack('v', substr($header, 2, 2))[1];
        if ($dataLen < 6) {
            $dataLen = 6;
        }

        $rest = (string) fread($socket, (int) $dataLen);
        $buf = $header.$rest;

        $command = unpack('v', substr($buf, 6, 2))[1];
        $sessionId = unpack('v', substr($buf, 10, 2))[1];
        $replyId = unpack('v', substr($buf, 12, 2))[1];
        $data = strlen($buf) > 14 ? substr($buf, 14) : '';

        return [
            'command' => (int) $command,
            'sessionId' => (int) $sessionId,
            'replyId' => (int) $replyId,
            'data' => $data,
        ];
    }

    private function kirimPaketTcp($socket, int $command, int $sessionId, int $replyId, string $data): void
    {
        $packet = $this->buatPaketTcp($command, $sessionId, $replyId, $data);
        fwrite($socket, $packet);
    }

    private function kirimPaketTcpDenganTunggu($socket, int $command, int $sessionId, int $replyId, string $data): array
    {
        $this->kirimPaketTcp($socket, $command, $sessionId, $replyId, $data);

        $header = (string) fread($socket, 8);
        if (strlen($header) < 8) {
            throw new RuntimeException('Respons dari mesin via TCP terlalu pendek.');
        }

        $dataLen = unpack('v', substr($header, 2, 2))[1];
        if ($dataLen < 6) {
            $dataLen = 6;
        }

        $rest = (string) fread($socket, (int) $dataLen);
        $buf = $header.$rest;

        $commandTerkendali = unpack('v', substr($buf, 6, 2))[1];
        $sessionIdTerkendali = unpack('v', substr($buf, 10, 2))[1];
        $replyIdTerkendali = unpack('v', substr($buf, 12, 2))[1];
        $dataTerkendali = strlen($buf) > 14 ? substr($buf, 14) : '';

        return [
            'command' => (int) $commandTerkendali,
            'sessionId' => (int) $sessionIdTerkendali,
            'replyId' => (int) $replyIdTerkendali,
            'data' => $dataTerkendali,
        ];
    }

    private function buatPaketTcp(int $command, int $sessionId, int $replyId, string $data): string
    {
        $packetLength = 8 + 6 + strlen($data);
        $packet = chr(0x50).chr(0x50).pack('v', strlen($data) + 8).pack('v', 0).pack('v', $command);

        $checksumInput = pack('v', $command).pack('v', 0).pack('v', $sessionId).pack('v', $replyId).$data;
        $checksumSum = 0;
        $panjang = strlen($checksumInput);
        for ($i = 0; $i + 1 < $panjang; $i += 2) {
            $checksumSum += ord($checksumInput[$i]).(ord($checksumInput[$i + 1]) * 256);
        }
        if ($panjang % 2 === 1) {
            $checksumSum += ord($checksumInput[$panjang - 1]);
        }
        while ($checksumSum > 65535) {
            $checksumSum = ($checksumSum & 0xFFFF) + intdiv($checksumSum, 65536);
        }
        $checksum = (~$checksumSum) & 0xFFFF;

        return $packet.pack('v', $checksum).pack('v', $sessionId).pack('v', $replyId).$data;
    }

    /**
     * @return array{userId: int, waktu: Carbon, state: int}|null
     */
    private function parseRecordTcp(string $record, int $recordSize): ?array
    {
        if ($recordSize === 8) {
            $userId = unpack('v', substr($record, 0, 2))[1];
            $timeSeconds = unpack('V', substr($record, 2, 4))[1];
            $state = ord($record[6]);
            $verifyType = ord($record[7]);
        } elseif ($recordSize === 16) {
            $userId = unpack('V', substr($record, 0, 4))[1];
            $timeSeconds = unpack('V', substr($record, 4, 4))[1];
            $state = ord($record[8]);
            $verifyType = ord($record[9]);
        } elseif ($recordSize === 40) {
            $userIdTeks = substr($record, 0, 9);
            $userIdTeks = rtrim($userIdTeks, "\x00");
            $userId = (int) $userIdTeks;
            $timeSeconds = unpack('V', substr($record, 24, 4))[1];
            $state = ord($record[28]);
            $verifyType = ord($record[29]);
        } else {
            return null;
        }

        if ($userId <= 0) {
            return null;
        }

        $waktu = Carbon::parse(self::MESIN_EPOCH)->addSeconds((int) $timeSeconds);

        return [
            'userId' => (int) $userId,
            'waktu' => $waktu,
            'state' => (int) $state,
            'verifyType' => (int) $verifyType,
        ];
    }

    /**
     * Deteksi ukuran record presensi dari payload yang dibaca.
     */
    private function deteksiUkuranRecord(string $data): int
    {
        foreach ([40, 16, 8] as $ukuran) {
            if (strlen($data) < $ukuran) {
                continue;
            }
            if (strlen($data) % $ukuran !== 0) {
                continue;
            }

            $sample = substr($data, 0, $ukuran);
            $parsed = $this->parseRecordTcp($sample, $ukuran);
            if ($parsed !== null && $parsed['userId'] > 0) {
                return $ukuran;
            }
        }

        return 0;
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

        // Access kadang mengembalikan pemisah "." alih/kultur (mis. "07.15.00").
        $value = (string) preg_replace('/(\d{1,2})\.(\d{2})\.(\d{2})/', '$1:$2:$3', $value);

        // Access kadang mengembalikan tanggal dalam format Amerika atau serial.
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'm/d/Y H:i:s', 'm/d/Y H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H.i.s', 'Y-m-d H.i'] as $format) {
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
