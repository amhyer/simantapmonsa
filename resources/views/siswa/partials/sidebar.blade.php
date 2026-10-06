@php
    $menuSiswa = [
        ['label' => 'MENU SISWA', 'items' => [
            ['route' => 'siswa.dashboard', 'icon' => 'lucide-home', 'label' => 'Beranda'],
            ['route' => 'siswa.materi.index', 'icon' => 'lucide-book-open', 'label' => 'Materi'],
            ['route' => 'siswa.kuis.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Kuis'],
            ['route' => 'siswa.nilai.index', 'icon' => 'lucide-trending-up', 'label' => 'Nilaiku'],
            ['route' => 'siswa.profil.show', 'icon' => 'lucide-user', 'label' => 'Profil Saya'],
        ]],
        ['label' => 'UJIAN & TRYOUT', 'items' => [
            ['route' => 'cbt.student.index', 'icon' => 'lucide-monitor', 'label' => 'Ujian CBT'],
            ['route' => 'tryout.student.index', 'icon' => 'lucide-package', 'label' => 'Tryout'],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuSiswa as $group)
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
    @endforeach
@endforeach