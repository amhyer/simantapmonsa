@include('errors.layout', [
    'kode' => 402,
    'judul' => 'Masa Berlangganan Berakhir',
    'pesan' => 'Masa aktif langganan atau lisensi hosting sekolah ini telah berakhir. Data sekolah tetap tersimpan aman — silakan hubungi Administrator Sekolah atau Super Administrator SIMANTAP untuk perpanjangan layanan.',
    'show_logout' => auth()->check(),
    'tombol' => auth()->check() ? null : ['url' => url('/login'), 'label' => 'Masuk'],
])
