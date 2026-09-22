@php
    $menuGuru = [
        ['label' => 'MENU UTAMA', 'items' => [
            ['route' => 'guru.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor'],
        ]],
        ['label' => 'PEMBELAJARAN', 'items' => [
            ['route' => 'guru.materi.index', 'icon' => 'lucide-book-open', 'label' => 'Materi Ajar'],
            ['route' => 'guru.dimensi.index', 'icon' => 'lucide-sprout', 'label' => 'Profil Lulusan'],
            ['route' => 'guru.tka.index', 'icon' => 'lucide-ruler', 'label' => 'Kesiapan TKA'],
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
        ['label' => 'PEMANTAUAN', 'items' => [
            ['route' => 'guru.kehadiran.index', 'icon' => 'lucide-calendar-check', 'label' => 'Kehadiran'],
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
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuGuru as $group)
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        @if(isset($item['children']))
            @php $childActive = collect($item['children'])->contains(fn($c) => $isActive($c['route'])); @endphp
            <x-sidebar-submenu :icon="$item['icon']" :label="$item['label']" :open="$childActive || ($item['open'] ?? false)" :active="$childActive">
                @foreach($item['children'] as $child)
                    <x-sidebar-link :href="route($child['route'])" :icon="$child['icon']" :label="$child['label']" :active="$isActive($child['route'])" :sub="true" />
                @endforeach
            </x-sidebar-submenu>
        @else
            <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
        @endif
    @endforeach
@endforeach