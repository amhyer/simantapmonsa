@php
    $menuOrtu = [
        ['label' => 'MENU ORANG TUA', 'items' => [
            ['route' => 'ortu.dashboard', 'icon' => 'lucide-home', 'label' => 'Ringkasan'],
        ]],
        ['label' => 'KEMITRAAN', 'items' => [
            ['route' => 'ortu.kebiasaan.index', 'icon' => 'lucide-home', 'label' => 'Isi 7 Kebiasaan'],
            ['route' => 'ortu.rekap.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Rekap Kebiasaan'],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['label' => 'Pemantauan Anak', 'icon' => 'lucide-eye', 'open' => true, 'children' => [
                ['route' => 'ortu.nilai.index', 'icon' => 'lucide-trending-up', 'label' => 'Perkembangan Nilai'],
                ['route' => 'ortu.kehadiran.index', 'icon' => 'lucide-calendar-check', 'label' => 'Kehadiran Anak'],
                ['route' => 'ortu.catatan.index', 'icon' => 'lucide-sticky-note', 'label' => 'Catatan Guru'],
                ['route' => 'ortu.laporan.index', 'icon' => 'lucide-file-text', 'label' => 'Laporan / Rapor'],
            ]],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuOrtu as $group)
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