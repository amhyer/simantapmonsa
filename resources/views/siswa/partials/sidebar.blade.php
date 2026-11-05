@php
    $menuSiswa = [
        ['label' => 'MENU SISWA', 'items' => [
            ['route' => 'siswa.dashboard', 'icon' => 'lucide-home', 'label' => 'Beranda'],
            ['route' => 'siswa.kartu.cetak', 'icon' => 'lucide-id-card', 'label' => 'Kartu Pelajarku'],
            ['route' => 'siswa.materi.index', 'icon' => 'lucide-book-open', 'label' => 'Materi'],
            ['route' => 'siswa.kuis.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Kuis'],
            ['route' => 'siswa.nilai.index', 'icon' => 'lucide-trending-up', 'label' => 'Nilaiku'],
            ['route' => 'siswa.profil.show', 'icon' => 'lucide-user', 'label' => 'Profil Saya'],
        ]],
        // Menu ini berada di balik middleware `module.asesmen`.
        ['label' => 'UJIAN & ASESMEN TKA', 'asesmen' => true, 'items' => [
            ['route' => 'cbt.student.index', 'icon' => 'lucide-monitor', 'label' => 'Ujian CBT'],
            ['route' => 'tryout.student.index', 'icon' => 'lucide-package', 'label' => 'Tryout Sekolah'],
            ['route' => 'siswa.tka-simulasi.index', 'icon' => 'lucide-brain', 'label' => 'Simulasi TKA Pusmendik'],
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
