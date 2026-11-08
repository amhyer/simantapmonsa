<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\PresensiMesinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PresensiMesinImportController extends Controller
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    private const EKSTENSI_DIIZINKAN = ['xls', 'xlsx', 'csv', 'txt'];

    private const KUNCI_SINKRON_TERAKHIR = 'presensi.mesin.terakhir_tarik';

    private const MAKS_HARI_TARIK = 31;

    /**
     * Potongan gelar yang boleh muncul di belakang nama tanpa membuat nama dianggap berbeda.
     *
     * @var list<string>
     */
    private const TOKEN_GELAR = [
        's', 'm', 'a', 'pd', 'si', 'sos', 'kom', 'ag', 'hi', 'ip', 'pt', 'ab',
        'iq', 'fil', 'psi', 'keb', 'kes', 'farm', 'pod', 'kp', 'kep', 'th',
        'mm', 'se', 'sh', 'ma', 'mba', 'phd', 'apt', 'drs', 'dra', 'prof',
        'st', 'mt', 'ssi', 'sst', 'spt', 'skm', 'sip', 'shi', 'sag',
    ];

    public function index(AttendanceDatabaseReader $reader)
    {
        $jumlahPegawai = User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)->where('aktif', true)->count();
        $sudahAdaNik = User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)
            ->whereNotNull('nik')
            ->where('nik', '<>', '')
            ->count();

        $directDisabled = \App\Modules\Tenant\Contexts\TenantContext::check();
        $koneksi = $directDisabled ? ['ok' => false, 'pesan' => 'Tarik langsung belum mendukung isolasi per sekolah.', 'path' => null, 'engine' => null] : $reader->status();
        $terakhirTarik = $directDisabled ? null : Cache::get(self::KUNCI_SINKRON_TERAKHIR);
        $mesinEngineTerpilih = $directDisabled ? (string) config('presensi.mesin_db_engine', 'auto') : $reader->engineTerpilih();

        return view('admin.presensi.mesin-import', compact('jumlahPegawai', 'sudahAdaNik', 'koneksi', 'terakhirTarik', 'directDisabled', 'mesinEngineTerpilih'));
    }

    public function templateKaryawan()
    {
        return $this->csvDownload('template-import-karyawan-mesin.csv', ['nik', 'nama'], [
            ['PIN001', 'Contoh Pegawai A'],
            ['PIN002', 'Contoh Pegawai B'],
        ]);
    }

    public function templateLog()
    {
        return $this->csvDownload('template-import-log-mesin.csv', ['nik', 'tanggal', 'jam_masuk', 'jam_pulang'], [
            ['PIN001', now()->toDateString(), '07:05', '14:10'],
            ['PIN002', now()->toDateString(), '07:20', '14:05'],
        ]);
    }

    public function importKaryawan(Request $request): RedirectResponse
    {
        if ($response = $this->validateUpload($request)) {
            return $response;
        }

        try {
            $rows = $this->readRows($request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'File tidak dapat dibaca: '.$e->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'File kosong atau tidak memiliki data.']);
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
            return back()->withErrors(['file' => 'Kolom NIK dan Nama tidak ditemukan. Gunakan template yang disediakan.']);
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

        $hasil = $this->simpanNikKaryawan($pasangan);

        AktivitasService::log(
            'tambah',
            'Import NIK karyawan dari mesin presensi',
            "Import data karyawan: {$hasil['diperbarui']} NIK diperbarui, {$hasil['tetap']} sudah sesuai, {$hasil['tidak_cocok']} nama tidak cocok, {$hasil['ambigu']} ambigu, {$hasil['bentrok']} bentrok.",
            'users',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pesanKaryawan($hasil));
    }

    /**
     * Ambil daftar pegawai (NIK/No.PIN + nama) langsung dari database mesin
     * lalu cocokkan dengan akun pegawai SIMANTAP berdasarkan nama.
     */
    public function tarikKaryawan(AttendanceDatabaseReader $reader): RedirectResponse
    {
        try {
            $pegawai = $reader->pegawai();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['mesin' => 'Gagal membaca daftar pegawai dari database mesin: '.$e->getMessage()]);
        }

        if ($pegawai === []) {
            return back()->with('success', 'Database mesin berhasil dibaca, tetapi belum ada data pegawai di dalamnya.');
        }

        $hasil = $this->simpanNikKaryawan($pegawai);

        AktivitasService::log(
            'tambah',
            'Tarik NIK karyawan dari database mesin',
            "Tarik data karyawan: {$hasil['diperbarui']} NIK diperbarui, {$hasil['tetap']} sudah sesuai, {$hasil['tidak_cocok']} nama tidak cocok, {$hasil['ambigu']} ambigu, {$hasil['bentrok']} bentrok.",
            'users',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pesanKaryawan($hasil));
    }

    /**
     * @param  list<array{nik: string, nama: string}>  $pasangan
     * @return array{baris: int, diperbarui: int, tetap: int, tidak_cocok: int, ambigu: int, bentrok: int, contoh_tidak_cocok: list<string>}
     */
    private function simpanNikKaryawan(array $pasangan): array
    {
        $users = User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)->get();

        $nikTerpakai = [];
        foreach ($users as $user) {
            $nikLama = $this->normalizeNikValue($user->nik);
            if ($nikLama !== null) {
                $nikTerpakai[$nikLama] = $user->id;
            }
        }

        $hasil = [
            'baris' => 0,
            'diperbarui' => 0,
            'tetap' => 0,
            'tidak_cocok' => 0,
            'ambigu' => 0,
            'bentrok' => 0,
            'contoh_tidak_cocok' => [],
        ];

        DB::transaction(function () use ($pasangan, $users, &$nikTerpakai, &$hasil) {
            foreach ($pasangan as $item) {
                $nik = $this->normalizeNikValue($item['nik']);
                $nama = trim((string) $item['nama']);

                if ($nik === null || $nama === '') {
                    continue;
                }

                $hasil['baris']++;

                $kandidat = $this->matchUsersByName($nama, $users);
                if ($kandidat === []) {
                    $hasil['tidak_cocok']++;
                    if (count($hasil['contoh_tidak_cocok']) < 8) {
                        $hasil['contoh_tidak_cocok'][] = $nama;
                    }

                    continue;
                }
                if (count($kandidat) > 1) {
                    $hasil['ambigu']++;

                    continue;
                }

                /** @var User $user */
                $user = $kandidat[0];
                $nikLama = $this->normalizeNikValue($user->nik);

                if ($nikLama !== null && $nikLama !== $nik) {
                    $hasil['bentrok']++;

                    continue;
                }
                if ($nikLama === $nik) {
                    $hasil['tetap']++;

                    continue;
                }

                $pemilik = $nikTerpakai[$nik] ?? null;
                if ($pemilik !== null && $pemilik !== $user->id) {
                    $hasil['bentrok']++;

                    continue;
                }

                $user->nik = $nik;
                $user->save();

                $nikTerpakai[$nik] = $user->id;
                $hasil['diperbarui']++;
            }
        });

        return $hasil;
    }

    /**
     * @param  array{diperbarui: int, tetap: int, tidak_cocok: int, ambigu: int, bentrok: int, contoh_tidak_cocok?: list<string>}  $hasil
     */
    private function pesanKaryawan(array $hasil): string
    {
        $pesan = "Data karyawan: {$hasil['diperbarui']} NIK diperbarui, {$hasil['tetap']} sudah sesuai, "
            ."{$hasil['tidak_cocok']} nama tidak cocok, {$hasil['ambigu']} ambigu, {$hasil['bentrok']} bentrok.";

        $contoh = (array) ($hasil['contoh_tidak_cocok'] ?? []);
        if ($contoh !== []) {
            $pesan .= ' Contoh nama tidak cocok: '.implode(', ', $contoh).'.';
        }

        return $pesan;
    }

    public function importLog(Request $request, PresensiMesinService $presensi): RedirectResponse
    {
        if ($response = $this->validateUpload($request)) {
            return $response;
        }

        try {
            $rows = $this->readRows($request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'File tidak dapat dibaca: '.$e->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'File kosong atau tidak memiliki data.']);
        }

        $headerIndex = $this->findHeaderRow($rows);
        if ($headerIndex === null) {
            return back()->withErrors(['file' => 'Header file tidak dikenali. Gunakan export mesin atau template yang disediakan.']);
        }

        $headers = array_map(fn (array $cell) => $this->normalizeHeader($cell['fmt']), $rows[$headerIndex]);
        $nikIndex = $this->findColumn($headers, ['nik', 'no pin', 'pin']);
        $statusIndex = $this->findColumn($headers, ['status']);
        $dateTimeIndex = $this->findColumn($headers, ['tgl waktu', 'tanggal waktu', 'waktu lengkap', 'datetime']);
        $tanggalIndex = $this->findColumn($headers, ['tanggal', 'tgl', 'date']);
        $masukIndex = $this->findColumn($headers, ['jam masuk', 'masuk', 'check in', 'jam datang']);
        $pulangIndex = $this->findColumn($headers, ['jam pulang', 'pulang', 'check out', 'jam keluar']);

        if ($nikIndex === null) {
            return back()->withErrors(['file' => 'Kolom NIK/No.PIN tidak ditemukan pada file.']);
        }

        $mode = null;
        if ($tanggalIndex !== null && ($masukIndex !== null || $pulangIndex !== null)) {
            $mode = 'kolom';
        } elseif ($dateTimeIndex !== null) {
            $mode = 'datetime';
        }

        if ($mode === null) {
            return back()->withErrors(['file' => 'Kolom tanggal/waktu tidak ditemukan. Gunakan export mesin atau template yang disediakan.']);
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
            return back()->withErrors(['file' => 'Tidak ada baris presensi yang valid. Periksa format tanggal dan jam pada file.']);
        }

        $hasil = $presensi->importScans($scans, auth()->id());
        $hasil['baris_diabaikan'] = $barisDiabaikan;

        $this->catatPerubahanKehadiran($hasil);

        AktivitasService::log(
            'tambah',
            'Import log presensi mesin',
            "Import log mesin: {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, {$hasil['tetap']} tetap, {$hasil['dilindungi']} dilindungi, {$hasil['nik_tidak_dikenal']} NIK tidak dikenal.",
            'presensi_pegawai',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pesanHasil($hasil, 'Import log mesin selesai'));
    }

    /**
     * Tarik langsung dari database Attendance Management tanpa unggah file.
     */
    public function tarik(Request $request, AttendanceDatabaseReader $reader, PresensiMesinService $presensi): RedirectResponse
    {
        $data = $request->validate([
            'dari' => ['required', 'date'],
            'sampai' => ['required', 'date', 'after_or_equal:dari'],
        ]);

        $dari = Carbon::parse($data['dari'], config('app.timezone'))->startOfDay();
        $sampai = Carbon::parse($data['sampai'], config('app.timezone'))->startOfDay();

        if ((int) $dari->diffInDays($sampai) > self::MAKS_HARI_TARIK - 1) {
            return back()
                ->withErrors(['dari' => 'Rentang tarik data maksimal '.self::MAKS_HARI_TARIK.' hari agar proses tetap cepat.'])
                ->withInput();
        }

        try {
            $scans = $reader->baca($dari, $sampai);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors(['mesin' => 'Gagal membaca database mesin: '.$e->getMessage()])
                ->withInput();
        }

        if ($scans === []) {
            Cache::forever(self::KUNCI_SINKRON_TERAKHIR, now()->toDateTimeString());

            return back()->with(
                'success',
                'Database mesin berhasil dibaca, tetapi tidak ada scan pada '.$dari->format('d/m/Y').' – '.$sampai->format('d/m/Y').'.',
            );
        }

        $hasil = $presensi->importScans($scans, auth()->id());
        $hasil['baris_diabaikan'] = 0;

        $this->catatPerubahanKehadiran($hasil);

        Cache::forever(self::KUNCI_SINKRON_TERAKHIR, now()->toDateTimeString());

        AktivitasService::log(
            'tambah',
            'Tarik presensi dari database mesin',
            "Tarik data mesin {$dari->toDateString()} s/d {$sampai->toDateString()}: {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, {$hasil['tetap']} tetap, {$hasil['dilindungi']} dilindungi, {$hasil['nik_tidak_dikenal']} NIK tidak dikenal.",
            'presensi_pegawai',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pesanHasil(
            $hasil,
            'Tarik data mesin '.$dari->format('d/m/Y').' – '.$sampai->format('d/m/Y').' selesai',
        ));
    }

    public function tarikSemua(AttendanceDatabaseReader $reader, PresensiMesinService $presensi): RedirectResponse
    {
        try {
            $rentang = $reader->rentang();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['mesin' => 'Gagal membaca database mesin: '.$e->getMessage()]);
        }

        $dari = $rentang['min'];
        $sampai = $rentang['maks'];

        if ($dari === null || $sampai === null) {
            Cache::forever(self::KUNCI_SINKRON_TERAKHIR, now()->toDateTimeString());

            return back()->with('success', 'Database mesin berhasil dibaca, tetapi tidak ada scan pada database.');
        }

        $hasil = [
            'scans' => 0,
            'dibuat' => 0,
            'diperbarui' => 0,
            'tetap' => 0,
            'dilindungi' => 0,
            'nik_tidak_dikenal' => 0,
            'contoh_nik_tidak_dikenal' => [],
            'baris_diabaikan' => 0,
        ];

        $mulai = $dari->copy()->startOfDay();
        $batasAkhir = $sampai->copy()->startOfDay();

        while ($mulai->lte($batasAkhir)) {
            $batasChunk = $mulai->copy()->addDays(self::MAKS_HARI_TARIK - 1);
            $sampaiChunk = $batasChunk->lt($batasAkhir) ? $batasChunk : $batasAkhir;

            try {
                $scans = $reader->baca($mulai, $sampaiChunk);
            } catch (\Throwable $e) {
                report($e);

                return back()->withErrors(['mesin' => 'Gagal membaca database mesin: '.$e->getMessage()]);
            }

            $hasil['scans'] += count($scans);

            if ($scans !== []) {
                $r = $presensi->importScans($scans, auth()->id());
                $this->catatPerubahanKehadiran($r);
                $hasil['dibuat'] += $r['dibuat'];
                $hasil['diperbarui'] += $r['diperbarui'];
                $hasil['tetap'] += $r['tetap'];
                $hasil['dilindungi'] += $r['dilindungi'];
                $hasil['nik_tidak_dikenal'] += $r['nik_tidak_dikenal'];
                $hasil['contoh_nik_tidak_dikenal'] = array_values(array_unique(array_merge(
                    (array) $hasil['contoh_nik_tidak_dikenal'],
                    (array) $r['contoh_nik_tidak_dikenal'],
                )));
            }

            if ($sampaiChunk->eq($batasAkhir)) {
                break;
            }

            $mulai = $sampaiChunk->copy()->addDay();
        }

        Cache::forever(self::KUNCI_SINKRON_TERAKHIR, now()->toDateTimeString());

        AktivitasService::log(
            'tambah',
            'Tarik presensi dari database mesin',
            "Tarik data mesin {$dari->toDateString()} s/d {$sampai->toDateString()}: {$hasil['scans']} scan, {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, {$hasil['tetap']} tetap, {$hasil['dilindungi']} dilindungi, {$hasil['nik_tidak_dikenal']} NIK tidak dikenal.",
            'presensi_pegawai',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pesanHasil($hasil, 'Tarik semua data mesin selesai'));
    }

    /**
     * Cek apakah database Attendance Management bisa dibaca dari server ini.
     */
    public function tesKoneksi(AttendanceDatabaseReader $reader): JsonResponse
    {
        $status = $reader->status();

        if ($status['ok'] ?? false) {
            try {
                $rentang = $reader->rentang();
                if ($rentang['maks'] !== null) {
                    $status['terakhir_scan'] = $rentang['maks']->toDateTimeString();
                }
            } catch (\Throwable) {
                // Lewati informasi rentang waktu bila pembacaan gagal.
            }
        }

        return response()->json($status);
    }

    /**
     * Simpan pengaturan pembacaan mesin (engine) dari halaman admin.
     * Nilai disimpan di tabel sekolah_settings (kolom pengaturan JSON).
     */
    public function updatePengaturan(Request $request): RedirectResponse
    {
        $engine = strtolower(trim((string) $request->input('mesin_engine', '')));

        if (! in_array($engine, ['auto', 'tcp', 'powershell', 'odbc'], true)) {
            return back()->withErrors(['mesin_engine' => 'Pembaca mesin tidak valid. Pilih: Auto, TCP, Database (PowerShell), atau ODBC.'])->withInput();
        }

        try {
            $sekolah = \App\Models\SekolahSettings::query()->first();
            $pengaturan = is_array($sekolah?->pengaturan ?? null) ? $sekolah->pengaturan : [];
            $pengaturan['presensi_mesin_engine'] = $engine;

            if ($sekolah) {
                $sekolah->update(['pengaturan' => $pengaturan]);
            } else {
                \App\Models\SekolahSettings::create(['pengaturan' => $pengaturan]);
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['mesin' => 'Gagal menyimpan pengaturan mesin: '.$e->getMessage()])->withInput();
        }

        return back()->with('success', 'Pengaturan pembacaan mesin berhasil disimpan (mesin_engine: '.$engine.').');
    }

    /**
     * Mencatat perubahan kehadiran (mis. terdeteksi terlambat) ke log aktivitas
     * sebagai notifikasi internal setelah proses import/tarik selesai.
     *
     * @param  array<string, mixed>  $hasil
     */
    private function catatPerubahanKehadiran(array $hasil): void
    {
        if (empty($hasil['perubahan'])) {
            return;
        }

        AktivitasService::log(
            'kehadiran',
            'Perubahan kehadiran dari mesin fingerprint',
            'Terdeteksi perubahan kehadiran (mis. terlambat): '.count($hasil['perubahan']).' kejadian.',
            'presensi_pegawai',
            null,
            null,
            $hasil['perubahan'],
        );
    }

    /**
     * @param  array<string, mixed>  $hasil
     */
    private function pesanHasil(array $hasil, string $awalan): string
    {
        $pesan = "{$awalan}: {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, "
            ."{$hasil['tetap']} tidak berubah, {$hasil['dilindungi']} dilindungi (koreksi manual/izin/sakit), "
            ."{$hasil['nik_tidak_dikenal']} NIK tidak dikenal";

        if (($hasil['baris_diabaikan'] ?? 0) > 0) {
            $pesan .= ", {$hasil['baris_diabaikan']} baris diabaikan";
        }

        $pesan .= '.';

        $contoh = (array) ($hasil['contoh_nik_tidak_dikenal'] ?? []);
        if ($contoh !== []) {
            $pesan .= ' Contoh NIK tidak dikenal: '.implode(', ', $contoh).'.';
        }

        return $pesan;
    }

    private function validateUpload(Request $request): ?RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $ext = strtolower((string) $request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, self::EKSTENSI_DIIZINKAN, true)) {
            return back()->withErrors(['file' => 'Format file harus XLS, XLSX, CSV, atau TXT.']);
        }

        return null;
    }

    /**
     * @return list<list<array{raw: mixed, fmt: string}>>
     */
    private function readRows(UploadedFile $file): array
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
    private function findHeaderRow(array $rows): ?int
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

    /**
     * @return list<User>
     */
    private function matchUsersByName(string $nama, $users): array
    {
        $target = $this->nameTokens($nama);
        if ($target === []) {
            return [];
        }

        $exact = [];
        $variant = [];

        foreach ($users as $user) {
            $tokens = $this->nameTokens((string) $user->nama_lengkap);
            if ($tokens === []) {
                continue;
            }

            if ($tokens === $target) {
                $exact[] = $user;
            } elseif ($this->isTitleVariant($tokens, $target)) {
                $variant[] = $user;
            }
        }

        return $exact !== [] ? $exact : $variant;
    }

    /**
     * @return list<string>
     */
    private function nameTokens(string $nama): array
    {
        $nama = str_replace("\u{FEFF}", '', $nama);
        $nama = mb_strtolower(trim($nama));

        $potongan = preg_split('/,/', $nama);
        if (is_array($potongan) && isset($potongan[0])) {
            $nama = $potongan[0];
        }

        $nama = preg_replace('/[^a-z0-9]+/u', ' ', $nama) ?? '';
        $nama = preg_replace('/\s+/', ' ', $nama) ?? '';
        $nama = trim($nama);

        return $nama === '' ? [] : explode(' ', $nama);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function isTitleVariant(array $a, array $b): bool
    {
        $pendek = count($a) <= count($b) ? $a : $b;
        $panjang = count($a) <= count($b) ? $b : $a;

        if ($pendek === [] || count($panjang) <= count($pendek)) {
            return false;
        }

        if (array_slice($panjang, 0, count($pendek)) !== $pendek) {
            return false;
        }

        foreach (array_slice($panjang, count($pendek)) as $token) {
            if (mb_strlen($token) > 2 && ! in_array($token, self::TOKEN_GELAR, true)) {
                return false;
            }
        }

        return true;
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

        return $this->normalizeNikValue($value);
    }

    private function normalizeNikValue($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9A-Za-z]+/', '', $value) ?? '';

        return $value === '' ? null : $value;
    }

    private function parseDateTimeCell(?array $cell): ?Carbon
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

    private function parseDateCell(?array $cell): ?Carbon
    {
        return $this->parseDateTimeCell($cell)?->copy()->startOfDay();
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseTimeCell(?array $cell): ?array
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

    private function jenisScan(string $status, Carbon $waktu): string
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

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    private function csvDownload(string $filename, array $header, array $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
