@php
    $isActive = function ($route) {
        try {
            return request()->routeIs($route, $route . '.*');
        } catch (\Exception $e) {
            return false;
        }
    };
    $isPlanned = function ($item) {
        return ($item['planned'] ?? false) === true || !isset($item['route']);
    };

    $menuAdmin = [
        ['label' => 'CBT & E-LEARNING', 'icon' => 'lucide-monitor', 'children' => [
            ['route' => 'admin.cbt.sessions.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Sesi Ujian CBT'],
            ['route' => 'admin.cbt.banks.index', 'icon' => 'lucide-database', 'label' => 'Bank Soal CBT'],
            ['route' => 'admin.tryout.statistics', 'icon' => 'lucide-bar-chart-3', 'label' => 'Dashboard Tryout'],
            ['route' => 'admin.tryout.packages.index', 'icon' => 'lucide-package', 'label' => 'Paket Tryout'],
            ['route' => 'admin.tryout.sessions.index', 'icon' => 'lucide-calendar-clock', 'label' => 'Sesi Tryout'],
            ['route' => 'admin.tryout.results.index', 'icon' => 'lucide-chart-line', 'label' => 'Hasil Tryout'],
        ]],
    ];
@endphp

@foreach($menuAdmin as $group)
    @if(isset($group['children']))
        @php $childActive = collect($group['children'])->contains(fn($c) => isset($c['route']) && $isActive($c['route'])); @endphp
        <x-sidebar-submenu :icon="$group['icon']" :label="ucwords(strtolower($group['label']))" :open="$childActive || ($group['open'] ?? false)" :active="$childActive">
            @foreach($group['children'] as $child)
                @if($isPlanned($child))
                    <x-sidebar-planned :icon="$child['icon']" :label="$child['label']" :sub="true" />
                @else
                    <x-sidebar-link :href="route($child['route'])" :icon="$child['icon']" :label="$child['label']" :active="$isActive($child['route'])" :sub="true" :badge="$child['badge'] ?? null" :badge-type="$child['badge_type'] ?? null" />
                @endif
            @endforeach
        </x-sidebar-submenu>
    @else
        @foreach($group['items'] as $item)
            <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" :badge="$item['badge'] ?? null" :badge-type="$item['badge_type'] ?? null" />
        @endforeach
    @endif
@endforeach
