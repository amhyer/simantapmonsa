<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BobotController;
use App\Http\Controllers\Admin\Cbt\CbtBankController;
use App\Http\Controllers\Admin\Cbt\CbtSessionController;
use App\Http\Controllers\Admin\CetakController;
use App\Http\Controllers\Admin\DapodikImportController;
use App\Http\Controllers\Admin\DapodikPushController;
use App\Http\Controllers\Admin\DataReferensiController;
use App\Http\Controllers\Admin\KodeAksesController;
use App\Http\Controllers\Admin\KokurikulerController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MapelController;
use App\Http\Controllers\Admin\ModulController;
use App\Http\Controllers\Admin\PenilaianController;
use App\Http\Controllers\Admin\PerkembanganController;
use App\Http\Controllers\Admin\PetaKelasController;
use App\Http\Controllers\Admin\PresensiKartuController;
use App\Http\Controllers\Admin\PresensiLaporanController;
use App\Http\Controllers\Admin\PresensiMesinImportController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RaporMerdekaController;
use App\Http\Controllers\Admin\SekolahController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\SheetController;
use App\Http\Controllers\Admin\Tka\TkaAdminController;
use App\Http\Controllers\Admin\Tka\TkaLaporanSekolahController;
use App\Http\Controllers\Admin\TranskripController;
use App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ValidasiDataController;
use App\Http\Controllers\Api\DapodikController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterSekolahController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Cbt\CbtIntegrityController;
use App\Http\Controllers\Cbt\CbtQuestionBankController;
use App\Http\Controllers\Cbt\CbtQuestionController;
use App\Http\Controllers\Cbt\CbtStudentController;
use App\Http\Controllers\Cbt\CbtTestSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Guru\AnalisisController;
use App\Http\Controllers\Guru\CatatanController;
use App\Http\Controllers\Guru\DashboardController as GuruDashboard;
use App\Http\Controllers\Guru\DimensiController;
use App\Http\Controllers\Guru\ErapotGeneratorController;
use App\Http\Controllers\Guru\GuruKebiasaanController;
use App\Http\Controllers\Guru\ImportErapotController;
use App\Http\Controllers\Guru\InputNilaiController;
use App\Http\Controllers\Guru\IntegrasiController;
use App\Http\Controllers\Guru\IzinPresensiController;
use App\Http\Controllers\Guru\KehadiranController;
use App\Http\Controllers\Guru\KelasDigitalController;
use App\Http\Controllers\Guru\KuisController;
use App\Http\Controllers\Guru\LaporanController;
use App\Http\Controllers\Guru\MateriController;
use App\Http\Controllers\Guru\NilaiController;
use App\Http\Controllers\Guru\NilaiErapotController;
use App\Http\Controllers\Guru\PengaturanController;
use App\Http\Controllers\Guru\PembelajaranMendalam\AlokasiWaktuController;
use App\Http\Controllers\Guru\PembelajaranMendalam\AtpController;
use App\Http\Controllers\Guru\PembelajaranMendalam\BukuPaketController;
use App\Http\Controllers\Guru\PembelajaranMendalam\CpController;
use App\Http\Controllers\Guru\PembelajaranMendalam\GeneratorBatchController;
use App\Http\Controllers\Guru\PembelajaranMendalam\KalenderController;
use App\Http\Controllers\Guru\PembelajaranMendalam\KktpController;
use App\Http\Controllers\Guru\PembelajaranMendalam\KokurikulerPmController;
use App\Http\Controllers\Guru\PembelajaranMendalam\ProsemController;
use App\Http\Controllers\Guru\PembelajaranMendalam\ProtaController;
use App\Http\Controllers\Guru\PembelajaranMendalam\TandaTanganController;
use App\Http\Controllers\Guru\PembelajaranMendalam\TpController as PmTpController;
use App\Http\Controllers\Guru\PerangkatController;
use App\Http\Controllers\Guru\SiswaController;
use App\Http\Controllers\Guru\TKAController;
use App\Http\Controllers\Guru\TkaInovasiController;
use App\Http\Controllers\Guru\WaliKelasController;
use App\Http\Controllers\Kepsek\AktivitasController;
use App\Http\Controllers\Kepsek\HasilBelajarController;
use App\Http\Controllers\Kepsek\KebiasaanController as KepsekKebiasaanController;
use App\Http\Controllers\Kepsek\PerangkatSupervisiController;
use App\Http\Controllers\Kepsek\PetaKelasController as KepsekPetaKelas;
use App\Http\Controllers\Kepsek\RekapController;
use App\Http\Controllers\Kepsek\SupervisiPmController;
use App\Http\Controllers\Kepsek\TkaLaporanController;
use App\Http\Controllers\Pengawas\DashboardController as PengawasDashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Media\SiswaFotoController;
use App\Http\Controllers\Ortu\CatatanController as OrtuCatatanController;
use App\Http\Controllers\Ortu\DashboardController as OrtuDashboard;
use App\Http\Controllers\Ortu\KebiasaanController as OrtuKebiasaanController;
use App\Http\Controllers\Ortu\KehadiranController as OrtuKehadiranController;
use App\Http\Controllers\Ortu\LaporanController as OrtuLaporanController;
use App\Http\Controllers\Ortu\RekapController as OrtuRekapController;
use App\Http\Controllers\Ortu\TkaRiwayatController;
use App\Http\Controllers\Pegawai\PresensiController;
use App\Http\Controllers\Security\CspReportController;
use App\Http\Controllers\Siswa\DashboardController as SiswaDashboard;
use App\Http\Controllers\Siswa\KuisController as SiswaKuisController;
use App\Http\Controllers\Siswa\MateriController as SiswaMateriController;
use App\Http\Controllers\Siswa\NilaiController as SiswaNilaiController;
use App\Http\Controllers\Siswa\ProfilController;
use App\Http\Controllers\Siswa\RaporController;
use App\Http\Controllers\Siswa\TkaSimulasiController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Controllers\Tka\KesiapanController;
use App\Http\Controllers\Tka\TkaAssessmentController;
use App\Http\Controllers\Tka\TkaDimensionController;
use App\Http\Controllers\Tka\TkaParentController;
use App\Http\Controllers\TkaController as TkaStatsController;
use App\Http\Controllers\Tryout\TryoutPackageController;
use App\Http\Controllers\Tryout\TryoutSessionController;
use App\Http\Controllers\Tryout\TryoutStudentController;
use App\Http\Middleware\CbtAccessMiddleware;
use App\Http\Middleware\CspReportOnlyHeaders;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Landing Page
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/demo', [LandingController::class, 'demo'])->name('demo');

// TKA Statistics (public view, protected write)
Route::get('/tka-statistik', [TkaStatsController::class, 'index'])->name('tka.statistik');
Route::get('/api/tka-statistik', [TkaStatsController::class, 'api'])->name('tka.api');
Route::post('/tka-statistik/refresh', [TkaStatsController::class, 'refresh'])
    ->middleware(['auth', 'role:admin,super_admin', 'throttle:10,1'])
    ->name('tka.refresh');

// Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    // Tidak ada registrasi untuk guru, siswa, maupun orang tua.
    // Akses /register langsung diarahkan ke Pendaftaran Akun Sekolah (jika diaktifkan).
    Route::get('/register', fn () => config('simantap.school_registration_enabled', true)
        ? redirect()->route('sekolah.register')
        : redirect()->route('login')->with('status', 'Pendaftaran mandiri ditutup.'))
        ->name('register');

    // Reset sandi mandiri. Pengguna yang belum punya email tetap diarahkan ke admin.
    Route::get('/lupa-sandi', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/lupa-sandi', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-sandi/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-sandi', [ResetPasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');

    // Pendaftaran Sekolah Baru (Multi-Tenant SaaS)
    Route::get('/daftar-sekolah', [RegisterSekolahController::class, 'create'])->name('sekolah.register');
    Route::post('/daftar-sekolah', [RegisterSekolahController::class, 'store'])->middleware('throttle:5,1')->name('sekolah.register.store');
});
Route::post('/csp-report', [CspReportController::class, 'store'])
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        ValidateCsrfToken::class,
        PreventRequestForgery::class,
        IdentifyTenant::class,
        CspReportOnlyHeaders::class,
    ])
    ->middleware('throttle:csp-report')
    ->name('csp.report');

Route::post('/logout', [LoginController::class, 'logout'])->middleware('throttle:30,1')->name('logout');

Route::get('/media/siswa/{siswa:uuid}/foto', [SiswaFotoController::class, 'show'])
    ->middleware(['auth', 'school.context'])
    ->name('media.siswa.foto');

// Force Password Change
Route::middleware(['auth'])->group(function () {
    Route::get('/force-password-change', [ForcePasswordChangeController::class, 'show'])->name('force-password-change.show');
    Route::put('/force-password-change', [ForcePasswordChangeController::class, 'update'])->middleware('throttle:10,1')->name('force-password-change.update');
});

// Profil & Ubah Kata Sandi Mandiri (semua peran terautentikasi)
Route::middleware(['auth', 'force.password.change'])->prefix('akun')->name('account.')->group(function () {
    Route::get('/profil', [\App\Http\Controllers\AccountProfileController::class, 'show'])->name('profile.show');
    Route::put('/profil', [\App\Http\Controllers\AccountProfileController::class, 'updateProfile'])->middleware('throttle:10,1')->name('profile.update');
    Route::put('/profil/password', [\App\Http\Controllers\AccountProfileController::class, 'updatePassword'])->middleware('throttle:10,1')->name('profile.password');
});

// Presensi mandiri pegawai (guru, kepala sekolah, dan tendik): kartu identitas QR & geofence sekolah.
Route::middleware(['auth', 'role:guru,kepsek,tendik', 'force.password.change', 'school.context', 'menu.telemetry'])
    ->prefix('presensi/pegawai')->name('presensi.pegawai.')->group(function () {
        Route::get('/', [PresensiController::class, 'index'])->name('index');
        Route::get('/kartu-identitas', [PresensiController::class, 'kartuSaya'])->name('kartu');
        Route::post('/scan', [PresensiController::class, 'scanMandiri'])->middleware('throttle:20,1')->name('scan');
        Route::post('/masuk', [PresensiController::class, 'masuk'])->middleware('throttle:10,1')->name('masuk');
        Route::post('/pulang', [PresensiController::class, 'pulang'])->middleware('throttle:10,1')->name('pulang');
    });

// Dashboard redirect (controller invokable agar route:cache berfungsi)
Route::get('/dashboard', DashboardRedirectController::class)->name('home');

// ==================== SUPER ADMIN ROUTES ====================
Route::middleware(['auth', 'role:super_admin', 'force.password.change', 'menu.telemetry'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->middleware('throttle:30,1')->name('tenants.store');
    Route::post('/tenants/bulk-destroy-expired', [TenantController::class, 'destroyExpiredTenants'])->middleware('throttle:10,1')->name('tenants.bulk-destroy-expired');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->middleware('throttle:30,1')->name('tenants.update');
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->middleware('throttle:15,1')->name('tenants.destroy');
    Route::post('/tenants/{tenant}/toggle', [TenantController::class, 'toggleActive'])->middleware('throttle:30,1')->name('tenants.toggle');
    Route::post('/tenants/{tenant}/impersonate', [TenantController::class, 'impersonate'])->middleware('throttle:20,1')->name('tenants.impersonate');
    Route::post('/tenants/{tenant}/reset-admin-password', [TenantController::class, 'resetAdminPassword'])->middleware('throttle:10,1')->name('tenants.reset-admin-password');
    Route::get('/users', [TenantController::class, 'users'])->name('users.index');
    Route::delete('/users/{user}', [TenantController::class, 'destroyUser'])->middleware('throttle:20,1')->name('users.destroy');
    Route::post('/users/bulk-destroy', [TenantController::class, 'bulkDestroyUsers'])->middleware('throttle:10,1')->name('users.bulk-destroy');
    Route::post('/users/{user}/assign-tenant', [TenantController::class, 'assignTenant'])->middleware('throttle:30,1')->name('users.assign-tenant');
});

Route::post('/superadmin/impersonate/leave', [TenantController::class, 'leaveImpersonate'])
    ->middleware('auth')
    ->name('superadmin.impersonate.leave');

Route::post('/admin/impersonate/leave', [UserController::class, 'leaveGuruImpersonate'])
    ->middleware('auth')
    ->name('admin.impersonate.leave');

// ==================== ADMIN ROUTES ====================
Route::middleware(['auth', 'role:admin', 'force.password.change', 'school.context', 'menu.telemetry'])->prefix('admin')->name('admin.')->group(function () {
    // Dasbor
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

    // Presensi pegawai, area geofence, dan pemetaan QR kartu siswa.
    Route::prefix('presensi')->name('presensi.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\PresensiController::class, 'index'])->name('index');
        Route::get('/dashboard-mesin', [App\Http\Controllers\Admin\PresensiController::class, 'dashboardMesin'])->name('dashboard.mesin');
        Route::get('/laporan', [PresensiLaporanController::class, 'index'])->name('laporan');
        Route::get('/laporan/export', [PresensiLaporanController::class, 'export'])->name('laporan.export');
        Route::get('/laporan/cetak', [PresensiLaporanController::class, 'cetak'])->name('laporan.cetak');
        Route::post('/lokasi', [App\Http\Controllers\Admin\PresensiController::class, 'storeLocation'])->name('lokasi.store');
        Route::put('/lokasi/{lokasi}', [App\Http\Controllers\Admin\PresensiController::class, 'updateLocation'])->name('lokasi.update');
        Route::delete('/lokasi/{lokasi}', [App\Http\Controllers\Admin\PresensiController::class, 'destroyLocation'])->name('lokasi.destroy');
        Route::post('/pegawai/koreksi', [App\Http\Controllers\Admin\PresensiController::class, 'koreksi'])->middleware('throttle:30,1')->name('pegawai.koreksi');
        Route::post('/mesin/ringkasan', [App\Http\Controllers\Admin\PresensiController::class, 'ringkasanMesin'])->name('mesin.ringkasan');
        Route::post('/mesin/ringkasan/flush', [App\Http\Controllers\Admin\PresensiController::class, 'flushRingkasan'])->name('mesin.ringkasan.flush');
        Route::post('/mesin/ringkasan/pull', [App\Http\Controllers\Admin\PresensiController::class, 'pullRingkasan'])->name('mesin.ringkasan.pull');

        Route::get('/mesin', [PresensiMesinImportController::class, 'index'])->name('mesin.index');
        Route::get('/mesin/sinkron', [PresensiMesinImportController::class, 'sinkron'])->middleware(['throttle:30,1'])->name('mesin.sinkron');
        Route::get('/mesin/template-karyawan', [PresensiMesinImportController::class, 'templateKaryawan'])->name('mesin.template.karyawan');
        Route::post('/mesin/import-karyawan', [PresensiMesinImportController::class, 'importKaryawan'])->middleware('throttle:10,1')->name('mesin.karyawan.import');
        Route::post('/mesin/tarik-karyawan', [PresensiMesinImportController::class, 'tarikKaryawan'])->middleware(['throttle:10,1', 'data.single-school:presensi-mesin'])->name('mesin.karyawan.tarik');
        Route::get('/mesin/template-log', [PresensiMesinImportController::class, 'templateLog'])->name('mesin.template.log');
        Route::post('/mesin/import-log', [PresensiMesinImportController::class, 'importLog'])->middleware('throttle:10,1')->name('mesin.log.import');
        Route::post('/mesin/tarik', [PresensiMesinImportController::class, 'tarik'])->middleware(['throttle:10,1', 'data.single-school:presensi-mesin'])->name('mesin.tarik');
        Route::post('/mesin/tarik-semua', [PresensiMesinImportController::class, 'tarikSemua'])->middleware(['throttle:5,1', 'data.single-school:presensi-mesin'])->name('mesin.tarik.semua');
        Route::get('/mesin/tarik-semua', [PresensiMesinImportController::class, 'formTarikSemua'])->name('mesin.tarik.semua.form');
        Route::post('/mesin/tes-koneksi', [PresensiMesinImportController::class, 'tesKoneksi'])->middleware(['throttle:30,1', 'data.single-school:presensi-mesin'])->name('mesin.tes');
        Route::get('/mesin/live', [PresensiMesinImportController::class, 'live'])->middleware(['throttle:30,1', 'data.single-school:presensi-mesin'])->name('mesin.live');
        Route::post('/mesin/pengaturan', [PresensiMesinImportController::class, 'updatePengaturan'])->middleware(['throttle:10,1', 'data.single-school:presensi-mesin'])->name('mesin.pengaturan');

        Route::get('/kartu-siswa', [PresensiKartuController::class, 'index'])->name('kartu.index');
        Route::post('/kartu-siswa/terbitkan-massal', [PresensiKartuController::class, 'issueSiswaMassal'])->middleware('throttle:10,1')->name('kartu.siswa.issue-massal');
        Route::get('/kartu-siswa/cetak-massal', [PresensiKartuController::class, 'cetakSiswaMassal'])->name('kartu.siswa.cetak-massal');
        Route::post('/kartu-siswa/{siswa:uuid}/terbitkan', [PresensiKartuController::class, 'issueSiswa'])->middleware('throttle:60,1')->name('kartu.siswa.issue');
        Route::post('/kartu-siswa/{siswa:uuid}/kembalikan', [PresensiKartuController::class, 'rollbackSiswa'])->middleware('throttle:60,1')->name('kartu.siswa.rollback');
        Route::get('/kartu-siswa/{siswa:uuid}/cetak', [PresensiKartuController::class, 'cetakSiswa'])->name('kartu.siswa.cetak');
        Route::post('/kartu-siswa/{siswa}/hubungkan', [PresensiKartuController::class, 'bind'])->middleware('throttle:60,1')->name('kartu.bind');
        Route::delete('/kartu-siswa/{siswa}', [PresensiKartuController::class, 'reset'])->name('kartu.reset');

        Route::post('/kartu-pegawai/terbitkan-massal', [PresensiKartuController::class, 'issuePegawaiMassal'])->middleware('throttle:10,1')->name('kartu.pegawai.issue-massal');
        Route::get('/kartu-pegawai/cetak-massal', [PresensiKartuController::class, 'cetakPegawaiMassal'])->name('kartu.pegawai.cetak-massal');
        Route::post('/kartu-pegawai/{pegawai:uuid}/terbitkan', [PresensiKartuController::class, 'issuePegawai'])->middleware('throttle:60,1')->name('kartu.pegawai.issue');
        Route::post('/kartu-pegawai/{pegawai:uuid}/kembalikan', [PresensiKartuController::class, 'rollbackPegawai'])->middleware('throttle:60,1')->name('kartu.pegawai.rollback');
        Route::get('/kartu-pegawai/{pegawai:uuid}/cetak', [PresensiKartuController::class, 'cetakPegawai'])->name('kartu.pegawai.cetak');
        Route::post('/kartu-pegawai/{pegawai:uuid}/hubungkan', [PresensiKartuController::class, 'bindPegawai'])->middleware('throttle:60,1')->name('kartu.pegawai.bind');
        Route::delete('/kartu-pegawai/{pegawai:uuid}', [PresensiKartuController::class, 'resetPegawai'])->name('kartu.pegawai.reset');
    });

    // Profile (cermin menu Profile e-Rapor)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:10,1')->name('profile.password');

    // Kelola Pengguna (Data Pengguna e-Rapor)
    Route::get('/data_pengguna', [UserController::class, 'index'])->name('data-pengguna.index');
    Route::get('/data_siswa', [UserController::class, 'siswaIndex'])->name('data-siswa.index');
    Route::get('/users/akun-massal', [UserController::class, 'akunMassal'])->name('users.akun-massal');
    Route::get('/users/siswa', [UserController::class, 'siswaIndex'])->name('users.siswa');
    Route::put('/users/siswa/{siswa}', [UserController::class, 'updateDataSiswa'])->middleware('throttle:30,1')->name('users.siswa.update');
    Route::post('/users/proses-massal', [UserController::class, 'prosesAkunMassal'])->middleware('throttle:5,1')->name('users.proses-massal');
    Route::post('/users/generate-semua', [UserController::class, 'generateSemuaPengguna'])->middleware('throttle:10,1')->name('users.generate-semua');
    Route::get('/users/generate-semua/ekspor/{token}', [UserController::class, 'eksporSandiAwal'])->middleware('throttle:admin-ekspor-sandi-awal')->name('users.ekspor-sandi-awal');
    Route::post('/users/reset-massal/pratinjau', [UserController::class, 'pratinjauResetMassal'])->middleware('throttle:admin-reset-massal-pratinjau')->name('users.reset-massal.pratinjau');
    Route::post('/users/reset-massal', [UserController::class, 'resetMassalPassword'])->middleware('throttle:admin-reset-massal')->name('users.reset-massal');
    Route::delete('/users/hapus-semua/{peran}', [UserController::class, 'destroyAll'])->middleware('throttle:5,1')->name('users.destroy-all');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonateGuru'])->name('users.impersonate');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPasswordInstan'])->middleware('throttle:30,1')->name('users.reset-password');
    Route::post('/users/{user}/toggle-aktif', [UserController::class, 'toggleAktif'])->middleware('throttle:30,1')->name('users.toggle-aktif');
    Route::resource('users', UserController::class)->except(['show']);

    // Peta Kelas
    Route::get('/peta-kelas', [PetaKelasController::class, 'index'])->name('peta-kelas.index');
    Route::get('/peta-kelas/daftar', [PetaKelasController::class, 'daftar'])->name('peta-kelas.daftar');
    Route::get('/peta-kelas/kelas-options', [PetaKelasController::class, 'getKelasOptions'])->name('peta-kelas.kelas-options');
    Route::get('/peta-kelas/{rombel}', [PetaKelasController::class, 'detail'])->whereNumber('rombel')->name('peta-kelas.detail');
    Route::post('/peta-kelas/{rombel}/toggle-kunci', [PetaKelasController::class, 'toggleKunciRapor'])->whereNumber('rombel')->name('peta-kelas.toggle-kunci');
    Route::post('/peta-kelas/toggle-kunci-semua', [PetaKelasController::class, 'toggleKunciSemua'])->name('peta-kelas.toggle-kunci-semua');
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
    Route::get('/data_guru', [DataReferensiController::class, 'guru'])->name('data-guru.index');
    Route::get('/edit_guru', [DataReferensiController::class, 'guru'])->name('edit-guru.index');
    Route::get('/referensi/guru', [DataReferensiController::class, 'guru'])->name('referensi.guru');
    Route::post('/referensi/guru', [DataReferensiController::class, 'storeGuru'])->middleware('throttle:30,1')->name('referensi.guru.store');
    Route::delete('/referensi/guru/{ptk}', [DataReferensiController::class, 'destroyGuru'])->middleware('throttle:30,1')->name('referensi.guru.destroy');
    Route::put('/referensi/gelar-ptk', [DataReferensiController::class, 'updateGelarPtk'])->middleware('throttle:30,1')->name('referensi.gelar-ptk.update');
    Route::get('/referensi/pembelajaran', [DataReferensiController::class, 'pembelajaran'])->name('referensi.pembelajaran');
    Route::post('/referensi/pembelajaran', [DataReferensiController::class, 'storePembelajaran'])->middleware('throttle:30,1')->name('referensi.pembelajaran.store');
    Route::delete('/referensi/pembelajaran/{pembelajaran}', [DataReferensiController::class, 'destroyPembelajaran'])->middleware('throttle:30,1')->name('referensi.pembelajaran.destroy');
    Route::get('/referensi/tanggal-rapor', [DataReferensiController::class, 'tanggalRapor'])->name('referensi.tanggal-rapor');
    Route::post('/referensi/tanggal-rapor', [DataReferensiController::class, 'storeTanggalRapor'])->middleware('throttle:30,1')->name('referensi.tanggal-rapor.store');
    Route::delete('/referensi/tanggal-rapor/{tanggalRapor}', [DataReferensiController::class, 'destroyTanggalRapor'])->middleware('throttle:30,1')->name('referensi.tanggal-rapor.destroy');
    Route::get('/referensi/kelompok-mapel', [DataReferensiController::class, 'kelompokMapel'])->name('referensi.kelompok-mapel');
    Route::get('/referensi/alignment', [DataReferensiController::class, 'alignmentCheck'])->name('referensi.alignment');
    Route::post('/referensi/alignment/refresh', [DataReferensiController::class, 'refreshAlignment'])->middleware('throttle:5,1')->name('referensi.alignment.refresh');
    Route::get('/referensi/alignment/export', [DataReferensiController::class, 'exportUnmatchedAlignment'])->name('referensi.alignment.export');
    Route::get('/referensi/mapping-rapor', [DataReferensiController::class, 'mappingRapor'])->name('referensi.mapping-rapor');
    Route::put('/referensi/mapel-meta', [DataReferensiController::class, 'updateMapelMeta'])->middleware('throttle:30,1')->name('referensi.mapel-meta.update');
    Route::get('/referensi/logo-ttd', [DataReferensiController::class, 'logoTtd'])->name('referensi.logo-ttd');
    Route::post('/referensi/logo-ttd', [DataReferensiController::class, 'storeLogoTtd'])->middleware('throttle:30,1')->name('referensi.logo-ttd.store');
    Route::get('/foto_siswa', [DataReferensiController::class, 'fotoSiswa'])->name('foto-siswa.index');
    Route::get('/referensi/foto-siswa', [DataReferensiController::class, 'fotoSiswa'])->name('referensi.foto-siswa');
    Route::post('/referensi/foto-siswa', [DataReferensiController::class, 'storeFotoSiswa'])->middleware('throttle:10,1')->name('referensi.foto-siswa.store');
    Route::delete('/referensi/foto-siswa/reset-semua', [DataReferensiController::class, 'resetSemuaFotoSiswa'])->middleware('throttle:10,1')->name('referensi.foto-siswa.reset-semua');
    Route::post('/referensi/foto-siswa/{siswa}', [DataReferensiController::class, 'storeFotoSiswaSatuan'])->middleware('throttle:30,1')->name('referensi.foto-siswa.satuan');
    Route::delete('/referensi/foto-siswa/{siswa}', [DataReferensiController::class, 'destroyFotoSiswa'])->middleware('throttle:30,1')->name('referensi.foto-siswa.destroy');

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
    Route::get('/cetak/kelengkapan', [CetakController::class, 'kelengkapan'])->name('cetak.kelengkapan');
    Route::get('/cetak/pengaturan-rapor', [CetakController::class, 'pengaturanRapor'])->name('cetak.pengaturan-rapor');
    Route::post('/cetak/pengaturan-rapor', [CetakController::class, 'simpanPengaturanRapor'])->name('cetak.pengaturan-rapor.simpan');
    Route::get('/cetak/modifikasi-tampilan', [CetakController::class, 'modifikasiTampilan'])->name('cetak.modifikasi-tampilan');
    Route::post('/cetak/modifikasi-tampilan', [CetakController::class, 'simpanModifikasiTampilan'])->name('cetak.modifikasi-tampilan.simpan');
    Route::get('/cetak/modifikasi-tampilan/preview', [CetakController::class, 'previewModifikasiTampilan'])->name('cetak.modifikasi-tampilan.preview');
    Route::get('/cetak/pts/{siswa}', [CetakController::class, 'showPts'])->name('cetak.pts.show');
    Route::get('/cetak/buku-induk/{siswa}', [CetakController::class, 'showBukuInduk'])->name('cetak.buku-induk.show');
    Route::get('/cetak/p5bk/{siswa}', [CetakController::class, 'showP5bk'])->name('cetak.p5bk.show');

    // Fitur Rapor Merdeka: Naik Kelas, Teknik Penilaian, Siswa Belajar, P5BK, Forum
    Route::get('/rapor/naik-kelas', [RaporMerdekaController::class, 'naikKelas'])->name('rapor.naik-kelas');
    Route::post('/rapor/naik-kelas', [RaporMerdekaController::class, 'simpanNaikKelas'])->name('rapor.naik-kelas.simpan');
    Route::get('/rapor/teknik-penilaian', [RaporMerdekaController::class, 'teknikPenilaian'])->name('rapor.teknik-penilaian');
    Route::post('/rapor/teknik-penilaian', [RaporMerdekaController::class, 'simpanTeknikPenilaian'])->name('rapor.teknik-penilaian.simpan');
    Route::get('/rapor/siswa-belajar', [RaporMerdekaController::class, 'siswaBelajar'])->name('rapor.siswa-belajar');
    Route::post('/rapor/siswa-belajar', [RaporMerdekaController::class, 'simpanSiswaBelajar'])->name('rapor.siswa-belajar.simpan');
    Route::get('/rapor/p5', [RaporMerdekaController::class, 'p5Index'])->name('rapor.p5.index');
    Route::post('/rapor/p5', [RaporMerdekaController::class, 'p5Simpan'])->name('rapor.p5.simpan');
    Route::post('/forum', [RaporMerdekaController::class, 'simpanForum'])->name('forum.simpan');
    Route::delete('/forum/{postId}', [RaporMerdekaController::class, 'hapusForum'])->name('forum.hapus');
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
    Route::get('/validasi-data', [ValidasiDataController::class, 'index'])->name('validasi-data.index');
    Route::post('/sekolah/search', [SekolahController::class, 'search'])->name('sekolah.search');
    Route::post('/sekolah/reset', [SekolahController::class, 'reset'])->name('sekolah.reset');
    Route::post('/sekolah', [SekolahController::class, 'update'])->name('sekolah.update');

    // Penyimpanan Data
    Route::get('/sheet', [SheetController::class, 'index'])->name('sheet.index');
    Route::post('/sheet/connect', [SheetController::class, 'connect'])->name('sheet.connect');
    Route::post('/sheet/disconnect', [SheetController::class, 'disconnect'])->name('sheet.disconnect');

    // Data & Pemulihan
    Route::get('/backup', [BackupController::class, 'index'])->middleware('data.single-school')->name('backup.index');
    Route::post('/backup/export', [BackupController::class, 'export'])->middleware('data.single-school')->name('backup.export');
    Route::post('/backup/import', [BackupController::class, 'import'])->middleware(['data.single-school', 'throttle:5,1'])->name('backup.import');
    Route::post('/backup/wipe', [BackupController::class, 'wipeAll'])->middleware(['data.single-school', 'throttle:10,5'])->name('backup.wipe');

    // Log Aktivitas
    Route::get('/log', [LogController::class, 'index'])->middleware('data.single-school')->name('log.index');

    // API Keys & Dapodik
    Route::prefix('api-keys')->name('api-keys.')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index'])->name('index');
        Route::post('/', [ApiKeyController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy'])->middleware('throttle:20,1')->name('destroy');
        Route::post('/{apiKey}/toggle', [ApiKeyController::class, 'toggle'])->middleware('throttle:20,1')->name('toggle');
    });

    // Semester Management
    Route::prefix('semester')->name('semester.')->group(function () {
        Route::get('/', [SemesterController::class, 'index'])->name('index');
        Route::post('/', [SemesterController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::delete('/{semester}', [SemesterController::class, 'destroy'])->middleware('throttle:20,1')->name('destroy');
        Route::post('/{semester}/activate', [SemesterController::class, 'activate'])->middleware('throttle:20,1')->name('activate');
    });

    // Dapodik Import
    Route::prefix('dapodik')->name('dapodik.')->group(function () {
        Route::get('/', [DapodikImportController::class, 'index'])->name('index');
        Route::post('/import', [DapodikImportController::class, 'import'])->middleware('throttle:15,1')->name('import');
        Route::post('/preview', [DapodikImportController::class, 'preview'])->middleware('throttle:20,1')->name('preview');
        Route::get('/log/{id}', [DapodikImportController::class, 'logDetail'])->name('log-detail');
        Route::delete('/log/{id}', [DapodikImportController::class, 'logDestroy'])->middleware('throttle:20,1')->name('log-destroy');
        Route::post('/reset', [DapodikImportController::class, 'resetData'])->middleware('throttle:10,5')->name('reset');
        Route::get('/download-bridge', [DapodikImportController::class, 'downloadBridge'])->name('download-bridge');
    });

    // Dapodik WebService API (moved from api.php to get session auth)
    Route::prefix('dapodik/api')->name('dapodik.api.')->group(function () {
        Route::get('/config', [DapodikController::class, 'config'])->name('config');
        Route::post('/config', [DapodikController::class, 'saveConfig'])->middleware('throttle:20,1')->name('config.save');
        Route::post('/test-connection', [DapodikController::class, 'testConnection'])->middleware('throttle:15,1')->name('test-connection');
        Route::post('/preview-data', [DapodikController::class, 'preview'])->middleware('throttle:15,1')->name('preview');
        Route::post('/pull', [DapodikController::class, 'pull'])->middleware('throttle:15,1')->name('pull');
        Route::get('/semesters', [DapodikController::class, 'semesters'])->name('semesters');
        Route::post('/sync', [DapodikController::class, 'sync'])->middleware('throttle:15,1')->name('sync');
    });

    // Dapodik Push (Sync ke Dapodik Server)
    Route::prefix('dapodik/push')->name('dapodik.push.')->middleware(['throttle:10,1'])->group(function () {
        Route::get('/', [DapodikPushController::class, 'index'])->name('index');
        Route::post('/sekolah', [App\Http\Controllers\Api\DapodikPushController::class, 'pushSekolah'])->name('sekolah');
        Route::post('/peserta-didik', [App\Http\Controllers\Api\DapodikPushController::class, 'pushPesertaDidik'])->name('peserta-didik');
        Route::post('/gtk', [App\Http\Controllers\Api\DapodikPushController::class, 'pushGtk'])->name('gtk');
        Route::post('/rombel', [App\Http\Controllers\Api\DapodikPushController::class, 'pushRombel'])->name('rombel');
        Route::post('/jadwal', [App\Http\Controllers\Api\DapodikPushController::class, 'pushJadwal'])->name('jadwal');
        Route::post('/nilai-rapor', [App\Http\Controllers\Api\DapodikPushController::class, 'pushNilaiRapor'])->name('nilai-rapor');
        Route::post('/kehadiran', [App\Http\Controllers\Api\DapodikPushController::class, 'pushKehadiran'])->name('kehadiran');
        Route::get('/status', [App\Http\Controllers\Api\DapodikPushController::class, 'status'])->name('status');
    });

    // Pemantauan Kesiapan TKA (agregat sekolah + konfigurasi aturan & pemetaan)
    Route::prefix('tka-kesiapan')->name('tka-kesiapan.')->group(function () {
        Route::get('/', [KesiapanController::class, 'index'])->name('index');
        Route::get('/ekspor', [KesiapanController::class, 'ekspor'])->name('ekspor');
        Route::get('/pengaturan', [KesiapanController::class, 'pengaturan'])->name('pengaturan');
        Route::post('/aturan', [KesiapanController::class, 'simpanAturan'])->middleware('throttle:30,1')->name('aturan.simpan');
        Route::post('/pemetaan', [KesiapanController::class, 'simpanPemetaan'])->middleware('throttle:30,1')->name('pemetaan.simpan');
        Route::delete('/pemetaan/{id}', [KesiapanController::class, 'hapusPemetaan'])->whereNumber('id')->middleware('throttle:30,1')->name('pemetaan.hapus');
    });

    // Master Pembelajaran Mendalam Sekolah (dikelola Admin/Operator, Read-Only untuk Guru)
    Route::prefix('pembelajaran-mendalam')->name('pm.')->group(function () {
        Route::get('/alokasi', [AlokasiWaktuController::class, 'index'])->name('alokasi.index');
        Route::post('/alokasi', [AlokasiWaktuController::class, 'store'])->middleware('throttle:30,1')->name('alokasi.store');
        Route::post('/alokasi/standar', [AlokasiWaktuController::class, 'isiStandar'])->middleware('throttle:15,1')->name('alokasi.standar');
        Route::get('/alokasi/cetak-pdf', [AlokasiWaktuController::class, 'cetakPdf'])->name('alokasi.pdf');

        Route::get('/kalender', [KalenderController::class, 'index'])->name('kalender.index');
        Route::post('/kalender', [KalenderController::class, 'simpanKalender'])->middleware('throttle:30,1')->name('kalender.simpan');
        Route::post('/kalender/default', [KalenderController::class, 'isiDefault'])->middleware('throttle:15,1')->name('kalender.default');
        Route::post('/kalender/libur', [KalenderController::class, 'storeLibur'])->middleware('throttle:30,1')->name('kalender.libur.store');
        Route::delete('/kalender/libur/{libur}', [KalenderController::class, 'destroyLibur'])->middleware('throttle:30,1')->name('kalender.libur.destroy');
    });
});

// ==================== GURU ROUTES ====================
Route::middleware(['auth', 'role:guru', 'force.password.change', 'school.context', 'menu.telemetry'])->prefix('guru')->name('guru.')->group(function () {
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

    // E-Learning / Kelas Digital
    Route::prefix('kelas-digital')->name('kelas-digital.')->group(function () {
        Route::get('/', [KelasDigitalController::class, 'index'])->name('index');
        Route::get('/create', [KelasDigitalController::class, 'create'])->name('create');
        Route::post('/', [KelasDigitalController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/{kelasDigital}', [KelasDigitalController::class, 'show'])->name('show');
        Route::get('/{kelasDigital}/edit', [KelasDigitalController::class, 'edit'])->name('edit');
        Route::put('/{kelasDigital}', [KelasDigitalController::class, 'update'])->middleware('throttle:30,1')->name('update');
        Route::delete('/{kelasDigital}', [KelasDigitalController::class, 'destroy'])->middleware('throttle:30,1')->name('destroy');

        Route::post('/{kelasDigital}/modul', [KelasDigitalController::class, 'storeModul'])->middleware('throttle:30,1')->name('modul.store');
        Route::delete('/{kelasDigital}/modul/{modul}', [KelasDigitalController::class, 'destroyModul'])->middleware('throttle:30,1')->name('modul.destroy');

        Route::post('/{kelasDigital}/modul/{modul}/pertemuan', [KelasDigitalController::class, 'storePertemuan'])->middleware('throttle:30,1')->name('pertemuan.store');
        Route::delete('/{kelasDigital}/pertemuan/{pertemuan}', [KelasDigitalController::class, 'destroyPertemuan'])->middleware('throttle:30,1')->name('pertemuan.destroy');

        Route::post('/{kelasDigital}/pertemuan/{pertemuan}/item', [KelasDigitalController::class, 'storeItem'])->middleware('throttle:30,1')->name('item.store');
        Route::delete('/{kelasDigital}/item/{item}', [KelasDigitalController::class, 'destroyItem'])->middleware('throttle:30,1')->name('item.destroy');
        Route::get('/{kelasDigital}/item/{item}/unduh', [KelasDigitalController::class, 'downloadItem'])->name('item.download');

        Route::post('/{kelasDigital}/pertemuan/{pertemuan}/tugas', [KelasDigitalController::class, 'storeTugas'])->middleware('throttle:30,1')->name('tugas.store');
        Route::delete('/{kelasDigital}/tugas/{tugas}', [KelasDigitalController::class, 'destroyTugas'])->middleware('throttle:30,1')->name('tugas.destroy');
        Route::get('/{kelasDigital}/tugas/{tugas}/lampiran', [KelasDigitalController::class, 'downloadLampiranTugas'])->name('tugas.download');
        Route::get('/{kelasDigital}/tugas/{tugas}/pengumpulan', [KelasDigitalController::class, 'daftarPengumpulan'])->name('tugas.pengumpulan');
        Route::post('/{kelasDigital}/tugas/{tugas}/pengumpulan/{pengumpulan}/nilai', [KelasDigitalController::class, 'nilaiPengumpulan'])->middleware('throttle:60,1')->name('tugas.nilai');
        Route::get('/{kelasDigital}/pengumpulan/{pengumpulan}/unduh', [KelasDigitalController::class, 'downloadPengumpulan'])->name('pengumpulan.download');
    });

    // Pembelajaran Mendalam (Fase 1: Alokasi Waktu, CP, TP, ATP, Buku Paket SIBI, KKTP)
    Route::prefix('pembelajaran-mendalam')->name('pm.')->group(function () {
        // Konteks Aktif Persisten (Tahun Ajaran · Semester · Kelas)
        Route::post('/konteks', [AlokasiWaktuController::class, 'simpanKonteks'])->middleware('throttle:60,1')->name('konteks.simpan');

        // Alokasi Waktu
        Route::get('/alokasi', [AlokasiWaktuController::class, 'index'])->name('alokasi.index');
        Route::post('/alokasi', [AlokasiWaktuController::class, 'store'])->middleware('throttle:30,1')->name('alokasi.store');
        Route::post('/alokasi/standar', [AlokasiWaktuController::class, 'isiStandar'])->middleware('throttle:15,1')->name('alokasi.standar');
        Route::get('/alokasi/cetak-pdf', [AlokasiWaktuController::class, 'cetakPdf'])->name('alokasi.pdf');

        // Capaian Pembelajaran (CP)
        Route::get('/cp', [CpController::class, 'index'])->name('cp.index');
        Route::post('/cp', [CpController::class, 'store'])->middleware('throttle:30,1')->name('cp.store');
        Route::put('/cp/{cp}', [CpController::class, 'update'])->middleware('throttle:30,1')->name('cp.update');
        Route::delete('/cp/{cp}', [CpController::class, 'destroy'])->middleware('throttle:30,1')->name('cp.destroy');
        Route::post('/cp/ai', [CpController::class, 'aiGenerate'])->middleware('throttle:15,1')->name('cp.ai');
        Route::post('/cp/standar-bskap', [CpController::class, 'muatStandarBskap'])->middleware('throttle:15,1')->name('cp.standar');
        Route::post('/cp/import-excel', [CpController::class, 'importExcel'])->middleware('throttle:15,1')->name('cp.import');
        Route::get('/cp/export-excel', [CpController::class, 'exportExcel'])->name('cp.excel');
        Route::get('/cp/cetak-pdf', [CpController::class, 'cetakPdf'])->name('cp.pdf');

        // Tujuan Pembelajaran (TP)
        Route::get('/tp', [PmTpController::class, 'index'])->name('tp.index');
        Route::post('/tp', [PmTpController::class, 'store'])->middleware('throttle:30,1')->name('tp.store');
        Route::put('/tp/{tp}', [PmTpController::class, 'update'])->middleware('throttle:30,1')->name('tp.update');
        Route::delete('/tp/{tp}', [PmTpController::class, 'destroy'])->middleware('throttle:30,1')->name('tp.destroy');
        Route::post('/tp/ai', [PmTpController::class, 'aiGenerate'])->middleware('throttle:15,1')->name('tp.ai');
        Route::post('/tp/import-excel', [PmTpController::class, 'importExcel'])->middleware('throttle:15,1')->name('tp.import');
        Route::post('/tp/sinkron-erapor', [PmTpController::class, 'sinkronErapor'])->middleware('throttle:15,1')->name('tp.sinkron-erapor');
        Route::get('/tp/export-excel', [PmTpController::class, 'exportExcel'])->name('tp.excel');
        Route::get('/tp/cetak-pdf', [PmTpController::class, 'cetakPdf'])->name('tp.pdf');

        // Alur Tujuan Pembelajaran (ATP)
        Route::get('/atp', [AtpController::class, 'index'])->name('atp.index');
        Route::post('/atp', [AtpController::class, 'simpanAlur'])->middleware('throttle:30,1')->name('atp.simpan');
        Route::post('/atp/ai', [AtpController::class, 'aiGenerate'])->middleware('throttle:15,1')->name('atp.ai');
        Route::get('/atp/cetak-pdf', [AtpController::class, 'cetakPdf'])->name('atp.pdf');

        // Buku Paket (SIBI)
        Route::get('/buku-paket', [BukuPaketController::class, 'index'])->name('buku-paket.index');
        Route::post('/buku-paket', [BukuPaketController::class, 'store'])->middleware('throttle:30,1')->name('buku-paket.store');
        Route::post('/buku-paket/ai', [BukuPaketController::class, 'aiGenerate'])->middleware('throttle:15,1')->name('buku-paket.ai');

        // KKTP
        Route::get('/kktp', [KktpController::class, 'index'])->name('kktp.index');
        Route::post('/kktp/interval', [KktpController::class, 'simpanInterval'])->middleware('throttle:30,1')->name('kktp.interval');
        Route::post('/kktp/item', [KktpController::class, 'simpanItem'])->middleware('throttle:30,1')->name('kktp.item');
        Route::get('/kktp/export-excel', [KktpController::class, 'exportExcel'])->name('kktp.excel');
        Route::get('/kktp/cetak-pdf', [KktpController::class, 'cetakPdf'])->name('kktp.pdf');

        // Fase 2: Kalender Pendidikan & Hari Libur
        Route::get('/kalender', [KalenderController::class, 'index'])->name('kalender.index');
        Route::post('/kalender', [KalenderController::class, 'simpanKalender'])->middleware('throttle:30,1')->name('kalender.simpan');
        Route::post('/kalender/default', [KalenderController::class, 'isiDefault'])->middleware('throttle:15,1')->name('kalender.default');
        Route::post('/kalender/libur', [KalenderController::class, 'storeLibur'])->middleware('throttle:30,1')->name('kalender.libur.store');
        Route::delete('/kalender/libur/{libur}', [KalenderController::class, 'destroyLibur'])->middleware('throttle:30,1')->name('kalender.libur.destroy');

        // Fase 2: Program Tahunan (Prota)
        Route::get('/prota', [ProtaController::class, 'index'])->name('prota.index');
        Route::post('/prota', [ProtaController::class, 'store'])->middleware('throttle:30,1')->name('prota.store');
        Route::post('/prota/ambil-atp', [ProtaController::class, 'ambilDariAtp'])->middleware('throttle:15,1')->name('prota.ambil-atp');
        Route::post('/prota/ai', [ProtaController::class, 'aiGenerate'])->middleware('throttle:15,1')->name('prota.ai');
        Route::get('/prota/cetak-pdf', [ProtaController::class, 'cetakPdf'])->name('prota.pdf');

        // Fase 2: Program Semester (Prosem Matriks)
        Route::get('/prosem', [ProsemController::class, 'index'])->name('prosem.index');
        Route::post('/prosem/susun', [ProsemController::class, 'susunOtomatis'])->middleware('throttle:15,1')->name('prosem.susun');
        Route::post('/prosem', [ProsemController::class, 'storeBaris'])->middleware('throttle:30,1')->name('prosem.store');
        Route::delete('/prosem/{prosem}', [ProsemController::class, 'destroyBaris'])->middleware('throttle:30,1')->name('prosem.destroy');
        Route::get('/prosem/cetak-pdf', [ProsemController::class, 'cetakPdf'])->name('prosem.pdf');

        // Fase 3 & Sprint 3: Generator Batch (Jadwal + Pratinjau + Bundel 1 Semester) + Bantuan AI RPM & Jurnal
        Route::get('/generate', [GeneratorBatchController::class, 'index'])->name('generate.index');
        Route::post('/generate/pratinjau', [GeneratorBatchController::class, 'pratinjau'])->middleware('throttle:30,1')->name('generate.pratinjau');
        Route::post('/generate/batch', [GeneratorBatchController::class, 'generateBatch'])->middleware('throttle:15,1')->name('generate.batch');
        Route::post('/generate/rpm-ai', [GeneratorBatchController::class, 'generateRpmAi'])->middleware('throttle:15,1')->name('generate.rpm-ai');
        Route::post('/generate/jurnal-ai', [GeneratorBatchController::class, 'generateJurnalAi'])->middleware('throttle:15,1')->name('generate.jurnal-ai');
        Route::get('/bundel-pdf', [GeneratorBatchController::class, 'cetakBundelSemesterPdf'])->name('bundel.pdf');

        // Fase 3: Kokurikuler Pembelajaran Mendalam
        Route::get('/kokurikuler', [KokurikulerPmController::class, 'index'])->name('kokurikuler.index');
        Route::post('/kokurikuler', [KokurikulerPmController::class, 'store'])->middleware('throttle:30,1')->name('kokurikuler.store');
        Route::put('/kokurikuler/{kokurikuler}', [KokurikulerPmController::class, 'update'])->middleware('throttle:30,1')->name('kokurikuler.update');
        Route::delete('/kokurikuler/{kokurikuler}', [KokurikulerPmController::class, 'destroy'])->middleware('throttle:30,1')->name('kokurikuler.destroy');
        Route::post('/kokurikuler/ai', [KokurikulerPmController::class, 'aiGenerate'])->middleware('throttle:15,1')->name('kokurikuler.ai');
        Route::get('/kokurikuler/export-excel', [KokurikulerPmController::class, 'exportExcel'])->name('kokurikuler.excel');
        Route::get('/kokurikuler/cetak-pdf', [KokurikulerPmController::class, 'cetakPdf'])->name('kokurikuler.pdf');

        // Tanda Tangan Digital Guru (untuk cetakan PDF perangkat ajar)
        Route::get('/tanda-tangan', [TandaTanganController::class, 'index'])->name('ttd.index');
        Route::post('/tanda-tangan', [TandaTanganController::class, 'store'])->middleware('throttle:20,1')->name('ttd.store');
        Route::delete('/tanda-tangan', [TandaTanganController::class, 'hapus'])->middleware('throttle:20,1')->name('ttd.hapus');
    });

    // Perangkat Ajar (CP, TP, ATP, Prota, Promes, Modul Ajar/RPP, KKM/KKTP) & Jurnal Mengajar — Gelombang 6
    Route::prefix('perangkat')->name('perangkat.')->group(function () {
        Route::get('/jurnal', [PerangkatController::class, 'jurnalIndex'])->name('jurnal.index');
        Route::post('/jurnal', [PerangkatController::class, 'jurnalStore'])->middleware('throttle:30,1')->name('jurnal.store');
        Route::delete('/jurnal/{jurnal}', [PerangkatController::class, 'jurnalDestroy'])->middleware('throttle:30,1')->name('jurnal.destroy');
        Route::get('/jurnal/cetak-pdf', [PerangkatController::class, 'jurnalCetakPdf'])->name('jurnal.pdf');

        Route::get('/', [PerangkatController::class, 'index'])->name('index');
        Route::get('/buat', [PerangkatController::class, 'create'])->name('create');
        Route::post('/', [PerangkatController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/{perangkat}', [PerangkatController::class, 'show'])->name('show');
        Route::get('/{perangkat}/ubah', [PerangkatController::class, 'edit'])->name('edit');
        Route::put('/{perangkat}', [PerangkatController::class, 'update'])->middleware('throttle:30,1')->name('update');
        Route::delete('/{perangkat}', [PerangkatController::class, 'destroy'])->middleware('throttle:30,1')->name('destroy');
        Route::get('/{perangkat}/cetak-pdf', [PerangkatController::class, 'cetakPdf'])->name('pdf');
        Route::get('/{perangkat}/lampiran', [PerangkatController::class, 'downloadLampiran'])->name('lampiran');
    });

    // Kuis & Soal
    Route::resource('kuis', KuisController::class)->except(['show']);
    Route::post('/kuis/{kuis}/toggle', [KuisController::class, 'toggle'])->name('kuis.toggle');
    Route::get('/kuis/{kuis}/hasil', [KuisController::class, 'hasil'])->name('kuis.hasil');

    // Input Nilai Cepat
    Route::get('/input-nilai', [InputNilaiController::class, 'index'])->name('input-nilai.index');
    Route::get('/input-nilai/data', [InputNilaiController::class, 'getNilai'])->name('input-nilai.data');
    Route::post('/input-nilai/simpan', [InputNilaiController::class, 'simpanNilai'])->middleware('throttle:60,1')->name('input-nilai.simpan');
    Route::post('/input-nilai/paste', [InputNilaiController::class, 'pasteFromExcel'])->middleware('throttle:30,1')->name('input-nilai.paste');

    // Input Nilai e-Rapor
    Route::get('/nilai-erapor', [NilaiErapotController::class, 'index'])->name('nilai-erapor.index');
    Route::post('/nilai-erapor/simpan', [NilaiErapotController::class, 'simpan'])->middleware('throttle:60,1')->name('nilai-erapor.simpan');
    Route::post('/nilai-erapor/auto-save', [NilaiErapotController::class, 'autoSave'])->middleware('throttle:120,1')->name('nilai-erapor.auto-save');
    Route::get('/nilai-erapor/deskripsi/{siswaId}', [NilaiErapotController::class, 'deskripsi'])->name('nilai-erapor.deskripsi');
    Route::get('/nilai-erapor/template', [ImportErapotController::class, 'downloadTemplate'])->name('nilai-erapor.template');
    Route::post('/nilai-erapor/import', [ImportErapotController::class, 'import'])->middleware('throttle:15,1')->name('nilai-erapor.import');
    Route::get('/nilai-erapor/export', [ImportErapotController::class, 'export'])->name('nilai-erapor.export');

    // Generate e-Rapor (PDF & Excel)
    Route::prefix('erapor')->name('erapor.')->group(function () {
        Route::get('/', [ErapotGeneratorController::class, 'index'])->name('index');
        Route::get('/pdf/{siswa}', [ErapotGeneratorController::class, 'generatePdf'])->name('pdf');
        Route::post('/pdf-bulk', [ErapotGeneratorController::class, 'generateBulkPdf'])->middleware('throttle:15,1')->name('pdf-bulk');
        Route::get('/excel', [ErapotGeneratorController::class, 'exportExcel'])->name('excel');
    });

    // Profil Lulusan
    Route::resource('dimensi', DimensiController::class)->only(['index', 'store', 'destroy']);

    // Daftar Nilai
    Route::resource('nilai', NilaiController::class)->except(['show']);

    // Pengajuan orang tua diverifikasi wali kelas, tidak otomatis mengubah presensi.
    Route::get('/kehadiran/izin', [IzinPresensiController::class, 'index'])->name('kehadiran.izin.index');
    Route::post('/kehadiran/izin/{izin}/review', [IzinPresensiController::class, 'review'])->middleware('throttle:30,1')->name('kehadiran.izin.review');

    // Kehadiran harian dan catatan tambahan mapel melalui QR.
    Route::get('/kehadiran/jadwal/{jadwal}', [KehadiranController::class, 'jadwal'])->name('kehadiran.jadwal');
    Route::get('/kehadiran/jadwal/{jadwal}/kartu-kelas', [KehadiranController::class, 'cetakKartuKelas'])->name('kehadiran.kartu-kelas');
    Route::post('/kehadiran/jadwal/{jadwal}/scan', [KehadiranController::class, 'scan'])->middleware('throttle:120,1')->name('kehadiran.scan');
    Route::post('/kehadiran/jadwal/{jadwal}/tandai-semua', [KehadiranController::class, 'tandaiSemuaHadir'])->name('kehadiran.tandai-semua');
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

    // Kesiapan TKA & Inovasi Kognitif
    Route::get('/tka', [TKAController::class, 'index'])->name('tka.index');
    Route::get('/tka/analisis', [TKAController::class, 'analysis'])->name('tka.analysis');
    Route::get('/tka/kognitif', [TkaInovasiController::class, 'kognitifIndex'])->name('tka.kognitif.index');
    Route::get('/tka/kognitif/{siswaUuid}', [TkaInovasiController::class, 'kognitifShow'])->name('tka.kognitif.show');
    Route::post('/tka/rekomendasi/generate', [TkaInovasiController::class, 'generateRekomendasi'])->name('tka.rekomendasi.generate');
    Route::post('/tka/rekomendasi/{recommendation}', [TkaInovasiController::class, 'updateRekomendasi'])->name('tka.rekomendasi.update');
    Route::get('/tka/integrasi-erapor', [TkaInovasiController::class, 'integrasiErapor'])->name('tka.integrasi-erapor.index');
    Route::post('/tka/integrasi-erapor/sync', [TkaInovasiController::class, 'syncErapor'])->middleware('throttle:30,1')->name('tka.integrasi-erapor.sync');
    Route::get('/tka/laporan-kelas', [TkaInovasiController::class, 'laporanKelas'])->name('tka.laporan-kelas.index');
    Route::get('/tka/{id}', [TKAController::class, 'show'])->name('tka.show');
    Route::post('/tka', [TKAController::class, 'store'])->name('tka.store');

    // Pemantauan Kesiapan Siswa Menghadapi TKA Kementerian (kelas 4 → 5 → 6)
    Route::prefix('tka-kesiapan')->name('tka-kesiapan.')->group(function () {
        Route::get('/', [KesiapanController::class, 'index'])->name('index');
        Route::get('/ekspor', [KesiapanController::class, 'ekspor'])->name('ekspor');
        Route::get('/siswa/{uuid}', [KesiapanController::class, 'siswa'])->name('siswa');
        Route::post('/siswa/{uuid}/susun', [KesiapanController::class, 'susun'])->middleware('throttle:30,1')->name('susun');
        Route::patch('/tindak-lanjut/{tindakLanjut}', [KesiapanController::class, 'perbarui'])->middleware('throttle:60,1')->name('perbarui');
    });

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

    // Tujuan Pembelajaran (TP), Projek P5, Cek Penilaian & Menu Wali Kelas (e-Rapor SD 2025.1)
    Route::get('/tujuan-pembelajaran', [WaliKelasController::class, 'tujuanPembelajaran'])->name('tp.index');
    Route::post('/tujuan-pembelajaran', [WaliKelasController::class, 'simpanTujuanPembelajaran'])->middleware('throttle:30,1')->name('tp.store');
    Route::delete('/tujuan-pembelajaran/{id}', [WaliKelasController::class, 'hapusTujuanPembelajaran'])->middleware('throttle:30,1')->name('tp.destroy');

    Route::get('/p5', [WaliKelasController::class, 'p5Index'])->name('p5.index');
    Route::post('/p5', [WaliKelasController::class, 'p5Simpan'])->middleware('throttle:30,1')->name('p5.simpan');

    Route::get('/cek-penilaian', [WaliKelasController::class, 'cekPenilaian'])->name('cek-penilaian.index');

    Route::prefix('walas')->name('walas.')->group(function () {
        Route::get('/ekskul', [WaliKelasController::class, 'ekskul'])->name('ekskul');
        Route::post('/ekskul', [WaliKelasController::class, 'simpanEkskul'])->middleware('throttle:30,1')->name('ekskul.simpan');
        Route::get('/naik-kelas', [WaliKelasController::class, 'naikKelas'])->name('naik-kelas');
        Route::post('/naik-kelas', [WaliKelasController::class, 'simpanNaikKelas'])->middleware('throttle:30,1')->name('naik-kelas.simpan');
        Route::get('/status-penilaian', [WaliKelasController::class, 'statusPenilaianKelas'])->name('status-penilaian');
        Route::get('/leger', [WaliKelasController::class, 'legerKelas'])->name('leger');
        Route::get('/rapor-p5/{siswa}', [WaliKelasController::class, 'raporP5'])->name('rapor-p5');
        Route::get('/transkrip', [WaliKelasController::class, 'transkripIndex'])->name('transkrip');
        Route::post('/transkrip/{siswa}', [WaliKelasController::class, 'transkripSimpan'])->middleware('throttle:30,1')->name('transkrip.simpan');
        Route::get('/transkrip/{siswa}/cetak', [WaliKelasController::class, 'transkripCetak'])->name('transkrip.cetak');
    });
});

// ==================== SISWA ROUTES ====================
Route::middleware(['auth', 'role:siswa', 'force.password.change', 'school.context', 'menu.telemetry'])->prefix('siswa')->name('siswa.')->group(function () {
    Route::get('/dashboard', [SiswaDashboard::class, 'index'])->name('dashboard');
    Route::get('/materi', [SiswaMateriController::class, 'index'])->name('materi.index');
    Route::get('/materi/{id}', [SiswaMateriController::class, 'show'])->name('materi.show');

    // E-Learning / Kelas Digital Siswa
    Route::prefix('kelas-digital')->name('kelas-digital.')->group(function () {
        Route::get('/', [App\Http\Controllers\Siswa\KelasDigitalController::class, 'index'])->name('index');
        Route::get('/{kelasDigital}', [App\Http\Controllers\Siswa\KelasDigitalController::class, 'show'])->name('show');
        Route::get('/{kelasDigital}/pertemuan/{pertemuan}', [App\Http\Controllers\Siswa\KelasDigitalController::class, 'pertemuan'])->name('pertemuan');
        Route::post('/{kelasDigital}/pertemuan/{pertemuan}/selesai', [App\Http\Controllers\Siswa\KelasDigitalController::class, 'tandaiSelesai'])->middleware('throttle:60,1')->name('pertemuan.selesai');
        Route::post('/{kelasDigital}/tugas/{tugas}/kumpul', [App\Http\Controllers\Siswa\KelasDigitalController::class, 'kumpulTugas'])->middleware('throttle:30,1')->name('tugas.kumpul');
        Route::get('/{kelasDigital}/item/{item}/unduh', [App\Http\Controllers\Siswa\KelasDigitalController::class, 'downloadItem'])->name('item.download');
        Route::get('/{kelasDigital}/tugas/{tugas}/lampiran', [App\Http\Controllers\Siswa\KelasDigitalController::class, 'downloadLampiranTugas'])->name('tugas.download');
        Route::get('/{kelasDigital}/pengumpulan/{pengumpulan}/unduh', [App\Http\Controllers\Siswa\KelasDigitalController::class, 'downloadPengumpulan'])->name('pengumpulan.download');
    });
    Route::get('/kuis', [SiswaKuisController::class, 'index'])->name('kuis.index');
    Route::get('/kuis/{kuis}', [SiswaKuisController::class, 'show'])->name('kuis.show');
    Route::post('/kuis/{kuis}/submit', [SiswaKuisController::class, 'submit'])->middleware('throttle:10,1')->name('kuis.submit');
    Route::get('/nilai', [SiswaNilaiController::class, 'index'])->name('nilai.index');
    Route::get('/nilai/{jenis}', [SiswaNilaiController::class, 'detail'])->name('nilai.detail');
    Route::get('/rapor/capaian', [RaporController::class, 'capaian'])->name('rapor.capaian');
    Route::get('/rapor/unduh', [RaporController::class, 'unduh'])->name('rapor.unduh');
    Route::get('/rapor/cetak/akademik', [RaporController::class, 'cetakAkademik'])->name('rapor.cetak.akademik');
    Route::get('/rapor/cetak/p5', [RaporController::class, 'cetakP5'])->name('rapor.cetak.p5');
    Route::get('/rapor/cetak/pelengkap', [RaporController::class, 'cetakPelengkap'])->name('rapor.cetak.pelengkap');
    Route::get('/profil', [ProfilController::class, 'show'])->name('profil.show');
    Route::get('/kartu-identitas', [ProfilController::class, 'kartu'])->name('kartu.cetak');
    Route::get('/profil/edit', [ProfilController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');

    // Simulasi TKA Mandiri & Adaptif (Pusmendik Kemendikdasmen)
    Route::prefix('tka-simulasi')->name('tka-simulasi.')->group(function () {
        Route::get('/', [TkaSimulasiController::class, 'index'])->name('index');
        Route::get('/kognitif', [TkaSimulasiController::class, 'profilKognitif'])->name('kognitif');
        Route::post('/adaptif/{paket}/start', [TkaSimulasiController::class, 'startAdaptif'])->name('adaptif.start');
        Route::get('/adaptif/{simulation}/play', [TkaSimulasiController::class, 'playAdaptif'])->name('adaptif.play');
        Route::post('/adaptif/{simulation}/answer', [TkaSimulasiController::class, 'answerAdaptif'])->middleware('throttle:120,1')->name('adaptif.answer');
        Route::get('/adaptif/{simulation}/result', [TkaSimulasiController::class, 'resultAdaptif'])->name('adaptif.result');
        Route::get('/{id}/confirm', [TkaSimulasiController::class, 'confirm'])->name('confirm');
        Route::post('/{id}/start', [TkaSimulasiController::class, 'start'])->name('start');
        Route::get('/{id}/exam', [TkaSimulasiController::class, 'exam'])->name('exam');
        Route::post('/{id}/answer', [TkaSimulasiController::class, 'answer'])->middleware('throttle:120,1')->name('answer');
        Route::post('/{id}/submit', [TkaSimulasiController::class, 'submit'])->name('submit');
        Route::get('/{id}/result', [TkaSimulasiController::class, 'result'])->name('result');
    });
    Route::get('/tka-kesiapan', [KesiapanController::class, 'mandiri'])->name('tka-kesiapan.index');
});

// ==================== ORANG TUA ROUTES ====================
Route::middleware(['auth', 'role:ortu', 'force.password.change', 'school.context', 'menu.telemetry'])->prefix('ortu')->name('ortu.')->group(function () {
    Route::get('/dashboard', [OrtuDashboard::class, 'index'])->name('dashboard');
    Route::get('/kebiasaan', [OrtuKebiasaanController::class, 'index'])->name('kebiasaan.index');
    Route::post('/kebiasaan', [OrtuKebiasaanController::class, 'store'])->name('kebiasaan.store');
    Route::get('/nilai', [DashboardController::class, 'ortuNilai'])->name('nilai.index');
    Route::get('/rekap', [OrtuRekapController::class, 'index'])->name('rekap.index');
    Route::get('/kehadiran', [OrtuKehadiranController::class, 'index'])->name('kehadiran.index');
    Route::post('/kehadiran/izin', [App\Http\Controllers\Ortu\IzinPresensiController::class, 'store'])->middleware('throttle:10,1')->name('kehadiran.izin.store');
    Route::get('/catatan', [OrtuCatatanController::class, 'index'])->name('catatan.index');
    Route::get('/laporan', [OrtuLaporanController::class, 'index'])->name('laporan.index');
    Route::get('/tka-riwayat', [TkaRiwayatController::class, 'index'])->name('tka-riwayat.index');
    Route::get('/tka-riwayat/{simulation}', [TkaRiwayatController::class, 'show'])->name('tka-riwayat.show');
    Route::post('/tka-riwayat/{simulation}/konfirmasi', [TkaRiwayatController::class, 'konfirmasiBaca'])->middleware('throttle:30,1')->name('tka-riwayat.konfirmasi');
    Route::get('/tka-kesiapan', [KesiapanController::class, 'mandiri'])->name('tka-kesiapan.index');
});

// ==================== KEPSEK ROUTES ====================
Route::middleware(['auth', 'role:kepsek', 'force.password.change', 'school.context', 'menu.telemetry'])->prefix('kepsek')->name('kepsek.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Kepsek\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/pantau', [App\Http\Controllers\Kepsek\DashboardController::class, 'pantau'])->name('pantau.index');
    Route::get('/rekap', [RekapController::class, 'index'])->name('rekap.index');
    Route::get('/rekap/{guruId}', [RekapController::class, 'detail'])->name('rekap.detail');
    Route::get('/rekap/unduh/csv', [RekapController::class, 'unduh'])->name('rekap.unduh');
    Route::get('/peta-kelas', [KepsekPetaKelas::class, 'index'])->name('peta-kelas.index');
    Route::get('/peta-kelas/{guruId}', [KepsekPetaKelas::class, 'detail'])->whereNumber('guruId')->name('peta-kelas.detail');
    Route::get('/hasil-belajar', [HasilBelajarController::class, 'index'])->name('hasil-belajar.index');
    Route::get('/kebiasaan', [KepsekKebiasaanController::class, 'index'])->name('kebiasaan.index');
    Route::get('/aktivitas', [AktivitasController::class, 'index'])->name('aktivitas.index');
    Route::get('/tka-laporan', [TkaLaporanController::class, 'index'])->name('tka-laporan.index');
    Route::get('/tka-laporan/export-csv', [TkaLaporanController::class, 'exportCsv'])->name('tka-laporan.export');
    Route::get('/tka-kesiapan', [KesiapanController::class, 'index'])->name('tka-kesiapan.index');
    Route::get('/tka-kesiapan/ekspor', [KesiapanController::class, 'ekspor'])->name('tka-kesiapan.ekspor');
    Route::get('/perangkat', [PerangkatSupervisiController::class, 'index'])->name('perangkat.index');
    Route::get('/perangkat/{perangkat}/cetak-pdf', [PerangkatSupervisiController::class, 'cetakPdf'])->name('perangkat.pdf');

    // Sprint 2: Supervisi Pembelajaran Mendalam Kepala Sekolah (+ Persetujuan Digital)
    Route::prefix('supervisi-pm')->name('supervisi-pm.')->group(function () {
        Route::get('/', [SupervisiPmController::class, 'index'])->name('index');
        Route::get('/rekap-pdf', [SupervisiPmController::class, 'cetakRekapPdf'])->name('pdf');
        Route::get('/guru/{guru:uuid}', [SupervisiPmController::class, 'show'])->name('show');
        Route::post('/guru/{guru:uuid}/keputusan', [SupervisiPmController::class, 'simpanKeputusan'])
            ->middleware('throttle:30,1')
            ->name('keputusan');
    });

    // Tanda Tangan Digital Mandiri Kepala Sekolah
    Route::get('/tanda-tangan', [SupervisiPmController::class, 'tandaTanganIndex'])->name('ttd.index');
    Route::post('/tanda-tangan', [SupervisiPmController::class, 'tandaTanganStore'])->middleware('throttle:20,1')->name('ttd.store');
    Route::delete('/tanda-tangan', [SupervisiPmController::class, 'tandaTanganHapus'])->middleware('throttle:20,1')->name('ttd.hapus');
});

// ==================== PENGAWAS SEKOLAH ROUTES ====================
Route::middleware(['auth', 'role:pengawas', 'force.password.change', 'school.context', 'menu.telemetry'])->prefix('pengawas')->name('pengawas.')->group(function () {
    Route::get('/dashboard', [PengawasDashboardController::class, 'index'])->name('dashboard');
    Route::post('/sekolah-binaan', [PengawasDashboardController::class, 'gantiSekolahBinaan'])->middleware('throttle:30,1')->name('sekolah.ganti');

    // Verifikasi Berjenjang Perangkat Pembelajaran Mendalam (Guru -> Kepsek -> Pengawas)
    Route::prefix('supervisi')->name('supervisi.')->group(function () {
        Route::get('/', [PengawasDashboardController::class, 'supervisiIndex'])->name('index');
        Route::get('/guru/{guru:uuid}', [PengawasDashboardController::class, 'supervisiShow'])->name('show');
        Route::post('/guru/{guru:uuid}/verifikasi', [PengawasDashboardController::class, 'simpanVerifikasi'])
            ->middleware('throttle:30,1')
            ->name('verifikasi');
    });

    // E-Visitasi & Instrumen Observasi Kelas Pembelajaran Mendalam
    Route::prefix('visitasi')->name('visitasi.')->group(function () {
        Route::get('/', [PengawasDashboardController::class, 'visitasiIndex'])->name('index');
        Route::post('/', [PengawasDashboardController::class, 'visitasiStore'])->middleware('throttle:20,1')->name('store');
        Route::delete('/{visitasi:uuid}', [PengawasDashboardController::class, 'visitasiDestroy'])->middleware('throttle:20,1')->name('destroy');
        Route::get('/{visitasi:uuid}/pdf', [PengawasDashboardController::class, 'visitasiPdf'])->name('pdf');
    });

    // Audit Keselarasan Kurikulum — KKTP — e-Rapor & Peta Hasil Belajar
    Route::get('/audit-kurikulum', [PengawasDashboardController::class, 'auditIndex'])->name('audit.index');

    // Tanda Tangan Digital Mandiri Pengawas
    Route::get('/tanda-tangan', [PengawasDashboardController::class, 'tandaTanganIndex'])->name('ttd.index');
    Route::post('/tanda-tangan', [PengawasDashboardController::class, 'tandaTanganStore'])->middleware('throttle:20,1')->name('ttd.store');
    Route::delete('/tanda-tangan', [PengawasDashboardController::class, 'tandaTanganHapus'])->middleware('throttle:20,1')->name('ttd.hapus');

    // Generator 1-Klik Laporan Kepengawasan Semester PDF
    Route::get('/laporan-semester-pdf', [PengawasDashboardController::class, 'cetakLaporanSemesterPdf'])->name('laporan.pdf');
});

// ==================== CBT (PILIHAN GANDA) ====================
// Aktifkan setelah migrasi + pengujian integrasi; tetap false secara default.
if (config('simantap.cbt_enabled', false)) {
    // ==================== CBT ROUTES ====================
    // `force.password.change` wajib ada di sini: tanpa itu guru yang masih memakai
    // sandi awal tetap bisa masuk alur ujian, padahal 7 grup route lain menolaknya.
    Route::middleware(['auth', 'role:guru', 'school.context', 'force.password.change', CbtAccessMiddleware::class])->prefix('cbt')->name('cbt.')->group(function () {
        // Question Banks
        Route::prefix('question-banks')->name('question-banks.')->group(function () {
            Route::get('/', [CbtQuestionBankController::class, 'index'])->name('index');
            Route::get('/create', [CbtQuestionBankController::class, 'create'])->name('create');
            Route::post('/', [CbtQuestionBankController::class, 'store'])->name('store');
            Route::get('/{bank}', [CbtQuestionBankController::class, 'show'])->name('show');
            Route::get('/{bank}/edit', [CbtQuestionBankController::class, 'edit'])->name('edit');
            Route::put('/{bank}', [CbtQuestionBankController::class, 'update'])->name('update');
            Route::delete('/{bank}', [CbtQuestionBankController::class, 'destroy'])->name('destroy');
            Route::get('/{bank}/api/questions', [CbtQuestionBankController::class, 'apiGetQuestions'])->name('api.questions');
        });

        // Questions
        Route::prefix('questions')->name('questions.')->group(function () {
            Route::post('/{bank}', [CbtQuestionController::class, 'store'])->name('store');
            Route::put('/{question}', [CbtQuestionController::class, 'update'])->name('update');
            Route::delete('/{question}', [CbtQuestionController::class, 'destroy'])->name('destroy');
        });

        // Test Sessions
        Route::prefix('sessions')->name('sessions.')->group(function () {
            Route::get('/', [CbtTestSessionController::class, 'index'])->name('index');
            Route::get('/create', [CbtTestSessionController::class, 'create'])->name('create');
            Route::post('/', [CbtTestSessionController::class, 'store'])->name('store');
            Route::get('/{session}', [CbtTestSessionController::class, 'show'])->name('show');
            Route::post('/{session}/publish', [CbtTestSessionController::class, 'publish'])->name('publish');
            Route::post('/{session}/start', [CbtTestSessionController::class, 'start'])->name('start');
            Route::post('/{session}/complete', [CbtTestSessionController::class, 'complete'])->name('complete');
            Route::get('/{session}/monitoring', [CbtTestSessionController::class, 'monitoring'])->name('monitoring');
            Route::get('/{session}/api/monitoring', [CbtTestSessionController::class, 'apiMonitoring'])->name('api.monitoring');
        });

        // Integrity (Anti-Cheat)
        Route::prefix('integrity')->name('integrity.')->group(function () {
            Route::get('/{result}/violations', [CbtIntegrityController::class, 'getViolations'])->name('violations');
        });
    });

    // CBT Student Routes
    // Sama seperti grup guru di atas: sandi awal harus sudah diganti sebelum ujian.
    Route::middleware(['auth', 'role:siswa', 'school.context', 'force.password.change', CbtAccessMiddleware::class])->prefix('cbt/student')->name('cbt.student.')->group(function () {
        Route::get('/', [CbtStudentController::class, 'index'])->name('index');
        Route::get('/{session}/start', [CbtStudentController::class, 'start'])->name('start');
        Route::post('/{session}/start', [CbtStudentController::class, 'begin'])->name('begin');
        Route::post('/{result}/integrity', [CbtIntegrityController::class, 'logViolation'])->middleware('throttle:30,1')->name('integrity');
        Route::post('/{result}/answer', [CbtStudentController::class, 'submitAnswer'])->middleware('throttle:120,1')->name('answer');
        Route::post('/{result}/submit', [CbtStudentController::class, 'submit'])->name('submit');
        Route::get('/{result}/result', [CbtStudentController::class, 'result'])->name('result');
    });
}

// ==================== ADMIN CBT MANAGEMENT ====================
Route::middleware(['module.asesmen', 'auth', 'role:admin', 'school.context', 'force.password.change', 'menu.telemetry'])->prefix('admin/cbt')->name('admin.cbt.')->group(function () {
    // Question Banks (Bank Soal — dapat disiapkan admin di mode persiapan)
    Route::prefix('banks')->name('banks.')->group(function () {
        Route::get('/', [CbtBankController::class, 'index'])->name('index');
        Route::get('/create', [CbtBankController::class, 'create'])->name('create');
        Route::post('/', [CbtBankController::class, 'store'])->name('store');
        Route::get('/{bank}', [CbtBankController::class, 'show'])->name('show');
        Route::get('/{bank}/edit', [CbtBankController::class, 'edit'])->name('edit');
        Route::put('/{bank}', [CbtBankController::class, 'update'])->name('update');
        Route::delete('/{bank}', [CbtBankController::class, 'destroy'])->name('destroy');
        Route::get('/api/statistics', [CbtBankController::class, 'statistics'])->name('statistics');
    });

    // Test Sessions, Hasil, Analisis, Ekspor (terkunci 404 saat cbt_enabled=false)
    Route::middleware('cbt.enabled')->prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/', [CbtSessionController::class, 'index'])->name('index');
        Route::get('/create', [CbtSessionController::class, 'create'])->name('create');
        Route::post('/', [CbtSessionController::class, 'store'])->name('store');
        Route::get('/{session}', [CbtSessionController::class, 'show'])->name('show');
        Route::post('/{session}/update-status', [CbtSessionController::class, 'updateStatus'])->name('update-status');
        Route::post('/{session}/force-complete', [CbtSessionController::class, 'forceComplete'])->name('force-complete');
        Route::delete('/{session}', [CbtSessionController::class, 'destroy'])->name('destroy');
        Route::get('/{session}/export/{format}', [CbtSessionController::class, 'exportResults'])->name('export');
        Route::get('/{session}/export-engine/{format}', [CbtSessionController::class, 'exportResultsEngine'])->name('export-engine');
        Route::get('/{session}/analisis-butir', [CbtSessionController::class, 'analisisButir'])->name('analisis-butir');
    });
});

// ==================== ADMIN TRYOUT MANAGEMENT ====================
Route::middleware(['module.asesmen', 'auth', 'role:admin', 'school.context', 'force.password.change', 'menu.telemetry'])->prefix('admin/tryout')->name('admin.tryout.')->group(function () {
    Route::get('/statistics', [TryoutPackageAdminController::class, 'statistics'])->name('statistics');

    // Packages
    Route::prefix('packages')->name('packages.')->group(function () {
        Route::get('/', [TryoutPackageAdminController::class, 'index'])->name('index');
        Route::get('/create', [TryoutPackageAdminController::class, 'create'])->name('create');
        Route::post('/', [TryoutPackageAdminController::class, 'store'])->name('store');
        Route::get('/{package}', [TryoutPackageAdminController::class, 'show'])->name('show');
        Route::get('/{package}/edit', [TryoutPackageAdminController::class, 'edit'])->name('edit');
        Route::put('/{package}', [TryoutPackageAdminController::class, 'update'])->name('update');
        Route::delete('/{package}', [TryoutPackageAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{package}/toggle-status', [TryoutPackageAdminController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{package}/duplicate', [TryoutPackageAdminController::class, 'duplicate'])->name('duplicate');
    });

    // All Sessions
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/', [TryoutPackageAdminController::class, 'allSessions'])->name('index');
        Route::get('/create', [TryoutPackageAdminController::class, 'createSession'])->name('create');
        Route::post('/', [TryoutPackageAdminController::class, 'storeSession'])->name('store');
        Route::get('/{session}', [TryoutPackageAdminController::class, 'showSession'])->name('show');
        Route::get('/{session}/edit', [TryoutPackageAdminController::class, 'editSession'])->name('edit');
        Route::put('/{session}', [TryoutPackageAdminController::class, 'updateSession'])->name('update');
        Route::delete('/{session}', [TryoutPackageAdminController::class, 'destroySession'])->name('destroy');
        Route::post('/{session}/force-complete', [TryoutPackageAdminController::class, 'forceCompleteSession'])->name('force-complete');
    });

    // All Results
    Route::prefix('results')->name('results.')->group(function () {
        Route::get('/', [TryoutPackageAdminController::class, 'allResults'])->name('index');
        Route::get('/export', [TryoutPackageAdminController::class, 'exportResults'])->name('export');
        Route::get('/engine/{attempt}', [TryoutPackageAdminController::class, 'showEngineResult'])->name('engine.show');
        Route::get('/{result}', [TryoutPackageAdminController::class, 'showResult'])->name('show');
    });
});

// ==================== ADMIN TKA MANAGEMENT ====================
Route::middleware(['module.asesmen', 'auth', 'role:admin', 'school.context', 'force.password.change', 'menu.telemetry'])->prefix('admin/tka')->name('admin.tka.')->group(function () {
    Route::get('/', [TkaAdminController::class, 'index'])->name('dashboard');

    // Dimensions Management
    Route::prefix('dimensions')->name('dimensions.')->group(function () {
        Route::get('/', [TkaAdminController::class, 'dimensions'])->name('index');
        Route::get('/create', [TkaAdminController::class, 'createDimension'])->name('create');
        Route::post('/', [TkaAdminController::class, 'storeDimension'])->name('store');
        Route::get('/{dimension}', [TkaAdminController::class, 'showDimension'])->name('show');
        Route::get('/{dimension}/edit', [TkaAdminController::class, 'editDimension'])->name('edit');
        Route::put('/{dimension}', [TkaAdminController::class, 'updateDimension'])->name('update');
        Route::post('/{dimension}/toggle-status', [TkaAdminController::class, 'toggleDimensionStatus'])->name('toggle-status');
    });

    // Assessments Management
    Route::prefix('assessments')->name('assessments.')->group(function () {
        Route::get('/', [TkaAdminController::class, 'assessments'])->name('index');
        Route::get('/siswa/{siswaId}/history', [TkaAdminController::class, 'studentHistory'])->name('history');
    });

    // Early Warnings Management
    Route::prefix('warnings')->name('warnings.')->group(function () {
        Route::get('/', [TkaAdminController::class, 'earlyWarnings'])->name('index');
        Route::get('/{warning}', [TkaAdminController::class, 'showWarning'])->name('show');
        Route::post('/{warning}/update-status', [TkaAdminController::class, 'updateWarningStatus'])->name('update-status');
    });

    // Export & Reports
    Route::post('/export', [TkaAdminController::class, 'export'])->name('export');
    Route::post('/generate-report', [TkaAdminController::class, 'generateReport'])->name('generate-report');
    Route::get('/kognitif-siswa', [TkaLaporanSekolahController::class, 'kognitifIndex'])->name('kognitif.index');
    Route::get('/kognitif-siswa/{siswaUuid}', [TkaLaporanSekolahController::class, 'kognitifShow'])->name('kognitif.show');
    Route::get('/laporan-kelas', [TkaLaporanSekolahController::class, 'laporanKelas'])->name('laporan-kelas.index');
    Route::get('/laporan-kelas/export-csv', [TkaLaporanSekolahController::class, 'exportCsv'])->name('laporan-kelas.export');
});

// ==================== TRYOUT ROUTES ====================
Route::middleware(['module.asesmen', 'auth', 'role:guru,admin', 'school.context', 'force.password.change'])->prefix('tryout')->name('tryout.')->group(function () {
    // Categories (nonaktif sementara: controller belum ada — milik area Kiro)
    // Route::resource('categories', \App\Http\Controllers\Tryout\TryoutCategoryController::class)->except(['show']);

    // Packages
    Route::resource('packages', TryoutPackageController::class);

    // Sessions
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/', [TryoutSessionController::class, 'index'])->name('index');
        Route::get('/create', [TryoutSessionController::class, 'create'])->name('create');
        Route::post('/', [TryoutSessionController::class, 'store'])->name('store');
        Route::get('/{session}', [TryoutSessionController::class, 'show'])->name('show');
        Route::post('/{session}/publish', [TryoutSessionController::class, 'publish'])->name('publish');
        Route::post('/{session}/start', [TryoutSessionController::class, 'start'])->name('start');
        Route::post('/{session}/complete', [TryoutSessionController::class, 'complete'])->name('complete');
        Route::get('/{session}/monitoring', [TryoutSessionController::class, 'monitoring'])->name('monitoring');
        Route::get('/{session}/api/monitoring', [TryoutSessionController::class, 'apiMonitoring'])->name('api.monitoring');
    });
});

// Tryout Student Routes
Route::middleware(['module.asesmen', 'auth', 'role:siswa', 'school.context', 'force.password.change'])->prefix('tryout/student')->name('tryout.student.')->group(function () {
    Route::get('/', [TryoutStudentController::class, 'index'])->name('index');
    Route::get('/{session}/start', [TryoutStudentController::class, 'start'])->name('start');
    Route::post('/{result}/answer', [TryoutStudentController::class, 'submitAnswer'])->name('answer');
    Route::post('/{result}/complete', [TryoutStudentController::class, 'complete'])->name('complete');
    Route::get('/{result}/result', [TryoutStudentController::class, 'result'])->name('result');
});

// ==================== TKA ENHANCED ROUTES ====================
Route::middleware(['module.asesmen', 'auth', 'role:guru', 'school.context', 'force.password.change'])->prefix('tka')->name('tka.')->group(function () {
    // Dimensions
    Route::resource('dimensions', TkaDimensionController::class);
    Route::post('/dimensions/{dimension}/item', [TkaDimensionController::class, 'addItem'])->name('dimensions.add-item');
    Route::delete('/dimensions/{dimension}/item/{item}', [TkaDimensionController::class, 'destroyItem'])->name('dimensions.destroy-item');
    Route::get('/dimensions/{dimension}/api/items', [TkaDimensionController::class, 'getItems'])->name('dimensions.api.items');

    // Assessments
    // Resource diganti eksplisit: TkaAssessmentController hanya punya
    // index/create/store/show — route edit/update/destroy dari resource
    // melempar BadMethodCallException (HTTP 500) saat dipanggil.
    Route::get('/assessments', [TkaAssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/assessments/create', [TkaAssessmentController::class, 'create'])->name('assessments.create');
    Route::post('/assessments', [TkaAssessmentController::class, 'store'])->name('assessments.store');
    Route::get('/assessments/{assessment}', [TkaAssessmentController::class, 'show'])->name('assessments.show');
    Route::get('/assessments/siswa/{siswaId}', [TkaAssessmentController::class, 'bySiswa'])->name('assessments.by-siswa');

    // Generate Parent Report
    Route::post('/assessments/{assessment}/generate-report', [TkaParentController::class, 'generateReport'])->name('assessments.generate-report');
});

// TKA Parent Routes
Route::middleware(['module.asesmen', 'auth', 'role:ortu', 'school.context', 'force.password.change'])->prefix('tka/parent')->name('tka.parent.')->group(function () {
    Route::get('/', [TkaParentController::class, 'index'])->name('index');
    Route::get('/{report}', [TkaParentController::class, 'show'])->name('show');
});
