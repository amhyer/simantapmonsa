<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DapodikConfig;
use App\Models\DapodikSyncLog;
use App\Models\JadwalPelajaran;
use App\Models\Kehadiran;
use App\Models\NilaiErapot;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\View\View;

class DapodikPushController extends Controller
{
    /**
     * Halaman "Kirim Nilai Ke Dapodik" (cermin menu e-Rapor).
     * Menampilkan status koneksi, jumlah data siap push per modul,
     * dan tombol aksi yang memanggil endpoint JSON push via fetch.
     */
    public function index(): View
    {
        $config = DapodikConfig::getInstance();
        $semesters = Semester::orderByDesc('semester_id')->get();

        $counts = [
            'sekolah' => SekolahSettings::exists() ? 1 : 0,
            'peserta_didik' => Siswa::where('aktif', true)->whereNull('archived_at')->count(),
            'gtk' => User::where('peran', 'guru')->where('aktif', true)->whereNotNull('dapodik_id')->count(),
            'rombel' => Rombel::whereNotNull('dapodik_id')->count(),
            'jadwal' => JadwalPelajaran::whereNotNull('dapodik_id')->count(),
            'nilai_rapor' => NilaiErapot::count(),
            'kehadiran' => Kehadiran::count(),
        ];

        $logs = DapodikSyncLog::orderByDesc('created_at')->limit(10)->get();

        return view('admin.dapodik.push.index', compact('config', 'semesters', 'counts', 'logs'));
    }
}
