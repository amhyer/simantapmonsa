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
use App\Http\Controllers\Admin\DataReferensiController;
use App\Http\Controllers\Admin\KokurikulerController;
use App\Http\Controllers\Admin\PenilaianController;
use App\Http\Controllers\Admin\PerkembanganController;
use App\Http\Controllers\Admin\TranskripController;
use App\Http\Controllers\Admin\CetakController;
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

// Presensi mandiri pegawai (guru, kepala sekolah, dan tendik), dilindungi geofence sekolah.
Route::middleware(['auth', 'role:guru,kepsek,tendik', 'force.password.change'])
    ->prefix('presensi/pegawai')->name('presensi.pegawai.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Pegawai\PresensiController::class, 'index'])->name('index');
        Route::post('/masuk', [\App\Http\Controllers\Pegawai\PresensiController::class, 'masuk'])->middleware('throttle:10,1')->name('masuk');
        Route::post('/pulang', [\App\Http\Controllers\Pegawai\PresensiController::class, 'pulang'])->middleware('throttle:10,1')->name('pulang');
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
        'tendik' => redirect()->route('presensi.pegawai.index'),
        default => redirect()->route('login'),
    };
})->name('home');

// ==================== ADMIN ROUTES ====================
Route::middleware(['auth', 'role:admin', 'force.password.change'])->prefix('admin')->name('admin.')->group(function () {
    // Dasbor
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

    // Presensi pegawai, area geofence, dan pemetaan QR kartu siswa.
    Route::prefix('presensi')->name('presensi.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PresensiController::class, 'index'])->name('index');
        Route::post('/lokasi', [\App\Http\Controllers\Admin\PresensiController::class, 'storeLocation'])->name('lokasi.store');
        Route::put('/lokasi/{lokasi}', [\App\Http\Controllers\Admin\PresensiController::class, 'updateLocation'])->name('lokasi.update');
        Route::delete('/lokasi/{lokasi}', [\App\Http\Controllers\Admin\PresensiController::class, 'destroyLocation'])->name('lokasi.destroy');
        Route::post('/pegawai/koreksi', [\App\Http\Controllers\Admin\PresensiController::class, 'koreksi'])->middleware('throttle:30,1')->name('pegawai.koreksi');
        Route::get('/kartu-siswa', [\App\Http\Controllers\Admin\PresensiKartuController::class, 'index'])->name('kartu.index');
        Route::post('/kartu-siswa/{siswa}/hubungkan', [\App\Http\Controllers\Admin\PresensiKartuController::class, 'bind'])->middleware('throttle:60,1')->name('kartu.bind');
        Route::delete('/kartu-siswa/{siswa}', [\App\Http\Controllers\Admin\PresensiKartuController::class, 'reset'])->name('kartu.reset');
    });

    // Profile (cermin menu Profile e-Rapor)
    Route::get('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/password', [\App\Http\Controllers\Admin\ProfileController::class, 'updatePassword'])->middleware('throttle:10,1')->name('profile.password');
    
    // Kelola Pengguna
    Route::get('/users/akun-massal', [UserController::class, 'akunMassal'])->name('users.akun-massal');
    Route::get('/users/siswa', [UserController::class, 'siswaIndex'])->name('users.siswa');
    Route::post('/users/proses-massal', [UserController::class, 'prosesAkunMassal'])->middleware('throttle:5,1')->name('users.proses-massal');
    Route::delete('/users/hapus-semua/{peran}', [UserController::class, 'destroyAll'])->middleware('throttle:5,1')->name('users.destroy-all');
    Route::resource('users', UserController::class)->except(['show']);
    
    // Peta Kelas
    Route::get('/peta-kelas', [PetaKelasController::class, 'index'])->name('peta-kelas.index');
    Route::get('/peta-kelas/daftar', [PetaKelasController::class, 'daftar'])->name('peta-kelas.daftar');
    Route::get('/peta-kelas/kelas-options', [PetaKelasController::class, 'getKelasOptions'])->name('peta-kelas.kelas-options');
    Route::get('/peta-kelas/{rombel}', [PetaKelasController::class, 'detail'])->whereNumber('rombel')->name('peta-kelas.detail');
    Route::post('/peta-kelas', [PetaKelasController::class, 'store'])->name('peta-kelas.store');
    Route::delete('/peta-kelas/{id}', [PetaKelasController::class, 'destroy'])->name('peta-kelas.destroy');
    Route::post('/peta-kelas/pindah', [PetaKelasController::class, 'pindahSiswa'])->name('peta-kelas.pindah');
    Route::post('/peta-kelas/keluarkan', [PetaKelasController::class, 'keluarkanSiswa'])->name('peta-kelas.keluarkan');
    
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

    // Data Referensi (guru & pembelajaran dari Dapodik, read-only)
    Route::get('/referensi/guru', [DataReferensiController::class, 'guru'])->name('referensi.guru');
    Route::put('/referensi/gelar-ptk', [DataReferensiController::class, 'updateGelarPtk'])->middleware('throttle:30,1')->name('referensi.gelar-ptk.update');
    Route::get('/referensi/pembelajaran', [DataReferensiController::class, 'pembelajaran'])->name('referensi.pembelajaran');
    Route::post('/referensi/pembelajaran', [DataReferensiController::class, 'storePembelajaran'])->middleware('throttle:30,1')->name('referensi.pembelajaran.store');
    Route::delete('/referensi/pembelajaran/{pembelajaran}', [DataReferensiController::class, 'destroyPembelajaran'])->middleware('throttle:30,1')->name('referensi.pembelajaran.destroy');
    Route::get('/referensi/tanggal-rapor', [DataReferensiController::class, 'tanggalRapor'])->name('referensi.tanggal-rapor');
    Route::post('/referensi/tanggal-rapor', [DataReferensiController::class, 'storeTanggalRapor'])->middleware('throttle:30,1')->name('referensi.tanggal-rapor.store');
    Route::delete('/referensi/tanggal-rapor/{tanggalRapor}', [DataReferensiController::class, 'destroyTanggalRapor'])->middleware('throttle:30,1')->name('referensi.tanggal-rapor.destroy');
    Route::get('/referensi/kelompok-mapel', [DataReferensiController::class, 'kelompokMapel'])->name('referensi.kelompok-mapel');
    Route::get('/referensi/alignment', [DataReferensiController::class, 'alignmentCheck'])->name('referensi.alignment');
    Route::get('/referensi/mapping-rapor', [DataReferensiController::class, 'mappingRapor'])->name('referensi.mapping-rapor');
    Route::put('/referensi/mapel-meta', [DataReferensiController::class, 'updateMapelMeta'])->middleware('throttle:30,1')->name('referensi.mapel-meta.update');
    Route::get('/referensi/logo-ttd', [DataReferensiController::class, 'logoTtd'])->name('referensi.logo-ttd');
    Route::post('/referensi/logo-ttd', [DataReferensiController::class, 'storeLogoTtd'])->middleware('throttle:30,1')->name('referensi.logo-ttd.store');
    Route::get('/referensi/foto-siswa', [DataReferensiController::class, 'fotoSiswa'])->name('referensi.foto-siswa');
    Route::post('/referensi/foto-siswa', [DataReferensiController::class, 'storeFotoSiswa'])->middleware('throttle:10,1')->name('referensi.foto-siswa.store');
    Route::post('/referensi/foto-siswa/{siswa}', [DataReferensiController::class, 'storeFotoSiswaSatuan'])->middleware('throttle:30,1')->name('referensi.foto-siswa.satuan');

    // Kokurikuler
    Route::get('/kokurikuler/tema', [KokurikulerController::class, 'tema'])->name('kokurikuler.tema');
    Route::post('/kokurikuler/tema', [KokurikulerController::class, 'storeTema'])->middleware('throttle:30,1')->name('kokurikuler.tema.store');
    Route::delete('/kokurikuler/tema/{tema}', [KokurikulerController::class, 'destroyTema'])->middleware('throttle:30,1')->name('kokurikuler.tema.destroy');
    Route::get('/kokurikuler/kegiatan', [KokurikulerController::class, 'kegiatan'])->name('kokurikuler.kegiatan');
    Route::post('/kokurikuler/kegiatan', [KokurikulerController::class, 'storeKegiatan'])->middleware('throttle:30,1')->name('kokurikuler.kegiatan.store');
    Route::delete('/kokurikuler/kegiatan/{kegiatan}', [KokurikulerController::class, 'destroyKegiatan'])->middleware('throttle:30,1')->name('kokurikuler.kegiatan.destroy');
    Route::get('/kokurikuler/kelompok', [KokurikulerController::class, 'kelompok'])->name('kokurikuler.kelompok');
    Route::post('/kokurikuler/kelompok', [KokurikulerController::class, 'storeKelompok'])->middleware('throttle:30,1')->name('kokurikuler.kelompok.store');
    Route::get('/kokurikuler/kelompok/{kelompok}', [KokurikulerController::class, 'showKelompok'])->name('kokurikuler.kelompok.show');
    Route::put('/kokurikuler/kelompok/{kelompok}/anggota', [KokurikulerController::class, 'updateAnggota'])->middleware('throttle:30,1')->name('kokurikuler.anggota.update');
    Route::delete('/kokurikuler/kelompok/{kelompok}', [KokurikulerController::class, 'destroyKelompok'])->middleware('throttle:30,1')->name('kokurikuler.kelompok.destroy');

    // Penilaian (agregasi read-only)
    Route::get('/penilaian/status', [PenilaianController::class, 'status'])->name('penilaian.status');
    Route::get('/penilaian/statistik', [PenilaianController::class, 'statistik'])->name('penilaian.statistik');

    // Perkembangan Nilai (tren + grafik, agregasi read-only)
    Route::get('/perkembangan', [PerkembanganController::class, 'index'])->name('perkembangan.index');
    Route::get('/perkembangan/grafik', [PerkembanganController::class, 'grafik'])->name('perkembangan.grafik');

    // Transkrip Ijazah
    Route::get('/transkrip/setting', [TranskripController::class, 'setting'])->name('transkrip.setting');
    Route::put('/transkrip/setting', [TranskripController::class, 'updateSetting'])->middleware('throttle:30,1')->name('transkrip.setting.update');
    Route::get('/transkrip/nomor', [TranskripController::class, 'nomor'])->name('transkrip.nomor');
    Route::put('/transkrip/nomor/{siswa}', [TranskripController::class, 'updateNomor'])->middleware('throttle:30,1')->name('transkrip.nomor.update');
    Route::post('/transkrip/nomor/import', [TranskripController::class, 'importNomor'])->middleware('throttle:10,1')->name('transkrip.nomor.import');
    Route::get('/transkrip/input', [TranskripController::class, 'input'])->name('transkrip.input');
    Route::get('/transkrip/input/{siswa}', [TranskripController::class, 'formInput'])->name('transkrip.input.form');
    Route::put('/transkrip/input/{siswa}', [TranskripController::class, 'storeInput'])->middleware('throttle:30,1')->name('transkrip.input.store');
    Route::get('/transkrip/import', [TranskripController::class, 'importNilai'])->name('transkrip.import');
    Route::get('/transkrip/import/template', [TranskripController::class, 'templateNilai'])->name('transkrip.import.template');
    Route::post('/transkrip/import', [TranskripController::class, 'storeImportNilai'])->middleware('throttle:10,1')->name('transkrip.import.store');
    Route::get('/transkrip/cetak', [TranskripController::class, 'cetak'])->name('transkrip.cetak');
    Route::get('/transkrip/cetak/{siswa}', [TranskripController::class, 'showCetak'])->name('transkrip.cetak.show');

    // Cetak Nilai (leger, pelengkap, nilai rapor — agregasi read-only)
    Route::get('/cetak/leger', [CetakController::class, 'leger'])->name('cetak.leger');
    Route::get('/cetak/pelengkap', [CetakController::class, 'pelengkap'])->name('cetak.pelengkap');
    Route::get('/cetak/nilai', [CetakController::class, 'nilai'])->name('cetak.nilai');
    Route::get('/cetak/nilai/{siswa}', [CetakController::class, 'showNilai'])->name('cetak.nilai.show');
    Route::get('/referensi/ekstrakurikuler', [DataReferensiController::class, 'ekstrakurikuler'])->name('referensi.ekstrakurikuler');
    Route::post('/referensi/ekstrakurikuler', [DataReferensiController::class, 'storeEkstrakurikuler'])->middleware('throttle:30,1')->name('referensi.ekstrakurikuler.store');
    Route::delete('/referensi/ekstrakurikuler/{ekstrakurikuler}', [DataReferensiController::class, 'destroyEkstrakurikuler'])->middleware('throttle:30,1')->name('referensi.ekstrakurikuler.destroy');
    
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
    Route::post('/backup/wipe', [BackupController::class, 'wipeAll'])->middleware('throttle:10,5')->name('backup.wipe');
    
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

    // Dapodik Push (Sync ke Dapodik Server)
    Route::prefix('dapodik/push')->name('dapodik.push.')->middleware('throttle:10,1')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DapodikPushController::class, 'index'])->name('index');
        Route::get('/sekolah', [\App\Http\Controllers\Api\DapodikPushController::class, 'pushSekolah'])->name('sekolah');
        Route::post('/peserta-didik', [\App\Http\Controllers\Api\DapodikPushController::class, 'pushPesertaDidik'])->name('peserta-didik');
        Route::post('/gtk', [\App\Http\Controllers\Api\DapodikPushController::class, 'pushGtk'])->name('gtk');
        Route::post('/rombel', [\App\Http\Controllers\Api\DapodikPushController::class, 'pushRombel'])->name('rombel');
        Route::post('/jadwal', [\App\Http\Controllers\Api\DapodikPushController::class, 'pushJadwal'])->name('jadwal');
        Route::post('/nilai-rapor', [\App\Http\Controllers\Api\DapodikPushController::class, 'pushNilaiRapor'])->name('nilai-rapor');
        Route::post('/kehadiran', [\App\Http\Controllers\Api\DapodikPushController::class, 'pushKehadiran'])->name('kehadiran');
        Route::get('/status', [\App\Http\Controllers\Api\DapodikPushController::class, 'status'])->name('status');
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
    Route::resource('dimensi', DimensiController::class)->only(['index', 'store', 'destroy']);
    
    // Daftar Nilai
    Route::resource('nilai', NilaiController::class)->except(['show']);
    
    // Kehadiran harian dan catatan tambahan mapel melalui QR.
    Route::get('/kehadiran/jadwal/{jadwal}', [KehadiranController::class, 'jadwal'])->name('kehadiran.jadwal');
    Route::post('/kehadiran/jadwal/{jadwal}/scan', [KehadiranController::class, 'scan'])->middleware('throttle:120,1')->name('kehadiran.scan');
    Route::post('/kehadiran/jadwal/{jadwal}/siswa/{siswa}/status', [KehadiranController::class, 'setStatus'])->middleware('throttle:120,1')->name('kehadiran.status');
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
    Route::get('/tka/analisis', [TKAController::class, 'analysis'])->name('tka.analysis');
    Route::get('/tka/{id}', [TKAController::class, 'show'])->name('tka.show');
    Route::post('/tka', [TKAController::class, 'store'])->name('tka.store');
    
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
    Route::get('/rekap', [RekapController::class, 'index'])->name('rekap.index');
    Route::get('/rekap/{guruId}', [RekapController::class, 'detail'])->name('rekap.detail');
    Route::get('/rekap/unduh/csv', [RekapController::class, 'unduh'])->name('rekap.unduh');
    Route::get('/peta-kelas', [KepsekPetaKelas::class, 'index'])->name('peta-kelas.index');
    Route::get('/peta-kelas/{guruId}', [KepsekPetaKelas::class, 'detail'])->whereNumber('guruId')->name('peta-kelas.detail');
    Route::get('/hasil-belajar', [HasilBelajarController::class, 'index'])->name('hasil-belajar.index');
    Route::get('/kebiasaan', [KepsekKebiasaanController::class, 'index'])->name('kebiasaan.index');
    Route::get('/aktivitas', [AktivitasController::class, 'index'])->name('aktivitas.index');
});

// ==================== CBT ROUTES (NONAKTIF) ====================
// Modul CBT belum punya controller/view/tabel. Route dibungkus feature flag
// `simantap.cbt_enabled` (default false) supaya tidak melempar HTTP 500.
// Aktifkan kembali via SIMANTAP_CBT_ENABLED=true setelah modulnya lengkap.
if (config('simantap.cbt_enabled', false)) {
    // ==================== CBT ROUTES ====================
    Route::middleware(['auth', 'role:guru'])->prefix('cbt')->name('cbt.')->group(function () {
        // Question Banks
        Route::prefix('question-banks')->name('question-banks.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Cbt\CbtQuestionBankController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Cbt\CbtQuestionBankController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Cbt\CbtQuestionBankController::class, 'store'])->name('store');
            Route::get('/{bank}', [\App\Http\Controllers\Cbt\CbtQuestionBankController::class, 'show'])->name('show');
            Route::get('/{bank}/edit', [\App\Http\Controllers\Cbt\CbtQuestionBankController::class, 'edit'])->name('edit');
            Route::put('/{bank}', [\App\Http\Controllers\Cbt\CbtQuestionBankController::class, 'update'])->name('update');
            Route::delete('/{bank}', [\App\Http\Controllers\Cbt\CbtQuestionBankController::class, 'destroy'])->name('destroy');
            Route::get('/{bank}/api/questions', [\App\Http\Controllers\Cbt\CbtQuestionBankController::class, 'apiGetQuestions'])->name('api.questions');
        });

        // Questions
        Route::prefix('questions')->name('questions.')->group(function () {
            Route::post('/{bank}', [\App\Http\Controllers\Cbt\CbtQuestionController::class, 'store'])->name('store');
            Route::put('/{question}', [\App\Http\Controllers\Cbt\CbtQuestionController::class, 'update'])->name('update');
            Route::delete('/{question}', [\App\Http\Controllers\Cbt\CbtQuestionController::class, 'destroy'])->name('destroy');
        });

        // Test Sessions
        Route::prefix('sessions')->name('sessions.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'store'])->name('store');
            Route::get('/{session}', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'show'])->name('show');
            Route::post('/{session}/publish', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'publish'])->name('publish');
            Route::post('/{session}/start', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'start'])->name('start');
            Route::post('/{session}/complete', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'complete'])->name('complete');
            Route::get('/{session}/monitoring', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'monitoring'])->name('monitoring');
            Route::get('/{session}/api/monitoring', [\App\Http\Controllers\Cbt\CbtTestSessionController::class, 'apiMonitoring'])->name('api.monitoring');
        });

        // Integrity (Anti-Cheat)
        Route::prefix('integrity')->name('integrity.')->group(function () {
            Route::post('/{result}/log', [\App\Http\Controllers\Cbt\CbtIntegrityController::class, 'logViolation'])->name('log');
            Route::get('/{result}/violations', [\App\Http\Controllers\Cbt\CbtIntegrityController::class, 'getViolations'])->name('violations');
        });
    });

    // CBT Student Routes
    Route::middleware(['auth', 'role:siswa', 'force.password.change', 'cbt.session.lock', 'cbt.security'])->prefix('cbt/student')->name('cbt.student.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Cbt\CbtStudentController::class, 'index'])->name('index');
        Route::get('/{session}/start', [\App\Http\Controllers\Cbt\CbtStudentController::class, 'start'])->name('start');
        Route::post('/{result}/answer', [\App\Http\Controllers\Cbt\CbtStudentController::class, 'submitAnswer'])->name('answer');
        Route::post('/{result}/submit', [\App\Http\Controllers\Cbt\CbtStudentController::class, 'submit'])->name('submit');
        Route::get('/{result}/result', [\App\Http\Controllers\Cbt\CbtStudentController::class, 'result'])->name('result');
    });
}

// ==================== ADMIN CBT MANAGEMENT ====================
Route::middleware(['auth', 'role:admin', 'force.password.change'])->prefix('admin/cbt')->name('admin.cbt.')->group(function () {
    // Question Banks
    Route::prefix('banks')->name('banks.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'store'])->name('store');
        Route::get('/{bank}', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'show'])->name('show');
        Route::get('/{bank}/edit', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'edit'])->name('edit');
        Route::put('/{bank}', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'update'])->name('update');
        Route::delete('/{bank}', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'destroy'])->name('destroy');
        Route::post('/{bank}/toggle-status', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'toggleStatus'])->name('toggle-status');
        Route::get('/api/statistics', [\App\Http\Controllers\Admin\Cbt\CbtBankController::class, 'statistics'])->name('statistics');
    });
    
    // Test Sessions
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Cbt\CbtSessionController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\Cbt\CbtSessionController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\Cbt\CbtSessionController::class, 'store'])->name('store');
        Route::get('/{session}', [\App\Http\Controllers\Admin\Cbt\CbtSessionController::class, 'show'])->name('show');
        Route::post('/{session}/update-status', [\App\Http\Controllers\Admin\Cbt\CbtSessionController::class, 'updateStatus'])->name('update-status');
        Route::post('/{session}/force-complete', [\App\Http\Controllers\Admin\Cbt\CbtSessionController::class, 'forceComplete'])->name('force-complete');
        Route::delete('/{session}', [\App\Http\Controllers\Admin\Cbt\CbtSessionController::class, 'destroy'])->name('destroy');
        Route::get('/{session}/export/{format}', [\App\Http\Controllers\Admin\Cbt\CbtSessionController::class, 'exportResults'])->name('export');
    });
});

// ==================== ADMIN TRYOUT MANAGEMENT ====================
Route::middleware(['auth', 'role:admin', 'force.password.change'])->prefix('admin/tryout')->name('admin.tryout.')->group(function () {
    Route::get('/statistics', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'statistics'])->name('statistics');
    
    // Packages
    Route::prefix('packages')->name('packages.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'store'])->name('store');
        Route::get('/{package}', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'show'])->name('show');
        Route::get('/{package}/edit', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'edit'])->name('edit');
        Route::put('/{package}', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'update'])->name('update');
        Route::delete('/{package}', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{package}/toggle-status', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{package}/duplicate', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'duplicate'])->name('duplicate');
    });
    
    // All Sessions
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'allSessions'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'createSession'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'storeSession'])->name('store');
        Route::get('/{session}', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'showSession'])->name('show');
        Route::get('/{session}/edit', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'editSession'])->name('edit');
        Route::put('/{session}', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'updateSession'])->name('update');
        Route::delete('/{session}', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'destroySession'])->name('destroy');
        Route::post('/{session}/force-complete', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'forceCompleteSession'])->name('force-complete');
    });
    
    // All Results
    Route::prefix('results')->name('results.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'allResults'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'exportResults'])->name('export');
        Route::get('/{result}', [\App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController::class, 'showResult'])->name('show');
    });
});

// ==================== ADMIN TKA MANAGEMENT ====================
Route::middleware(['auth', 'role:admin', 'force.password.change'])->prefix('admin/tka')->name('admin.tka.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'index'])->name('dashboard');
    
    // Dimensions Management
    Route::prefix('dimensions')->name('dimensions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'dimensions'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'createDimension'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'storeDimension'])->name('store');
        Route::get('/{dimension}', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'showDimension'])->name('show');
        Route::get('/{dimension}/edit', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'editDimension'])->name('edit');
        Route::put('/{dimension}', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'updateDimension'])->name('update');
        Route::post('/{dimension}/toggle-status', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'toggleDimensionStatus'])->name('toggle-status');
    });
    
    // Assessments Management
    Route::prefix('assessments')->name('assessments.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'assessments'])->name('index');
        Route::get('/siswa/{siswaId}/history', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'studentHistory'])->name('history');
    });
    
    // Early Warnings Management
    Route::prefix('warnings')->name('warnings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'earlyWarnings'])->name('index');
        Route::get('/{warning}', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'showWarning'])->name('show');
        Route::post('/{warning}/update-status', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'updateWarningStatus'])->name('update-status');
    });
    
    // Export & Reports
    Route::post('/export', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'export'])->name('export');
    Route::post('/generate-report', [\App\Http\Controllers\Admin\Tka\TkaAdminController::class, 'generateReport'])->name('generate-report');
});

// ==================== TRYOUT ROUTES ====================
Route::middleware(['auth', 'role:guru'])->prefix('tryout')->name('tryout.')->group(function () {
    // Categories (nonaktif sementara: controller belum ada — milik area Kiro)
    // Route::resource('categories', \App\Http\Controllers\Tryout\TryoutCategoryController::class)->except(['show']);
    
    // Packages
    Route::resource('packages', \App\Http\Controllers\Tryout\TryoutPackageController::class);
    
    // Sessions
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'store'])->name('store');
        Route::get('/{session}', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'show'])->name('show');
        Route::post('/{session}/publish', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'publish'])->name('publish');
        Route::post('/{session}/start', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'start'])->name('start');
        Route::post('/{session}/complete', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'complete'])->name('complete');
        Route::get('/{session}/monitoring', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'monitoring'])->name('monitoring');
        Route::get('/{session}/api/monitoring', [\App\Http\Controllers\Tryout\TryoutSessionController::class, 'apiMonitoring'])->name('api.monitoring');
    });
});

// Tryout Student Routes
Route::middleware(['auth', 'role:siswa', 'force.password.change'])->prefix('tryout/student')->name('tryout.student.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Tryout\TryoutStudentController::class, 'index'])->name('index');
    Route::get('/{session}/start', [\App\Http\Controllers\Tryout\TryoutStudentController::class, 'start'])->name('start');
    Route::post('/{result}/answer', [\App\Http\Controllers\Tryout\TryoutStudentController::class, 'submitAnswer'])->name('answer');
    Route::post('/{result}/complete', [\App\Http\Controllers\Tryout\TryoutStudentController::class, 'complete'])->name('complete');
    Route::get('/{result}/result', [\App\Http\Controllers\Tryout\TryoutStudentController::class, 'result'])->name('result');
});

// ==================== TKA ENHANCED ROUTES ====================
Route::middleware(['auth', 'role:guru'])->prefix('tka')->name('tka.')->group(function () {
    // Dimensions
    Route::resource('dimensions', \App\Http\Controllers\Tka\TkaDimensionController::class);
    Route::post('/dimensions/{dimension}/item', [\App\Http\Controllers\Tka\TkaDimensionController::class, 'addItem'])->name('dimensions.add-item');
    Route::delete('/dimensions/{dimension}/item/{item}', [\App\Http\Controllers\Tka\TkaDimensionController::class, 'destroyItem'])->name('dimensions.destroy-item');
    Route::get('/dimensions/{dimension}/api/items', [\App\Http\Controllers\Tka\TkaDimensionController::class, 'getItems'])->name('dimensions.api.items');
    
    // Assessments
    Route::resource('assessments', \App\Http\Controllers\Tka\TkaAssessmentController::class);
    Route::get('/assessments/siswa/{siswaId}', [\App\Http\Controllers\Tka\TkaAssessmentController::class, 'bySiswa'])->name('assessments.by-siswa');
    
    // Generate Parent Report
    Route::post('/assessments/{assessment}/generate-report', [\App\Http\Controllers\Tka\TkaParentController::class, 'generateReport'])->name('assessments.generate-report');
});

// TKA Parent Routes
Route::middleware(['auth', 'role:ortu'])->prefix('tka/parent')->name('tka.parent.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Tka\TkaParentController::class, 'index'])->name('index');
    Route::get('/{report}', [\App\Http\Controllers\Tka\TkaParentController::class, 'show'])->name('show');
});
