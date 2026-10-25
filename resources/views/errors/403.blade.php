@include('errors.layout', [
    'kode' => 403,
    'judul' => 'Akses ditolak',
    'pesan' => 'Anda tidak memiliki izin untuk membuka halaman ini. Jika merasa seharusnya bisa, hubungi administrator sekolah.',
    'tombol' => auth()->check() ? ['url' => url('/dashboard'), 'label' => 'Ke dasbor saya'] : ['url' => url('/login'), 'label' => 'Masuk'],
])
