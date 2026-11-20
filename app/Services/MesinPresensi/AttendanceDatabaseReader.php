<?php

namespace App\Services\MesinPresensi;

use App\Models\SekolahSettings;
use App\Services\PresensiMesinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Membaca log fingerprint langsung dari mesin ZKTeco via TCP, atau dari
 * database Attendance Management (att2000.mdb / .accdb) bila TCP tidak
 * tersedia. Database dibaca memakai dua mesin pembaca:
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

    private const COMMAND_AUTH = 1102;

    private const COMMAND_ATTLOG_RRQ = 13;

    private const COMMAND_PREPARE_DATA = 1500;

    private const COMMAND_DATA = 1501;

    private const COMMAND_FREE_DATA = 1502;

    private const COMMAND_DATA_WRRQ = 1503;

    private const COMMAND_DATA_RDY = 1504;

    private const COMMAND_ACK_OK = 2000;

    private const COMMAND_ACK_ERROR = 2001;

    private const COMMAND_ACK_DATA = 2002;

    private const COMMAND_ACK_UNAUTH = 2005;

    private const COMMAND_ACK_ERROR_DATA = 65531;

    private const COMMAND_ACK_ERROR_CMD = 65533;

    private const COMMAND_ACK_UNKNOWN = 65535;

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
        // Prioritas: pengaturan disimpan di database sekolah (bisa diubah via website),
        // lalu fallback ke nilai dari file konfigurasi (.env / PRESENSI_MESIN_DB_ENGINE).
        $pilihan = strtolower((string) ($this->pengaturanMesin()['presensi_mesin_engine'] ?? config('presensi.mesin_db_engine', 'auto')));

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
     * Nama engine pembacaan mesin yang sedang dipakai (dari pengaturan website atau .env).
     */
    public function engineTerpilih(): string
    {
        $pilihan = strtolower((string) ($this->pengaturanMesin()['presensi_mesin_engine'] ?? config('presensi.mesin_db_engine', 'auto')));

        return in_array($pilihan, ['auto', 'tcp', 'powershell', 'odbc'], true) ? $pilihan : 'auto';
    }

    /**
     * Ambil pengaturan mesin dari tabel settings sekolah (JSON).
     *
     * @return array<string, mixed>
     */
    private function pengaturanMesin(): array
    {
        try {
            $sekolah = SekolahSettings::query()->first();
            $data = $sekolah?->pengaturan;

            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Status singkat untuk tombol "Tes Koneksi".
     *
     * @return array{ok: bool, pesan: string, path: ?string, engine: string, jumlah_pegawai?: int, jumlah_scan?: int, terakhir_scan?: ?string}
     */
    public function status(): array
    {
        $engine = $this->engine();

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

            // Jangan tunggu 120 detik hanya untuk tes koneksi; mesin yang mati
            // harus cepat dilaporkan di halaman admin.
            $timeoutTcp = min(5.0, (float) config('presensi.mesin_db_timeout', 120));
            $errnoTcp = 0;
            $errstrTcp = '';
            $socketTcp = $this->openTcpSocket($tcpHost, $tcpPort, $timeoutTcp, $errnoTcp, $errstrTcp);
            if ($socketTcp === false) {
                return [
                    'ok' => false,
                    'pesan' => 'Tidak dapat terhubung ke mesin via TCP ('.$tcpHost.':'.$tcpPort.') : '
                        .($errstrTcp !== '' ? $errstrTcp : 'timeout / tidak ada route ke mesin'),
                    'path' => $tcpHost.':'.$tcpPort,
                    'engine' => $engine,
                ];
            }

            // Port terbuka belum tentu sesi diterima. Lakukan handshake
            // singkat agar communication key yang salah ikut terdeteksi di
            // tombol "Tes Koneksi", bukan baru muncul saat tarik data.
            try {
                stream_set_timeout($socketTcp, (int) max(1, ceil($timeoutTcp)));
                $sesi = $this->bukaSesiTcp($socketTcp);

                // Tutup sesi dengan sopan; balasannya tidak penting.
                stream_set_timeout($socketTcp, 2);
                $this->kirimPaketTcp($socketTcp, self::COMMAND_EXIT, $sesi['sessionId'], $sesi['replyId'], '');
                $this->abaikanBalasanTcp($socketTcp);
            } catch (Throwable $e) {
                fclose($socketTcp);

                return [
                    'ok' => false,
                    'pesan' => 'Port TCP terbuka, tetapi sesi mesin gagal: '.$e->getMessage(),
                    'path' => $tcpHost.':'.$tcpPort,
                    'engine' => $engine,
                ];
            }

            fclose($socketTcp);

            return [
                'ok' => true,
                'pesan' => 'Terhubung ke mesin via TCP dan communication key diterima.',
                'path' => $tcpHost.':'.$tcpPort,
                'engine' => $engine,
                'jumlah_pegawai' => 0,
                'jumlah_scan' => null,
            ];
        }

        $path = $this->path();

        if ($path === null) {
            return [
                'ok' => false,
                'pesan' => 'Lokasi database Attendance Management belum diatur. Isi PRESENSI_MESIN_DB_PATH di .env.',
                'path' => null,
                'engine' => $engine,
            ];
        }

        if (! is_file($path)) {
            return [
                'ok' => false,
                'pesan' => 'Database tidak ditemukan di '.$path.'.',
                'path' => $path,
                'engine' => $engine,
            ];
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EKSTENSI, true)) {
            return [
                'ok' => false,
                'pesan' => 'File database harus berekstensi .mdb atau .accdb.',
                'path' => $path,
                'engine' => $engine,
            ];
        }

        if ($engine === 'tidak-tersedia') {
            return [
                'ok' => false,
                'pesan' => 'Tidak ada pembaca database yang tersedia. Aktifkan ekstensi pdo_odbc atau jalankan di Windows.',
                'path' => $path,
                'engine' => $engine,
            ];
        }

        try {
            // Mode statistik hanya menjalankan COUNT/MIN/MAX di Access, sehingga
            // halaman admin tidak perlu mengekspor seluruh tabel CHECKINOUT.
            $data = $this->bacaMentahMode($path, $engine, 'statistik');
            $terakhirScan = $this->parseWaktu($data['maks'] ?? null);

            return [
                'ok' => true,
                'pesan' => 'Database berhasil dibaca.',
                'path' => $path,
                'engine' => $engine,
                'jumlah_pegawai' => (int) ($data['jumlah_pegawai'] ?? 0),
                'jumlah_scan' => (int) ($data['jumlah_scan'] ?? 0),
                'terakhir_scan' => $terakhirScan?->toDateTimeString(),
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
            if ($this->engineTerpilih() === 'tcp' && trim((string) config('presensi.mesin_db_tcp_host', '')) === '') {
                throw new RuntimeException('Mesin tidak dikonfigurasi; gunakan jalur Bridge.');
            }

            throw new RuntimeException('Tidak ada pembaca database yang tersedia. Aktifkan ekstensi pdo_odbc atau jalankan di Windows.');
        }

        if ($engine === 'tcp') {
            $host = trim((string) config('presensi.mesin_db_tcp_host', ''));
            $port = (int) config('presensi.mesin_db_tcp_port', 4370);
            if ($host === '') {
                throw new RuntimeException('Mesin tidak dikonfigurasi; gunakan jalur Bridge.');
            }

            try {
                $hasilTcp = $this->bacaLewatTcp($host, $port);
                $scans = [];
                $batasAkhir = $sampai->copy()->addDay()->startOfDay();

                foreach ((array) ($hasilTcp['scans'] ?? []) as $row) {
                    $nik = $this->presensi->normalizeNik($row['nik'] ?? null);
                    $waktu = $this->parseWaktu($row['waktu'] ?? null);
                    if ($nik === null || $waktu === null) {
                        continue;
                    }
                    if ($waktu->lt($dari) || $waktu->gte($batasAkhir)) {
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
                Log::warning('Tarik presensi via TCP gagal, memakai database Attendance Management: '.$e->getMessage());

                if ($path === null || ! is_file($path)) {
                    throw new RuntimeException(
                        'Gagal koneksi ke mesin via TCP ('.$e->getMessage().') dan database cadangan Attendance Management tidak ditemukan.',
                        0,
                        $e
                    );
                }
                if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EKSTENSI, true)) {
                    throw new RuntimeException('File database mesin harus berekstensi .mdb atau .accdb.', 0, $e);
                }

                $fallbackEngine = in_array('odbc', \PDO::getAvailableDrivers(), true) ? 'odbc' : 'powershell';
                $data = $this->bacaMentah($path, $fallbackEngine, $dari, $sampai);

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

        if ($path === null) {
            throw new RuntimeException('Lokasi database Attendance Management belum diatur. Isi PRESENSI_MESIN_DB_PATH di .env.');
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

        $data = $this->bacaMentahMode($path, $engine, 'pegawai');

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
        $engine = $this->engine();
        if ($engine === 'tidak-tersedia') {
            if ($this->engineTerpilih() === 'tcp' && trim((string) config('presensi.mesin_db_tcp_host', '')) === '') {
                throw new RuntimeException('Mesin tidak dikonfigurasi; gunakan jalur Bridge.');
            }

            throw new RuntimeException('Tidak ada pembaca database yang tersedia. Aktifkan ekstensi pdo_odbc atau jalankan di Windows.');
        }

        // Mesin ZKTeco via TCP tidak punya tabel SQL, jadi rentang dihitung
        // dari log yang dibaca langsung dari perangkat.
        if ($engine === 'tcp') {
            $host = trim((string) config('presensi.mesin_db_tcp_host', ''));
            if ($host === '') {
                throw new RuntimeException('Mesin tidak dikonfigurasi; gunakan jalur Bridge.');
            }
            $hasilTcp = $this->bacaLewatTcp(
                $host,
                (int) config('presensi.mesin_db_tcp_port', 4370),
            );

            $min = null;
            $maks = null;
            foreach ((array) ($hasilTcp['scans'] ?? []) as $row) {
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

        // Statistik MIN/MAX jauh lebih cepat daripada mengekspor seluruh log scan.
        $data = $this->bacaMentahMode($path, $engine, 'statistik');

        return [
            'min' => $this->parseWaktu($data['min'] ?? null),
            'maks' => $this->parseWaktu($data['maks'] ?? null),
        ];
    }

    /**
     * @return array{jumlah_pegawai?: int, jumlah_scan?: int, pegawai?: list<array<string, mixed>>, scans?: list<array<string, mixed>>}
     */
    /**
     * Mode live monitor: baca scan terbaru dari mesin ZKTeco via TCP.
     *
     * @return array{ok: bool, pesan: string, scans: list<array{nik: string, waktu: Carbon, jenis: string}>}
     */
    public function bacaLive(): array
    {
        $host = trim((string) config('presensi.mesin_db_tcp_host', ''));
        if ($host === '') {
            return [
                'ok' => false,
                'pesan' => 'Mesin tidak dikonfigurasi; gunakan jalur Bridge.',
                'scans' => [],
            ];
        }

        $engine = $this->engine();
        if ($engine === 'tidak-tersedia') {
            return [
                'ok' => false,
                'pesan' => 'Tidak ada pembaca database yang tersedia. Aktifkan ekstensi pdo_odbc atau jalankan di Windows.',
                'scans' => [],
            ];
        }

        if ($engine !== 'tcp') {
            return [
                'ok' => false,
                'pesan' => 'Mode live memakai mesin ZKTeco via TCP. Aktifkan pembaca TCP (SIMANTAP_MESIN_HOST).',
                'scans' => [],
            ];
        }

        try {
            $liveTimeout = min(3, max(1, (int) config('presensi.mesin_db_live_timeout', 3)));
            $hasilTcp = $this->bacaLewatTcp(
                $host,
                (int) config('presensi.mesin_db_tcp_port', 4370),
                $liveTimeout,
            );

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

            return ['ok' => true, 'pesan' => 'OK', 'scans' => $scans];
        } catch (Throwable $e) {
            return ['ok' => false, 'pesan' => 'Gagal membaca data live dari mesin via TCP: '.$e->getMessage(), 'scans' => []];
        }
    }

    protected function bacaMentah(string $path, string $engine, ?Carbon $dari = null, ?Carbon $sampai = null): array
    {
        // Tanpa rentang waktu (null), kedua mesin pembaca membaca seluruh isi CHECKINOUT.
        return $engine === 'odbc'
            ? $this->bacaLewatOdbc($path, $dari, $sampai)
            : $this->bacaLewatPowerShell($path, $dari, $sampai);
    }

    /**
     * Pembacaan ringkas tanpa mengekspor seluruh tabel CHECKINOUT:
     * mode "statistik" (COUNT/MIN/MAX) atau "pegawai" (daftar USERINFO).
     *
     * @return array<string, mixed>
     */
    protected function bacaMentahMode(string $path, string $engine, string $mode): array
    {
        return $engine === 'odbc'
            ? $this->bacaLewatOdbc($path, null, null, $mode)
            : $this->bacaLewatPowerShell($path, null, null, $mode);
    }

    /**
     * @return array{jumlah_pegawai?: int, jumlah_scan?: int, pegawai?: list<array<string, mixed>>, scans?: list<array<string, mixed>>}
     */
    private function bacaLewatOdbc(string $path, ?Carbon $dari, ?Carbon $sampai, string $mode = 'lengkap'): array
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

        if ($mode === 'statistik') {
            $ringkasan = $pdo->query('SELECT MIN(CHECKTIME) AS MINWAKTU, MAX(CHECKTIME) AS MAKSWAKTU FROM CHECKINOUT')->fetch();
            $hasil['min'] = $ringkasan['MINWAKTU'] ?? null;
            $hasil['maks'] = $ringkasan['MAKSWAKTU'] ?? null;

            return $hasil;
        }

        foreach ($pdo->query('SELECT BADGENUMBER AS NIK, NAME AS NAMA FROM USERINFO ORDER BY NAME') as $row) {
            $hasil['pegawai'][] = [
                'nik' => $row['NIK'] ?? null,
                'nama' => $row['NAMA'] ?? null,
            ];
        }

        if ($mode === 'pegawai') {
            return $hasil;
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
    private function bacaLewatPowerShell(string $path, ?Carbon $dari, ?Carbon $sampai, string $mode = 'lengkap'): array
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

            if ($mode !== 'lengkap') {
                $perintah[] = '-Mode';
                $perintah[] = $mode;
            }

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
     * Pembungkus koneksi socket TCP agar dapat di-mock di pengujian tanpa membuka jaringan nyata.
     *
     * @return resource|false
     */
    protected function openTcpSocket(string $host, int $port, float $timeout, int &$errno, string &$errstr)
    {
        return @fsockopen($host, $port, $errno, $errstr, $timeout);
    }

    /**
     * Baca log presensi langsung dari mesin ZKTeco via TCP (port default: 4370).
     *
     * @return array{ok: bool, pesan: string, scans?: list<array<string, mixed>>}
     */
    private function bacaLewatTcp(string $host, int $port, ?int $connectTimeout = null): array
    {
        $host = trim($host);
        if ($host === '') {
            throw new RuntimeException('Mesin tidak dikonfigurasi; gunakan jalur Bridge.');
        }

        $batas = max(5, (int) config('presensi.mesin_db_timeout', 120));
        $timeoutKoneksi = $connectTimeout !== null
            ? (float) max(1, min($connectTimeout, $batas))
            : (float) min(10, $batas);
        $errno = 0;
        $errstr = '';
        $socket = $this->openTcpSocket($host, $port, $timeoutKoneksi, $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException(
                'Gagal koneksi ke mesin via TCP ('.$host.':'.$port.') : '
                .($errstr !== '' ? $errstr : 'timeout / tidak ada route ke mesin')
            );
        }

        stream_set_timeout($socket, max(5, min(60, $batas)));

        try {
            // 1. Buka sesi (CMD_CONNECT) dan autentikasi bila diminta mesin.
            $sesi = $this->bukaSesiTcp($socket);
            $replyId = $sesi['replyId'];
            $sessionId = $sesi['sessionId'];

            // 2. Hentikan sementara pembacaan sidik jari (best effort).
            $this->kirimPaketTcp($socket, self::COMMAND_DISABLE_DEVICE, $sessionId, $replyId, '');
            $replyId++;
            $this->abaikanBalasanTcp($socket);

            // 3. Minta seluruh log presensi.
            $mentah = $this->mintaLogPresensiTcp($socket, $sessionId, $replyId);

            // 4. Bersihkan buffer, aktifkan kembali mesin, lalu tutup sesi.
            //    Balasan tiga perintah terakhir bersifat opsional.
            stream_set_timeout($socket, 3);
            $this->kirimPaketTcp($socket, self::COMMAND_FREE_DATA, $sessionId, $replyId, '');
            $replyId++;
            $this->abaikanBalasanTcp($socket);

            $this->kirimPaketTcp($socket, self::COMMAND_ENABLE_DEVICE, $sessionId, $replyId, '');
            $replyId++;
            $this->abaikanBalasanTcp($socket);

            $this->kirimPaketTcp($socket, self::COMMAND_EXIT, $sessionId, $replyId, '');
            $this->abaikanBalasanTcp($socket);

            return ['ok' => true, 'pesan' => 'OK', 'scans' => $this->uraiLogPresensiTcp($mentah)];
        } finally {
            @fclose($socket);
        }
    }

    /**
     * Buka sesi TCP: CMD_CONNECT, lalu CMD_AUTH memakai communication key
     * bila mesin membalas CMD_ACK_UNAUTH. Dipakai bersama oleh pembacaan log
     * penuh dan tombol "Tes Koneksi".
     *
     * @return array{replyId: int, sessionId: int}
     */
    private function bukaSesiTcp($socket): array
    {
        $replyId = 0;
        $this->kirimPaketTcp($socket, self::COMMAND_CONNECT, 0, $replyId, '');
        $replyId++;
        $resp = $this->terimaPaketTcp($socket);
        $kode = (int) $resp['command'];
        $sessionId = (int) $resp['sessionId'];

        if ($kode === self::COMMAND_ACK_UNAUTH) {
            $this->kirimPaketTcp(
                $socket,
                self::COMMAND_AUTH,
                $sessionId,
                $replyId,
                ZkTcpProtocol::commKey($this->commKeyMesin(), $sessionId),
            );
            $replyId++;
            $resp = $this->terimaPaketTcp($socket);
            $kode = (int) $resp['command'];

            $sessionBaru = (int) $resp['sessionId'];
            if ($sessionBaru !== 0) {
                $sessionId = $sessionBaru;
            }

            if ($kode === self::COMMAND_ACK_UNAUTH) {
                throw new RuntimeException(
                    'Communication key mesin salah. Periksa menu Komunikasi pada mesin '
                    .'atau isi PRESENSI_MESIN_COMM_KEY sesuai kunci perangkat.'
                );
            }
        }

        if ($kode !== self::COMMAND_ACK_OK) {
            throw new RuntimeException('Mesin menolak sesi koneksi (kode: '.$kode.').');
        }

        return ['replyId' => $replyId, 'sessionId' => $sessionId];
    }

    /**
     * Communication key dari konfigurasi. Kunci kosong dianggap 0 karena
     * mesin dengan kunci bawaan tetap meminta autentikasi (CMD_AUTH).
     */
    private function commKeyMesin(): int
    {
        $kunci = trim((string) config('presensi.mesin_comm_key', ''));

        if ($kunci === '') {
            return 0;
        }

        if (! preg_match('/^\d{1,10}$/', $kunci)) {
            throw new RuntimeException('PRESENSI_MESIN_COMM_KEY harus berupa angka.');
        }

        return (int) $kunci;
    }

    /**
     * @return array{command: int, sessionId: int, replyId: int, data: string}
     */
    private function terimaPaketTcp($socket): array
    {
        $header = $this->bacaTepatTcp($socket, ZkTcpProtocol::UKURAN_HEADER);
        if (strlen($header) < ZkTcpProtocol::UKURAN_HEADER) {
            throw new RuntimeException('Respons dari mesin via TCP terlalu pendek (koneksi ditutup mesin).');
        }

        if (substr($header, 0, 4) !== ZkTcpProtocol::MAGIC) {
            throw new RuntimeException(
                'Header balasan mesin tidak dikenali ('.bin2hex(substr($header, 0, 4)).'). '
                .'Pastikan port 4370 memakai protokol ZKTeco standar.'
            );
        }

        $panjang = (int) unpack('V', substr($header, 4, 4))[1];
        if ($panjang < 8 || $panjang > 64 * 1024 * 1024) {
            throw new RuntimeException('Ukuran payload balasan mesin tidak wajar ('.$panjang.' byte).');
        }

        $payload = $this->bacaTepatTcp($socket, $panjang);
        if (strlen($payload) < $panjang) {
            throw new RuntimeException('Balasan mesin terpotong ('.$panjang.' byte diminta, '.strlen($payload).' byte diterima).');
        }

        return ZkTcpProtocol::urai($payload);
    }

    private function kirimPaketTcp($socket, int $command, int $sessionId, int $replyId, string $data): void
    {
        if (@fwrite($socket, ZkTcpProtocol::paket($command, $sessionId, $replyId, $data)) === false) {
            throw new RuntimeException('Gagal mengirim paket ke mesin via TCP.');
        }
    }

    /**
     * fread() pada socket bisa mengembalikan sebagian data; baca sampai utuh
     * atau timeout agar header/payload tidak terpotong.
     */
    private function bacaTepatTcp($socket, int $jumlah): string
    {
        $buffer = '';
        $batas = microtime(true) + max(5, (float) config('presensi.mesin_db_timeout', 120));

        while (strlen($buffer) < $jumlah) {
            if (microtime(true) > $batas) {
                break;
            }

            $potongan = fread($socket, $jumlah - strlen($buffer));
            if ($potongan === false || $potongan === '') {
                $meta = stream_get_meta_data($socket);
                if (! empty($meta['timed_out']) || feof($socket)) {
                    break;
                }
                usleep(5000);

                continue;
            }

            $buffer .= $potongan;
        }

        return $buffer;
    }

    private function abaikanBalasanTcp($socket): void
    {
        try {
            $this->terimaPaketTcp($socket);
        } catch (Throwable) {
            // Balasan untuk perintah best effort tidak wajib ada.
        }
    }

    /**
     * Minta log presensi. Firmware baru memakai CMD_DATA_WRRQ dengan payload
     * tetap 11 byte; firmware lama hanya mengenal CMD_ATTLOG_RRQ sehingga
     * dipakai sebagai cadangan bila permintaan pertama ditolak.
     */
    private function mintaLogPresensiTcp($socket, int $sessionId, int &$replyId): string
    {
        $payload = hex2bin('010d000000000000000000') ?: '';

        $resp = $this->kirimDanTerimaTcp($socket, self::COMMAND_DATA_WRRQ, $sessionId, $replyId, $payload);

        if ($this->balasanGagalTcp((int) $resp['command'])) {
            $resp = $this->kirimDanTerimaTcp($socket, self::COMMAND_ATTLOG_RRQ, $sessionId, $replyId, '');
        }

        return $this->bacaBalasanLogTcp($socket, $sessionId, $replyId, $resp);
    }

    /**
     * @return array{command: int, sessionId: int, replyId: int, data: string}
     */
    private function kirimDanTerimaTcp($socket, int $command, int $sessionId, int &$replyId, string $data): array
    {
        $this->kirimPaketTcp($socket, $command, $sessionId, $replyId, $data);
        $replyId++;

        return $this->terimaPaketTcp($socket);
    }

    private function balasanGagalTcp(int $command): bool
    {
        return in_array($command, [
            self::COMMAND_ACK_ERROR,
            self::COMMAND_ACK_ERROR_DATA,
            self::COMMAND_ACK_ERROR_CMD,
            self::COMMAND_ACK_UNKNOWN,
        ], true);
    }

    /**
     * @param  array{command: int, sessionId: int, replyId: int, data: string}  $resp
     */
    private function bacaBalasanLogTcp($socket, int $sessionId, int &$replyId, array $resp): string
    {
        $perintah = (int) $resp['command'];
        $data = (string) $resp['data'];

        if ($perintah === self::COMMAND_ACK_UNAUTH) {
            throw new RuntimeException('Mesin meminta communication key.');
        }

        if ($perintah === self::COMMAND_DATA || $perintah === self::COMMAND_ACK_DATA) {
            return $data;
        }

        if ($perintah === self::COMMAND_PREPARE_DATA) {
            $ukuran = strlen($data) >= 4 ? (int) unpack('V', substr($data, 0, 4))[1] : 0;
            if ($ukuran <= 0) {
                throw new RuntimeException('Mesin menyiapkan data tetapi ukurannya tidak valid.');
            }

            return $this->kumpulkanDataTcp($socket, $ukuran);
        }

        if ($perintah === self::COMMAND_ACK_OK) {
            // Sebagian firmware membalas ACK_OK tanpa data saat tidak ada log.
            if ($data === '') {
                return '';
            }

            $ukuran = $this->ukuranDariBalasanTcp($data);
            if ($ukuran <= 0) {
                throw new RuntimeException('Mesin tidak menyertakan ukuran log presensi.');
            }

            // CMD_DATA_RDY: 4 byte nol + ukuran dataset (little endian).
            $this->kirimPaketTcp($socket, self::COMMAND_DATA_RDY, $sessionId, $replyId, pack('V', 0).pack('V', $ukuran));
            $replyId++;

            return $this->kumpulkanDataTcp($socket, $ukuran);
        }

        throw new RuntimeException('Mesin menolak permintaan log presensi (kode: '.$perintah.').');
    }

    private function ukuranDariBalasanTcp(string $data): int
    {
        $kandidat = [];

        // Struktur "data stat" CMD_DATA_WRRQ: 1 byte nol + ukuran 4 byte.
        if (strlen($data) >= 5) {
            $kandidat[] = (int) unpack('V', substr($data, 1, 4))[1];
        }

        // Sebagian firmware mengirim ukuran langsung di awal data.
        if (strlen($data) >= 4) {
            $kandidat[] = (int) unpack('V', substr($data, 0, 4))[1];
        }

        foreach ($kandidat as $ukuran) {
            if ($ukuran > 0 && $ukuran <= 64 * 1024 * 1024) {
                return $ukuran;
            }
        }

        return 0;
    }

    private function kumpulkanDataTcp($socket, int $ukuran): string
    {
        $ukuran = max(1, $ukuran);
        $buffer = '';
        $batas = microtime(true) + max(10, (float) config('presensi.mesin_db_timeout', 120));

        while (strlen($buffer) < $ukuran) {
            if (microtime(true) > $batas) {
                throw new RuntimeException(
                    'Timeout menunggu data log presensi dari mesin ('.strlen($buffer).' dari '.$ukuran.' byte).'
                );
            }

            $paket = $this->terimaPaketTcp($socket);
            $perintah = (int) $paket['command'];

            if ($perintah === self::COMMAND_DATA) {
                $buffer .= (string) $paket['data'];

                continue;
            }

            if ($perintah === self::COMMAND_PREPARE_DATA) {
                continue;
            }

            if ($perintah === self::COMMAND_ACK_OK) {
                break;
            }

            if ($this->balasanGagalTcp($perintah)) {
                throw new RuntimeException('Mesin menolak pengiriman data log (kode: '.$perintah.').');
            }

            // Paket lain (mis. event realtime) diabaikan.
        }

        if ($buffer === '') {
            throw new RuntimeException('Mesin tidak mengirim data log presensi.');
        }

        return $buffer;
    }

    /**
     * Dataset log diawali 4 byte ukuran total. Coba dengan dan tanpa prefix
     * tersebut agar tetap kompatibel dengan beberapa versi firmware.
     *
     * @return list<array{nik: string, nama: string, waktu: Carbon, tipe: string}>
     */
    private function uraiLogPresensiTcp(string $mentah): array
    {
        $kandidat = strlen($mentah) > 4 ? [substr($mentah, 4), $mentah] : [$mentah];

        $data = null;
        $ukuranRecord = 0;

        foreach ($kandidat as $isi) {
            if ($isi === '') {
                continue;
            }

            $ukuran = ZkTcpProtocol::ukuranRecord($isi);
            if ($ukuran > 0) {
                $data = $isi;
                $ukuranRecord = $ukuran;

                break;
            }
        }

        if ($data === null) {
            return [];
        }

        $scans = [];
        $panjang = strlen($data);
        for ($i = 0; $i + $ukuranRecord <= $panjang; $i += $ukuranRecord) {
            $log = ZkTcpProtocol::record(substr($data, $i, $ukuranRecord), $ukuranRecord);
            if ($log === null) {
                continue;
            }

            $nik = $this->formatUserIdMesin((string) $log['userId']);

            $scans[] = [
                'nik' => $nik,
                'nama' => 'Pegawai Mesin #'.$nik,
                'waktu' => $log['waktu'],
                'tipe' => (string) $log['state'],
            ];
        }

        return $scans;
    }

    /**
     * ZKTime menyimpan No. PIN sebagai 10 digit berawalan nol, sedangkan
     * mesin mengirim user id tanpa nol di depan ("2"). Samakan formatnya
     * agar hasil tarik TCP cocok dengan users.nik hasil "Tarik NIK".
     */
    private function formatUserIdMesin(string $userId): string
    {
        $userId = trim($userId);
        $pad = (int) config('presensi.mesin_user_id_pad', 10);

        if ($pad > 0 && $userId !== '' && ctype_digit($userId) && strlen($userId) < $pad) {
            return str_pad($userId, $pad, '0', STR_PAD_LEFT);
        }

        return $userId;
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
