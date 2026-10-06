@php
    use App\Models\DapodikSyncLog;
    use App\Models\Siswa;
    use Illuminate\Support\Facades\Cache;

    // Cache dengan key yang berbeda dari sidebar default (setup-only)
    [$lastSyncAt, $syncedCount] = Cache::remember('admin-sidebar-setup-sync:v2', 120, function () {
        $last = DapodikSyncLog::orderByDesc('created_at')->first();
        return [
            $last?->created_at?->toIso8601String(),
            Siswa::whereNotNull('dapodik_id')->count(),
        ];
    });

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
        ['label' => 'PENGGUNA', 'items' => [
            ['route' => 'admin.users.index', 'icon' => 'lucide-users', 'label' => 'Data Pengguna'],
        ]],
        ['label' => 'DAPODIK', 'icon' => 'lucide-database-zap', 'children' => [
            ['route' => 'admin.dapodik.index', 'icon' => 'lucide-refresh-cw', 'label' => 'Ambil Data Dapodik', 'badge' => $lastSyncAt ? \Carbon\Carbon::parse($lastSyncAt)->diffForHumans() : 'Belum sync', 'badge_type' => $lastSyncAt ? 'ok' : 'warn'],
            ['route' => 'admin.dapodik.push.index', 'icon' => 'lucide-upload', 'label' => 'Kirim Nilai Ke Dapodik'],
        ]],
        ['label' => 'DATA REFERENSI', 'items' => [
            ['route' => 'admin.sekolah.index', 'icon' => 'lucide-building-2', 'label' => 'Data Sekolah'],
        ]],
        ['label' => 'PETA KELAS', 'items' => [
            ['route' => 'admin.peta-kelas.daftar', 'icon' => 'lucide-school', 'label' => 'Daftar Peta Kelas'],
        ]],
        ['label' => 'DATA MAPEL & PEMBELAJARAN', 'icon' => 'lucide-book', 'children' => [
            ['route' => 'admin.mapel.index', 'icon' => 'lucide-book', 'label' => 'Data Mapel'],
            ['route' => 'admin.referensi.pembelajaran', 'icon' => 'lucide-calendar-days', 'label' => 'Data Pembelajaran'],
        ]],
        ['label' => 'DATA SEMESTER', 'items' => [
            ['route' => 'admin.semester.index', 'icon' => 'lucide-calendar', 'label' => 'Data Semester'],
        ]],
        ['label' => 'DATA KOKURIKULER', 'icon' => 'lucide-notebook-pen', 'children' => [
            ['route' => 'admin.kokurikuler.tema', 'icon' => 'lucide-target', 'label' => 'Daftar Tema'],
            ['route' => 'admin.kokurikuler.kegiatan', 'icon' => 'lucide-list-checks', 'label' => 'Kegiatan Kokurikuler'],
            ['route' => 'admin.kokurikuler.kelompok', 'icon' => 'lucide-users', 'label' => 'Kelompok Kokurikuler'],
        ]],
        ['label' => 'KODE & AKSES', 'items' => [
            ['route' => 'admin.kode-akses.index', 'icon' => 'lucide-key', 'label' => 'Kode & Akses'],
        ]],
        ['label' => 'MODUL & TAMPILAN', 'items' => [
            ['route' => 'admin.modul.index', 'icon' => 'lucide-box', 'label' => 'Modul & Tampilan'],
        ]],
        ['label' => 'BOBOT & KETUNTASAN', 'items' => [
            ['route' => 'admin.bobot.index', 'icon' => 'lucide-scale', 'label' => 'Bobot & Ketuntasan'],
        ]],
        ['label' => 'SYSTEM', 'icon' => 'lucide-server-cog', 'children' => [
            ['route' => 'admin.sheet.index', 'icon' => 'lucide-table', 'label' => 'Penyimpanan Data'],
            ['route' => 'admin.backup.index', 'icon' => 'lucide-database', 'label' => 'Backup & Restore'],
            ['route' => 'admin.log.index', 'icon' => 'lucide-clipboard-list', 'label' => 'Log Aktivitas'],
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
