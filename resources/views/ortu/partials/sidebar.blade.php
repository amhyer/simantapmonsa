<div class="nav-label">MENU ORANG TUA</div>
<a href="{{ route('ortu.dashboard') }}" 
   class="nav-item {{ request()->routeIs('ortu.dashboard') ? 'active' : '' }}">
    <span class="icon"><i class="fa-solid fa-house"></i></span> Ringkasan
</a>
<a href="{{ route('ortu.kebiasaan.index') }}" 
   class="nav-item {{ request()->routeIs('ortu.kebiasaan.*') ? 'active' : '' }}">
    <span class="icon"><i class="fa-solid fa-house-chimney"></i></span> Isi 7 Kebiasaan
</a>
<a href="{{ route('ortu.nilai.index') }}" 
   class="nav-item {{ request()->routeIs('ortu.nilai.*') ? 'active' : '' }}">
    <span class="icon"><i class="fa-solid fa-chart-line"></i></span> Perkembangan Nilai
</a>
<a href="{{ route('ortu.rekap.index') }}" 
   class="nav-item {{ request()->routeIs('ortu.rekap.*') ? 'active' : '' }}">
    <span class="icon"><i class="fa-solid fa-chart-bar"></i></span> Rekap Kebiasaan
</a>
<a href="{{ route('ortu.kehadiran.index') }}" 
   class="nav-item {{ request()->routeIs('ortu.kehadiran.*') ? 'active' : '' }}">
    <span class="icon"><i class="fa-solid fa-calendar-check"></i></span> Kehadiran Anak
</a>
<a href="{{ route('ortu.catatan.index') }}" 
   class="nav-item {{ request()->routeIs('ortu.catatan.*') ? 'active' : '' }}">
    <span class="icon"><i class="fa-solid fa-sticky-note"></i></span> Catatan Guru
</a>
<a href="{{ route('ortu.laporan.index') }}" 
   class="nav-item {{ request()->routeIs('ortu.laporan.*') ? 'active' : '' }}">
    <span class="icon"><i class="fa-solid fa-file-lines"></i></span> Laporan / Rapor
</a>
