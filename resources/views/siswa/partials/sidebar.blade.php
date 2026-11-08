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
        // CBT dan Tryout bergantung pada modul; simulasi mandiri & adaptif tidak.
        ['label' => 'UJIAN & ASESMEN TKA', 'items' => [
            ['route' => 'cbt.student.index', 'asesmen' => true, 'icon' => 'lucide-monitor', 'label' => 'Ujian CBT'],
            ['route' => 'tryout.student.index', 'asesmen' => true, 'icon' => 'lucide-package', 'label' => 'Tryout Sekolah'],
            ['route' => 'siswa.tka-simulasi.index', 'icon' => 'lucide-brain', 'label' => 'Simulasi TKA & Adaptif'],
            ['route' => 'siswa.tka-simulasi.kognitif', 'icon' => 'lucide-trending-up', 'label' => 'Profil Kognitif & Latihanku'],
        ]],
    ];
    $isActive = function ($route) {
        return sidebarRouteActive($route);
    };
@endphp

@foreach($menuSiswa as $group)
    <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
    @foreach($group['items'] as $item)
        @continue(($item['asesmen'] ?? false) && !modulAsesmenAktif())
        @continue(!\Illuminate\Support\Facades\Route::has($item['route']))
        <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
    @endforeach
@endforeach
