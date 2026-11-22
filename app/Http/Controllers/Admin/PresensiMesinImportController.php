<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportPresensiMesinRequest;
use App\Jobs\TarikSemuaPresensiJob;
use App\Models\PresensiMesinSyncLog;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\MesinPresensi\PresensiMesinFileParser;
use App\Services\MesinPresensi\PresensiMesinKaryawanService;
use App\Services\MesinPresensi\PresensiMesinPullService;
use App\Services\PresensiMesinService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresensiMesinImportController extends Controller
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    public function __construct(
        private readonly PresensiMesinKaryawanService $karyawan,
        private readonly PresensiMesinFileParser $parser,
        private readonly PresensiMesinPullService $pull,
    ) {}

    public function index(AttendanceDatabaseReader $reader): View
    {
        $jumlahPegawai = User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)->where('aktif', true)->count();
        $sudahAdaNik = User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)
            ->whereNotNull('nik')
            ->where('nik', '<>', '')
            ->count();

        $directDisabled = $this->pull->tarikLangsungDinonaktifkan();
        $koneksi = $directDisabled
            ? ['ok' => false, 'pesan' => 'Tarik langsung belum mendukung isolasi per sekolah.', 'path' => null, 'engine' => null]
            : $reader->status();
        $terakhirTarik = $this->pull->terakhirTarik();
        $mesinEngineTerpilih = $directDisabled
            ? (string) config('presensi.mesin_db_engine', 'auto')
            : $reader->engineTerpilih();

        return view('admin.presensi.mesin-import', compact('jumlahPegawai', 'sudahAdaNik', 'koneksi', 'terakhirTarik', 'directDisabled', 'mesinEngineTerpilih'));
    }

    /**
     * Dashboard riwayat sinkron presensi mesin via bridge (hasil POST
     * /api/presensi/mesin/sinkron dari PC sekolah ke website hosting).
     */
    public function sinkron(Request $request, AttendanceDatabaseReader $reader): View
    {
        $logs = PresensiMesinSyncLog::with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
        $terakhir = PresensiMesinSyncLog::orderByDesc('created_at')->orderByDesc('id')->first();
        $directDisabled = $this->pull->tarikLangsungDinonaktifkan();
        $terakhirTarik = $this->pull->terakhirTarik();

        $koneksi = $directDisabled
            ? ['ok' => false, 'pesan' => 'Tarik langsung belum mendukung isolasi per sekolah.', 'path' => null, 'engine' => null]
            : $reader->status();

        return view('admin.presensi.mesin-sinkron', [
            'logs' => $logs,
            'terakhir' => $terakhir,
            'terakhirTarik' => $terakhirTarik,
            'directDisabled' => $directDisabled,
            'koneksi' => $koneksi,
        ]);
    }

    public function templateKaryawan(): StreamedResponse
    {
        return $this->csvDownload('template-import-karyawan-mesin.csv', ['nik', 'nama'], [
            ['PIN001', 'Contoh Pegawai A'],
            ['PIN002', 'Contoh Pegawai B'],
        ]);
    }

    public function templateLog(): StreamedResponse
    {
        return $this->csvDownload('template-import-log-mesin.csv', ['nik', 'tanggal', 'jam_masuk', 'jam_pulang'], [
            ['PIN001', now()->toDateString(), '07:05', '14:10'],
            ['PIN002', now()->toDateString(), '07:20', '14:05'],
        ]);
    }

    public function importKaryawan(ImportPresensiMesinRequest $request): RedirectResponse
    {
        try {
            $pasangan = $this->parser->parseKaryawan($request->file('file'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'File tidak dapat dibaca: '.$e->getMessage()]);
        }

        $hasil = $this->karyawan->simpanNikKaryawan($pasangan);

        AktivitasService::log(
            'tambah',
            'Import NIK karyawan dari mesin presensi',
            "Import data karyawan: {$hasil['diperbarui']} NIK diperbarui, {$hasil['tetap']} sudah sesuai, {$hasil['tidak_cocok']} nama tidak cocok, {$hasil['ambigu']} ambigu, {$hasil['bentrok']} bentrok.",
            'users',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pull->pesanKaryawan($hasil));
    }

    /**
     * Ambil daftar pegawai (NIK/No.PIN + nama) langsung dari database mesin
     * lalu cocokkan dengan akun pegawai SIMANTAP berdasarkan nama.
     */
    public function tarikKaryawan(AttendanceDatabaseReader $reader): RedirectResponse
    {
        // Membaca USERINFO lewat PowerShell bisa lambat pada peluncuran pertama.
        @set_time_limit(300);
        @ignore_user_abort(true);

        try {
            $pegawai = $reader->pegawai();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['mesin' => 'Gagal membaca daftar pegawai dari database mesin: '.$e->getMessage()]);
        }

        if ($pegawai === []) {
            return back()->with('success', 'Database mesin berhasil dibaca, tetapi belum ada data pegawai di dalamnya.');
        }

        $hasil = $this->karyawan->simpanNikKaryawan($pegawai);

        AktivitasService::log(
            'tambah',
            'Tarik NIK karyawan dari database mesin',
            "Tarik data karyawan: {$hasil['diperbarui']} NIK diperbarui, {$hasil['tetap']} sudah sesuai, {$hasil['tidak_cocok']} nama tidak cocok, {$hasil['ambigu']} ambigu, {$hasil['bentrok']} bentrok.",
            'users',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pull->pesanKaryawan($hasil));
    }

    public function importLog(ImportPresensiMesinRequest $request, PresensiMesinService $presensi): RedirectResponse
    {
        try {
            $parsed = $this->parser->parseLogScans($request->file('file'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'File tidak dapat dibaca: '.$e->getMessage()]);
        }

        $hasil = $presensi->importScans($parsed['scans'], auth()->id());
        $hasil['baris_diabaikan'] = $parsed['baris_diabaikan'];

        $this->pull->catatPerubahanKehadiran($hasil);

        AktivitasService::log(
            'tambah',
            'Import log presensi mesin',
            "Import log mesin: {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, {$hasil['tetap']} tetap, {$hasil['dilindungi']} dilindungi, {$hasil['nik_tidak_dikenal']} NIK tidak dikenal.",
            'presensi_pegawai',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pull->pesanHasil($hasil, 'Import log mesin selesai'));
    }

    /**
     * Tarik langsung dari database Attendance Management tanpa unggah file.
     */
    public function tarik(Request $request, AttendanceDatabaseReader $reader, PresensiMesinService $presensi): RedirectResponse
    {
        // Membaca database Access (PowerShell) bisa melewati batas 30 detik.
        @set_time_limit(300);
        @ignore_user_abort(true);

        $data = $request->validate([
            'dari' => ['required', 'date'],
            'sampai' => ['required', 'date', 'after_or_equal:dari'],
        ]);

        $dari = Carbon::parse($data['dari'], config('app.timezone'))->startOfDay();
        $sampai = Carbon::parse($data['sampai'], config('app.timezone'))->startOfDay();

        if ((int) $dari->diffInDays($sampai) > PresensiMesinPullService::MAKS_HARI_TARIK - 1) {
            return back()
                ->withErrors(['dari' => 'Rentang tarik data maksimal '.PresensiMesinPullService::MAKS_HARI_TARIK.' hari agar proses tetap cepat.'])
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
            $this->pull->tandaiTerakhirTarik();

            return back()->with(
                'success',
                'Database mesin berhasil dibaca, tetapi tidak ada scan pada '.$dari->format('d/m/Y').' – '.$sampai->format('d/m/Y').'.',
            );
        }

        $hasil = $presensi->importScans($scans, auth()->id());
        $hasil['baris_diabaikan'] = 0;

        $this->pull->catatPerubahanKehadiran($hasil);
        $this->pull->tandaiTerakhirTarik();

        AktivitasService::log(
            'tambah',
            'Tarik presensi dari database mesin',
            "Tarik data mesin {$dari->toDateString()} s/d {$sampai->toDateString()}: {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, {$hasil['tetap']} tetap, {$hasil['dilindungi']} dilindungi, {$hasil['nik_tidak_dikenal']} NIK tidak dikenal.",
            'presensi_pegawai',
            null,
            null,
            $hasil,
        );

        return back()->with('success', $this->pull->pesanHasil(
            $hasil,
            'Tarik data mesin '.$dari->format('d/m/Y').' – '.$sampai->format('d/m/Y').' selesai',
        ));
    }

    /**
     * Alamat tarik-semua adalah aksi POST yang dipicu tombol. Bila dibuka
     * langsung dari address bar (GET), arahkan ke halaman import agar tidak
     * menampilkan halaman 405 Method Not Allowed.
     */
    public function formTarikSemua(): RedirectResponse
    {
        return redirect()
            ->route('admin.presensi.mesin.index')
            ->with('warning', 'Tombol "Tarik Semua Data (1 Klik)" ada di halaman Import Presensi Mesin. Klik tombol tersebut untuk memulai penarikan.');
    }

    public function tarikSemua(): RedirectResponse
    {
        // Tarik seluruh riwayat berjalan di queue worker (bukan request web),
        // dengan lock agar tidak bisa dikirim ganda. Hasilnya tercatat di
        // Aktivitas + tandaiTerakhirTarik oleh TarikSemuaPresensiJob.
        $lock = Cache::lock('presensi-tarik-semua', 600);
        if (! $lock->acquire()) {
            return back()->withErrors(['mesin' => 'Tarik semua data sedang berjalan. Tunggu hingga selesai, lalu muat ulang halaman.']);
        }

        TarikSemuaPresensiJob::dispatch(auth()->id());

        return back()->with('success', 'Tarik semua data mesin dimulai di latar belakang. Data akan muncul setelah proses selesai — muat ulang halaman dalam beberapa saat.');
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
     * Mode live monitor: baca scan terbaru dari mesin ZKTeco via TCP.
     */
    public function live(AttendanceDatabaseReader $reader): JsonResponse
    {
        $hasil = $reader->bacaLive();

        return response()->json([
            'ok' => (bool) ($hasil['ok'] ?? false),
            'pesan' => (string) ($hasil['pesan'] ?? ''),
            'scans' => array_map(
                fn (array $scan): array => [
                    'nik' => (string) ($scan['nik'] ?? ''),
                    'nama' => (string) ($scan['nama'] ?? ''),
                    'waktu' => isset($scan['waktu']) ? (string) $scan['waktu']->toDateTimeString() : null,
                    'jenis' => (string) ($scan['jenis'] ?? 'masuk'),
                ],
                (array) ($hasil['scans'] ?? []),
            ),
        ]);
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
            $sekolah = SekolahSettings::query()->first();
            $pengaturan = is_array($sekolah?->pengaturan ?? null) ? $sekolah->pengaturan : [];
            $pengaturan['presensi_mesin_engine'] = $engine;

            if ($sekolah) {
                $sekolah->update(['pengaturan' => $pengaturan]);
            } else {
                SekolahSettings::create(['pengaturan' => $pengaturan]);
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['mesin' => 'Gagal menyimpan pengaturan mesin: '.$e->getMessage()])->withInput();
        }

        return back()->with('success', 'Pengaturan pembacaan mesin berhasil disimpan (mesin_engine: '.$engine.').');
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    private function csvDownload(string $filename, array $header, array $rows): StreamedResponse
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
