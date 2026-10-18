@php
    $isActive = function ($route) {
        try {
            return request()->routeIs($route, $route . '.*');
        } catch (\Exception $e) {
            return false;
        }
    };
    $isPlanned = function ($item) {
        return ($item['planned'] ?? false) === true || !isset($item['route']);
    };

    $menuAdmin = [
        ['label' => 'UTAMA', 'items' => [
            ['route' => 'admin.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor Sistem'],
            ['route' => 'admin.profile', 'icon' => 'lucide-id-card', 'label' => 'Profile'],
        ]],
        ['label' => 'STATUS PENILAIAN', 'icon' => 'lucide-graduation-cap', 'children' => [
            ['route' => 'admin.penilaian.status', 'icon' => 'lucide-check-circle', 'label' => 'Status per Guru'],
            ['route' => 'admin.penilaian.statistik', 'icon' => 'lucide-bar-chart-2', 'label' => 'Statistik Nilai Rapor'],
        ]],
        ['label' => 'PERKEMBANGAN NILAI', 'icon' => 'lucide-trending-up', 'children' => [
            ['route' => 'admin.perkembangan.index', 'icon' => 'lucide-trending-up', 'label' => 'Perkembangan Nilai'],
            ['route' => 'admin.perkembangan.grafik', 'icon' => 'lucide-area-chart', 'label' => 'Grafik Nilai Rapor'],
        ]],
        ['label' => 'TRANSKRIP IJAZAH', 'icon' => 'lucide-file-text', 'children' => [
            ['route' => 'admin.transkrip.setting', 'icon' => 'lucide-settings', 'label' => 'Setting Transkrip'],
            ['route' => 'admin.transkrip.nomor', 'icon' => 'lucide-file-input', 'label' => 'Nomor Ijazah'],
            ['route' => 'admin.referensi.mapping-rapor', 'icon' => 'lucide-shuffle', 'label' => 'Mapping Mapel'],
            ['route' => 'admin.transkrip.input', 'icon' => 'lucide-pen-square', 'label' => 'Input Nilai Transkrip'],
            ['route' => 'admin.transkrip.import', 'icon' => 'lucide-upload', 'label' => 'Import Nilai Transkrip'],
            ['route' => 'admin.transkrip.cetak', 'icon' => 'lucide-printer', 'label' => 'Cetak Transkrip Nilai'],
        ]],
        ['label' => 'CETAK NILAI', 'icon' => 'lucide-printer', 'children' => [
            ['route' => 'admin.cetak.leger', 'icon' => 'lucide-table', 'label' => 'Leger Rapor'],
            ['route' => 'admin.cetak.pelengkap', 'icon' => 'lucide-scroll-text', 'label' => 'Pelengkap Rapor'],
            ['route' => 'admin.cetak.nilai', 'icon' => 'lucide-file-signature', 'label' => 'Nilai Rapor'],
        ]],
        ['label' => 'DATA REFERENSI', 'icon' => 'lucide-laptop', 'children' => [
            ['route' => 'admin.sekolah.index', 'icon' => 'lucide-building-2', 'label' => 'Data Sekolah'],
            ['route' => 'admin.referensi.guru', 'icon' => 'lucide-presentation', 'label' => 'Data Guru'],
            ['route' => 'admin.users.siswa', 'icon' => 'lucide-graduation-cap', 'label' => 'Data Siswa'],
            ['route' => 'admin.peta-kelas.daftar', 'icon' => 'lucide-school', 'label' => 'Data Kelas'],
            ['route' => 'admin.mapel.index', 'icon' => 'lucide-book', 'label' => 'Data Mapel'],
            ['route' => 'admin.referensi.pembelajaran', 'icon' => 'lucide-calendar-days', 'label' => 'Data Pembelajaran'],
            ['route' => 'admin.referensi.alignment', 'icon' => 'lucide-link', 'label' => 'Cek Alignment'],
            ['route' => 'admin.semester.index', 'icon' => 'lucide-calendar', 'label' => 'Data Semester'],
            ['route' => 'admin.referensi.ekstrakurikuler', 'icon' => 'lucide-activity', 'label' => 'Data Ekstrakurikuler'],
            ['route' => 'admin.referensi.kelompok-mapel', 'icon' => 'lucide-layers', 'label' => 'Data Kelompok Mapel'],
            ['route' => 'admin.referensi.mapping-rapor', 'icon' => 'lucide-shuffle', 'label' => 'Mapping Rapor'],
            ['route' => 'admin.referensi.logo-ttd', 'icon' => 'lucide-image', 'label' => 'Logo dan TTD'],
            ['route' => 'admin.referensi.tanggal-rapor', 'icon' => 'lucide-calendar', 'label' => 'Tanggal Rapor'],
            ['route' => 'admin.referensi.foto-siswa', 'icon' => 'lucide-camera', 'label' => 'Foto Siswa'],
        ]],
    ];
@endphp

@foreach($menuAdmin as $group)
    @if(isset($group['children']))
        @php $childActive = collect($group['children'])->contains(fn($c) => isset($c['route']) && $isActive($c['route'])); @endphp
        <x-sidebar-submenu :icon="$group['icon']" :label="ucwords(strtolower($group['label']))" :open="$childActive || ($group['open'] ?? false)" :active="$childActive">
            @foreach($group['children'] as $child)
                @if($isPlanned($child))
                    <x-sidebar-planned :icon="$child['icon']" :label="$child['label']" :sub="true" />
                @else
                    <x-sidebar-link :href="route($child['route'])" :icon="$child['icon']" :label="$child['label']" :active="$isActive($child['route'])" :sub="true" :badge="$child['badge'] ?? null" :badge-type="$child['badge_type'] ?? null" />
                @endif
            @endforeach
        </x-sidebar-submenu>
    @else
        @foreach($group['items'] as $item)
            <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" :badge="$item['badge'] ?? null" :badge-type="$item['badge_type'] ?? null" />
        @endforeach
    @endif
@endforeach
