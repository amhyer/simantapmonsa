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
        ['label' => 'TKA (PROFIL PELAJAR)', 'icon' => 'lucide-award', 'children' => [
            ['route' => 'admin.tka.dashboard', 'icon' => 'lucide-gauge', 'label' => 'Dashboard TKA'],
            ['route' => 'admin.tka.dimensions.index', 'icon' => 'lucide-layers', 'label' => 'Dimensi P5'],
            ['route' => 'admin.tka.assessments.index', 'icon' => 'lucide-clipboard-check', 'label' => 'Data Penilaian'],
            ['route' => 'admin.tka.warnings.index', 'icon' => 'lucide-alert-triangle', 'label' => 'Early Warning'],
        ]],
    ];
@endphp

{{-- Seluruh menu di tab ini berada di balik middleware `module.asesmen`.
     Saat modul mati menu tidak ditampilkan sama sekali, karena setiap tautan
     di dalamnya pasti berujung 404. --}}
@if(modulAsesmenAktif())
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
@endif
