@php
    $totalSekolah = \App\Modules\Tenant\Models\Tenant::count();

    $menuSuperAdmin = [
        ['label' => 'PUSAT KONTROL', 'items' => [
            ['route' => 'superadmin.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor Platform'],
        ]],
        ['label' => 'MULTI-TENANT & SEKOLAH', 'items' => [
            ['route' => 'superadmin.tenants.index', 'icon' => 'lucide-building-2', 'label' => 'Katalog Sekolah', 'badge' => $totalSekolah > 0 ? (string)$totalSekolah : null, 'badgeType' => 'ok'],
            ['href' => route('superadmin.tenants.index', ['tambah' => 1]) . '#tambah', 'icon' => 'lucide-plus-circle', 'label' => 'Daftarkan Sekolah Baru'],
        ]],
        ['label' => 'PENGGUNA', 'items' => [
            ['route' => 'superadmin.users.index', 'icon' => 'lucide-users', 'label' => 'Semua Akun Pengguna'],
        ]],
    ];

    $isActive = function ($item) {
        if (isset($item['route'])) {
            return sidebarRouteActive($item['route']);
        }
        return false;
    };
@endphp

@foreach($menuSuperAdmin as $group)
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        @php
            $href = isset($item['route']) ? route($item['route']) : ($item['href'] ?? '#');
        @endphp
        <x-sidebar-link 
            :href="$href" 
            :icon="$item['icon']" 
            :label="$item['label']" 
            :active="$isActive($item)" 
            :badge="$item['badge'] ?? null" 
            :badgeType="$item['badgeType'] ?? null" 
        />
    @endforeach
@endforeach
