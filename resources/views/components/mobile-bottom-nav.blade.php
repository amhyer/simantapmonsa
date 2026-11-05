@php
    $user = auth()->user();
    if (!$user) return;
    $role = $user->peran;

    $isActive = function ($route) {
        try {
            return request()->routeIs($route, $route . '.*');
        } catch (\Throwable) {
            return false;
        }
    };

    $tabs = [];
    if ($role === 'guru') {
        $tabs = [
            ['title' => 'Beranda', 'route' => route('guru.dashboard'), 'active' => $isActive('guru.dashboard'), 'icon' => 'fas fa-home'],
            ['title' => 'Presensi Siswa', 'route' => route('guru.kehadiran.index'), 'active' => $isActive('guru.kehadiran.*'), 'icon' => 'fas fa-qrcode'],
            ['title' => 'Presensi Saya', 'route' => route('presensi.pegawai.index'), 'active' => $isActive('presensi.pegawai.*'), 'icon' => 'fas fa-user-clock'],
            ['title' => 'Input Nilai', 'route' => route('guru.input-nilai.index'), 'active' => $isActive('guru.input-nilai.*'), 'icon' => 'fas fa-pen-fancy'],
            ['title' => 'Menu', 'route' => '#', 'onclick' => 'toggleSidebar(); return false;', 'active' => false, 'icon' => 'fas fa-bars'],
        ];
    } elseif ($role === 'siswa') {
        $tabs = [
            ['title' => 'Beranda', 'route' => route('siswa.dashboard'), 'active' => $isActive('siswa.dashboard'), 'icon' => 'fas fa-home'],
            ['title' => 'Kartu QR', 'route' => route('siswa.kartu.cetak'), 'active' => $isActive('siswa.kartu.*'), 'icon' => 'fas fa-id-card'],
            ['title' => 'Kuis', 'route' => route('siswa.kuis.index'), 'active' => $isActive('siswa.kuis.*'), 'icon' => 'fas fa-clipboard-list'],
            ['title' => 'Nilaiku', 'route' => route('siswa.nilai.index'), 'active' => $isActive('siswa.nilai.*'), 'icon' => 'fas fa-chart-line'],
            ['title' => 'Profil', 'route' => route('siswa.profil.show'), 'active' => $isActive('siswa.profil.*'), 'icon' => 'fas fa-user'],
        ];
    } elseif ($role === 'kepsek') {
        $tabs = [
            ['title' => 'Dashboard', 'route' => route('kepsek.dashboard'), 'active' => $isActive('kepsek.dashboard'), 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Presensi', 'route' => route('presensi.pegawai.index'), 'active' => $isActive('presensi.pegawai.*'), 'icon' => 'fas fa-user-clock'],
            ['title' => 'Rekap', 'route' => route('kepsek.rekap.index'), 'active' => $isActive('kepsek.rekap.*'), 'icon' => 'fas fa-school'],
            ['title' => 'Pantau', 'route' => route('kepsek.pantau.index'), 'active' => $isActive('kepsek.pantau.*'), 'icon' => 'fas fa-chart-bar'],
            ['title' => 'Menu', 'route' => '#', 'onclick' => 'toggleSidebar(); return false;', 'active' => false, 'icon' => 'fas fa-bars'],
        ];
    } elseif ($role === 'ortu') {
        $tabs = [
            ['title' => 'Ringkasan', 'route' => route('ortu.dashboard'), 'active' => $isActive('ortu.dashboard'), 'icon' => 'fas fa-home'],
            ['title' => '7 Kebiasaan', 'route' => route('ortu.kebiasaan.index'), 'active' => $isActive('ortu.kebiasaan.*'), 'icon' => 'fas fa-check-square'],
            ['title' => 'Kehadiran', 'route' => route('ortu.kehadiran.index'), 'active' => $isActive('ortu.kehadiran.*'), 'icon' => 'fas fa-calendar-check'],
            ['title' => 'Nilai', 'route' => route('ortu.nilai.index'), 'active' => $isActive('ortu.nilai.*'), 'icon' => 'fas fa-award'],
            ['title' => 'Menu', 'route' => '#', 'onclick' => 'toggleSidebar(); return false;', 'active' => false, 'icon' => 'fas fa-bars'],
        ];
    } elseif ($role === 'admin') {
        $tabs = [
            ['title' => 'Dasbor', 'route' => route('admin.dashboard'), 'active' => $isActive('admin.dashboard'), 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Presensi', 'route' => route('admin.presensi.index'), 'active' => $isActive('admin.presensi.*'), 'icon' => 'fas fa-map-marker-alt'],
            ['title' => 'Siswa', 'route' => route('admin.users.siswa'), 'active' => $isActive('admin.users.siswa'), 'icon' => 'fas fa-user-graduate'],
            ['title' => 'CBT', 'route' => route('admin.cbt.sessions.index'), 'active' => $isActive('admin.cbt.*'), 'icon' => 'fas fa-laptop-code'],
            ['title' => 'Menu', 'route' => '#', 'onclick' => 'toggleSidebar(); return false;', 'active' => false, 'icon' => 'fas fa-bars'],
        ];
    }
@endphp

@if(count($tabs) > 0)
    <nav class="mobile-bottom-nav" aria-label="Navigasi Bawah Seluler">
        @foreach($tabs as $tab)
            <a href="{{ $tab['route'] }}" 
               @if(isset($tab['onclick'])) onclick="{{ $tab['onclick'] }}" @endif
               class="mbn-item {{ $tab['active'] ? 'active' : '' }}">
                <div class="mbn-icon"><i class="{{ $tab['icon'] }}"></i></div>
                <div class="mbn-label">{{ $tab['title'] }}</div>
            </a>
        @endforeach
    </nav>
@endif
