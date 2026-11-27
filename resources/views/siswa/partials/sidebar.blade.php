@php
    $menuSiswa = [
        ['label' => 'MENU SISWA', 'items' => [
            ['route' => 'siswa.dashboard', 'icon' => 'lucide-home', 'label' => 'Beranda'],
            ['label' => 'Belajar', 'icon' => 'lucide-book-open', 'open' => request()->routeIs('siswa.kelas-digital.*', 'siswa.materi.*', 'siswa.kuis.*'), 'children' => [
                ['route' => 'siswa.kelas-digital.index', 'icon' => 'lucide-school', 'label' => 'Kelas Digital'],
                ['route' => 'siswa.materi.index', 'icon' => 'lucide-book-open', 'label' => 'Materi'],
                ['route' => 'siswa.kuis.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Kuis'],
            ]],
            ['label' => 'Hasil Belajar', 'icon' => 'lucide-trending-up', 'open' => request()->routeIs('siswa.nilai.*', 'siswa.rapor.*'), 'children' => [
                ['route' => 'siswa.nilai.index', 'icon' => 'lucide-trending-up', 'label' => 'Nilaiku'],
                ['route' => 'siswa.rapor.capaian', 'icon' => 'lucide-award', 'label' => 'Rekap Capaian Rapor'],
                ['route' => 'siswa.rapor.unduh', 'icon' => 'lucide-file-down', 'label' => 'Download Rapor'],
            ]],
            ['label' => 'Akun Saya', 'icon' => 'lucide-user', 'open' => request()->routeIs('siswa.kartu.*', 'siswa.profil.*'), 'children' => [
                ['route' => 'siswa.profil.show', 'icon' => 'lucide-user', 'label' => 'Profil Saya'],
                ['route' => 'siswa.kartu.cetak', 'icon' => 'lucide-id-card', 'label' => 'Kartu Pelajarku'],
            ]],
        ]],
        // CBT dan Tryout bergantung pada modul; simulasi mandiri & adaptif tidak.
        ['label' => 'UJIAN & ASESMEN TKA', 'items' => [
            ['route' => 'siswa.tka-kesiapan.index', 'kesiapan_tka' => true, 'icon' => 'lucide-compass', 'label' => 'Kesiapan Belajarku (TKA)'],
            ['route' => 'cbt.student.index', 'asesmen' => true, 'icon' => 'lucide-monitor', 'label' => 'Ujian CBT'],
            ['route' => 'tryout.student.index', 'asesmen' => true, 'icon' => 'lucide-package', 'label' => 'Tryout Sekolah'],
            ['label' => 'Simulasi TKA', 'icon' => 'lucide-brain', 'open' => request()->routeIs('siswa.tka-simulasi.*'), 'children' => [
                ['route' => 'siswa.tka-simulasi.index', 'icon' => 'lucide-brain', 'label' => 'Simulasi TKA Pusmendik'],
                ['route' => 'siswa.tka-simulasi.kognitif', 'icon' => 'lucide-trending-up', 'label' => 'Profil Kognitif & Latihanku'],
            ]],
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
        @continue(($item['kesiapan_tka'] ?? false) && !kesiapanTkaAktif())
        @if(isset($item['children']))
            @php
                $children = array_values(array_filter($item['children'], fn($c) => \Illuminate\Support\Facades\Route::has($c['route'])));
                $childActive = collect($children)->contains(fn($c) => $isActive($c['route']));
            @endphp
            @continue($children === [])
            <x-sidebar-submenu :icon="$item['icon']" :label="$item['label']" :open="$childActive || ($item['open'] ?? false)" :active="$childActive">
                @foreach($children as $child)
                    <x-sidebar-link :href="route($child['route'])" :icon="$child['icon']" :label="$child['label']" :active="$isActive($child['route'])" :sub="true" />
                @endforeach
            </x-sidebar-submenu>
        @else
            @continue(!\Illuminate\Support\Facades\Route::has($item['route']))
            <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" />
        @endif
    @endforeach
@endforeach
