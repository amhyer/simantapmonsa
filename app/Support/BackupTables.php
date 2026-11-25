<?php

namespace App\Support;

use App\Models\Aktivitas;
use App\Models\Catatan;
use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\CbtViolation;
use App\Models\Dimensi;
use App\Models\Ekstrakurikuler;
use App\Models\Elearning\KelasDigital;
use App\Models\Elearning\KelasDigitalMateriItem;
use App\Models\Elearning\KelasDigitalModul;
use App\Models\Elearning\KelasDigitalPengumpulan;
use App\Models\Elearning\KelasDigitalPertemuan;
use App\Models\Elearning\KelasDigitalProgress;
use App\Models\Elearning\KelasDigitalTugas;
use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Bank;
use App\Models\Exam\Engine;
use App\Models\Exam\Question;
use App\Models\Exam\QuestionOption;
use App\Models\Exam\Section;
use App\Models\Exam\Violation;
use App\Models\HasilKuis;
use App\Models\JadwalPelajaran;
use App\Models\Kebiasaan;
use App\Models\KegiatanKokurikuler;
use App\Models\Kehadiran;
use App\Models\KehadiranMapel;
use App\Models\KelompokKokurikuler;
use App\Models\Kuis;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\NilaiErapotAudit;
use App\Models\OrangTuaSiswa;
use App\Models\PengajuanIzinPresensi;
use App\Models\PengaturanGuru;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\PresensiLokasi;
use App\Models\PresensiPegawai;
use App\Models\Ptk;
use App\Models\RingkasanGuru;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Models\TemaKokurikuler;
use App\Models\Tka\AssessmentItem;
use App\Models\Tka\AssessmentResponse;
use App\Models\Tka\Dimension;
use App\Models\Tka\EarlyWarning;
use App\Models\Tka\HistoricalTracking;
use App\Models\Tka\Kesiapan\Audit;
use App\Models\Tka\Kesiapan\Pemetaan;
use App\Models\Tka\Kesiapan\Pengaturan;
use App\Models\Tka\Kesiapan\TindakLanjut;
use App\Models\Tka\Kesiapan\TindakLanjutRevisi;
use App\Models\Tka\ParentReport;
use App\Models\Tka\StudentAssessment;
use App\Models\TranskripNilai;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result;
use App\Models\Tryout\Session;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tryout\Subject;
use App\Models\Tryout\TryoutQuestion;
use App\Models\Tryout\TryoutQuestionOption;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu sumber kebenaran untuk cakupan backup (export) dan restore (import).
 *
 * PERBAIKAN BUG: sebelumnya export/import hanya menangani 14 tabel, sedangkan
 * tombol "Hapus Semua Data" (BackupController::wipeAll) mengosongkan 31 tabel
 * data — sehingga nilai e-Rapor, transkrip, PTK, rombel, jadwal, mapel,
 * ekstrakurikuler, dan kokurikuler hilang permanen setelah wipe + restore.
 *
 * Urutan entri = urutan restore; tabel induk WAJIB lebih dulu agar foreign key
 * terpenuhi saat import.
 *
 * 'class'  = kelas model Eloquent; null untuk tabel pivot tanpa model.
 * 'except' = kolom yang tidak dicadangkan (rahasia / berkas biner besar).
 */
class BackupTables
{
    /**
     * @return array<string, array{class: class-string<Model>|null, except: array<int, string>}>
     */
    public static function semua(): array
    {
        return [
            // ── Identitas & referensi induk ─────────────────────────────
            'tenants' => ['class' => Tenant::class, 'except' => []],
            'users' => ['class' => User::class, 'except' => ['kata_sandi', 'remember_token', 'dapodik_id', 'rekaman']],
            'semesters' => ['class' => Semester::class, 'except' => []],
            'sekolah_settings' => ['class' => SekolahSettings::class, 'except' => []],
            'ptk' => ['class' => Ptk::class, 'except' => []],
            'rombel' => ['class' => Rombel::class, 'except' => []],
            'mata_pelajaran' => ['class' => MataPelajaran::class, 'except' => []],

            // ── Peserta didik & relasinya ───────────────────────────────
            // NIK/no_kk SENGAJA ikut dibackup: tanpa keduanya, restore merusak
            // pencocokan scan presensi mesin (yang memakai NIK sebagai kunci).
            // File backup hanya tersimpan di disk lokal admin, bukan publik.
            'siswa' => ['class' => Siswa::class, 'except' => ['rekaman', 'foto']],
            'orang_tua_siswa' => ['class' => OrangTuaSiswa::class, 'except' => []],
            'ekstrakurikuler' => ['class' => Ekstrakurikuler::class, 'except' => []],

            // ── Pembelajaran & penilaian ────────────────────────────────
            'jadwal_pelajaran' => ['class' => JadwalPelajaran::class, 'except' => []],
            'pengaturan_guru' => ['class' => PengaturanGuru::class, 'except' => []],
            'ringkasan_guru' => ['class' => RingkasanGuru::class, 'except' => []],
            'materi' => ['class' => Materi::class, 'except' => []],
            'kuis' => ['class' => Kuis::class, 'except' => []],
            'nilai' => ['class' => Nilai::class, 'except' => []],
            'kelas_digital' => ['class' => KelasDigital::class, 'except' => []],
            'kelas_digital_modul' => ['class' => KelasDigitalModul::class, 'except' => []],
            'kelas_digital_pertemuan' => ['class' => KelasDigitalPertemuan::class, 'except' => []],
            'kelas_digital_materi_item' => ['class' => KelasDigitalMateriItem::class, 'except' => []],
            'kelas_digital_tugas' => ['class' => KelasDigitalTugas::class, 'except' => []],
            'kelas_digital_pengumpulan' => ['class' => KelasDigitalPengumpulan::class, 'except' => []],
            'kelas_digital_progress' => ['class' => KelasDigitalProgress::class, 'except' => []],
            'perangkat_pembelajaran' => ['class' => PerangkatPembelajaran::class, 'except' => []],
            'jurnal_mengajar' => ['class' => JurnalMengajar::class, 'except' => []],
            'nilai_erapor' => ['class' => NilaiErapot::class, 'except' => []],
            'nilai_erapor_audit_logs' => ['class' => NilaiErapotAudit::class, 'except' => []],
            'transkrip_nilai' => ['class' => TranskripNilai::class, 'except' => []],
            'kehadiran' => ['class' => Kehadiran::class, 'except' => []],
            'kehadiran_mapel' => ['class' => KehadiranMapel::class, 'except' => []],
            'presensi_lokasi' => ['class' => PresensiLokasi::class, 'except' => []],
            'presensi_pegawai' => ['class' => PresensiPegawai::class, 'except' => []],
            'pengajuan_izin_presensi' => ['class' => PengajuanIzinPresensi::class, 'except' => []],
            'hasil_kuis' => ['class' => HasilKuis::class, 'except' => []],
            'catatan' => ['class' => Catatan::class, 'except' => []],
            'dimensi' => ['class' => Dimensi::class, 'except' => []],
            'kebiasaan' => ['class' => Kebiasaan::class, 'except' => []],
            // Tabel penilaian tanpa model Eloquent (class null → query builder).
            'capaian_pembelajaran' => ['class' => null, 'except' => []],
            'nilai_mapel' => ['class' => null, 'except' => []],
            'nilai_cp' => ['class' => null, 'except' => []],

            // ── Ekstrakurikuler & kokurikuler ───────────────────────────
            'siswa_ekstrakurikuler' => ['class' => null, 'except' => []],
            'tema_kokurikuler' => ['class' => TemaKokurikuler::class, 'except' => []],
            'kegiatan_kokurikuler' => ['class' => KegiatanKokurikuler::class, 'except' => []],
            'kelompok_kokurikuler' => ['class' => KelompokKokurikuler::class, 'except' => []],
            'anggota_kelompok' => ['class' => null, 'except' => []],

            // CBT: urutan induk -> anak; snapshot/kunci disertakan dalam backup privat.
            'cbt_question_banks' => ['class' => CbtQuestionBank::class, 'except' => []],
            'cbt_questions' => ['class' => CbtQuestion::class, 'except' => []],
            'cbt_test_sessions' => ['class' => CbtTestSession::class, 'except' => []],
            'cbt_results' => ['class' => CbtResult::class, 'except' => []],
            'cbt_violations' => ['class' => CbtViolation::class, 'except' => []],

            // Tryout legacy tetap diperlukan pembaca admin/laporan selama
            // cutover. Restore mengikuti urutan FK induk -> anak.
            'tryout_categories' => ['class' => Category::class, 'except' => []],
            'tryout_packages' => ['class' => Package::class, 'except' => []],
            'tryout_subjects' => ['class' => Subject::class, 'except' => []],
            'tryout_questions' => ['class' => TryoutQuestion::class, 'except' => []],
            'tryout_question_options' => ['class' => TryoutQuestionOption::class, 'except' => []],
            'tryout_sessions' => ['class' => Session::class, 'except' => []],
            'tryout_results' => ['class' => Result::class, 'except' => []],
            'tryout_student_answers' => ['class' => StudentAnswer::class, 'except' => []],

            // TKA asesmen + laporan orang tua adalah data akademik sumber.
            'tka_dimensions_enhanced' => ['class' => Dimension::class, 'except' => []],
            'tka_assessment_items' => ['class' => AssessmentItem::class, 'except' => []],
            'tka_student_assessments' => ['class' => StudentAssessment::class, 'except' => []],
            'tka_assessment_responses' => ['class' => AssessmentResponse::class, 'except' => []],
            'tka_parent_reports' => ['class' => ParentReport::class, 'except' => []],
            'tka_historical_tracking' => ['class' => HistoricalTracking::class, 'except' => []],
            'tka_early_warnings' => ['class' => EarlyWarning::class, 'except' => []],
            'tka_kesiapan_pengaturan' => ['class' => Pengaturan::class, 'except' => []],
            'tka_kesiapan_pemetaan' => ['class' => Pemetaan::class, 'except' => []],
            'tka_kesiapan_tindak_lanjut' => ['class' => TindakLanjut::class, 'except' => []],
            'tka_kesiapan_tindak_lanjut_revisi' => ['class' => TindakLanjutRevisi::class, 'except' => []],
            'tka_kesiapan_audit' => ['class' => Audit::class, 'except' => []],

            // Engine ujian terpadu (Fase 3): urutan induk -> anak. Tanpa entri
            // ini, hasil ujian baru hilang permanen setelah wipe + restore,
            // karena `exam_*` tidak masuk backup sedangkan `cbt_*`/`tryout_*`
            // hanyalah cermin yang bisa kosong pada alur engine.
            'exam_engines' => ['class' => Engine::class, 'except' => []],
            'exam_banks' => ['class' => Bank::class, 'except' => []],
            'exam_sections' => ['class' => Section::class, 'except' => []],
            'exam_questions' => ['class' => Question::class, 'except' => []],
            'exam_question_options' => ['class' => QuestionOption::class, 'except' => []],
            'exam_sessions' => ['class' => \App\Models\Exam\Session::class, 'except' => []],
            'exam_attempts' => ['class' => Attempt::class, 'except' => []],
            'exam_answers' => ['class' => Answer::class, 'except' => []],
            'exam_results' => ['class' => \App\Models\Exam\Result::class, 'except' => []],
            'exam_violations' => ['class' => Violation::class, 'except' => []],

            // ── Rapor Merdeka, P5, & Prestasi ───────────────────────────
            'kenaikan_kelas' => ['class' => null, 'except' => []],
            'siswa_belajar' => ['class' => null, 'except' => []],
            'prestasi' => ['class' => null, 'except' => []],
            'p5_projek' => ['class' => null, 'except' => []],
            'p5_siswa' => ['class' => null, 'except' => []],
            'p5_penilaian' => ['class' => null, 'except' => []],

            // ── Pembelajaran Mendalam (PM), Supervisi, & Pengawas ───────
            'pm_alokasi_waktu' => ['class' => null, 'except' => []],
            'pm_buku_paket' => ['class' => null, 'except' => []],
            'pm_capaian_pembelajaran' => ['class' => null, 'except' => []],
            'pm_tujuan_pembelajaran' => ['class' => null, 'except' => []],
            'pm_kalender_pendidikan' => ['class' => null, 'except' => []],
            'pm_hari_libur' => ['class' => null, 'except' => []],
            'pm_prota' => ['class' => null, 'except' => []],
            'pm_prosem' => ['class' => null, 'except' => []],
            'pm_kokurikuler' => ['class' => null, 'except' => []],
            'pm_kktp_interval' => ['class' => null, 'except' => []],
            'pm_kktp_item' => ['class' => null, 'except' => []],
            'pm_supervisi' => ['class' => null, 'except' => []],
            'pm_visitasi_pengawas' => ['class' => null, 'except' => []],

            // ── TKA Inovasi (Simulasi Adaptif & Rekomendasi) ────────────
            'tka_adaptive_simulations' => ['class' => null, 'except' => []],
            'tka_learning_recommendations' => ['class' => null, 'except' => []],
            'tka_erapor_sync_logs' => ['class' => null, 'except' => []],

            // ── Lain-lain ───────────────────────────────────────────────
            'tanggal_rapor' => ['class' => TanggalRapor::class, 'except' => []],
            'aktivitas' => ['class' => Aktivitas::class, 'except' => []],
        ];
    }

    /**
     * Tabel yang dicadangkan dan TIDAK dikosongkan oleh "Hapus Semua Data".
     *
     * Daftar ini harus tetap kecil dan disengaja: akun, semester, dan identitas
     * sekolah dipertahankan agar admin masih bisa login dan aplikasi tetap tahu
     * konteks tahun ajar setelah wipe.
     *
     * Diperiksa oleh tools/verifikasi-perbaikan.php.
     *
     * @return array<int, string>
     */
    public static function tidakDikosongkan(): array
    {
        return [
            'tenants',
            'users',
            'semesters',
            'sekolah_settings',
        ];
    }

    /**
     * Tabel data yang dikosongkan tombol "Hapus Semua Data".
     *
     * Diturunkan dari registry yang sama agar cakupan backup dan wipe tidak
     * pernah menyimpang (bug lama: tryout_* dan transkrip_nilai tercadang/terisi
     * tetapi tidak pernah dikosongkan).
     *
     * @return array<int, string>
     */
    public static function dikosongkan(): array
    {
        return array_values(array_diff(
            array_merge(self::tabel(), self::tidakDicadangkan()),
            self::tidakDikosongkan()
        ));
    }

    /**
     * Tabel yang SENGAJA tidak dicadangkan karena isinya dapat dibuat ulang
     * (log & cache sinkronisasi). Didaftarkan eksplisit agar selisih antara
     * tabel yang di-wipe dan yang dicadangkan selalu disengaja, bukan bug.
     *
     * Diperiksa oleh tools/verifikasi-perbaikan.php.
     *
     * @return array<int, string>
     */
    public static function tidakDicadangkan(): array
    {
        return [
            'dapodik_sync_logs',        // riwayat sinkronisasi (dapat dibuat ulang)
            'dapodik_import_logs',      // riwayat import (dapat dibuat ulang)
            'dapodik_data_cache',       // cache data Dapodik (diisi ulang saat sync)
            'presensi_mesin_sync_logs', // log sinkronisasi mesin absensi
            'pm_ai_usage_logs',         // log kuota pemakaian AI Gemini
            'menu_usage_daily',         // agregat telemetri menu (tanpa PII)
            'cbt_integrity_logs',       // tabel CBT legacy
            'cbt_question_options',     // tabel CBT legacy
            'cbt_student_answers',      // tabel CBT legacy
            'cbt_test_results',         // tabel CBT legacy
        ];
    }

    /**
     * Tabel konfigurasi/kredensial sensitif yang sengaja tidak dicadangkan ke
     * berkas JSON dan tidak dikosongkan otomatis oleh "Hapus Semua Data".
     *
     * @return array<int, string>
     */
    public static function konfigurasiRahasia(): array
    {
        return [
            'api_keys',        // hash kunci API integrasi
            'dapodik_configs', // menyimpan token Dapodik & CF Access terenkripsi
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function tabel(): array
    {
        return array_keys(self::semua());
    }

    public static function model(string $table): ?string
    {
        return self::semua()[$table]['class'] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function except(string $table): array
    {
        return self::semua()[$table]['except'] ?? [];
    }
}
