<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Materi;
use App\Models\Kuis;
use App\Models\Kehadiran;
use App\Models\User;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\PengaturanGuru;
use App\Models\SekolahSettings;
use App\Models\DapodikSyncLog;
use App\Models\Semester;

class DashboardController extends Controller
{
    public function admin()
    {
        $totalUsers = User::count();
        $siswa = Siswa::count();
        $siswaAktif = Siswa::where('aktif', true)->count();
        $ptk = Ptk::count();
        $guru = User::where('peran', 'guru')->count();
        $rombel = Rombel::count();
        $kelas = Rombel::count();

        $sekolahSettings = SekolahSettings::first();
        $semesterAktif = Semester::orderByDesc('semester_id')->first();
        $syncLogs = DapodikSyncLog::orderByDesc('created_at')->take(5)->get();
        $lastSync = $syncLogs->first();
        $syncedSiswa = Siswa::whereNotNull('dapodik_id')->count();
        $syncedGuru = User::where('peran', 'guru')->whereNotNull('dapodik_id')->count();

        $totalMateri = Materi::count();
        $totalKuis = Kuis::count();
        $totalKehadiran = Kehadiran::count();
        $totalHadir = Kehadiran::where('status', 'H')->count();

        $recentUsers = User::whereNotNull('terakhir_masuk')->orderByDesc('terakhir_masuk')->take(6)->get();

        return view('admin.dashboard', compact(
            'totalUsers', 'guru', 'siswa', 'siswaAktif', 'ptk', 'rombel', 'kelas',
            'sekolahSettings', 'semesterAktif', 'syncLogs', 'lastSync',
            'syncedSiswa', 'syncedGuru',
            'totalMateri', 'totalKuis', 'totalKehadiran', 'totalHadir',
            'recentUsers'
        ));
    }

    public function siswa()
    {
        $user = auth()->user();
        $siswa = Siswa::where('nis', $user->nama_pengguna)->first();

        if (!$siswa && !empty($user->terhubung_dengan)) {
            $siswa = Siswa::whereIn('id', $user->terhubung_dengan)->first();
        }

        $kehadiran = 0;
        $totalHadir = 0;
        
        if ($siswa) {
            $totalKehadiran = Kehadiran::where('siswa_id', $siswa->id)->count();
            $totalHadir = Kehadiran::where('siswa_id', $siswa->id)->where('status', 'H')->count();
            $kehadiran = $totalKehadiran > 0 ? round($totalHadir / $totalKehadiran * 100, 1) : 0;
        }

        return view('siswa.dashboard', compact('siswa', 'kehadiran', 'totalHadir'));
    }

    public function ortu()
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];
        
        if (empty($anakIds)) {
            return view('ortu.dashboard', ['anak' => null]);
        }

        $anak = Siswa::whereIn('id', $anakIds)->first();
        return view('ortu.dashboard', compact('anak'));
    }

    public function ortuNilai()
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];
        $siswa = Siswa::whereIn('id', $anakIds)->first();
        
        return view('ortu.nilai.index', ['siswa' => $siswa]);
    }
}
