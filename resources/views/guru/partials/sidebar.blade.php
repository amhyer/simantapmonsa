@php
    $adalahWaliKelas = guruAdalahWaliKelas();
    $menuGuru = [
        ['label' => 'MENU UTAMA', 'items' => [
            ['route' => 'guru.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor'],
        ]],
        ['label' => 'PEMBELAJARAN', 'items' => [
            ['route' => 'guru.kelas-digital.index', 'icon' => 'lucide-school', 'label' => 'Kelas Digital'],
            ['label' => 'Pembelajaran Mendalam', 'icon' => 'lucide-sparkles', 'open' => request()->routeIs('guru.pm.*', 'guru.perangkat.*'), 'children' => [
                ['route' => 'guru.pm.alokasi.index', 'icon' => 'lucide-clock', 'label' => 'Alokasi Waktu'],
                ['route' => 'guru.pm.cp.index', 'icon' => 'lucide-award', 'label' => 'Capaian Pembelajaran (CP)'],
                ['route' => 'guru.pm.tp.index', 'icon' => 'lucide-target', 'label' => 'Tujuan Pembelajaran (TP)'],
                ['route' => 'guru.pm.atp.index', 'icon' => 'lucide-git-branch', 'label' => 'Alur Tujuan Pembelajaran'],
                ['route' => 'guru.pm.buku-paket.index', 'icon' => 'lucide-book-copy', 'label' => 'Buku Paket (SIBI)'],
                ['route' => 'guru.pm.kktp.index', 'icon' => 'lucide-sliders', 'label' => 'KKTP'],
                ['route' => 'guru.pm.kalender.index', 'icon' => 'lucide-calendar-days', 'label' => 'Kalender & Hari Libur'],
                ['route' => 'guru.pm.prota.index', 'icon' => 'lucide-calendar-range', 'label' => 'Program Tahunan (Prota)'],
                ['route' => 'guru.pm.prosem.index', 'icon' => 'lucide-grid-3x3', 'label' => 'Program Semester (Prosem)'],
                ['route' => 'guru.perangkat.index', 'icon' => 'lucide-folder-open', 'label' => 'Modul Ajar / RPM'],
                ['route' => 'guru.perangkat.jurnal.index', 'icon' => 'lucide-book-marked', 'label' => 'Jurnal Mengajar'],
                ['route' => 'guru.pm.generate.index', 'icon' => 'lucide-wand-2', 'label' => 'Buat Modul & Jurnal Sekaligus'],
                ['route' => 'guru.pm.kokurikuler.index', 'icon' => 'lucide-compass', 'label' => 'Kokurikuler'],
                ['route' => 'guru.pm.ttd.index', 'icon' => 'lucide-pen-tool', 'label' => 'Tanda Tangan Digital'],
            ]],
            ['route' => 'guru.tp.index', 'icon' => 'lucide-check-square', 'label' => 'TP e-Rapor'],
            ['route' => 'guru.materi.index', 'icon' => 'lucide-book-open', 'label' => 'Materi Ajar'],
            ['route' => 'guru.dimensi.index', 'icon' => 'lucide-sprout', 'label' => 'Profil Lulusan'],
        ]],
        ['label' => 'ASESMEN', 'items' => [
            ['label' => 'Asesmen & Nilai', 'icon' => 'lucide-clipboard-check', 'open' => true, 'children' => [
                ['route' => 'guru.input-nilai.index', 'icon' => 'lucide-keyboard', 'label' => 'Input Nilai Cepat'],
                ['route' => 'guru.nilai-erapor.index', 'icon' => 'lucide-file-text', 'label' => 'Input Nilai e-Rapor'],
                ['route' => 'guru.p5.index', 'icon' => 'lucide-sparkles', 'label' => 'Input Projek P5'],
                ['route' => 'guru.cek-penilaian.index', 'icon' => 'lucide-check-circle-2', 'label' => 'Cek Penilaian & Deskripsi'],
                ['route' => 'guru.kuis.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Kuis & Soal'],
                ['route' => 'guru.nilai.index', 'icon' => 'lucide-calculator', 'label' => 'Daftar Nilai'],
                ['route' => 'guru.analisis.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Analisis Belajar'],
            ]],
        ]],
        ['label' => 'MENU WALI KELAS', 'walas' => true, 'items' => [
            ['label' => 'Input Kelengkapan Walas', 'icon' => 'lucide-user-check', 'open' => request()->routeIs('guru.walas.ekskul', 'guru.walas.naik-kelas'), 'children' => [
                ['route' => 'guru.walas.ekskul', 'icon' => 'lucide-activity', 'label' => 'Input Nilai Ekskul'],
                ['route' => 'guru.walas.naik-kelas', 'icon' => 'lucide-arrow-up-circle', 'label' => 'Input Kenaikan Kelas'],
            ]],
            ['label' => 'Cek & Cetak Rapor Kelas', 'icon' => 'lucide-printer', 'open' => request()->routeIs('guru.walas.status-penilaian', 'guru.walas.leger', 'guru.walas.transkrip'), 'children' => [
                ['route' => 'guru.walas.status-penilaian', 'icon' => 'lucide-pie-chart', 'label' => 'Status & Statistik Kelas'],
                ['route' => 'guru.walas.leger', 'icon' => 'lucide-table', 'label' => 'Leger & Rapor P5'],
                ['route' => 'guru.walas.transkrip', 'icon' => 'lucide-graduation-cap', 'label' => 'Transkrip Ijazah'],
            ]],
        ]],
        ['label' => 'CBT, TRYOUT & TKA', 'items' => [
            ['label' => 'Ujian CBT', 'asesmen' => true, 'icon' => 'lucide-monitor', 'open' => request()->routeIs('cbt.*'), 'children' => [
                ['route' => 'cbt.sessions.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Sesi Ujian CBT'],
                ['route' => 'cbt.question-banks.index', 'icon' => 'lucide-database', 'label' => 'Bank Soal CBT'],
            ]],
            ['label' => 'Tryout', 'asesmen' => true, 'icon' => 'lucide-package', 'open' => request()->routeIs('tryout.*'), 'children' => [
                ['route' => 'tryout.packages.index', 'icon' => 'lucide-package', 'label' => 'Paket Tryout'],
                ['route' => 'tryout.sessions.index', 'icon' => 'lucide-calendar-clock', 'label' => 'Sesi Tryout'],
            ]],
            ['label' => 'Asesmen & Simulasi TKA', 'icon' => 'lucide-award', 'open' => request()->routeIs('tka.*', 'guru.tka.*', 'guru.tka-kesiapan.*'), 'children' => [
                ['route' => 'guru.tka-kesiapan.index', 'kesiapan_tka' => true, 'icon' => 'lucide-compass', 'label' => 'Pemantauan Kesiapan TKA'],
                ['route' => 'guru.tka.index', 'icon' => 'lucide-gauge', 'label' => 'Monitoring Kesiapan TKA'],
                ['route' => 'guru.tka.kognitif.index', 'icon' => 'lucide-brain', 'label' => 'Rekap Kognitif & Rekomendasi'],
                ['route' => 'guru.tka.integrasi-erapor.index', 'icon' => 'lucide-refresh-cw', 'label' => 'Integrasi TKA ke e-Rapor'],
                ['route' => 'guru.tka.laporan-kelas.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Heatmap Kelas TKA'],
                ['route' => 'guru.tka.analysis', 'icon' => 'lucide-trending-up', 'label' => 'Analisis Kognitif Kelas'],
                ['route' => 'tka.dimensions.index', 'asesmen' => true, 'icon' => 'lucide-layers', 'label' => 'Dimensi & Kisi-kisi TKA'],
                ['route' => 'tka.assessments.index', 'asesmen' => true, 'icon' => 'lucide-clipboard-check', 'label' => 'Penilaian Diagnostik TKA'],
            ]],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['route' => 'presensi.pegawai.index', 'icon' => 'lucide-map-pin-check', 'label' => 'Presensi Saya'],
            ['route' => 'guru.kehadiran.index', 'icon' => 'lucide-calendar-check', 'label' => 'Kehadiran Siswa'],
            ['route' => 'guru.catatan.index', 'icon' => 'lucide-sticky-note', 'label' => 'Catatan Siswa'],
            ['route' => 'guru.kebiasaan.index', 'icon' => 'lucide-home', 'label' => '7 Kebiasaan'],
            ['route' => 'guru.siswa.index', 'icon' => 'lucide-users', 'label' => 'Data Siswa'],
        ]],
        ['label' => 'PELAPORAN', 'items' => [
            ['route' => 'guru.laporan.index', 'icon' => 'lucide-printer', 'label' => 'Laporan & Rapor'],
            ['route' => 'guru.erapor.index', 'icon' => 'lucide-file-text', 'label' => 'Generate e-Rapor'],
        ]],
        ['label' => 'PENGATURAN', 'items' => [
            ['route' => 'guru.integrasi.index', 'icon' => 'lucide-cloud', 'label' => 'Google Sheet'],
            ['route' => 'guru.pengaturan.index', 'icon' => 'lucide-settings', 'label' => 'Pengaturan'],
        ]],
    ];
    $isActive = function ($route) {
        return sidebarRouteActive($route);
    };
@endphp

@foreach($menuGuru as $group)
    @continue(($group['walas'] ?? false) && !$adalahWaliKelas)
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        @continue(($item['asesmen'] ?? false) && !modulAsesmenAktif())
        @continue(($item['kesiapan_tka'] ?? false) && !kesiapanTkaAktif())
        @continue(!isset($item['children']) && !\Illuminate\Support\Facades\Route::has($item['route']))
        @if(isset($item['children']))
            @php $childActive = collect($item['children'])->contains(fn($c) => (!(($c['asesmen'] ?? false)) || modulAsesmenAktif()) && (!(($c['kesiapan_tka'] ?? false)) || kesiapanTkaAktif()) && $isActive($c['route'])); @endphp
            <x-sidebar-submenu :icon="$item['icon']" :label="$item['label']" :open="$childActive || ($item['open'] ?? false)" :active="$childActive">
                @foreach($item['children'] as $child)
                    @continue(($child['asesmen'] ?? false) && !modulAsesmenAktif())
                    @continue(($child['kesiapan_tka'] ?? false) && !kesiapanTkaAktif())
                    @continue(!\Illuminate\Support\Facades\Route::has($child['route']))
                    <x-sidebar-link :href="route($child['route'])" :icon="$child['icon']" :label="$child['label']" :active="$isActive($child['route'])" :sub="true" />
                @endforeach
            </x-sidebar-submenu>
        @else
            <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
        @endif
    @endforeach
@endforeach
