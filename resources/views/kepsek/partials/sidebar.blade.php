@php
    $menuKepsek = [
        ['label' => 'MENU KEPSEK', 'items' => [
            ['route' => 'kepsek.dashboard', 'icon' => 'fa-solid fa-chart-pie', 'label' => 'Dashboard'],
            ['route' => 'kepsek.rekap.index', 'icon' => 'fa-solid fa-school', 'label' => 'Rekap Sekolah'],
        ]],
        ['label' => 'PEMETAAN', 'items' => [
            ['route' => 'kepsek.peta-kelas.index', 'icon' => 'fa-solid fa-map', 'label' => 'Peta Kelas'],
            ['route' => 'kepsek.hasil-belajar.index', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Hasil Belajar'],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['route' => 'kepsek.pantau.index', 'icon' => 'fa-solid fa-satellite-dish', 'label' => 'Pantau Aktivitas'],
            ['route' => 'kepsek.kebiasaan.index', 'icon' => 'fa-solid fa-star', 'label' => 'Rekap Kebiasaan'],
            ['route' => 'kepsek.aktivitas.index', 'icon' => 'fa-solid fa-clipboard-list', 'label' => 'Log Aktivitas'],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuKepsek as $group)
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
