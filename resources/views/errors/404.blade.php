@include('errors.layout', [
    'kode' => 404,
    'judul' => 'Halaman tidak ditemukan',
    'pesan' => 'Tautan yang Anda buka mungkin sudah dipindahkan, diganti namanya, atau memang tidak pernah ada.',
    'tombol' => auth()->check() ? ['url' => url('/dashboard'), 'label' => 'Ke dasbor saya'] : ['url' => url('/login'), 'label' => 'Masuk'],
])
