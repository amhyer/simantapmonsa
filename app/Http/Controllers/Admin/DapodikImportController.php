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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DapodikImportController extends Controller
{
    protected $dapodikService;

    public function __construct(DapodikService $dapodikService)
    {
        $this->dapodikService = $dapodikService;
    }

    public function index()
    {
        $logs = DapodikSyncLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $stats = [
            'total_siswa' => Siswa::count(),
            'dari_dapodik' => Siswa::where('dapodik_id', '!=', null)->count(),
            'sinkron_terakhir' => DapodikSyncLog::orderBy('created_at', 'desc')->value('created_at'),
            'semesters' => Semester::count(),
            'ptk' => Ptk::count(),
            'guru_users' => User::where('peran', 'guru')->count(),
            'rombel' => Rombel::count(),
            'jadwal' => JadwalPelajaran::count(),
        ];

        $semesters = Semester::orderByDesc('semester_id')->get();
        $ptkList = Ptk::with('semester')->orderBy('nama')->limit(50)->get();
        $rombelList = Rombel::with('semester')->orderBy('nama_rombel')->limit(50)->get();

        return view('admin.dapodik.index', compact('logs', 'stats', 'semesters', 'ptkList', 'rombelList'));
    }

    public function import(Request $request)
    {
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

        // Dispatch job async
        $this->dispatch->job(new ImportDapodikJob($data, $data['sekolah']['npsn'] ?? null, $data['tahun_ajaran'] ?? null, $data['semester'] ?? null));

        return response()->json([
            'success' => true,
            'message' => 'Import Dapodik dimulai secara asynchronous. Cek log untuk status progres.',
            'log_id' => $log->id,
        ]);
    }

    public function preview(Request $request)
    {
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

    public function downloadBridge()
    {
        $filePath = public_path('bridge/SIMANTAP-Bridge.exe');
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
