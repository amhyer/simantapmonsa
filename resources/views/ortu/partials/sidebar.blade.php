@php
    $menuOrtu = [
        ['label' => 'MENU ORANG TUA', 'items' => [
            ['route' => 'ortu.dashboard', 'icon' => 'lucide-home', 'label' => 'Ringkasan'],
        ]],
        ['label' => 'KEMITRAAN', 'items' => [
            ['route' => 'ortu.kebiasaan.index', 'icon' => 'lucide-home', 'label' => 'Isi 7 Kebiasaan'],
            ['route' => 'ortu.rekap.index', 'icon' => 'lucide-bar-chart-2', 'label' => 'Rekap Kebiasaan'],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['label' => 'Pemantauan Anak', 'icon' => 'lucide-eye', 'open' => true, 'children' => [
                ['route' => 'ortu.nilai.index', 'icon' => 'lucide-trending-up', 'label' => 'Perkembangan Nilai'],
                ['route' => 'ortu.kehadiran.index', 'icon' => 'lucide-calendar-check', 'label' => 'Kehadiran Anak'],
                ['route' => 'ortu.catatan.index', 'icon' => 'lucide-sticky-note', 'label' => 'Catatan Guru'],
                ['route' => 'ortu.laporan.index', 'icon' => 'lucide-file-text', 'label' => 'Laporan / Rapor'],
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
                    <span class="icon"><x-dynamic-component :component="$item['icon']" class="w-5 h-5" /></span>
                    {{ $item['label'] }}
                </summary>
                <div>
                    @foreach($item['children'] as $child)
                        <a href="{{ route($child['route']) }}"
                           class="nav-item nav-subitem {{ $isActive($child['route']) ? 'active' : '' }}">
                            <span class="icon"><x-dynamic-component :component="$child['icon']" class="w-5 h-5" /></span>
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            </details>
        @else
            <a href="{{ route($item['route']) }}"
               class="nav-item {{ $isActive($item['route']) ? 'active' : '' }}">
                <span class="icon"><x-dynamic-component :component="$item['icon']" class="w-5 h-5" /></span>
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
@endforeach