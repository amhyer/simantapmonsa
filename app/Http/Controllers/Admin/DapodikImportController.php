<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\DapodikSyncLog;
use App\Models\OrangTuaSiswa;
use App\Models\Aktivitas;
use App\Models\Semester;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\JadwalPelajaran;
use App\Models\User;
use App\Jobs\ImportDapodikJob;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DapodikImportController extends Controller
{
    public function index()
    {
        abort_if(! TenantContext::check() && (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()), 403, 'Akun admin belum terhubung ke sekolah.');
        $tenantId = TenantContext::id();
        $isTenant = TenantMode::isMultiSchool();
        $logs = DapodikSyncLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $stats = [
            'total_siswa' => Siswa::count(),
            'dari_dapodik' => Siswa::where('dapodik_id', '!=', null)->count(),
            'sinkron_terakhir' => DapodikSyncLog::orderBy('created_at', 'desc')->value('created_at'),
            'semesters' => Semester::count(),
            'ptk' => $isTenant ? null : Ptk::count(),
            'guru_users' => User::where('tenant_id', $tenantId)->where('peran', 'guru')->count(),
            'rombel' => Rombel::count(),
            'jadwal' => JadwalPelajaran::count(),
        ];

        $semesters = Semester::orderByDesc('semester_id')->get();
        $ptkList = $isTenant ? collect() : Ptk::with('semester')->orderBy('nama')->limit(50)->get();
        $rombelList = Rombel::with('semester')->orderBy('nama_rombel')->limit(50)->get();

        $bridgePath = public_path('bridge/SIMANTAP-Bridge.exe');
        if (!file_exists($bridgePath)) {
            $bridgePath = base_path('dapodik-bridge/dist/SIMANTAP-Bridge.exe');
        }
        $bridgeChecksum = file_exists($bridgePath) ? hash_file('sha256', $bridgePath) : null;
        $bridgeSize = file_exists($bridgePath) ? filesize($bridgePath) : null;

        return view('admin.dapodik.index', compact('logs', 'stats', 'semesters', 'ptkList', 'rombelList', 'bridgeChecksum', 'bridgeSize', 'isTenant'));
    }

    public function import(Request $request)
    {
        abort_if(! TenantContext::check() && (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()), 403, 'Akun admin belum terhubung ke sekolah.');
        $validator = Validator::make($request->all(), [
            'sekolah' => 'required|array',
            'sekolah.npsn' => 'required|string',
            'sekolah.nama' => 'required|string',
            'siswa' => 'required|array|min:1',
            'siswa.*.nama' => 'required|string|max:255',
            'siswa.*.nisn' => 'nullable|string|max:20',
            'siswa.*.jenis_kelamin' => 'required|in:L,P',
            'siswa.*.kelas' => 'required|string|max:50',
            'tahun_ajaran' => 'nullable|string',
            'semester' => 'nullable|string',
            'tipe_sync' => 'nullable|in:baru,update,semua',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $data = $validator->validated();
        $tipeSync = $data['tipe_sync'] ?? 'semua';

        $log = DapodikSyncLog::create([
            'uuid' => Str::uuid(),
            'user_id' => auth()->id(),
            'sekolah_npsn' => $data['sekolah']['npsn'] ?? null,
            'nama_sekolah' => $data['sekolah']['nama'] ?? null,
            'tipe' => 'siswa',
            'tahun_ajaran' => $data['tahun_ajaran'] ?? null,
            'semester' => $data['semester'] ?? null,
            'total_data' => count($data['siswa']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'started_at' => now(),
        ]);

        // Dispatch job async (antrean: sync = langsung jalan, database/redis = background)
        $guru = auth()->user();
        ImportDapodikJob::dispatch(
            $data,
            $log->id,
            $tipeSync,
            $guru?->id,
            $guru?->nama_lengkap ?? 'Dapodik Bridge'
        );

        return response()->json([
            'success' => true,
            'message' => 'Import Dapodik dimulai secara asynchronous. Cek log untuk status progres.',
            'log_id' => $log->id,
        ]);
    }

    public function preview(Request $request)
    {
        abort_if(! TenantContext::check() && (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()), 403, 'Akun admin belum terhubung ke sekolah.');
        $validator = Validator::make($request->all(), ['siswa' => 'required|array']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $data = $validator->validated();
        $preview = [];

        foreach ($data['siswa'] as $s) {
            $existing = null;
            if (!empty($s['nisn'])) {
                $existing = Siswa::where('nisn', $s['nisn'])->first();
            }
            $preview[] = [
                'nama' => $s['nama'],
                'nisn' => $s['nisn'] ?? '-',
                'kelas' => $s['kelas'],
                'status' => $existing ? 'update' : 'baru',
                'existing_id' => $existing?->id,
            ];
        }

        return response()->json([
            'success' => true,
            'preview' => $preview,
            'summary' => [
                'total' => count($preview),
                'baru' => collect($preview)->where('status', 'baru')->count(),
                'update' => collect($preview)->where('status', 'update')->count(),
            ],
        ]);
    }

    public function logDetail($id)
    {
        $log = DapodikSyncLog::findOrFail($id);
        return view('admin.dapodik.log-detail', compact('log'));
    }

    public function logDestroy($id)
    {
        DapodikSyncLog::findOrFail($id)->delete();
        return redirect()->route('admin.dapodik.index')->with('success', 'Log dihapus.');
    }

    public function resetData(Request $request)
    {
        abort_if(! TenantContext::check() && (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()), 403, 'Akun admin belum terhubung ke sekolah.');

        $request->validate([
            'konfirmasi' => 'required|in:KOSONGKAN DATA DAPODIK',
            'password' => 'required|current_password',
        ], [
            'konfirmasi.required' => 'Ketik persis KOSONGKAN DATA DAPODIK untuk konfirmasi.',
            'konfirmasi.in' => 'Teks konfirmasi salah. Ketik persis: KOSONGKAN DATA DAPODIK.',
            'password.required' => 'Kata sandi wajib diisi untuk konfirmasi identitas.',
            'password.current_password' => 'Kata sandi salah. Gunakan kata sandi akun Anda yang sedang login.',
        ]);

        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;
        $currentUserId = auth()->id();
        $totalSiswaDihapus = 0;
        $totalRombelDihapus = 0;
        $totalPtkDihapus = 0;
        $totalUsersDihapus = 0;
        $totalJadwalDihapus = 0;

        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();
        try {
            // 1. Ambil ID Siswa milik sekolah ini
            $siswaQuery = Siswa::query();
            if ($tenantId !== null) {
                $siswaQuery->where('tenant_id', $tenantId);
            }
            $siswaIds = $siswaQuery->pluck('id');

            // Hapus semua data anak yang mereferensikan siswa_id
            if ($siswaIds->isNotEmpty()) {
                $allTables = Schema::getTableListing();
                foreach ($allTables as $tbl) {
                    if ($tbl === 'siswa') continue;
                    if (Schema::hasColumn($tbl, 'siswa_id')) {
                        DB::table($tbl)->whereIn('siswa_id', $siswaIds)->delete();
                    }
                }
            }

            // Hapus data fisik siswa
            if ($tenantId !== null) {
                Siswa::where('tenant_id', $tenantId)->update(['rombel_id' => null]);
                $totalSiswaDihapus = Siswa::where('tenant_id', $tenantId)->forceDelete();
            } else {
                Siswa::query()->update(['rombel_id' => null]);
                $totalSiswaDihapus = Siswa::query()->forceDelete();
            }

            // 2. Hapus Rombel milik sekolah ini
            $rombelQuery = Rombel::query();
            if ($tenantId !== null) {
                $rombelQuery->where('tenant_id', $tenantId);
            }
            $rombelIds = $rombelQuery->pluck('id');

            if ($rombelIds->isNotEmpty()) {
                foreach (['jadwal_pelajaran', 'pembelajaran', 'anggota_rombel', 'penilaian', 'nilai', 'presensi'] as $tbl) {
                    if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'rombel_id')) {
                        DB::table($tbl)->whereIn('rombel_id', $rombelIds)->delete();
                    }
                }
            }

            if ($tenantId !== null) {
                $totalRombelDihapus = Rombel::where('tenant_id', $tenantId)->forceDelete();
            } else {
                $totalRombelDihapus = Rombel::query()->forceDelete();
            }

            // 3. Hapus PTK milik sekolah ini
            if (Schema::hasTable('ptk')) {
                $ptkQuery = Ptk::query();
                if ($tenantId !== null && Schema::hasColumn('ptk', 'tenant_id')) {
                    $ptkQuery->where('tenant_id', $tenantId);
                }
                $totalPtkDihapus = $ptkQuery->delete();
            }

            // 4. Hapus Jadwal Pelajaran milik sekolah ini
            if (Schema::hasTable('jadwal_pelajaran')) {
                $jadwalQuery = JadwalPelajaran::query();
                if ($tenantId !== null && Schema::hasColumn('jadwal_pelajaran', 'tenant_id')) {
                    $jadwalQuery->where('tenant_id', $tenantId);
                }
                $totalJadwalDihapus = $jadwalQuery->delete();
            }

            // 5. Hapus akun pengguna (users) yang bersumber dari Dapodik:
            // - Semua akun siswa
            // - Semua akun guru/tendik dengan dapodik_id
            // Jangan hapus akun admin yang sedang login dan super_admin
            $userQuery = User::query();
            if ($tenantId !== null && Schema::hasColumn('users', 'tenant_id')) {
                $userQuery->where('tenant_id', $tenantId);
            }
            $userQuery->where(function ($q) {
                $q->where('peran', 'siswa')
                  ->orWhereNotNull('dapodik_id');
            });
            if ($currentUserId) {
                $userQuery->where('id', '!=', $currentUserId);
            }
            $userQuery->where('peran', '!=', 'super_admin');

            $targetUserIds = $userQuery->pluck('id');
            if ($targetUserIds->isNotEmpty()) {
                if (Schema::hasTable('presensi_pegawai')) {
                    DB::table('presensi_pegawai')->whereIn('user_id', $targetUserIds)->delete();
                }
                $totalUsersDihapus = User::whereIn('id', $targetUserIds)->forceDelete();
            }

            // 6. Hapus riwayat log sinkronisasi Dapodik milik sekolah ini
            if (Schema::hasTable('dapodik_sync_logs')) {
                $logQuery = DapodikSyncLog::query();
                if ($tenantId !== null && Schema::hasColumn('dapodik_sync_logs', 'tenant_id')) {
                    $logQuery->where('tenant_id', $tenantId);
                }
                $logQuery->delete();
            }

            // 7. Hapus identitas sekolah (sekolah_settings) milik sekolah ini
            if (Schema::hasTable('sekolah_settings')) {
                $settingsQuery = \App\Models\SekolahSettings::query();
                if ($tenantId !== null && Schema::hasColumn('sekolah_settings', 'tenant_id')) {
                    $settingsQuery->where('tenant_id', $tenantId);
                }
                $settingsQuery->delete();
            }

            // Flush cache aplikasi
            \Illuminate\Support\Facades\Cache::flush();

            // 7. Reset timestamp sinkronisasi Dapodik config
            try {
                \App\Models\DapodikConfig::getInstance()->update([
                    'last_sync_at' => null,
                    'last_sync_by' => null,
                    'auto_sync_last_run_at' => null,
                    'auto_sync_run_status' => null,
                    'auto_sync_run_error' => null,
                ]);
            } catch (\Throwable $e) {
                // Abaikan jika tabel config belum termuat
            }

            // 8. Reset PostgreSQL sequences jika driver pgsql
            if (DB::getDriverName() === 'pgsql') {
                foreach (['siswa', 'rombel', 'ptk', 'jadwal_pelajaran', 'dapodik_sync_logs'] as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'id')) {
                        $seq = DB::selectOne("SELECT pg_get_serial_sequence(?, ?) AS seq", [$table, 'id'])?->seq;
                        if ($seq) {
                            $maxId = (int) DB::table($table)->max('id');
                            DB::statement("SELECT setval(?, ?, ?)", [$seq, max($maxId, 1), $maxId > 0]);
                        }
                    }
                }
            }

            // Catat aktivitas
            $guru = auth()->user();
            try {
                Aktivitas::create([
                    'uuid' => (string) Str::uuid(),
                    'guru_id' => $guru?->id,
                    'nama_guru' => $guru?->nama_lengkap ?? 'Admin',
                    'jenis' => 'pengaturan',
                    'judul' => 'Reset Data Dapodik',
                    'deskripsi' => "Mengosongkan seluruh data Dapodik: {$totalSiswaDihapus} siswa, {$totalRombelDihapus} rombel, {$totalPtkDihapus} PTK, {$totalUsersDihapus} akun pengguna, dan {$totalJadwalDihapus} jadwal.",
                    'tabel_terkait' => 'siswa,rombel,ptk,users,jadwal_pelajaran',
                ]);
            } catch (\Throwable $e) {
                // Abaikan error logging aktivitas
            }

            DB::commit();

            return redirect()->route('admin.dapodik.index')->with(
                'success',
                "Seluruh data Dapodik berhasil dikosongkan total ({$totalSiswaDihapus} siswa, {$totalRombelDihapus} rombel, {$totalPtkDihapus} PTK, {$totalUsersDihapus} akun pengguna Dapodik dihapus). Anda kini dapat melakukan penarikan data baru dari Dapodik secara bersih."
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('admin.dapodik.index')->with(
                'error',
                'Gagal mengosongkan data Dapodik: ' . $e->getMessage()
            );
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function downloadBridge()
    {
        $filePath = public_path('bridge/SIMANTAP-Bridge.exe');
        if (!file_exists($filePath)) {
            $filePath = base_path('dapodik-bridge/dist/SIMANTAP-Bridge.exe');
        }
        $fileName = 'SIMANTAP-Bridge.exe';

        if (!file_exists($filePath)) {
            return redirect()->route('admin.dapodik.index')->with('error', 'File Bridge tidak ditemukan.');
        }

        return response()->streamDownload(function () use ($filePath) {
            readfile($filePath);
        }, $fileName, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => filesize($filePath),
        ]);
    }
}
