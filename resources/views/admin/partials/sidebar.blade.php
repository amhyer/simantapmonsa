@php
    use App\Models\DapodikSyncLog;
    use App\Models\Siswa;
    $lastSync = DapodikSyncLog::orderByDesc('created_at')->first();
    $syncedCount = Siswa::whereNotNull('dapodik_id')->count();
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
            ['label' => 'Data Referensi', 'icon' => 'fa-solid fa-laptop', 'children' => [
                ['route' => 'admin.sekolah.index', 'icon' => 'fa-solid fa-building-columns', 'label' => 'Data Sekolah'],
                ['route' => 'admin.referensi.guru', 'icon' => 'fa-solid fa-chalkboard-user', 'label' => 'Data Guru'],
                ['route' => 'admin.peta-kelas.index', 'icon' => 'fa-solid fa-school', 'label' => 'Data Kelas'],
                ['route' => 'admin.mapel.index', 'icon' => 'fa-solid fa-book', 'label' => 'Data Mapel'],
                ['route' => 'admin.referensi.pembelajaran', 'icon' => 'fa-solid fa-calendar-days', 'label' => 'Data Pembelajaran'],
            ]],
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
            ['action' => 'logout', 'icon' => 'fa-solid fa-right-from-bracket', 'label' => 'Keluar'],
        ]],
    ];
    $isActive = function ($route) {
        return request()->routeIs($route, $route . '.*');
    };
@endphp

<style>
    .sidebar-nav details.nav-submenu > summary { list-style: none; }
    .sidebar-nav details.nav-submenu > summary::-webkit-details-marker { display: none; }
    .sidebar-nav details.nav-submenu > summary::after {
        content: '\f078';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        font-size: 10px;
        margin-left: auto;
        opacity: .6;
    }
    .sidebar-nav details.nav-submenu[open] > summary::after { content: '\f077'; }
    .sidebar-nav .nav-subitem { padding-left: 46px; font-size: 12.5px; }
</style>

@foreach($menuAdmin as $group)
    <div class="nav-label">{{ $group['label'] }}</div>
    @foreach($group['items'] as $item)
        @if(isset($item['children']))
            @php $childActive = collect($item['children'])->contains(fn($c) => $isActive($c['route'])); @endphp
            <details class="nav-submenu" {{ $childActive ? 'open' : '' }}>
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
        @elseif(isset($item['action']) && $item['action'] === 'logout')
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button type="submit" class="nav-item">
                    <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
                    {{ $item['label'] }}
                </button>
            </form>
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
