@include('errors.layout', [
    'kode' => 429,
    'judul' => 'Terlalu banyak permintaan',
    'pesan' => 'Anda mengirim permintaan terlalu sering dalam waktu singkat. Tunggu sebentar, lalu coba lagi.',
])
