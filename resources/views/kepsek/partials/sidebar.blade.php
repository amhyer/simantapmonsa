@php
    $menuKepsek = [
        ['label' => 'MENU KEPSEK', 'items' => [
            ['route' => 'kepsek.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dashboard'],
            ['route' => 'kepsek.rekap.index', 'icon' => 'lucide-school', 'label' => 'Rekap Sekolah'],
        ]],
        ['label' => 'PEMETAAN', 'items' => [
            ['route' => 'kepsek.peta-kelas.index', 'icon' => 'lucide-map', 'label' => 'Peta Kelas'],
            ['route' => 'kepsek.hasil-belajar.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Hasil Belajar'],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['route' => 'kepsek.pantau.index', 'icon' => 'lucide-activity', 'label' => 'Pantau Aktivitas'],
            ['route' => 'kepsek.kebiasaan.index', 'icon' => 'lucide-star', 'label' => 'Rekap Kebiasaan'],
            ['route' => 'kepsek.aktivitas.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Log Aktivitas'],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuKepsek as $group)
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