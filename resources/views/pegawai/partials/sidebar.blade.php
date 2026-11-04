<x-sidebar-label>MENU PEGAWAI</x-sidebar-label>
<x-sidebar-link :href="route('presensi.pegawai.index')" icon="lucide-calendar-check" label="Presensi Saya" :active="request()->routeIs('presensi.pegawai.*')" />

<x-sidebar-label>AKUN & KEAMANAN</x-sidebar-label>
<x-sidebar-link :href="route('force-password-change.show')" icon="lucide-key" label="Ganti Kata Sandi" :active="request()->routeIs('force-password-change.*')" />
