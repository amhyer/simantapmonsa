<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\TkaController as TkaStatsController;
use App\Http\Controllers\Guru\DashboardController as GuruDashboard;
use App\Http\Controllers\Guru\SiswaController;
use App\Http\Controllers\Guru\MateriController;
use App\Http\Controllers\Guru\KuisController;
use App\Http\Controllers\Guru\NilaiController;
use App\Http\Controllers\Guru\KehadiranController;
use App\Http\Controllers\Guru\CatatanController;
use App\Http\Controllers\Guru\DimensiController;
use App\Http\Controllers\Guru\LaporanController;
use App\Http\Controllers\Guru\PengaturanController;
use App\Http\Controllers\Guru\TKAController;
use App\Http\Controllers\Guru\AnalisisController;
use App\Http\Controllers\Guru\GuruKebiasaanController;
use App\Http\Controllers\Guru\IntegrasiController;
use App\Http\Controllers\Ortu\KebiasaanController as OrtuKebiasaanController;
use App\Http\Controllers\Ortu\RekapController as OrtuRekapController;
use App\Http\Controllers\Ortu\KehadiranController as OrtuKehadiranController;
use App\Http\Controllers\Ortu\CatatanController as OrtuCatatanController;
use App\Http\Controllers\Ortu\LaporanController as OrtuLaporanController;
use App\Http\Controllers\Siswa\DashboardController as SiswaDashboard;
use App\Http\Controllers\Siswa\MateriController as SiswaMateriController;
use App\Http\Controllers\Siswa\KuisController as SiswaKuisController;
use App\Http\Controllers\Siswa\NilaiController as SiswaNilaiController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PetaKelasController;
use App\Http\Controllers\Admin\KodeAksesController;
use App\Http\Controllers\Admin\ModulController;
use App\Http\Controllers\Admin\BobotController;
use App\Http\Controllers\Admin\SekolahController;
use App\Http\Controllers\Admin\SheetController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MapelController;
use App\Http\Controllers\Kepsek\RekapController;
use App\Http\Controllers\Kepsek\PetaKelasController as KepsekPetaKelas;
use App\Http\Controllers\Kepsek\HasilBelajarController;
use App\Http\Controllers\Kepsek\KebiasaanController as KepsekKebiasaanController;
use App\Http\Controllers\Kepsek\AktivitasController;
use Illuminate\Support\Facades\Route;

// Landing Page
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/demo', [LandingController::class, 'demo'])->name('demo');

// TKA Statistics (public view, protected write)
Route::get('/tka-statistik', [TkaStatsController::class, 'index'])->name('tka.statistik');
Route::get('/api/tka-statistik', [TkaStatsController::class, 'api'])->name('tka.api');
Route::post('/tka-statistik/refresh', [TkaStatsController::class, 'refresh'])
    ->middleware('auth')
    ->name('tka.refresh');

// Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [LandingController::class, 'register'])->name('register');
    Route::post('/register', [LandingController::class, 'storeRegister'])->middleware('throttle:5,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('throttle:30,1')->name('logout');

// Force Password Change
Route::middleware(['auth'])->group(function () {
    Route::get('/force-password-change', [ForcePasswordChangeController::class, 'show'])->name('force-password-change.show');
    Route::put('/force-password-change', [ForcePasswordChangeController::class, 'update'])->name('force-password-change.update');
});

// Dashboard redirect
Route::get('/dashboard', function () {
    if (!auth()->check()) return redirect()->route('login');
    $user = auth()->user();
    return match($user->peran) {
        'admin' => redirect()->route('admin.dashboard'),
        'guru' => redirect()->route('guru.dashboard'),
        'siswa' => redirect()->route('siswa.dashboard'),
        'ortu' => redirect()->route('ortu.dashboard'),
        'kepsek' => redirect()->route('kepsek.dashboard'),
        default => redirect()->route('login'),
    };
})->name('home');

// ==================== ADMIN ROUTES ====================
Route::middleware(['auth', 'role:admin', 'force.password.change'])->prefix('admin')->name('admin.')->group(function () {
    // Dasbor
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
    
    // Kelola Pengguna
    Route::get('/users/akun-massal', [UserController::class, 'akunMassal'])->name('users.akun-massal');
    Route::post('/users/proses-massal', [UserController::class, 'prosesAkunMassal'])->middleware('throttle:5,1')->name('users.proses-massal');
    Route::delete('/users/hapus-semua/{peran}', [UserController::class, 'destroyAll'])->middleware('throttle:5,1')->name('users.destroy-all');
    Route::resource('users', UserController::class)->except(['show']);
    
    // Peta Kelas
    Route::get('/peta-kelas', [PetaKelasController::class, 'index'])->name('peta-kelas.index');
    Route::post('/peta-kelas', [PetaKelasController::class, 'store'])->name('peta-kelas.store');
    Route::delete('/peta-kelas/{id}', [PetaKelasController::class, 'destroy'])->name('peta-kelas.destroy');
    Route::post('/peta-kelas/pindah', [PetaKelasController::class, 'pindahSiswa'])->name('peta-kelas.pindah');
    Route::post('/peta-kelas/keluarkan', [PetaKelasController::class, 'keluarkanSiswa'])->name('peta-kelas.keluarkan');
    Route::get('/peta-kelas/kelas-options', [PetaKelasController::class, 'getKelasOptions'])->name('peta-kelas.kelas-options');
    
    // Mata Pelajaran
    Route::get('/mapel', [MapelController::class, 'index'])->name('mapel.index');
    Route::get('/mapel/create', [MapelController::class, 'create'])->name('mapel.create');
    Route::post('/mapel', [MapelController::class, 'store'])->name('mapel.store');
    Route::delete('/mapel/hapus-semua', [MapelController::class, 'destroyAll'])->name('mapel.destroy-all');
    Route::delete('/mapel/hapus-terpilih', [MapelController::class, 'destroySelected'])->name('mapel.destroy-selected');
    Route::post('/mapel/import-dapodik', [MapelController::class, 'importDapodik'])->middleware('throttle:10,1')->name('mapel.import-dapodik');
    Route::post('/mapel/import-lokal', [MapelController::class, 'importLokal'])->middleware('throttle:10,1')->name('mapel.import-lokal');
    Route::post('/mapel/seed', [MapelController::class, 'seedDefault'])->name('mapel.seed');
    Route::get('/mapel/{mapel}/edit', [MapelController::class, 'edit'])->name('mapel.edit');
    Route::put('/mapel/{mapel}', [MapelController::class, 'update'])->name('mapel.update');
    Route::delete('/mapel/{mapel}', [MapelController::class, 'destroy'])->name('mapel.destroy');
    
    // Kode & Akses
    Route::get('/kode-akses', [KodeAksesController::class, 'index'])->name('kode-akses.index');
    Route::post('/kode-akses/{id}', [KodeAksesController::class, 'update'])->name('kode-akses.update');
    
    // Modul & Tampilan
    Route::get('/modul', [ModulController::class, 'index'])->name('modul.index');
    Route::post('/modul', [ModulController::class, 'update'])->name('modul.update');
    
    // Bobot & Ketuntasan
    Route::get('/bobot', [BobotController::class, 'index'])->name('bobot.index');
    Route::post('/bobot/{id}', [BobotController::class, 'update'])->name('bobot.update');
    
    // Identitas Sekolah
    Route::get('/sekolah', [SekolahController::class, 'index'])->name('sekolah.index');
    Route::post('/sekolah/search', [SekolahController::class, 'search'])->name('sekolah.search');
    Route::post('/sekolah', [SekolahController::class, 'update'])->name('sekolah.update');
    
    // Penyimpanan Data
    Route::get('/sheet', [SheetController::class, 'index'])->name('sheet.index');
    Route::post('/sheet/connect', [SheetController::class, 'connect'])->name('sheet.connect');
    Route::post('/sheet/disconnect', [SheetController::class, 'disconnect'])->name('sheet.disconnect');
    
    // Data & Pemulihan
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::get('/backup/export', [BackupController::class, 'export'])->name('backup.export');
    Route::post('/backup/import', [BackupController::class, 'import'])->middleware('throttle:5,1')->name('backup.import');
    
    // Log Aktivitas
    Route::get('/log', [LogController::class, 'index'])->name('log.index');

    // API Keys & Dapodik
    Route::prefix('api-keys')->name('api-keys.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ApiKeyController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\ApiKeyController::class, 'store'])->name('store');
        Route::delete('/{apiKey}', [\App\Http\Controllers\Admin\ApiKeyController::class, 'destroy'])->name('destroy');
        Route::post('/{apiKey}/toggle', [\App\Http\Controllers\Admin\ApiKeyController::class, 'toggle'])->name('toggle');
    });

    // Semester Management
    Route::prefix('semester')->name('semester.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SemesterController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\SemesterController::class, 'store'])->name('store');
        Route::delete('/{semester}', [\App\Http\Controllers\Admin\SemesterController::class, 'destroy'])->name('destroy');
        Route::post('/{semester}/activate', [\App\Http\Controllers\Admin\SemesterController::class, 'activate'])->name('activate');
    });

    // Dapodik Import
    Route::prefix('dapodik')->name('dapodik.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DapodikImportController::class, 'index'])->name('index');
        Route::post('/import', [\App\Http\Controllers\Admin\DapodikImportController::class, 'import'])->name('import');
        Route::post('/preview', [\App\Http\Controllers\Admin\DapodikImportController::class, 'preview'])->name('preview');
        Route::get('/log/{id}', [\App\Http\Controllers\Admin\DapodikImportController::class, 'logDetail'])->name('log-detail');
        Route::delete('/log/{id}', [\App\Http\Controllers\Admin\DapodikImportController::class, 'logDestroy'])->name('log-destroy');
        Route::get('/download-bridge', [\App\Http\Controllers\Admin\DapodikImportController::class, 'downloadBridge'])->name('download-bridge');
    });

    // Dapodik WebService API (moved from api.php to get session auth)
    Route::prefix('dapodik/api')->name('dapodik.api.')->group(function () {
        Route::get('/config', [\App\Http\Controllers\Api\DapodikController::class, 'config'])->name('config');
        Route::post('/config', [\App\Http\Controllers\Api\DapodikController::class, 'saveConfig'])->name('config.save');
        Route::post('/test-connection', [\App\Http\Controllers\Api\DapodikController::class, 'testConnection'])->name('test-connection');
        Route::post('/preview-data', [\App\Http\Controllers\Api\DapodikController::class, 'preview'])->name('preview');
        Route::post('/pull', [\App\Http\Controllers\Api\DapodikController::class, 'pull'])->name('pull');
        Route::get('/semesters', [\App\Http\Controllers\Api\DapodikController::class, 'semesters'])->name('semesters');
        Route::post('/sync', [\App\Http\Controllers\Api\DapodikController::class, 'sync'])->name('sync');
    });
});

// ==================== GURU ROUTES ====================
Route::middleware(['auth', 'role:guru', 'force.password.change'])->prefix('guru')->name('guru.')->group(function () {
    // Dasbor
    Route::get('/dashboard', [GuruDashboard::class, 'index'])->name('dashboard');
    
    // Data Siswa
    Route::resource('siswa', SiswaController::class)->except(['show']);
    Route::get('/siswa/unduh/csv', [SiswaController::class, 'unduh'])->name('siswa.unduh');
    Route::post('/siswa/impor', [SiswaController::class, 'impor'])->name('siswa.impor');
    Route::get('/siswa/pungut', [SiswaController::class, 'pungut'])->name('siswa.pungut');
    Route::get('/siswa/pungut/kelas', [SiswaController::class, 'getKelasSiswa'])->name('siswa.pungut.kelas');
    Route::post('/siswa/pungut/simpan', [SiswaController::class, 'simpanPungut'])->name('siswa.pungut.simpan');
    
    // Materi Ajar
    Route::resource('materi', MateriController::class);
    
    // Kuis & Soal
    Route::resource('kuis', KuisController::class)->except(['show']);
    Route::post('/kuis/{kuis}/toggle', [KuisController::class, 'toggle'])->name('kuis.toggle');
    Route::get('/kuis/{kuis}/hasil', [KuisController::class, 'hasil'])->name('kuis.hasil');
    
    // Input Nilai Cepat
    Route::get('/input-nilai', [\App\Http\Controllers\Guru\InputNilaiController::class, 'index'])->name('input-nilai.index');
    Route::get('/input-nilai/data', [\App\Http\Controllers\Guru\InputNilaiController::class, 'getNilai'])->name('input-nilai.data');
    Route::post('/input-nilai/simpan', [\App\Http\Controllers\Guru\InputNilaiController::class, 'simpanNilai'])->middleware('throttle:60,1')->name('input-nilai.simpan');
    Route::post('/input-nilai/paste', [\App\Http\Controllers\Guru\InputNilaiController::class, 'pasteFromExcel'])->middleware('throttle:30,1')->name('input-nilai.paste');
    
    // Input Nilai e-Rapor
    Route::get('/nilai-erapor', [\App\Http\Controllers\Guru\NilaiErapotController::class, 'index'])->name('nilai-erapor.index');
    Route::post('/nilai-erapor/simpan', [\App\Http\Controllers\Guru\NilaiErapotController::class, 'simpan'])->middleware('throttle:60,1')->name('nilai-erapor.simpan');
    Route::post('/nilai-erapor/auto-save', [\App\Http\Controllers\Guru\NilaiErapotController::class, 'autoSave'])->middleware('throttle:120,1')->name('nilai-erapor.auto-save');
    Route::get('/nilai-erapor/deskripsi/{siswaId}', [\App\Http\Controllers\Guru\NilaiErapotController::class, 'deskripsi'])->name('nilai-erapor.deskripsi');
    Route::get('/nilai-erapor/template', [\App\Http\Controllers\Guru\ImportErapotController::class, 'downloadTemplate'])->name('nilai-erapor.template');
    Route::post('/nilai-erapor/import', [\App\Http\Controllers\Guru\ImportErapotController::class, 'import'])->name('nilai-erapor.import');
    Route::get('/nilai-erapor/export', [\App\Http\Controllers\Guru\ImportErapotController::class, 'export'])->name('nilai-erapor.export');
    
    // Generate e-Rapor (PDF & Excel)
    Route::prefix('erapor')->name('erapor.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Guru\ErapotGeneratorController::class, 'index'])->name('index');
        Route::get('/pdf/{siswa}', [\App\Http\Controllers\Guru\ErapotGeneratorController::class, 'generatePdf'])->name('pdf');
        Route::post('/pdf-bulk', [\App\Http\Controllers\Guru\ErapotGeneratorController::class, 'generateBulkPdf'])->name('pdf-bulk');
        Route::get('/excel', [\App\Http\Controllers\Guru\ErapotGeneratorController::class, 'exportExcel'])->name('excel');
    });
    
    // Profil Lulusan
    Route::resource('dimensi', DimensiController::class)->only(['index', 'store']);
    
    // Daftar Nilai
    Route::resource('nilai', NilaiController::class)->except(['show']);
    
    // Kehadiran
    Route::resource('kehadiran', KehadiranController::class)->only(['index', 'store', 'destroy']);
    
    // Catatan Siswa
    Route::resource('catatan', CatatanController::class)->only(['index', 'store', 'destroy']);
    
    // Laporan & Rapor
    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/pdf', [LaporanController::class, 'pdf'])->name('laporan.pdf');
    
    // Pengaturan
    Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
    Route::post('/pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
    
    // Kesiapan TKA
    Route::get('/tka', [TKAController::class, 'index'])->name('tka.index');
    Route::get('/tka/{id}', [TKAController::class, 'show'])->name('tka.show');
    Route::post('/tka', [TKAController::class, 'store'])->name('tka.store');
    Route::get('/tka/{id}/analisis', [TKAController::class, 'analysis'])->name('tka.analysis');
    
    // Analisis Belajar
    Route::get('/analisis', [AnalisisController::class, 'index'])->name('analisis.index');
    Route::get('/analisis/{siswaId}', [AnalisisController::class, 'detail'])->name('analisis.detail');
    
    // 7 Kebiasaan dari Orang Tua
    Route::get('/kebiasaan', [GuruKebiasaanController::class, 'index'])->name('kebiasaan.index');
    Route::get('/kebiasaan/{id}', [GuruKebiasaanController::class, 'show'])->name('kebiasaan.show');
    
    // Google Sheet Integration
    Route::get('/integrasi', [IntegrasiController::class, 'index'])->name('integrasi.index');
    Route::post('/integrasi/connect', [IntegrasiController::class, 'connect'])->name('integrasi.connect');
    Route::post('/integrasi/disconnect', [IntegrasiController::class, 'disconnect'])->name('integrasi.disconnect');
    Route::post('/integrasi/update-settings', [IntegrasiController::class, 'updateSettings'])->name('integrasi.update-settings');
    Route::post('/integrasi/full-sync', [IntegrasiController::class, 'fullSync'])->middleware('throttle:5,1')->name('integrasi.full-sync');
});

// ==================== SISWA ROUTES ====================
Route::middleware(['auth', 'role:siswa', 'force.password.change'])->prefix('siswa')->name('siswa.')->group(function () {
    Route::get('/dashboard', [SiswaDashboard::class, 'index'])->name('dashboard');
    Route::get('/materi', [SiswaMateriController::class, 'index'])->name('materi.index');
    Route::get('/materi/{id}', [SiswaMateriController::class, 'show'])->name('materi.show');
    Route::get('/kuis', [SiswaKuisController::class, 'index'])->name('kuis.index');
    Route::get('/kuis/{kuis}', [SiswaKuisController::class, 'show'])->name('kuis.show');
    Route::post('/kuis/{kuis}/submit', [SiswaKuisController::class, 'submit'])->middleware('throttle:10,1')->name('kuis.submit');
    Route::get('/nilai', [SiswaNilaiController::class, 'index'])->name('nilai.index');
    Route::get('/nilai/{jenis}', [SiswaNilaiController::class, 'detail'])->name('nilai.detail');
    Route::get('/profil', [\App\Http\Controllers\Siswa\ProfilController::class, 'show'])->name('profil.show');
    Route::get('/profil/edit', [\App\Http\Controllers\Siswa\ProfilController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [\App\Http\Controllers\Siswa\ProfilController::class, 'update'])->name('profil.update');
});

// ==================== ORANG TUA ROUTES ====================
Route::middleware(['auth', 'role:ortu', 'force.password.change'])->prefix('ortu')->name('ortu.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'ortu'])->name('dashboard');
    Route::get('/kebiasaan', [OrtuKebiasaanController::class, 'index'])->name('kebiasaan.index');
    Route::post('/kebiasaan', [OrtuKebiasaanController::class, 'store'])->name('kebiasaan.store');
    Route::get('/nilai', [DashboardController::class, 'ortuNilai'])->name('nilai.index');
    Route::get('/rekap', [OrtuRekapController::class, 'index'])->name('rekap.index');
    Route::get('/kehadiran', [OrtuKehadiranController::class, 'index'])->name('kehadiran.index');
    Route::get('/catatan', [OrtuCatatanController::class, 'index'])->name('catatan.index');
    Route::get('/laporan', [OrtuLaporanController::class, 'index'])->name('laporan.index');
});

// ==================== KEPSEK ROUTES ====================
Route::middleware(['auth', 'role:kepsek', 'force.password.change'])->prefix('kepsek')->name('kepsek.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Kepsek\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/pantau', [\App\Http\Controllers\Kepsek\DashboardController::class, 'pantau'])->name('pantau.index');
    Route::get('/belajar', [\App\Http\Controllers\Kepsek\DashboardController::class, 'belajar'])->name('belajar.index');
    Route::get('/rekap', [RekapController::class, 'index'])->name('rekap.index');
    Route::get('/rekap/{guruId}', [RekapController::class, 'detail'])->name('rekap.detail');
    Route::get('/rekap/unduh/csv', [RekapController::class, 'unduh'])->name('rekap.unduh');
    Route::get('/peta-kelas', [KepsekPetaKelas::class, 'index'])->name('peta-kelas.index');
    Route::get('/peta-kelas/{guruId}', [KepsekPetaKelas::class, 'detail'])->name('peta-kelas.detail');
    Route::get('/hasil-belajar', [HasilBelajarController::class, 'index'])->name('hasil-belajar.index');
    Route::get('/kebiasaan', [KepsekKebiasaanController::class, 'index'])->name('kebiasaan.index');
    Route::get('/aktivitas', [AktivitasController::class, 'index'])->name('aktivitas.index');
});
