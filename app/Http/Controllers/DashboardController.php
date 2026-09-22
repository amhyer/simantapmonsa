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
use App\Models\Semester;
use App\Models\TanggalRapor;
use App\Models\TemaKokurikuler;
use Illuminate\Support\Facades\Storage;

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
        $totalJadwal = JadwalPelajaran::count();
        $totalKuis = Kuis::count();
        $totalKehadiran = Kehadiran::count();
        $totalHadir = Kehadiran::where('status', 'H')->count();

        $recentUsers = User::whereNotNull('terakhir_masuk')->orderByDesc('terakhir_masuk')->take(6)->get();

        $onlineCount = User::where('terakhir_masuk', '>=', now()->subMinutes(15))->count();

        $dapodikConfig = DapodikConfig::getInstance();
        $pengaturanSekolah = $sekolahSettings?->pengaturan ?? [];
        $statusKerja = [
            ['label' => 'Menyimpan data koneksi webservice', 'done' => !empty($dapodikConfig->npsn) && !empty($dapodikConfig->token)],
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
            ['label' => 'Kirim Nilai Ke Dapodik', 'done' => false],
        ];
        $progresKerja = count($statusKerja) > 0
            ? round(collect($statusKerja)->where('done', true)->count() / count($statusKerja) * 100, 2)
            : 0;

        return view('admin.dashboard', compact(
            'totalUsers', 'guru', 'siswa', 'siswaAktif', 'ptk', 'rombel', 'kelas',
            'sekolahSettings', 'semesterAktif', 'syncLogs', 'lastSync',
            'syncedSiswa', 'syncedGuru',
            'totalMateri', 'totalKuis', 'totalKehadiran', 'totalHadir',
            'recentUsers', 'onlineCount', 'totalJadwal', 'statusKerja', 'progresKerja'
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
