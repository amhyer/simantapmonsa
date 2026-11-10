@php
    $user = auth()->user();
    $role = $user?->peran ?? 'guest';

    $commandMenu = [];

    if ($role === 'super_admin') {
        $commandMenu = [
            ['title' => 'Dasbor Platform Multi-Sekolah', 'route' => route('superadmin.dashboard'), 'cat' => 'Utama', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Katalog Sekolah Terdaftar (Tenants)', 'route' => route('superadmin.tenants.index'), 'cat' => 'Sekolah', 'icon' => 'fas fa-school'],
            ['title' => 'Tambah Sekolah Baru', 'route' => route('superadmin.tenants.index') . '#tambah', 'cat' => 'Sekolah', 'icon' => 'fas fa-plus-circle'],
            ['title' => 'Akun Pengguna Seluruh Sekolah', 'route' => route('superadmin.users.index'), 'cat' => 'Pengguna', 'icon' => 'fas fa-users'],
        ];
    } elseif ($role === 'admin') {
        $multiSchoolMode = \App\Modules\Tenant\Support\TenantMode::isMultiSchool();
        $commandMenu = [
            ['title' => 'Dasbor Sistem', 'route' => route('admin.dashboard'), 'cat' => 'Utama', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Profil Sekolah', 'route' => route('admin.sekolah.index'), 'cat' => 'Master Data', 'icon' => 'fas fa-school'],
            ['title' => 'Data Siswa & Akun', 'route' => route('admin.users.siswa'), 'cat' => 'Data Guru & Siswa', 'icon' => 'fas fa-user-graduate'],
            ['title' => 'Presensi Pegawai & Lokasi', 'route' => route('admin.presensi.index'), 'cat' => 'Presensi', 'icon' => 'fas fa-map-marker-alt'],
            ['title' => 'Pemetaan QR & Kartu Identitas', 'route' => route('admin.presensi.kartu.index'), 'cat' => 'Presensi', 'icon' => 'fas fa-qrcode'],
            ['title' => 'Riwayat Sinkron Mesin (Bridge)', 'route' => route('admin.presensi.mesin.sinkron'), 'cat' => 'Presensi', 'icon' => 'fas fa-history'],
            ['title' => 'Cetak Kode Akses Login', 'route' => route('admin.kode-akses.index'), 'cat' => 'Akun', 'icon' => 'fas fa-key'],
            ['title' => 'Dapodik & Impor Data', 'route' => route('admin.dapodik.index'), 'cat' => 'Dapodik', 'icon' => 'fas fa-sync'],
            ['title' => 'Status Penilaian per Guru', 'route' => route('admin.penilaian.status'), 'cat' => 'e-Rapor', 'icon' => 'fas fa-check-circle'],
            ['title' => 'Statistik Nilai Rapor', 'route' => route('admin.penilaian.statistik'), 'cat' => 'e-Rapor', 'icon' => 'fas fa-chart-bar'],
            ['title' => 'Cetak Leger Rapor', 'route' => route('admin.cetak.leger'), 'cat' => 'Cetak', 'icon' => 'fas fa-table'],
            ['title' => 'Cetak Nilai Rapor', 'route' => route('admin.cetak.nilai'), 'cat' => 'Cetak', 'icon' => 'fas fa-file-alt'],
            ['title' => 'Cetak Transkrip Ijazah', 'route' => route('admin.transkrip.cetak'), 'cat' => 'Transkrip', 'icon' => 'fas fa-certificate'],
            ['title' => 'Bank Soal CBT', 'route' => route('admin.cbt.banks.index'), 'cat' => 'CBT', 'icon' => 'fas fa-database'],
            ['title' => 'Data Guru & PTK', 'route' => route('admin.referensi.guru'), 'cat' => 'Data Guru & Siswa', 'icon' => 'fas fa-chalkboard-teacher'],
            ['title' => 'Sesi Ujian CBT', 'route' => route('admin.cbt.sessions.index'), 'cat' => 'CBT', 'icon' => 'fas fa-laptop-code'],
        ];
        if (! $multiSchoolMode) {
            $commandMenu[] = ['title' => 'Backup & Restore Database', 'route' => route('admin.backup.index'), 'cat' => 'Sistem', 'icon' => 'fas fa-shield-alt'];
        }
    } elseif ($role === 'guru') {
        $commandMenu = [
            ['title' => 'Dasbor Guru', 'route' => route('guru.dashboard'), 'cat' => 'Utama', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Presensi Saya (Scan Mandiri)', 'route' => route('presensi.pegawai.index'), 'cat' => 'Presensi', 'icon' => 'fas fa-user-check'],
            ['title' => 'Kehadiran Siswa & Roster', 'route' => route('guru.kehadiran.index'), 'cat' => 'Presensi', 'icon' => 'fas fa-clipboard-check'],
            ['title' => 'Input Nilai Cepat', 'route' => route('guru.input-nilai.index'), 'cat' => 'Asesmen', 'icon' => 'fas fa-keyboard'],
            ['title' => 'Input Nilai e-Rapor', 'route' => route('guru.nilai-erapor.index'), 'cat' => 'Asesmen', 'icon' => 'fas fa-file-invoice'],
            ['title' => 'Kuis & Bank Soal', 'route' => route('guru.kuis.index'), 'cat' => 'Asesmen', 'icon' => 'fas fa-question-circle'],
            ['title' => 'Daftar Nilai Siswa', 'route' => route('guru.nilai.index'), 'cat' => 'Asesmen', 'icon' => 'fas fa-calculator'],
            ['title' => 'Materi Ajar', 'route' => route('guru.materi.index'), 'cat' => 'Pembelajaran', 'icon' => 'fas fa-book-open'],
            ['title' => 'Catatan Karakter Siswa', 'route' => route('guru.catatan.index'), 'cat' => 'Pemantauan', 'icon' => 'fas fa-sticky-note'],
            ['title' => 'Pantau 7 Kebiasaan Anak', 'route' => route('guru.kebiasaan.index'), 'cat' => 'Pemantauan', 'icon' => 'fas fa-star'],
            ['title' => 'Laporan & Rapor Kelas', 'route' => route('guru.laporan.index'), 'cat' => 'Pelaporan', 'icon' => 'fas fa-print'],
            ['title' => 'Generate e-Rapor', 'route' => route('guru.erapor.index'), 'cat' => 'Pelaporan', 'icon' => 'fas fa-file-signature'],
        ];
    } elseif ($role === 'siswa') {
        $commandMenu = [
            ['title' => 'Beranda Siswa', 'route' => route('siswa.dashboard'), 'cat' => 'Utama', 'icon' => 'fas fa-home'],
            ['title' => 'Kartu Pelajarku (Cetak / Tampilkan QR)', 'route' => route('siswa.kartu.cetak'), 'cat' => 'Identitas', 'icon' => 'fas fa-id-card'],
            ['title' => 'Nilaiku & Hasil Belajar', 'route' => route('siswa.nilai.index'), 'cat' => 'Akademik', 'icon' => 'fas fa-chart-line'],
            ['title' => 'Kuis & Tugas', 'route' => route('siswa.kuis.index'), 'cat' => 'Akademik', 'icon' => 'fas fa-tasks'],
            ['title' => 'Materi Pembelajaran', 'route' => route('siswa.materi.index'), 'cat' => 'Akademik', 'icon' => 'fas fa-book-reader'],
            ['title' => 'Profil Siswa', 'route' => route('siswa.profil.show'), 'cat' => 'Akun', 'icon' => 'fas fa-user'],
        ];
    } elseif ($role === 'kepsek') {
        $commandMenu = [
            ['title' => 'Dashboard Kepala Sekolah', 'route' => route('kepsek.dashboard'), 'cat' => 'Utama', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Presensi Saya', 'route' => route('presensi.pegawai.index'), 'cat' => 'Presensi', 'icon' => 'fas fa-user-check'],
            ['title' => 'Rekap Statistik Sekolah', 'route' => route('kepsek.rekap.index'), 'cat' => 'Statistik', 'icon' => 'fas fa-chart-pie'],
            ['title' => 'Pantau Aktivitas Guru', 'route' => route('kepsek.pantau.index'), 'cat' => 'Supervisi', 'icon' => 'fas fa-eye'],
            ['title' => 'Rekap 7 Kebiasaan Anak', 'route' => route('kepsek.kebiasaan.index'), 'cat' => 'Karakter', 'icon' => 'fas fa-star'],
        ];
        if (! \App\Modules\Tenant\Support\TenantMode::isMultiSchool()) {
            $commandMenu[] = ['title' => 'Peta Kelas & Rombel', 'route' => route('kepsek.peta-kelas.index'), 'cat' => 'Pemetaan', 'icon' => 'fas fa-map'];
            $commandMenu[] = ['title' => 'Hasil Belajar Siswa', 'route' => route('kepsek.hasil-belajar.index'), 'cat' => 'Akademik', 'icon' => 'fas fa-graduation-cap'];
        }
    } elseif ($role === 'ortu') {
        $commandMenu = [
            ['title' => 'Ringkasan Perkembangan Anak', 'route' => route('ortu.dashboard'), 'cat' => 'Utama', 'icon' => 'fas fa-home'],
            ['title' => 'Isi 7 Kebiasaan Harian Anak', 'route' => route('ortu.kebiasaan.index'), 'cat' => 'Kemitraan', 'icon' => 'fas fa-check-square'],
            ['title' => 'Rekap Kebiasaan Anak', 'route' => route('ortu.rekap.index'), 'cat' => 'Kemitraan', 'icon' => 'fas fa-chart-line'],
            ['title' => 'Kehadiran Anak di Sekolah', 'route' => route('ortu.kehadiran.index'), 'cat' => 'Pemantauan', 'icon' => 'fas fa-calendar-check'],
            ['title' => 'Perkembangan Nilai Anak', 'route' => route('ortu.nilai.index'), 'cat' => 'Pemantauan', 'icon' => 'fas fa-award'],
            ['title' => 'Catatan dari Guru', 'route' => route('ortu.catatan.index'), 'cat' => 'Pemantauan', 'icon' => 'fas fa-sticky-note'],
            ['title' => 'Laporan / Rapor Anak', 'route' => route('ortu.laporan.index'), 'cat' => 'Pelaporan', 'icon' => 'fas fa-file-alt'],
        ];
    } elseif ($role === 'tendik') {
        $commandMenu = [
            ['title' => 'Presensi Mandiri Saya', 'route' => route('presensi.pegawai.index'), 'cat' => 'Presensi', 'icon' => 'fas fa-user-check'],
            ['title' => 'Kartu Identitas Presensi', 'route' => route('presensi.pegawai.kartu'), 'cat' => 'Identitas', 'icon' => 'fas fa-id-card'],
            ['title' => 'Ganti Kata Sandi', 'route' => route('force-password-change.show'), 'cat' => 'Keamanan', 'icon' => 'fas fa-key'],
        ];
    }
@endphp

<div id="commandPaletteModal" class="cp-overlay" style="display:none" onclick="if(event.target === this) closeCommandPalette()">
    <div class="cp-modal" role="dialog" aria-modal="true" aria-label="Command Palette">
        <div class="cp-header">
            <i class="fas fa-search cp-search-icon"></i>
            <input type="text" id="cpSearchInput" class="cp-input" placeholder="Cari menu, halaman, atau aksi cepat... (Ketik atau gunakan panah)" autocomplete="off">
            <button type="button" class="cp-close-btn" onclick="closeCommandPalette()" title="Tutup (Esc)">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="cp-body" id="cpResultsList">
            @forelse($commandMenu as $idx => $cmd)
                <a href="{{ $cmd['route'] }}" class="cp-item" data-index="{{ $idx }}" data-title="{{ strtolower($cmd['title']) }}" data-cat="{{ strtolower($cmd['cat']) }}">
                    <div class="cp-item-icon">
                        <i class="{{ $cmd['icon'] }}"></i>
                    </div>
                    <div class="cp-item-info">
                        <div class="cp-item-title">{{ $cmd['title'] }}</div>
                        <div class="cp-item-cat">{{ $cmd['cat'] }}</div>
                    </div>
                    <span class="cp-item-enter"><i class="fas fa-arrow-right"></i></span>
                </a>
            @empty
                <div class="cp-empty">Tidak ada daftar perintah cepat.</div>
            @endforelse
            <div id="cpNoMatch" class="cp-empty" style="display:none">Menu tidak ditemukan. Coba kata kunci lain.</div>
        </div>
        <div class="cp-footer">
            <span>Gunakan <kbd>↑</kbd> <kbd>↓</kbd> untuk memilih</span>
            <span><kbd>Enter</kbd> untuk membuka</span>
            <span><kbd>Esc</kbd> untuk keluar</span>
        </div>
    </div>
</div>

<script>
    let cpSelectedIndex = -1;

    function openCommandPalette() {
        const modal = document.getElementById('commandPaletteModal');
        const input = document.getElementById('cpSearchInput');
        if (!modal || !input) return;
        modal.style.display = 'flex';
        input.value = '';
        filterCommandPalette('');
        setTimeout(() => input.focus(), 50);
    }

    function closeCommandPalette() {
        const modal = document.getElementById('commandPaletteModal');
        if (modal) modal.style.display = 'none';
    }

    function toggleCommandPalette() {
        const modal = document.getElementById('commandPaletteModal');
        if (!modal) return;
        if (modal.style.display === 'flex') {
            closeCommandPalette();
        } else {
            openCommandPalette();
        }
    }

    function filterCommandPalette(q) {
        const term = q.trim().toLowerCase();
        const items = document.querySelectorAll('.cp-item');
        let matched = 0;
        cpSelectedIndex = -1;

        items.forEach(item => {
            const title = item.getAttribute('data-title') || '';
            const cat = item.getAttribute('data-cat') || '';
            item.classList.remove('active');
            if (term === '' || title.includes(term) || cat.includes(term)) {
                item.style.display = 'flex';
                matched++;
            } else {
                item.style.display = 'none';
            }
        });

        const noMatch = document.getElementById('cpNoMatch');
        if (noMatch) {
            noMatch.style.display = matched === 0 ? 'block' : 'none';
        }

        // Highlight first visible
        const firstVisible = Array.from(items).find(i => i.style.display !== 'none');
        if (firstVisible) {
            firstVisible.classList.add('active');
            cpSelectedIndex = parseInt(firstVisible.getAttribute('data-index') || '0', 10);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('cpSearchInput');
        if (!input) return;

        input.addEventListener('input', function (e) {
            filterCommandPalette(e.target.value);
        });

        input.addEventListener('keydown', function (e) {
            const visibleItems = Array.from(document.querySelectorAll('.cp-item')).filter(i => i.style.display !== 'none');
            if (visibleItems.length === 0) return;

            let currentActiveIndex = visibleItems.findIndex(i => i.classList.contains('active'));

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                let nextIndex = (currentActiveIndex + 1) % visibleItems.length;
                visibleItems.forEach(i => i.classList.remove('active'));
                visibleItems[nextIndex].classList.add('active');
                visibleItems[nextIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                let prevIndex = currentActiveIndex <= 0 ? visibleItems.length - 1 : currentActiveIndex - 1;
                visibleItems.forEach(i => i.classList.remove('active'));
                visibleItems[prevIndex].classList.add('active');
                visibleItems[prevIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const active = visibleItems.find(i => i.classList.contains('active')) || visibleItems[0];
                if (active) {
                    window.location.href = active.getAttribute('href');
                }
            }
        });

        window.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                toggleCommandPalette();
            } else if (e.key === 'Escape') {
                closeCommandPalette();
            }
        });
    });
</script>
