{{-- Sidebar admin — SATU SUMBER.
     Semua grup menu disimpan di sini, dikelompokkan per tab:
       erapor → dasbor, penilaian, pengaturan rapor, transkrip, cetak
       cbt   → pengelolaan sesi CBT dan Tryout
       tka   → kesiapan, penilaian, dan peringatan TKA
       setup → sekolah, data akademik, integrasi, presensi, akun, dan sistem
     Renderer (loop, active, planned) berasal dari komponen <x-admin-menu />.
     Menu ditentukan lewat @include dari layout admin dengan data ['tab' => ...]. --}}
@php
    // Status sinkron dipisah menggunakan tenant_id yang ditetapkan server.
    $lastSyncAt = \App\Services\Dapodik\SidebarSyncStatus::latest()['created_at'] ?? null;
    $multiSchoolMode = \App\Modules\Tenant\Support\TenantMode::isMultiSchool();
    // Aturan: grup yang punya 'children' dirender sebagai header lipat
    // (<details>), TANPA label grup terpisah — persis pola e-Rapor.
    // Item 'planned' => true berarti backend belum ada (Fase 2): tampil
    // nonaktif, BUKAN link mati. Jangan beri 'route' pada item planned.

    $isActive = function ($route) {
        try {
            return sidebarRouteActive($route);
        } catch (\Exception $e) {
            return false;
        }
    };
    $isPlanned = function ($item) {
        return ($item['planned'] ?? false) === true || !isset($item['route']);
    };

    $menuAdmin = [
        'erapor' => [
            ['label' => 'UTAMA', 'items' => [
                ['route' => 'admin.profile', 'icon' => 'lucide-id-card', 'label' => 'Profil'],
            ]],
            ['label' => 'STATUS PENILAIAN', 'icon' => 'lucide-graduation-cap', 'children' => [
                ['route' => 'admin.penilaian.status', 'icon' => 'lucide-check-circle', 'label' => 'Status per Guru'],
                ['route' => 'admin.penilaian.statistik', 'icon' => 'lucide-bar-chart-2', 'label' => 'Statistik Nilai Rapor'],
            ]],
            ['label' => 'PERKEMBANGAN NILAI', 'icon' => 'lucide-trending-up', 'children' => [
                ['route' => 'admin.perkembangan.index', 'icon' => 'lucide-trending-up', 'label' => 'Perkembangan Nilai'],
                ['route' => 'admin.perkembangan.grafik', 'icon' => 'lucide-area-chart', 'label' => 'Grafik Nilai Rapor'],
            ]],
            ['label' => 'PENGATURAN CETAK & RAPOR', 'icon' => 'lucide-file-cog', 'children' => [
                ['route' => 'admin.referensi.mapping-rapor', 'icon' => 'lucide-shuffle', 'label' => 'Mapping Rapor'],
                ['route' => 'admin.referensi.tanggal-rapor', 'icon' => 'lucide-calendar', 'label' => 'Tanggal Rapor'],
                ['route' => 'admin.referensi.logo-ttd', 'icon' => 'lucide-image', 'label' => 'Logo dan TTD'],
                ['route' => 'admin.referensi.alignment', 'icon' => 'lucide-link', 'label' => 'Cek Alignment'],
                ['route' => 'admin.bobot.index', 'icon' => 'lucide-scale', 'label' => 'Bobot & Ketuntasan'],
                ['route' => 'admin.rapor.naik-kelas', 'icon' => 'lucide-arrow-up-circle', 'label' => 'Pengaturan Naik Kelas'],
                ['route' => 'admin.rapor.p5.index', 'icon' => 'lucide-sparkles', 'label' => 'Penilaian P5BK'],
            ]],
            ['label' => 'TRANSKRIP IJAZAH', 'icon' => 'lucide-file-text', 'children' => [
                ['route' => 'admin.transkrip.setting', 'icon' => 'lucide-settings', 'label' => 'Setting Transkrip'],
                ['route' => 'admin.transkrip.nomor', 'icon' => 'lucide-file-input', 'label' => 'Nomor Ijazah'],
                ['route' => 'admin.transkrip.input', 'icon' => 'lucide-pen-square', 'label' => 'Input Nilai Transkrip'],
                ['route' => 'admin.transkrip.import', 'icon' => 'lucide-upload', 'label' => 'Import Nilai Transkrip'],
                ['route' => 'admin.transkrip.cetak', 'icon' => 'lucide-printer', 'label' => 'Cetak Transkrip Nilai'],
            ]],
            ['label' => 'CETAK NILAI', 'icon' => 'lucide-printer', 'children' => [
                ['route' => 'admin.cetak.kelengkapan', 'icon' => 'lucide-check-square', 'label' => 'Kelengkapan Nilai'],
                ['route' => 'admin.cetak.leger', 'icon' => 'lucide-table', 'label' => 'Leger Rapor'],
                ['route' => 'admin.cetak.pelengkap', 'icon' => 'lucide-scroll-text', 'label' => 'Pelengkap Rapor'],
                ['route' => 'admin.cetak.nilai', 'icon' => 'lucide-file-signature', 'label' => 'Nilai Rapor'],
                ['route' => 'admin.cetak.pengaturan-rapor', 'icon' => 'lucide-sliders', 'label' => 'Pengaturan Cetak Rapor'],
                ['route' => 'admin.cetak.modifikasi-tampilan', 'icon' => 'lucide-palette', 'label' => 'Modifikasi Tampilan Rapor'],
            ]],
        ],
        'cbt' => [
            ['label' => 'UTAMA', 'items' => [
                ['route' => 'admin.tryout.statistics', 'icon' => 'lucide-bar-chart-3', 'label' => 'Statistik Ujian'],
            ]],
            ['label' => 'MANAJEMEN UJIAN CBT', 'icon' => 'lucide-monitor', 'children' => array_values(array_filter([
                modulAsesmenAktif() ? ['route' => 'admin.cbt.sessions.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Sesi Ujian CBT'] : null,
                ['route' => 'admin.cbt.banks.index', 'icon' => 'lucide-database', 'label' => 'Bank Soal CBT'],
            ]))],
            ['label' => 'TRYOUT SEKOLAH', 'icon' => 'lucide-package', 'children' => [
                ['route' => 'admin.tryout.packages.index', 'icon' => 'lucide-package', 'label' => 'Paket Tryout'],
                ['route' => 'admin.tryout.sessions.index', 'icon' => 'lucide-calendar-clock', 'label' => 'Sesi Tryout'],
                ['route' => 'admin.tryout.results.index', 'icon' => 'lucide-chart-line', 'label' => 'Hasil Tryout'],
            ]],
        ],
        'tka' => [
            [
                'label' => 'TES KEMAMPUAN AKADEMIK (TKA)', 'icon' => 'lucide-award', 'children' => [
                    ['route' => 'admin.tka-kesiapan.index', 'kesiapan_tka' => true, 'icon' => 'lucide-compass', 'label' => 'Pemantauan Kesiapan TKA'],
                    ['route' => 'admin.tka-kesiapan.pengaturan', 'kesiapan_tka' => true, 'icon' => 'lucide-sliders-horizontal', 'label' => 'Pengaturan & Pemetaan TKA'],
                    ['route' => 'admin.tka.dashboard', 'icon' => 'lucide-gauge', 'label' => 'Dashboard Kesiapan TKA'],
                    ['route' => 'admin.tka.kognitif.index', 'icon' => 'lucide-brain', 'label' => 'Analisis Kognitif Siswa'],
                    ['route' => 'admin.tka.laporan-kelas.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Laporan Kelas & Heatmap'],
                    ['route' => 'admin.tka.dimensions.index', 'icon' => 'lucide-layers', 'label' => 'Dimensi & Kisi-kisi TKA'],
                    ['route' => 'admin.tka.assessments.index', 'icon' => 'lucide-clipboard-check', 'label' => 'Data Penilaian Peserta'],
                    ['route' => 'admin.tka.warnings.index', 'icon' => 'lucide-alert-triangle', 'label' => 'Early Warning Kesiapan'],
                ],
            ],
        ],
        'setup' => [
            ['label' => 'UTAMA', 'items' => [
                ['route' => 'admin.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor'],
            ]],
            ['label' => 'INTEGRASI & IMPOR DATA', 'icon' => 'lucide-database-zap', 'children' => [
                ['route' => 'admin.dapodik.index', 'icon' => 'lucide-refresh-cw', 'label' => 'Dapodik & Impor Data', 'badge' => $lastSyncAt ? \Carbon\Carbon::parse($lastSyncAt)->diffForHumans() : 'Belum sync', 'badge_type' => $lastSyncAt ? 'ok' : 'warn'],
                ['route' => 'admin.dapodik.push.index', 'icon' => 'lucide-upload', 'label' => 'Kirim Nilai Ke Dapodik'],
            ]],
            ['label' => 'DATA SEKOLAH & PERIODE', 'icon' => 'lucide-building-2', 'children' => [
                ['route' => 'admin.sekolah.index', 'icon' => 'lucide-building-2', 'label' => 'Profil Sekolah'],
                ['route' => 'admin.semester.index', 'icon' => 'lucide-calendar', 'label' => 'Data Semester & Tahun Ajaran'],
                ['route' => 'admin.validasi-data.index', 'icon' => 'lucide-clipboard-check', 'label' => 'Validasi Kelengkapan Data'],
            ]],
            ['label' => 'PENDIDIK & PESERTA DIDIK', 'icon' => 'lucide-users', 'children' => [
                ['route' => 'admin.referensi.guru', 'icon' => 'lucide-presentation', 'label' => 'Data Guru & PTK'],
                ['route' => 'admin.users.siswa', 'icon' => 'lucide-graduation-cap', 'label' => 'Data Siswa'],
                ['route' => 'admin.referensi.foto-siswa', 'icon' => 'lucide-camera', 'label' => 'Foto Siswa'],
            ]],
            ['label' => 'KELAS & PEMBELAJARAN', 'icon' => 'lucide-school', 'children' => [
                ['route' => 'admin.peta-kelas.daftar', 'icon' => 'lucide-school', 'label' => 'Rombel & Peta Kelas'],
                ['route' => 'admin.mapel.index', 'icon' => 'lucide-book', 'label' => 'Mata Pelajaran'],
                ['route' => 'admin.pm.alokasi.index', 'icon' => 'lucide-clock', 'label' => 'Alokasi Waktu Kurikulum'],
                ['route' => 'admin.pm.kalender.index', 'icon' => 'lucide-calendar-range', 'label' => 'Kalender & Hari Libur'],
                ['route' => 'admin.referensi.pembelajaran', 'icon' => 'lucide-calendar-days', 'label' => 'Data Pembelajaran'],
                ['route' => 'admin.referensi.ekstrakurikuler', 'icon' => 'lucide-activity', 'label' => 'Data Ekstrakurikuler'],
                ['route' => 'admin.referensi.kelompok-mapel', 'icon' => 'lucide-layers', 'label' => 'Data Kelompok Mapel'],
                ['route' => 'admin.rapor.teknik-penilaian', 'icon' => 'lucide-check-circle', 'label' => 'Teknik Penilaian'],
                ['route' => 'admin.rapor.siswa-belajar', 'icon' => 'lucide-user-check', 'label' => 'Siswa Belajar'],
            ]],
            ['label' => 'AKUN & KODE AKSES', 'icon' => 'lucide-key', 'children' => [
                ['route' => 'admin.users.index', 'icon' => 'lucide-users', 'label' => 'Data Pengguna (Manajemen Akun)'],
                ['route' => 'admin.kode-akses.index', 'icon' => 'lucide-key', 'label' => 'Cetak Kode Akses Login'],
            ]],
            ['label' => 'PRESENSI', 'icon' => 'lucide-calendar-check', 'children' => [
                ['route' => 'admin.presensi.dashboard.mesin', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dashboard Presensi Mesin'],
                ['route' => 'admin.presensi.mesin.sinkron', 'icon' => 'lucide-history', 'label' => 'Riwayat Sinkron Mesin'],
                ['route' => 'admin.presensi.index', 'icon' => 'lucide-map-pin-check', 'label' => 'Presensi Pegawai & Lokasi'],
                ['route' => 'admin.presensi.laporan', 'icon' => 'lucide-file-text', 'label' => 'Laporan Presensi'],
                ['route' => 'admin.presensi.mesin.index', 'icon' => 'lucide-upload', 'label' => 'Import Presensi Mesin'],
                ['route' => 'admin.presensi.kartu.index', 'icon' => 'lucide-scan-line', 'label' => 'Kartu QR Siswa & Pegawai'],
            ]],
            ['label' => 'KOKURIKULER', 'icon' => 'lucide-notebook-pen', 'children' => [
                ['route' => 'admin.kokurikuler.tema', 'icon' => 'lucide-target', 'label' => 'Daftar Tema'],
                ['route' => 'admin.kokurikuler.kegiatan', 'icon' => 'lucide-list-checks', 'label' => 'Kegiatan Kokurikuler'],
                ['route' => 'admin.kokurikuler.kelompok', 'icon' => 'lucide-users', 'label' => 'Kelompok Kokurikuler'],
            ]],
            ['label' => 'SISTEM & KEAMANAN', 'icon' => 'lucide-server-cog', 'children' => [
                ['route' => 'admin.modul.index', 'icon' => 'lucide-box', 'label' => 'Modul & Tampilan'],
                ['route' => 'admin.sheet.index', 'icon' => 'lucide-table', 'label' => 'Penyimpanan Data'],
                ['route' => 'admin.backup.index', 'icon' => 'lucide-database', 'label' => 'Backup & Restore'],
                ['route' => 'admin.api-keys.index', 'icon' => 'lucide-key-round', 'label' => 'Kunci API Bridge'],
                ['route' => 'admin.log.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Log Aktivitas'],
            ]],
        ],
    ];

    // Sembunyikan fitur data global yang belum aman untuk tenant, modul yang
    // dimatikan, serta menu yang tabel/datanya belum terisi di sekolah ini.
    $dataAvail = \App\Services\Navigation\SchoolDataAvailabilityService::status();
    foreach ($menuAdmin as &$groups) {
        $filteredGroups = [];
        foreach ($groups as $group) {
            if (isset($group['children'])) {
                $group['children'] = array_values(array_filter($group['children'], function ($item) use ($multiSchoolMode, $dataAvail, $isActive) {
                    $route = $item['route'] ?? '';
                    if ($multiSchoolMode && in_array($route, ['admin.backup.index', 'admin.log.index'], true)) {
                        return false;
                    }
                    if (!kesiapanTkaAktif() && !empty($item['kesiapan_tka'])) {
                        return false;
                    }
                    if ($route !== '' && !$isActive($route) && !\App\Services\Navigation\SchoolDataAvailabilityService::canShowAdminRoute($route, $dataAvail)) {
                        return false;
                    }

                    return true;
                }));

                if ($group['children'] !== []) {
                    $filteredGroups[] = $group;
                }
            } elseif (isset($group['items'])) {
                $group['items'] = array_values(array_filter($group['items'], function ($item) use ($multiSchoolMode, $dataAvail, $isActive) {
                    $route = $item['route'] ?? '';
                    if ($multiSchoolMode && in_array($route, ['admin.backup.index', 'admin.log.index'], true)) {
                        return false;
                    }
                    if (!kesiapanTkaAktif() && !empty($item['kesiapan_tka'])) {
                        return false;
                    }
                    if ($route !== '' && !$isActive($route) && !\App\Services\Navigation\SchoolDataAvailabilityService::canShowAdminRoute($route, $dataAvail)) {
                        return false;
                    }

                    return true;
                }));

                if ($group['items'] !== []) {
                    $filteredGroups[] = $group;
                }
            }
        }
        $groups = $filteredGroups;
    }
    unset($groups);

    $tabSekarang = $tab ?? 'setup';
    $grupMenu = $menuAdmin[$tabSekarang] ?? $menuAdmin['setup'];
@endphp

<x-admin-menu :groups="$grupMenu" />
