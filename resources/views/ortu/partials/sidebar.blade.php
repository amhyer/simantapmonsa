@php
    $menuOrtu = [
        ['label' => 'MENU ORANG TUA', 'items' => [
            ['route' => 'ortu.dashboard', 'icon' => 'fa-solid fa-house', 'label' => 'Ringkasan'],
        ]],
        ['label' => 'KEMITRAAN', 'items' => [
            ['route' => 'ortu.kebiasaan.index', 'icon' => 'fa-solid fa-house-chimney', 'label' => 'Isi 7 Kebiasaan'],
            ['route' => 'ortu.rekap.index', 'icon' => 'fa-solid fa-chart-bar', 'label' => 'Rekap Kebiasaan'],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['label' => 'Pemantauan Anak', 'icon' => 'fa-solid fa-eye', 'open' => true, 'children' => [
                ['route' => 'ortu.nilai.index', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Perkembangan Nilai'],
                ['route' => 'ortu.kehadiran.index', 'icon' => 'fa-solid fa-calendar-check', 'label' => 'Kehadiran Anak'],
                ['route' => 'ortu.catatan.index', 'icon' => 'fa-solid fa-sticky-note', 'label' => 'Catatan Guru'],
                ['route' => 'ortu.laporan.index', 'icon' => 'fa-solid fa-file-lines', 'label' => 'Laporan / Rapor'],
            ]],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuOrtu as $group)
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
