<?php

namespace App\Http\Controllers;

use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use App\Models\Materi;
use App\Models\Kuis;
use App\Models\Kehadiran;
use App\Models\User;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\PengaturanGuru;
use App\Models\SekolahSettings;
use App\Models\DapodikConfig;
use App\Models\DapodikSyncLog;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\Aktivitas;
use App\Models\Semester;
use App\Models\TanggalRapor;
use App\Models\TemaKokurikuler;
use App\Models\Catatan;
use App\Models\Kebiasaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function admin()
    {
        // Seluruh angka dashboard di-cache 120 detik: ~30 query + scan
        // filesystem diringkas menjadi 0 query saat cache hit.
        // Model Eloquent TIDAK ikut di-cache (pernah menjadi objek korup
        // saat unserialize); diambil segar di bawah setiap request.
        $data = Cache::remember('admin-dashboard:v2', 120, function () {
            $totalUsers = User::count();
            $siswa = Siswa::count();
            $siswaAktif = Siswa::where('aktif', true)->count();
            $ptk = Ptk::count();
            $guru = User::where('peran', 'guru')->where('aktif', true)->count();
            $rombel = Rombel::count();

            $sekolahSettings = SekolahSettings::first();
            $semesterAktif = Semester::orderByDesc('semester_id')->first();
            $syncLogs = DapodikSyncLog::orderByDesc('created_at')->take(5)->get();
            $lastSync = $syncLogs->first();
            $syncedSiswa = Siswa::whereNotNull('dapodik_id')->count();
            $syncedGuru = User::where('peran', 'guru')->whereNotNull('dapodik_id')->count();

            $totalMateri = Materi::count();
            $totalJadwal = JadwalPelajaran::count();
            $totalKuis = Kuis::count();
            $totalKehadiran = Kehadiran::count();
            $totalHadir = Kehadiran::where('status', 'H')->count();

            $onlineCount = User::where('terakhir_masuk', '>=', now()->subMinutes(15))->count();

            // Baca config tanpa menulis (getInstance() = firstOrCreate menulis di tiap GET).
            $dapodikConfig = DapodikConfig::first();
            $pengaturanSekolah = $sekolahSettings?->pengaturan ?? [];
            $statusKerja = [
                ['label' => 'Menyimpan data koneksi webservice', 'done' => !empty($dapodikConfig?->npsn) && !empty($dapodikConfig?->token)],
                ['label' => 'Ambil Data Dapodik', 'done' => $lastSync !== null],
                ['label' => 'Menambah Data Administrator', 'done' => User::where('peran', 'admin')->exists()],
                ['label' => 'Generate User Guru dan Siswa', 'done' => User::where('peran', 'guru')->exists() && User::where('peran', 'siswa')->exists()],
                ['label' => 'Edit Data Kepala Sekolah', 'done' => !empty($sekolahSettings?->kepala_sekolah)],
                ['label' => 'Update Gelar Guru', 'done' => Ptk::whereNotNull('gelar_belakang')->orWhereNotNull('gelar_depan')->exists()],
                ['label' => 'Update Data Siswa', 'done' => $syncedSiswa > 0],
                ['label' => 'Mapping Mapel Rapor', 'done' => MataPelajaran::where('urutan', '>', 0)->orWhereNotNull('kelompok')->exists()],
                ['label' => 'Update data logo pemda', 'done' => !empty($pengaturanSekolah['logo_pemda'])],
                ['label' => 'Update data logo sekolah', 'done' => !empty($pengaturanSekolah['logo_sekolah'])],
                ['label' => 'Update data Kop Sekolah', 'done' => !empty($pengaturanSekolah['kop_sekolah'])],
                ['label' => 'Update tanggal rapor', 'done' => TanggalRapor::exists()],
                ['label' => 'Update tema kokurikuler', 'done' => TemaKokurikuler::exists()],
                ['label' => 'Backup Data', 'done' => count(Storage::disk('local')->files('backups')) > 0],
                ['label' => 'Kirim Nilai Ke Dapodik', 'done' => NilaiErapot::where('terkirim_erapor', true)->exists()],
            ];
            $progresKerja = count($statusKerja) > 0
                ? round(collect($statusKerja)->where('done', true)->count() / count($statusKerja) * 100, 2)
                : 0;

            $aktivitasTerbaru = Aktivitas::orderByDesc('created_at')->take(6)->get();

            // Rincian akun per peran (1 query agregat, portable lintas driver).
            $peranRows = User::selectRaw("peran, count(*) as total, sum(case when aktif then 1 else 0 end) as aktif")
                ->groupBy('peran')
                ->get()
                ->keyBy('peran');
            $peranBreakdown = [];
            foreach (['admin', 'guru', 'siswa', 'ortu', 'kepsek'] as $peran) {
                $row = $peranRows->get($peran);
                $peranBreakdown[] = [
                    'peran' => $peran,
                    'total' => (int) ($row->total ?? 0),
                    'aktif' => (int) ($row->aktif ?? 0),
                ];
            }

            // Info backup: file + ukuran + antrean export async + job gagal.
            $backupFiles = Storage::disk('local')->files('backups');
            $backupUkuran = 0;
            $backupTerakhir = null;
            foreach ($backupFiles as $file) {
                $backupUkuran += Storage::disk('local')->size($file);
                $waktu = Storage::disk('local')->lastModified($file);
                if ($backupTerakhir === null || $waktu > $backupTerakhir) {
                    $backupTerakhir = $waktu;
                }
            }
            $backupAntre = Schema::hasTable('jobs')
                ? DB::table('jobs')->where('payload', 'like', '%ExportBackupJob%')->count()
                : 0;
            $jobGagal = Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->count()
                : 0;
            $backupInfo = [
                'jumlah' => count($backupFiles),
                'ukuran' => $this->formatBytes($backupUkuran),
                'terakhir' => $backupTerakhir ? now()->setTimestamp($backupTerakhir)->diffForHumans() : null,
                'antre' => $backupAntre,
                'gagal' => $jobGagal,
            ];

            // Tren pendaftaran 6 minggu terakhir (1 query, bucket di PHP agar portable).
            $sejak = now()->subWeeks(5)->startOfWeek();
            $minggu = [];
            for ($i = 5; $i >= 0; $i--) {
                $awal = now()->subWeeks($i)->startOfWeek();
                $minggu[$awal->format('Y-m-d')] = ['label' => $awal->format('d/m'), 'jumlah' => 0];
            }
            User::where('created_at', '>=', $sejak)->pluck('created_at')->each(function ($tgl) use (&$minggu) {
                $kunci = $tgl->copy()->startOfWeek()->format('Y-m-d');
                if (isset($minggu[$kunci])) {
                    $minggu[$kunci]['jumlah']++;
                }
            });
            $trenPendaftaran = array_values($minggu);
            $trenMaks = max(array_column($trenPendaftaran, 'jumlah')) ?: 1;

            // Kesehatan sistem.
            $health = [
                ['label' => 'Environment', 'nilai' => config('app.env'), 'ok' => config('app.env') === 'production' ? true : null],
                ['label' => 'Debug mode', 'nilai' => config('app.debug') ? 'ON' : 'OFF', 'ok' => !config('app.debug')],
                ['label' => 'Antrean', 'nilai' => config('queue.default') . " ({$backupAntre} menunggu)", 'ok' => $backupAntre === 0 ? true : null],
                ['label' => 'Job gagal', 'nilai' => (string) $jobGagal, 'ok' => $jobGagal === 0],
                ['label' => 'Cache', 'nilai' => config('cache.default'), 'ok' => true],
                ['label' => 'Database', 'nilai' => DB::getDriverName() . ' (' . $this->formatBytes($this->ukuranDatabase()) . ')', 'ok' => true],
                ['label' => 'Folder backup', 'nilai' => $this->formatBytes($backupUkuran), 'ok' => true],
            ];

            // Rekap kelengkapan guru aktif (distinct guru_id per tabel konten).
            $guruIds = User::where('peran', 'guru')->where('aktif', true)->pluck('id');
            $totalGuru = $guruIds->count();
            $rekapGuru = [
                'total' => $totalGuru,
                'item' => [
                    ['label' => 'Data siswa terisi', 'done' => $this->hitungGuruUnik(Siswa::class, $guruIds)],
                    ['label' => 'Upload materi', 'done' => $this->hitungGuruUnik(Materi::class, $guruIds)],
                    ['label' => 'Membuat kuis', 'done' => $this->hitungGuruUnik(Kuis::class, $guruIds)],
                    ['label' => 'Input nilai', 'done' => $this->hitungGuruUnik(Nilai::class, $guruIds)],
                    ['label' => 'Isi e-Rapor', 'done' => $this->hitungGuruUnik(NilaiErapot::class, $guruIds)],
                ],
            ];

            return compact(
                'totalUsers', 'guru', 'siswa', 'siswaAktif', 'ptk', 'rombel',
                'syncedSiswa', 'syncedGuru',
                'totalMateri', 'totalKuis', 'totalKehadiran', 'totalHadir',
                'onlineCount', 'totalJadwal', 'statusKerja', 'progresKerja',
                'peranBreakdown', 'backupInfo',
                'trenPendaftaran', 'trenMaks', 'health', 'rekapGuru'
            );
        });

        // Model segar setiap request (di luar cache).
        $data['sekolahSettings'] = SekolahSettings::first();
        $data['semesterAktif'] = Semester::orderByDesc('semester_id')->first();
        $data['syncLogs'] = DapodikSyncLog::orderByDesc('created_at')->take(5)->get();
        $data['lastSync'] = $data['syncLogs']->first();
        $data['aktivitasTerbaru'] = Aktivitas::orderByDesc('created_at')->take(6)->get();

        // Alias lama agar view tak berubah makna: $kelas = jumlah rombel.
        $data['kelas'] = $data['rombel'];

        return view('admin.dashboard', $data);
    }

    /**
     * Hitung berapa guru unik (dari daftar id) yang punya baris di tabel konten.
     */
    protected function hitungGuruUnik(string $model, $guruIds): int
    {
        if ($guruIds->isEmpty()) {
            return 0;
        }

        return $model::whereIn('guru_id', $guruIds)->distinct()->count('guru_id');
    }

    /**
     * Ukuran database dalam byte (null bila tak bisa diukur). Driver-aware.
     */
    protected function ukuranDatabase(): ?int
    {
        try {
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                $path = config('database.connections.sqlite.database');
                if ($path && $path !== ':memory:' && is_file($path)) {
                    return (int) filesize($path);
                }

                return null;
            }
            if ($driver === 'pgsql') {
                return (int) DB::selectOne('select pg_database_size(current_database()) as size')->size;
            }
            if ($driver === 'mysql') {
                $row = DB::selectOne(
                    'select sum(data_length + index_length) as size from information_schema.tables where table_schema = ?',
                    [config('database.connections.mysql.database')]
                );

                return $row && $row->size !== null ? (int) $row->size : null;
            }
        } catch (\Throwable) {
            // Abaikan: ukuran DB bersifat informatif.
        }

        return null;
    }

    protected function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '-';
        }
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $satuan = ['KB', 'MB', 'GB'];
        $nilai = (float) $bytes / 1024;
        foreach ($satuan as $s) {
            if ($nilai < 1024 || $s === 'GB') {
                return number_format($nilai, 1) . ' ' . $s;
            }
            $nilai /= 1024;
        }

        return number_format($bytes) . ' B';
    }

    public function ortu(Request $request)
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.dashboard', ['anak' => null, 'anakList' => collect()]);
        }

        $siswaId = $request->get('siswa_id') ?? $anakIds[0];

        if (!in_array($siswaId, $anakIds)) {
            abort(403, 'Anda tidak memiliki akses ke data siswa ini.');
        }

        $anak = Siswa::find($siswaId);
        $anakList = Siswa::whereIn('id', $anakIds)->get();

        // Ringkasan pantauan: nilai, kehadiran, kebiasaan minggu ini, catatan terbaru.
        $ringkasan = null;
        if ($anak) {
            $nilaiService = app(\App\Services\NilaiService::class);
            $na = $nilaiService->hitungNilaiAkhir($anak->id);
            $totalKehadiran = Kehadiran::where('siswa_id', $anak->id)->count();
            $hadir = Kehadiran::where('siswa_id', $anak->id)->where('status', 'H')->count();
            $ringkasan = [
                'nilai_akhir' => $na['nilai_akhir'],
                'predikat' => $na['nilai_akhir'] > 0
                    ? $nilaiService->getPredikat($na['nilai_akhir'], getKKM($anak->guru_id))
                    : null,
                'hadir_persen' => $totalKehadiran > 0 ? round($hadir / $totalKehadiran * 100, 1) : null,
                'kebiasaan_minggu' => Kebiasaan::where('siswa_id', $anak->id)
                    ->where('tanggal', '>=', now()->startOfWeek()->toDateString())
                    ->count(),
                'catatan' => Catatan::where('siswa_id', $anak->id)
                    ->orderByDesc('tanggal')->limit(3)->get(),
            ];
        }

        return view('ortu.dashboard', compact('anak', 'anakList', 'ringkasan'));
    }

    public function ortuNilai(Request $request)
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.nilai.index', ['siswa' => null, 'anakList' => collect()]);
        }

        $siswaId = $request->get('siswa_id') ?? $anakIds[0];

        if (!in_array($siswaId, $anakIds)) {
            abort(403, 'Anda tidak memiliki akses ke data siswa ini.');
        }

        $siswa = Siswa::find($siswaId);
        $anakList = Siswa::whereIn('id', $anakIds)->get();

        return view('ortu.nilai.index', compact('siswa', 'anakList'));
    }
}
