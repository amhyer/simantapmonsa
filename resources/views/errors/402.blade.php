@include('errors.layout', [
    'kode' => 402,
    'judul' => 'Masa Berlangganan Berakhir',
    'pesan' => 'Masa aktif langganan atau lisensi hosting sekolah ini telah berakhir. Silakan hubungi Administrator Sekolah atau Super Administrator SIMANTAP untuk perpanjangan layanan.',
    'tombol' => auth()->check() ? ['url' => route('home'), 'label' => 'Kembali ke Beranda'] : ['url' => url('/login'), 'label' => 'Masuk'],
])
