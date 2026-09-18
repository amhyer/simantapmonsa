@php
    $menuSiswa = [
        ['label' => 'MENU SISWA', 'items' => [
            ['route' => 'siswa.dashboard', 'icon' => 'fa-solid fa-home', 'label' => 'Beranda'],
            ['route' => 'siswa.materi.index', 'icon' => 'fa-solid fa-book', 'label' => 'Materi'],
            ['route' => 'siswa.kuis.index', 'icon' => 'fa-solid fa-clipboard-list', 'label' => 'Kuis'],
            ['route' => 'siswa.nilai.index', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Nilaiku'],
            ['route' => 'siswa.profil.show', 'icon' => 'fa-solid fa-user-circle', 'label' => 'Profil Saya'],
        ]],
    ];
@endphp

@foreach($menuSiswa as $group)
    <div class="nav-label">{{ $group['label'] }}</div>
    @foreach($group['items'] as $item)
        <a href="{{ route($item['route']) }}" 
           class="nav-item {{ request()->routeIs($item['route'] . '*') ? 'active' : '' }}">
            <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
            {{ $item['label'] }}
        </a>
    @endforeach
@endforeach
