@php
    $menuKepsek = [
        ['label' => 'MENU KEPSEK', 'items' => [
            ['route' => 'kepsek.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dashboard'],
            ['route' => 'kepsek.rekap.index', 'icon' => 'lucide-school', 'label' => 'Rekap Sekolah'],
            ['route' => 'presensi.pegawai.index', 'icon' => 'lucide-map-pin-check', 'label' => 'Presensi Saya'],
        ]],
        ['label' => 'PEMETAAN', 'items' => [
            ['route' => 'kepsek.peta-kelas.index', 'icon' => 'lucide-map', 'label' => 'Peta Kelas'],
            ['route' => 'kepsek.hasil-belajar.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Hasil Belajar'],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['label' => 'Supervisi', 'icon' => 'lucide-sparkles', 'open' => request()->routeIs('kepsek.supervisi-pm.*', 'kepsek.perangkat.*', 'kepsek.ttd.*'), 'children' => [
                ['route' => 'kepsek.supervisi-pm.index', 'icon' => 'lucide-sparkles', 'label' => 'Supervisi Pembelajaran Mendalam'],
                ['route' => 'kepsek.perangkat.index', 'icon' => 'lucide-folder-open', 'label' => 'Supervisi Perangkat Ajar'],
                ['route' => 'kepsek.ttd.index', 'icon' => 'lucide-pen-tool', 'label' => 'Tanda Tangan Saya'],
            ]],
            ['label' => 'TKA', 'icon' => 'lucide-compass', 'open' => request()->routeIs('kepsek.tka-*'), 'children' => [
                ['route' => 'kepsek.tka-kesiapan.index', 'kesiapan_tka' => true, 'icon' => 'lucide-compass', 'label' => 'Pemantauan Kesiapan TKA'],
                ['route' => 'kepsek.tka-laporan.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Heatmap & Laporan TKA'],
            ]],
            ['label' => 'Aktivitas Sekolah', 'icon' => 'lucide-activity', 'open' => request()->routeIs('kepsek.pantau.*', 'kepsek.kebiasaan.*', 'kepsek.aktivitas.*'), 'children' => [
                ['route' => 'kepsek.pantau.index', 'icon' => 'lucide-activity', 'label' => 'Pantau Aktivitas'],
                ['route' => 'kepsek.kebiasaan.index', 'icon' => 'lucide-star', 'label' => 'Rekap Kebiasaan'],
                ['route' => 'kepsek.aktivitas.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Log Aktivitas'],
            ]],
        ]],
    ];
    if (\App\Modules\Tenant\Support\TenantMode::isMultiSchool()) {
        $menuKepsek = array_values(array_filter($menuKepsek, fn ($group) => $group['label'] !== 'PEMETAAN'));
    }
    $isActive = function ($route) {
        return sidebarRouteActive($route);
    };
@endphp

@foreach($menuKepsek as $group)
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        @continue(($item['kesiapan_tka'] ?? false) && !kesiapanTkaAktif())
        @if(isset($item['children']))
            @php
                $item['children'] = array_values(array_filter($item['children'], fn($c) => !(($c['kesiapan_tka'] ?? false) && !kesiapanTkaAktif())));
                $childActive = collect($item['children'])->contains(fn($c) => $isActive($c['route']));
            @endphp
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