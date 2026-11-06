@php
    $user = auth()->user();
    $peranLabel = match ($user?->peran) {
        'super_admin', 'fkkg' => 'Super Administrator (FKKG)',
        'admin' => 'Administrator Sekolah',
        'guru' => 'Guru',
        'kepsek' => 'Kepala Sekolah',
        'tendik' => 'Tenaga Kependidikan / Pegawai',
        'siswa' => 'Siswa',
        'ortu' => 'Orang Tua / Wali',
        default => null,
    };

    $targetDashboard = match ($user?->peran) {
        'super_admin', 'fkkg' => route('superadmin.dashboard'),
        'admin' => route('admin.dashboard'),
        'guru' => route('guru.dashboard'),
        'kepsek' => route('kepsek.dashboard'),
        'tendik' => route('presensi.pegawai.index'),
        'siswa' => route('siswa.dashboard'),
        'ortu' => route('ortu.dashboard'),
        default => url('/dashboard'),
    };

    $pesan = $user
        ? "Anda saat ini masuk sebagai {$peranLabel} ({$user->nama_pengguna}). Halaman yang Anda tuju adalah area khusus peran lain. Silakan beralih ke dasbor atau menu resmi untuk peran Anda."
        : 'Anda tidak memiliki izin untuk membuka halaman ini. Silakan masuk terlebih dahulu dengan akun yang memiliki hak akses.';
@endphp

@include('errors.layout', [
    'kode' => 403,
    'judul' => 'Akses Ditolak (Peran Tidak Sesuai)',
    'pesan' => $pesan,
    'tombol' => $user
        ? ['url' => $targetDashboard, 'label' => 'Ke Dasbor ' . ($peranLabel ?: 'Saya')]
        : ['url' => route('login'), 'label' => 'Masuk'],
])
