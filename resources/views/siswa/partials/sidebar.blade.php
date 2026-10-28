@php
    $menuSiswa = [
        ['label' => 'MENU SISWA', 'items' => [
            ['route' => 'siswa.dashboard', 'icon' => 'lucide-home', 'label' => 'Beranda'],
            ['route' => 'siswa.materi.index', 'icon' => 'lucide-book-open', 'label' => 'Materi'],
            ['route' => 'siswa.kuis.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Kuis'],
            ['route' => 'siswa.nilai.index', 'icon' => 'lucide-trending-up', 'label' => 'Nilaiku'],
            ['route' => 'siswa.profil.show', 'icon' => 'lucide-user', 'label' => 'Profil Saya'],
        ]],
        // Menu ini berada di balik middleware `module.asesmen`.
        ['label' => 'UJIAN & TRYOUT', 'asesmen' => true, 'items' => [
            ['route' => 'tryout.student.index', 'icon' => 'lucide-package', 'label' => 'Tryout'],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuSiswa as $group)
    {{-- Grup yang menaut ke modul asesmen disembunyikan saat modul dimatikan
         (route-nya dijawab 404 oleh middleware module.asesmen). --}}
    @continue(($group['asesmen'] ?? false) && !modulAsesmenAktif())
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
    @endforeach
@endforeach
@if(modulAsesmenAktif() && Route::has('cbt.student.index'))
    <x-sidebar-label>UJIAN CBT</x-sidebar-label>
    <x-sidebar-link :href="route('cbt.student.index')" icon="lucide-clipboard-check" label="Ujian CBT" :active="request()->routeIs('cbt.*')" />
@endif
