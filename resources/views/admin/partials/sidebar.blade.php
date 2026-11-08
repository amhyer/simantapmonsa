{{-- Sidebar admin — SATU SUMBER.
     Semua grup menu disimpan di sini, dikelompokkan per tab:
       erapor → menu lengkap (e-Rapor, Dapodik, presensi, data referensi, CBT/Tryout, TKA, transkrip, cetak, sistem)
       cbt   → menu fokus CBT & Tryout
       tka   → menu fokus TKA
       setup → menu fokus setup & master data
     Renderer (loop, active, planned) berasal dari komponen <x-admin-menu />.
     Menu ditentukan lewat @include dari layout admin dengan data ['tab' => ...]. --}}
@php
    // Status sinkron dipisah menggunakan tenant_id yang ditetapkan server.
    $lastSyncAt = \App\Services\Dapodik\SidebarSyncStatus::latest()['created_at'] ?? null;
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
                ['route' => 'admin.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor Sistem'],
                ['route' => 'admin.profile', 'icon' => 'lucide-id-card', 'label' => 'Profile'],
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
            ]],
            ['label' => 'TRANSKRIP IJAZAH', 'icon' => 'lucide-file-text', 'children' => [
                ['route' => 'admin.transkrip.setting', 'icon' => 'lucide-settings', 'label' => 'Setting Transkrip'],
                ['route' => 'admin.transkrip.nomor', 'icon' => 'lucide-file-input', 'label' => 'Nomor Ijazah'],
                ['route' => 'admin.transkrip.input', 'icon' => 'lucide-pen-square', 'label' => 'Input Nilai Transkrip'],
                ['route' => 'admin.transkrip.import', 'icon' => 'lucide-upload', 'label' => 'Import Nilai Transkrip'],
                ['route' => 'admin.transkrip.cetak', 'icon' => 'lucide-printer', 'label' => 'Cetak Transkrip Nilai'],
            ]],
            ['label' => 'CETAK NILAI', 'icon' => 'lucide-printer', 'children' => [
                ['route' => 'admin.cetak.leger', 'icon' => 'lucide-table', 'label' => 'Leger Rapor'],
                ['route' => 'admin.cetak.pelengkap', 'icon' => 'lucide-scroll-text', 'label' => 'Pelengkap Rapor'],
                ['route' => 'admin.cetak.nilai', 'icon' => 'lucide-file-signature', 'label' => 'Nilai Rapor'],
            ]],
        ],
        'cbt' => [
            ['label' => 'UTAMA', 'items' => [
                ['route' => 'admin.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor Utama (E-Rapor)'],
                ['route' => 'admin.tryout.statistics', 'icon' => 'lucide-bar-chart-3', 'label' => 'Statistik Ujian'],
            ]],
            ['label' => 'MANAJEMEN UJIAN CBT', 'icon' => 'lucide-monitor', 'children' => [
                ['route' => 'admin.cbt.sessions.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Sesi Ujian CBT'],
                ['route' => 'admin.cbt.banks.index', 'icon' => 'lucide-database', 'label' => 'Bank Soal CBT'],
            ]],
            ['label' => 'TRYOUT SEKOLAH', 'icon' => 'lucide-package', 'children' => [
                ['route' => 'admin.tryout.packages.index', 'icon' => 'lucide-package', 'label' => 'Paket Tryout'],
                ['route' => 'admin.tryout.sessions.index', 'icon' => 'lucide-calendar-clock', 'label' => 'Sesi Tryout'],
                ['route' => 'admin.tryout.results.index', 'icon' => 'lucide-chart-line', 'label' => 'Hasil Tryout'],
            ]],
            ['label' => 'SISTEM', 'icon' => 'lucide-server-cog', 'children' => [
                ['route' => 'admin.modul.index', 'icon' => 'lucide-box', 'label' => 'Modul & Tampilan (Setup)'],
                ['route' => 'admin.log.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Log Aktivitas (Setup)'],
            ]],
        ],
        'tka' => [
            [
                'label' => 'TES KEMAMPUAN AKADEMIK (TKA)', 'icon' => 'lucide-award', 'children' => [
                    ['route' => 'admin.tka.dashboard', 'icon' => 'lucide-gauge', 'label' => 'Dashboard Kesiapan TKA'],
                    ['route' => 'admin.tka.dimensions.index', 'icon' => 'lucide-layers', 'label' => 'Dimensi & Kisi-kisi TKA'],
                    ['route' => 'admin.tka.assessments.index', 'icon' => 'lucide-clipboard-check', 'label' => 'Data Penilaian Peserta'],
                    ['route' => 'admin.tka.warnings.index', 'icon' => 'lucide-alert-triangle', 'label' => 'Early Warning Kesiapan'],
                ],
            ],
        ],
        'setup' => [
            ['label' => 'DAPODIK (INTEGRASI 1-PINTU)', 'icon' => 'lucide-database-zap', 'children' => [
                ['route' => 'admin.dapodik.index', 'icon' => 'lucide-refresh-cw', 'label' => 'Tarik Data Dapodik', 'badge' => $lastSyncAt ? \Carbon\Carbon::parse($lastSyncAt)->diffForHumans() : 'Belum sync', 'badge_type' => $lastSyncAt ? 'ok' : 'warn'],
                ['route' => 'admin.dapodik.push.index', 'icon' => 'lucide-upload', 'label' => 'Kirim Nilai Ke Dapodik'],
            ]],
            ['label' => 'DATA SEKOLAH & SEMESTER', 'icon' => 'lucide-building-2', 'children' => [
                ['route' => 'admin.sekolah.index', 'icon' => 'lucide-building-2', 'label' => 'Profil Sekolah'],
                ['route' => 'admin.semester.index', 'icon' => 'lucide-calendar', 'label' => 'Data Semester & Tahun Ajaran'],
            ]],
            ['label' => 'DATA GURU & SISWA', 'icon' => 'lucide-users', 'children' => [
                ['route' => 'admin.referensi.guru', 'icon' => 'lucide-presentation', 'label' => 'Data Guru & PTK'],
                ['route' => 'admin.users.siswa', 'icon' => 'lucide-graduation-cap', 'label' => 'Data Siswa'],
                ['route' => 'admin.referensi.foto-siswa', 'icon' => 'lucide-camera', 'label' => 'Foto Siswa'],
            ]],
            ['label' => 'ROMBEL & PEMBELAJARAN', 'icon' => 'lucide-school', 'children' => [
                ['route' => 'admin.peta-kelas.daftar', 'icon' => 'lucide-school', 'label' => 'Rombel & Peta Kelas'],
                ['route' => 'admin.mapel.index', 'icon' => 'lucide-book', 'label' => 'Mata Pelajaran'],
                ['route' => 'admin.referensi.pembelajaran', 'icon' => 'lucide-calendar-days', 'label' => 'Data Pembelajaran'],
                ['route' => 'admin.referensi.ekstrakurikuler', 'icon' => 'lucide-activity', 'label' => 'Data Ekstrakurikuler'],
                ['route' => 'admin.referensi.kelompok-mapel', 'icon' => 'lucide-layers', 'label' => 'Data Kelompok Mapel'],
            ]],
            ['label' => 'AKUN & KODE AKSES', 'icon' => 'lucide-key', 'children' => [
                ['route' => 'admin.users.index', 'icon' => 'lucide-users', 'label' => 'Manajemen Akun'],
                ['route' => 'admin.kode-akses.index', 'icon' => 'lucide-key', 'label' => 'Cetak Kode Akses Login'],
            ]],
            ['label' => 'PRESENSI', 'icon' => 'lucide-calendar-check', 'children' => [
                ['route' => 'admin.presensi.index', 'icon' => 'lucide-map-pin-check', 'label' => 'Presensi Pegawai & Lokasi'],
                ['route' => 'admin.presensi.laporan', 'icon' => 'lucide-file-text', 'label' => 'Laporan Presensi'],
                ['route' => 'admin.presensi.mesin.index', 'icon' => 'lucide-upload', 'label' => 'Import Presensi Mesin'],
                ['route' => 'admin.presensi.kartu.index', 'icon' => 'lucide-scan-line', 'label' => 'Kartu QR Siswa & Pegawai'],
            ]],
            ['label' => 'DATA KOKURIKULER (P5)', 'icon' => 'lucide-notebook-pen', 'children' => [
                ['route' => 'admin.kokurikuler.tema', 'icon' => 'lucide-target', 'label' => 'Daftar Tema'],
                ['route' => 'admin.kokurikuler.kegiatan', 'icon' => 'lucide-list-checks', 'label' => 'Kegiatan Kokurikuler'],
                ['route' => 'admin.kokurikuler.kelompok', 'icon' => 'lucide-users', 'label' => 'Kelompok Kokurikuler'],
            ]],
            ['label' => 'SISTEM & MODUL', 'icon' => 'lucide-server-cog', 'children' => [
                ['route' => 'admin.modul.index', 'icon' => 'lucide-box', 'label' => 'Modul & Tampilan'],
                ['route' => 'admin.bobot.index', 'icon' => 'lucide-scale', 'label' => 'Bobot & Ketuntasan'],
                ['route' => 'admin.sheet.index', 'icon' => 'lucide-table', 'label' => 'Penyimpanan Data'],
                ['route' => 'admin.backup.index', 'icon' => 'lucide-database', 'label' => 'Backup & Restore'],
                ['route' => 'admin.api-keys.index', 'icon' => 'lucide-key-round', 'label' => 'Kunci API Bridge'],
                ['route' => 'admin.log.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Log Aktivitas'],
            ]],
        ],
    ];

    // Sembunyikan fitur data global yang belum aman untuk tenant.
    if (\App\Modules\Tenant\Contexts\TenantContext::check() || config('simantap.multi_tenant_enabled', false) || \App\Modules\Tenant\Models\Tenant::query()->exists()) {
        foreach ($menuAdmin as &$groups) {
            foreach ($groups as &$group) {
                if (isset($group['children'])) {
                    $group['children'] = array_values(array_filter($group['children'], fn ($item) =>
                        ! in_array($item['route'] ?? '', ['admin.dapodik.push.index', 'admin.backup.index', 'admin.log.index'], true)
                    ));
                }
            }
            unset($group);
        }
        unset($groups);
    }

    $tabSekarang = $tab ?? 'erapor';
    $grupMenu = $menuAdmin[$tabSekarang] ?? $menuAdmin['erapor'];
@endphp

<x-admin-menu :groups="$grupMenu" />
