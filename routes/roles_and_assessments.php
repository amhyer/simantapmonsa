<?php

use App\Http\Controllers\Admin\Cbt\CbtBankController;
use App\Http\Controllers\Admin\Cbt\CbtSessionController;
use App\Http\Controllers\Admin\Tka\TkaAdminController;
use App\Http\Controllers\Admin\Tka\TkaLaporanSekolahController;
use App\Http\Controllers\Admin\Tryout\TryoutPackageAdminController;
use App\Http\Controllers\Cbt\CbtIntegrityController;
use App\Http\Controllers\Cbt\CbtQuestionBankController;
use App\Http\Controllers\Cbt\CbtQuestionController;
use App\Http\Controllers\Cbt\CbtStudentController;
use App\Http\Controllers\Cbt\CbtTestSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Kepsek\AktivitasController;
use App\Http\Controllers\Kepsek\HasilBelajarController;
use App\Http\Controllers\Kepsek\KebiasaanController as KepsekKebiasaanController;
use App\Http\Controllers\Kepsek\PerangkatSupervisiController;
use App\Http\Controllers\Kepsek\PetaKelasController as KepsekPetaKelas;
use App\Http\Controllers\Kepsek\RekapController;
use App\Http\Controllers\Kepsek\SupervisiPmController;
use App\Http\Controllers\Kepsek\TkaLaporanController;
use App\Http\Controllers\Ortu\CatatanController as OrtuCatatanController;
use App\Http\Controllers\Ortu\DashboardController as OrtuDashboard;
use App\Http\Controllers\Ortu\IzinPresensiController;
use App\Http\Controllers\Ortu\KebiasaanController as OrtuKebiasaanController;
use App\Http\Controllers\Ortu\KehadiranController as OrtuKehadiranController;
use App\Http\Controllers\Ortu\LaporanController as OrtuLaporanController;
use App\Http\Controllers\Ortu\RekapController as OrtuRekapController;
use App\Http\Controllers\Ortu\TkaRiwayatController;
use App\Http\Controllers\Pengawas\DashboardController as PengawasDashboardController;
use App\Http\Controllers\Siswa\DashboardController as SiswaDashboard;
use App\Http\Controllers\Siswa\KelasDigitalController;
use App\Http\Controllers\Siswa\KuisController as SiswaKuisController;
use App\Http\Controllers\Siswa\MateriController as SiswaMateriController;
use App\Http\Controllers\Siswa\NilaiController as SiswaNilaiController;
use App\Http\Controllers\Siswa\ProfilController;
use App\Http\Controllers\Siswa\RaporController;
use App\Http\Controllers\Siswa\TkaSimulasiController;
use App\Http\Controllers\Tka\KesiapanController;
use App\Http\Controllers\Tka\TkaAssessmentController;
use App\Http\Controllers\Tka\TkaDimensionController;
use App\Http\Controllers\Tka\TkaParentController;
use App\Http\Controllers\Tryout\TryoutPackageController;
use App\Http\Controllers\Tryout\TryoutSessionController;
use App\Http\Controllers\Tryout\TryoutStudentController;
use App\Http\Middleware\CbtAccessMiddleware;
use Illuminate\Support\Facades\Route;

// ==================== SISWA ROUTES ====================
Route::middleware(['auth', 'role:siswa', 'force.password.change', 'school.context', 'menu.telemetry'])->prefix('siswa')->name('siswa.')->group(function () {
    Route::get('/dashboard', [SiswaDashboard::class, 'index'])->name('dashboard');
    Route::get('/materi', [SiswaMateriController::class, 'index'])->name('materi.index');
    Route::get('/materi/{id}', [SiswaMateriController::class, 'show'])->name('materi.show');

    // E-Learning / Kelas Digital Siswa
    Route::prefix('kelas-digital')->name('kelas-digital.')->group(function () {
        Route::get('/', [KelasDigitalController::class, 'index'])->name('index');
        Route::get('/{kelasDigital}', [KelasDigitalController::class, 'show'])->name('show');
        Route::get('/{kelasDigital}/pertemuan/{pertemuan}', [KelasDigitalController::class, 'pertemuan'])->name('pertemuan');
        Route::post('/{kelasDigital}/pertemuan/{pertemuan}/selesai', [KelasDigitalController::class, 'tandaiSelesai'])->middleware('throttle:60,1')->name('pertemuan.selesai');
        Route::post('/{kelasDigital}/tugas/{tugas}/kumpul', [KelasDigitalController::class, 'kumpulTugas'])->middleware('throttle:30,1')->name('tugas.kumpul');
        Route::get('/{kelasDigital}/item/{item}/unduh', [KelasDigitalController::class, 'downloadItem'])->name('item.download');
        Route::get('/{kelasDigital}/tugas/{tugas}/lampiran', [KelasDigitalController::class, 'downloadLampiranTugas'])->name('tugas.download');
        Route::get('/{kelasDigital}/pengumpulan/{pengumpulan}/unduh', [KelasDigitalController::class, 'downloadPengumpulan'])->name('pengumpulan.download');
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
    Route::post('/kehadiran/izin', [IzinPresensiController::class, 'store'])->middleware('throttle:10,1')->name('kehadiran.izin.store');
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
        Route::post('/{result}/submit', [CbtStudentController::class, 'submit'])->middleware('throttle:20,1')->name('submit');
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
    Route::post('/{result}/answer', [TryoutStudentController::class, 'submitAnswer'])->middleware('throttle:120,1')->name('answer');
    Route::post('/{result}/complete', [TryoutStudentController::class, 'complete'])->middleware('throttle:20,1')->name('complete');
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
