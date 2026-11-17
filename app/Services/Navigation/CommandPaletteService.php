<?php

namespace App\Services\Navigation;

use App\Models\User;
use App\Modules\Tenant\Support\TenantMode;
use Illuminate\Support\Facades\Route;

/**
 * Gelombang 7A: Satu sumber kebenaran daftar menu pencarian cepat (Ctrl+K)
 * untuk seluruh 7 peran — terfilter ketat menurut peran, flag wali kelas,
 * feature flag modul asesmen/TKA, mode multi-sekolah, dan keberadaan rute.
 *
 * Menjamin tidak ada rute 403/404 muncul di hasil pencarian pengguna.
 */
class CommandPaletteService
{
    /**
     * @return array<int, array{title: string, category: string, url: string, route: string, icon: string, keywords: string}>
     */
    public function itemsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $raw = match ($user->peran) {
            'super_admin' => $this->superAdminItems(),
            'admin' => $this->adminItems(),
            'guru' => $this->guruItems($user),
            'siswa' => $this->siswaItems(),
            'kepsek' => $this->kepsekItems(),
            'ortu' => $this->ortuItems(),
            'tendik' => $this->tendikItems(),
            default => [],
        };

        $asesmenAktif = modulAsesmenAktif();
        $tkaAktif = kesiapanTkaAktif();
        $multiSchool = TenantMode::isMultiSchool();
        $seenRoutes = [];
        $result = [];

        foreach ($raw as $item) {
            $routeName = $item['route'] ?? '';
            if ($routeName === '' || isset($seenRoutes[$routeName])) {
                continue;
            }
            if (($item['asesmen'] ?? false) && ! $asesmenAktif) {
                continue;
            }
            if (($item['kesiapan_tka'] ?? false) && ! $tkaAktif) {
                continue;
            }
            if (($item['single_school_only'] ?? false) && $multiSchool) {
                continue;
            }
            if (! Route::has($routeName)) {
                continue;
            }

            $seenRoutes[$routeName] = true;
            $result[] = [
                'title' => $item['title'],
                'category' => $item['category'],
                'url' => route($routeName),
                'route' => $routeName,
                'icon' => $item['icon'] ?? 'fas fa-link',
                'keywords' => $item['keywords'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Ringkasan panduan singkat kontekstual untuk pengguna saat membuka pencarian cepat.
     */
    public function contextualHintFor(?User $user): string
    {
        return match ($user?->peran) {
            'admin' => 'Ketik nama menu (mis. "validasi", "dapodik", "rapor", "akun", "presensi") atau tekan panah atas/bawah lalu Enter.',
            'guru' => 'Ketik "cp", "tp", "atp", "prota", "promes", "modul ajar", "jurnal", "kelas digital", atau "nilai" untuk berpindah cepat.',
            'siswa' => 'Ketik "kelas digital", "materi", "kuis", "nilai", "rapor", atau "simulasi tka" untuk membuka menu belajar.',
            'kepsek' => 'Ketik "supervisi", "perangkat", "laporan", "tka", atau "guru" untuk membuka pemantauan sekolah.',
            'ortu' => 'Ketik "nilai", "kehadiran", "catatan", atau "rapor" untuk memantau perkembangan anak.',
            'tendik' => 'Ketik "presensi" atau "beranda" untuk mengakses kehadiran harian.',
            'super_admin' => 'Ketik "sekolah", "paket", atau "audit" untuk mengelola sekolah multi-tenant.',
            default => 'Ketik kata kunci menu lalu tekan Enter.',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function superAdminItems(): array
    {
        return [
            ['title' => 'Dasbor Super Admin', 'category' => 'Platform', 'route' => 'superadmin.dashboard', 'icon' => 'fas fa-tachometer-alt', 'keywords' => 'beranda ringkasan'],
            ['title' => 'Daftar Sekolah (Tenant)', 'category' => 'Platform', 'route' => 'superadmin.tenants.index', 'icon' => 'fas fa-school', 'keywords' => 'sekolah tenant npsn'],
            ['title' => 'Manajemen Pengguna Lintas Sekolah', 'category' => 'Platform', 'route' => 'superadmin.users.index', 'icon' => 'fas fa-users-cog', 'keywords' => 'pengguna akun global'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function adminItems(): array
    {
        return [
            ['title' => 'Dasbor Admin', 'category' => 'Utama', 'route' => 'admin.dashboard', 'icon' => 'fas fa-home', 'keywords' => 'beranda utama'],
            ['title' => 'Profil Admin', 'category' => 'Utama', 'route' => 'admin.profile', 'icon' => 'fas fa-user-shield', 'keywords' => 'akun sandi'],
            ['title' => 'Validasi & Kelengkapan Data', 'category' => 'Data Sekolah', 'route' => 'admin.validasi-data.index', 'icon' => 'fas fa-clipboard-check', 'keywords' => 'cek data kosong siswa tanpa rombel wali kelas'],
            ['title' => 'Profil Sekolah', 'category' => 'Data Sekolah', 'route' => 'admin.sekolah.index', 'icon' => 'fas fa-building', 'keywords' => 'npsn kop surat kepala sekolah'],
            ['title' => 'Semester & Tahun Ajaran', 'category' => 'Data Sekolah', 'route' => 'admin.semester.index', 'icon' => 'fas fa-calendar', 'keywords' => 'periode aktif'],
            ['title' => 'Dapodik & Impor Data', 'category' => 'Integrasi', 'route' => 'admin.dapodik.index', 'icon' => 'fas fa-sync', 'keywords' => 'sinkronisasi bridge impor'],
            ['title' => 'Kirim Nilai ke Dapodik', 'category' => 'Integrasi', 'route' => 'admin.dapodik.push.index', 'icon' => 'fas fa-upload', 'keywords' => 'push dapodik'],
            ['title' => 'Data Guru & PTK', 'category' => 'Pendidik & Siswa', 'route' => 'admin.referensi.guru', 'icon' => 'fas fa-chalkboard-teacher', 'keywords' => 'ptk pendidik'],
            ['title' => 'Data Siswa', 'category' => 'Pendidik & Siswa', 'route' => 'admin.users.siswa', 'icon' => 'fas fa-user-graduate', 'keywords' => 'peserta didik nis nisn'],
            ['title' => 'Rombel & Peta Kelas', 'category' => 'Kelas', 'route' => 'admin.peta-kelas.daftar', 'icon' => 'fas fa-school', 'keywords' => 'rombel wali kelas ruangan'],
            ['title' => 'Mata Pelajaran', 'category' => 'Kelas', 'route' => 'admin.mapel.index', 'icon' => 'fas fa-book', 'keywords' => 'mapel kurikulum'],
            ['title' => 'Data Pembelajaran', 'category' => 'Kelas', 'route' => 'admin.referensi.pembelajaran', 'icon' => 'fas fa-calendar-alt', 'keywords' => 'jadwal guru mapel'],
            ['title' => 'Manajemen Akun & Wali Kelas', 'category' => 'Akun', 'route' => 'admin.users.index', 'icon' => 'fas fa-users-cog', 'keywords' => 'pengguna user reset sandi wali kelas'],
            ['title' => 'Cetak Kode Akses Login', 'category' => 'Akun', 'route' => 'admin.kode-akses.index', 'icon' => 'fas fa-key', 'keywords' => 'kartu login siswa'],
            ['title' => 'Status Penilaian per Guru', 'category' => 'e-Rapor', 'route' => 'admin.penilaian.status', 'icon' => 'fas fa-check-circle', 'keywords' => 'progres nilai rapor'],
            ['title' => 'Statistik Nilai Rapor', 'category' => 'e-Rapor', 'route' => 'admin.penilaian.statistik', 'icon' => 'fas fa-chart-bar', 'keywords' => 'rekap nilai'],
            ['title' => 'Cetak Leger Rapor', 'category' => 'Cetak Rapor', 'route' => 'admin.cetak.leger', 'icon' => 'fas fa-table', 'keywords' => 'leger kelas'],
            ['title' => 'Cetak Nilai Rapor', 'category' => 'Cetak Rapor', 'route' => 'admin.cetak.nilai', 'icon' => 'fas fa-print', 'keywords' => 'print rapor pdf'],
            ['title' => 'Cetak Transkrip Nilai', 'category' => 'Transkrip', 'route' => 'admin.transkrip.cetak', 'icon' => 'fas fa-graduation-cap', 'keywords' => 'ijazah kelulusan'],
            ['title' => 'Sesi Ujian CBT', 'category' => 'CBT & Tryout', 'route' => 'admin.cbt.sessions.index', 'icon' => 'fas fa-laptop-code', 'asesmen' => true, 'keywords' => 'ujian token pengawas'],
            ['title' => 'Bank Soal CBT', 'category' => 'CBT & Tryout', 'route' => 'admin.cbt.banks.index', 'icon' => 'fas fa-database', 'asesmen' => true, 'keywords' => 'soal pilihan ganda'],
            ['title' => 'Paket Tryout Sekolah', 'category' => 'CBT & Tryout', 'route' => 'admin.tryout.packages.index', 'icon' => 'fas fa-box-open', 'asesmen' => true, 'keywords' => 'latihan ujian'],
            ['title' => 'Dashboard Kesiapan TKA', 'category' => 'TKA', 'route' => 'admin.tka.dashboard', 'icon' => 'fas fa-award', 'keywords' => 'tes kemampuan akademik'],
            ['title' => 'Pemantauan Kesiapan TKA', 'category' => 'TKA', 'route' => 'admin.tka-kesiapan.index', 'icon' => 'fas fa-compass', 'kesiapan_tka' => true, 'keywords' => 'siap tka'],
            ['title' => 'Presensi Pegawai & Lokasi', 'category' => 'Presensi', 'route' => 'admin.presensi.index', 'icon' => 'fas fa-map-marker-alt', 'keywords' => 'absensi gps'],
            ['title' => 'Laporan Presensi', 'category' => 'Presensi', 'route' => 'admin.presensi.laporan', 'icon' => 'fas fa-file-alt', 'keywords' => 'rekap absen'],
            ['title' => 'Kartu QR Siswa & Pegawai', 'category' => 'Presensi', 'route' => 'admin.presensi.kartu.index', 'icon' => 'fas fa-qrcode', 'keywords' => 'cetak kartu qr'],
            ['title' => 'Modul & Tampilan', 'category' => 'Sistem', 'route' => 'admin.modul.index', 'icon' => 'fas fa-sliders-h', 'keywords' => 'fitur aktif nonaktif'],
            ['title' => 'Backup & Restore', 'category' => 'Sistem', 'route' => 'admin.backup.index', 'icon' => 'fas fa-database', 'single_school_only' => true, 'keywords' => 'cadangan data'],
            ['title' => 'Log Aktivitas', 'category' => 'Sistem', 'route' => 'admin.log.index', 'icon' => 'fas fa-history', 'single_school_only' => true, 'keywords' => 'audit riwayat'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function guruItems(User $guru): array
    {
        $items = [
            ['title' => 'Dasbor Guru', 'category' => 'Utama', 'route' => 'guru.dashboard', 'icon' => 'fas fa-home', 'keywords' => 'beranda ringkasan'],
            ['title' => 'Kelas Digital (E-Learning)', 'category' => 'Pembelajaran', 'route' => 'guru.kelas-digital.index', 'icon' => 'fas fa-chalkboard', 'keywords' => 'elearning kelas online materi tugas'],
            ['title' => 'Perangkat Ajar (CP, TP, ATP, Prota, Promes, Modul Ajar, KKM)', 'category' => 'Pembelajaran', 'route' => 'guru.perangkat.index', 'icon' => 'fas fa-folder-open', 'keywords' => 'cp tp atp ato prota promes rpp modul ajar kkm kktp perangkat administrasi cetak pdf'],
            ['title' => 'Buat Perangkat Ajar Baru', 'category' => 'Pembelajaran', 'route' => 'guru.perangkat.create', 'icon' => 'fas fa-file-medical', 'keywords' => 'tambah cp tp atp prota promes modul ajar rpp kkm'],
            ['title' => 'Jurnal Mengajar Harian', 'category' => 'Pembelajaran', 'route' => 'guru.perangkat.jurnal.index', 'icon' => 'fas fa-book-reader', 'keywords' => 'jurnal harian catatan mengajar cetak pdf'],
            ['title' => 'Tujuan Pembelajaran (TP Cepat)', 'category' => 'Pembelajaran', 'route' => 'guru.tp.index', 'icon' => 'fas fa-bullseye', 'keywords' => 'tp tujuan kurikulum'],
            ['title' => 'Materi Ajar', 'category' => 'Pembelajaran', 'route' => 'guru.materi.index', 'icon' => 'fas fa-book-open', 'keywords' => 'bahan ajar modul'],
            ['title' => 'Profil Lulusan', 'category' => 'Pembelajaran', 'route' => 'guru.dimensi.index', 'icon' => 'fas fa-seedling', 'keywords' => 'dimensi p5 pembelajaran mendalam'],
            ['title' => 'Input Nilai Cepat', 'category' => 'Asesmen', 'route' => 'guru.input-nilai.index', 'icon' => 'fas fa-keyboard', 'keywords' => 'nilai formatif sumatif'],
            ['title' => 'Input Nilai e-Rapor', 'category' => 'Asesmen', 'route' => 'guru.nilai-erapor.index', 'icon' => 'fas fa-file-signature', 'keywords' => 'nilai rapor akhir'],
            ['title' => 'Input Projek P5', 'category' => 'Asesmen', 'route' => 'guru.p5.index', 'icon' => 'fas fa-star', 'keywords' => 'kokurikuler p5'],
            ['title' => 'Cek Penilaian & Deskripsi', 'category' => 'Asesmen', 'route' => 'guru.cek-penilaian.index', 'icon' => 'fas fa-check-double', 'keywords' => 'validasi deskripsi nilai'],
            ['title' => 'Kuis & Soal', 'category' => 'Asesmen', 'route' => 'guru.kuis.index', 'icon' => 'fas fa-clipboard-list', 'keywords' => 'latihan soal online'],
            ['title' => 'Daftar Nilai', 'category' => 'Asesmen', 'route' => 'guru.nilai.index', 'icon' => 'fas fa-calculator', 'keywords' => 'rekap nilai'],
            ['title' => 'Analisis Belajar', 'category' => 'Asesmen', 'route' => 'guru.analisis.index', 'icon' => 'fas fa-chart-line', 'keywords' => 'ketuntasan remedial'],
            ['title' => 'Sesi Ujian CBT', 'category' => 'CBT & Tryout', 'route' => 'cbt.sessions.index', 'icon' => 'fas fa-desktop', 'asesmen' => true, 'keywords' => 'ujian cbt'],
            ['title' => 'Bank Soal CBT', 'category' => 'CBT & Tryout', 'route' => 'cbt.question-banks.index', 'icon' => 'fas fa-database', 'asesmen' => true, 'keywords' => 'bank soal'],
            ['title' => 'Paket Tryout', 'category' => 'CBT & Tryout', 'route' => 'tryout.packages.index', 'icon' => 'fas fa-box', 'asesmen' => true, 'keywords' => 'tryout'],
            ['title' => 'Monitoring Kesiapan TKA', 'category' => 'TKA', 'route' => 'guru.tka.index', 'icon' => 'fas fa-award', 'keywords' => 'tka kognitif'],
            ['title' => 'Pemantauan Kesiapan TKA', 'category' => 'TKA', 'route' => 'guru.tka-kesiapan.index', 'icon' => 'fas fa-compass', 'kesiapan_tka' => true, 'keywords' => 'tka'],
            ['title' => 'Presensi Saya', 'category' => 'Pemantauan', 'route' => 'presensi.pegawai.index', 'icon' => 'fas fa-map-pin', 'keywords' => 'absen guru'],
            ['title' => 'Kehadiran Siswa', 'category' => 'Pemantauan', 'route' => 'guru.kehadiran.index', 'icon' => 'fas fa-calendar-check', 'keywords' => 'absensi siswa'],
            ['title' => 'Catatan Siswa', 'category' => 'Pemantauan', 'route' => 'guru.catatan.index', 'icon' => 'fas fa-sticky-note', 'keywords' => 'sikap perkembangan'],
            ['title' => '7 Kebiasaan Anak Indonesia Hebat', 'category' => 'Pemantauan', 'route' => 'guru.kebiasaan.index', 'icon' => 'fas fa-sun', 'keywords' => 'karakter kebiasaan'],
            ['title' => 'Data Siswa', 'category' => 'Pemantauan', 'route' => 'guru.siswa.index', 'icon' => 'fas fa-users', 'keywords' => 'daftar peserta didik'],
            ['title' => 'Laporan & Rapor', 'category' => 'Pelaporan', 'route' => 'guru.laporan.index', 'icon' => 'fas fa-print', 'keywords' => 'cetak laporan pdf'],
            ['title' => 'Generate e-Rapor', 'category' => 'Pelaporan', 'route' => 'guru.erapor.index', 'icon' => 'fas fa-file-pdf', 'keywords' => 'unduh rapor'],
            ['title' => 'Pengaturan Guru', 'category' => 'Pengaturan', 'route' => 'guru.pengaturan.index', 'icon' => 'fas fa-cog', 'keywords' => 'profil sandi'],
        ];

        // Menu khusus Wali Kelas HANYA disertakan jika guru benar-benar Wali Kelas.
        if (guruAdalahWaliKelas($guru)) {
            $items[] = ['title' => 'Input Nilai Ekskul (Wali Kelas)', 'category' => 'Menu Wali Kelas', 'route' => 'guru.walas.ekskul', 'icon' => 'fas fa-running', 'keywords' => 'ekstrakurikuler walas'];
            $items[] = ['title' => 'Input Kenaikan Kelas (Wali Kelas)', 'category' => 'Menu Wali Kelas', 'route' => 'guru.walas.naik-kelas', 'icon' => 'fas fa-level-up-alt', 'keywords' => 'naik kelas lulus walas'];
            $items[] = ['title' => 'Status & Statistik Penilaian Kelas', 'category' => 'Menu Wali Kelas', 'route' => 'guru.walas.status-penilaian', 'icon' => 'fas fa-chart-pie', 'keywords' => 'progres kelas walas'];
            $items[] = ['title' => 'Leger & Rapor P5 Kelas', 'category' => 'Menu Wali Kelas', 'route' => 'guru.walas.leger', 'icon' => 'fas fa-table', 'keywords' => 'leger walas p5'];
            $items[] = ['title' => 'Transkrip Ijazah (Wali Kelas)', 'category' => 'Menu Wali Kelas', 'route' => 'guru.walas.transkrip', 'icon' => 'fas fa-graduation-cap', 'keywords' => 'ijazah walas'];
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    private function siswaItems(): array
    {
        return [
            ['title' => 'Beranda Siswa', 'category' => 'Menu Siswa', 'route' => 'siswa.dashboard', 'icon' => 'fas fa-home', 'keywords' => 'beranda utama'],
            ['title' => 'Kelas Digital', 'category' => 'Belajar', 'route' => 'siswa.kelas-digital.index', 'icon' => 'fas fa-chalkboard', 'keywords' => 'elearning kelas online materi tugas kumpul'],
            ['title' => 'Materi Pelajaran', 'category' => 'Belajar', 'route' => 'siswa.materi.index', 'icon' => 'fas fa-book-open', 'keywords' => 'modul bacaan'],
            ['title' => 'Kuis Online', 'category' => 'Belajar', 'route' => 'siswa.kuis.index', 'icon' => 'fas fa-clipboard-list', 'keywords' => 'latihan soal'],
            ['title' => 'Nilaiku', 'category' => 'Capaian', 'route' => 'siswa.nilai.index', 'icon' => 'fas fa-chart-line', 'keywords' => 'hasil belajar'],
            ['title' => 'Rekap Capaian Rapor', 'category' => 'Capaian', 'route' => 'siswa.rapor.capaian', 'icon' => 'fas fa-award', 'keywords' => 'capaian rapor'],
            ['title' => 'Download Rapor', 'category' => 'Capaian', 'route' => 'siswa.rapor.unduh', 'icon' => 'fas fa-file-download', 'keywords' => 'unduh pdf rapor'],
            ['title' => 'Kartu Pelajarku', 'category' => 'Profil', 'route' => 'siswa.kartu.cetak', 'icon' => 'fas fa-id-card', 'keywords' => 'kartu qr'],
            ['title' => 'Profil Saya', 'category' => 'Profil', 'route' => 'siswa.profil.show', 'icon' => 'fas fa-user', 'keywords' => 'biodata'],
            ['title' => 'Ujian CBT', 'category' => 'Ujian & TKA', 'route' => 'cbt.student.index', 'icon' => 'fas fa-laptop', 'asesmen' => true, 'keywords' => 'ujian sekolah'],
            ['title' => 'Tryout Sekolah', 'category' => 'Ujian & TKA', 'route' => 'tryout.student.index', 'icon' => 'fas fa-box', 'asesmen' => true, 'keywords' => 'latihan tryout'],
            ['title' => 'Kesiapan Belajarku (TKA)', 'category' => 'Ujian & TKA', 'route' => 'siswa.tka-kesiapan.index', 'icon' => 'fas fa-compass', 'kesiapan_tka' => true, 'keywords' => 'tka'],
            ['title' => 'Simulasi TKA Pusmendik', 'category' => 'Ujian & TKA', 'route' => 'siswa.tka-simulasi.index', 'icon' => 'fas fa-brain', 'keywords' => 'simulasi literasi numerasi'],
            ['title' => 'Profil Kognitif & Latihanku', 'category' => 'Ujian & TKA', 'route' => 'siswa.tka-simulasi.kognitif', 'icon' => 'fas fa-chart-bar', 'keywords' => 'rekomendasi belajar'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function kepsekItems(): array
    {
        return [
            ['title' => 'Dasbor Kepala Sekolah', 'category' => 'Utama', 'route' => 'kepsek.dashboard', 'icon' => 'fas fa-tachometer-alt', 'keywords' => 'beranda ringkasan'],
            ['title' => 'Pantau Aktivitas Sekolah', 'category' => 'Pemantauan', 'route' => 'kepsek.pantau.index', 'icon' => 'fas fa-eye', 'keywords' => 'pantau harian'],
            ['title' => 'Supervisi Perangkat Ajar Guru', 'category' => 'Pemantauan', 'route' => 'kepsek.perangkat.index', 'icon' => 'fas fa-folder-open', 'keywords' => 'supervisi cp tp atp prota promes modul ajar rpp kkm jurnal'],
            ['title' => 'Rekap Kinerja Guru', 'category' => 'Pemantauan', 'route' => 'kepsek.rekap.index', 'icon' => 'fas fa-file-alt', 'keywords' => 'rekap guru laporan'],
            ['title' => 'Peta Kelas Sekolah', 'category' => 'Pemantauan', 'route' => 'kepsek.peta-kelas.index', 'icon' => 'fas fa-school', 'single_school_only' => true, 'keywords' => 'rombel kelas'],
            ['title' => 'Hasil Belajar Siswa', 'category' => 'Pemantauan', 'route' => 'kepsek.hasil-belajar.index', 'icon' => 'fas fa-chart-bar', 'single_school_only' => true, 'keywords' => 'nilai rapor siswa'],
            ['title' => '7 Kebiasaan Anak Indonesia Hebat', 'category' => 'Pemantauan', 'route' => 'kepsek.kebiasaan.index', 'icon' => 'fas fa-sun', 'keywords' => 'karakter'],
            ['title' => 'Log Aktivitas Sekolah', 'category' => 'Pemantauan', 'route' => 'kepsek.aktivitas.index', 'icon' => 'fas fa-history', 'keywords' => 'riwayat'],
            ['title' => 'Laporan TKA Sekolah', 'category' => 'Pemantauan', 'route' => 'kepsek.tka-laporan.index', 'icon' => 'fas fa-award', 'keywords' => 'tka kognitif'],
            ['title' => 'Pemantauan Kesiapan TKA', 'category' => 'Pemantauan', 'route' => 'kepsek.tka-kesiapan.index', 'icon' => 'fas fa-compass', 'kesiapan_tka' => true, 'keywords' => 'tka'],
            ['title' => 'Presensi Saya', 'category' => 'Presensi', 'route' => 'presensi.pegawai.index', 'icon' => 'fas fa-map-pin', 'keywords' => 'absensi'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ortuItems(): array
    {
        return [
            ['title' => 'Beranda Orang Tua', 'category' => 'Menu Orang Tua', 'route' => 'ortu.dashboard', 'icon' => 'fas fa-home', 'keywords' => 'beranda anak'],
            ['title' => '7 Kebiasaan di Rumah', 'category' => 'Pemantauan', 'route' => 'ortu.kebiasaan.index', 'icon' => 'fas fa-sun', 'keywords' => 'karakter kebiasaan'],
            ['title' => 'Nilai Anak', 'category' => 'Pemantauan', 'route' => 'ortu.nilai.index', 'icon' => 'fas fa-chart-line', 'keywords' => 'nilai pelajaran'],
            ['title' => 'Rekap Belajar Anak', 'category' => 'Pemantauan', 'route' => 'ortu.rekap.index', 'icon' => 'fas fa-award', 'keywords' => 'rekap capaian'],
            ['title' => 'Kehadiran & Izin Anak', 'category' => 'Pemantauan', 'route' => 'ortu.kehadiran.index', 'icon' => 'fas fa-calendar-check', 'keywords' => 'absensi izin'],
            ['title' => 'Catatan Guru', 'category' => 'Pemantauan', 'route' => 'ortu.catatan.index', 'icon' => 'fas fa-sticky-note', 'keywords' => 'perkembangan'],
            ['title' => 'Laporan & Rapor Anak', 'category' => 'Pemantauan', 'route' => 'ortu.laporan.index', 'icon' => 'fas fa-file-alt', 'keywords' => 'rapor'],
            ['title' => 'Riwayat Simulasi TKA Anak', 'category' => 'Pemantauan', 'route' => 'ortu.tka-riwayat.index', 'icon' => 'fas fa-brain', 'keywords' => 'tka'],
            ['title' => 'Kesiapan TKA Anak', 'category' => 'Pemantauan', 'route' => 'ortu.tka-kesiapan.index', 'icon' => 'fas fa-compass', 'kesiapan_tka' => true, 'keywords' => 'tka'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function tendikItems(): array
    {
        return [
            ['title' => 'Presensi Mandiri Saya', 'category' => 'Presensi', 'route' => 'presensi.pegawai.index', 'icon' => 'fas fa-map-pin', 'keywords' => 'absen kehadiran beranda'],
            ['title' => 'Kartu Identitas Presensi', 'category' => 'Identitas', 'route' => 'presensi.pegawai.kartu', 'icon' => 'fas fa-id-card', 'keywords' => 'kartu qr pegawai'],
        ];
    }
}
