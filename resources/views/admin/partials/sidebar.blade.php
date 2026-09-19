@php
    use App\Models\DapodikSyncLog;
    use App\Models\Siswa;
    $lastSync = DapodikSyncLog::orderByDesc('created_at')->first();
    $syncedCount = Siswa::whereNotNull('dapodik_id')->count();
    // 'planned' => true berarti backend belum ada (Fase 2): tampil nonaktif,
    // BUKAN link mati. Jangan beri 'route' pada item planned.
    $menuAdmin = [
        ['label' => 'UTAMA', 'items' => [
            ['route' => 'admin.dashboard', 'icon' => 'lucide-layout-dashboard', 'label' => 'Dasbor Sistem'],
        ]],
['label' => 'INTEGRASI DAPODIK', 'items' => [
            ['route' => 'admin.dapodik.index', 'icon' => 'lucide-plug-zap', 'label' => 'Web Service Dapodik'],
            ['route' => 'admin.dapodik.index', 'icon' => 'lucide-refresh-cw', 'label' => 'Ambil Data Dapodik', 'badge' => $lastSync ? $lastSync->created_at->diffForHumans() : 'Belum sync', 'badge_type' => $lastSync ? 'ok' : 'warn'],
        ]],
        ['label' => 'PUSH KE DAPODIK', 'items' => [
            ['route' => 'admin.dapodik.push.sekolah', 'icon' => 'lucide-upload-cloud', 'label' => 'Push Sekolah'],
            ['route' => 'admin.dapodik.push.peserta-didik', 'icon' => 'lucide-upload', 'label' => 'Push Peserta Didik'],
            ['route' => 'admin.dapodik.push.gtk', 'icon' => 'lucide-users', 'label' => 'Push GTK (Guru/Tendik)'],
            ['route' => 'admin.dapodik.push.rombel', 'icon' => 'lucide-users', 'label' => 'Push Rombel'],
            ['route' => 'admin.dapodik.push.jadwal', 'icon' => 'lucide-calendar-clock', 'label' => 'Push Jadwal'],
            ['route' => 'admin.dapodik.push.nilai-rapor', 'icon' => 'lucide-file-text', 'label' => 'Push Nilai Rapor'],
            ['route' => 'admin.dapodik.push.kehadiran', 'icon' => 'lucide-calendar-check', 'label' => 'Push Kehadiran'],
            ['route' => 'admin.dapodik.push.status', 'icon' => 'lucide-activity', 'label' => 'Status Push'],
        ]],
        ['label' => 'PENGGUNA', 'items' => [
            ['route' => 'admin.users.index', 'icon' => 'lucide-users', 'label' => 'Data Pengguna'],
            ['route' => 'admin.users.siswa', 'icon' => 'lucide-graduation-cap', 'label' => 'Data Siswa'],
        ]],
        ['label' => 'DATA REFERENSI', 'items' => [
            ['label' => 'Data Referensi', 'icon' => 'lucide-laptop', 'open' => true, 'children' => [
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
        ]],
        ['label' => 'DATA KOKURIKULER', 'items' => [
            ['route' => 'admin.kokurikuler.tema', 'icon' => 'lucide-target', 'label' => 'Daftar Tema'],
            ['route' => 'admin.kokurikuler.kegiatan', 'icon' => 'lucide-list-check', 'label' => 'Kegiatan Kokurikuler'],
            ['route' => 'admin.kokurikuler.kelompok', 'icon' => 'lucide-users', 'label' => 'Kelompok Kokurikuler'],
        ]],
        ['label' => 'STATUS PENILAIAN', 'items' => [
            ['label' => 'Status Penilaian', 'icon' => 'lucide-graduation-cap', 'children' => [
                ['route' => 'admin.penilaian.status', 'icon' => 'lucide-check-circle', 'label' => 'Status Penilaian'],
                ['route' => 'admin.penilaian.statistik', 'icon' => 'lucide-bar-chart-2', 'label' => 'Statistik Nilai Rapor'],
            ]],
        ]],
        ['label' => 'PERKEMBANGAN NILAI', 'items' => [
            ['label' => 'Perkembangan Nilai', 'icon' => 'lucide-trending-up', 'children' => [
                ['icon' => 'lucide-trending-up', 'label' => 'Perkembangan Nilai', 'planned' => true],
                ['icon' => 'lucide-area-chart', 'label' => 'Grafik Nilai Rapor', 'planned' => true],
            ]],
        ]],
        ['label' => 'TRANSKRIP IJAZAH', 'items' => [
            ['label' => 'Transkrip Ijazah', 'icon' => 'lucide-file-text', 'children' => [
                ['icon' => 'lucide-file-input', 'label' => 'Import Nomor Ijazah', 'planned' => true],
                ['icon' => 'lucide-settings', 'label' => 'Setting Transkrip', 'planned' => true],
                ['icon' => 'lucide-shuffle', 'label' => 'Mapping Mapel', 'planned' => true],
                ['icon' => 'lucide-pen-square', 'label' => 'Input Nilai Transkrip', 'planned' => true],
                ['icon' => 'lucide-upload', 'label' => 'Import Nilai Transkrip', 'planned' => true],
                ['icon' => 'lucide-printer', 'label' => 'Cetak Transkrip Nilai', 'planned' => true],
            ]],
        ]],
        ['label' => 'CETAK NILAI', 'items' => [
            ['label' => 'Cetak Nilai', 'icon' => 'lucide-printer', 'children' => [
                ['icon' => 'lucide-table', 'label' => 'Leger Rapor', 'planned' => true],
                ['icon' => 'lucide-scroll-text', 'label' => 'Pelengkap Rapor', 'planned' => true],
                ['icon' => 'lucide-file-signature', 'label' => 'Nilai Rapor', 'planned' => true],
            ]],
        ]],
        ['label' => 'LAINNYA', 'items' => [
            ['icon' => 'lucide-upload', 'label' => 'Kirim Nilai Ke Dapodik', 'planned' => true],
        ]],
        ['label' => 'PENGATURAN', 'items' => [
            ['route' => 'admin.semester.index', 'icon' => 'lucide-calendar', 'label' => 'Semester'],
            ['route' => 'admin.kode-akses.index', 'icon' => 'lucide-key', 'label' => 'Kode & Akses'],
            ['route' => 'admin.modul.index', 'icon' => 'lucide-box', 'label' => 'Modul & Tampilan'],
            ['route' => 'admin.bobot.index', 'icon' => 'lucide-scale', 'label' => 'Bobot & Ketuntasan'],
        ]],
        ['label' => 'SISTEM & LOG', 'items' => [
            ['route' => 'admin.sheet.index', 'icon' => 'lucide-table', 'label' => 'Penyimpanan Data'],
            ['route' => 'admin.backup.index', 'icon' => 'lucide-database', 'label' => 'Data & Pemulihan'],
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
    <div class="nav-label">{{ $group['label'] }}</div>
    @foreach($group['items'] as $item)
        @if(isset($item['children']))
            @php $childActive = collect($item['children'])->contains(fn($c) => isset($c['route']) && $isActive($c['route'])); @endphp
            <details class="nav-submenu" {{ ($childActive || ($item['open'] ?? false)) ? 'open' : '' }}>
                <summary class="nav-item {{ $childActive ? 'active' : '' }}">
                    <span class="icon"><x-dynamic-component :component="$item['icon']" class="w-5 h-5" /></span>
                    {{ $item['label'] }}
                </summary>
                <div>
                    @foreach($item['children'] as $child)
                        @if($isPlanned($child))
                            <span class="nav-item nav-subitem nav-planned" title="Segera hadir — backend Fase 2">
                                <span class="icon"><x-dynamic-component :component="$child['icon']" class="w-5 h-5" /></span>
                                {{ $child['label'] }}
                                <span class="badge badge-segera">Segera</span>
                            </span>
                        @else
                            <a href="{{ route($child['route']) }}"
                               class="nav-item nav-subitem {{ $isActive($child['route']) ? 'active' : '' }}">
                                <span class="icon"><x-dynamic-component :component="$child['icon']" class="w-5 h-5" /></span>
                                {{ $child['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </details>
        @elseif($isPlanned($item))
            <span class="nav-item nav-planned" title="Segera hadir — backend Fase 2">
                <span class="icon"><x-dynamic-component :component="$item['icon']" class="w-5 h-5" /></span>
                {{ $item['label'] }}
                <span class="badge badge-segera">Segera</span>
            </span>
        @else
            <a href="{{ route($item['route']) }}"
               class="nav-item {{ $isActive($item['route']) ? 'active' : '' }}">
                <span class="icon"><x-dynamic-component :component="$item['icon']" class="w-5 h-5" /></span>
                {{ $item['label'] }}
                @if(isset($item['badge']))
                    <span class="badge" style="background:{{ ($item['badge_type'] ?? '') === 'ok' ? 'var(--ok)' : '#F59E0B' }};color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;margin-left:auto;white-space:nowrap">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endif
    @endforeach
@endforeach