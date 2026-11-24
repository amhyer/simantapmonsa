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
        $belumTerpetakan = $this->karyawan->daftarBelumTerpetakan();
        $daftarPegawai = $this->karyawan->daftarPengguna();
        $opsiAutoAkun = $this->karyawan->pengaturanAutoAkun();

        return view('admin.presensi.mesin-import', compact(
            'jumlahPegawai',
            'sudahAdaNik',
            'koneksi',
            'terakhirTarik',
            'directDisabled',
            'mesinEngineTerpilih',
            'belumTerpetakan',
            'daftarPegawai',
            'opsiAutoAkun',
        ));
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
        $belumTerpetakan = $this->karyawan->daftarBelumTerpetakan();
        $daftarPegawai = $this->karyawan->daftarPengguna();
        $opsiAutoAkun = $this->karyawan->pengaturanAutoAkun();

        return view('admin.presensi.mesin-sinkron', [
            'logs' => $logs,
            'terakhir' => $terakhir,
            'terakhirTarik' => $terakhirTarik,
            'directDisabled' => $directDisabled,
            'koneksi' => $koneksi,
            'belumTerpetakan' => $belumTerpetakan,
            'daftarPegawai' => $daftarPegawai,
            'opsiAutoAkun' => $opsiAutoAkun,
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

        $autoCreate = $request->has('buat_akun_otomatis') ? $request->boolean('buat_akun_otomatis') : null;
        $peranDefault = $request->filled('peran_otomatis') ? (string) $request->input('peran_otomatis') : null;

        $hasil = $this->karyawan->simpanNikKaryawan($pasangan, $autoCreate, $peranDefault);

        AktivitasService::log(
            'tambah',
            'Import NIK karyawan dari mesin presensi',
            "Import data karyawan: {$hasil['diperbarui']} NIK diperbarui, {$hasil['akun_dibuat']} akun baru dibuat, {$hasil['tetap']} sudah sesuai, {$hasil['tidak_cocok']} nama tidak cocok, {$hasil['ambigu']} ambigu, {$hasil['bentrok']} bentrok.",
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
    public function tarikKaryawan(Request $request, AttendanceDatabaseReader $reader): RedirectResponse
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

        $autoCreate = $request->has('buat_akun_otomatis') ? $request->boolean('buat_akun_otomatis') : null;
        $peranDefault = $request->filled('peran_otomatis') ? (string) $request->input('peran_otomatis') : null;

        $hasil = $this->karyawan->simpanNikKaryawan($pegawai, $autoCreate, $peranDefault);

        AktivitasService::log(
            'tambah',
            'Tarik NIK karyawan dari database mesin',
            "Tarik data karyawan: {$hasil['diperbarui']} NIK diperbarui, {$hasil['akun_dibuat']} akun baru dibuat, {$hasil['tetap']} sudah sesuai, {$hasil['tidak_cocok']} nama tidak cocok, {$hasil['ambigu']} ambigu, {$hasil['bentrok']} bentrok.",
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
        $lock = Cache::lock(TarikSemuaPresensiJob::lockKey(), TarikSemuaPresensiJob::TTL_LOCK);

        if (! $lock->acquire()) {
            return back()->withErrors(['mesin' => 'Tarik semua data sedang berjalan. Tunggu hingga selesai, lalu muat ulang halaman.']);
        }

        try {
            TarikSemuaPresensiJob::dispatch(auth()->id(), auth()->user()?->tenant_id);
        } catch (\Throwable $e) {
            // Driver queue bermasalah: jangan tinggalkan lock menggantung.
            $lock->forceRelease();
            report($e);

            return back()->withErrors(['mesin' => 'Gagal menjadwalkan penarikan data. Coba lagi atau hubungi admin server.']);
        }

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
                SekolahSettings::create([
                    'nama_sekolah' => (string) config('app.name', 'SIMANTAP'),
                    'pengaturan' => $pengaturan,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['mesin' => 'Gagal menyimpan pengaturan mesin: '.$e->getMessage()])->withInput();
        }

        return back()->with('success', 'Pengaturan pembacaan mesin berhasil disimpan (mesin_engine: '.$engine.').');
    }

    /**
     * Simpan pengaturan pembuatan akun otomatis untuk nama baru dari mesin absensi.
     */
    public function updatePengaturanAkun(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'auto_akun' => ['nullable', 'boolean'],
            'default_peran' => ['required', 'string', 'in:tendik,guru'],
        ]);

        $aktif = $request->boolean('auto_akun');
        $peran = (string) $validated['default_peran'];

        $this->karyawan->simpanPengaturanAutoAkun($aktif, $peran);

        $statusTeks = $aktif
            ? 'Aktif (otomatis buat akun '.strtoupper($peran).' untuk nama baru dari mesin)'
            : 'Nonaktif (tahan nama baru di daftar Pemetaan PIN Mesin)';

        return back()->with('success', 'Pengaturan akun otomatis mesin absensi disimpan: '.$statusTeks.'.');
    }

    /**
     * Pasangkan PIN mesin yang belum cocok ke akun pegawai yang dipilih dari dropdown.
     */
    public function pasangkanPin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:50'],
            'user_id' => ['required', 'integer'],
        ]);

        try {
            $user = $this->karyawan->pasangkanPinKePegawai($validated['nik'], (int) $validated['user_id']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['pemetaan' => $e->getMessage()]);
        }

        AktivitasService::log(
            'ubah',
            'Pasangkan PIN mesin absensi ke pegawai',
            "PIN mesin {$user->nik} dipasangkan ke akun {$user->nama_lengkap} ({$user->peran}).",
            'users',
            $user->id,
            null,
            ['nik' => $user->nik, 'user_id' => $user->id],
        );

        return back()->with('success', "PIN {$user->nik} berhasil dipasangkan ke akun {$user->nama_lengkap}.");
    }

    /**
     * Buat 1 akun pegawai baru dengan 1 klik dari daftar PIN mesin belum terpetakan.
     */
    public function buatAkunDariPin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:120'],
            'peran' => ['required', 'string', 'in:tendik,guru'],
        ]);

        try {
            $res = $this->karyawan->buatAkunPegawaiMesin($validated['nik'], $validated['nama'], $validated['peran']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['pemetaan' => $e->getMessage()]);
        }

        $user = $res['user'];

        AktivitasService::log(
            'tambah',
            'Buat akun pegawai dari PIN mesin absensi',
            "Akun {$user->nama_lengkap} ({$user->peran}) dibuat dari PIN mesin {$user->nik}.",
            'users',
            $user->id,
            null,
            ['nik' => $user->nik, 'username' => $res['username'], 'peran' => $user->peran],
        );

        return back()->with(
            'success',
            "Akun {$user->nama_lengkap} (".strtoupper($user->peran).") berhasil dibuat dengan PIN {$user->nik}. Username: {$res['username']} | Sandi awal: {$res['password_awal']}",
        );
    }

    /**
     * Buat akun baru secara massal (1 klik) untuk seluruh PIN mesin yang belum terpetakan.
     */
    public function buatSemuaAkunPin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'peran' => ['required', 'string', 'in:tendik,guru'],
        ]);

        $res = $this->karyawan->buatSemuaAkunBelumTerpetakan($validated['peran']);

        if ($res['dibuat'] === 0) {
            return back()->with('warning', 'Tidak ada PIN mesin yang dapat dibuatkan akun baru.');
        }

        AktivitasService::log(
            'tambah',
            'Buat akun massal dari PIN mesin absensi',
            "{$res['dibuat']} akun pegawai ({$validated['peran']}) dibuat otomatis dari daftar PIN mesin belum terpetakan.",
            'users',
            null,
            null,
            $res,
        );

        return back()->with(
            'success',
            "{$res['dibuat']} akun baru (".strtoupper($validated['peran']).") berhasil dibuat dan langsung terhubung dengan PIN mesin masing-masing.",
        );
    }

    /**
     * Hapus/abaikan 1 entri dari daftar antrean Pemetaan PIN Mesin.
     */
    public function hapusPinBelumTerpetakan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:50'],
        ]);

        $this->karyawan->hapusBelumTerpetakan($validated['nik']);

        return back()->with('success', "PIN {$validated['nik']} dihapus dari daftar antrean pemetaan.");
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
