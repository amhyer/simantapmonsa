@php
    $menuKepsek = [
        ['label' => 'MENU KEPSEK', 'items' => [
            ['route' => 'kepsek.dashboard', 'icon' => 'fa-solid fa-chart-pie', 'label' => 'Dashboard'],
            ['route' => 'kepsek.rekap.index', 'icon' => 'fa-solid fa-school', 'label' => 'Rekap Sekolah'],
        ]],
        ['label' => 'PEMETAAN', 'items' => [
            ['route' => 'kepsek.peta-kelas.index', 'icon' => 'fa-solid fa-map', 'label' => 'Peta Kelas'],
            ['route' => 'kepsek.belajar.index', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Hasil Belajar'],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['route' => 'kepsek.pantau.index', 'icon' => 'fa-solid fa-satellite-dish', 'label' => 'Pantau Aktivitas'],
            ['route' => 'kepsek.kebiasaan.index', 'icon' => 'fa-solid fa-star', 'label' => 'Rekap Kebiasaan'],
            ['route' => 'kepsek.aktivitas.index', 'icon' => 'fa-solid fa-clipboard-list', 'label' => 'Log Aktivitas'],
        ]],
    ];
@endphp

@foreach($menuKepsek as $group)
    <div class="nav-label">{{ $group['label'] }}</div>
    @foreach($group['items'] as $item)
        <a href="{{ route($item['route']) }}"
           class="nav-item {{ request()->routeIs($item['route'].'*') ? 'active' : '' }}">
            <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
            {{ $item['label'] }}
        </a>
    @endforeach
@endforeach
