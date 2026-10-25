@include('errors.layout', [
    'kode' => 500,
    'judul' => 'Terjadi kesalahan pada server',
    'pesan' => 'Permintaan Anda tidak dapat diselesaikan. Kejadian ini sudah tercatat; administrator sekolah dapat memeriksa log aplikasi.',
])
