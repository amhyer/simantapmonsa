@props(['groups' => []])

{{-- Renderer sidebar admin yang SATU: seluruh grup menu dirender lewat
     komponen ini. Data menu (berlabel, icon, route, badge, planned) tinggal
     didefinisikan di partial sidebar; markup/chrome tidak boleh diduplikasi. --}}
@php
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
@endphp

@foreach(($groups ?? []) as $group)
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
        <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
        @foreach($group['items'] as $item)
            <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" :badge="$item['badge'] ?? null" :badge-type="$item['badge_type'] ?? null" />
        @endforeach
    @endif
@endforeach
