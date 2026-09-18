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
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuSiswa as $group)
    <div class="nav-label">{{ $group['label'] }}</div>
    @foreach($group['items'] as $item)
        @if(isset($item['children']))
            @php $childActive = collect($item['children'])->contains(fn($c) => $isActive($c['route'])); @endphp
            <details class="nav-submenu" {{ ($childActive || ($item['open'] ?? false)) ? 'open' : '' }}>
                <summary class="nav-item {{ $childActive ? 'active' : '' }}">
                    <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
                    {{ $item['label'] }}
                </summary>
                <div>
                    @foreach($item['children'] as $child)
                        <a href="{{ route($child['route']) }}"
                           class="nav-item nav-subitem {{ $isActive($child['route']) ? 'active' : '' }}">
                            <span class="icon"><i class="{{ $child['icon'] }}"></i></span>
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            </details>
        @else
            <a href="{{ route($item['route']) }}"
               class="nav-item {{ $isActive($item['route']) ? 'active' : '' }}">
                <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
@endforeach
