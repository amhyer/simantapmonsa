@php
    use App\Models\DapodikSyncLog;
    use App\Models\Siswa;
    $lastSync = DapodikSyncLog::orderByDesc('created_at')->first();
    $syncedCount = Siswa::whereNotNull('dapodik_id')->count();
    $menuAdmin = [
        ['label' => 'SISTEM', 'items' => [
            ['route' => 'admin.dashboard', 'icon' => 'fa-solid fa-gauge-high', 'label' => 'Dasbor Sistem'],
            ['route' => 'admin.users.index', 'icon' => 'fa-solid fa-users', 'label' => 'Kelola Pengguna'],
        ]],
        ['label' => 'PENGATURAN', 'items' => [
            ['route' => 'admin.peta-kelas.index', 'icon' => 'fa-solid fa-school', 'label' => 'Peta Kelas'],
            ['route' => 'admin.semester.index', 'icon' => 'fa-solid fa-calendar-alt', 'label' => 'Semester'],
            ['route' => 'admin.kode-akses.index', 'icon' => 'fa-solid fa-key', 'label' => 'Kode & Akses'],
            ['route' => 'admin.modul.index', 'icon' => 'fa-solid fa-box-open', 'label' => 'Modul & Tampilan'],
            ['route' => 'admin.mapel.index', 'icon' => 'fa-solid fa-book', 'label' => 'Mata Pelajaran'],
            ['route' => 'admin.bobot.index', 'icon' => 'fa-solid fa-scale-balanced', 'label' => 'Bobot & Ketuntasan'],
            ['route' => 'admin.sekolah.index', 'icon' => 'fa-solid fa-building-columns', 'label' => 'Identitas Sekolah'],
        ]],
        ['label' => 'DATA', 'items' => [
            ['route' => 'admin.sheet.index', 'icon' => 'fa-solid fa-table', 'label' => 'Penyimpanan Data'],
            ['route' => 'admin.backup.index', 'icon' => 'fa-solid fa-database', 'label' => 'Data & Pemulihan'],
            ['route' => 'admin.api-keys.index', 'icon' => 'fa-solid fa-link', 'label' => 'API Keys'],
            ['route' => 'admin.dapodik.index', 'icon' => 'fa-solid fa-arrows-rotate', 'label' => 'Import Dapodik', 'badge' => $lastSync ? $lastSync->created_at->diffForHumans() : 'Belum sync', 'badge_type' => $lastSync ? 'ok' : 'warn'],
            ['route' => 'admin.log.index', 'icon' => 'fa-solid fa-clipboard-list', 'label' => 'Log Aktivitas'],
        ]],
    ];
@endphp

@foreach($menuAdmin as $group)
    <div class="nav-label">{{ $group['label'] }}</div>
    @foreach($group['items'] as $item)
        <a href="{{ route($item['route']) }}" 
           class="nav-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
            <span class="icon"><i class="{{ $item['icon'] }}"></i></span>
            {{ $item['label'] }}
            @if(isset($item['badge']))
                <span class="badge" style="background:{{ ($item['badge_type'] ?? '') === 'ok' ? 'var(--ok)' : '#F59E0B' }};color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;margin-left:auto;white-space:nowrap">{{ $item['badge'] }}</span>
            @endif
        </a>
    @endforeach
@endforeach
