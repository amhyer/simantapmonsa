@php
    $menuGuru = [
        ['label' => 'MENU UTAMA', 'items' => [
            ['route' => 'guru.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor'],
        ]],
        ['label' => 'PEMBELAJARAN', 'items' => [
            ['route' => 'guru.materi.index', 'icon' => 'lucide-book-open', 'label' => 'Materi Ajar'],
            ['route' => 'guru.dimensi.index', 'icon' => 'lucide-sprout', 'label' => 'Profil Lulusan'],
        ]],
        ['label' => 'ASESMEN', 'items' => [
            ['label' => 'Asesmen & Nilai', 'icon' => 'lucide-clipboard-check', 'open' => true, 'children' => [
                ['route' => 'guru.input-nilai.index', 'icon' => 'lucide-keyboard', 'label' => 'Input Nilai Cepat'],
                ['route' => 'guru.nilai-erapor.index', 'icon' => 'lucide-file-text', 'label' => 'Input Nilai e-Rapor'],
                ['route' => 'guru.kuis.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Kuis & Soal'],
                ['route' => 'guru.nilai.index', 'icon' => 'lucide-calculator', 'label' => 'Daftar Nilai'],
                ['route' => 'guru.analisis.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Analisis Belajar'],
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
            ['label' => 'Asesmen & Simulasi TKA', 'icon' => 'lucide-award', 'open' => request()->routeIs('tka.*', 'guru.tka.*'), 'children' => [
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
        ]],
        ['label' => 'PELAPORAN', 'items' => [
            ['route' => 'guru.laporan.index', 'icon' => 'lucide-printer', 'label' => 'Laporan & Rapor'],
            ['route' => 'guru.erapor.index', 'icon' => 'lucide-file-text', 'label' => 'Generate e-Rapor'],
            ['route' => 'guru.siswa.index', 'icon' => 'lucide-users', 'label' => 'Data Siswa'],
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
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        @continue(($item['asesmen'] ?? false) && !modulAsesmenAktif())
        @continue(!isset($item['children']) && !\Illuminate\Support\Facades\Route::has($item['route']))
        @if(isset($item['children']))
            @php $childActive = collect($item['children'])->contains(fn($c) => (!(($c['asesmen'] ?? false)) || modulAsesmenAktif()) && $isActive($c['route'])); @endphp
            <x-sidebar-submenu :icon="$item['icon']" :label="$item['label']" :open="$childActive || ($item['open'] ?? false)" :active="$childActive">
                @foreach($item['children'] as $child)
                    @continue(($child['asesmen'] ?? false) && !modulAsesmenAktif())
                    @continue(!\Illuminate\Support\Facades\Route::has($child['route']))
                    <x-sidebar-link :href="route($child['route'])" :icon="$child['icon']" :label="$child['label']" :active="$isActive($child['route'])" :sub="true" />
                @endforeach
            </x-sidebar-submenu>
        @else
            <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
        @endif
    @endforeach
@endforeach
