<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportDapodikJob;
use App\Models\DapodikSyncLog;
use App\Models\JadwalPelajaran;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantMode;
use App\Services\Admin\SchoolDataResetService;
use App\Services\Navigation\SchoolDataAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DapodikImportController extends Controller
{
    public function index()
    {
        abort_if(! TenantContext::check() && (config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()), 403, 'Akun admin belum terhubung ke sekolah.');
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

        $bridgeDownloadUrl = config('dapodik.bridge_download_url');
        $bridgePath = $this->resolveBridgePath();
        $bridgeAvailable = ! empty($bridgeDownloadUrl) || $bridgePath !== null;
        $bridgeVersion = config('dapodik.bridge_version');
        $bridgeChecksum = config('dapodik.bridge_sha256') ?: ($bridgePath ? hash_file('sha256', $bridgePath) : null);
        $bridgeSize = $bridgePath ? filesize($bridgePath) : null;

        return view('admin.dapodik.index', compact(
            'logs', 'stats', 'semesters', 'ptkList', 'rombelList',
            'bridgeDownloadUrl', 'bridgeAvailable', 'bridgeVersion', 'bridgeChecksum', 'bridgeSize', 'isTenant'
        ));
    }

    public function import(Request $request)
    {
        abort_if(! TenantContext::check() && (config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()), 403, 'Akun admin belum terhubung ke sekolah.');
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
        abort_if(! TenantContext::check() && (config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()), 403, 'Akun admin belum terhubung ke sekolah.');
        $validator = Validator::make($request->all(), ['siswa' => 'required|array']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $data = $validator->validated();
        $preview = [];

        foreach ($data['siswa'] as $s) {
            $existing = null;
            if (! empty($s['nisn'])) {
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
        SchoolDataAvailabilityService::forget(TenantContext::id() ?? auth()->user()?->tenant_id);

        return redirect()->route('admin.dapodik.index')->with('success', 'Log dihapus.');
    }

    public function resetData(Request $request, SchoolDataResetService $resetService)
    {
        abort_if(! TenantContext::check() && (config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()), 403, 'Akun admin belum terhubung ke sekolah.');

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

        try {
            $stats = $resetService->resetSchoolKeepAdmins(
                $tenantId !== null ? (int) $tenantId : null,
                $currentUserId !== null ? (int) $currentUserId : null
            );

            return redirect()->route('admin.dapodik.index')->with(
                'success',
                "Seluruh data sekolah berhasil dikosongkan total ({$stats['siswa']} siswa, {$stats['rombel']} rombel, {$stats['ptk']} PTK, {$stats['users']} akun pengguna non-admin dihapus; hanya akun Administrator yang dipertahankan). Menu yang datanya kosong disembunyikan otomatis dan akan muncul kembali sesuai data yang ditarik dari Dapodik."
            );
        } catch (\Throwable $e) {
            return redirect()->route('admin.dapodik.index')->with(
                'error',
                'Gagal mengosongkan data sekolah: '.$e->getMessage()
            );
        }
    }

    public function downloadBridge()
    {
        $downloadUrl = config('dapodik.bridge_download_url');
        if (! empty($downloadUrl)) {
            return redirect()->away($downloadUrl);
        }

        $filePath = $this->resolveBridgePath();
        $fileName = 'SIMANTAP-Bridge.exe';

        if (! $filePath) {
            return redirect()->route('admin.dapodik.index')->with('error', 'File SIMANTAP-Bridge belum tersedia. Hubungi administrator atau konfigurasikan DAPODIK_BRIDGE_DOWNLOAD_URL.');
        }

        return response()->streamDownload(function () use ($filePath) {
            readfile($filePath);
        }, $fileName, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Content-Length' => filesize($filePath),
        ]);
    }

    private function resolveBridgePath(): ?string
    {
        $privatePath = (string) config('dapodik.bridge_storage_path', storage_path('app/private/bridge/SIMANTAP-Bridge.exe'));
        if ($privatePath !== '' && file_exists($privatePath)) {
            return $privatePath;
        }

        // Artefak build hasil `npm run build:bridge` (atau fixture test) hanya
        // dipakai di mesin pengembang/CI. Dulu gate-nya `local` saja sehingga
        // test di env `testing` selalu melihat bridge "tidak tersedia".
        if (app()->environment(['local', 'testing'])) {
            foreach ([
                public_path('bridge/SIMANTAP-Bridge.exe'),
                base_path('dapodik-bridge/dist/SIMANTAP-Bridge.exe'),
            ] as $legacyPath) {
                if (file_exists($legacyPath)) {
                    return $legacyPath;
                }
            }
        }

        return null;
    }
}
