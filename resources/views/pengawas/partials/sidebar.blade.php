@php
    $menuPengawas = [
        ['label' => 'MENU PENGAWAS', 'items' => [
            ['route' => 'pengawas.dashboard', 'icon' => 'lucide-radar', 'label' => 'Radar Mutu Sekolah'],
            ['route' => 'pengawas.supervisi.index', 'icon' => 'lucide-shield-check', 'label' => 'Verifikasi Perangkat PM'],
            ['route' => 'pengawas.visitasi.index', 'icon' => 'lucide-clipboard-check', 'label' => 'E-Visitasi & Observasi Kelas'],
            ['route' => 'pengawas.audit.index', 'icon' => 'lucide-git-compare', 'label' => 'Audit Kurikulum & e-Rapor'],
        ]],
        ['label' => 'PELAPORAN & IDENTITAS', 'items' => [
            ['route' => 'pengawas.ttd.index', 'icon' => 'lucide-pen-tool', 'label' => 'Tanda Tangan Pengawas'],
            ['route' => 'pengawas.laporan.pdf', 'icon' => 'lucide-file-text', 'label' => 'Cetak Laporan Semester PDF'],
        ]],
    ];
    $isActive = function ($route) {
        return sidebarRouteActive($route);
    };
@endphp

@foreach($menuPengawas as $group)
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
    @endforeach
@endforeach
