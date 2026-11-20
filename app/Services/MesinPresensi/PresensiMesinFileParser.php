<?php

namespace App\Services\MesinPresensi;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PresensiMesinFileParser
{
    public function __construct(
        private readonly PresensiMesinKaryawanService $karyawan,
    ) {}

    /**
     * Baca dan urai pasangan NIK + Nama karyawan dari file unggahan.
     *
     * @return list<array{nik: string, nama: string}>
     */
    public function parseKaryawan(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        return $this->parseKaryawanRows($rows);
    }

    /**
     * Urai pasangan NIK + Nama dari matriks sel mentah.
     *
     * @param  list<list<array{raw: mixed, fmt: string}>>  $rows
     * @return list<array{nik: string, nama: string}>
     */
    public function parseKaryawanRows(array $rows): array
    {
        if ($rows === []) {
            throw new InvalidArgumentException('File kosong atau tidak memiliki data.');
        }

        $headerIndex = $this->findHeaderRow($rows);
        if ($headerIndex !== null) {
            $headers = array_map(fn (array $cell) => $this->normalizeHeader($cell['fmt']), $rows[$headerIndex]);
            $nikIndex = $this->findColumn($headers, ['nik', 'no pin', 'pin']);
            $namaIndex = $this->findColumn($headers, ['nama', 'nama karyawan', 'nama pegawai', 'nama lengkap']);
            $dataRows = array_slice($rows, $headerIndex + 1);
        } else {
            [$nikIndex, $namaIndex] = $this->inferMasterColumns($rows);
            $dataRows = $rows;
        }

        if ($nikIndex === null || $namaIndex === null) {
            throw new InvalidArgumentException('Kolom NIK dan Nama tidak ditemukan. Gunakan template yang disediakan.');
        }

        $pasangan = [];
        foreach ($dataRows as $row) {
            $nik = $this->normalizeNikCell($row[$nikIndex] ?? null);
            $nama = trim((string) ($row[$namaIndex]['fmt'] ?? ''));

            if ($nik === null || $nama === '') {
                continue;
            }

            $pasangan[] = ['nik' => $nik, 'nama' => $nama];
        }

        return $pasangan;
    }

    /**
     * Baca dan urai baris log scan presensi dari file unggahan.
     *
     * @return array{scans: list<array{nik: string, waktu: Carbon, jenis: string}>, baris_diabaikan: int}
     */
    public function parseLogScans(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        return $this->parseLogRows($rows);
    }

    /**
     * Urai baris log scan presensi dari matriks sel mentah.
     *
     * @param  list<list<array{raw: mixed, fmt: string}>>  $rows
     * @return array{scans: list<array{nik: string, waktu: Carbon, jenis: string}>, baris_diabaikan: int}
     */
    public function parseLogRows(array $rows): array
    {
        if ($rows === []) {
            throw new InvalidArgumentException('File kosong atau tidak memiliki data.');
        }

        $headerIndex = $this->findHeaderRow($rows);
        if ($headerIndex === null) {
            throw new InvalidArgumentException('Header file tidak dikenali. Gunakan export mesin atau template yang disediakan.');
        }

        $headers = array_map(fn (array $cell) => $this->normalizeHeader($cell['fmt']), $rows[$headerIndex]);
        $nikIndex = $this->findColumn($headers, ['nik', 'no pin', 'pin']);
        $statusIndex = $this->findColumn($headers, ['status']);
        $dateTimeIndex = $this->findColumn($headers, ['tgl waktu', 'tanggal waktu', 'waktu lengkap', 'datetime']);
        $tanggalIndex = $this->findColumn($headers, ['tanggal', 'tgl', 'date']);
        $masukIndex = $this->findColumn($headers, ['jam masuk', 'masuk', 'check in', 'jam datang']);
        $pulangIndex = $this->findColumn($headers, ['jam pulang', 'pulang', 'check out', 'jam keluar']);

        if ($nikIndex === null) {
            throw new InvalidArgumentException('Kolom NIK/No.PIN tidak ditemukan pada file.');
        }

        $mode = null;
        if ($tanggalIndex !== null && ($masukIndex !== null || $pulangIndex !== null)) {
            $mode = 'kolom';
        } elseif ($dateTimeIndex !== null) {
            $mode = 'datetime';
        }

        if ($mode === null) {
            throw new InvalidArgumentException('Kolom tanggal/waktu tidak ditemukan. Gunakan export mesin atau template yang disediakan.');
        }

        $dataRows = array_slice($rows, $headerIndex + 1);
        $scans = [];
        $barisDiabaikan = 0;

        foreach ($dataRows as $row) {
            $nik = $this->normalizeNikCell($row[$nikIndex] ?? null);
            if ($nik === null) {
                $barisDiabaikan++;

                continue;
            }

            if ($mode === 'datetime') {
                $waktu = $this->parseDateTimeCell($row[$dateTimeIndex] ?? null);
                if ($waktu === null) {
                    $barisDiabaikan++;

                    continue;
                }
                $jenis = $this->jenisScan($statusIndex !== null ? (string) ($row[$statusIndex]['fmt'] ?? '') : '', $waktu);
                $scans[] = ['nik' => $nik, 'waktu' => $waktu, 'jenis' => $jenis];

                continue;
            }

            $tanggal = $this->parseDateCell($row[$tanggalIndex] ?? null);
            if ($tanggal === null) {
                $barisDiabaikan++;

                continue;
            }

            $adaJam = false;
            foreach (['masuk' => $masukIndex, 'pulang' => $pulangIndex] as $jenis => $index) {
                if ($index === null) {
                    continue;
                }
                $jam = $this->parseTimeCell($row[$index] ?? null);
                if ($jam === null) {
                    continue;
                }
                $adaJam = true;
                $waktu = $tanggal->copy()->setTime($jam[0], $jam[1], $jam[2]);
                $scans[] = ['nik' => $nik, 'waktu' => $waktu, 'jenis' => $jenis];
            }

            if (! $adaJam) {
                $barisDiabaikan++;
            }
        }

        if ($scans === []) {
            throw new InvalidArgumentException('Tidak ada baris presensi yang valid. Periksa format tanggal dan jam pada file.');
        }

        return [
            'scans' => $scans,
            'baris_diabaikan' => $barisDiabaikan,
        ];
    }

    /**
     * @return list<list<array{raw: mixed, fmt: string}>>
     */
    public function readRows(UploadedFile $file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $reader = match ($ext) {
            'xlsx' => IOFactory::createReader('Xlsx'),
            'xls' => IOFactory::createReader('Xls'),
            default => $this->makeCsvReader($file),
        };
        $reader->setReadDataOnly(false);

        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = min($sheet->getHighestDataRow(), 20000);
        $highestColumn = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 40);

        $rows = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $cells = [];
            $adaIsi = false;

            for ($c = 1; $c <= $highestColumn; $c++) {
                $cell = $sheet->getCell([$c, $r]);
                $raw = $cell->getValue();
                $fmt = trim((string) $cell->getFormattedValue());

                if ($raw !== null && trim((string) $raw) !== '') {
                    $adaIsi = true;
                }

                $cells[] = ['raw' => $raw, 'fmt' => $fmt];
            }

            if ($adaIsi) {
                $rows[] = $cells;
            }
        }

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function makeCsvReader(UploadedFile $file): CsvReader
    {
        $reader = new CsvReader;
        $reader->setInputEncoding('UTF-8');
        $reader->setEnclosure('"');

        $sample = (string) @file_get_contents($file->getRealPath(), false, null, 0, 4096);
        $delimiter = ',';
        $tertinggi = substr_count($sample, ',');
        foreach ([',', ';', "\t", '|'] as $kandidat) {
            $jumlah = substr_count($sample, $kandidat);
            if ($jumlah > $tertinggi) {
                $tertinggi = $jumlah;
                $delimiter = $kandidat;
            }
        }

        $reader->setDelimiter($delimiter);

        return $reader;
    }

    /**
     * @param  list<list<array{raw: mixed, fmt: string}>>  $rows
     */
    public function findHeaderRow(array $rows): ?int
    {
        $batas = min(count($rows), 20);

        for ($i = 0; $i < $batas; $i++) {
            $headers = array_map(fn (array $cell) => $this->normalizeHeader($cell['fmt']), $rows[$i]);

            $skor = 0;
            if ($this->findColumn($headers, ['nik', 'no pin', 'pin']) !== null) {
                $skor += 2;
            }
            if ($this->findColumn($headers, ['nama', 'nama karyawan', 'nama pegawai', 'nama lengkap']) !== null) {
                $skor += 2;
            }
            if ($this->findColumn($headers, ['tgl waktu', 'tanggal waktu', 'waktu lengkap', 'datetime', 'tanggal', 'tgl']) !== null) {
                $skor += 2;
            }
            if ($this->findColumn($headers, ['status']) !== null) {
                $skor += 1;
            }
            if ($this->findColumn($headers, ['jam masuk', 'jam pulang']) !== null) {
                $skor += 2;
            }

            if ($skor >= 3) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $aliases
     */
    private function findColumn(array $headers, array $aliases): ?int
    {
        foreach ($headers as $index => $header) {
            if ($header !== '' && in_array($header, $aliases, true)) {
                return $index;
            }
        }

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            foreach ($aliases as $alias) {
                if (mb_strlen($alias) >= 4 && str_contains($header, $alias)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        $value = str_replace("\u{FEFF}", '', $value);
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    /**
     * Deteksi kolom untuk file tanpa baris header (mis. export daftar guru).
     *
     * @param  list<list<array{raw: mixed, fmt: string}>>  $rows
     * @return array{0: int|null, 1: int|null}
     */
    private function inferMasterColumns(array $rows): array
    {
        $jumlahKolom = 0;
        foreach ($rows as $row) {
            $jumlahKolom = max($jumlahKolom, count($row));
        }
        if ($jumlahKolom === 0) {
            return [null, null];
        }

        $skorNik = array_fill(0, $jumlahKolom, 0);
        $skorNama = array_fill(0, $jumlahKolom, 0);

        foreach (array_slice($rows, 0, 10) as $row) {
            foreach ($row as $index => $cell) {
                $fmt = trim((string) ($cell['fmt'] ?? ''));
                $digit = preg_replace('/\D+/', '', $fmt) ?? '';

                if (strlen($digit) >= 15 && strlen($digit) <= 20 && strlen($fmt) <= 30) {
                    $skorNik[$index]++;
                }
                if (preg_match('/[A-Za-z]{3,}/', $fmt) === 1) {
                    $skorNama[$index]++;
                }
            }
        }

        $nikIndex = $this->argMax($skorNik);
        if ($nikIndex !== null && $skorNik[$nikIndex] === 0) {
            $nikIndex = null;
        }

        $namaIndex = null;
        $nilaiTerbaik = 0;
        foreach ($skorNama as $index => $skor) {
            if ($index === $nikIndex) {
                continue;
            }
            if ($skor > $nilaiTerbaik) {
                $nilaiTerbaik = $skor;
                $namaIndex = $index;
            }
        }

        return [$nikIndex, $namaIndex];
    }

    /**
     * @param  list<int>  $values
     */
    private function argMax(array $values): ?int
    {
        $best = null;
        $bestValue = -1;

        foreach ($values as $index => $value) {
            if ($value > $bestValue) {
                $bestValue = $value;
                $best = $index;
            }
        }

        return $best;
    }

    private function normalizeNikCell(?array $cell): ?string
    {
        if ($cell === null) {
            return null;
        }

        $raw = $cell['raw'] ?? null;
        $fmt = trim((string) ($cell['fmt'] ?? ''));
        $value = $fmt;

        if (is_numeric($raw)) {
            $angka = (float) $raw;
            if ($fmt === '' || stripos($fmt, 'e+') !== false || stripos($fmt, 'e-') !== false) {
                $value = number_format($angka, 0, '', '');
            }
        }

        return $this->karyawan->normalizeNikValue($value);
    }

    public function parseDateTimeCell(?array $cell): ?Carbon
    {
        if ($cell === null) {
            return null;
        }

        $raw = $cell['raw'] ?? null;
        $fmt = trim((string) ($cell['fmt'] ?? ''));

        if (is_numeric($raw)) {
            $angka = (float) $raw;
            if ($angka > 20000 && $angka < 100000) {
                $waktu = $this->dariSerialExcel($angka);
                if ($waktu !== null) {
                    return $waktu;
                }
            }
        }

        if (preg_match('/^\d{5}(\.\d+)?$/', $fmt) === 1) {
            $waktu = $this->dariSerialExcel((float) $fmt);
            if ($waktu !== null) {
                return $waktu;
            }
        }

        $formatTanggal = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y/m/d H:i:s',
            'Y/m/d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'YmdHis',
            'Ymd',
        ];

        foreach ($formatTanggal as $format) {
            try {
                $waktu = Carbon::createFromFormat($format, $fmt, config('app.timezone'));
                if ($waktu !== false) {
                    return $waktu;
                }
            } catch (\Throwable) {
                // Coba format berikutnya.
            }
        }

        try {
            return Carbon::parse($fmt, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    public function parseDateCell(?array $cell): ?Carbon
    {
        return $this->parseDateTimeCell($cell)?->copy()->startOfDay();
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    public function parseTimeCell(?array $cell): ?array
    {
        if ($cell === null) {
            return null;
        }

        $raw = $cell['raw'] ?? null;
        $fmt = trim((string) ($cell['fmt'] ?? ''));

        if (is_numeric($raw)) {
            $angka = (float) $raw;
            if ($angka >= 0 && $angka < 1) {
                $detik = (int) round($angka * 86400);

                return [intdiv($detik, 3600) % 24, intdiv($detik, 60) % 60, $detik % 60];
            }
        }

        if (preg_match('/(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?/', $fmt, $cocok) === 1) {
            $jam = (int) $cocok[1];
            $menit = (int) $cocok[2];
            $detik = (int) ($cocok[3] ?? 0);

            if ($jam < 24 && $menit < 60 && $detik < 60) {
                return [$jam, $menit, $detik];
            }
        }

        if (preg_match('/^\d{3,4}$/', $fmt) === 1) {
            $angka = str_pad($fmt, 4, '0', STR_PAD_LEFT);
            $jam = (int) substr($angka, 0, 2);
            $menit = (int) substr($angka, 2, 2);

            if ($jam < 24 && $menit < 60) {
                return [$jam, $menit, 0];
            }
        }

        return null;
    }

    private function dariSerialExcel(float $serial): ?Carbon
    {
        try {
            $waktu = ExcelDate::excelToDateTimeObject($serial, config('app.timezone'));

            return Carbon::instance($waktu);
        } catch (\Throwable) {
            return null;
        }
    }

    public function jenisScan(string $status, Carbon $waktu): string
    {
        $status = mb_strtolower(trim($status));

        if ($status !== '') {
            if (str_contains($status, 'keluar') || str_contains($status, 'pulang') || str_contains($status, 'out')) {
                return 'pulang';
            }
            if (str_contains($status, 'masuk') || str_contains($status, 'datang') || str_contains($status, 'in')) {
                return 'masuk';
            }
        }

        return $waktu->hour >= 12 ? 'pulang' : 'masuk';
    }
}
