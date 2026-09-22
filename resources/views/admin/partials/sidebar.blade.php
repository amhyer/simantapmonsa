@php
    use App\Models\DapodikSyncLog;
    use App\Models\Siswa;
    $lastSync = DapodikSyncLog::orderByDesc('created_at')->first();
    $syncedCount = Siswa::whereNotNull('dapodik_id')->count();
    // Aturan: grup yang punya 'children' dirender sebagai header lipat
    // (<details>), TANPA label grup terpisah — persis pola e-Rapor.
    // Item 'planned' => true berarti backend belum ada (Fase 2): tampil
    // nonaktif, BUKAN link mati. Jangan beri 'route' pada item planned.
    $menuAdmin = [
        // Urutan grup mengikuti Aplikasi e-Rapor SD (lihat docs/gambar):
        // Dashboard, Profile, Web Service, Ambil Data, Pengguna,
        // Referensi, Kokurikuler, Status, Perkembangan, Transkrip,
        // Cetak, Kirim, Backup. Grup PENGATURAN & SISTEM tambahan
        // khas SIMANTAP dipertahankan (tidak ada menu yang dihapus).
        ['label' => 'UTAMA', 'items' => [
            ['route' => 'admin.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor Sistem'],
            ['route' => 'admin.profile', 'icon' => 'lucide-id-card', 'label' => 'Profile'],
        ]],
        ['label' => 'INTEGRASI DAPODIK', 'items' => [
            ['route' => 'admin.dapodik.index', 'icon' => 'lucide-plug-zap', 'label' => 'Web Service Dapodik'],
            ['route' => 'admin.dapodik.index', 'icon' => 'lucide-refresh-cw', 'label' => 'Ambil Data Dapodik', 'badge' => $lastSync ? $lastSync->created_at->diffForHumans() : 'Belum sync', 'badge_type' => $lastSync ? 'ok' : 'warn'],
        ]],
        ['label' => 'PENGGUNA', 'items' => [
            ['route' => 'admin.users.index', 'icon' => 'lucide-users', 'label' => 'Data Pengguna'],
        ]],
        ['label' => 'DATA REFERENSI', 'icon' => 'lucide-laptop', 'children' => [
            ['route' => 'admin.sekolah.index', 'icon' => 'lucide-building-2', 'label' => 'Data Sekolah'],
            ['route' => 'admin.referensi.guru', 'icon' => 'lucide-presentation', 'label' => 'Data Guru'],
            ['route' => 'admin.users.siswa', 'icon' => 'lucide-graduation-cap', 'label' => 'Data Siswa'],
            ['route' => 'admin.peta-kelas.index', 'icon' => 'lucide-school', 'label' => 'Data Kelas'],
            ['route' => 'admin.mapel.index', 'icon' => 'lucide-book', 'label' => 'Data Mapel'],
            ['route' => 'admin.referensi.pembelajaran', 'icon' => 'lucide-calendar-days', 'label' => 'Data Pembelajaran'],
            ['route' => 'admin.referensi.ekstrakurikuler', 'icon' => 'lucide-activity', 'label' => 'Data Ekstrakurikuler'],
            ['route' => 'admin.referensi.kelompok-mapel', 'icon' => 'lucide-layers', 'label' => 'Data Kelompok Mapel'],
            ['route' => 'admin.referensi.mapping-rapor', 'icon' => 'lucide-shuffle', 'label' => 'Mapping Rapor'],
            ['route' => 'admin.referensi.logo-ttd', 'icon' => 'lucide-image', 'label' => 'Logo dan TTD'],
            ['route' => 'admin.referensi.tanggal-rapor', 'icon' => 'lucide-calendar', 'label' => 'Tanggal Rapor'],
            ['route' => 'admin.referensi.foto-siswa', 'icon' => 'lucide-camera', 'label' => 'Foto Siswa'],
        ]],
        ['label' => 'DATA KOKURIKULER', 'items' => [
            ['route' => 'admin.kokurikuler.tema', 'icon' => 'lucide-target', 'label' => 'Daftar Tema'],
            ['route' => 'admin.kokurikuler.kegiatan', 'icon' => 'lucide-list-checks', 'label' => 'Kegiatan Kokurikuler'],
            ['route' => 'admin.kokurikuler.kelompok', 'icon' => 'lucide-users', 'label' => 'Kelompok Kokurikuler'],
        ]],
        ['label' => 'STATUS PENILAIAN', 'icon' => 'lucide-graduation-cap', 'children' => [
            ['route' => 'admin.penilaian.status', 'icon' => 'lucide-check-circle', 'label' => 'Status per Guru'],
            ['route' => 'admin.penilaian.statistik', 'icon' => 'lucide-bar-chart-2', 'label' => 'Statistik Nilai Rapor'],
        ]],
        ['label' => 'PERKEMBANGAN NILAI', 'icon' => 'lucide-trending-up', 'children' => [
            ['icon' => 'lucide-area-chart', 'label' => 'Grafik Nilai Rapor', 'planned' => true],
        ]],
        ['label' => 'TRANSKRIP IJAZAH', 'icon' => 'lucide-file-text', 'children' => [
            ['icon' => 'lucide-file-input', 'label' => 'Import Nomor Ijazah', 'planned' => true],
            ['icon' => 'lucide-settings', 'label' => 'Setting Transkrip', 'planned' => true],
            ['icon' => 'lucide-shuffle', 'label' => 'Mapping Mapel', 'planned' => true],
            ['icon' => 'lucide-pen-square', 'label' => 'Input Nilai Transkrip', 'planned' => true],
            ['icon' => 'lucide-upload', 'label' => 'Import Nilai Transkrip', 'planned' => true],
            ['icon' => 'lucide-printer', 'label' => 'Cetak Transkrip Nilai', 'planned' => true],
        ]],
        ['label' => 'CETAK NILAI', 'icon' => 'lucide-printer', 'children' => [
            ['icon' => 'lucide-table', 'label' => 'Leger Rapor', 'planned' => true],
            ['icon' => 'lucide-scroll-text', 'label' => 'Pelengkap Rapor', 'planned' => true],
            ['icon' => 'lucide-file-signature', 'label' => 'Nilai Rapor', 'planned' => true],
        ]],
        ['label' => 'KIRIM DAPODIK', 'items' => [
            ['route' => 'admin.dapodik.push.index', 'icon' => 'lucide-upload', 'label' => 'Kirim Nilai Ke Dapodik'],
        ]],
        ['label' => 'PENGATURAN', 'items' => [
            ['route' => 'admin.semester.index', 'icon' => 'lucide-calendar', 'label' => 'Semester'],
            ['route' => 'admin.kode-akses.index', 'icon' => 'lucide-key', 'label' => 'Kode & Akses'],
            ['route' => 'admin.modul.index', 'icon' => 'lucide-box', 'label' => 'Modul & Tampilan'],
            ['route' => 'admin.bobot.index', 'icon' => 'lucide-scale', 'label' => 'Bobot & Ketuntasan'],
        ]],
        ['label' => 'SISTEM & LOG', 'items' => [
            ['route' => 'admin.sheet.index', 'icon' => 'lucide-table', 'label' => 'Penyimpanan Data'],
            ['route' => 'admin.backup.index', 'icon' => 'lucide-database', 'label' => 'Backup & Restore'],
            ['route' => 'admin.api-keys.index', 'icon' => 'lucide-link', 'label' => 'API Keys'],
            ['route' => 'admin.log.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Log Aktivitas'],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
    $isPlanned = function ($item) {
        return ($item['planned'] ?? false) === true || !isset($item['route']);
    };
@endphp

@foreach($menuAdmin as $group)
    @if(isset($group['children']))
        @php $childActive = collect($group['children'])->contains(fn($c) => isset($c['route']) && $isActive($c['route'])); @endphp
        <x-sidebar-submenu :icon="$group['icon']" :label="ucwords(strtolower($group['label']))" :open="$childActive || ($group['open'] ?? false)" :active="$childActive">
            @foreach($group['children'] as $child)
                @if($isPlanned($child))
                    <x-sidebar-planned :icon="$child['icon']" :label="$child['label']" :sub="true" />
                @else
                    <x-sidebar-link :href="route($child['route'])" :icon="$child['icon']" :label="$child['label']" :active="$isActive($child['route'])" :sub="true" />
                @endif
            @endforeach
        </x-sidebar-submenu>
    @else
        <x-sidebar-label>{{ $group['label'] }}</x-sidebar-label>
        @foreach($group['items'] as $item)
            <x-sidebar-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="$isActive($item['route'])" :badge="$item['badge'] ?? null" :badge-type="$item['badge_type'] ?? null" />
        @endforeach
    @endif
@endforeach
