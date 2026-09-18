@php
    $menuGuru = [
        ['label' => 'MENU UTAMA', 'items' => [
            ['route' => 'guru.dashboard', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Dasbor'],
        ]],
        ['label' => 'PEMBELAJARAN', 'items' => [
            ['route' => 'guru.materi.index', 'icon' => 'fa-solid fa-book', 'label' => 'Materi Ajar'],
            ['route' => 'guru.dimensi.index', 'icon' => 'fa-solid fa-seedling', 'label' => 'Profil Lulusan'],
            ['route' => 'guru.tka.index', 'icon' => 'fa-solid fa-ruler-combined', 'label' => 'Kesiapan TKA'],
        ]],
        ['label' => 'ASESMEN', 'items' => [
            ['label' => 'Asesmen & Nilai', 'icon' => 'fa-solid fa-clipboard-check', 'open' => true, 'children' => [
                ['route' => 'guru.input-nilai.index', 'icon' => 'fa-solid fa-keyboard', 'label' => 'Input Nilai Cepat'],
                ['route' => 'guru.nilai-erapor.index', 'icon' => 'fa-solid fa-file-alt', 'label' => 'Input Nilai e-Rapor'],
                ['route' => 'guru.kuis.index', 'icon' => 'fa-solid fa-clipboard-list', 'label' => 'Kuis & Soal'],
                ['route' => 'guru.nilai.index', 'icon' => 'fa-solid fa-calculator', 'label' => 'Daftar Nilai'],
                ['route' => 'guru.analisis.index', 'icon' => 'fa-solid fa-chart-bar', 'label' => 'Analisis Belajar'],
            ]],
        ]],
        ['label' => 'PEMANTAUAN', 'items' => [
            ['route' => 'guru.kehadiran.index', 'icon' => 'fa-solid fa-calendar-check', 'label' => 'Kehadiran'],
            ['route' => 'guru.catatan.index', 'icon' => 'fa-solid fa-sticky-note', 'label' => 'Catatan Siswa'],
            ['route' => 'guru.kebiasaan.index', 'icon' => 'fa-solid fa-home', 'label' => '7 Kebiasaan'],
        ]],
        ['label' => 'PELAPORAN', 'items' => [
            ['route' => 'guru.laporan.index', 'icon' => 'fa-solid fa-print', 'label' => 'Laporan & Rapor'],
            ['route' => 'guru.erapor.index', 'icon' => 'fa-solid fa-file-pdf', 'label' => 'Generate e-Rapor'],
            ['route' => 'guru.siswa.index', 'icon' => 'fa-solid fa-users', 'label' => 'Data Siswa'],
        ]],
        ['label' => 'PENGATURAN', 'items' => [
            ['route' => 'guru.integrasi.index', 'icon' => 'fa-solid fa-cloud', 'label' => 'Google Sheet'],
            ['route' => 'guru.pengaturan.index', 'icon' => 'fa-solid fa-gear', 'label' => 'Pengaturan'],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

@foreach($menuGuru as $group)
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
