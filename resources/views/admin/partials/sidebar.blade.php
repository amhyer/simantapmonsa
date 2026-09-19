@php
    use App\Models\DapodikSyncLog;
    use App\Models\Siswa;
    $lastSync = DapodikSyncLog::orderByDesc('created_at')->first();
    $syncedCount = Siswa::whereNotNull('dapodik_id')->count();
    // 'planned' => true berarti backend belum ada (Fase 2): tampil nonaktif,
    // BUKAN link mati. Jangan beri 'route' pada item planned.
    $menuAdmin = [
        ['label' => 'UTAMA', 'items' => [
            ['route' => 'admin.dashboard', 'icon' => 'fa-solid fa-gauge-high', 'label' => 'Dasbor Sistem'],
        ]],
        ['label' => 'INTEGRASI DAPODIK', 'items' => [
            ['route' => 'admin.dapodik.index', 'icon' => 'fa-solid fa-plug-circle-check', 'label' => 'Web Service Dapodik'],
            ['route' => 'admin.dapodik.index', 'icon' => 'fa-solid fa-arrows-rotate', 'label' => 'Ambil Data Dapodik', 'badge' => $lastSync ? $lastSync->created_at->diffForHumans() : 'Belum sync', 'badge_type' => $lastSync ? 'ok' : 'warn'],
        ]],
        ['label' => 'PENGGUNA', 'items' => [
            ['route' => 'admin.users.index', 'icon' => 'fa-solid fa-users', 'label' => 'Data Pengguna'],
            ['route' => 'admin.users.siswa', 'icon' => 'fa-solid fa-graduation-cap', 'label' => 'Data Siswa'],
        ]],
        ['label' => 'DATA REFERENSI', 'items' => [
            ['label' => 'Data Referensi', 'icon' => 'fa-solid fa-laptop', 'open' => true, 'children' => [
                ['route' => 'admin.sekolah.index', 'icon' => 'fa-solid fa-building-columns', 'label' => 'Data Sekolah'],
                ['route' => 'admin.referensi.guru', 'icon' => 'fa-solid fa-chalkboard-user', 'label' => 'Data Guru'],
                ['route' => 'admin.users.siswa', 'icon' => 'fa-solid fa-graduation-cap', 'label' => 'Data Siswa'],
                ['route' => 'admin.peta-kelas.index', 'icon' => 'fa-solid fa-school', 'label' => 'Data Kelas'],
                ['route' => 'admin.mapel.index', 'icon' => 'fa-solid fa-book', 'label' => 'Data Mapel'],
                ['route' => 'admin.referensi.pembelajaran', 'icon' => 'fa-solid fa-calendar-days', 'label' => 'Data Pembelajaran'],
                ['route' => 'admin.referensi.ekstrakurikuler', 'icon' => 'fa-solid fa-futbol', 'label' => 'Data Ekstrakurikuler'],
                ['route' => 'admin.referensi.kelompok-mapel', 'icon' => 'fa-solid fa-layer-group', 'label' => 'Data Kelompok Mapel'],
                ['route' => 'admin.referensi.mapping-rapor', 'icon' => 'fa-solid fa-shuffle', 'label' => 'Mapping Rapor'],
                ['route' => 'admin.referensi.logo-ttd', 'icon' => 'fa-solid fa-image', 'label' => 'Logo dan TTD'],
                ['route' => 'admin.referensi.tanggal-rapor', 'icon' => 'fa-solid fa-calendar-day', 'label' => 'Tanggal Rapor'],
                ['route' => 'admin.referensi.foto-siswa', 'icon' => 'fa-solid fa-camera', 'label' => 'Foto Siswa'],
            ]],
        ]],
        ['label' => 'DATA KOKURIKULER', 'items' => [
            ['label' => 'Data Kokurikuler', 'icon' => 'fa-solid fa-book-open', 'children' => [
                ['route' => 'admin.kokurikuler.tema', 'icon' => 'fa-solid fa-bullseye', 'label' => 'Daftar Tema'],
                ['route' => 'admin.kokurikuler.kegiatan', 'icon' => 'fa-solid fa-list-check', 'label' => 'Kegiatan Kokurikuler'],
                ['route' => 'admin.kokurikuler.kelompok', 'icon' => 'fa-solid fa-users', 'label' => 'Kelompok Kokurikuler'],
            ]],
        ]],
        ['label' => 'STATUS PENILAIAN', 'items' => [
            ['label' => 'Status Penilaian', 'icon' => 'fa-solid fa-graduation-cap', 'children' => [
                ['route' => 'admin.penilaian.status', 'icon' => 'fa-solid fa-circle-check', 'label' => 'Status Penilaian'],
                ['route' => 'admin.penilaian.statistik', 'icon' => 'fa-solid fa-chart-column', 'label' => 'Statistik Nilai Rapor'],
            ]],
        ]],
        ['label' => 'PERKEMBANGAN NILAI', 'items' => [
            ['label' => 'Perkembangan Nilai', 'icon' => 'fa-solid fa-chart-line', 'children' => [
                ['icon' => 'fa-solid fa-chart-line', 'label' => 'Perkembangan Nilai', 'planned' => true],
                ['icon' => 'fa-solid fa-chart-area', 'label' => 'Grafik Nilai Rapor', 'planned' => true],
            ]],
        ]],
        ['label' => 'TRANSKRIP IJAZAH', 'items' => [
            ['label' => 'Transkrip Ijazah', 'icon' => 'fa-solid fa-file-pdf', 'children' => [
                ['icon' => 'fa-solid fa-file-import', 'label' => 'Import Nomor Ijazah', 'planned' => true],
                ['icon' => 'fa-solid fa-gear', 'label' => 'Setting Transkrip', 'planned' => true],
                ['icon' => 'fa-solid fa-shuffle', 'label' => 'Mapping Mapel', 'planned' => true],
                ['icon' => 'fa-solid fa-pen-to-square', 'label' => 'Input Nilai Transkrip', 'planned' => true],
                ['icon' => 'fa-solid fa-upload', 'label' => 'Import Nilai Transkrip', 'planned' => true],
                ['icon' => 'fa-solid fa-print', 'label' => 'Cetak Transkrip Nilai', 'planned' => true],
            ]],
        ]],
        ['label' => 'CETAK NILAI', 'items' => [
            ['label' => 'Cetak Nilai', 'icon' => 'fa-solid fa-print', 'children' => [
                ['icon' => 'fa-solid fa-table-list', 'label' => 'Leger Rapor', 'planned' => true],
                ['icon' => 'fa-solid fa-file-lines', 'label' => 'Pelengkap Rapor', 'planned' => true],
                ['icon' => 'fa-solid fa-file-signature', 'label' => 'Nilai Rapor', 'planned' => true],
            ]],
        ]],
        ['label' => 'LAINNYA', 'items' => [
            ['icon' => 'fa-solid fa-upload', 'label' => 'Kirim Nilai Ke Dapodik', 'planned' => true],
        ]],
        ['label' => 'PENGATURAN', 'items' => [
            ['route' => 'admin.semester.index', 'icon' => 'fa-solid fa-calendar-alt', 'label' => 'Semester'],
            ['route' => 'admin.kode-akses.index', 'icon' => 'fa-solid fa-key', 'label' => 'Kode & Akses'],
            ['route' => 'admin.modul.index', 'icon' => 'fa-solid fa-box-open', 'label' => 'Modul & Tampilan'],
            ['route' => 'admin.bobot.index', 'icon' => 'fa-solid fa-scale-balanced', 'label' => 'Bobot & Ketuntasan'],
        ]],
        ['label' => 'SISTEM & LOG', 'items' => [
            ['route' => 'admin.sheet.index', 'icon' => 'fa-solid fa-table', 'label' => 'Penyimpanan Data'],
            ['route' => 'admin.backup.index', 'icon' => 'fa-solid fa-database', 'label' => 'Data & Pemulihan'],
            ['route' => 'admin.api-keys.index', 'icon' => 'fa-solid fa-link', 'label' => 'API Keys'],
            ['route' => 'admin.log.index', 'icon' => 'fa-solid fa-clipboard-list', 'label' => 'Log Aktivitas'],
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
                    <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
                    {{ $item['label'] }}
                </summary>
                <div>
                    @foreach($item['children'] as $child)
                        @if($isPlanned($child))
                            <span class="nav-item nav-subitem nav-planned" title="Segera hadir — backend Fase 2">
                                <span class="icon"><i class="{{ $child['icon'] }}"></i></span>
                                {{ $child['label'] }}
                                <span class="badge badge-segera">Segera</span>
                            </span>
                        @else
                            <a href="{{ route($child['route']) }}"
                               class="nav-item nav-subitem {{ $isActive($child['route']) ? 'active' : '' }}">
                                <span class="icon"><i class="{{ $child['icon'] }}"></i></span>
                                {{ $child['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </details>
        @elseif($isPlanned($item))
            <span class="nav-item nav-planned" title="Segera hadir — backend Fase 2">
                <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
                {{ $item['label'] }}
                <span class="badge badge-segera">Segera</span>
            </span>
        @else
            <a href="{{ route($item['route']) }}"
               class="nav-item {{ $isActive($item['route']) ? 'active' : '' }}">
                <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
                {{ $item['label'] }}
                @if(isset($item['badge']))
                    <span class="badge" style="background:{{ ($item['badge_type'] ?? '') === 'ok' ? 'var(--ok)' : '#F59E0B' }};color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;margin-left:auto;white-space:nowrap">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endif
    @endforeach
@endforeach
