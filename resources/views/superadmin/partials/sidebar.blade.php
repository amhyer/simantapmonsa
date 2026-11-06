@php
    $menuSuperAdmin = [
        ['label' => 'PUSAT KONTROL', 'items' => [
            ['route' => 'superadmin.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor Platform'],
        ]],
        ['label' => 'MULTI-TENANT', 'items' => [
            ['route' => 'superadmin.tenants.index', 'icon' => 'lucide-building-2', 'label' => 'Katalog Sekolah'],
            ['route' => 'superadmin.users.index', 'icon' => 'lucide-users', 'label' => 'Akun Seluruh Sekolah'],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuSuperAdmin as $group)
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
    @endforeach
@endforeach
