@include('errors.layout', [
    'kode' => 419,
    'judul' => 'Sesi Anda berakhir',
    'pesan' => 'Halaman sudah terlalu lama terbuka sehingga token keamanannya kedaluwarsa. Masuk kembali, lalu ulangi tindakan Anda.',
    'tombol' => ['url' => url('/login'), 'label' => 'Masuk kembali'],
])
